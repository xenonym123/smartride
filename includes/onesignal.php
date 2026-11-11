<?php
// Talks to the OneSignal API (push notifications).
//
// How it works:
//   - In the browser, the OneSignal SDK asks "Allow notifications?" and links that
//     phone/computer to the user with OneSignal.login('smartride-user-5').
//     (See views/partials/dashboard_bottom.php.)
//   - On the server, whenever SmartRide saves a notification, onesignal_send() asks
//     OneSignal to push it to that user. The REST API key goes in the
//     Authorization header and never leaves the server.
//
// Docs: https://documentation.onesignal.com/reference/push-notification

require_once __DIR__ . '/http.php';

// Both the App ID and the REST API key are needed to send pushes.
function onesignal_ready()
{
    return ONESIGNAL_APP_ID !== '' && ONESIGNAL_REST_API_KEY !== '';
}

// The name OneSignal knows each SmartRide user by.
function onesignal_external_id($user_id)
{
    return 'smartride-user-' . (int) $user_id;
}

// Sends one push to one user. Returns true if OneSignal took it.
// If it fails we only log it: the alert is still saved and shown inside SmartRide.
function onesignal_send($user_id, $title, $body, $link = '')
{
    if (!onesignal_ready()) {
        return false;
    }
    $message = [
        'app_id'          => ONESIGNAL_APP_ID,
        'target_channel'  => 'push',
        'include_aliases' => ['external_id' => [onesignal_external_id($user_id)]],
        'headings'        => ['en' => $title],
        'contents'        => ['en' => $body !== '' ? $body : $title],
    ];
    // OneSignal only accepts https links. On localhost the push still opens the site.
    if ($link !== '' && strpos(app_url($link), 'https://') === 0) {
        $message['url'] = app_url($link);
    }

    try {
        api_request('POST', ONESIGNAL_API . '/notifications?c=push',
            ['Authorization: Key ' . ONESIGNAL_REST_API_KEY], $message, 5);
        return true;
    } catch (Exception $ex) {
        error_log('SmartRide OneSignal: ' . $ex->getMessage());
        return false;
    }
}
