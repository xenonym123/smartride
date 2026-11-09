<?php
// JSON for the Notification API. Every dashboard page asks this every 10 seconds.
//   GET  ?after=0   -> new alerts after that id, plus the unread count
//   GET  (no after) -> just the newest id, so old alerts don't pop up again on page load
//   POST            -> mark everything as read
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../models/notification_model.php';

session_start();
require_role_json(['passenger', 'driver', 'admin']);

$user_id = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_csrf()) {
        json_out(['ok' => false, 'message' => 'Your session expired. Reload the page.'], 403);
    }
    mark_all_read($conn, $user_id);
    json_out(['ok' => true, 'unread' => 0]);
}

if (!isset($_GET['after'])) {
    json_out(['latest' => get_latest_notification_id($conn, $user_id), 'unread' => count_unread($conn, $user_id), 'items' => []]);
}

$items = get_new_notifications($conn, $user_id, max(0, (int) $_GET['after']));
$latest = (int) $_GET['after'];
foreach ($items as $item) {
    $latest = max($latest, (int) $item['notif_id']);
}
json_out(['latest' => $latest, 'unread' => count_unread($conn, $user_id), 'items' => $items]);
