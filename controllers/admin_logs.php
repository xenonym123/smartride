<?php
// Admin: System Logs, 40 per page, with filters.
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

session_start();
require_role('admin');

$groups = log_groups();
$group = input('type');
if (!isset($groups[$group])) {
    $group = '';
}

$per_page = 40;
$total = count_logs($conn, $group);
$pages = max(1, (int) ceil($total / $per_page));
$page = min($pages, max(1, (int) input('page', '1')));
$logs = get_logs($conn, $group, $per_page, ($page - 1) * $per_page);

$title = 'System logs';
$current_page = 'admin_logs.php';
include '../views/admin/logs.php';
