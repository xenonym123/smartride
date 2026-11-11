<?php
// Passenger: Reload Wallet.
//   Maya   -> makes a Maya Checkout and sends the passenger to Maya's payment page
//   Store / kiosk (cash) -> approved right away in the demo
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/maya.php';
require_once '../models/wallet_model.php';
require_once '../models/payment_model.php';
require_once '../models/notification_model.php';
require_once '../models/user_model.php';

session_start();
require_role('passenger');

$user_id = (int) $_SESSION['user_id'];
$wallet = get_my_wallet($conn, $user_id);
$methods = reload_methods();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Quick reload on Home posts here too, so go back to wherever the form was.
    $back = (strpos($_SERVER['HTTP_REFERER'] ?? '', 'passenger_home.php') !== false) ? 'passenger_home.php' : 'passenger_reload.php';
    require_csrf($back);

    $amount = round((float) input('amount'), 2);
    $method = input('method', 'maya');

    // Check the input on the server too. The min/max in the HTML can be removed by anyone.
    if (!isset($methods[$method])) {
        flash('error', 'Pick how you are paying for the reload.');
        go($back);
    }
    if ($amount < RELOAD_MIN || $amount > RELOAD_MAX) {
        flash('error', 'Reload between ' . peso(RELOAD_MIN, 0) . ' and ' . peso(RELOAD_MAX, 0) . '.');
        go($back);
    }
    if ($wallet['status'] !== 'active') {
        flash('error', 'Unfreeze your QR first, then reload.');
        go($back);
    }

    if ($method === 'maya') {
        // ---- Maya Checkout ----
        $reference = 'SR-' . bin2hex(random_bytes(8)); // our own reference number for Maya
        if (maya_demo_mode()) {
            $payment_id = create_payment($conn, $wallet['wallet_id'], $amount, 'demo', $reference);
            go('passenger_pay.php?id=' . $payment_id);
        }
        $payment_id = create_payment($conn, $wallet['wallet_id'], $amount, 'maya', $reference);
        $me = get_user_by_id($conn, $user_id);
        try {
            $checkout = maya_create_checkout($reference, $amount, $me['full_name'], $me['email'], $payment_id);
        } catch (Exception $ex) {
            error_log('SmartRide Maya: ' . $ex->getMessage());
            set_payment_status($conn, $payment_id, 'failed');
            flash('error', "We couldn't reach Maya just now. Try again in a minute, or reload with cash at a partner store.");
            go($back);
        }
        set_payment_checkout($conn, $payment_id, $checkout['checkout_id'], $checkout['url']);
        // Off to Maya's payment page. Maya sends the passenger back to passenger_pay.php.
        header('Location: ' . $checkout['url']);
        exit;
    }

    // ---- Cash at a partner store or kiosk ----
    $conn->begin_transaction();
    try {
        add_reload($conn, $wallet['wallet_id'], $amount, $method);
        credit_wallet($conn, $wallet['wallet_id'], $amount);
        $conn->commit();
    } catch (Exception $ex) {
        $conn->rollback();
        throw $ex;
    }
    $wallet = get_my_wallet($conn, $user_id);
    add_log($conn, $user_id, 'reload', 'Reloaded ' . peso($amount) . ' via ' . $methods[$method]);
    add_notification($conn, $user_id, 'reload', 'Reload successful',
        peso($amount) . ' added. Your balance is now ' . peso($wallet['balance']) . '.', 'passenger_reload.php');
    flash('success', peso($amount) . ' added to your wallet.');
    go($back);
}

$history = get_reloads($conn, $wallet['wallet_id'], 50);
$demo_mode = maya_demo_mode();

$title = 'Reload wallet';
$current_page = 'passenger_reload.php';
include '../views/passenger/reload.php';
