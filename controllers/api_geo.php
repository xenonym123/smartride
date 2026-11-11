<?php
// JSON for the Geoapify features on the Track a Jeepney page.
//   ?action=where&lat=..&lng=..             street name for the passenger's spot
//   ?action=route&lat=..&lng=..&unit=BCN 105  walking route to that jeepney
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
