<?php
// fare_transactions table: one row per QR scan, paid or blocked.

function record_fare($conn, $wallet_id, $trip_id, $amount, $status)
{
    $stmt = $conn->prepare("INSERT INTO fare_transactions (wallet_id, trip_id, amount, status, scanned_at)
                            VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("iids", $wallet_id, $trip_id, $amount, $status);
    $stmt->execute();
}

function already_paid($conn, $wallet_id, $trip_id)
{
    $stmt = $conn->prepare("SELECT txn_id FROM fare_transactions WHERE wallet_id = ? AND trip_id = ? AND status = 'paid'");
    $stmt->bind_param("ii", $wallet_id, $trip_id);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

// A passenger's ride history.
function get_rides($conn, $wallet_id, $limit = 100)
{
    $stmt = $conn->prepare("SELECT f.*, r.route_name, j.unit_code FROM fare_transactions f
                            JOIN trips t ON t.trip_id = f.trip_id
                            JOIN routes r ON r.route_id = t.route_id
                            JOIN jeepneys j ON j.unit_id = t.unit_id
                            WHERE f.wallet_id = ?
                            ORDER BY f.scanned_at DESC LIMIT ?");
    $stmt->bind_param("ii", $wallet_id, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Rides and pesos spent this month.
function get_month_summary($conn, $wallet_id)
{
    $from = date('Y-m-01 00:00:00');
    $stmt = $conn->prepare("SELECT COUNT(*) AS rides, COALESCE(SUM(amount), 0) AS spent FROM fare_transactions
                            WHERE wallet_id = ? AND status = 'paid' AND scanned_at >= ?");
    $stmt->bind_param("is", $wallet_id, $from);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// A driver's passengers, earnings and trips from a date onward.
function get_driver_totals($conn, $driver_id, $from)
{
    $stmt = $conn->prepare("SELECT COUNT(f.txn_id) AS passengers, COALESCE(SUM(f.amount), 0) AS earnings,
                                   COUNT(DISTINCT t.trip_id) AS trips
                            FROM trips t
                            LEFT JOIN fare_transactions f ON f.trip_id = t.trip_id AND f.status = 'paid'
                            WHERE t.driver_id = ? AND t.started_at >= ?");
    $stmt->bind_param("is", $driver_id, $from);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function get_driver_by_day($conn, $driver_id, $days = 14)
{
    $from = date('Y-m-d', strtotime("-$days days"));
    $stmt = $conn->prepare("SELECT DATE(t.started_at) AS day, COUNT(DISTINCT t.trip_id) AS trips,
                                   COUNT(f.txn_id) AS passengers, COALESCE(SUM(f.amount), 0) AS earnings
                            FROM trips t
                            LEFT JOIN fare_transactions f ON f.trip_id = t.trip_id AND f.status = 'paid'
                            WHERE t.driver_id = ? AND t.started_at >= ?
                            GROUP BY DATE(t.started_at) ORDER BY day DESC");
    $stmt->bind_param("is", $driver_id, $from);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Paid scans, money collected and blocked scans for one trip.
function get_trip_stats($conn, $trip_id)
{
    $stmt = $conn->prepare("SELECT COALESCE(SUM(status = 'paid'), 0) AS paid,
                                   COALESCE(SUM(CASE WHEN status = 'paid' THEN amount END), 0) AS collected,
                                   COALESCE(SUM(status <> 'paid'), 0) AS blocked
                            FROM fare_transactions WHERE trip_id = ?");
    $stmt->bind_param("i", $trip_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Latest scans on a trip, for the driver's list.
function get_recent_scans($conn, $trip_id, $limit = 8)
{
    $stmt = $conn->prepare("SELECT f.*, u.full_name FROM fare_transactions f
                            JOIN wallets w ON w.wallet_id = f.wallet_id
                            JOIN users u ON u.user_id = w.user_id
                            WHERE f.trip_id = ?
                            ORDER BY f.scanned_at DESC, f.txn_id DESC LIMIT ?");
    $stmt->bind_param("ii", $trip_id, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Paid rides, revenue and blocked scans across all units from a date onward.
function get_fare_totals_since($conn, $from)
{
    $stmt = $conn->prepare("SELECT COALESCE(SUM(status = 'paid'), 0) AS paid,
                                   COALESCE(SUM(CASE WHEN status = 'paid' THEN amount END), 0) AS revenue,
                                   COALESCE(SUM(status = 'blocked_full'), 0) AS blocked_full,
                                   COALESCE(SUM(status <> 'paid'), 0) AS blocked
                            FROM fare_transactions WHERE scanned_at >= ?");
    $stmt->bind_param("s", $from);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}
