<?php
// Passenger: the result of a Maya Checkout reload.
// Maya sends the passenger back here (?result=success / failure / cancel). We don't
// believe that by itself: we ask Maya for the real status before crediting anything.
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/maya.php';
require_once '../models/wallet_model.php';
require_once '../models/payment_model.php';

session_start();
require_role('passenger');

$wallet = get_my_wallet($conn, (int) $_SESSION['user_id']);
$payment = get_payment($conn, (int) input('id'), (int) $wallet['wallet_id']);
if (!$payment) {
    flash('error', "That payment doesn't exist.");
    go('passenger_reload.php');
}
$page = 'passenger_pay.php?id=' . $payment['payment_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf($page);

    if (input('action') === 'cancel') {
        set_payment_status($conn, $payment['payment_id'], 'cancelled');
        flash('success', 'Reload cancelled. Nothing was charged.');
        go('passenger_reload.php');
    }

    // Demo mode only: pretend Maya said PAYMENT_SUCCESS. Never allowed for a real Maya payment.
    if (input('action') === 'simulate' && $payment['provider'] === 'demo') {
        complete_payment($conn, $payment);
        flash('success', peso($payment['amount']) . ' added to your wallet.');
        go('passenger_reload.php');
    }
    go($page);
}

// Back from Maya: ask Maya what really happened.
if ($payment['status'] === 'pending' && $payment['provider'] === 'maya') {
    try {
        sync_payment_with_maya($conn, $payment);
    } catch (Exception $ex) {
        error_log('SmartRide Maya status: ' . $ex->getMessage()); // the page checks again by itself
    }
}
expire_payment_if_old($conn, $payment['payment_id']);
$payment = get_payment($conn, (int) $payment['payment_id'], (int) $wallet['wallet_id']);

$result = input('result');            // what Maya's redirect said: success / failure / cancel
$seconds_left = max(0, strtotime($payment['expires_at']) - time());

$title = 'Maya Checkout';
$current_page = 'passenger_reload.php';
include '../views/passenger/pay.php';
