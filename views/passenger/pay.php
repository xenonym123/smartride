<?php include '../views/partials/dashboard_top.php'; ?>
<?php page_head('Pay with QR Ph', 'Reload ' . peso($payment['amount']) . ' through PayMongo', '<a class="btn ghost" href="passenger_reload.php">Back to reload</a>'); ?>
<?php show_flash(); ?>
<div class="row cols-main">
    <div class="card pay-card" data-payment-id="<?= (int) $payment['payment_id'] ?>" data-status="<?= e($payment['status']) ?>">
        <?php if ($payment['status'] === 'pending'): ?>
            <div class="qr-frame big">
                <?php if ($payment['provider'] === 'paymongo'): ?>
                    <img src="<?= e($payment['qr_image']) ?>" alt="QR Ph code for <?= e(peso($payment['amount'])) ?>" width="240" height="240">
                <?php else: ?>
                    <span id="demoPayQR"></span>
                <?php endif; ?>
            </div>
            <div class="pay-amount num"><?= peso($payment['amount']) ?></div>
            <p class="muted">Open GCash, Maya or your bank app, choose <b>Scan QR</b>, and scan this code.</p>
            <p><span class="pill pill-brand" id="payCountdown" data-seconds="<?= (int) $seconds_left ?>"><?= icon('clock') ?>Expires in 30:00</span></p>
            <p class="pay-wait" id="payWait"><span class="spinner" aria-hidden="true"></span>Waiting for PayMongo to confirm…</p>

            <?php if ($payment['provider'] === 'demo'): ?>
                <form method="post" action="passenger_pay.php?id=<?= (int) $payment['payment_id'] ?>">
                    <?= csrf_field() ?><input type="hidden" name="action" value="simulate">
                    <button class="btn block" type="submit"><?= icon('check') ?>Simulate payment (demo only)</button>
                </form>
            <?php elseif (!empty($payment['test_url'])): ?>
                <a class="btn ghost block" href="<?= e($payment['test_url']) ?>" target="_blank" rel="noopener">Open PayMongo's test page to simulate paying</a>
            <?php endif; ?>
            <form method="post" action="passenger_pay.php?id=<?= (int) $payment['payment_id'] ?>" data-confirm="Cancel this reload?">
                <?= csrf_field() ?><input type="hidden" name="action" value="cancel">
                <button class="btn ghost block" type="submit">Cancel</button>
            </form>
        <?php elseif ($payment['status'] === 'paid'): ?>
            <div class="pay-done green"><?= icon('check') ?></div>
            <h3>Paid</h3>
            <p class="muted"><?= peso($payment['amount']) ?> is in your wallet.</p>
            <a class="btn" href="passenger_home.php">Back to home</a>
        <?php else: ?>
            <div class="pay-done red"><?= icon('x') ?></div>
            <h3>This QR is <?= e($payment['status']) ?></h3>
            <p class="muted">Nothing was charged. Make a new reload to get a fresh QR.</p>
            <a class="btn" href="passenger_reload.php">New reload</a>
        <?php endif; ?>
    </div>
    <div class="card form-stack">
        <h3 style="margin:0">How QR Ph works</h3>
        <div class="list-row"><div class="who"><b>1. SmartRide asks PayMongo for a QR</b><span>It's made for this exact amount and only works once.</span></div></div>
        <div class="list-row"><div class="who"><b>2. You pay with any QR Ph app</b><span>GCash, Maya, BPI, BDO, UnionBank and other banks all read QR Ph.</span></div></div>
        <div class="list-row"><div class="who"><b>3. PayMongo tells SmartRide</b><span>Your wallet is credited the moment the payment is confirmed. This page updates by itself.</span></div></div>
        <p class="hint">The code expires after 30 minutes. If it does, just make a new reload.</p>
        <?php if ($payment['provider'] === 'demo'): ?>
            <p class="hint"><span class="pill pill-amber">Demo mode</span> This is a sample QR. Add your PayMongo test key in <code>config/config.php</code> to get a real one.</p>
        <?php endif; ?>
    </div>
</div>
<?php if ($payment['status'] === 'pending'): ?>
<script src="../assets/vendor/qrcode.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const card = document.querySelector('.pay-card');
  const id = card.dataset.paymentId;

  // Demo mode draws a sample QR. A real PayMongo QR is already an <img>.
  const demo = document.getElementById('demoPayQR');
  if (demo) SMARTRIDE.drawQR(demo, 'SR-DEMO-PAY-' + id);

  // Count down the 30 minutes.
  const cd = document.getElementById('payCountdown');
  let left = Number(cd.dataset.seconds);
  const showLeft = () => { cd.lastChild.textContent = `Expires in ${Math.floor(left / 60)}:${String(left % 60).padStart(2, '0')}`; };
  showLeft();
  setInterval(() => { if (left > 0) { left -= 1; showLeft(); } }, 1000);

  // Every 3 seconds: is it paid yet? When it changes, reload the page to show the result.
  setInterval(async () => {
    try {
      const d = await SMARTRIDE.get('api_payment_status.php?id=' + id);
      if (d.status !== 'pending') location.reload();
    } catch (e) { /* try again next time */ }
  }, 3000);
});
</script>
<?php endif; ?>
<?php include '../views/partials/dashboard_bottom.php'; ?>
