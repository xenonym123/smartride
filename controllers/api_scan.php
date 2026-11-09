<?php
// JSON: the jeepney's QR scanner sends each scanned code here.
// Takes the fare from the passenger's wallet, or blocks it when:
//   - the QR is fake, expired or frozen
//   - the passenger already paid on this trip
//   - the unit is full
//   - the balance is too low
// Every blocked scan is saved too, with the reason.
require_once '../config/db_connect.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../models/wallet_model.php';
require_once '../models/trip_model.php';
require_once '../models/fare_model.php';
require_once '../models/notification_model.php';

session_start();
require_role_json(['driver']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !check_csrf()) {
    json_out(['ok' => false, 'result' => 'error', 'message' => 'Your session expired. Reload the page.'], 403);
}

$driver_id = (int) $_SESSION['user_id'];
$trip = get_active_trip($conn, $driver_id);
if (!$trip) {
    json_out(['ok' => false, 'result' => 'error', 'message' => 'Start a trip first, then scan.'], 409);
}

$trip_id = (int) $trip['trip_id'];
$fare = (float) $trip['base_fare'];
$capacity = (int) $trip['capacity'];

$body = read_json();
$wallet = wallet_from_qr($conn, (string) ($body['code'] ?? ''));

// 1. Fake or old QR
if (!$wallet) {
    json_out(['ok' => false, 'result' => 'invalid', 'message' => 'Invalid or expired QR. Ask the passenger to open a fresh one.'] + get_live_data($conn, $trip));
}

$name = first_name($wallet['full_name']);
$wallet_id = (int) $wallet['wallet_id'];
$count = get_passenger_count($conn, $trip_id);

// 2. Paid already on this trip (the same QR shown twice). Not saved again.
if (already_paid($conn, $wallet_id, $trip_id)) {
    json_out(['ok' => false, 'result' => 'duplicate', 'message' => $name . ' already paid on this trip.'] + get_live_data($conn, $trip));
}

$blocked = '';
$message = '';

if ($wallet['status'] === 'frozen' || $wallet['user_status'] !== 'active') {
    // 3. Frozen QR or deactivated account
    $blocked = 'blocked_frozen';
    $message = $name . "'s QR is frozen. Fare not taken.";
} elseif ((int) $count['onboard'] >= $capacity) {
    // 4. Unit is full
    $blocked = 'blocked_full';
    $message = 'Unit full (' . $capacity . '/' . $capacity . '). ' . $name . " can't board, fare not taken.";
} else {
    // 5. Take the fare. The balance, the fare record and the count change together, or not at all.
    $conn->begin_transaction();
    try {
        $paid = debit_wallet($conn, $wallet_id, $fare); // false if the balance is too low
        if ($paid) {
            record_fare($conn, $wallet_id, $trip_id, $fare, 'paid');
            change_passenger_count($conn, $trip_id, 1);
        }
        $conn->commit();
    } catch (Exception $ex) {
        $conn->rollback();
        throw $ex;
    }

    if ($paid) {
        add_log($conn, $driver_id, 'fare_paid', peso($fare) . ' from ' . $name . ' on ' . $trip['unit_code']);

        // Notification API: tell the passenger, and warn them if they're running low.
        $passenger_id = (int) $wallet['user_id'];
        $balance = (float) get_wallet($conn, $wallet_id)['balance'];
        add_notification($conn, $passenger_id, 'fare_paid', 'Fare paid · ' . peso($fare),
            $trip['unit_code'] . ', ' . $trip['route_name'] . '. Balance: ' . peso($balance) . '.', 'passenger_rides.php');
        if ($balance < $fare * 2) {
            add_notification($conn, $passenger_id, 'low_balance', 'Low balance',
                'You have ' . peso($balance) . ' left. Reload before your next ride.', 'passenger_reload.php');
        }
        // And tell the driver when this rider filled the last seat.
        if ((int) $count['onboard'] + 1 >= $capacity) {
            add_notification($conn, $driver_id, 'unit_full', $trip['unit_code'] . ' is full',
                'All ' . $capacity . ' seats taken. The scanner refuses new fares until someone gets off.', 'driver_count.php');
        }
        json_out(['ok' => true, 'result' => 'paid', 'message' => 'Paid ' . peso($fare) . '. Welcome aboard, ' . $name . '!'] + get_live_data($conn, $trip));
    }

    // 6. Not enough money
    $blocked = 'blocked_balance';
    $message = $name . "'s balance is too low for " . peso($fare) . '.';
}

// Save the blocked scan so the passenger and the admin can see what happened.
record_fare($conn, $wallet_id, $trip_id, $fare, $blocked);
add_log($conn, $driver_id, 'fare_blocked', $trip['unit_code'] . ': ' . $message);

// Notification API: tell the passenger why the fare didn't go through.
$passenger_id = (int) $wallet['user_id'];
if ($blocked === 'blocked_full') {
    add_notification($conn, $passenger_id, 'fare_blocked', $trip['unit_code'] . ' is full',
        'Your fare was not taken. Wait for the next unit, or check Track a Jeepney for one with seats.', 'passenger_track.php');
} elseif ($blocked === 'blocked_balance') {
    add_notification($conn, $passenger_id, 'low_balance', 'Not enough balance',
        'The fare is ' . peso($fare) . '. Reload your wallet to ride.', 'passenger_reload.php');
} else {
    add_notification($conn, $passenger_id, 'qr_misuse', 'Someone scanned your frozen QR',
        'On ' . $trip['unit_code'] . '. No fare was taken. If that wasn\'t you, keep your QR frozen.', 'passenger_qr.php');
}
json_out(['ok' => false, 'result' => $blocked, 'message' => $message] + get_live_data($conn, $trip));
