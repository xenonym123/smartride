<?php
// password_resets table: forgot-password links.
// The link has a random token. We only save its SHA-256 hash, so even someone who
// reads the database can't use the links. Each link works once, for 30 minutes.

// Makes a new link for this user. Returns the token to put in the email.
function create_reset_token($conn, $user_id)
{
    $token = bin2hex(random_bytes(32));          // 64 random characters
    $hash = hash('sha256', $token);
    $stmt = $conn->prepare("INSERT INTO password_resets (user_id, token_hash, created_at, expires_at)
                            VALUES (?, ?, NOW(), NOW() + INTERVAL 30 MINUTE)");
    $stmt->bind_param("is", $user_id, $hash);
    $stmt->execute();
    return $token;
}

// How many links this user asked for in the last hour (we allow 3).
function count_recent_resets($conn, $user_id)
{
    $stmt = $conn->prepare("SELECT COUNT(*) AS n FROM password_resets
                            WHERE user_id = ? AND created_at > NOW() - INTERVAL 1 HOUR");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return (int) $stmt->get_result()->fetch_assoc()['n'];
}

// The reset row for a token, only if it's unused and not expired. Otherwise null.
function find_valid_reset($conn, $token)
{
    $hash = hash('sha256', (string) $token);
    $stmt = $conn->prepare("SELECT r.*, u.username, u.full_name, u.email FROM password_resets r
                            JOIN users u ON u.user_id = r.user_id
                            WHERE r.token_hash = ? AND r.used_at IS NULL AND r.expires_at > NOW() AND u.status = 'active'");
    $stmt->bind_param("s", $hash);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// After the password is changed, kill this link and every other open link for the user.
function use_up_resets($conn, $user_id)
{
    $stmt = $conn->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
}
