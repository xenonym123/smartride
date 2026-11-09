<?php
// Passenger: My QR. Shows the live QR, and freezes or unfreezes it.
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../models/wallet_model.php';
require_once '../models/notification_model.php';

session_start();
require_role('passenger');

$user_id = (int) $_SESSION['user_id'];
$wallet = get_my_wallet($conn, $user_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('passenger_qr.php');

    if (input('action') === 'freeze') {
        freeze_wallet($conn, $wallet['wallet_id']);
        add_log($conn, $user_id, 'qr_frozen', 'Froze ride QR');
        add_notification($conn, $user_id, 'qr', 'Your QR is frozen', 'No one can pay with it until you unfreeze it.', 'passenger_qr.php');
        flash('success', 'Your QR is frozen. No one can pay with it until you unfreeze it.');
    } elseif (input('action') === 'unfreeze') {
        unfreeze_wallet($conn, $wallet['wallet_id']);
        add_log($conn, $user_id, 'qr_unfrozen', 'Unfroze ride QR with a new code');
        add_notification($conn, $user_id, 'qr', 'Your QR works again', 'It has a brand-new code. Old screenshots are useless.', 'passenger_qr.php');
        flash('success', 'Your QR works again. It has a brand-new code, so old screenshots are useless.');
    }
    go('passenger_qr.php');
}

$title = 'My QR';
$current_page = 'passenger_qr.php';
include '../views/passenger/qr.php';
