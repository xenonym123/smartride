<?php
// payments table: QR Ph reloads through PayMongo (or the demo stand-in).

require_once __DIR__ . '/wallet_model.php';
require_once __DIR__ . '/notification_model.php';
require_once __DIR__ . '/log_model.php';

// QR Ph codes expire after 30 minutes on PayMongo's side.
function create_payment($conn, $wallet_id, $amount, $provider, $intent_id, $qr_image, $test_url)
{
    $stmt = $conn->prepare("INSERT INTO payments (wallet_id, amount, provider, intent_id, qr_image, test_url, status, created_at, expires_at)
                            VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW() + INTERVAL 30 MINUTE)");
    $stmt->bind_param("idssss", $wallet_id, $amount, $provider, $intent_id, $qr_image, $test_url);
    $stmt->execute();
    return $conn->insert_id;
}

// One payment, only if it belongs to this wallet (so nobody can open someone else's).
function get_payment($conn, $payment_id, $wallet_id)
{
    $stmt = $conn->prepare("SELECT * FROM payments WHERE payment_id = ? AND wallet_id = ?");
    $stmt->bind_param("ii", $payment_id, $wallet_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Used by the webhook, which only knows PayMongo's payment intent id.
function get_payment_by_intent($conn, $intent_id)
{
    $stmt = $conn->prepare("SELECT * FROM payments WHERE intent_id = ?");
    $stmt->bind_param("s", $intent_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// pending -> failed / expired / cancelled. A paid payment never changes again.
function set_payment_status($conn, $payment_id, $status)
{
    $stmt = $conn->prepare("UPDATE payments SET status = ? WHERE payment_id = ? AND status = 'pending'");
    $stmt->bind_param("si", $status, $payment_id);
    $stmt->execute();
}

// Marks a pending QR as expired once its 30 minutes are up. Returns true if it just expired.
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
        add_reload($conn, $wallet_id, $amount, 'qrph');
        credit_wallet($conn, $wallet_id, $amount);
        $conn->commit();
    } catch (Exception $ex) {
        $conn->rollback();
        throw $ex;
    }

    $wallet = get_wallet($conn, $wallet_id);
    $how = $payment['provider'] === 'demo' ? ' (demo)' : '';
    add_log($conn, (int) $wallet['user_id'], 'reload', 'Reloaded ' . peso($amount) . ' via QR Ph / PayMongo' . $how);
    add_notification($conn, (int) $wallet['user_id'], 'reload', 'Reload successful',
        peso($amount) . ' added. Your balance is now ' . peso($wallet['balance']) . '.', 'passenger_reload.php');
    return true;
}
