<?php
// activity_logs and login_sessions tables. Feeds the admin's System Logs page.

function add_log($conn, $user_id, $action, $details = '')
{
    $details = mb_substr($details, 0, 255);
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details, logged_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iss", $user_id, $action, $details);
    $stmt->execute();
}

// The filter buttons on the logs page, and which actions each one shows.
function log_groups()
{
    return [
        'login' => ['Logins', ['login', 'logout', 'login_failed', 'register']],
        'money' => ['Reloads and fares', ['reload', 'fare_paid', 'fare_blocked']],
        'trips' => ['Trips and units', ['trip_start', 'trip_end', 'unit_status', 'unit_added', 'route_added', 'fare_changed']],
        'admin' => ['Account changes', ['user_added', 'user_updated', 'user_status', 'profile_updated', 'password_changed', 'password_reset', 'qr_frozen', 'qr_unfrozen']],
    ];
}

// Newest logs first. $group is one of the keys above, or '' for everything.
function get_logs($conn, $group = '', $limit = 60, $offset = 0)
{
    $groups = log_groups();
    $sql = "SELECT l.*, u.full_name, u.username FROM activity_logs l
            LEFT JOIN users u ON u.user_id = l.user_id";
    $types = "";
    $params = [];

    if (isset($groups[$group])) {
        $actions = $groups[$group][1];
        // One ? per action: "IN (?, ?, ?)"
        $sql .= " WHERE l.action IN (" . implode(', ', array_fill(0, count($actions), '?')) . ")";
        $types .= str_repeat("s", count($actions));
        $params = $actions;
    }
    $sql .= " ORDER BY l.log_id DESC LIMIT ? OFFSET ?";
    $types .= "ii";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function count_logs($conn, $group = '')
{
    $groups = log_groups();
    if (!isset($groups[$group])) {
        $stmt = $conn->prepare("SELECT COUNT(*) AS n FROM activity_logs");
        $stmt->execute();
        return (int) $stmt->get_result()->fetch_assoc()['n'];
    }
    $actions = $groups[$group][1];
    $stmt = $conn->prepare("SELECT COUNT(*) AS n FROM activity_logs
                            WHERE action IN (" . implode(', ', array_fill(0, count($actions), '?')) . ")");
    $stmt->bind_param(str_repeat("s", count($actions)), ...$actions);
    $stmt->execute();
    return (int) $stmt->get_result()->fetch_assoc()['n'];
}


// ---------- Login sessions ----------

function start_login_session($conn, $user_id)
{
    $stmt = $conn->prepare("INSERT INTO login_sessions (user_id, login_at) VALUES (?, NOW())");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $conn->insert_id;
}

function end_login_session($conn, $session_id)
{
    $stmt = $conn->prepare("UPDATE login_sessions SET logout_at = NOW() WHERE session_id = ? AND logout_at IS NULL");
    $stmt->bind_param("i", $session_id);
    $stmt->execute();
}
