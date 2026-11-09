<?php
// Admin: Jeepney Tracking. The live map, the fleet, and the routes and fares.
// The forms on this page post here with a hidden "action":
//   add_unit, unit_status, add_route, route_fare
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../models/fare_model.php';
require_once '../models/fleet_model.php';

session_start();
require_role('admin');

$admin_id = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('admin_tracking.php');
    $action = input('action');
    $back = 'admin_tracking.php#fleet';

    if ($action === 'add_unit') {
        $code = strtoupper(preg_replace('/\s+/', ' ', input('unit_code')));
        $plate = strtoupper(input('plate_no'));
        $capacity = (int) input('capacity', '25');

        if (!preg_match('/^[A-Z]{2,4} ?\d{2,4}$/', $code)) {
            flash('error', 'Unit codes look like BCN 110.');
        } elseif (unit_code_taken($conn, $code)) {
            flash('error', $code . ' is already in the fleet.');
        } elseif (!preg_match('/^[A-Z0-9 -]{2,12}$/', $plate) || $capacity < 10 || $capacity > 40) {
            flash('error', 'Add a plate number and a capacity between 10 and 40.');
        } else {
            add_unit($conn, $code, $plate, $capacity, $admin_id);
            add_log($conn, $admin_id, 'unit_added', 'Added unit ' . $code . ' (' . $plate . ', ' . $capacity . ' seats)');
            flash('success', $code . ' is in the fleet.');
        }
    }

    if ($action === 'unit_status') {
        $unit = get_unit($conn, (int) input('unit_id'));
        $status = input('status');
        if ($unit && in_array($status, ['active', 'breakdown', 'offline'], true)) {
            set_unit_status($conn, $unit['unit_id'], $status);
            add_log($conn, $admin_id, 'unit_status', $unit['unit_code'] . ' set to ' . $status);
            flash('success', $unit['unit_code'] . ' is now marked ' . $status . '.');
        }
    }

    if ($action === 'add_route') {
        $origin = mb_substr(input('origin'), 0, 60);
        $destination = mb_substr(input('destination'), 0, 60);
        $fare = (float) input('base_fare');
        if ($origin === '' || $destination === '' || $fare < 1 || $fare > 200) {
            flash('error', 'Add a start, an end and a fare between ₱1 and ₱200.');
        } else {
            add_route($conn, $origin, $destination, $fare);
            add_log($conn, $admin_id, 'route_added', 'Added route ' . $origin . ' – ' . $destination . ' at ' . peso($fare));
            flash('success', 'Route ' . $origin . ' – ' . $destination . ' added.');
        }
        $back = 'admin_tracking.php#routes';
    }

    if ($action === 'route_fare') {
        $route = get_route($conn, (int) input('route_id'));
        $fare = (float) input('base_fare');
        if ($route && $fare >= 1 && $fare <= 200) {
            update_route_fare($conn, $route['route_id'], $fare);
            add_log($conn, $admin_id, 'fare_changed', $route['route_name'] . ' fare set to ' . peso($fare));
            flash('success', $route['route_name'] . ' now costs ' . peso($fare) . '.');
        } else {
            flash('error', 'Fares go from ₱1 to ₱200.');
        }
        $back = 'admin_tracking.php#routes';
    }

    go($back);
}

$fleet = get_fleet($conn);
$on_road = 0;
$riding = 0;
foreach ($fleet as $unit) {
    if ($unit['state'] === 'on_trip') {
        $on_road++;
        $riding += (int) $unit['onboard'];
    }
}
$routes = get_routes($conn);
$today = get_fare_totals_since($conn, date('Y-m-d 00:00:00'));

$title = 'Jeepney tracking';
$current_page = 'admin_tracking.php';
$use_map = true;
include '../views/admin/tracking.php';
