<?php
// The operator numbers on the admin side: fares per unit and per route, load, peak hour.

require_once __DIR__ . '/wallet_model.php';

function get_report_summary($conn, $from)
{
    $stmt = $conn->prepare("SELECT COALESCE(SUM(CASE WHEN status = 'paid' THEN amount END), 0) AS revenue,
                                   COALESCE(SUM(status = 'paid'), 0) AS paid,
                                   COALESCE(SUM(status = 'blocked_full'), 0) AS blocked_full
                            FROM fare_transactions WHERE scanned_at >= ?");
    $stmt->bind_param("s", $from);
    $stmt->execute();
    $summary = $stmt->get_result()->fetch_assoc();
    $summary['avg_fare'] = $summary['paid'] ? $summary['revenue'] / $summary['paid'] : 0;

    // The hour of the day with the most paid scans.
    $stmt = $conn->prepare("SELECT HOUR(scanned_at) AS h, COUNT(*) AS n FROM fare_transactions
                            WHERE status = 'paid' AND scanned_at >= ?
                            GROUP BY HOUR(scanned_at) ORDER BY n DESC LIMIT 1");
    $stmt->bind_param("s", $from);
    $stmt->execute();
    $peak = $stmt->get_result()->fetch_assoc();
    $summary['peak_hour'] = $peak ? (int) $peak['h'] : null;

    $reloads = reload_totals_since($conn, $from);
    $summary['reloads'] = $reloads['total'];
    return $summary;
}

// Per jeepney: trips, passengers, revenue, blocked scans, and how full it ran on average.
function get_report_by_unit($conn, $from)
{
    $stmt = $conn->prepare("SELECT j.unit_code, j.capacity, COUNT(DISTINCT t.trip_id) AS trips,
                                   COALESCE(SUM(f.status = 'paid'), 0) AS passengers,
                                   COALESCE(SUM(CASE WHEN f.status = 'paid' THEN f.amount END), 0) AS revenue,
                                   COALESCE(SUM(f.status = 'blocked_full'), 0) AS blocked_full,
                                   (SELECT AVG(peak) FROM (
                                        SELECT t2.unit_id, MAX(pl.onboard) AS peak FROM trips t2
                                        JOIN passenger_logs pl ON pl.trip_id = t2.trip_id
                                        WHERE t2.started_at >= ?
                                        GROUP BY t2.trip_id, t2.unit_id) x
                                    WHERE x.unit_id = j.unit_id) AS avg_peak
                            FROM jeepneys j
                            LEFT JOIN trips t ON t.unit_id = j.unit_id AND t.started_at >= ?
                            LEFT JOIN fare_transactions f ON f.trip_id = t.trip_id
                            GROUP BY j.unit_id, j.unit_code, j.capacity
                            ORDER BY revenue DESC");
    $stmt->bind_param("ss", $from, $from);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function get_report_by_route($conn, $from)
{
    $stmt = $conn->prepare("SELECT r.route_name, r.base_fare, COUNT(DISTINCT t.trip_id) AS trips,
                                   COALESCE(SUM(f.status = 'paid'), 0) AS passengers,
                                   COALESCE(SUM(CASE WHEN f.status = 'paid' THEN f.amount END), 0) AS revenue
                            FROM routes r
                            LEFT JOIN trips t ON t.route_id = r.route_id AND t.started_at >= ?
                            LEFT JOIN fare_transactions f ON f.trip_id = t.trip_id
                            GROUP BY r.route_id, r.route_name, r.base_fare
                            ORDER BY revenue DESC");
    $stmt->bind_param("s", $from);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Fare revenue per day, for the bar chart.
function get_report_daily($conn, $from)
{
    $stmt = $conn->prepare("SELECT DATE(scanned_at) AS day, COALESCE(SUM(amount), 0) AS revenue, COUNT(*) AS paid
                            FROM fare_transactions
                            WHERE status = 'paid' AND scanned_at >= ?
                            GROUP BY DATE(scanned_at) ORDER BY day");
    $stmt->bind_param("s", $from);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
