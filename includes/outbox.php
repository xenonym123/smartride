<?php
// The outbox: texts (PhilSMS), emails (Gmail) and pushes (OneSignal) wait here
// and go out AFTER the page has answered the browser. So when a driver scans a QR,
// the scanner shows "Paid" right away instead of waiting for three outside APIs.

require_once __DIR__ . '/onesignal.php';
require_once __DIR__ . '/philsms.php';
require_once __DIR__ . '/gmail.php';

// Adds one message to the outbox. $channel is 'push', 'sms' or 'email'.
function queue_message($channel, $to, $title, $body, $link = '')
{
    if (empty($GLOBALS['smartride_outbox'])) {
        $GLOBALS['smartride_outbox'] = [];
        ob_start();                                   // hold the page so we can finish it early
        register_shutdown_function('send_outbox');    // runs when the page is done
    }
    $GLOBALS['smartride_outbox'][] = ['channel' => $channel, 'to' => $to, 'title' => $title, 'body' => $body, 'link' => $link];
}

function send_outbox()
{
    // 1. Finish the page for the browser first. Take the whole page out of every
    //    buffer (XAMPP adds one of its own, output_buffering = 4096), then send it
    //    in one go with its exact length, so the browser knows it has everything.
    $page = '';
    while (ob_get_level() > 0) {
        $page = ob_get_clean() . $page;
    }
    if (!headers_sent()) {
        header('Content-Length: ' . strlen($page)); // "this is the whole answer"
        header('Connection: close');
    }
    echo $page;
    flush();
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();                        // save the session now, don't block other pages
    }
    ignore_user_abort(true);                          // keep going even though the browser is done

    // 2. Now send what was queued. Each sender logs its own errors and never stops the others.
    foreach ($GLOBALS['smartride_outbox'] as $msg) {
        if ($msg['channel'] === 'push') {
            onesignal_send($msg['to'], $msg['title'], $msg['body'], $msg['link']);
        } elseif ($msg['channel'] === 'sms') {
            philsms_send($msg['to'], 'SmartRide: ' . $msg['title'] . '. ' . $msg['body']);
        } elseif ($msg['channel'] === 'email') {
            gmail_send($msg['to'], $msg['title'], $msg['body']);
        }
    }
    $GLOBALS['smartride_outbox'] = [];
}
