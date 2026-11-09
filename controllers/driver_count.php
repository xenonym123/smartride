<?php
// Driver: Passenger Count. The live numbers refresh from api_count.php.
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../models/trip_model.php';

session_start();
require_role('driver');

$trip = get_active_trip($conn, (int) $_SESSION['user_id']);
$live = get_live_data($conn, $trip);

$title = 'Passenger count';
$current_page = 'driver_count.php';
include '../views/driver/count.php';
