<?php
// JSON for the maps: every jeepney line with its color, landmarks (A to B) and road line.
// The 24 LPTRP lines come traced on the roads. A route the admin adds is drawn by Geoapify.
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/geoapify.php';
require_once '../models/fleet_model.php';

session_start();
require_role_json(['passenger', 'driver', 'admin']);

$routes = [];
foreach (get_routes($conn) as $route) {
    $path = route_path_for($conn, (int) $route['route_id']);
    $stops = [];
    foreach (get_route_stops($conn, (int) $route['route_id']) as $stop) {
        $stops[] = ['id' => (int) $stop['stop_id'], 'name' => $stop['name'],
                    'lat' => (float) $stop['latitude'], 'lng' => (float) $stop['longitude']];
    }
    $routes[] = [
        'id'      => (int) $route['route_id'],
        'name'    => $route['route_name'],
        'short'   => $route['short_name'],
        'color'   => $route['color'],
        'fare'    => peso($route['base_fare']),
        'stops'   => $stops,
        'points'  => $path['points'],
        'km'      => round($path['meters'] / 1000, 1),
        'minutes' => (int) round($path['seconds'] / 60),
        // osm = mapped street by street on OpenStreetMap, lptrp = traced from the LPTRP landmarks,
        // geoapify = Geoapify bus routing, straight = demo mode (no key)
        'source'  => $path['source'],
    ];
}
json_out(['routes' => $routes]);
