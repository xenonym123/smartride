<?php
// Log out. The sidebar's Log out button posts here with the CSRF token.
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    go('home.php');
}

// Only log out from our own button (POST + token), not from a link on another site.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !check_csrf()) {
    go(home_for_role($_SESSION['role']));
}

add_log($conn, (int) $_SESSION['user_id'], 'logout', 'Signed out');
sign_out_user($conn); // ends the session and starts a fresh one with a new id

flash('success', "You're signed out.");
go('login.php');
