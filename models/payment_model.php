<?php
// payments table: wallet reloads through Maya Checkout (or the demo stand-in).

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
        add_reload($conn, $wallet_id, $amount, 'maya');
        credit_wallet($conn, $wallet_id, $amount);
        $conn->commit();
    } catch (Exception $ex) {
        $conn->rollback();
        throw $ex;
    }

    $wallet = get_wallet($conn, $wallet_id);
    $how = $payment['provider'] === 'demo' ? ' (demo)' : '';
    add_log($conn, (int) $wallet['user_id'], 'reload', 'Reloaded ' . peso($amount) . ' via Maya Checkout' . $how);
    add_notification($conn, (int) $wallet['user_id'], 'reload', 'Reload successful',
        peso($amount) . ' added. Your balance is now ' . peso($wallet['balance']) . '.', 'passenger_reload.php');
    return true;
}
