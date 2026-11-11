<?php include '../views/partials/auth_top.php'; ?>
<form class="form" method="post" action="forgot_password.php" novalidate>
    <?= csrf_field() ?>
    <h1>Forgot password</h1>
    <p class="lead">Type the email on your account. We'll send a link to choose a new password.</p>
    <?php show_flash(); ?>
    <?php if ($sent): ?>
        <div class="flash success" role="status"><?= icon('check') ?><span>If that email is in SmartRide, a reset link is on its way. It expires in 30 minutes.</span></div>
        <?php if ($demo): ?>
            <p class="hint"><span class="pill pill-amber">Demo mode</span> Gmail isn't set up in config/keys.php, so no email was sent. The link was written to the PHP error log instead.</p>
        <?php endif; ?>
    <?php endif; ?>
    <div class="field"><label for="email">Email</label>
        <input class="input" id="email" name="email" type="email" autocomplete="email" maxlength="100" required value="<?= e(old('email')) ?>"></div>
    <button class="btn lg block" type="submit">Send reset link</button>
    <p class="center muted"><a class="linkbtn" href="login.php">Back to sign in</a></p>
</form>
<?php include '../views/partials/auth_bottom.php'; ?>
