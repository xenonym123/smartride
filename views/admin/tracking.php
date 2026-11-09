<?php include '../views/partials/dashboard_top.php'; ?>
<?php page_head('Jeepney tracking', 'Every unit on the road, updated every 15 seconds', '<span class="pill pill-brand">' . icon('clock') . 'Today · ' . date('g:i A') . '</span>'); ?>
<?php show_flash(); ?>
<div class="row cols-4">
    <div class="card stat-card"><div class="label">Units on the road</div><div class="value"><?= $on_road ?> <small>of <?= count($fleet) ?></small></div></div>
    <div class="card stat-card"><div class="label">Passengers right now</div><div class="value"><?= $riding ?></div></div>
    <div class="card stat-card"><div class="label">Fares collected today</div><div class="value"><?= peso($today['revenue'], 0) ?></div></div>
    <div class="card stat-card"><div class="label">Scans blocked at full</div><div class="value amber"><?= (int) $today['blocked_full'] ?></div></div>
</div>
<div class="row cols-main">
    <div class="card" style="padding:16px"><div class="map" id="map"><div class="map-note">Loading the map…</div></div></div>
    <div class="card">
        <h3>Units</h3>
        <?php $dots = ['on_trip' => 'green', 'parked' => 'brand', 'breakdown' => 'red', 'offline' => 'gray']; ?>
        <?php foreach ($fleet as $u): $dot = $dots[$u['state']]; ?>
            <div class="list-row">
                <span class="dot <?= $dot ?>"></span>
                <div class="who" style="flex:1"><b><?= e($u['unit_code']) ?></b>
                    <span><?= $u['state'] === 'on_trip' ? e($u['route_name']) . ' · ' . (int) $u['onboard'] . '/' . (int) $u['capacity'] . ' · ' . e(first_name($u['driver_name'])) : 'Last seen ' . e($u['seen']) ?></span></div>
                <?= pill($u['state']) ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="row cols-2" id="fleet">
    <div class="card">
        <h3>Fleet</h3>
        <div class="table-scroll"><table class="data fit">
            <thead><tr><th>Unit</th><th>Plate</th><th>Seats</th><th class="r">Status</th></tr></thead>
            <tbody><?php foreach ($fleet as $u): ?>
                <tr><td><b><?= e($u['unit_code']) ?></b></td><td><?= e($u['plate_no']) ?></td><td class="num"><?= (int) $u['capacity'] ?></td>
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
            <div class="field" style="justify-content:flex-end"><button class="btn" type="submit"><?= icon('plus') ?>Add unit</button></div>
        </form>
    </div>
    <div class="card" id="routes">
        <h3>Routes and fares</h3>
        <?php foreach ($routes as $r): ?>
            <div class="list-row">
                <div class="who"><b><?= e($r['route_name']) ?></b><span><?= e($r['origin']) ?> to <?= e($r['destination']) ?></span></div>
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
<script>document.addEventListener('DOMContentLoaded', () => SMARTRIDE.fleetMap('map', 'api_fleet.php'));</script>
<?php include '../views/partials/dashboard_bottom.php'; ?>
