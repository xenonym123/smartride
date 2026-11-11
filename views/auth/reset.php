<?php include '../views/partials/auth_top.php'; ?>
<form class="form" method="post" action="reset_password.php?token=<?= e(urlencode($token)) ?>" novalidate>
    <?= csrf_field() ?>
    <h1>Choose a new password</h1>
    <p class="lead">For <b><?= e($reset['username']) ?></b>. This link works once.</p>
    <?php show_flash(); ?>
    <div class="field<?= error_class($errors, 'password') ?>"><label for="password">New password</label>
        <input class="input" id="password" name="password" type="password" autocomplete="new-password" required><span class="hint">At least 8 characters</span><?= field_error($errors, 'password') ?></div>
    <div class="field<?= error_class($errors, 'password_confirm') ?>"><label for="password_confirm">Confirm new password</label>
        <input class="input" id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" required><?= field_error($errors, 'password_confirm') ?></div>
    <button class="btn lg block" type="submit">Save new password</button>
</form>
<?php include '../views/partials/auth_bottom.php'; ?>
