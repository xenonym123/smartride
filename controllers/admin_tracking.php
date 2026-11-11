<?php
// Admin: Jeepney Tracking. The live map, the fleet, and the routes and fares.
// The forms on this page post here with a hidden "action":
//   add_unit, unit_status, add_route, route_fare, add_stop, delete_stop, refresh_routes
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../models/fare_model.php';
require_once '../models/fleet_model.php';
require_once '../includes/geoapify.php';
require_once '../models/route_model.php';

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

    // ---- Route landmarks (Geoapify) ----
    if ($action === 'add_stop') {
        $route_id = (int) input('route_id');
        $name = mb_substr(input('stop_name'), 0, 100);
        $count = count(get_route_stops($conn, $route_id));
        $position = max(1, min($count + 1, (int) input('position', (string) ($count + 1))));

        if (!get_route($conn, $route_id) || mb_strlen($name) < 3) {
            flash('error', 'Pick a route and type the landmark name, like "Robinsons Place Bacolod".');
        } elseif (geoapify_demo_mode()) {
            flash('error', 'Add a Geoapify key in config/keys.php so SmartRide can find new landmarks on the map.');
        } else {
            try {
                $place = geoapify_find_place($name);   // GEOAPIFY forward geocoding
            } catch (Exception $ex) {
                error_log('SmartRide Geoapify: ' . $ex->getMessage());
                $place = null;
            }
            if ($place) {
                add_route_stop($conn, $route_id, $position, $name, $place['lat'], $place['lng'], 1);
                add_log($conn, $admin_id, 'route_added', 'Added landmark ' . $name . ' to route #' . $route_id);
                flash('success', $name . ' added. Geoapify found it at ' . $place['address'] . '.');
            } else {
                flash('error', "Geoapify couldn't find \"" . $name . "\" in Bacolod. Try the full name.");
            }
        }
        $back = 'admin_tracking.php#landmarks';
    }

    if ($action === 'delete_stop') {
        if (delete_route_stop($conn, (int) input('stop_id'))) {
            add_log($conn, $admin_id, 'route_added', 'Removed a landmark from a route');
            flash('success', 'Landmark removed. The route line will be redrawn.');
        }
        $back = 'admin_tracking.php#landmarks';
    }

    if ($action === 'refresh_routes') {
        // Look every landmark up again on Geoapify and redraw every bus route line.
        foreach (get_routes($conn) as $route) {
            reset_route_geocoding($conn, (int) $route['route_id']);
            route_path_for($conn, (int) $route['route_id']);
        }
        flash('success', geoapify_demo_mode()
            ? 'Demo mode: no Geoapify key, so routes are drawn as straight lines between landmarks.'
            : 'Landmarks and bus routes updated from Geoapify.');
        $back = 'admin_tracking.php#landmarks';
    }

    go($back);
}

$fleet = get_fleet($conn);

// GEOAPIFY: street name for each unit's last GPS point (cached in geo_cache).
foreach ($fleet as $i => $unit) {
    $fleet[$i]['address'] = '';
    if ($unit['latitude'] !== null && !geoapify_demo_mode()) {
        try {
            $fleet[$i]['address'] = geoapify_address($conn, $unit['latitude'], $unit['longitude']);
        } catch (Exception $ex) {
            error_log('SmartRide Geoapify: ' . $ex->getMessage()); // the page still works without it
        }
    }
}

$on_road = 0;
$riding = 0;
foreach ($fleet as $unit) {
    if ($unit['state'] === 'on_trip') {
        $on_road++;
        $riding += (int) $unit['onboard'];
    }
}
$routes = get_routes($conn);
$route_stops = [];
foreach ($routes as $route) {
    $route_stops[$route['route_id']] = get_route_stops($conn, (int) $route['route_id']);
}
$today = get_fare_totals_since($conn, date('Y-m-d 00:00:00'));

$title = 'Jeepney tracking';
$current_page = 'admin_tracking.php';
$use_map = true;
include '../views/admin/tracking.php';
