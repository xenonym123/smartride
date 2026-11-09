<?php
// Top of every dashboard page: <head> and the sidebar for the signed-in role.
// Uses $title, $current_page (which menu item is highlighted) and $use_map.

$menus = [
    'passenger' => [
        ['passenger_home.php', 'home', 'Home'],
        ['passenger_qr.php', 'qr', 'My QR'],
        ['passenger_track.php', 'map', 'Track a Jeepney'],
        ['passenger_rides.php', 'history', 'Ride History'],
        ['passenger_reload.php', 'wallet', 'Reload Wallet'],
        ['passenger_profile.php', 'user', 'Profile'],
        ['notifications.php', 'bell', 'Notifications'],
    ],
    'driver' => [
        ['driver_home.php', 'gauge', 'Home'],
        ['driver_count.php', 'users', 'Passenger Count'],
        ['driver_scan.php', 'scan', 'QR Scanner'],
        ['driver_earnings.php', 'peso', 'Earnings'],
        ['driver_routes.php', 'route', 'Routes'],
        ['notifications.php', 'bell', 'Notifications'],
    ],
    'admin' => [
        ['admin_home.php', 'chart', 'Analytics'],
        ['admin_users.php', 'users', 'User Management'],
        ['admin_tracking.php', 'bus', 'Jeepney Tracking'],
        ['admin_reports.php', 'receipt', 'Fare Reports'],
        ['admin_logs.php', 'logs', 'System Logs'],
        ['notifications.php', 'bell', 'Notifications'],
    ],
];
$role_labels = ['passenger' => 'Passenger', 'driver' => 'Driver', 'admin' => 'Admin · Operator'];

$my_role = $_SESSION['role'];
$my_name = $_SESSION['full_name'];
$current_page = $current_page ?? basename($_SERVER['PHP_SELF']);
?>
<!doctype html>
<html lang="en">
<head>
<?php include '../views/partials/head.php'; ?>
<?php if (!empty($use_map)): ?><link rel="stylesheet" href="../assets/vendor/leaflet/leaflet.css"><?php endif; ?>
</head>
<body>
<div class="app">
    <aside class="sidebar" id="sidebar">
        <a href="<?= home_for_role($my_role) ?>" aria-label="SmartRide dashboard"><?= logo(true) ?></a>
        <nav aria-label="Dashboard">
            <?php foreach ($menus[$my_role] as $item): ?>
                <a href="<?= $item[0] ?>" class="<?= $current_page === $item[0] ? 'on' : '' ?>"><?= icon($item[1]) ?><span><?= e($item[2]) ?></span><?php if ($item[0] === 'notifications.php'): ?><span class="nav-badge" data-notif-count hidden></span><?php endif; ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="spacer"></div>
        <form method="post" action="logout.php"><?= csrf_field() ?><button class="logout" type="submit"><?= icon('logout') ?>Log out</button></form>
        <div class="me">
            <div class="av"><?= e(initials($my_name)) ?></div>
            <div><b><?= e($my_name) ?></b><span><?= e($role_labels[$my_role]) ?></span></div>
        </div>
    </aside>
    <div class="scrim" data-close-nav></div>
    <main class="main">
