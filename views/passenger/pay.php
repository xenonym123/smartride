<?php include '../views/partials/dashboard_top.php'; ?>
<?php page_head('Maya Checkout', 'Reload ' . peso($payment['amount']) . ' · reference ' . $payment['reference_no'], '<a class="btn ghost" href="passenger_reload.php">Back to reload</a>'); ?>
<?php show_flash(); ?>
<div class="row cols-main">
    <div class="card pay-card" data-payment-id="<?= (int) $payment['payment_id'] ?>" data-status="<?= e($payment['status']) ?>">
        <?php if ($payment['status'] === 'pending'): ?>
            <div class="pay-amount num"><?= peso($payment['amount']) ?></div>
            <?php if ($result === 'failure'): ?>
                <p class="flash error" style="margin:0"><?= icon('alert') ?><span>That payment didn't go through. You can try again on Maya's page.</span></p>
            <?php elseif ($result === 'success'): ?>
                <p class="pay-wait"><span class="spinner" aria-hidden="true"></span>Maya says you paid. Checking with Maya before we add it…</p>
            <?php else: ?>
                <p class="pay-wait" id="payWait"><span class="spinner" aria-hidden="true"></span>Waiting for your Maya payment…</p>
            <?php endif; ?>
            <p><span class="pill pill-brand" id="payCountdown" data-seconds="<?= (int) $seconds_left ?>"><?= icon('clock') ?>Open for 60:00</span></p>

            <?php if ($payment['provider'] === 'maya'): ?>
                <a class="btn block" href="<?= e($payment['checkout_url']) ?>"><?= icon('wallet') ?>Continue to Maya</a>
            <?php else: ?>
                <form method="post" action="passenger_pay.php?id=<?= (int) $payment['payment_id'] ?>">
                    <?= csrf_field() ?><input type="hidden" name="action" value="simulate">
                    <button class="btn block" type="submit"><?= icon('check') ?>Simulate payment (demo only)</button>
                </form>
            <?php endif; ?>
            <form method="post" action="passenger_pay.php?id=<?= (int) $payment['payment_id'] ?>" data-confirm="Cancel this reload?">
                <?= csrf_field() ?><input type="hidden" name="action" value="cancel">
                <button class="btn ghost block" type="submit">Cancel</button>
            </form>
        <?php elseif ($payment['status'] === 'paid'): ?>
            <div class="pay-done green"><?= icon('check') ?></div>
            <h3>Paid</h3>
            <p class="muted"><?= peso($payment['amount']) ?> is in your wallet. Maya confirmed it.</p>
            <a class="btn" href="passenger_home.php">Back to home</a>
        <?php else: ?>
            <div class="pay-done red"><?= icon('x') ?></div>
            <h3>This reload is <?= e($payment['status']) ?></h3>
            <p class="muted">Nothing was charged. Start a new reload if you still want to load your wallet.</p>
            <a class="btn" href="passenger_reload.php">New reload</a>
        <?php endif; ?>
    </div>
    <div class="card form-stack">
        <h3 style="margin:0">How Maya Checkout works</h3>
        <div class="list-row"><div class="who"><b>1. SmartRide asks Maya for a checkout</b><span>It's made for this exact amount and reference number.</span></div></div>
        <div class="list-row"><div class="who"><b>2. You pay on Maya's page</b><span>With a card or your Maya wallet. SmartRide never sees your card number.</span></div></div>
        <div class="list-row"><div class="who"><b>3. SmartRide checks with Maya</b><span>Your wallet is credited only when Maya says PAYMENT_SUCCESS. This page updates by itself.</span></div></div>
        <?php if ($payment['provider'] === 'maya'): ?>
            <h3 style="margin:8px 0 0">Sandbox test accounts</h3>
            <p class="hint">This is Maya's sandbox: no real money. Use these on Maya's page.</p>
            <div class="list-row"><div class="who"><b>Visa 4123 4501 3100 1381</b><span>Expiry 12/2030 · CVV 123 · 3-D Secure password mctest1</span></div></div>
            <div class="list-row"><div class="who"><b>Mastercard 5123 4567 8901 2346</b><span>Expiry 12/2030 · CVV 111 · no 3-D Secure</span></div></div>
            <div class="list-row"><div class="who"><b>Maya wallet +639900100900</b><span>Password Password@1 · OTP 123456</span></div></div>
        <?php else: ?>
            <p class="hint"><span class="pill pill-amber">Demo mode</span> No Maya keys in <code>config/keys.php</code>, so this is a stand-in for Maya's page.</p>
        <?php endif; ?>
    </div>
</div>
<?php if ($payment['status'] === 'pending'): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const id = document.querySelector('.pay-card').dataset.paymentId;

  // Count down the hour Maya keeps the checkout open.
  const cd = document.getElementById('payCountdown');
  let left = Number(cd.dataset.seconds);
  const showLeft = () => { cd.lastChild.textContent = `Open for ${Math.floor(left / 60)}:${String(left % 60).padStart(2, '0')}`; };
  showLeft();
  setInterval(() => { if (left > 0) { left -= 1; showLeft(); } }, 1000);

  // Every 4 seconds: has Maya confirmed it? When the status changes, reload to show the result.
  setInterval(async () => {
    try {
      const d = await SMARTRIDE.get('api_payment_status.php?id=' + id);
      if (d.status !== 'pending') location.href = 'passenger_pay.php?id=' + id;
    } catch (e) { /* try again next time */ }
  }, 4000);
});
</script>
<?php endif; ?>
<?php include '../views/partials/dashboard_bottom.php'; ?>
