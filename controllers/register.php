<?php
// Create an account. Only passengers can sign up here.
// Drivers and admins are added by an admin, so nobody can make themselves an admin.
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../models/user_model.php';
require_once '../models/wallet_model.php';
require_once '../models/notification_model.php';

session_start();

if (isset($_SESSION['user_id'])) {
    go(home_for_role($_SESSION['role']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('register.php');

    $d = user_form_input();
    $errors = validate_user($conn, $d, $_POST['password_confirm'] ?? '');

    if ($errors) {
        $_SESSION['errors'] = $errors;
        remember_input(['full_name', 'email', 'phone', 'username']);
        go('register.php');
    }

    // Save the user and their wallet together. If one fails, neither is saved.
    $conn->begin_transaction();
    try {
        $user_id = create_user($conn, $d, 'passenger'); // role is fixed, not taken from the form
        create_wallet($conn, $user_id);
        $conn->commit();
    } catch (Exception $ex) {
        $conn->rollback();
        throw $ex;
    }

    forget_input();
    add_log($conn, $user_id, 'register', 'New passenger account ' . $d['username']);
    add_notification($conn, $user_id, 'welcome', 'Welcome to SmartRide', 'Reload your wallet, then show your QR when you board.', 'passenger_reload.php');
    notify_role($conn, 'admin', 'new_passenger', 'New passenger', $d['full_name'] . ' (' . $d['username'] . ') just signed up.', 'admin_users.php');
    sign_in_user($conn, get_user_by_id($conn, $user_id));
    flash('success', 'Welcome to SmartRide! Your ride QR is ready. Load your wallet to start riding.');
    go('passenger_home.php');
}

$errors = $_SESSION['errors'] ?? [];
unset($_SESSION['errors']);

$title = 'Create an account';
include '../views/auth/register.php';
forget_input(); // the form has been shown, clear what was typed
