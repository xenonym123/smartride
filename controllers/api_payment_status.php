<?php
// JSON: is this Maya reload paid yet? The pay page asks every 4 seconds.
// We ask Maya with the SECRET key. When Maya says PAYMENT_SUCCESS for the right
// amount, the wallet is credited (once). The webhook can do the same thing first.
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/maya.php';
require_once '../models/wallet_model.php';
require_once '../models/payment_model.php';

session_start();
require_role_json(['passenger']);

$wallet = get_my_wallet($conn, (int) $_SESSION['user_id']);
$payment = get_payment($conn, (int) input('id'), (int) $wallet['wallet_id']);
if (!$payment) {
    json_out(['status' => 'missing'], 404);
}

try {
    sync_payment_with_maya($conn, $payment);
} catch (Exception $ex) {
    // Maya didn't answer this time. The page just asks again in a few seconds.
    error_log('SmartRide Maya status: ' . $ex->getMessage());
}
expire_payment_if_old($conn, $payment['payment_id']);

$payment = get_payment($conn, (int) $payment['payment_id'], (int) $wallet['wallet_id']);
$wallet = get_my_wallet($conn, (int) $_SESSION['user_id']);
json_out([
    'status'  => $payment['status'],
    'balance' => peso($wallet['balance']),
]);
