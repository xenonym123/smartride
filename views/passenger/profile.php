<?php include '../views/partials/dashboard_top.php'; ?>
<?php page_head('Profile', 'Your account details and your ride QR'); ?>
<?php show_flash(); ?>
<div class="row cols-main">
    <form class="card form-stack" method="post" action="passenger_profile.php" novalidate>
        <?= csrf_field() ?>
        <h3 style="margin:0">Account details</h3>
        <div class="form-grid">
            <div class="field<?= error_class($errors, 'full_name') ?>"><label for="full_name">Full name</label>
                <input class="input" id="full_name" name="full_name" maxlength="100" value="<?= e(old('full_name', $account['full_name'])) ?>"><?= field_error($errors, 'full_name') ?></div>
            <div class="field<?= error_class($errors, 'username') ?>"><label for="username">Username</label>
                <input class="input" id="username" name="username" maxlength="30" value="<?= e(old('username', $account['username'])) ?>"><?= field_error($errors, 'username') ?></div>
            <div class="field<?= error_class($errors, 'email') ?>"><label for="email">Email</label>
                <input class="input" id="email" name="email" type="email" maxlength="100" value="<?= e(old('email', $account['email'])) ?>"><?= field_error($errors, 'email') ?></div>
            <div class="field<?= error_class($errors, 'phone') ?>"><label for="phone">Mobile number</label>
                <input class="input" id="phone" name="phone" type="tel" maxlength="16" value="<?= e(old('phone', $account['phone'])) ?>"><?= field_error($errors, 'phone') ?></div>
        </div>
        <h3 style="margin:6px 0 0">Change password</h3>
        <p class="hint" style="margin-top:-8px">Leave these empty to keep your current password.</p>
        <div class="form-grid">
            <div class="field<?= error_class($errors, 'password') ?>"><label for="new_password">New password</label>
                <input class="input" id="new_password" name="new_password" type="password" autocomplete="new-password"><?= field_error($errors, 'password') ?></div>
            <div class="field<?= error_class($errors, 'password_confirm') ?>"><label for="new_password_confirm">Confirm new password</label>
                <input class="input" id="new_password_confirm" name="new_password_confirm" type="password" autocomplete="new-password"><?= field_error($errors, 'password_confirm') ?></div>
        </div>
        <div class="field<?= error_class($errors, 'current_password') ?>" style="max-width:420px"><label for="current_password">Current password</label>
            <input class="input" id="current_password" name="current_password" type="password" autocomplete="current-password" required><span class="hint">Needed to save any change</span><?= field_error($errors, 'current_password') ?></div>
        <div><button class="btn" type="submit">Save changes</button></div>
    </form>
    <div class="card" style="display:flex;flex-direction:column;align-items:center;text-align:center;gap:14px">
        <h3 style="align-self:flex-start;margin:0">Your ride QR</h3>
        <div class="qr-frame<?= $wallet['status'] === 'frozen' ? ' frozen' : '' ?>"><span id="profileQR"></span></div>
        <p style="font-size:15px">Linked to your wallet. No card to carry and nothing to type in.</p>
        <p style="display:flex;gap:8px;flex-wrap:wrap;justify-content:center"><span class="pill pill-brand" data-qr-countdown>Loading…</span><?= pill($wallet['status']) ?></p>
        <a class="btn ghost" href="passenger_qr.php"><?= $wallet['status'] === 'active' ? 'Lost your phone? Freeze your QR' : 'Unfreeze your QR' ?></a>
        <p class="hint">Member since <?= e(fmt_date($account['created_at'])) ?></p>
    </div>
</div>
<script src="../assets/vendor/qrcode.js"></script>
<script>document.addEventListener('DOMContentLoaded', () => SMARTRIDE.liveQR({ url: 'api_qr_token.php', target: '#profileQR' }));</script>
<?php include '../views/partials/dashboard_bottom.php'; ?>
