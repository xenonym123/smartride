<?php
// users table (and roles). Every query uses a prepared statement with bind_param,
// so what a user types is never pasted into the SQL. That's what blocks SQL injection.

function get_user_by_id($conn, $user_id)
{
    $stmt = $conn->prepare("SELECT u.*, r.role_name FROM users u
                            JOIN roles r ON r.role_id = u.role_id
                            WHERE u.user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function get_user_by_username($conn, $username)
{
    $stmt = $conn->prepare("SELECT u.*, r.role_name FROM users u
                            JOIN roles r ON r.role_id = u.role_id
                            WHERE u.username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Is the username used by someone else? ($except_id = the user being edited)
function username_taken($conn, $username, $except_id = 0)
{
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id <> ?");
    $stmt->bind_param("si", $username, $except_id);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

// Adds a user. The password is stored only as a hash.
function create_user($conn, $d, $role)
{
    $hash = password_hash($d['password'], PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (role_id, full_name, email, phone, username, password_hash, status, created_at)
                            SELECT role_id, ?, ?, ?, ?, ?, 'active', NOW() FROM roles WHERE role_name = ?");
    $stmt->bind_param("ssssss", $d['full_name'], $d['email'], $d['phone'], $d['username'], $hash, $role);
    $stmt->execute();
    return $conn->insert_id;
}

function update_user_details($conn, $user_id, $d)
{
    $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, username = ? WHERE user_id = ?");
    $stmt->bind_param("ssssi", $d['full_name'], $d['email'], $d['phone'], $d['username'], $user_id);
    $stmt->execute();
}

function set_user_role($conn, $user_id, $role)
{
    $stmt = $conn->prepare("UPDATE users SET role_id = (SELECT role_id FROM roles WHERE role_name = ?) WHERE user_id = ?");
    $stmt->bind_param("si", $role, $user_id);
    $stmt->execute();
}

function set_user_password($conn, $user_id, $password)
{
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
    $stmt->bind_param("si", $hash, $user_id);
    $stmt->execute();
}

function set_user_status($conn, $user_id, $status)
{
    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
    $stmt->bind_param("si", $status, $user_id);
    $stmt->execute();
}

// User Management table. $role and $search are optional filters.
function get_user_list($conn, $role = '', $search = '')
{
    $sql = "SELECT u.user_id, u.full_name, u.username, u.email, u.phone, u.status, u.created_at, r.role_name,
                   w.balance, w.status AS wallet_status,
                   (SELECT MAX(login_at) FROM login_sessions ls WHERE ls.user_id = u.user_id) AS last_login
            FROM users u
            JOIN roles r ON r.role_id = u.role_id
            LEFT JOIN wallets w ON w.user_id = u.user_id
            WHERE 1 = 1";
    $types = "";
    $params = [];

    if ($role !== '') {
        $sql .= " AND r.role_name = ?";
        $types .= "s";
        $params[] = $role;
    }
    if ($search !== '') {
        $like = '%' . $search . '%';
        $sql .= " AND (u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
        $types .= "sss";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    $sql .= " ORDER BY r.role_id, u.full_name";

    $stmt = $conn->prepare($sql);
    if ($types !== "") {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// ['passenger' => 30, 'driver' => 3, 'admin' => 1], active accounts only.
function count_users_by_role($conn)
{
    $stmt = $conn->prepare("SELECT r.role_name, COUNT(u.user_id) AS n FROM roles r
                            LEFT JOIN users u ON u.role_id = r.role_id AND u.status = 'active'
                            GROUP BY r.role_name");
    $stmt->execute();
    $counts = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $counts[$row['role_name']] = (int) $row['n'];
    }
    return $counts;
}

// "Text me" (PhilSMS) and "Email me" (Gmail) switches on the Profile page.
function set_alert_settings($conn, $user_id, $sms_alerts, $email_alerts)
{
    $sms = $sms_alerts ? 1 : 0;
    $email = $email_alerts ? 1 : 0;
    $stmt = $conn->prepare("UPDATE users SET sms_alerts = ?, email_alerts = ? WHERE user_id = ?");
    $stmt->bind_param("iii", $sms, $email, $user_id);
    $stmt->execute();
}

// For forgot password: the active account with this email, or null.
function get_user_by_email($conn, $email)
{
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND status = 'active' LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}
