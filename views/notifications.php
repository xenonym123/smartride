<?php include '../views/partials/dashboard_top.php'; ?>
<?php page_head('Notifications', 'Fares, reloads and alerts for your account'); ?>
<?php show_flash(); ?>
<?php include '../views/partials/alerts_banner.php'; ?>
<?php
$notif_icons = [
    'fare_paid' => ['check', 'green'], 'fare_blocked' => ['alert', 'amber'], 'low_balance' => ['wallet', 'red'],
    'reload' => ['wallet', 'green'], 'unit_full' => ['users', 'amber'], 'qr' => ['qr', ''], 'qr_misuse' => ['shield', 'red'],
    'new_passenger' => ['user', 'green'], 'welcome' => ['bell', ''],
];
?>
<div class="card">
    <?php if (!$notifications): ?><p class="empty">Nothing yet. Your fares, reloads and alerts show up here.</p><?php endif; ?>
    <?php foreach ($notifications as $n): ?>
        <?php $look = $notif_icons[$n['type']] ?? ['bell', '']; ?>
        <div class="notif log<?= $n['is_read'] ? '' : ' unread' ?>">
            <div class="ico <?= $look[1] ?>"><?= icon($look[0]) ?></div>
            <div class="body">
                <div class="top"><b><?= e($n['title']) ?></b><span><?= e(fmt_date($n['created_at'], 'M j · g:i A')) ?></span></div>
                <p><?= e($n['body']) ?><?php if ($n['link'] !== ''): ?> · <a class="linkbtn" href="<?= e($n['link']) ?>">Open</a><?php endif; ?></p>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php include '../views/partials/dashboard_bottom.php'; ?>
