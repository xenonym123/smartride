<?php
// notifications table. SmartRide saves an alert here (fare paid, low balance, unit full...).
// The page asks api_notifications.php for new ones every few seconds, and the
// browser's Notification API pops them up on the phone or computer.

function add_notification($conn, $user_id, $type, $title, $body = '', $link = '')
{
    $title = mb_substr($title, 0, 100);
    $body = mb_substr($body, 0, 255);
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, body, link, is_read, created_at)
                            VALUES (?, ?, ?, ?, ?, 0, NOW())");
    $stmt->bind_param("issss", $user_id, $type, $title, $body, $link);
    $stmt->execute();
}

// Send the same alert to every active account with a role (for example all admins).
function notify_role($conn, $role, $type, $title, $body = '', $link = '')
{
    $stmt = $conn->prepare("SELECT u.user_id FROM users u JOIN roles r ON r.role_id = u.role_id
                            WHERE r.role_name = ? AND u.status = 'active'");
    $stmt->bind_param("s", $role);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        add_notification($conn, (int) $row['user_id'], $type, $title, $body, $link);
    }
}

// Newest first, for the Notifications page.
function get_notifications($conn, $user_id, $limit = 50)
{
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY notif_id DESC LIMIT ?");
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Only the ones newer than $after_id (what the browser hasn't seen yet), oldest first.
function get_new_notifications($conn, $user_id, $after_id)
{
    $stmt = $conn->prepare("SELECT notif_id, type, title, body, link, created_at FROM notifications
                            WHERE user_id = ? AND notif_id > ? ORDER BY notif_id LIMIT 10");
    $stmt->bind_param("ii", $user_id, $after_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function get_latest_notification_id($conn, $user_id)
{
    $stmt = $conn->prepare("SELECT COALESCE(MAX(notif_id), 0) AS id FROM notifications WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return (int) $stmt->get_result()->fetch_assoc()['id'];
}

function count_unread($conn, $user_id)
{
    $stmt = $conn->prepare("SELECT COUNT(*) AS n FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return (int) $stmt->get_result()->fetch_assoc()['n'];
}

function mark_all_read($conn, $user_id)
{
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
}
