<?php
// Reset password: opened from the link in the email. Sets a new password.
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/outbox.php';
require_once '../models/user_model.php';
require_once '../models/reset_model.php';

session_start();

$token = input('token');
$reset = find_valid_reset($conn, $token);

if (!$reset) {
    flash('error', 'That reset link is expired or was already used. Ask for a new one.');
    go('forgot_password.php');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('reset_password.php?token=' . urlencode($token));
    $password = $_POST['password'] ?? '';

    if (strlen($password) < 8) {
        $errors['password'] = 'Use at least 8 characters.';
    } elseif ($password !== ($_POST['password_confirm'] ?? '')) {
        $errors['password_confirm'] = "The passwords don't match.";
    }

    if (!$errors) {
        $user_id = (int) $reset['user_id'];
        set_user_password($conn, $user_id, $password);  // saved as a hash (password_hash)
        use_up_resets($conn, $user_id);                  // this link and any others stop working
        add_log($conn, $user_id, 'password_changed', 'Reset password from the email link');

        if (gmail_ready()) {
            $html = email_html('Your password was changed', [
                'The password for <b>' . e($reset['username']) . '</b> was just changed.',
                'If this wasn\'t you, reset it again right away and tell a SmartRide admin.',
            ]);
            queue_message('email', $reset['email'], 'Your SmartRide password was changed', $html);
        }

        flash('success', 'Password changed. Sign in with your new password.');
        go('login.php');
    }
}

$title = 'Choose a new password';
include '../views/auth/reset.php';
