<?php
// Forgot password: emails a reset link through the Gmail API.
// The answer is the same whether or not the email is in SmartRide, so this page
// can't be used to find out who has an account.
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/outbox.php';
require_once '../models/user_model.php';
require_once '../models/reset_model.php';

session_start();

if (isset($_SESSION['user_id'])) {
    go(home_for_role($_SESSION['role']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('forgot_password.php');
    $email = strtolower(input('email'));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        remember_input(['email']);
        flash('error', 'Enter the email address on your account.');
        go('forgot_password.php');
    }

    $user = get_user_by_email($conn, $email);
    // At most 3 links an hour per account, so nobody can flood someone's inbox.
    if ($user && count_recent_resets($conn, (int) $user['user_id']) < 3) {
        $token = create_reset_token($conn, (int) $user['user_id']);
        $link = app_url('reset_password.php?token=' . $token);
        add_log($conn, (int) $user['user_id'], 'password_reset', 'Asked for a password reset link');

        if (gmail_ready()) {
            $html = email_html('Reset your SmartRide password', [
                'Hi ' . e(first_name($user['full_name'])) . ',',
                'Someone (hopefully you) asked to reset the password for <b>' . e($user['username']) . '</b>.',
                'The link works once and expires in 30 minutes. If you didn\'t ask for this, just ignore this email.',
            ], 'Choose a new password', $link);
            queue_message('email', $user['email'], 'Reset your SmartRide password', $html);
        } else {
            // DEMO MODE (no Gmail keys): the link goes to the PHP error log, never to the screen.
            error_log('SmartRide DEMO (Gmail not set up) reset link for ' . $user['username'] . ': ' . $link);
        }
    }

    forget_input();
    $_SESSION['reset_sent'] = true;
    go('forgot_password.php');
}

$sent = !empty($_SESSION['reset_sent']);
unset($_SESSION['reset_sent']);
$demo = !gmail_ready();

$title = 'Forgot password';
include '../views/auth/forgot.php';
forget_input();
