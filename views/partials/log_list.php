<?php
// List of activity logs. Needs $logs. Each action gets an icon, a color and a label.
$log_labels = [
    'login'            => ['login', '', 'Sign in'],
    'logout'           => ['logout', '', 'Sign out'],
    'login_failed'     => ['alert', 'red', 'Failed sign-in'],
    'register'         => ['user', 'green', 'New passenger'],
    'reload'           => ['wallet', 'green', 'Wallet reload'],
    'fare_paid'        => ['check', 'green', 'Fare paid'],
    'fare_blocked'     => ['alert', 'amber', 'Scan blocked'],
    'trip_start'       => ['play', '', 'Trip started'],
    'trip_end'         => ['stop', '', 'Trip ended'],
    'unit_status'      => ['bus', 'amber', 'Unit status'],
    'unit_added'       => ['bus', 'green', 'Unit added'],
    'route_added'      => ['route', 'green', 'Route added'],
    'fare_changed'     => ['peso', 'amber', 'Fare changed'],
    'user_added'       => ['user', 'green', 'Account added'],
    'user_updated'     => ['edit', '', 'Account updated'],
    'user_status'      => ['user', 'amber', 'Account status'],
    'profile_updated'  => ['edit', '', 'Profile updated'],
    'password_changed' => ['lock', '', 'Password changed'],
    'password_reset'   => ['lock', 'amber', 'Reset link asked'],
    'qr_frozen'        => ['snow', 'red', 'QR frozen'],
    'qr_unfrozen'      => ['qr', 'green', 'QR unfrozen'],
];
?>
<?php if (!$logs): ?><p class="empty">Nothing logged yet.</p><?php endif; ?>
<?php foreach ($logs as $log): ?>
    <?php
    if (isset($log_labels[$log['action']])) {
        $look = $log_labels[$log['action']];
    } else {
        $look = ['logs', '', ucfirst(str_replace('_', ' ', $log['action']))];
    }
    ?>
    <div class="log">
        <div class="ico <?= $look[1] ?>"><?= icon($look[0]) ?></div>
        <div class="body">
            <div class="top"><b><?= e($look[2]) ?></b><span title="<?= e($log['logged_at']) ?>"><?= e(fmt_date($log['logged_at'], 'M j · g:i A')) ?></span></div>
            <p><?= e($log['full_name'] ?? 'System') ?><?= $log['username'] ? ' (' . e($log['username']) . ')' : '' ?> · <?= e($log['details']) ?></p>
        </div>
    </div>
<?php endforeach; ?>
