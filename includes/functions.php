<?php
// Small helper functions used by the controllers and views.

// ---------- Error handling ----------

// If anything throws an error we didn't catch, show a friendly page instead of
// PHP's raw message. The real message goes to the PHP error log.
function show_error_page($ex)
{
    error_log('SmartRide error: ' . $ex->getMessage() . ' in ' . $ex->getFile() . ':' . $ex->getLine());
    http_response_code(500);
    if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'message' => 'Something went wrong on our side. Try again.']);
        exit;
    }
    echo '<!doctype html><meta charset="utf-8"><title>SmartRide</title>';
    echo '<div style="font-family:sans-serif;max-width:520px;margin:80px auto;padding:0 16px">';
    echo '<h1>Something went wrong</h1><p>Sorry, that didn\'t work. Go back and try again.</p>';
    if (APP_DEBUG) {
        echo '<p><small>' . e($ex->getMessage()) . '</small></p>';
    }
    echo '<p><a href="home.php">Back to SmartRide</a></p></div>';
    exit;
}
set_exception_handler('show_error_page');


// ---------- Output ----------

// Escape text before printing it in HTML. This is what stops XSS.
function e($value)
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function peso($amount, $decimals = 2)
{
    return '₱' . number_format((float) $amount, $decimals);
}

// Inline SVG icon by name, from includes/icons.php.
function icon($name)
{
    static $icons = null;
    if ($icons === null) {
        $icons = require __DIR__ . '/icons.php';
    }
    return $icons[$name] ?? '';
}

// The SmartRide wordmark. $white = true for dark backgrounds.
function logo($white = false)
{
    return '<span class="logo' . ($white ? ' white' : '') . '">'
        . '<svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . '<rect x="3" y="3" width="26" height="26" rx="8"/><path d="M11 20.5c1.4 1.3 3 1.9 5 1.9 2.7 0 4.5-1.4 4.5-3.4 0-4.6-9.2-2.6-9.2-7.3 0-1.9 1.8-3.2 4.3-3.2 1.8 0 3.3.6 4.4 1.7"/></svg>'
        . '<span>Smart<span class="logo-ride">Ride</span></span></span>';
}

function initials($name)
{
    $parts = preg_split('/\s+/', trim($name));
    $first = mb_substr($parts[0] ?? '?', 0, 1);
    $last = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
    return mb_strtoupper($first . $last);
}

function first_name($name)
{
    $parts = explode(' ', trim($name));
    return $parts[0] !== '' ? $parts[0] : $name;
}

function time_ago($datetime)
{
    if (!$datetime) {
        return 'never';
    }
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hr ago';
    return floor($diff / 86400) . ' days ago';
}

function fmt_date($datetime, $format = 'M j, Y')
{
    return $datetime ? date($format, strtotime($datetime)) : '—';
}

function fmt_time($datetime)
{
    return $datetime ? date('g:i A', strtotime($datetime)) : '—';
}

// Colored label for fares, units and accounts.
function pill($status)
{
    $labels = [
        'paid'            => ['green', 'Paid'],
        'blocked_full'    => ['amber', 'Blocked · full'],
        'blocked_balance' => ['red', 'Blocked · low balance'],
        'blocked_frozen'  => ['red', 'Blocked · QR frozen'],
        'active'          => ['green', 'Active'],
        'breakdown'       => ['red', 'Breakdown'],
        'offline'         => ['gray', 'Offline'],
        'inactive'        => ['gray', 'Deactivated'],
        'frozen'          => ['red', 'Frozen'],
        'on_trip'         => ['green', 'On a trip'],
        'parked'          => ['brand', 'Parked'],
        'reload'          => ['brand', 'Reload'],
    ];
    $tone = 'gray';
    $label = ucfirst($status);
    if (isset($labels[$status])) {
        $tone = $labels[$status][0];
        $label = $labels[$status][1];
    }
    return '<span class="pill pill-' . $tone . '">' . e($label) . '</span>';
}

// Title row at the top of every dashboard page. $tools is trusted HTML (buttons).
function page_head($heading, $sub = '', $tools = '')
{
    echo '<div class="page-head">';
    echo '<div style="display:flex;gap:14px;align-items:center;min-width:0">';
    echo '<button class="burger" type="button" aria-label="Open menu" data-open-nav>' . icon('menu') . '</button>';
    echo '<div style="min-width:0"><h1>' . e($heading) . '</h1>';
    if ($sub !== '') {
        echo '<p>' . e($sub) . '</p>';
    }
    echo '</div></div>';
    if ($tools !== '') {
        echo '<div class="tools">' . $tools . '</div>';
    }
    echo '</div>';
}

// "+12% vs yesterday" on the admin dashboard.
function vs_yesterday($now, $before)
{
    if (!$before) {
        return 'No data for yesterday';
    }
    $percent = round(($now - $before) / $before * 100);
    return ($percent >= 0 ? '+' : '') . $percent . '% vs yesterday';
}


// ---------- Messages and forms ----------

// flash('success', 'Saved.') stores a message; flash('success') reads it once.
function flash($type, $message = null)
{
    if ($message !== null) {
        $_SESSION['flash'][$type] = $message;
        return null;
    }
    $msg = $_SESSION['flash'][$type] ?? null;
    unset($_SESSION['flash'][$type]);
    return $msg;
}

function show_flash()
{
    $types = ['success' => 'check', 'error' => 'alert'];
    foreach ($types as $type => $ic) {
        $msg = flash($type);
        if ($msg) {
            $role = $type === 'error' ? 'alert' : 'status';
            echo '<div class="flash ' . $type . '" role="' . $role . '">' . icon($ic) . '<span>' . e($msg) . '</span></div>';
        }
    }
}

// Keep what the user typed when a form has errors, so they don't retype it.
function remember_input($fields)
{
    $_SESSION['old'] = [];
    foreach ($fields as $f) {
        $_SESSION['old'][$f] = $_POST[$f] ?? '';
    }
}

function forget_input()
{
    unset($_SESSION['old']);
}

function old($key, $default = '')
{
    return (string) ($_SESSION['old'][$key] ?? $default);
}

// Error text under a form field, and the red border class.
function field_error($errors, $key)
{
    return isset($errors[$key]) ? '<span class="err">' . e($errors[$key]) . '</span>' : '';
}

function error_class($errors, $key)
{
    return isset($errors[$key]) ? ' has-err' : '';
}

// Trimmed text from a form or the URL.
function input($key, $default = '')
{
    $value = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

// Checks the sign-up / user form. Returns a list of errors (empty = OK).
// $confirm is the "confirm password" box, or null when the form has none.
function validate_user($conn, $d, $confirm, $except_id = 0, $password_required = true)
{
    $errors = [];
    if (mb_strlen($d['full_name']) < 2 || mb_strlen($d['full_name']) > 100) {
        $errors['full_name'] = 'Enter a full name.';
    }
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if (!preg_match('/^(\+?63|0)9\d{9}$/', $d['phone'])) {
        $errors['phone'] = 'Use a PH mobile number, like 09171234567.';
    }
    if (!preg_match('/^[a-z0-9._]{3,30}$/', $d['username'])) {
        $errors['username'] = '3 to 30 characters: letters, numbers, dot or underscore.';
    } elseif (username_taken($conn, $d['username'], $except_id)) {
        $errors['username'] = 'That username is taken. Try another one.';
    }
    if ($password_required || $d['password'] !== '') {
        if (strlen($d['password']) < 8) {
            $errors['password'] = 'Use at least 8 characters.';
        } elseif ($confirm !== null && $confirm !== $d['password']) {
            $errors['password_confirm'] = "The passwords don't match.";
        }
    }
    return $errors;
}

// Cleans up the name/email/phone/username fields from a form.
function user_form_input()
{
    return [
        'full_name' => input('full_name'),
        'email'     => input('email'),
        'phone'     => preg_replace('/[\s-]/', '', input('phone')),
        'username'  => strtolower(input('username')),
        'password'  => $_POST['password'] ?? '',
    ];
}


// ---------- Responses ----------

function go($page)
{
    header('Location: ' . $page);
    exit;
}

// Send JSON back to a fetch() call and stop.
function json_out($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// The JSON body sent by SMARTRIDE.post() in assets/js/app.js.
function read_json()
{
    $data = json_decode(file_get_contents('php://input') ?: '[]', true);
    return is_array($data) ? $data : [];
}

// Download a CSV file (opens in Excel).
function send_csv($filename, $header, $rows)
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // so Excel shows ₱ correctly
    fputcsv($out, $header);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}
