<?php include '../views/partials/dashboard_top.php'; ?>
<?php page_head('Jeepney tracking', 'Every unit on the road, updated every 15 seconds', '<span class="pill pill-brand">' . icon('clock') . 'Today · ' . date('g:i A') . '</span>'); ?>
<?php show_flash(); ?>
<div class="row cols-4">
    <div class="card stat-card"><div class="label">Units on the road</div><div class="value"><?= $on_road ?> <small>of <?= count($fleet) ?></small></div></div>
    <div class="card stat-card"><div class="label">Passengers right now</div><div class="value"><?= $riding ?></div></div>
    <div class="card stat-card"><div class="label">Fares collected today</div><div class="value"><?= peso($today['revenue'], 0) ?></div></div>
    <div class="card stat-card"><div class="label">Scans blocked at full</div><div class="value amber"><?= (int) $today['blocked_full'] ?></div></div>
</div>
<div class="lines-layout">
    <div class="card" style="padding:16px"><div class="map" id="map"><div class="map-note">Loading the map…</div></div></div>
    <div class="card lines-panel" id="linesPanel"><p class="empty">Loading the lines…</p></div>
</div>
<?php $real = array_filter($fleet, function ($u) { return !$u['simulated']; }); ?>
<div class="card">
    <h3>Real units</h3>
    <p class="hint" style="margin-top:-6px">Units with a driver and a phone sending GPS. The other <?= count($fleet) - count($real) ?> units on the map are demo units that drive their line by themselves (2 per line).</p>
    <?php if (geoapify_demo_mode()): ?><p class="hint"><span class="pill pill-amber">Demo mode</span> Add a Geoapify key to show street names.</p><?php endif; ?>
    <?php $dots = ['on_trip' => 'green', 'parked' => 'brand', 'breakdown' => 'red', 'offline' => 'gray']; ?>
    <?php foreach ($real as $u): $dot = $dots[$u['state']]; ?>
        <div class="list-row">
            <span class="dot <?= $dot ?>"></span>
            <div class="who" style="flex:1"><b><?= e($u['unit_code']) ?></b>
                <span><?= $u['route_id'] ? 'Line ' . (int) $u['route_id'] . ' · ' . e($u['short_name']) : 'No line' ?><?= $u['state'] === 'on_trip' ? ' · ' . (int) $u['onboard'] . '/' . (int) $u['capacity'] . ' · ' . e(first_name($u['driver_name'])) : ' · Last seen ' . e($u['seen']) ?></span>
                <?php if (!empty($u['address'])): ?><span class="small muted"><?= icon('pin') ?> <?= e($u['address']) ?></span><?php endif; ?></div>
            <?= pill($u['state']) ?>
        </div>
    <?php endforeach; ?>
</div>

<div class="row cols-2" id="fleet">
    <div class="card">
        <h3>Fleet</h3>
        <div class="table-scroll"><table class="data fit">
            <thead><tr><th>Unit</th><th>Line</th><th>Plate</th><th>Seats</th><th class="r">Status</th></tr></thead>
            <tbody><?php foreach ($fleet as $u): ?>
                <tr><td><b><?= e($u['unit_code']) ?></b><?= $u['simulated'] ? ' <span class="pill pill-gray">Demo</span>' : '' ?></td><td><?= $u['route_id'] ? (int) $u['route_id'] : '–' ?></td><td><?= e($u['plate_no']) ?></td><td class="num"><?= (int) $u['capacity'] ?></td>
                    <td class="r"><form class="inline-form" method="post" action="admin_tracking.php"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="unit_status"><input type="hidden" name="unit_id" value="<?= (int) $u['unit_id'] ?>">
                        <label class="sr-only" for="st<?= (int) $u['unit_id'] ?>">Status of <?= e($u['unit_code']) ?></label>
                        <select class="input" id="st<?= (int) $u['unit_id'] ?>" name="status" onchange="this.form.submit()">
                            <?php foreach (['active' => 'Active', 'breakdown' => 'Breakdown', 'offline' => 'Offline'] as $k => $l): ?><option value="<?= $k ?>" <?= $u['status'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
                        </select><noscript><button class="btn sm" type="submit">Save</button></noscript></form></td></tr>
            <?php endforeach; ?></tbody>
        </table></div>
        <form class="form-grid" method="post" action="admin_tracking.php" style="margin-top:18px">
            <?= csrf_field() ?><input type="hidden" name="action" value="add_unit">
            <div class="field"><label for="unit_code">New unit code</label><input class="input" id="unit_code" name="unit_code" placeholder="BCN 110" maxlength="10" required></div>
            <div class="field"><label for="plate_no">Plate number</label><input class="input" id="plate_no" name="plate_no" placeholder="FAB 1106" maxlength="12" required></div>
            <div class="field"><label for="capacity">Seats</label><input class="input" id="capacity" name="capacity" type="number" min="10" max="40" value="25" required></div>
            <div class="field"><label for="unit_route">Line</label><select class="input" id="unit_route" name="route_id" required>
                <?php foreach ($routes as $r): ?><option value="<?= (int) $r['route_id'] ?>"><?= (int) $r['route_id'] ?> · <?= e($r['short_name']) ?></option><?php endforeach; ?>
            </select></div>
            <div class="field" style="justify-content:flex-end"><button class="btn" type="submit"><?= icon('plus') ?>Add unit</button></div>
        </form>
    </div>
    <div class="card" id="routes">
        <h3>Routes and fares</h3>
        <?php foreach ($routes as $r): ?>
            <div class="list-row">
                <div class="who"><b><span class="line-no" style="background:<?= e($r['color']) ?>;display:inline-block;width:24px;height:24px;line-height:24px;font-size:12px;border-radius:7px;margin-right:6px"><?= (int) $r['route_id'] ?></span><?= e($r['route_name']) ?></b><span><?= e($r['origin']) ?> to <?= e($r['destination']) ?></span></div>
                <form class="inline-form" method="post" action="admin_tracking.php"><?= csrf_field() ?>
                    <input type="hidden" name="action" value="route_fare"><input type="hidden" name="route_id" value="<?= (int) $r['route_id'] ?>">
                    <label class="sr-only" for="fare<?= (int) $r['route_id'] ?>">Fare</label>
                    <input class="input num" id="fare<?= (int) $r['route_id'] ?>" name="base_fare" type="number" step="0.5" min="1" max="200" value="<?= e($r['base_fare']) ?>" style="width:90px">
                    <button class="btn sm ghost" type="submit">Save</button></form>
            </div>
        <?php endforeach; ?>
        <form class="form-grid" method="post" action="admin_tracking.php" style="margin-top:18px">
            <?= csrf_field() ?><input type="hidden" name="action" value="add_route">
            <div class="field"><label for="origin">From</label><input class="input" id="origin" name="origin" placeholder="Libertad" maxlength="60" required></div>
            <div class="field"><label for="destination">To</label><input class="input" id="destination" name="destination" placeholder="Robinsons" maxlength="60" required></div>
            <div class="field"><label for="base_fare">Fare (₱)</label><input class="input" id="base_fare" name="base_fare" type="number" step="0.5" min="1" max="200" value="15" required></div>
            <div class="field" style="justify-content:flex-end"><button class="btn" type="submit"><?= icon('plus') ?>Add route</button></div>
        </form>
    </div>
</div>
<div class="card" id="landmarks">
    <div class="head"><h3>Route landmarks</h3>
        <form method="post" action="admin_tracking.php"><?= csrf_field() ?><input type="hidden" name="action" value="refresh_routes">
            <button class="btn sm ghost" type="submit"><?= icon('refresh') ?>Update from Geoapify</button></form>
    </div>
    <p class="hint" style="margin-top:-6px">Each line runs from landmark A to landmark B, and passengers pick one of these as their drop-off. New landmarks are found by name with Geoapify. The 24 LPTRP lines keep their traced road line when you change landmarks; a route you add gets its bus road line from Geoapify.</p>
    <?php foreach ($routes as $r): ?>
        <?php $stops = $route_stops[$r['route_id']]; ?>
        <div class="route-row">
            <div style="flex:1;min-width:0">
                <b><?= (int) $r['route_id'] ?> · <?= e($r['route_name']) ?></b>
                <div class="stops">
                    <?php foreach ($stops as $i => $stop): ?>
                        <form class="inline-form" method="post" action="admin_tracking.php" style="display:inline-flex;margin:2px 4px 2px 0" data-confirm="Remove <?= e($stop['name']) ?> from this route?">
                            <?= csrf_field() ?><input type="hidden" name="action" value="delete_stop"><input type="hidden" name="stop_id" value="<?= (int) $stop['stop_id'] ?>">
                            <span class="pill <?= $stop['geocoded'] ? 'pill-brand' : 'pill-gray' ?>" title="<?= $stop['geocoded'] ? 'Position from Geoapify' : 'Approximate position' ?>">
                                <?= $i === 0 ? 'A · ' : ($i === count($stops) - 1 ? 'B · ' : ($i + 1) . ' · ') ?><?= e($stop['name']) ?>
                                <button type="submit" aria-label="Remove <?= e($stop['name']) ?>" style="border:0;background:none;color:inherit;cursor:pointer;padding:0 0 0 4px">×</button>
                            </span>
                        </form>
                    <?php endforeach; ?>
                </div>
                <form class="inline-form" method="post" action="admin_tracking.php" style="margin-top:8px;flex-wrap:wrap">
                    <?= csrf_field() ?><input type="hidden" name="action" value="add_stop"><input type="hidden" name="route_id" value="<?= (int) $r['route_id'] ?>">
                    <label class="sr-only" for="stop_name_<?= (int) $r['route_id'] ?>">Landmark name</label>
                    <input class="input" id="stop_name_<?= (int) $r['route_id'] ?>" name="stop_name" maxlength="100" placeholder="Landmark, e.g. Bacolod Public Plaza" style="min-width:220px">
                    <label class="sr-only" for="position_<?= (int) $r['route_id'] ?>">Position</label>
                    <select class="input" id="position_<?= (int) $r['route_id'] ?>" name="position">
                        <?php for ($p = 1; $p <= count($stops) + 1; $p++): ?>
                            <option value="<?= $p ?>" <?= $p === count($stops) ? 'selected' : '' ?>><?= $p === 1 ? 'As new A' : ($p === count($stops) + 1 ? 'As new B' : 'Stop ' . $p) ?></option>
                        <?php endfor; ?>
                    </select>
                    <button class="btn sm" type="submit"><?= icon('plus') ?>Add landmark</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
  // The 24 jeepney lines; pick one to see only that line and its units (plates and drivers for admins).
  SMARTRIDE.lineMap({ map: 'map', panel: 'linesPanel', routesUrl: 'api_routes.php', fleetUrl: 'api_fleet.php', geoUrl: 'api_geo.php', admin: true, locate: false });
});
</script>
<?php include '../views/partials/dashboard_bottom.php'; ?>
