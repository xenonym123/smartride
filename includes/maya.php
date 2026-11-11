<?php
// Talks to Maya Checkout (sandbox), the payment gateway for wallet reloads.
// Sandbox = test only: test cards and test Maya wallets, no real money moves.
//
// The flow for one reload:
//   1. POST /checkout/v1/checkouts (PUBLIC key)  "Collect ₱100 for reference SR-..."
//      Maya answers with a checkoutId and a redirectUrl (Maya's payment page).
//   2. The passenger pays on Maya's page with a test card or test Maya wallet.
//   3. Maya sends them back to passenger_pay.php (and can call maya_webhook.php).
//   4. We never trust the redirect or the webhook by itself. We ask Maya:
//      GET /payments/v1/payment-rrns/{reference} (SECRET key). Only PAYMENT_SUCCESS
//      for the right amount puts money in the wallet.
//
// Docs: https://developers.maya.ph/reference/accept-one-time-payment-using-maya-checkout

require_once __DIR__ . '/http.php';

// True when there are no Maya keys, so SmartRide fakes the payment for the demo.
function maya_demo_mode()
{
    return MAYA_PUBLIC_KEY === '' || MAYA_SECRET_KEY === '';
}

// One request to Maya. Maya uses Basic auth: the key is the username, the password is empty.
function maya_request($method, $path, $key, $body = null)
{
    return api_request($method, MAYA_API . $path, ['Authorization: Basic ' . base64_encode($key . ':')], $body);
}

// Step 1. Returns Maya's checkoutId and the page to send the passenger to.
function maya_create_checkout($reference, $amount, $name, $email, $payment_id)
{
    $parts = explode(' ', trim($name), 2);
    $back = 'passenger_pay.php?id=' . (int) $payment_id . '&result=';
    $value = number_format($amount, 2, '.', '');

    $checkout = maya_request('POST', '/checkout/v1/checkouts', MAYA_PUBLIC_KEY, [
        'totalAmount' => ['value' => (float) $value, 'currency' => 'PHP'],
        'buyer' => [
            'firstName' => $parts[0],
            'lastName'  => $parts[1] ?? $parts[0],
            'contact'   => ['email' => $email],
        ],
        'items' => [[
            'name'        => 'SmartRide wallet reload',
            'quantity'    => 1,
            'code'        => 'RELOAD',
            'totalAmount' => ['value' => (float) $value],
        ]],
        'redirectUrl' => [
            'success' => app_url($back . 'success'),
            'failure' => app_url($back . 'failure'),
            'cancel'  => app_url($back . 'cancel'),
        ],
        'requestReferenceNumber' => $reference,
    ]);

    if (empty($checkout['checkoutId']) || empty($checkout['redirectUrl'])) {
        throw new Exception('Maya did not send a checkout page.');
    }
    return ['checkout_id' => $checkout['checkoutId'], 'url' => $checkout['redirectUrl']];
}

// Step 4. Asks Maya what happened to the payments for this reference number.
// Returns 'paid', 'expired', 'cancelled' or 'pending' (still waiting, or a failed try
// the passenger can retry on the same page).
function maya_check_payment($reference, $amount)
{
    $answer = maya_request('GET', '/payments/v1/payment-rrns/' . rawurlencode($reference), MAYA_SECRET_KEY);
    // Maya sends a list of payment attempts. Make one payment look like a list too.
    $attempts = isset($answer['id']) ? [$answer] : $answer;

    $result = 'pending';
    foreach ($attempts as $attempt) {
        $status = $attempt['status'] ?? ($attempt['paymentStatus'] ?? '');
        $paid_amount = (float) ($attempt['amount'] ?? ($attempt['totalAmount']['value'] ?? 0));

        if ($status === 'PAYMENT_SUCCESS') {
            // Only count it if Maya collected the amount we asked for.
            if (abs($paid_amount - $amount) < 0.01) {
                return 'paid';
            }
            error_log('SmartRide Maya: amount mismatch for ' . $reference);
        } elseif ($status === 'PAYMENT_EXPIRED') {
            $result = 'expired';
        } elseif ($status === 'PAYMENT_CANCELLED') {
            $result = 'cancelled';
        }
    }
    return $result;
}
