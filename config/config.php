<?php
// SmartRide settings. Change the database part to match your XAMPP.

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';            // XAMPP's root password is empty by default
$db_name = 'smartride_db';
$db_port = 3306;

// true  = show the real error message on screen (only while you're coding)
// false = show a friendly error page and write the details to the PHP error log
define('APP_DEBUG', false);

define('QR_WINDOW', 30);     // seconds before a ride QR changes
define('RELOAD_MIN', 20);    // smallest reload, in pesos
define('RELOAD_MAX', 5000);  // biggest reload, in pesos

// =====================================================================
// API KEYS live in config/keys.php, which is NOT uploaded to GitHub (see .gitignore).
// New copy of the project? Copy config/keys.example.php to config/keys.php and fill it in.
// Any key left empty runs that feature in DEMO MODE, so the site never breaks.
// =====================================================================
if (file_exists(__DIR__ . '/keys.php')) {
    require __DIR__ . '/keys.php';
}
$key_names = [
    'GEOAPIFY_API_KEY',                                   // 1. Geoapify (maps, landmarks, routes)
    'MAYA_PUBLIC_KEY', 'MAYA_SECRET_KEY',                 // 2. Maya Checkout (wallet reloads)
    'ONESIGNAL_APP_ID', 'ONESIGNAL_REST_API_KEY',         // 3. OneSignal (push notifications)
    'PHILSMS_API_TOKEN', 'PHILSMS_SENDER_ID',             // 4. PhilSMS (text messages)
    'GMAIL_CLIENT_ID', 'GMAIL_CLIENT_SECRET', 'GMAIL_REFRESH_TOKEN', 'GMAIL_SENDER',  // 5. Gmail API
];
foreach ($key_names as $key_name) {
    if (!defined($key_name)) {
        define($key_name, ''); // not set in keys.php = demo mode for that API
    }
}

// Where the APIs live. You don't need to change these.
define('GEOAPIFY_API', 'https://api.geoapify.com/v1');
define('MAYA_API', 'https://pg-sandbox.paymaya.com');   // Maya's sandbox (test only, no real money)
define('ONESIGNAL_API', 'https://api.onesignal.com');
define('PHILSMS_API', 'https://app.philsms.com/api/v3');
define('GMAIL_API', 'https://gmail.googleapis.com/gmail/v1');
define('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token');

define('ARRIVAL_RADIUS_M', 300); // text the passenger when the jeepney is this close to their stop

// XAMPP on Windows sometimes can't check the APIs' SSL certificates ("cURL error 60").
// If that happens, put the full path of a CA file here, for example:
// 'C:\\xampp\\apache\\bin\\curl-ca-bundle.crt'
define('CURL_CA_FILE', '');

date_default_timezone_set('Asia/Manila');

if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

// Ways a passenger can reload. 'maya' goes through Maya Checkout. The other two are
// cash over the counter, approved right away in the demo.
function reload_methods()
{
    return [
        'maya'  => 'Maya Checkout (card or Maya wallet)',
        'store' => 'Partner store (cash)',
        'kiosk' => 'SmartRide kiosk (cash)',
    ];
}

// Name of a reload method for the history lists. Older reloads used gcash / maya.
function reload_method_label($method)
{
    $labels = reload_methods();
    $labels['maya'] = 'Maya';
    $labels['gcash'] = 'GCash';
    return $labels[$method] ?? $method;
}
