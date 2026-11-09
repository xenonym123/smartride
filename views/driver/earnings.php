<?php include '../views/partials/dashboard_top.php'; ?>
<?php page_head('Earnings', 'Fares paid by QR on your trips'); ?>
<div class="row cols-3">
    <?php foreach (['Today' => $today, 'This week' => $week, 'This month' => $month] as $label => $t): ?>
        <div class="card stat-card"><div class="label"><?= e($label) ?><?= icon('peso') ?></div><div class="value"><?= peso($t['earnings']) ?></div>
            <div class="sub"><?= (int) $t['passengers'] ?> passengers · <?= (int) $t['trips'] ?> trips</div></div>
    <?php endforeach; ?>
</div>
<div class="card">
    <h3>Last 14 days</h3>
    <?php if (!$days): ?><p class="empty">No trips in the last two weeks.</p><?php else: ?>
    <div class="table-scroll"><table class="data">
        <thead><tr><th>Date</th><th>Trips</th><th>Passengers</th><th>Average per trip</th><th class="r">Earnings</th></tr></thead>
        <tbody><?php foreach ($days as $d): ?>
            <tr><td><?= e(fmt_date($d['day'], 'D, M j')) ?></td><td class="num"><?= (int) $d['trips'] ?></td><td class="num"><?= (int) $d['passengers'] ?></td>
                <td class="num"><?= $d['trips'] ? round($d['passengers'] / $d['trips'], 1) : 0 ?></td><td class="r num"><b><?= peso($d['earnings']) ?></b></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
</div>
<?php include '../views/partials/dashboard_bottom.php'; ?>
