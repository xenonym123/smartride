<?php
// PayMongo calls this URL when a QR Ph payment is paid, fails or expires.
// There's no session here (PayMongo isn't signed in), so instead we check the
// Paymongo-Signature header. Without the right signature, nothing happens.
//
// Set it up in the PayMongo dashboard > Developers > Webhooks with the events
// payment.paid, payment.failed and qrph.expired. PayMongo needs a public https URL,
// so on XAMPP you'd use a tunnel like ngrok. Without a webhook, the pay page still
// works: it checks the payment status itself.
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/paymongo.php';
require_once '../models/payment_model.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_out(['ok' => false], 405);
}

$raw = file_get_contents('php://input');      // the body exactly as PayMongo sent it
$event = json_decode($raw, true);
$attributes = $event['data']['attributes'] ?? [];
$livemode = !empty($attributes['livemode']);

if (!paymongo_check_signature($raw, $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '', $livemode)) {
    error_log('SmartRide webhook: bad or missing signature');
    json_out(['ok' => false], 401);
}

$type = $attributes['type'] ?? '';
$resource = $attributes['data']['attributes'] ?? [];
// payment.* events carry the payment intent id. qrph.expired may name it differently.
$intent_id = $resource['payment_intent_id'] ?? ($resource['payment_intent']['id'] ?? '');

$payment = $intent_id !== '' ? get_payment_by_intent($conn, $intent_id) : null;
if ($payment) {
    if ($type === 'payment.paid') {
        complete_payment($conn, $payment);
    } elseif ($type === 'payment.failed') {
        set_payment_status($conn, $payment['payment_id'], 'failed');
    } elseif ($type === 'qrph.expired') {
        set_payment_status($conn, $payment['payment_id'], 'expired');
    }
}

// Always answer 200 to a real PayMongo event, or PayMongo keeps retrying it.
json_out(['ok' => true]);
