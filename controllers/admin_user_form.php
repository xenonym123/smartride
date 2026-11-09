<?php
// Admin: Add user (admin_user_form.php) or Edit user (admin_user_form.php?id=5).
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../models/user_model.php';
require_once '../models/wallet_model.php';

session_start();
require_role('admin');

$admin_id = (int) $_SESSION['user_id'];
$id = (int) input('id');
$account = null;

if ($id > 0) {
    $account = get_user_by_id($conn, $id);
    if (!$account) {
        flash('error', "That account doesn't exist.");
        go('admin_users.php');
    }
}
$form_page = $account ? 'admin_user_form.php?id=' . $id : 'admin_user_form.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf($form_page);

    $d = user_form_input();
    $role = input('role');
    if (!in_array($role, ['passenger', 'driver', 'admin'], true)) {
        $role = 'passenger';
    }
    if ($account && $id === $admin_id) {
        $role = 'admin'; // you can't demote yourself
    }

    if ($account) {
        $errors = validate_user($conn, $d, null, $id, false); // password is optional when editing
    } else {
        $errors = validate_user($conn, $d, null);
    }

    if ($errors) {
        $_SESSION['errors'] = $errors;
        $_POST['role'] = $role;
        remember_input(['full_name', 'email', 'phone', 'username', 'role']);
        go($form_page);
    }

    if ($account) {
        // Edit
        update_user_details($conn, $id, $d);
        set_user_role($conn, $id, $role);
        if ($role === 'passenger' && !get_wallet_by_user($conn, $id)) {
            create_wallet($conn, $id);
        }
        if ($d['password'] !== '') {
            set_user_password($conn, $id, $d['password']);
        }
        add_log($conn, $admin_id, 'user_updated', 'Updated account ' . $d['username'] . ($d['password'] !== '' ? ' and reset the password' : ''));
        flash('success', 'Saved ' . $d['full_name'] . '.');
    } else {
        // Add. The user and (for passengers) the wallet are saved together.
        $conn->begin_transaction();
        try {
            $new_id = create_user($conn, $d, $role);
            if ($role === 'passenger') {
                create_wallet($conn, $new_id);
            }
            $conn->commit();
        } catch (Exception $ex) {
            $conn->rollback();
            throw $ex;
        }
        add_log($conn, $admin_id, 'user_added', 'Added ' . $role . ' account ' . $d['username']);
        flash('success', $d['full_name'] . ' can now sign in as ' . $d['username'] . '.');
    }

    forget_input();
    go('admin_users.php');
}

$errors = $_SESSION['errors'] ?? [];
unset($_SESSION['errors']);
$wallet = $account ? get_wallet_by_user($conn, $id) : null;

$title = $account ? 'Edit ' . $account['full_name'] : 'Add user';
$current_page = 'admin_users.php';
include '../views/admin/user_form.php';
forget_input(); // the form has been shown, clear what was typed
