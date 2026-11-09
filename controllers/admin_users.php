<?php
// Admin: User Management. Lists accounts, and deactivates or reactivates them.
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../models/user_model.php';

session_start();
require_role('admin');

$admin_id = (int) $_SESSION['user_id'];

// Deactivate / Reactivate button. Accounts are never deleted, so their rides stay on record.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('admin_users.php');

    $user_id = (int) input('user_id');
    $account = get_user_by_id($conn, $user_id);

    if ($account && $user_id !== $admin_id) { // you can't deactivate yourself
        $new_status = $account['status'] === 'active' ? 'inactive' : 'active';
        set_user_status($conn, $user_id, $new_status);
        if ($new_status === 'active') {
            add_log($conn, $admin_id, 'user_status', 'Reactivated ' . $account['username']);
            flash('success', $account['full_name'] . ' can sign in again.');
        } else {
            add_log($conn, $admin_id, 'user_status', 'Deactivated ' . $account['username']);
            flash('success', $account['full_name'] . ' is deactivated.');
        }
    }
    go('admin_users.php');
}

// Only accept the three real roles as a filter.
$role = input('role');
if (!in_array($role, ['passenger', 'driver', 'admin'], true)) {
    $role = '';
}
$q = mb_substr(input('q'), 0, 60);

$users = get_user_list($conn, $role, $q);
$counts = count_users_by_role($conn);

$title = 'User management';
$current_page = 'admin_users.php';
include '../views/admin/users.php';
