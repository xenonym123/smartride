<?php
// Driver: Home. Today's numbers, and start or end a trip.
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../models/trip_model.php';
require_once '../models/fare_model.php';
require_once '../models/fleet_model.php';
require_once '../models/route_model.php';

session_start();
require_role('driver');

$driver_id = (int) $_SESSION['user_id'];
$trip = get_active_trip($conn, $driver_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('driver_home.php');

    if (input('action') === 'start') {
        $unit_id = (int) input('unit_id');
        $route_id = (int) input('route_id');
        $unit = get_unit($conn, $unit_id);

        if ($trip) {
            flash('error', 'End your current trip before starting a new one.');
        } elseif (!$unit || $unit['status'] !== 'active' || unit_busy($conn, $unit_id)) {
            flash('error', "That unit can't go out right now. Pick another one.");
        } elseif (!get_route($conn, $route_id)) {
            flash('error', 'Pick a route.');
        } else {
            $trip_id = start_trip($conn, $driver_id, $unit_id, $route_id);
            add_log($conn, $driver_id, 'trip_start', 'Started trip #' . $trip_id . ' on ' . $unit['unit_code']);
            flash('success', 'Trip started on ' . $unit['unit_code'] . '. Open the QR scanner to take fares.');
        }
    }

    if (input('action') === 'end' && $trip) {
        end_trip($conn, $trip['trip_id']);
        $stats = get_trip_stats($conn, $trip['trip_id']);
        add_log($conn, $driver_id, 'trip_end', 'Ended trip #' . $trip['trip_id'] . ' · ' . $stats['paid'] . ' paid fares · ' . peso($stats['collected']));
        flash('success', 'Trip ended. ' . (int) $stats['paid'] . ' paid fares, ' . peso($stats['collected']) . ' collected.');
    }

    go('driver_home.php');
}

$totals = get_driver_totals($conn, $driver_id, date('Y-m-d 00:00:00'));
$trips = get_todays_trips($conn, $driver_id);
$units = get_available_units($conn);
$routes = get_routes($conn);
$trip_stops = $trip ? get_route_stops($conn, (int) $trip['route_id']) : []; // for the GPS simulator

$title = 'Driver dashboard';
$current_page = 'driver_home.php';
include '../views/driver/home.php';
