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

// ---------- PayMongo (QR Ph payment gateway) ----------
// Get your test keys at dashboard.paymongo.com > Developers > API Keys.
// Leave the secret key empty and SmartRide runs in DEMO MODE: it shows a sample QR
// and a "Simulate payment" button instead of calling PayMongo.
// Never upload your real keys to GitHub or share this file.
define('PAYMONGO_SECRET_KEY', '');        // sk_test_...
define('PAYMONGO_WEBHOOK_SECRET', '');    // whsk_... (only if you set up a webhook)
define('PAYMONGO_API', 'https://api.paymongo.com/v1');
// XAMPP on Windows sometimes can't check PayMongo's SSL certificate ("cURL error 60").
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

// Ways a passenger can reload. 'qrph' goes through PayMongo. The other two are
// cash over the counter, approved right away in the demo.
function reload_methods()
{
    return [
        'qrph'  => 'QR Ph (GCash, Maya, any bank app)',
        'store' => 'Partner store (cash)',
        'kiosk' => 'SmartRide kiosk (cash)',
    ];
}

// Name of a reload method for the history lists. Older reloads used gcash / maya.
function reload_method_label($method)
{
    $labels = reload_methods();
    $labels['qrph'] = 'QR Ph via PayMongo';
    $labels['gcash'] = 'GCash';
    $labels['maya'] = 'Maya';
    return $labels[$method] ?? $method;
}
