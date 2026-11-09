<?php
// Passenger: Profile. Any change needs the current password.
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../models/user_model.php';
require_once '../models/wallet_model.php';

session_start();
require_role('passenger');

$user_id = (int) $_SESSION['user_id'];
$account = get_user_by_id($conn, $user_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('passenger_profile.php');

    $d = user_form_input();
    $d['password'] = $_POST['new_password'] ?? '';
    $errors = validate_user($conn, $d, $_POST['new_password_confirm'] ?? '', $user_id, false);

    if (!password_verify($_POST['current_password'] ?? '', $account['password_hash'])) {
        $errors['current_password'] = 'Your current password is wrong.';
    }
    if ($errors) {
        $_SESSION['errors'] = $errors;
        remember_input(['full_name', 'email', 'phone', 'username']);
        go('passenger_profile.php');
    }

    update_user_details($conn, $user_id, $d);
    if ($d['password'] !== '') {
        set_user_password($conn, $user_id, $d['password']);
        add_log($conn, $user_id, 'password_changed', 'Changed password');
    }
    add_log($conn, $user_id, 'profile_updated', 'Updated profile details');

    // Keep the name in the sidebar up to date.
    $_SESSION['full_name'] = $d['full_name'];
    $_SESSION['username'] = $d['username'];

    forget_input();
    flash('success', 'Your profile is saved.');
    go('passenger_profile.php');
}

$errors = $_SESSION['errors'] ?? [];
unset($_SESSION['errors']);
$wallet = get_my_wallet($conn, $user_id);

$title = 'Profile';
$current_page = 'passenger_profile.php';
include '../views/passenger/profile.php';
forget_input(); // the form has been shown, clear what was typed
