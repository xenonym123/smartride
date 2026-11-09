<?php
// Admin (operator side): Fare Reports for the last 7, 30 or 90 days.
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../models/report_model.php';

session_start();
require_role('admin');

$ranges = [7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days'];
$days = (int) input('days', '7');
if (!isset($ranges[$days])) {
    $days = 7;
}
$from = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));

$summary = get_report_summary($conn, $from);
$units = get_report_by_unit($conn, $from);
$routes = get_report_by_route($conn, $from);
$daily = get_report_daily($conn, $from);

$title = 'Fare reports';
$current_page = 'admin_reports.php';
include '../views/admin/reports.php';
