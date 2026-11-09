<?php include '../views/partials/dashboard_top.php'; ?>
<?php page_head('My QR', 'Show this to the scanner by the door when you board'); ?>
<?php show_flash(); ?>
<div class="row cols-main">
    <div class="card" style="display:flex;flex-direction:column;align-items:center;text-align:center;gap:16px;padding-block:34px">
        <div class="qr-frame big<?= $wallet['status'] === 'frozen' ? ' frozen' : '' ?>"><span id="bigQR"></span></div>
        <div>
            <div class="muted small">Wallet balance</div>
            <div style="font-size:32px;font-weight:700;letter-spacing:-.6px" class="num"><?= peso($wallet['balance']) ?></div>
        </div>
        <span class="pill pill-brand" data-qr-countdown>Loading…</span>
        <p class="hint" style="max-width:30em">The code changes every 30 seconds, so a screenshot stops working right away. Turn your screen brightness up if the scanner is slow to read it.</p>
    </div>
    <div class="card form-stack">
        <h3 style="margin:0">How paying works</h3>
        <div class="list-row"><div class="who"><b>1. Open this page</b><span>Or tap the QR on your Home screen.</span></div></div>
        <div class="list-row"><div class="who"><b>2. Hold it to the scanner</b><span>The fare for that route comes out of your wallet.</span></div></div>
        <div class="list-row"><div class="who"><b>3. Board</b><span>If the unit is full, the scanner won't take your fare, so you're never charged for a ride you can't take.</span></div></div>
        <hr style="border:0;border-top:1px solid var(--line)">
        <?php if ($wallet['status'] === 'active'): ?>
            <h3 style="margin:0">Lost your phone?</h3>
            <p class="muted">Freeze your QR from any device. Nobody can pay with it until you unfreeze it.</p>
            <form method="post" action="passenger_qr.php" data-confirm="Freeze your ride QR? You won't be able to pay until you unfreeze it.">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="freeze">
                <button class="btn danger block" type="submit"><?= icon('snow') ?>Freeze my QR</button>
            </form>
        <?php else: ?>
            <h3 style="margin:0">Your QR is frozen</h3>
            <p class="muted">Unfreezing gives you a brand-new code, so any copy of the old one stays useless.</p>
            <form method="post" action="passenger_qr.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="unfreeze">
                <button class="btn block" type="submit"><?= icon('check') ?>Unfreeze with a new code</button>
            </form>
        <?php endif; ?>
    </div>
</div>
<script src="../assets/vendor/qrcode.js"></script>
<script>document.addEventListener('DOMContentLoaded', () => SMARTRIDE.liveQR({ url: 'api_qr_token.php', target: '#bigQR' }));</script>
<?php include '../views/partials/dashboard_bottom.php'; ?>
