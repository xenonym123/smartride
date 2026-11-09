<?php
// Driver: QR Scanner. The camera reads the QR in the browser, then sends it to api_scan.php.
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../models/trip_model.php';

session_start();
require_role('driver');

$trip = get_active_trip($conn, (int) $_SESSION['user_id']);
$live = get_live_data($conn, $trip);

$title = 'QR scanner';
$current_page = 'driver_scan.php';
include '../views/driver/scan.php';
