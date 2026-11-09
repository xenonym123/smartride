<?php
// JSON for the maps: where every jeepney is and how full it is.
// Used by Track a Jeepney (passenger) and Jeepney Tracking (admin).
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../models/fleet_model.php';

session_start();
require_role_json(['passenger', 'admin']);

$units = get_fleet($conn);

// Passengers don't need plate numbers or driver names. Send only what the map shows.
if ($_SESSION['role'] === 'passenger') {
    $keep = ['unit_code', 'route_name', 'capacity', 'onboard', 'seats_left', 'state', 'latitude', 'longitude', 'seen'];
    $short = [];
    foreach ($units as $unit) {
        $row = [];
        foreach ($keep as $key) {
            $row[$key] = $unit[$key];
        }
        $short[] = $row;
    }
    $units = $short;
}

json_out(['units' => $units]);
