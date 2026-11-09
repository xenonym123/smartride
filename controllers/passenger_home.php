<?php
// Passenger: Home. Wallet balance, the ride QR, the next jeepney and recent activity.
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../models/wallet_model.php';
require_once '../models/fare_model.php';
require_once '../models/fleet_model.php';

session_start();
require_role('passenger');

$user_id = (int) $_SESSION['user_id'];
$wallet = get_my_wallet($conn, $user_id);
$month = get_month_summary($conn, $wallet['wallet_id']);

// Next jeepney = the unit on a trip with the most seats left.
$next = null;
foreach (get_fleet($conn) as $unit) {
    if ($unit['state'] === 'on_trip' && ($next === null || $unit['seats_left'] > $next['seats_left'])) {
        $next = $unit;
    }
}

// Used by usort() below to put the newest item first.
function newest_first($a, $b)
{
    return strcmp($b['when'], $a['when']);
}

// Recent: last 5 rides and last 5 reloads, mixed together, newest first.
$recent = [];
foreach (get_rides($conn, $wallet['wallet_id'], 5) as $ride) {
    $recent[] = ['when' => $ride['scanned_at'], 'label' => $ride['route_name'], 'amount' => $ride['amount'], 'status' => $ride['status']];
}
foreach (get_reloads($conn, $wallet['wallet_id'], 5) as $reload) {
    $recent[] = ['when' => $reload['reloaded_at'], 'label' => 'Wallet reload · ' . reload_method_label($reload['method']),
                 'amount' => $reload['amount'], 'status' => 'reload'];
}
usort($recent, 'newest_first');
$recent = array_slice($recent, 0, 5);

$title = 'Home';
$current_page = 'passenger_home.php';
include '../views/passenger/home.php';
