<?php include '../views/partials/auth_top.php'; ?>
<form class="form" method="post" action="register.php" novalidate>
    <?= csrf_field() ?>
    <h1>Create an account</h1>
    <p class="lead">For passengers. Your wallet and ride QR are made for you.</p>
    <?php show_flash(); ?>
    <div class="form-grid">
        <div class="field full<?= error_class($errors, 'full_name') ?>"><label for="full_name">Full name</label>
            <input class="input" id="full_name" name="full_name" autocomplete="name" maxlength="100" required value="<?= e(old('full_name')) ?>"><?= field_error($errors, 'full_name') ?></div>
        <div class="field<?= error_class($errors, 'email') ?>"><label for="email">Email</label>
            <input class="input" id="email" name="email" type="email" autocomplete="email" maxlength="100" required value="<?= e(old('email')) ?>"><?= field_error($errors, 'email') ?></div>
        <div class="field<?= error_class($errors, 'phone') ?>"><label for="phone">Mobile number</label>
            <input class="input" id="phone" name="phone" type="tel" inputmode="numeric" autocomplete="tel" placeholder="09171234567" maxlength="16" required value="<?= e(old('phone')) ?>"><?= field_error($errors, 'phone') ?></div>
        <div class="field full<?= error_class($errors, 'username') ?>"><label for="username">Username</label>
            <input class="input" id="username" name="username" autocomplete="username" maxlength="30" required value="<?= e(old('username')) ?>"><?= field_error($errors, 'username') ?></div>
        <div class="field<?= error_class($errors, 'password') ?>"><label for="password">Password</label>
            <input class="input" id="password" name="password" type="password" autocomplete="new-password" required><span class="hint">At least 8 characters</span><?= field_error($errors, 'password') ?></div>
        <div class="field<?= error_class($errors, 'password_confirm') ?>"><label for="password_confirm">Confirm password</label>
            <input class="input" id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" required><?= field_error($errors, 'password_confirm') ?></div>
    </div>
    <button class="btn lg block" type="submit">Create account</button>
    <p class="center muted">Already have one? <a class="linkbtn" href="login.php">Sign in</a></p>
</form>
<?php include '../views/partials/auth_bottom.php'; ?>
