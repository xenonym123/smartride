<?php
// JSON: is this QR Ph reload paid yet? The pay page asks every 3 seconds.
// With a real PayMongo QR we ask PayMongo for the payment intent's status. When it says
// "succeeded", the wallet is credited (once). The webhook can do the same thing first.
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/paymongo.php';
require_once '../models/wallet_model.php';
require_once '../models/payment_model.php';

session_start();
require_role_json(['passenger']);

$wallet = get_my_wallet($conn, (int) $_SESSION['user_id']);
$payment = get_payment($conn, (int) input('id'), (int) $wallet['wallet_id']);
if (!$payment) {
    json_out(['status' => 'missing'], 404);
}

if ($payment['status'] === 'pending' && $payment['provider'] === 'paymongo') {
    try {
        $status = paymongo_intent_status($payment['intent_id']);
        if ($status === 'succeeded') {
            complete_payment($conn, $payment);
        }
    } catch (Exception $ex) {
        // PayMongo didn't answer this time. The page just asks again in a few seconds.
        error_log('SmartRide PayMongo status: ' . $ex->getMessage());
    }
}
if ($payment['status'] === 'pending') {
    expire_payment_if_old($conn, $payment['payment_id']);
}

$payment = get_payment($conn, (int) $payment['payment_id'], (int) $wallet['wallet_id']);
$wallet = get_my_wallet($conn, (int) $_SESSION['user_id']);
json_out([
    'status'  => $payment['status'],
    'balance' => peso($wallet['balance']),
]);
