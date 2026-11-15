<?php
// payments table: wallet reloads through Maya Checkout, and cash reloads (partner store or
// kiosk) that wait for an admin to confirm the cash was received.

require_once __DIR__ . '/wallet_model.php';
require_once __DIR__ . '/notification_model.php';
require_once __DIR__ . '/log_model.php';
require_once __DIR__ . '/../includes/maya.php';

// A Maya checkout page stays open for 1 hour.
// $reference is our own random number (SR-...) that Maya keeps with the payment.
function create_payment($conn, $wallet_id, $amount, $provider, $reference)
{
    $stmt = $conn->prepare("INSERT INTO payments (wallet_id, amount, provider, reference_no, status, created_at, expires_at)
                            VALUES (?, ?, ?, ?, 'pending', NOW(), NOW() + INTERVAL 1 HOUR)");
    $stmt->bind_param("idss", $wallet_id, $amount, $provider, $reference);
    $stmt->execute();
    return $conn->insert_id;
}

// Saves Maya's checkout id and payment page once the checkout is made.
function set_payment_checkout($conn, $payment_id, $checkout_id, $checkout_url)
{
    $stmt = $conn->prepare("UPDATE payments SET checkout_id = ?, checkout_url = ? WHERE payment_id = ?");
    $stmt->bind_param("ssi", $checkout_id, $checkout_url, $payment_id);
    $stmt->execute();
}

// One payment, only if it belongs to this wallet (so nobody can open someone else's).
function get_payment($conn, $payment_id, $wallet_id)
{
    $stmt = $conn->prepare("SELECT * FROM payments WHERE payment_id = ? AND wallet_id = ?");
    $stmt->bind_param("ii", $payment_id, $wallet_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Used by the webhook, which only knows the reference number.
function get_payment_by_reference($conn, $reference)
{
    $stmt = $conn->prepare("SELECT * FROM payments WHERE reference_no = ?");
    $stmt->bind_param("s", $reference);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Asks Maya about a pending payment and records the answer. Returns the new status.
// Used by the pay page, the status check and the webhook, so they all agree.
function sync_payment_with_maya($conn, $payment)
{
    if ($payment['status'] !== 'pending' || $payment['provider'] !== 'maya') {
        return $payment['status'];
    }
    $result = maya_check_payment($payment['reference_no'], (float) $payment['amount']);
    if ($result === 'paid') {
        complete_payment($conn, $payment);
        return 'paid';
    }
    if ($result === 'expired' || $result === 'cancelled') {
        set_payment_status($conn, $payment['payment_id'], $result);
        return $result;
    }
    return 'pending';
}

// pending -> failed / expired / cancelled. A paid payment never changes again.
function set_payment_status($conn, $payment_id, $status)
{
    $stmt = $conn->prepare("UPDATE payments SET status = ? WHERE payment_id = ? AND status = 'pending'");
    $stmt->bind_param("si", $status, $payment_id);
    $stmt->execute();
}

// Marks a pending payment as expired once its hour is up. Returns true if it just expired.
function expire_payment_if_old($conn, $payment_id)
{
    $stmt = $conn->prepare("UPDATE payments SET status = 'expired' WHERE payment_id = ? AND status = 'pending' AND expires_at < NOW()");
    $stmt->bind_param("i", $payment_id);
    $stmt->execute();
    return $stmt->affected_rows === 1;
}

// The payment went through: mark it paid and put the money in the wallet.
// The UPDATE only works while the payment is still 'pending', so if the webhook and
// the status check both arrive, the wallet is still credited only once.
function complete_payment($conn, $payment)
{
    $payment_id = (int) $payment['payment_id'];
    $wallet_id = (int) $payment['wallet_id'];
    $amount = (float) $payment['amount'];

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE payments SET status = 'paid', paid_at = NOW() WHERE payment_id = ? AND status = 'pending'");
        $stmt->bind_param("i", $payment_id);
        $stmt->execute();
        if ($stmt->affected_rows !== 1) {
            $conn->rollback();
            return false; // already paid (or cancelled / expired)
        }
        $method = in_array($payment['provider'], ['store', 'kiosk'], true) ? $payment['provider'] : 'maya';
        add_reload($conn, $wallet_id, $amount, $method);
        credit_wallet($conn, $wallet_id, $amount);
        $conn->commit();
    } catch (Exception $ex) {
        $conn->rollback();
        throw $ex;
    }

    $wallet = get_wallet($conn, $wallet_id);
    $how = ['maya' => 'Maya Checkout', 'demo' => 'Maya Checkout', 'store' => 'cash at a partner store', 'kiosk' => 'cash at a kiosk'];
    add_log($conn, (int) $wallet['user_id'], 'reload', 'Reloaded ' . peso($amount) . ' via ' . $how[$payment['provider']]);
    add_notification($conn, (int) $wallet['user_id'], 'reload', 'Reload successful',
        peso($amount) . ' added. Your balance is now ' . peso($wallet['balance']) . '.', 'passenger_reload.php');
    return true;
}


// ---------- Cash reloads (partner store / kiosk) ----------
// The passenger asks for a cash reload and gets a short reference to show at the counter.
// Nothing is added to the wallet until an admin confirms the cash came in (approve_cash_reload).
// A request that nobody confirms within 24 hours expires.

function create_cash_request($conn, $wallet_id, $amount, $method)
{
    $reference = 'C-' . strtoupper(bin2hex(random_bytes(4)));   // e.g. C-4F2A91BE, easy to read out
    $stmt = $conn->prepare("INSERT INTO payments (wallet_id, amount, provider, reference_no, status, created_at, expires_at)
                            VALUES (?, ?, ?, ?, 'pending', NOW(), NOW() + INTERVAL 24 HOUR)");
    $stmt->bind_param("idss", $wallet_id, $amount, $method, $reference);
    $stmt->execute();
    return $reference;
}

// Old cash requests nobody confirmed: pending -> expired.
function expire_old_cash_requests($conn)
{
    $conn->query("UPDATE payments SET status = 'expired'
                  WHERE provider IN ('store', 'kiosk') AND status = 'pending' AND expires_at < NOW()");
}

// Every cash request still waiting, oldest first, with who asked (for the admin).
function get_waiting_cash_requests($conn)
{
    expire_old_cash_requests($conn);
    $stmt = $conn->prepare("SELECT p.*, u.full_name, u.username FROM payments p
                            JOIN wallets w ON w.wallet_id = p.wallet_id
                            JOIN users u ON u.user_id = w.user_id
                            WHERE p.provider IN ('store', 'kiosk') AND p.status = 'pending'
                            ORDER BY p.payment_id");
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// One passenger's own waiting cash requests (shown on their Reload page).
function get_my_cash_requests($conn, $wallet_id)
{
    expire_old_cash_requests($conn);
    $stmt = $conn->prepare("SELECT * FROM payments WHERE wallet_id = ? AND provider IN ('store', 'kiosk') AND status = 'pending'
                            ORDER BY payment_id DESC");
    $stmt->bind_param("i", $wallet_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function get_cash_request($conn, $payment_id)
{
    $stmt = $conn->prepare("SELECT * FROM payments WHERE payment_id = ? AND provider IN ('store', 'kiosk')");
    $stmt->bind_param("i", $payment_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}
