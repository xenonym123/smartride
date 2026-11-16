<?php
// Driver: Navigate. Turn-by-turn voice directions along the line of the running trip.
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../models/trip_model.php';
require_once '../models/fleet_model.php';

session_start();
require_role('driver');

$trip = get_active_trip($conn, (int) $_SESSION['user_id']);
$line = $trip ? get_route($conn, (int) $trip['route_id']) : null;

$use_map = true;   // Leaflet for the map
$title = 'Navigate';
$current_page = 'driver_navigate.php';
include '../views/driver/navigate.php';
