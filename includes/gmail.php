<?php
// Talks to the Gmail API: sends forgot-password links and payment receipts.
//
// Gmail uses OAuth, not a plain API key:
//   1. We trade our long-lived refresh token for a short-lived access token
//      (POST https://oauth2.googleapis.com/token).
//   2. We send the email with that access token
//      (POST https://gmail.googleapis.com/gmail/v1/users/me/messages/send).
// Setup steps are in APIS.md. Scope needed: https://www.googleapis.com/auth/gmail.send
//
// Docs: https://developers.google.com/workspace/gmail/api/guides/sending

require_once __DIR__ . '/http.php';

function gmail_ready()
{
    return GMAIL_CLIENT_ID !== '' && GMAIL_CLIENT_SECRET !== '' && GMAIL_REFRESH_TOKEN !== '' && GMAIL_SENDER !== '';
}

// Step 1: a fresh access token (they last about an hour, so we get one per request).
function gmail_access_token()
{
    $answer = api_request('POST', GOOGLE_TOKEN_URL, [], [
        'client_id'     => GMAIL_CLIENT_ID,
        'client_secret' => GMAIL_CLIENT_SECRET,
        'refresh_token' => GMAIL_REFRESH_TOKEN,
        'grant_type'    => 'refresh_token',
    ], 10, true);
    if (empty($answer['access_token'])) {
        throw new Exception('Google did not give an access token.');
    }
    return $answer['access_token'];
}

// Step 2: sends one HTML email. Returns true if Gmail accepted it. Failures are only logged.
function gmail_send($to, $subject, $html)
{
    if (!gmail_ready() || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    // The email itself, in the standard format (RFC 2822). Subject is encoded so ₱ and ñ work.
    $message = "From: SmartRide <" . GMAIL_SENDER . ">\r\n"
        . "To: <" . $to . ">\r\n"
        . "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n"
        . "MIME-Version: 1.0\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html));
    // Gmail wants it base64url-encoded (+ and / swapped, no = at the end).
    $raw = rtrim(strtr(base64_encode($message), '+/', '-_'), '=');

    try {
        api_request('POST', GMAIL_API . '/users/me/messages/send',
            ['Authorization: Bearer ' . gmail_access_token()], ['raw' => $raw], 10);
        return true;
    } catch (Exception $ex) {
        error_log('SmartRide Gmail: ' . $ex->getMessage());
        return false;
    }
}

// A plain, branded email body. $lines are already-safe HTML strings.
function email_html($heading, $lines, $button_text = '', $button_url = '')
{
    $html = '<div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;padding:24px;color:#17154F">'
        . '<div style="font-size:22px;font-weight:bold;color:#2A20D8;margin-bottom:18px">SmartRide</div>'
        . '<h2 style="margin:0 0 12px">' . htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') . '</h2>';
    foreach ($lines as $line) {
        $html .= '<p style="margin:0 0 10px;line-height:1.5">' . $line . '</p>';
    }
    if ($button_url !== '') {
        $html .= '<p style="margin:20px 0"><a href="' . htmlspecialchars($button_url, ENT_QUOTES, 'UTF-8') . '" '
            . 'style="background:#2A20D8;color:#fff;padding:12px 20px;border-radius:10px;text-decoration:none;font-weight:bold">'
            . htmlspecialchars($button_text, ENT_QUOTES, 'UTF-8') . '</a></p>';
    }
    return $html . '<p style="color:#888;font-size:12px;margin-top:24px">SmartRide · Integrated Public Transport and Cashless Payment System</p></div>';
}
