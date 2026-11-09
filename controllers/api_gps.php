<?php
// JSON: the driver's phone sends its location here every 20 seconds while a trip runs.
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../models/trip_model.php';
require_once '../models/fleet_model.php';

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
    json_out(['ok' => true]);
}
json_out(['ok' => false], 409);
