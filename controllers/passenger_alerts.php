<?php
// Passenger: saves the "Text me" (PhilSMS) and "Email me" (Gmail) switches from the Profile page.
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/philsms.php';
require_once '../models/user_model.php';

session_start();
require_role('passenger');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    go('passenger_profile.php');
}
require_csrf('passenger_profile.php');

$user_id = (int) $_SESSION['user_id'];
$account = get_user_by_id($conn, $user_id);
$sms = !empty($_POST['sms_alerts']);
$email = !empty($_POST['email_alerts']);

// A text needs a real PH mobile number on the account.
if ($sms && philsms_number($account['phone']) === null) {
    flash('error', 'Put a PH mobile number (like 09171234567) in your profile first, then turn on texts.');
    go('passenger_profile.php');
}

set_alert_settings($conn, $user_id, $sms, $email);
add_log($conn, $user_id, 'profile_updated', 'Alerts: texts ' . ($sms ? 'on' : 'off') . ', emails ' . ($email ? 'on' : 'off'));
flash('success', 'Alert settings saved.');
go('passenger_profile.php');
