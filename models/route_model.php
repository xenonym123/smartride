<?php
// route_stops and route_paths tables: the landmarks on each route (A to B) and the
// road line between them. Also where a passenger is getting off on their current ride.

function get_route_stops($conn, $route_id)
{
    $stmt = $conn->prepare("SELECT * FROM route_stops WHERE route_id = ? ORDER BY seq");
    $stmt->bind_param("i", $route_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Puts a landmark at $position (1 = first). The ones after it move down one place.
function add_route_stop($conn, $route_id, $position, $name, $lat, $lng, $geocoded)
{
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE route_stops SET seq = seq + 1 WHERE route_id = ? AND seq >= ?");
        $stmt->bind_param("ii", $route_id, $position);
        $stmt->execute();

        $stmt = $conn->prepare("INSERT INTO route_stops (route_id, seq, name, latitude, longitude, geocoded) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisddi", $route_id, $position, $name, $lat, $lng, $geocoded);
        $stmt->execute();
        $conn->commit();
    } catch (Exception $ex) {
        $conn->rollback();
        throw $ex;
    }
    clear_route_path($conn, $route_id);
}

// Removes a landmark and closes the gap in the order. Returns its route id, or 0.
function delete_route_stop($conn, $stop_id)
{
    $stmt = $conn->prepare("SELECT route_id, seq FROM route_stops WHERE stop_id = ?");
    $stmt->bind_param("i", $stop_id);
    $stmt->execute();
    $stop = $stmt->get_result()->fetch_assoc();
    if (!$stop) {
        return 0;
    }
    $route_id = (int) $stop['route_id'];
    $seq = (int) $stop['seq'];

    $stmt = $conn->prepare("DELETE FROM route_stops WHERE stop_id = ?");
    $stmt->bind_param("i", $stop_id);
    $stmt->execute();
    $stmt = $conn->prepare("UPDATE route_stops SET seq = seq - 1 WHERE route_id = ? AND seq > ?");
    $stmt->bind_param("ii", $route_id, $seq);
    $stmt->execute();

    clear_route_path($conn, $route_id);
    return $route_id;
}

// A landmark's position, after Geoapify found it by name.
function update_stop_position($conn, $stop_id, $lat, $lng)
{
    $stmt = $conn->prepare("UPDATE route_stops SET latitude = ?, longitude = ?, geocoded = 1 WHERE stop_id = ?");
    $stmt->bind_param("ddi", $lat, $lng, $stop_id);
    $stmt->execute();
}

// "Look these up again on Geoapify" (admin button).
function reset_route_geocoding($conn, $route_id)
{
    $stmt = $conn->prepare("UPDATE route_stops SET geocoded = 0 WHERE route_id = ?");
    $stmt->bind_param("i", $route_id);
    $stmt->execute();
    clear_route_path($conn, $route_id);
}


// ---------- Saved road lines ----------

function get_saved_route_path($conn, $route_id)
{
    $stmt = $conn->prepare("SELECT * FROM route_paths WHERE route_id = ?");
    $stmt->bind_param("i", $route_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function save_route_path($conn, $route_id, $points, $meters, $seconds, $source)
{
    $json = json_encode($points);
    $stmt = $conn->prepare("REPLACE INTO route_paths (route_id, points, meters, seconds, source, updated_at)
                            VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("isiis", $route_id, $json, $meters, $seconds, $source);
    $stmt->execute();
}

function clear_route_path($conn, $route_id)
{
    $stmt = $conn->prepare("DELETE FROM route_paths WHERE route_id = ?");
    $stmt->bind_param("i", $route_id);
    $stmt->execute();
}


// ---------- The passenger's current ride and drop-off ----------

// The passenger's paid fare on a trip that's still running, with where they're getting off.
function get_current_ride($conn, $wallet_id)
{
    $stmt = $conn->prepare("SELECT f.txn_id, f.dest_stop_id, f.arrival_sent_at, t.trip_id, t.route_id,
                                   r.route_name, j.unit_code, s.name AS dest_name
                            FROM fare_transactions f
                            JOIN trips t ON t.trip_id = f.trip_id
                            JOIN routes r ON r.route_id = t.route_id
                            JOIN jeepneys j ON j.unit_id = t.unit_id
                            LEFT JOIN route_stops s ON s.stop_id = f.dest_stop_id
                            WHERE f.wallet_id = ? AND f.status = 'paid' AND t.ended_at IS NULL
                            ORDER BY f.scanned_at DESC LIMIT 1");
    $stmt->bind_param("i", $wallet_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Saves the drop-off landmark. Only works for a landmark on that ride's own route.
function set_ride_destination($conn, $txn_id, $wallet_id, $stop_id)
{
    $stmt = $conn->prepare("UPDATE fare_transactions f
                            JOIN trips t ON t.trip_id = f.trip_id
                            JOIN route_stops s ON s.route_id = t.route_id AND s.stop_id = ?
                            SET f.dest_stop_id = s.stop_id, f.arrival_sent_at = NULL
                            WHERE f.txn_id = ? AND f.wallet_id = ?");
    $stmt->bind_param("iii", $stop_id, $txn_id, $wallet_id);
    $stmt->execute();
    return $stmt->affected_rows === 1;
}

// Riders on this trip who picked a drop-off and haven't been told they're arriving yet.
function get_waiting_arrivals($conn, $trip_id)
{
    $stmt = $conn->prepare("SELECT f.txn_id, w.user_id, s.name, s.latitude, s.longitude
                            FROM fare_transactions f
                            JOIN wallets w ON w.wallet_id = f.wallet_id
                            JOIN route_stops s ON s.stop_id = f.dest_stop_id
                            WHERE f.trip_id = ? AND f.status = 'paid' AND f.arrival_sent_at IS NULL");
    $stmt->bind_param("i", $trip_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Marks the "arriving" alert as sent. Returns false if it was already sent (so it goes out once).
function mark_arrival_sent($conn, $txn_id)
{
    $stmt = $conn->prepare("UPDATE fare_transactions SET arrival_sent_at = NOW() WHERE txn_id = ? AND arrival_sent_at IS NULL");
    $stmt->bind_param("i", $txn_id);
    $stmt->execute();
    return $stmt->affected_rows === 1;
}
