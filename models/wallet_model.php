<?php
// wallets and reloads tables, plus the ride QR.
//
// How the QR works: each wallet has a secret qr_token. The QR text is
//   SR-<wallet id>-<time window>-<signature>
// where the signature is an HMAC of the wallet id and the current 30-second
// window, keyed with qr_token. Only the server knows qr_token, so nobody can
// make a valid QR by hand, and a screenshot stops working within a minute.

function get_wallet_by_user($conn, $user_id)
{
    $stmt = $conn->prepare("SELECT * FROM wallets WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// A wallet with its owner's name and account status (used by the scanner).
function get_wallet($conn, $wallet_id)
{
    $stmt = $conn->prepare("SELECT w.*, u.full_name, u.status AS user_status FROM wallets w
                            JOIN users u ON u.user_id = w.user_id
                            WHERE w.wallet_id = ?");
    $stmt->bind_param("i", $wallet_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function create_wallet($conn, $user_id)
{
    $token = bin2hex(random_bytes(16));
    $stmt = $conn->prepare("INSERT INTO wallets (user_id, qr_token, balance, status, updated_at)
                            VALUES (?, ?, 0, 'active', NOW())");
    $stmt->bind_param("is", $user_id, $token);
    $stmt->execute();
    return $conn->insert_id;
}

// The signed-in passenger's wallet. Makes one if an old account has none yet.
function get_my_wallet($conn, $user_id)
{
    $wallet = get_wallet_by_user($conn, $user_id);
    if (!$wallet) {
        create_wallet($conn, $user_id);
        $wallet = get_wallet_by_user($conn, $user_id);
    }
    return $wallet;
}


// ---------- Ride QR ----------

// Which 30-second window we're in right now.
function qr_window()
{
    return intdiv(time(), QR_WINDOW);
}

function qr_sign($wallet, $window)
{
    $signature = hash_hmac('sha256', $wallet['wallet_id'] . '.' . $window, $wallet['qr_token']);
    return substr($signature, 0, 12);
}

// The text inside the QR right now, and how many seconds until it changes.
function qr_payload($wallet)
{
    $window = qr_window();
    return [
        'payload'    => 'SR-' . $wallet['wallet_id'] . '-' . $window . '-' . qr_sign($wallet, $window),
        'expires_in' => QR_WINDOW - (time() % QR_WINDOW),
        'status'     => $wallet['status'],
    ];
}

// Checks a scanned QR. Returns the wallet if the code is real and current, or null.
// Accepts this window and the one before, so a code that flips mid-scan still works.
function wallet_from_qr($conn, $text)
{
    if (!preg_match('/^SR-(\d+)-(\d+)-([a-f0-9]{12})$/', trim($text), $m)) {
        return null;
    }
    $wallet = get_wallet($conn, (int) $m[1]);
    $window = (int) $m[2];
    $now = qr_window();
    if (!$wallet || $window > $now || $window < $now - 1) {
        return null;
    }
    if (!hash_equals(qr_sign($wallet, $window), $m[3])) {
        return null;
    }
    return $wallet;
}


// ---------- Balance ----------

function credit_wallet($conn, $wallet_id, $amount)
{
    $stmt = $conn->prepare("UPDATE wallets SET balance = balance + ?, updated_at = NOW() WHERE wallet_id = ?");
    $stmt->bind_param("di", $amount, $wallet_id);
    $stmt->execute();
}

// Takes the fare only if the balance covers it. Returns false if it doesn't.
function debit_wallet($conn, $wallet_id, $amount)
{
    $stmt = $conn->prepare("UPDATE wallets SET balance = balance - ?, updated_at = NOW()
                            WHERE wallet_id = ? AND balance >= ?");
    $stmt->bind_param("did", $amount, $wallet_id, $amount);
    $stmt->execute();
    return $stmt->affected_rows === 1;
}

function freeze_wallet($conn, $wallet_id)
{
    $stmt = $conn->prepare("UPDATE wallets SET status = 'frozen', updated_at = NOW() WHERE wallet_id = ?");
    $stmt->bind_param("i", $wallet_id);
    $stmt->execute();
}

// Unfreezing also makes a new qr_token, so any old screenshot is useless.
function unfreeze_wallet($conn, $wallet_id)
{
    $token = bin2hex(random_bytes(16));
    $stmt = $conn->prepare("UPDATE wallets SET status = 'active', qr_token = ?, updated_at = NOW() WHERE wallet_id = ?");
    $stmt->bind_param("si", $token, $wallet_id);
    $stmt->execute();
}


// ---------- Reloads ----------

function add_reload($conn, $wallet_id, $amount, $method)
{
    $stmt = $conn->prepare("INSERT INTO reloads (wallet_id, amount, method, reloaded_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("ids", $wallet_id, $amount, $method);
    $stmt->execute();
}

function get_reloads($conn, $wallet_id, $limit = 50)
{
    $stmt = $conn->prepare("SELECT * FROM reloads WHERE wallet_id = ? ORDER BY reloaded_at DESC LIMIT ?");
    $stmt->bind_param("ii", $wallet_id, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Total pesos reloaded and how many reloads, from a date onward.
function reload_totals_since($conn, $from)
{
    $stmt = $conn->prepare("SELECT COUNT(*) AS n, COALESCE(SUM(amount), 0) AS total FROM reloads WHERE reloaded_at >= ?");
    $stmt->bind_param("s", $from);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}
