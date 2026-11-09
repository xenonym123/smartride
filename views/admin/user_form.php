<?php include '../views/partials/dashboard_top.php'; ?>
<?php
// $account is null when adding a new user.
$editing = $account !== null;
$role_now = old('role', $editing ? $account['role_name'] : 'driver');
$is_me = $editing && (int) $account['user_id'] === (int) $_SESSION['user_id'];

if ($editing) {
    page_head('Edit account', $account['full_name'] . ' · ' . $account['username'], '<a class="btn ghost" href="admin_users.php">Back to users</a>');
} else {
    page_head('Add user', 'Create a driver, admin or passenger account', '<a class="btn ghost" href="admin_users.php">Back to users</a>');
}
?>
<?php show_flash(); ?>
<div class="row cols-main">
    <form class="card form-stack" method="post" action="admin_user_form.php<?= $editing ? '?id=' . (int) $account['user_id'] : '' ?>" novalidate>
        <?= csrf_field() ?>
        <div class="form-grid">
            <div class="field full<?= error_class($errors, 'full_name') ?>"><label for="full_name">Full name</label>
                <input class="input" id="full_name" name="full_name" maxlength="100" value="<?= e(old('full_name', $editing ? $account['full_name'] : '')) ?>"><?= field_error($errors, 'full_name') ?></div>
            <div class="field<?= error_class($errors, 'email') ?>"><label for="email">Email</label>
                <input class="input" id="email" name="email" type="email" maxlength="100" value="<?= e(old('email', $editing ? $account['email'] : '')) ?>"><?= field_error($errors, 'email') ?></div>
            <div class="field<?= error_class($errors, 'phone') ?>"><label for="phone">Mobile number</label>
                <input class="input" id="phone" name="phone" type="tel" maxlength="16" placeholder="09171234567" value="<?= e(old('phone', $editing ? $account['phone'] : '')) ?>"><?= field_error($errors, 'phone') ?></div>
            <div class="field<?= error_class($errors, 'username') ?>"><label for="username">Username</label>
                <input class="input" id="username" name="username" maxlength="30" value="<?= e(old('username', $editing ? $account['username'] : '')) ?>"><?= field_error($errors, 'username') ?></div>
            <div class="field"><label for="role">Role</label>
                <select class="input" id="role" name="role" <?= $is_me ? 'disabled' : '' ?>>
                    <?php foreach (['driver' => 'Driver', 'admin' => 'Admin (operator side)', 'passenger' => 'Passenger'] as $key => $label): ?>
                        <option value="<?= $key ?>" <?= $role_now === $key ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($is_me): ?><span class="hint">You can't change your own role.</span><?php endif; ?></div>
            <div class="field full<?= error_class($errors, 'password') ?>"><label for="password"><?= $editing ? 'New password' : 'Password' ?></label>
                <input class="input" id="password" name="password" type="text" autocomplete="new-password" placeholder="<?= $editing ? 'Leave empty to keep the current one' : 'At least 8 characters' ?>">
                <span class="hint"><?= $editing ? 'Type one here to reset it, then tell the user.' : 'Give this to the person so they can sign in.' ?></span><?= field_error($errors, 'password') ?></div>
        </div>
        <div><button class="btn" type="submit"><?= $editing ? 'Save changes' : 'Create account' ?></button></div>
    </form>
    <div class="card">
        <?php if ($editing): ?>
            <h3>Account</h3>
            <div class="list-row"><span>Status</span><?= pill($account['status']) ?></div>
            <div class="list-row"><span>Created</span><span><?= e(fmt_date($account['created_at'])) ?></span></div>
            <?php if ($wallet): ?>
                <div class="list-row"><span>Wallet balance</span><b class="num"><?= peso($wallet['balance']) ?></b></div>
                <div class="list-row"><span>Ride QR</span><?= pill($wallet['status']) ?></div>
            <?php endif; ?>
        <?php else: ?>
            <h3>Who can sign up on their own?</h3>
            <p class="muted">Only passengers. Drivers and admins are added here, so nobody can make themselves a driver or an admin from the public site.</p>
            <p class="muted" style="margin-top:10px">Admins also see the operator side: fares per unit, capacity, and the fleet.</p>
        <?php endif; ?>
    </div>
</div>
<?php include '../views/partials/dashboard_bottom.php'; ?>
