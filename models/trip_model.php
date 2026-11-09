<?php
// trips and passenger_logs tables.
// A trip is one run of one jeepney, on one route, with one driver.
// passenger_logs keeps a running count for the trip: boarded, got off, on board now.

require_once __DIR__ . '/fare_model.php';

// The driver's trip that hasn't ended yet, or null.
function get_active_trip($conn, $driver_id)
{
    $stmt = $conn->prepare("SELECT t.*, j.unit_code, j.capacity, j.plate_no, r.route_name, r.base_fare
                            FROM trips t
                            JOIN jeepneys j ON j.unit_id = t.unit_id
                            JOIN routes r ON r.route_id = t.route_id
                            WHERE t.driver_id = ? AND t.ended_at IS NULL
                            ORDER BY t.started_at DESC LIMIT 1");
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Starts a trip and its passenger count at zero. Both saved together, or neither.
function start_trip($conn, $driver_id, $unit_id, $route_id)
{
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT INTO trips (unit_id, route_id, driver_id, started_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("iii", $unit_id, $route_id, $driver_id);
        $stmt->execute();
        $trip_id = $conn->insert_id;

        add_passenger_count($conn, $trip_id, 0, 0, 0);
        $conn->commit();
        return $trip_id;
    } catch (Exception $ex) {
        $conn->rollback();
        throw $ex;
    }
}

function end_trip($conn, $trip_id)
{
    $stmt = $conn->prepare("UPDATE trips SET ended_at = NOW() WHERE trip_id = ? AND ended_at IS NULL");
    $stmt->bind_param("i", $trip_id);
    $stmt->execute();
}

// Is this jeepney already out on a trip?
function unit_busy($conn, $unit_id)
{
    $stmt = $conn->prepare("SELECT trip_id FROM trips WHERE unit_id = ? AND ended_at IS NULL");
    $stmt->bind_param("i", $unit_id);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

// Today's trips for a driver, with passengers and fares.
function get_todays_trips($conn, $driver_id)
{
    $from = date('Y-m-d 00:00:00');
    $stmt = $conn->prepare("SELECT t.trip_id, t.started_at, t.ended_at, r.route_name, j.unit_code,
                                   COUNT(f.txn_id) AS passengers, COALESCE(SUM(f.amount), 0) AS collected
                            FROM trips t
                            JOIN routes r ON r.route_id = t.route_id
                            JOIN jeepneys j ON j.unit_id = t.unit_id
                            LEFT JOIN fare_transactions f ON f.trip_id = t.trip_id AND f.status = 'paid'
                            WHERE t.driver_id = ? AND t.started_at >= ?
                            GROUP BY t.trip_id, t.started_at, t.ended_at, r.route_name, j.unit_code
                            ORDER BY t.started_at DESC");
    $stmt->bind_param("is", $driver_id, $from);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// How full a driver's trips ran on each route in the last 30 days.
function get_driver_route_stats($conn, $driver_id)
{
    $from = date('Y-m-d', strtotime('-30 days'));
    $stmt = $conn->prepare("SELECT r.route_name, r.base_fare, COUNT(DISTINCT t.trip_id) AS trips,
                                   COUNT(f.txn_id) / NULLIF(COUNT(DISTINCT t.trip_id), 0) AS avg_passengers,
                                   AVG(j.capacity) AS capacity
                            FROM trips t
                            JOIN routes r ON r.route_id = t.route_id
                            JOIN jeepneys j ON j.unit_id = t.unit_id
                            LEFT JOIN fare_transactions f ON f.trip_id = t.trip_id AND f.status = 'paid'
                            WHERE t.driver_id = ? AND t.started_at >= ?
                            GROUP BY r.route_id, r.route_name, r.base_fare
                            ORDER BY trips DESC");
    $stmt->bind_param("is", $driver_id, $from);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}


// ---------- Passenger count (the onboard camera) ----------

function get_passenger_count($conn, $trip_id)
{
    $stmt = $conn->prepare("SELECT * FROM passenger_logs WHERE trip_id = ? ORDER BY count_id DESC LIMIT 1");
    $stmt->bind_param("i", $trip_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        $row = ['boarded' => 0, 'alighted' => 0, 'onboard' => 0, 'counted_at' => null];
    }
    return $row;
}

function add_passenger_count($conn, $trip_id, $boarded, $alighted, $onboard)
{
    $stmt = $conn->prepare("INSERT INTO passenger_logs (trip_id, boarded, alighted, onboard, counted_at)
                            VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("iiii", $trip_id, $boarded, $alighted, $onboard);
    $stmt->execute();
}

// Someone got on ($change = 1) or off ($change = -1). Never goes below zero.
function change_passenger_count($conn, $trip_id, $change)
{
    $last = get_passenger_count($conn, $trip_id);
    $onboard = (int) $last['onboard'];
    $boarded = (int) $last['boarded'];
    $alighted = (int) $last['alighted'];

    if ($change > 0) {
        $boarded++;
        $onboard++;
    } else {
        if ($onboard === 0) {
            return; // nobody to get off
        }
        $alighted++;
        $onboard--;
    }
    add_passenger_count($conn, $trip_id, $boarded, $alighted, $onboard);
}

// Everything the driver's Passenger Count and QR Scanner pages show, as one array.
// The pages also fetch this every few seconds as JSON.
function get_live_data($conn, $trip)
{
    if (!$trip) {
        return ['trip' => null];
    }
    $trip_id = (int) $trip['trip_id'];
    $capacity = (int) $trip['capacity'];
    $count = get_passenger_count($conn, $trip_id);
    $stats = get_trip_stats($conn, $trip_id);

    $alerts = [];
    foreach (get_recent_scans($conn, $trip_id, 8) as $scan) {
        $alerts[] = [
            'time'   => fmt_time($scan['scanned_at']),
            'name'   => first_name($scan['full_name']),
            'status' => $scan['status'],
            'amount' => peso($scan['amount']),
        ];
    }

    return [
        'trip'       => ['id' => $trip_id, 'unit' => $trip['unit_code'], 'route' => $trip['route_name']],
        'capacity'   => $capacity,
        'onboard'    => (int) $count['onboard'],
        'boarded'    => (int) $count['boarded'],
        'alighted'   => (int) $count['alighted'],
        'seats_left' => max(0, $capacity - (int) $count['onboard']),
        'full'       => (int) $count['onboard'] >= $capacity,
        'paid'       => (int) $stats['paid'],
        'collected'  => peso($stats['collected']),
        'blocked'    => (int) $stats['blocked'],
        'alerts'     => $alerts,
    ];
}
