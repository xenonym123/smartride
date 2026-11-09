<?php
// JSON for the driver's Passenger Count page.
//   GET  = the live numbers (the page asks every 5 seconds)
//   POST = the camera simulator: {"delta": 1} someone got on, {"delta": -1} someone got off
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../models/trip_model.php';
require_once '../models/notification_model.php';

session_start();
require_role_json(['driver']);

$trip = get_active_trip($conn, (int) $_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_csrf()) {
        json_out(['ok' => false, 'message' => 'Your session expired. Reload the page.'], 403);
    }
    if (!$trip) {
        json_out(['ok' => false, 'message' => 'Start a trip first.'], 409);
    }

    $body = read_json();
    $change = ((int) ($body['delta'] ?? 0)) >= 0 ? 1 : -1;
    $count = get_passenger_count($conn, $trip['trip_id']);

    if ($change > 0 && (int) $count['onboard'] >= (int) $trip['capacity']) {
        json_out(['ok' => false, 'message' => 'The unit is already full.'] + get_live_data($conn, $trip), 409);
    }
    change_passenger_count($conn, $trip['trip_id'], $change);
    if ($change > 0 && (int) $count['onboard'] + 1 >= (int) $trip['capacity']) {
        add_notification($conn, (int) $_SESSION['user_id'], 'unit_full', $trip['unit_code'] . ' is full',
            'All ' . $trip['capacity'] . ' seats taken. The scanner refuses new fares until someone gets off.', 'driver_count.php');
    }
    json_out(['ok' => true] + get_live_data($conn, $trip));
}

json_out(get_live_data($conn, $trip));
