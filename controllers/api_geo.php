<?php
// JSON for the Geoapify features on the Track a Jeepney page.
//   ?action=where&lat=..&lng=..             street name for the passenger's spot
//   ?action=route&lat=..&lng=..&unit=BCN 105  walking route to that jeepney
//   ?action=line&lat=..&lng=..&route=5        where to board line 5 (closest point) + walking route there
//   ?action=ip                              rough location when GPS is blocked
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/geoapify.php';
require_once '../models/fleet_model.php';

session_start();
require_role_json(['passenger', 'admin']);

$action = input('action');
$lat = (float) input('lat');
$lng = (float) input('lng');
$valid_point = $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180 && ($lat != 0 || $lng != 0);

try {
    if ($action === 'where') {
        if (!$valid_point) {
            json_out(['ok' => false, 'message' => 'That location is not on the map.'], 400);
        }
        json_out(['ok' => true, 'address' => geoapify_address($conn, $lat, $lng), 'demo' => geoapify_demo_mode()]);
    }

    if ($action === 'route') {
        if (!$valid_point) {
            json_out(['ok' => false, 'message' => 'That location is not on the map.'], 400);
        }
        // Use the jeepney's position from our own database, not from the browser.
        $unit = null;
        foreach (get_fleet($conn) as $u) {
            if ($u['unit_code'] === input('unit') && $u['latitude'] !== null) {
                $unit = $u;
            }
        }
        if (!$unit) {
            json_out(['ok' => false, 'message' => 'That jeepney has no position yet.'], 404);
        }
        $route = geoapify_walk_route($lat, $lng, $unit['latitude'], $unit['longitude']);
        json_out([
            'ok'      => true,
            'unit'    => $unit['unit_code'],
            'meters'  => $route['meters'],
            'minutes' => max(1, (int) round($route['seconds'] / 60)),
            'points'  => $route['points'],
            'demo'    => $route['demo'],
        ]);
    }

    if ($action === 'line') {
        if (!$valid_point) {
            json_out(['ok' => false, 'message' => 'That location is not on the map.'], 400);
        }
        $route_id = (int) input('route');
        if (!get_route($conn, $route_id)) {
            json_out(['ok' => false, 'message' => 'No such line.'], 404);
        }
        $path = route_path_for($conn, $route_id);
        if (count($path['points']) < 2) {
            json_out(['ok' => false, 'message' => 'That line has no road line yet.'], 404);
        }
        // The closest point on the line is where to wait for the jeepney.
        $board = nearest_point_on_line($path['points'], $lat, $lng);
        // Name it after the closest landmark on the line.
        $landmark = '';
        $best = null;
        foreach (get_route_stops($conn, $route_id) as $stop) {
            $d = line_km($board[0], $board[1], (float) $stop['latitude'], (float) $stop['longitude']);
            if ($best === null || $d < $best) {
                $best = $d;
                $landmark = $stop['name'];
            }
        }
        $walk = geoapify_walk_route($lat, $lng, $board[0], $board[1]);
        json_out([
            'ok'       => true,
            'board'    => ['lat' => $board[0], 'lng' => $board[1], 'meters' => $board[2]],
            'landmark' => $landmark,
            'near_km'  => round($best, 1),
            'meters'   => $walk['meters'],
            'minutes'  => max(1, (int) round($walk['seconds'] / 60)),
            'points'   => $walk['points'],
            'demo'     => $walk['demo'],
        ]);
    }

    if ($action === 'ip') {
        $place = geoapify_ip_location($_SERVER['REMOTE_ADDR'] ?? '');
        json_out(['ok' => true, 'lat' => $place['lat'], 'lng' => $place['lng'], 'city' => $place['city'], 'demo' => geoapify_demo_mode()]);
    }
} catch (Exception $ex) {
    // Geoapify is down or the key is wrong. Log the real reason, keep the page working.
    error_log('SmartRide Geoapify: ' . $ex->getMessage());
    json_out(['ok' => false, 'message' => "Couldn't reach the map service. Try again in a bit."], 502);
}

json_out(['ok' => false, 'message' => 'Unknown action.'], 400);
