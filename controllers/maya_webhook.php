<?php
// Maya calls this URL when a payment succeeds, fails, expires or is cancelled.
// Maya's webhooks are not signed, so we never trust what the message says.
// We only take the reference number from it, then ask Maya ourselves with the
// SECRET key (sync_payment_with_maya). A fake message can't credit a wallet.
//
// Set it up in Maya Business Manager > Settings > Webhooks (PAYMENT_SUCCESS,
// PAYMENT_FAILED, PAYMENT_EXPIRED, PAYMENT_CANCELLED). Maya needs a public https URL,
// so on XAMPP you'd use a tunnel like ngrok. Without a webhook the pay page still works:
// it asks Maya itself.
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/maya.php';
require_once '../models/payment_model.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false], 405);
}

$event = json_decode(file_get_contents('php://input'), true);
$reference = is_array($event) ? (string) ($event['requestReferenceNumber'] ?? '') : '';

$payment = $reference !== '' ? get_payment_by_reference($conn, $reference) : null;
if ($payment) {
    try {
        sync_payment_with_maya($conn, $payment); // asks Maya, credits only on PAYMENT_SUCCESS
    } catch (Exception $ex) {
        error_log('SmartRide Maya webhook: ' . $ex->getMessage());
    }
}

// Answer 200 so Maya doesn't keep resending it.
json_out(['ok' => true]);
