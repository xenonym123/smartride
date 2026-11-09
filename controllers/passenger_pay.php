<?php
// Passenger: Pay with QR Ph. Shows the PayMongo QR for one reload and waits for it to be paid.
// The page asks api_payment_status.php every 3 seconds whether the money came in.
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/paymongo.php';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('passenger_pay.php?id=' . $payment['payment_id']);

    if (input('action') === 'cancel') {
        set_payment_status($conn, $payment['payment_id'], 'cancelled');
        flash('success', 'Reload cancelled. Nothing was charged.');
        go('passenger_reload.php');
    }

    // Demo mode only: pretend the passenger paid. Never allowed with a real PayMongo QR.
    if (input('action') === 'simulate' && $payment['provider'] === 'demo') {
        complete_payment($conn, $payment);
        flash('success', peso($payment['amount']) . ' added to your wallet.');
        go('passenger_reload.php');
    }
    go('passenger_pay.php?id=' . $payment['payment_id']);
}

expire_payment_if_old($conn, $payment['payment_id']);
$payment = get_payment($conn, (int) $payment['payment_id'], (int) $wallet['wallet_id']);
$seconds_left = max(0, strtotime($payment['expires_at']) - time());

$title = 'Pay with QR Ph';
$current_page = 'passenger_reload.php';
include '../views/passenger/pay.php';
