<?php
// Driver: Routes. How full each route ran, and the fare for each.
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../models/trip_model.php';
require_once '../models/fleet_model.php';
require_once '../models/route_model.php';

session_start();
require_role('driver');

$driver_id = (int) $_SESSION['user_id'];
$trip = get_active_trip($conn, $driver_id);
$stats = get_driver_route_stats($conn, $driver_id);
$routes = get_routes($conn);
$route_stops = [];
foreach ($routes as $route) {
    $route_stops[$route['route_id']] = get_route_stops($conn, (int) $route['route_id']); // landmarks A to B
}

$title = 'Routes';
$current_page = 'driver_routes.php';
include '../views/driver/routes.php';
