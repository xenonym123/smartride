<?php
// Admin: download the fares-per-unit report as a CSV file.
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../models/report_model.php';

session_start();
require_role('admin');

$days = (int) input('days', '7');
if (!in_array($days, [7, 30, 90], true)) {
    $days = 7;
}
$from = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));

$rows = [];
foreach (get_report_by_unit($conn, $from) as $u) {
    $load = $u['avg_peak'] ? round($u['avg_peak'] / $u['capacity'] * 100) . '%' : '';
    $rows[] = [$u['unit_code'], $u['trips'], $u['passengers'], number_format($u['revenue'], 2, '.', ''), $u['blocked_full'], $load];
}
send_csv('smartride-fares-per-unit-' . $days . 'd.csv',
    ['Unit', 'Trips', 'Paid passengers', 'Revenue (PHP)', 'Scans blocked at full', 'Average peak load'], $rows);
