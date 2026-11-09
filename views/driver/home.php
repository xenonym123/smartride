<?php include '../views/partials/dashboard_top.php'; ?>
<?php
$tools = $trip ? '<span class="pill pill-brand gps-state" data-gps-state>' . icon('gps') . 'GPS starting…</span><a class="btn" href="driver_scan.php">' . icon('scan') . 'Open QR scanner</a>' : '';
page_head('Driver dashboard', 'Track your daily operations and earnings', $tools);
?>
<?php show_flash(); ?>

<div class="row cols-4">
    <div class="card stat-card"><div class="label">Passengers today<?= icon('users') ?></div><div class="value"><?= (int) $totals['passengers'] ?></div><div class="sub">Paid by QR</div></div>
    <div class="card stat-card"><div class="label">Earned today<?= icon('peso') ?></div><div class="value"><?= peso($totals['earnings']) ?></div>
        <div class="sub"><?= $totals['passengers'] ? 'Average fare ' . peso($totals['earnings'] / $totals['passengers']) : 'No fares yet today' ?></div></div>
    <div class="card stat-card"><div class="label">Trips today<?= icon('route') ?></div><div class="value"><?= (int) $totals['trips'] ?></div><div class="sub"><?= $trip ? '1 on the road now' : 'None running' ?></div></div>
    <div class="card stat-card"><div class="label">Vehicle<?= icon('bus') ?></div>
        <div class="value" style="font-size:26px"><?= $trip ? e($trip['unit_code']) : '—' ?></div><div class="sub"><?= $trip ? pill('on_trip') : 'Start a trip to pick a unit' ?></div></div>
</div>

<div class="row cols-main">
    <div class="card">
        <h3>Today's trips</h3>
        <?php if (!$trips): ?><p class="empty">No trips yet today.</p><?php else: ?>
        <div class="table-scroll"><table class="data">
            <thead><tr><th>Started</th><th>Route</th><th>Unit</th><th>Passengers</th><th class="r">Fares</th></tr></thead>
            <tbody><?php foreach ($trips as $t): ?>
                <tr><td class="num"><?= e(fmt_time($t['started_at'])) ?><?= $t['ended_at'] ? '' : ' ' . pill('on_trip') ?></td><td><?= e($t['route_name']) ?></td><td><?= e($t['unit_code']) ?></td>
                    <td class="num"><?= (int) $t['passengers'] ?></td><td class="r num"><?= peso($t['collected']) ?></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
        <?php endif; ?>
    </div>
    <div class="card">
        <?php if ($trip): ?>
            <h3>Trip in progress</h3>
            <div class="list-row"><div class="who"><b><?= e($trip['unit_code']) ?> · <?= e($trip['route_name']) ?></b><span>Since <?= e(fmt_time($trip['started_at'])) ?> · fare <?= peso($trip['base_fare']) ?></span></div></div>
            <div class="form-stack" style="margin-top:16px">
                <a class="btn block" href="driver_scan.php"><?= icon('scan') ?>QR scanner</a>
                <a class="btn ghost block" href="driver_count.php"><?= icon('users') ?>Live passenger count</a>
                <form method="post" action="driver_home.php" data-confirm="End this trip? The scanner stops taking fares.">
                    <?= csrf_field() ?><input type="hidden" name="action" value="end"><button class="btn danger block" type="submit"><?= icon('stop') ?>End trip</button></form>
            </div>
            <p class="hint" style="margin-top:12px">Keep this page open on your phone. It shares your location with the map while the trip runs.</p>
        <?php else: ?>
            <h3>Start a trip</h3>
            <?php if (!$units): ?>
                <p class="empty">Every active unit is already out. Ask the admin to free one up.</p>
            <?php else: ?>
            <form class="form-stack" method="post" action="driver_home.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="start">
                <div class="field"><label for="unit_id">Unit</label>
                    <select class="input" id="unit_id" name="unit_id" required><?php foreach ($units as $u): ?><option value="<?= (int) $u['unit_id'] ?>"><?= e($u['unit_code']) ?> · <?= e($u['plate_no']) ?> · <?= (int) $u['capacity'] ?> seats</option><?php endforeach; ?></select></div>
                <div class="field"><label for="route_id">Route</label>
                    <select class="input" id="route_id" name="route_id" required><?php foreach ($routes as $r): ?><option value="<?= (int) $r['route_id'] ?>"><?= e($r['route_name']) ?> · <?= peso($r['base_fare']) ?></option><?php endforeach; ?></select></div>
                <button class="btn block" type="submit"><?= icon('play') ?>Start trip</button>
            </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
<?php include '../views/partials/dashboard_bottom.php'; ?>
