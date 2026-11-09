<?php
// Admin: download every account as a CSV file.
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../models/user_model.php';

session_start();
require_role('admin');

$rows = [];
foreach (get_user_list($conn) as $u) {
    $rows[] = [$u['user_id'], $u['full_name'], $u['username'], $u['role_name'], $u['email'], $u['phone'],
               $u['balance'] ?? '', $u['status'], $u['created_at']];
}
send_csv('smartride-users-' . date('Y-m-d') . '.csv',
    ['ID', 'Name', 'Username', 'Role', 'Email', 'Phone', 'Wallet balance', 'Status', 'Created'], $rows);
