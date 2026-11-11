<?php
// JSON: the driver's phone sends its location here every 20 seconds while a trip runs.
// (The GPS simulator on the driver's Home page sends here too, for the demo.)
// Each ping also checks: is the jeepney near a landmark where a rider is getting off?
// If so, that rider gets an "arriving" text (PhilSMS) and push, once.
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../models/trip_model.php';
require_once '../models/fleet_model.php';
require_once '../models/route_model.php';
require_once '../models/notification_model.php';
require_once '../includes/geoapify.php';

session_start();
require_role_json(['driver']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !check_csrf()) {
    json_out(['ok' => false], 403);
}

$trip = get_active_trip($conn, (int) $_SESSION['user_id']);
$body = read_json();
$lat = (float) ($body['lat'] ?? 0);
$lng = (float) ($body['lng'] ?? 0);

// Only save real coordinates, and only while a trip is running.
$valid = $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180 && ($lat != 0 || $lng != 0);
if ($trip && $valid) {
    add_gps_ping($conn, $trip['unit_id'], $lat, $lng);

    // Arriving? Distance from the jeepney to each rider's drop-off landmark.
    $told = 0;
    foreach (get_waiting_arrivals($conn, $trip['trip_id']) as $rider) {
        $meters = straight_line_km($lat, $lng, $rider['latitude'], $rider['longitude']) * 1000;
        if ($meters <= ARRIVAL_RADIUS_M && mark_arrival_sent($conn, $rider['txn_id'])) {
            add_notification($conn, (int) $rider['user_id'], 'arriving', 'Arriving at ' . $rider['name'],
                $trip['unit_code'] . ' is about ' . (int) (round($meters / 10) * 10) . ' m away. Get ready to get off.', 'passenger_home.php');
            $told++;
        }
    }
    json_out(['ok' => true, 'arrivals' => $told]);
}
json_out(['ok' => false], 409);
