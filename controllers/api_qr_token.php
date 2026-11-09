<?php
// JSON for the passenger's QR. The My QR page calls this every 30 seconds to draw the next code.
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../models/wallet_model.php';

session_start();
require_role_json(['passenger']);

$wallet = get_my_wallet($conn, (int) $_SESSION['user_id']);
$data = qr_payload($wallet);
$data['balance'] = peso($wallet['balance']);
if ($wallet['status'] === 'frozen') {
    $data['payload'] = null; // a frozen QR shows nothing
}
json_out($data);
