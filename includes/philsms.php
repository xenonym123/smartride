<?php
// PhilSMS API: text messages to Philippine mobile numbers.
//
// SmartRide sends a text when:
//   1. a passenger's fare is paid (the driver scanned their QR), and
//   2. their jeepney is near the landmark where they get off.
// Only to passengers who turned on "Text me" (Profile > Alerts), because every text uses credits.
//
// One text = one request:
//   POST https://dashboard.philsms.com/api/v3/sms/send
//   Authorization: Bearer <PHILSMS_API_TOKEN>
//   {"recipient": "639171234567", "sender_id": "SmartRide", "type": "plain", "message": "..."}
//
// Keys (config/keys.php):
//   PHILSMS_API_TOKEN  from dashboard.philsms.com > Developers
//   PHILSMS_SENDER_ID  a sender name PhilSMS approved for your account (Sending > Sender ID).
//                      Leave it '' to let PhilSMS use your account's default sender.
//
// Docs: https://dashboard.philsms.com/developers/documentation

require_once __DIR__ . '/http.php';

// Is there a token? Without one, SmartRide runs texts in demo mode (nothing is sent).
function philsms_ready()
{
    return PHILSMS_API_TOKEN !== '';
}

// Turns any way of writing a PH mobile number into the one PhilSMS wants: 639XXXXXXXXX.
//   09171234567, 0917-123-4567, +63 917 123 4567, 639171234567  ->  639171234567
// Returns null if it isn't a PH mobile number.
function philsms_number($phone)
{
    $digits = preg_replace('/\D/', '', (string) $phone);   // keep only the digits
    if (preg_match('/^09\d{9}$/', $digits)) {
        return '63' . substr($digits, 1);                 // 0917... -> 63917...
    }
    if (preg_match('/^639\d{9}$/', $digits)) {
        return $digits;
    }
    return null;
}

// One request to PhilSMS. Always returns ['ok' => true/false, 'message' => '...', 'data' => [...]]
// and never throws, so a texting problem can never break a page or a fare scan.
function philsms_request($method, $path, $body = null)
{
    try {
        $answer = api_request($method, PHILSMS_API . $path,
            ['Authorization: Bearer ' . PHILSMS_API_TOKEN], $body, 10);
    } catch (Exception $ex) {
        // Couldn't connect, or PhilSMS answered with an HTTP error (wrong token, bad sender ID...).
        $reason = preg_replace('/^.*? said no: /', '', $ex->getMessage());
        return ['ok' => false, 'message' => $reason, 'data' => []];
    }
    // PhilSMS sometimes answers HTTP 200 but with "status": "error" inside, so check that too.
    if (($answer['status'] ?? '') !== 'success') {
        return ['ok' => false, 'message' => $answer['message'] ?? 'PhilSMS did not say why.', 'data' => []];
    }
    return ['ok' => true, 'message' => $answer['message'] ?? 'Sent.', 'data' => $answer['data'] ?? []];
}

// Sends one text. Returns the same ['ok', 'message', 'data'] answer.
// A failed text is also written to the PHP error log as "SmartRide PhilSMS: <reason>".
function philsms_send($phone, $message)
{
    if (!philsms_ready()) {
        return ['ok' => false, 'message' => 'Demo mode: no PhilSMS token in config/keys.php.', 'data' => []];
    }
    $number = philsms_number($phone);
    if ($number === null) {
        return ['ok' => false, 'message' => '"' . $phone . '" is not a PH mobile number (09XXXXXXXXX).', 'data' => []];
    }

    $body = [
        'recipient' => $number,
        'type'      => 'plain',
        'message'   => mb_substr($message, 0, 300),           // keep it to about 2 texts
    ];
    if (PHILSMS_SENDER_ID !== '') {
        $body['sender_id'] = PHILSMS_SENDER_ID;               // empty = account's default sender
    }

    $result = philsms_request('POST', '/sms/send', $body);
    if (!$result['ok']) {
        error_log('SmartRide PhilSMS: ' . $result['message']);
    }
    return $result;
}

// Credits left, e.g. "₱143". Returns null if there's no token or PhilSMS can't be asked.
function philsms_balance()
{
    if (!philsms_ready()) {
        return null;
    }
    $result = philsms_request('GET', '/balance');
    return $result['ok'] ? ($result['data']['remaining_balance'] ?? null) : null;
}
