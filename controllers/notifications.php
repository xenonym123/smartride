<?php
// Notifications page, for every role. Opening it marks everything as read.
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../models/notification_model.php';
require_once '../models/trip_model.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    flash('error', 'Please sign in first.');
    go('login.php');
}
require_role($_SESSION['role']); // any signed-in role; also turns off caching

$user_id = (int) $_SESSION['user_id'];
$notifications = get_notifications($conn, $user_id, 60);
mark_all_read($conn, $user_id);

if ($_SESSION['role'] === 'driver') {
    $trip = get_active_trip($conn, $user_id); // keeps the GPS beacon on
}

$title = 'Notifications';
$current_page = 'notifications.php';
include '../views/notifications.php';
