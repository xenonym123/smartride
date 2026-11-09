<?php include '../views/partials/dashboard_top.php'; ?>
<?php page_head('Ride history', (int) $month['rides'] . ' rides and ' . peso($month['spent']) . ' this month'); ?>
<div class="card">
    <?php if (!$rides): ?>
        <p class="empty">No rides yet. Show your QR on your first SmartRide jeepney and it'll appear here.</p>
    <?php else: ?>
    <div class="table-scroll"><table class="data">
        <thead><tr><th>Date</th><th>Time</th><th>Route</th><th>Unit</th><th>Status</th><th class="r">Fare</th></tr></thead>
        <tbody>
        <?php foreach ($rides as $r): ?>
            <tr>
                <td><?= e(fmt_date($r['scanned_at'])) ?></td>
                <td class="num"><?= e(fmt_time($r['scanned_at'])) ?></td>
                <td><?= e($r['route_name']) ?></td>
                <td><?= e($r['unit_code']) ?></td>
                <td><?= pill($r['status']) ?></td>
                <td class="r num <?= $r['status'] === 'paid' ? 'amt-minus' : 'muted' ?>"><?= $r['status'] === 'paid' ? '−' . peso($r['amount']) : 'Not charged' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>
<?php include '../views/partials/dashboard_bottom.php'; ?>
