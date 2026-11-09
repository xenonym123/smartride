<?php
// Admin (operator side): Analytics.
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../models/user_model.php';
require_once '../models/wallet_model.php';
require_once '../models/fare_model.php';
require_once '../models/fleet_model.php';

session_start();
require_role('admin');

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

$title = 'Analytics';
$current_page = 'admin_home.php';
include '../views/admin/home.php';
