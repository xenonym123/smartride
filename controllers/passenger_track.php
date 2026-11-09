<?php
// Passenger: Track a Jeepney. The map loads its data from api_fleet.php.
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

session_start();
require_role('passenger');

$title = 'Track a jeepney';
$current_page = 'passenger_track.php';
$use_map = true;
include '../views/passenger/track.php';
