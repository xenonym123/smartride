<?php include '../views/partials/dashboard_top.php'; ?>
<?php page_head('Analytics', 'System performance, fares and fleet at a glance', '<span class="pill pill-brand">' . icon('clock') . 'Today · ' . date('g:i A') . '</span>'); ?>
<?php show_flash(); ?>
<div class="row cols-4">
    <div class="card stat-card"><div class="label">Units on the road<?= icon('bus') ?></div><div class="value"><?= $on_road ?> <small>of <?= count($fleet) ?></small></div>
        <div class="sub"><?= $breakdowns ?> breakdown · <?= $offline ?> offline</div></div>
    <div class="card stat-card"><div class="label">Paid rides today<?= icon('users') ?></div><div class="value"><?= (int) $today['paid'] ?></div>
        <div class="sub"><?= e(vs_yesterday((int) $today['paid'], (int) $yesterday['paid'])) ?></div></div>
    <div class="card stat-card"><div class="label">Fares today<?= icon('peso') ?></div><div class="value"><?= peso($today['revenue'], 0) ?></div>
        <div class="sub"><?= $today['paid'] ? 'Average ' . peso($today['revenue'] / $today['paid']) : 'No fares yet' ?></div></div>
    <div class="card stat-card"><div class="label">Scans blocked at full<?= icon('alert') ?></div><div class="value amber"><?= (int) $today['blocked_full'] ?></div>
        <div class="sub">Riders turned away today</div></div>
</div>

<div class="row cols-wide">
    <div class="card">
        <div class="head"><h3>Live passenger count</h3><a class="btn sm ghost" href="admin_tracking.php"><?= icon('map') ?>Map</a></div>
        <div class="bars">
            <?php foreach ($fleet as $u): ?>
                <?php if ($u['state'] === 'on_trip'): ?>
                    <?php $pct = round($u['onboard'] / max(1, $u['capacity']) * 100); ?>
                    <div class="bar-row"><b><?= e($u['unit_code']) ?></b><div class="bar" title="<?= e($u['route_name']) ?>"><i class="<?= $pct >= 100 ? 'full' : '' ?>" style="width:<?= min(100, $pct) ?>%"></i></div><span class="num"><?= (int) $u['onboard'] ?>/<?= (int) $u['capacity'] ?></span></div>
                <?php else: ?>
                    <div class="bar-row"><b><?= e($u['unit_code']) ?></b><span class="muted small"><?= strip_tags(pill($u['state'])) ?></span><span></span></div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <p class="hint" style="margin-top:16px"><?= $riding ?> people riding right now across <?= $on_road ?> units.</p>
    </div>
    <div class="card">
        <h3>Today's totals</h3>
        <?php $max = max(1, (float) $today['revenue'], (float) $reloads['total']); ?>
        <div class="bars">
            <div><div class="list-row" style="border:0;padding-bottom:6px"><span>Fares collected (to operators)</span><b class="num"><?= peso($today['revenue']) ?></b></div><div class="bar"><i style="width:<?= round($today['revenue'] / $max * 100) ?>%"></i></div></div>
            <div><div class="list-row" style="border:0;padding-bottom:6px"><span>Wallet reloads (<?= (int) $reloads['n'] ?>)</span><b class="num"><?= peso($reloads['total']) ?></b></div><div class="bar"><i style="width:<?= round($reloads['total'] / $max * 100) ?>%;background:var(--green)"></i></div></div>
            <div><div class="list-row" style="border:0;padding-bottom:6px"><span>Scans blocked (all reasons)</span><b class="num"><?= (int) $today['blocked'] ?></b></div></div>
        </div>
        <h3 style="margin-top:22px">Accounts</h3>
        <div class="list-row"><span>Passengers</span><b class="num"><?= (int) ($users['passenger'] ?? 0) ?></b></div>
        <div class="list-row"><span>Drivers</span><b class="num"><?= (int) ($users['driver'] ?? 0) ?></b></div>
        <div class="list-row"><span>Admins</span><b class="num"><?= (int) ($users['admin'] ?? 0) ?></b></div>
    </div>
</div>

<div class="card">
    <div class="head"><h3>Connected APIs</h3></div>
    <?php foreach ($apis as $api): ?>
        <div class="list-row">
            <span class="dot <?= $api[2] === 'on' ? 'green' : 'gray' ?>"></span>
            <div class="who" style="flex:1"><b><?= e($api[0]) ?></b><span><?= e($api[1]) ?></span></div>
            <span class="pill <?= $api[2] === 'on' ? 'pill-green' : 'pill-amber' ?>"><?= e($api[3]) ?></span>
            <?php if ($api[4] !== ''): ?>
                <form method="post" action="admin_home.php"><?= csrf_field() ?><input type="hidden" name="action" value="<?= e($api[4]) ?>">
                    <button class="btn sm ghost" type="submit">Test</button></form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <p class="hint" style="margin-top:12px">Keys go in <code>config/keys.php</code> (never uploaded to GitHub). Without a key, that feature runs in demo mode. Test buttons send to your own account.</p>
</div>

<div class="card">
    <div class="head"><h3>Recent activity</h3><a class="btn sm ghost" href="admin_logs.php">All logs</a></div>
    <?php $logs = $activity; include '../views/partials/log_list.php'; ?>
</div>
<?php include '../views/partials/dashboard_bottom.php'; ?>
