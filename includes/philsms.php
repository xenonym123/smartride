<?php
// Talks to the PhilSMS API: sends text messages to Philippine mobile numbers.
// SmartRide texts a passenger when their fare is paid and when the jeepney is
// close to the landmark where they're getting off. Only if they turned on
// "Text me" in their Profile, because every text uses PhilSMS credits.
//
// Docs: https://dashboard.philsms.com/developers/documentation

require_once __DIR__ . '/http.php';

function philsms_ready()
{
    return PHILSMS_API_TOKEN !== '';
}

// 09171234567 / +639171234567 / 639171234567  ->  639171234567 (what PhilSMS wants).
// Returns null if it isn't a PH mobile number.
function philsms_number($phone)
{
    $digits = preg_replace('/\D/', '', (string) $phone);
    if (preg_match('/^09\d{9}$/', $digits)) {
        return '63' . substr($digits, 1);
    }
    if (preg_match('/^639\d{9}$/', $digits)) {
        return $digits;
    }
    return null;
}

// Sends one text. Returns true if PhilSMS accepted it. Failures are only logged.
function philsms_send($phone, $message)
{
    $number = philsms_number($phone);
    if (!philsms_ready() || $number === null) {
        return false;
    }
    try {
        $answer = api_request('POST', PHILSMS_API . '/sms/send',
            ['Authorization: Bearer ' . PHILSMS_API_TOKEN], [
                'recipient' => $number,
                'sender_id' => PHILSMS_SENDER_ID,
                'type'      => 'plain',
                'message'   => mb_substr($message, 0, 300),
            ], 8);
        if (($answer['status'] ?? '') !== 'success') {
            error_log('SmartRide PhilSMS: ' . ($answer['message'] ?? 'not sent'));
            return false;
        }
        return true;
    } catch (Exception $ex) {
        error_log('SmartRide PhilSMS: ' . $ex->getMessage());
        return false;
    }
}
