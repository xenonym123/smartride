<?php
// JSON for the driver's Navigate screen: turn-by-turn directions for the line of their trip.
//   ?from=1200    the driver is 1200 m along the line: name the streets ahead first
//   ?riders=1     only "who's getting off where" (asked again every 30 seconds)
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/guidance.php';
require_once '../models/trip_model.php';
require_once '../models/fleet_model.php';

session_start();
require_role_json(['driver']);

$trip = get_active_trip($conn, (int) $_SESSION['user_id']);
if (!$trip) {
    json_out(['ok' => false, 'message' => 'Start a trip first.'], 404);
}
session_write_close(); // naming streets can take a few seconds; don't hold up the driver's other pages

// Riders on this trip who picked a drop-off and haven't got off yet, per landmark.
$stmt = $conn->prepare("SELECT s.name, COUNT(*) AS riders FROM fare_transactions f
                        JOIN route_stops s ON s.stop_id = f.dest_stop_id
                        WHERE f.trip_id = ? AND f.status = 'paid' AND f.alighted_at IS NULL
                        GROUP BY s.stop_id, s.name");
$stmt->bind_param("i", $trip['trip_id']);
$stmt->execute();
$getting_off = [];
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
    $getting_off[$row['name']] = (int) $row['riders'];
}
if (input('riders') === '1') {
    json_out(['ok' => true, 'getting_off' => (object) $getting_off]);
}

$guide = route_guidance_for($conn, (int) $trip['route_id'], 6.0, max(0, (int) input('from')));
if (!$guide) {
    json_out(['ok' => false, 'message' => 'This line has no road line yet.'], 404);
}
$steps = [];
foreach ($guide['steps'] as $step) {
    if (!$step['skip']) {
        $steps[] = ['type' => $step['type'], 'kind' => $step['kind'], 'at' => $step['at'], 'lat' => $step['lat'],
                    'lng' => $step['lng'], 'street' => (string) $step['street'], 'text' => $step['text']];
    }
}
$route = get_route($conn, (int) $trip['route_id']);
json_out([
    'ok'          => true,
    'line'        => ['id' => (int) $trip['route_id'], 'name' => $trip['route_name'], 'short' => $route['short_name'] ?? $trip['route_name'],
                      'color' => $route['color'] ?? '#2A20D8'],
    'unit'        => $trip['unit_code'],
    'points'      => $guide['points'],
    'total'       => $guide['total'],
    'start_street'=> (string) $guide['start_street'],
    'steps'       => $steps,
    'complete'    => $guide['complete'],   // false = street names still being filled in
    'getting_off' => (object) $getting_off,
]);
