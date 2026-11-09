<?php include '../views/partials/dashboard_top.php'; ?>
<?php page_head('Routes', 'How full your trips run on each route, last 30 days'); ?>
<div class="row cols-wide">
    <div class="card">
        <h3>Your routes</h3>
        <?php if (!$stats): ?><p class="empty">No trips in the last 30 days.</p><?php endif; ?>
        <div class="bars">
        <?php foreach ($stats as $s): $load = $s['capacity'] ? min(100, round($s['avg_passengers'] / $s['capacity'] * 100)) : 0; ?>
            <div>
                <div class="list-row" style="border:0;padding-bottom:6px"><div class="who"><b><?= e($s['route_name']) ?></b><span><?= (int) $s['trips'] ?> trips · about <?= round((float) $s['avg_passengers']) ?> paid passengers per trip</span></div>
                    <span class="pill <?= $load >= 85 ? 'pill-amber' : 'pill-green' ?>"><?= $load ?>% full</span></div>
                <div class="bar"><i class="<?= $load >= 85 ? 'full' : '' ?>" style="width:<?= $load ?>%"></i></div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
    <div class="card">
        <h3>Fares by route</h3>
        <?php foreach ($routes as $r): ?>
            <div class="list-row"><div class="who"><b><?= e($r['route_name']) ?></b><span><?= e($r['origin']) ?> to <?= e($r['destination']) ?></span></div><span class="num"><b><?= peso($r['base_fare']) ?></b></span></div>
        <?php endforeach; ?>
        <p class="hint" style="margin-top:12px">Fares are set by the admin. The scanner charges the fare of the route you started the trip on.</p>
    </div>
</div>
<?php include '../views/partials/dashboard_bottom.php'; ?>
