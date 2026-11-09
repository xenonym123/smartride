<?php include '../views/partials/dashboard_top.php'; ?>
<?php page_head('User management', 'Passengers, drivers and admins',
    '<a class="btn ghost" href="admin_users_export.php">' . icon('download') . 'Export CSV</a><a class="btn" href="admin_user_form.php">' . icon('plus') . 'Add user</a>'); ?>
<?php show_flash(); ?>
<div class="card">
    <div class="head">
        <div class="filters">
            <?php foreach (['' => 'All', 'passenger' => 'Passengers', 'driver' => 'Drivers', 'admin' => 'Admins'] as $key => $label): ?>
                <a class="<?= $role === $key ? 'on' : '' ?>" href="admin_users.php<?= $key ? '?role=' . $key : '' ?>"><?= e($label) ?><?= $key ? ' · ' . (int) ($counts[$key] ?? 0) : '' ?></a>
            <?php endforeach; ?>
        </div>
        <form class="searchbar" method="get" action="admin_users.php">
            <?php if ($role): ?><input type="hidden" name="role" value="<?= e($role) ?>"><?php endif; ?>
            <label class="sr-only" for="q">Search</label><input class="input" id="q" name="q" maxlength="60" placeholder="Search name, username, email" value="<?= e($q) ?>">
            <button class="btn ghost sm" type="submit">Search</button>
        </form>
    </div>
    <?php if (!$users): ?>
        <p class="empty">No accounts match.</p>
    <?php else: ?>
    <div class="table-scroll"><table class="data">
        <thead><tr><th>ID</th><th>Name</th><th>Role</th><th>Wallet</th><th>Last sign-in</th><th>Status</th><th class="r">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td class="num muted"><?= str_pad((string) $u['user_id'], 3, '0', STR_PAD_LEFT) ?></td>
                <td><b><?= e($u['full_name']) ?></b><br><span class="muted small"><?= e($u['username']) ?> · <?= e($u['email']) ?></span></td>
                <td><?= e(ucfirst($u['role_name'])) ?></td>
                <td class="num">
                    <?php if ($u['balance'] !== null): ?>
                        <?= peso($u['balance']) ?><?= $u['wallet_status'] === 'frozen' ? ' ' . pill('frozen') : '' ?>
                    <?php else: ?>
                        <span class="muted">—</span>
                    <?php endif; ?>
                </td>
                <td class="small muted"><?= e(time_ago($u['last_login'])) ?></td>
                <td><?= pill($u['status']) ?></td>
                <td class="r" style="white-space:nowrap">
                    <a class="btn sm ghost" href="admin_user_form.php?id=<?= (int) $u['user_id'] ?>"><?= icon('edit') ?>Edit</a>
                    <?php if ((int) $u['user_id'] !== (int) $_SESSION['user_id']): ?>
                        <form class="inline-form" method="post" action="admin_users.php"
                              <?= $u['status'] === 'active' ? 'data-confirm="Deactivate ' . e($u['full_name']) . '? They won&#039;t be able to sign in."' : '' ?>>
                            <?= csrf_field() ?>
                            <input type="hidden" name="user_id" value="<?= (int) $u['user_id'] ?>">
                            <button class="btn sm <?= $u['status'] === 'active' ? 'danger' : 'ghost' ?>" type="submit"><?= $u['status'] === 'active' ? 'Deactivate' : 'Reactivate' ?></button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
    <p class="hint" style="margin-top:14px">Accounts are deactivated instead of deleted, so their rides, fares and logs stay on record.</p>
</div>
<?php include '../views/partials/dashboard_bottom.php'; ?>
