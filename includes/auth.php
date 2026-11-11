<?php
// Sessions, sign in / sign out, page access by role, and CSRF tokens.

require_once __DIR__ . '/../models/log_model.php';
require_once __DIR__ . '/onesignal.php'; // the dashboard footer links each signed-in user to OneSignal

// Session cookie settings. These must run before session_start().
ini_set('session.cookie_httponly', '1');   // JavaScript can't read the cookie
ini_set('session.cookie_samesite', 'Lax'); // other sites can't send it along with a form
ini_set('session.use_strict_mode', '1');   // ignore session ids the server didn't make
session_name('smartride_session');


// Called after the password is checked. Starts a fresh session for this user.
function sign_in_user($conn, $user)
{
    // New session id on login, so an old or stolen id is useless (session fixation).
    session_regenerate_id(true);

    $_SESSION['user_id']   = (int) $user['user_id'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role']      = $user['role_name']; // from the database, never from the form
    $_SESSION['login_session_id'] = start_login_session($conn, (int) $user['user_id']);
}

function sign_out_user($conn)
{
    if (!empty($_SESSION['login_session_id'])) {
        end_login_session($conn, (int) $_SESSION['login_session_id']);
    }
    session_unset();
    session_destroy();

    // Start a clean session with a new id so we can still show "You're signed out".
    session_start();
    session_regenerate_id(true);
}

// Where each role goes after signing in.
function home_for_role($role)
{
    if ($role === 'admin') return 'admin_home.php';
    if ($role === 'driver') return 'driver_home.php';
    if ($role === 'passenger') return 'passenger_home.php';
    return 'login.php';
}

// Put this at the top of every dashboard page. Stops the page unless the
// visitor is signed in with the right role.
function require_role($role)
{
    // Don't let the browser cache dashboard pages, so Back after logout shows nothing.
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    if (!isset($_SESSION['user_id'])) {
        flash('error', 'Please sign in first.');
        go('login.php');
    }
    if ($_SESSION['role'] !== $role) {
        go(home_for_role($_SESSION['role']));
    }
}

// Same check for the JSON pages (api_*.php). Answers with JSON instead of a redirect.
function require_role_json($roles)
{
    header('Cache-Control: no-store');
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], $roles, true)) {
        json_out(['ok' => false, 'message' => 'Please sign in again.'], 401);
    }
}


// ---------- CSRF ----------
// Every form that changes something carries a secret token. A fake form on
// another website can't know it, so its request gets rejected.

function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field()
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

// True if the form (or the X-CSRF header from app.js) has the right token.
function check_csrf()
{
    $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
    return is_string($token) && hash_equals(csrf_token(), $token);
}

// For normal forms: if the token is wrong, go back to $page with a message.
function require_csrf($page)
{
    if (!check_csrf()) {
        flash('error', 'Your session expired. Please try again.');
        go($page);
    }
}
