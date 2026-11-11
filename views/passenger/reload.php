<?php include '../views/partials/dashboard_top.php'; ?>
<?php page_head('Reload wallet', 'Balance: ' . peso($wallet['balance'])); ?>
<?php show_flash(); ?>
<div class="row cols-2">
    <div class="card">
        <h3>Add money</h3>
        <form method="post" action="passenger_reload.php" class="form-stack" id="reloadForm">
            <?= csrf_field() ?>
            <div class="amounts" role="radiogroup" aria-label="Amount">
                <?php foreach ([50, 100, 200, 500] as $amount): ?>
                    <label><input type="radio" name="preset" value="<?= $amount ?>" <?= $amount === 100 ? 'checked' : '' ?>><span>₱<?= $amount ?></span></label>
                <?php endforeach; ?>
            </div>
            <div class="field"><label for="amount">Amount (₱)</label>
                <input class="input num" id="amount" name="amount" type="number" inputmode="decimal" min="<?= RELOAD_MIN ?>" max="<?= RELOAD_MAX ?>" step="1" value="100" required>
                <span class="hint">Between <?= peso(RELOAD_MIN, 0) ?> and <?= peso(RELOAD_MAX, 0) ?></span></div>
            <div class="field"><label>Pay with</label>
                <div class="methods">
                    <?php foreach (reload_methods() as $key => $label): ?>
                        <label><input type="radio" name="method" value="<?= e($key) ?>" <?= $key === 'maya' ? 'checked' : '' ?>><?= e($label) ?></label>
                    <?php endforeach; ?>
                </div></div>
            <button class="btn lg block" type="submit">Reload wallet</button>
            <p class="hint"><b>Maya Checkout</b> opens Maya's payment page, where you pay with a card or your Maya wallet. The money lands once Maya confirms it. Cash reloads are approved right away in the demo.</p>
            <?php if ($demo_mode): ?>
                <p class="hint"><span class="pill pill-amber">Demo mode</span> No Maya keys in config/keys.php, so Maya Checkout shows a stand-in page with a "Simulate payment" button.</p>
            <?php endif; ?>
        </form>
    </div>
    <div class="card">
        <h3>Reload history</h3>
        <?php if (!$history): ?><p class="empty">No reloads yet.</p><?php endif; ?>
        <?php foreach ($history as $h): ?>
            <div class="list-row">
                <div class="who"><b><?= e(reload_method_label($h['method'])) ?></b><span><?= e(fmt_date($h['reloaded_at'], 'M j, Y · g:i A')) ?></span></div>
                <span class="amt-plus num">+<?= peso($h['amount']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<script>
// Clicking ₱50/₱100/... fills the amount box. Typing your own amount clears the buttons.
document.querySelectorAll('#reloadForm input[name=preset]').forEach((r) => r.addEventListener('change', () => { document.getElementById('amount').value = r.value; }));
document.getElementById('amount').addEventListener('input', () => document.querySelectorAll('#reloadForm input[name=preset]').forEach((r) => { r.checked = false; }));
</script>
<?php include '../views/partials/dashboard_bottom.php'; ?>
