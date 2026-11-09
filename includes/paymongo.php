<?php
// Talks to the PayMongo API (QR Ph). Uses cURL, which XAMPP turns on by default.
//
// The flow for one reload:
//   1. POST /payment_intents          "I want to collect ₱100, by QR Ph"
//   2. POST /payment_methods          a QR Ph payment method with the passenger's name and email
//   3. POST /payment_intents/{id}/attach   joins the two. PayMongo answers with the QR image.
//   4. The passenger scans the QR with GCash, Maya or their bank app and pays.
//   5. We find out it's paid from the webhook (payment.paid), or by asking
//      GET /payment_intents/{id} until its status is "succeeded".
//
// Docs: https://developers.paymongo.com/docs/qr-ph-api

// True when there's no secret key, so SmartRide fakes the payment for the demo.
function paymongo_demo_mode()
{
    return PAYMONGO_SECRET_KEY === '';
}

// Sends one request to PayMongo and returns the decoded JSON.
// Throws an exception if PayMongo can't be reached or says no.
function paymongo_request($method, $path, $body = null)
{
    $ch = curl_init(PAYMONGO_API . $path);
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        // Basic auth: the secret key is the username, the password is empty.
        'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':'),
    ];
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    if (CURL_CA_FILE !== '') {
        curl_setopt($ch, CURLOPT_CAINFO, CURL_CA_FILE);
    }
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['data' => ['attributes' => $body]]));
    }

    $raw = curl_exec($ch);
    if ($raw === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception('Could not reach PayMongo: ' . $error);
    }
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($raw, true);
    if ($status >= 400 || !is_array($json)) {
        $detail = $json['errors'][0]['detail'] ?? ('HTTP ' . $status);
        throw new Exception('PayMongo said no: ' . $detail);
    }
    return $json;
}

// Steps 1 to 3. Returns the payment intent id, the QR image and (test mode) a test link.
function paymongo_create_qr($amount, $name, $email, $description)
{
    $intent = paymongo_request('POST', '/payment_intents', [
        'amount'                 => (int) round($amount * 100), // PayMongo counts in centavos
        'currency'               => 'PHP',
        'payment_method_allowed' => ['qrph'],
        'capture_type'           => 'automatic',
        'description'            => $description,
    ]);
    $intent_id = $intent['data']['id'];

    $method = paymongo_request('POST', '/payment_methods', [
        'type'    => 'qrph',
        'billing' => ['name' => $name, 'email' => $email],
    ]);

    $attached = paymongo_request('POST', '/payment_intents/' . $intent_id . '/attach', [
        'payment_method' => $method['data']['id'],
        'client_key'     => $intent['data']['attributes']['client_key'],
    ]);

    $next = $attached['data']['attributes']['next_action'] ?? [];
    $image = $next['code']['image_url'] ?? '';
    if (strpos($image, 'data:image/') !== 0) {
        throw new Exception('PayMongo did not send a QR image.');
    }
    return [
        'intent_id' => $intent_id,
        'qr_image'  => $image,
        'test_url'  => $next['code']['test_url'] ?? ($next['test_url'] ?? null), // only in test mode
    ];
}

// Step 5 (the backup way): asks PayMongo for the status of a payment intent.
// "succeeded" means paid.
function paymongo_intent_status($intent_id)
{
    $intent = paymongo_request('GET', '/payment_intents/' . rawurlencode($intent_id));
    return $intent['data']['attributes']['status'] ?? 'unknown';
}

// Checks the Paymongo-Signature header on a webhook, so a fake request can't credit a wallet.
// Header looks like: t=1700000000,te=<test signature>,li=<live signature>
// Signature = HMAC-SHA256 of "t.raw_body" with the webhook secret.
function paymongo_check_signature($raw_body, $header, $livemode)
{
    if (PAYMONGO_WEBHOOK_SECRET === '' || $header === '') {
        return false;
    }
    $parts = [];
    foreach (explode(',', $header) as $piece) {
        $kv = explode('=', trim($piece), 2);
        if (count($kv) === 2) {
            $parts[$kv[0]] = $kv[1];
        }
    }
    if (empty($parts['t'])) {
        return false;
    }
    // Ignore old requests (more than 5 minutes) so a captured one can't be replayed later.
    if (abs(time() - (int) $parts['t']) > 300) {
        return false;
    }
    $expected = hash_hmac('sha256', $parts['t'] . '.' . $raw_body, PAYMONGO_WEBHOOK_SECRET);
    $given = $livemode ? ($parts['li'] ?? '') : ($parts['te'] ?? '');
    return $given !== '' && hash_equals($expected, $given);
}
