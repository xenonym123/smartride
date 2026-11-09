<?php
// Passenger: Ride History.
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../models/wallet_model.php';
require_once '../models/fare_model.php';

session_start();
require_role('passenger');

$wallet = get_my_wallet($conn, (int) $_SESSION['user_id']);
$rides = get_rides($conn, $wallet['wallet_id'], 200);
$month = get_month_summary($conn, $wallet['wallet_id']);

$title = 'Ride history';
$current_page = 'passenger_rides.php';
include '../views/passenger/rides.php';
