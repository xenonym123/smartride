<?php
// Sign in page.
// The Passenger / Driver tab is only a greeting. The role comes from the account in
// the database, so an admin can sign in from either tab and still land on the admin side.
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../models/user_model.php';

session_start();

// Already signed in? Go straight to your dashboard.
if (isset($_SESSION['user_id'])) {
    go(home_for_role($_SESSION['role']));
}

$tab = input('as') === 'driver' ? 'driver' : 'passenger';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('login.php?as=' . $tab);

    $username = strtolower(input('username'));
    $password = $_POST['password'] ?? '';

    $user = null;
    if ($username !== '' && $password !== '') {
        $user = get_user_by_username($conn, $username);
    }

    // password_verify checks the typed password against the saved hash.
    // Same message whether the username or the password is wrong, so nobody
    // can use this form to find out which usernames exist.
    if (!$user || !password_verify($password, $user['password_hash'])) {
        add_log($conn, $user ? (int) $user['user_id'] : null, 'login_failed', 'Failed sign-in for "' . $username . '"');
        remember_input(['username']);
        flash('error', 'Wrong username or password.');
        go('login.php?as=' . $tab);
    }

    if ($user['status'] !== 'active') {
        flash('error', 'This account is deactivated. Ask a SmartRide admin to turn it back on.');
        go('login.php?as=' . $tab);
    }

    forget_input();
    sign_in_user($conn, $user);
    add_log($conn, (int) $user['user_id'], 'login', 'Signed in as ' . $user['role_name']);
    go(home_for_role($user['role_name']));
}

$title = 'Sign in';
include '../views/auth/login.php';
forget_input(); // the form has been shown, clear what was typed
