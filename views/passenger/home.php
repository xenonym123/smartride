<?php include '../views/partials/dashboard_top.php'; ?>
<?php page_head('Welcome back, ' . first_name($_SESSION['full_name']), "Here's your wallet and your next ride", pill($wallet['status'] === 'active' ? 'active' : 'frozen')); ?>
<?php show_flash(); ?>
<?php include '../views/partials/alerts_banner.php'; ?>

<div class="row cols-main">
    <div class="card balance stat-card">
        <div class="label">Wallet balance</div>
        <div class="value"><?= peso($wallet['balance']) ?></div>
        <div class="sub">Good for about <?= (int) floor($wallet['balance'] / 15) ?> rides at ₱15</div>
        <div class="mini">
            <div><span class="sub">Rides this month</span><b class="num"><?= (int) $month['rides'] ?></b></div>
            <div><span class="sub">Spent this month</span><b class="num"><?= peso($month['spent']) ?></b></div>
        </div>
    </div>
    <div class="card qr-card">
        <a class="qr-frame<?= $wallet['status'] === 'frozen' ? ' frozen' : '' ?>" href="passenger_qr.php" aria-label="Open my QR"><span id="miniQR"></span></a>
        <div>
            <h3 style="margin-bottom:6px">Your ride QR</h3>
            <p style="font-size:15px">Show this to the scanner when you board. The fare comes out of your wallet.</p>
            <p style="margin-top:12px"><span class="pill pill-brand" data-qr-countdown>Loading…</span></p>
        </div>
    </div>
</div>

<div class="row cols-main">
    <div class="card">
        <div class="head"><h3>Next jeepney</h3><a class="btn sm ghost" href="passenger_track.php?locate=1"><?= icon('locate') ?>Nearest to me</a></div>
        <?php if ($next): ?>
            <?php $load = min(100, round($next['onboard'] / max(1, $next['capacity']) * 100)); ?>
            <div class="list-row">
                <div class="who"><b><?= e($next['unit_code']) ?> · <?= e($next['route_name']) ?></b><span>Last seen <?= e($next['seen']) ?> · driven by <?= e(first_name($next['driver_name'])) ?></span></div>
                <?php if ($next['seats_left'] > 0): ?>
                    <span class="pill pill-green"><?= (int) $next['seats_left'] ?> seats left</span>
                <?php else: ?>
                    <span class="pill pill-amber">Full</span>
                <?php endif; ?>
            </div>
            <div class="bars" style="margin-top:14px">
                <div class="bar-row"><b>On board</b><div class="bar"><i class="<?= $next['seats_left'] == 0 ? 'full' : '' ?>" style="width:<?= $load ?>%"></i></div><span class="num"><?= (int) $next['onboard'] ?>/<?= (int) $next['capacity'] ?></span></div>
            </div>
            <p class="hint" style="margin-top:14px">When a unit reaches <?= (int) $next['capacity'] ?>, its scanner stops taking fares until someone gets off.</p>
        <?php else: ?>
            <p class="empty">No SmartRide units are on the road right now.</p>
        <?php endif; ?>
    </div>
    <div class="card">
        <h3>Quick reload</h3>
        <form method="post" action="passenger_reload.php" class="form-stack">
            <?= csrf_field() ?>
            <div class="amounts" role="radiogroup" aria-label="Amount">
                <?php foreach ([50, 100, 200, 500] as $amount): ?>
                    <label><input type="radio" name="amount" value="<?= $amount ?>" <?= $amount === 100 ? 'checked' : '' ?>><span>₱<?= $amount ?></span></label>
                <?php endforeach; ?>
            </div>
            <div class="field"><label for="qmethod">Pay with</label>
                <select class="input" id="qmethod" name="method">
                    <?php foreach (reload_methods() as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?>
                </select></div>
            <button class="btn block" type="submit">Reload wallet</button>
        </form>

        <h3 style="margin:22px 0 4px">Recent</h3>
        <?php if (!$recent): ?><p class="empty">Nothing yet. Your rides and reloads show up here.</p><?php endif; ?>
        <?php foreach ($recent as $r): ?>
            <div class="list-row">
                <?php if ($r['status'] === 'reload'): ?>
                    <div class="who"><b><?= e($r['label']) ?></b><span><?= e(fmt_date($r['when'], 'M j · g:i A')) ?></span></div>
                    <span class="num amt-plus">+<?= peso($r['amount']) ?></span>
                <?php elseif ($r['status'] === 'paid'): ?>
                    <div class="who"><b><?= e($r['label']) ?></b><span><?= e(fmt_date($r['when'], 'M j · g:i A')) ?></span></div>
                    <span class="num amt-minus">−<?= peso($r['amount']) ?></span>
                <?php else: ?>
                    <div class="who"><b><?= e($r['label']) ?></b><span><?= e(fmt_date($r['when'], 'M j · g:i A')) ?> · <?= strip_tags(pill($r['status'])) ?></span></div>
                    <span class="num muted">₱0.00</span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script src="../assets/vendor/qrcode.js"></script>
<script>document.addEventListener('DOMContentLoaded', () => SMARTRIDE.liveQR({ url: 'api_qr_token.php', target: '#miniQR' }));</script>
<?php include '../views/partials/dashboard_bottom.php'; ?>
