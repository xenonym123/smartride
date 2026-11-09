<?php include '../views/partials/dashboard_top.php'; ?>
<?php
$peak = $summary['peak_hour'];
$peakLabel = $peak === null ? '—' : date('g A', mktime($peak, 0, 0)) . '–' . date('g A', mktime($peak + 1, 0, 0));
// Daily revenue bars, one per day in the range (missing days = 0).
$byDay = array_column($daily, 'revenue', 'day');
$bars = [];
for ($i = $days - 1; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $bars[] = [$d, (float) ($byDay[$d] ?? 0)];
}
$maxRev = max(1, max(array_column($bars, 1)));
$top = ceil($maxRev / 500) * 500;
$W = 1100; $H = 260; $padL = 46; $padB = 26; $plotW = $W - $padL - 6; $plotH = $H - $padB - 10;
$bw = $plotW / count($bars);
?>
<?php page_head('Fare reports', 'The operator view: what each unit and route earns',
    '<a class="btn ghost" href="admin_reports_export.php?days=' . $days . '">' . icon('download') . 'Export CSV</a>'); ?>
<div class="filters">
    <?php foreach ($ranges as $d => $label): ?><a class="<?= $days === $d ? 'on' : '' ?>" href="admin_reports.php?days=<?= $d ?>"><?= e($label) ?></a><?php endforeach; ?>
</div>
<div class="row cols-4">
    <div class="card stat-card"><div class="label">Fare revenue</div><div class="value"><?= peso($summary['revenue'], 0) ?></div><div class="sub"><?= number_format((int) $summary['paid']) ?> paid rides</div></div>
    <div class="card stat-card"><div class="label">Average fare</div><div class="value"><?= peso($summary['avg_fare']) ?></div><div class="sub">Per paid ride</div></div>
    <div class="card stat-card"><div class="label">Peak hour</div><div class="value" style="font-size:26px"><?= e($peakLabel) ?></div><div class="sub">Most scans</div></div>
    <div class="card stat-card"><div class="label">Scans blocked at full</div><div class="value amber"><?= (int) $summary['blocked_full'] ?></div><div class="sub">Wallet reloads: <?= peso($summary['reloads'], 0) ?></div></div>
</div>
<div class="card">
    <h3>Fare revenue per day</h3>
    <div class="table-scroll" style="margin:0"><svg class="chart" viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="Fare revenue per day" style="min-width:560px">
        <?php for ($g = 0; $g <= 4; $g++): $y = 10 + $plotH - $plotH * $g / 4; ?>
            <line class="grid" x1="<?= $padL ?>" x2="<?= $W - 6 ?>" y1="<?= $y ?>" y2="<?= $y ?>"/>
            <text x="<?= $padL - 8 ?>" y="<?= $y + 4 ?>" text-anchor="end">₱<?= number_format($top * $g / 4) ?></text>
        <?php endfor; ?>
        <?php foreach ($bars as $i => $bar): $d = $bar[0]; $v = $bar[1]; $h = $plotH * $v / $top; $x = $padL + $i * $bw + $bw * .15; ?>
            <rect class="b" x="<?= round($x, 1) ?>" y="<?= round(10 + $plotH - $h, 1) ?>" width="<?= round($bw * .7, 1) ?>" height="<?= round($h, 1) ?>" rx="3"><title><?= e(fmt_date($d, 'D, M j')) ?>: <?= e(peso($v)) ?></title></rect>
            <?php if ($days <= 14 || $i % (int) ceil($days / 10) === 0): ?><text x="<?= round($x + $bw * .35, 1) ?>" y="<?= $H - 8 ?>" text-anchor="middle"><?= e(date($days <= 7 ? 'D' : 'M j', strtotime($d))) ?></text><?php endif; ?>
        <?php endforeach; ?>
    </svg></div>
</div>
<div class="row cols-wide">
    <div class="card">
        <h3>Fares per unit</h3>
        <div class="table-scroll"><table class="data">
            <thead><tr><th>Unit</th><th>Trips</th><th>Paid rides</th><th>Average peak load</th><th>Blocked at full</th><th class="r">Revenue</th></tr></thead>
            <tbody><?php foreach ($units as $u): $load = $u['avg_peak'] ? round($u['avg_peak'] / $u['capacity'] * 100) : null; ?>
                <tr><td><b><?= e($u['unit_code']) ?></b></td><td class="num"><?= (int) $u['trips'] ?></td><td class="num"><?= (int) $u['passengers'] ?></td>
                    <td><?= $load === null ? '<span class="muted">—</span>' : '<span class="pill ' . ($load >= 95 ? 'pill-amber' : 'pill-green') . '">' . $load . '%</span>' ?></td>
                    <td class="num"><?= (int) $u['blocked_full'] ?></td><td class="r num"><b><?= peso($u['revenue']) ?></b></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
        <p class="hint" style="margin-top:12px">Average peak load is how full each trip got at its busiest. Units near 100% with many blocked scans need more trips on their route.</p>
    </div>
    <div class="card">
        <h3>Per route</h3>
        <?php foreach ($routes as $r): ?>
            <div class="list-row"><div class="who"><b><?= e($r['route_name']) ?></b><span><?= (int) $r['trips'] ?> trips · <?= (int) $r['passengers'] ?> paid rides · <?= peso($r['base_fare']) ?> fare</span></div><b class="num"><?= peso($r['revenue'], 0) ?></b></div>
        <?php endforeach; ?>
    </div>
</div>
<?php include '../views/partials/dashboard_bottom.php'; ?>
