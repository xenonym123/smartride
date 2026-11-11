<?php
// Admin (operator side): Analytics.
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../models/user_model.php';
require_once '../models/wallet_model.php';
require_once '../models/fare_model.php';
require_once '../models/fleet_model.php';
require_once '../models/notification_model.php';
require_once '../includes/maya.php';
require_once '../includes/geoapify.php';

session_start();
require_role('admin');

// The test buttons on the Connected APIs card: push (OneSignal), text (PhilSMS), email (Gmail).
// Each goes to the signed-in admin's own account.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('admin_home.php');
    $me = get_user_by_id($conn, (int) $_SESSION['user_id']);
    $test = input('action');

    if ($test === 'test_push') {
        add_notification($conn, (int) $me['user_id'], 'welcome', 'Test notification',
            'If you can read this as a pop-up, OneSignal push is working.', 'notifications.php');
        flash('success', onesignal_ready()
            ? 'Test sent through OneSignal. It should pop up in a few seconds (if you allowed alerts in this browser).'
            : 'Saved a test alert. Add your OneSignal keys in config/keys.php to get it as a real push.');
    } elseif ($test === 'test_sms') {
        // Sent right away (not through the outbox) so PhilSMS's answer can be shown here.
        $sent = philsms_send($me['phone'], 'SmartRide: Test message. If you got this, PhilSMS is working.');
        if ($sent['ok']) {
            $left = philsms_balance();
            flash('success', 'Test text sent to ' . $me['phone'] . ' through PhilSMS.' . ($left !== null ? ' Credits left: ' . $left . '.' : ''));
        } else {
            flash('error', 'PhilSMS: ' . $sent['message']);
        }
    } elseif ($test === 'test_email') {
        queue_message('email', $me['email'], 'SmartRide test email',
            email_html('Test email', ['If you can read this, the Gmail API is working.']));
        flash('success', gmail_ready()
            ? 'Test email sent to ' . $me['email'] . ' through the Gmail API.'
            : 'Demo mode: add your Gmail API keys in config/keys.php to send real emails.');
    }
    go('admin_home.php');
}

$fleet = get_fleet($conn);

// Count units by state, and how many people are riding right now.
$on_road = 0;
$breakdowns = 0;
$offline = 0;
$riding = 0;
foreach ($fleet as $unit) {
    if ($unit['state'] === 'on_trip') {
        $on_road++;
        $riding += (int) $unit['onboard'];
    } elseif ($unit['state'] === 'breakdown') {
        $breakdowns++;
    } elseif ($unit['state'] === 'offline') {
        $offline++;
    }
}

$today_start = date('Y-m-d 00:00:00');
$today = get_fare_totals_since($conn, $today_start);

// Yesterday only = (since yesterday 00:00) minus today.
$since_yesterday = get_fare_totals_since($conn, date('Y-m-d 00:00:00', strtotime('-1 day')));
$yesterday = ['paid' => (int) $since_yesterday['paid'] - (int) $today['paid']];

$reloads = reload_totals_since($conn, $today_start);
$users = count_users_by_role($conn);
$activity = get_logs($conn, '', 6);

// Which outside APIs have keys in config.php. Only says yes/no, never shows the keys.
$apis = [
    ['Geoapify', 'Maps: street names, route landmarks, bus route lines, walking routes',
        geoapify_demo_mode() ? 'demo' : 'on', geoapify_demo_mode() ? 'Demo mode · no key' : 'Key set', ''],
    ['Maya Checkout', 'Payment gateway for wallet reloads (sandbox, no real money)',
        maya_demo_mode() ? 'demo' : 'on', maya_demo_mode() ? 'Demo mode · no keys' : 'Sandbox keys set', ''],
    ['OneSignal', 'Push notifications to phones and computers',
        onesignal_ready() ? 'on' : 'demo',
        onesignal_ready() ? 'Keys set' : (ONESIGNAL_APP_ID !== '' ? 'REST API key missing' : 'Demo mode · no keys'), 'test_push'],
    ['PhilSMS', 'Texts: fare paid, and arriving near your stop',
        philsms_ready() ? 'on' : 'demo', philsms_ready() ? 'Token set' : 'Demo mode · no token', 'test_sms'],
    ['Gmail API', 'Emails: forgot password and payment receipts',
        gmail_ready() ? 'on' : 'demo', gmail_ready() ? 'Keys set' : 'Demo mode · no keys', 'test_email'],
];

$title = 'Analytics';
$current_page = 'admin_home.php';
include '../views/admin/home.php';
