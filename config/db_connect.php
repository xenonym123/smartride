<?php
// Opens the MySQLi connection. Every controller requires this file first.

require_once __DIR__ . '/config.php';

// Make MySQLi throw an exception when a query fails, instead of failing quietly.
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
    $conn->set_charset('utf8mb4');
    // Keep MySQL on Philippine time so NOW() and PHP's date() agree.
    $conn->query("SET time_zone = '+08:00'");
} catch (mysqli_sql_exception $ex) {
    // Don't show the real reason to visitors. It goes to the error log instead.
    error_log('SmartRide database connection failed: ' . $ex->getMessage());
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><title>SmartRide</title>';
    echo '<div style="font-family:sans-serif;max-width:520px;margin:80px auto;padding:0 16px">';
    echo '<h1>We can\'t reach the database</h1>';
    echo '<p>Start MySQL in XAMPP and import <b>database/smartride_db.sql</b> in phpMyAdmin, then reload this page.</p>';
    if (APP_DEBUG) {
        echo '<p><small>' . htmlspecialchars($ex->getMessage(), ENT_QUOTES, 'UTF-8') . '</small></p>';
    }
    echo '</div>';
    exit;
}
