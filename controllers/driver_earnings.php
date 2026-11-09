<?php
// Driver: Earnings.
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../models/fare_model.php';
require_once '../models/trip_model.php';

session_start();
require_role('driver');

$driver_id = (int) $_SESSION['user_id'];
$trip = get_active_trip($conn, $driver_id); // keeps the GPS beacon on while a trip runs
$today = get_driver_totals($conn, $driver_id, date('Y-m-d 00:00:00'));
$week = get_driver_totals($conn, $driver_id, date('Y-m-d 00:00:00', strtotime('monday this week')));
$month = get_driver_totals($conn, $driver_id, date('Y-m-01 00:00:00'));
$days = get_driver_by_day($conn, $driver_id, 14);

$title = 'Earnings';
$current_page = 'driver_earnings.php';
include '../views/driver/earnings.php';
