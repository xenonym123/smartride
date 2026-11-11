<?php
// Passenger: Home. Wallet balance, the ride QR, the next jeepney and recent activity.
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../models/wallet_model.php';
require_once '../models/fare_model.php';
require_once '../models/fleet_model.php';
require_once '../models/route_model.php';
require_once '../models/user_model.php';

session_start();
require_role('passenger');

$user_id = (int) $_SESSION['user_id'];
$wallet = get_my_wallet($conn, $user_id);

// "Where are you getting off?" When the jeepney gets near that landmark, the driver's GPS
// ping triggers an "arriving" text (PhilSMS) and push. See api_gps.php.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('passenger_home.php');
    $ride = get_current_ride($conn, $wallet['wallet_id']);
    if ($ride && set_ride_destination($conn, $ride['txn_id'], $wallet['wallet_id'], (int) input('stop_id'))) {
        flash('success', "Got it. We'll let you know when you're close.");
    } else {
        flash('error', 'Pick a landmark on the route you are riding.');
    }
    go('passenger_home.php');
}

$month = get_month_summary($conn, $wallet['wallet_id']);

// The ride the passenger is on right now (paid on a trip that hasn't ended), and its landmarks.
$current_ride = get_current_ride($conn, $wallet['wallet_id']);
$ride_stops = $current_ride ? get_route_stops($conn, (int) $current_ride['route_id']) : [];
$me = get_user_by_id($conn, $user_id);

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
