<?php include '../views/partials/dashboard_top.php'; ?>
<?php
$type_query = $group !== '' ? '&type=' . urlencode($group) : '';
page_head('System logs', number_format($total) . ' entries',
    '<a class="btn ghost" href="admin_logs.php?page=1' . e($type_query) . '">' . icon('refresh') . 'Refresh</a>');
?>
<div class="filters">
    <a class="<?= $group === '' ? 'on' : '' ?>" href="admin_logs.php">All activity</a>
    <?php foreach ($groups as $key => $info): ?>
        <a class="<?= $group === $key ? 'on' : '' ?>" href="admin_logs.php?type=<?= e($key) ?>"><?= e($info[0]) ?></a>
    <?php endforeach; ?>
</div>
<div class="card">
    <?php include '../views/partials/log_list.php'; ?>
    <?php if ($pages > 1): ?>
        <div class="pager">
            <?php if ($page > 1): ?><a class="btn sm ghost" href="admin_logs.php?page=<?= $page - 1 ?><?= e($type_query) ?>">Newer</a><?php endif; ?>
            <span class="muted small">Page <?= $page ?> of <?= $pages ?></span>
            <?php if ($page < $pages): ?><a class="btn sm ghost" href="admin_logs.php?page=<?= $page + 1 ?><?= e($type_query) ?>">Older</a><?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php include '../views/partials/dashboard_bottom.php'; ?>
