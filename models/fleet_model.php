<?php
// jeepneys, routes and gps_pings tables.

// ---------- Jeepneys ----------

function get_unit($conn, $unit_id)
{
    $stmt = $conn->prepare("SELECT * FROM jeepneys WHERE unit_id = ?");
    $stmt->bind_param("i", $unit_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Units a driver can take out: active and not already on a trip.
function get_available_units($conn)
{
    $stmt = $conn->prepare("SELECT * FROM jeepneys j
                            WHERE j.status = 'active'
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

function add_unit($conn, $code, $plate, $capacity, $operator_id)
{
    $stmt = $conn->prepare("INSERT INTO jeepneys (operator_id, unit_code, plate_no, capacity, status)
                            VALUES (?, ?, ?, ?, 'active')");
    $stmt->bind_param("issi", $operator_id, $code, $plate, $capacity);
    $stmt->execute();
}

function set_unit_status($conn, $unit_id, $status)
{
    $stmt = $conn->prepare("UPDATE jeepneys SET status = ? WHERE unit_id = ?");
    $stmt->bind_param("si", $status, $unit_id);
    $stmt->execute();
}

// Every unit with its current trip, live count and last GPS point. Feeds both maps.
function get_fleet($conn)
{
    $stmt = $conn->prepare("SELECT j.unit_id, j.unit_code, j.plate_no, j.capacity, j.status,
                                   t.trip_id, r.route_name, u.full_name AS driver_name,
                                   (SELECT onboard FROM passenger_logs pl WHERE pl.trip_id = t.trip_id
                                    ORDER BY pl.count_id DESC LIMIT 1) AS onboard,
                                   g.latitude, g.longitude, g.recorded_at
                            FROM jeepneys j
                            LEFT JOIN trips t ON t.unit_id = j.unit_id AND t.ended_at IS NULL
                            LEFT JOIN routes r ON r.route_id = t.route_id
                            LEFT JOIN users u ON u.user_id = t.driver_id
                            LEFT JOIN gps_pings g ON g.ping_id = (SELECT MAX(ping_id) FROM gps_pings gp WHERE gp.unit_id = j.unit_id)
                            ORDER BY j.unit_code");
    $stmt->execute();
    $units = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($units as $i => $unit) {
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

function add_gps_ping($conn, $unit_id, $lat, $lng)
{
    $stmt = $conn->prepare("INSERT INTO gps_pings (unit_id, latitude, longitude, recorded_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("idd", $unit_id, $lat, $lng);
    $stmt->execute();
}


// ---------- Routes ----------

function get_routes($conn)
{
    $stmt = $conn->prepare("SELECT * FROM routes ORDER BY route_name");
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
    $stmt = $conn->prepare("INSERT INTO routes (route_name, origin, destination, base_fare) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssd", $name, $origin, $destination, $fare);
    $stmt->execute();
}

function update_route_fare($conn, $route_id, $fare)
{
    $stmt = $conn->prepare("UPDATE routes SET base_fare = ? WHERE route_id = ?");
    $stmt->bind_param("di", $fare, $route_id);
    $stmt->execute();
}
