<?php
// jeepneys, routes and gps_pings tables.

require_once __DIR__ . '/route_model.php';

// ---------- Jeepneys ----------

function get_unit($conn, $unit_id)
{
    $stmt = $conn->prepare("SELECT * FROM jeepneys WHERE unit_id = ?");
    $stmt->bind_param("i", $unit_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Units a driver can take out: active, not a demo unit, and not already on a trip.
function get_available_units($conn)
{
    $stmt = $conn->prepare("SELECT * FROM jeepneys j
                            WHERE j.status = 'active' AND j.simulated = 0
                            AND NOT EXISTS (SELECT 1 FROM trips t WHERE t.unit_id = j.unit_id AND t.ended_at IS NULL)
                            ORDER BY unit_code");
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function unit_code_taken($conn, $code)
{
    $stmt = $conn->prepare("SELECT unit_id FROM jeepneys WHERE unit_code = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

// $route_id = the line the unit is franchised to.
function add_unit($conn, $code, $plate, $capacity, $operator_id, $route_id)
{
    $stmt = $conn->prepare("INSERT INTO jeepneys (operator_id, route_id, unit_code, plate_no, capacity, status)
                            VALUES (?, ?, ?, ?, ?, 'active')");
    $stmt->bind_param("iissi", $operator_id, $route_id, $code, $plate, $capacity);
    $stmt->execute();
}

function set_unit_status($conn, $unit_id, $status)
{
    $stmt = $conn->prepare("UPDATE jeepneys SET status = ? WHERE unit_id = ?");
    $stmt->bind_param("si", $status, $unit_id);
    $stmt->execute();
}

// Every unit with its line, current trip, live count and position. Feeds the maps.
// A unit's line is the route of its running trip, or else the line it's franchised to.
function get_fleet($conn)
{
    $stmt = $conn->prepare("SELECT j.unit_id, j.unit_code, j.plate_no, j.capacity, j.status, j.simulated,
                                   t.trip_id, r.route_id, r.route_name, r.short_name, r.color, u.full_name AS driver_name,
                                   (SELECT onboard FROM passenger_logs pl WHERE pl.trip_id = t.trip_id
                                    ORDER BY pl.count_id DESC LIMIT 1) AS onboard,
                                   g.latitude, g.longitude, g.recorded_at
                            FROM jeepneys j
                            LEFT JOIN trips t ON t.unit_id = j.unit_id AND t.ended_at IS NULL
                            LEFT JOIN routes r ON r.route_id = COALESCE(t.route_id, j.route_id)
                            LEFT JOIN users u ON u.user_id = t.driver_id
                            LEFT JOIN gps_pings g ON g.ping_id = (SELECT MAX(ping_id) FROM gps_pings gp WHERE gp.unit_id = j.unit_id)
                            ORDER BY j.simulated, j.unit_code");
    $stmt->execute();
    $units = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Simulated units only appear while PRESENTATION_TOOLS is on (config.php).
    if (!PRESENTATION_TOOLS) {
        $units = array_values(array_filter($units, function ($u) { return !$u['simulated']; }));
    }
    foreach ($units as $i => $unit) {
        $units[$i]['route_id'] = $unit['route_id'] !== null ? (int) $unit['route_id'] : null;
        $units[$i]['simulated'] = (bool) $unit['simulated'];
        if ($units[$i]['simulated']) {
            $units[$i] = simulate_unit($conn, $units[$i]);
            continue;
        }
        $on_trip = $unit['trip_id'] !== null;

        // breakdown / offline are set by the admin. Otherwise it's on a trip or parked.
        if ($unit['status'] !== 'active') {
            $units[$i]['state'] = $unit['status'];
        } elseif ($on_trip) {
            $units[$i]['state'] = 'on_trip';
        } else {
            $units[$i]['state'] = 'parked';
        }

        $units[$i]['onboard'] = $on_trip ? (int) $unit['onboard'] : null;
        $units[$i]['seats_left'] = $on_trip ? max(0, (int) $unit['capacity'] - (int) $unit['onboard']) : null;
        $units[$i]['latitude'] = $unit['latitude'] !== null ? (float) $unit['latitude'] : null;
        $units[$i]['longitude'] = $unit['longitude'] !== null ? (float) $unit['longitude'] : null;
        $units[$i]['seen'] = time_ago($unit['recorded_at']);
    }
    return $units;
}


// ---------- Simulated units (simulated = 1, only while PRESENTATION_TOOLS is on) ----------
// So the map isn't empty in a presentation, each line has demo jeepneys that drive it by themselves
// at 15 km/h. Their position is worked out from the clock, so every viewer sees the same thing.
// They have no driver and take no fares. Real units use the driver's phone GPS (api_gps.php).

define('DEMO_SPEED_MPS', 15 / 3.6);

function simulate_unit($conn, $unit)
{
    $unit['trip_id'] = null;
    $unit['driver_name'] = null;
    $unit['latitude'] = null;
    $unit['longitude'] = null;
    $unit['seen'] = 'just now';
    if ($unit['status'] !== 'active' || $unit['route_id'] === null) {
        $unit['state'] = $unit['status'] === 'active' ? 'parked' : $unit['status'];
        $unit['onboard'] = null;
        $unit['seats_left'] = null;
        return $unit;
    }

    $line = demo_line_geometry($conn, $unit['route_id']);
    if ($line) {
        // Two units on a line start half a loop apart (unit_id odd/even), plus a little extra.
        $start = (($unit['unit_id'] % 2) * 0.5 + ($unit['unit_id'] % 7) * 0.03) * $line['total'];
        $point = point_along_line($line, fmod(time() * DEMO_SPEED_MPS + $start, $line['total']));
        $unit['latitude'] = $point[0];
        $unit['longitude'] = $point[1];
    }

    // How full it is goes up and down slowly over the day.
    $cap = (int) $unit['capacity'];
    $onboard = (int) round($cap * (0.55 + 0.45 * sin(time() / 900 + $unit['unit_id'] * 1.7)));
    $unit['onboard'] = max(0, min($cap, $onboard));
    $unit['seats_left'] = $cap - $unit['onboard'];
    $unit['state'] = 'on_trip';
    return $unit;
}

// A line's points with the distance (meters) from the start to each one. Cached per page.
function demo_line_geometry($conn, $route_id)
{
    static $cache = [];
    if (array_key_exists($route_id, $cache)) {
        return $cache[$route_id];
    }
    $saved = get_saved_route_path($conn, $route_id);
    $points = $saved ? json_decode($saved['points'], true) : [];
    if (count($points) < 2) {
        return $cache[$route_id] = null;
    }
    // A line that doesn't end where it started is driven there and back.
    $first = $points[0];
    $last = $points[count($points) - 1];
    if (abs($first[0] - $last[0]) + abs($first[1] - $last[1]) > 0.002) {
        $points = array_merge($points, array_slice(array_reverse($points), 1));
    }
    $dist = [0.0];
    for ($k = 1; $k < count($points); $k++) {
        $dist[] = $dist[$k - 1] + 1000 * line_km($points[$k - 1][0], $points[$k - 1][1], $points[$k][0], $points[$k][1]);
    }
    return $cache[$route_id] = ['points' => $points, 'dist' => $dist, 'total' => max(1.0, $dist[count($dist) - 1])];
}

function point_along_line($line, $meters)
{
    $dist = $line['dist'];
    $points = $line['points'];
    for ($k = 1; $k < count($points); $k++) {
        if ($dist[$k] >= $meters) {
            $part = $dist[$k] - $dist[$k - 1];
            $t = $part > 0 ? ($meters - $dist[$k - 1]) / $part : 0;
            return [round($points[$k - 1][0] + ($points[$k][0] - $points[$k - 1][0]) * $t, 6),
                    round($points[$k - 1][1] + ($points[$k][1] - $points[$k - 1][1]) * $t, 6)];
        }
    }
    return $points[count($points) - 1];
}

// Haversine distance in km (same math as straight_line_km in geoapify.php).
function line_km($lat1, $lng1, $lat2, $lng2)
{
    $dlat = deg2rad($lat2 - $lat1);
    $dlng = deg2rad($lng2 - $lng1);
    $a = sin($dlat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dlng / 2) ** 2;
    return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

// The point on a line closest to $lat/$lng: where a passenger should walk to board.
// Returns [lat, lng, meters away].
function nearest_point_on_line($points, $lat, $lng)
{
    $k = 111320 * cos(deg2rad($lat));   // meters per degree of longitude here
    $best = null;
    for ($i = 1; $i < count($points); $i++) {
        $ax = ($points[$i - 1][1] - $lng) * $k;
        $ay = ($points[$i - 1][0] - $lat) * 110540;
        $bx = ($points[$i][1] - $lng) * $k;
        $by = ($points[$i][0] - $lat) * 110540;
        $dx = $bx - $ax;
        $dy = $by - $ay;
        $len = $dx * $dx + $dy * $dy;
        $t = $len > 0 ? max(0, min(1, -($ax * $dx + $ay * $dy) / $len)) : 0;
        $px = $ax + $t * $dx;
        $py = $ay + $t * $dy;
        $d = sqrt($px * $px + $py * $py);
        if ($best === null || $d < $best[2]) {
            $best = [round($lat + $py / 110540, 6), round($lng + $px / $k, 6), (int) round($d)];
        }
    }
    return $best;
}

function add_gps_ping($conn, $unit_id, $lat, $lng)
{
    $stmt = $conn->prepare("INSERT INTO gps_pings (unit_id, latitude, longitude, recorded_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("idd", $unit_id, $lat, $lng);
    $stmt->execute();
}


// ---------- Routes ----------

// In line-number order (1 to 24 are the LPTRP lines).
function get_routes($conn)
{
    $stmt = $conn->prepare("SELECT * FROM routes ORDER BY route_id");
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function get_route($conn, $route_id)
{
    $stmt = $conn->prepare("SELECT * FROM routes WHERE route_id = ?");
    $stmt->bind_param("i", $route_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function add_route($conn, $origin, $destination, $fare)
{
    $name = $origin . ' – ' . $destination;
    $short = mb_substr($name, 0, 40);
    $stmt = $conn->prepare("INSERT INTO routes (route_name, short_name, origin, destination, base_fare, color)
                            VALUES (?, ?, ?, ?, ?, '#2A20D8')");
    $stmt->bind_param("ssssd", $name, $short, $origin, $destination, $fare);
    $stmt->execute();
}

function update_route_fare($conn, $route_id, $fare)
{
    $stmt = $conn->prepare("UPDATE routes SET base_fare = ? WHERE route_id = ?");
    $stmt->bind_param("di", $fare, $route_id);
    $stmt->execute();
}
