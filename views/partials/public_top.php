<?php
// Top of the public pages (Home, About Us, Pay with QR). Uses $title and $page.
$page = $page ?? '';
?>
<!doctype html>
<html lang="en">
<head><?php include '../views/partials/head.php'; ?></head>
<body class="pub">
<header class="topnav" id="topnav">
    <div class="wrap">
        <a href="home.php" aria-label="SmartRide home"><?= logo() ?></a>
        <nav aria-label="Main">
            <a href="home.php" class="<?= $page === 'home' ? 'on' : '' ?>">Home</a>
            <a href="about.php" class="<?= $page === 'about' ? 'on' : '' ?>">About Us</a>
            <a href="pay_with_qr.php" class="<?= $page === 'qr' ? 'on' : '' ?>">Pay with QR</a>
        </nav>
        <div class="actions">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a class="btn" href="<?= home_for_role($_SESSION['role']) ?>">My dashboard</a>
            <?php else: ?>
                <a class="btn ghost" href="login.php">Login</a>
                <a class="btn" href="register.php">Create account</a>
            <?php endif; ?>
            <button class="navtoggle" type="button" aria-label="Menu" aria-expanded="false" data-toggle-nav><?= icon('menu') ?></button>
        </div>
    </div>
</header>
<?php if (!empty($_SESSION['flash'])): ?><div class="wrap" style="padding-top:16px"><?php show_flash(); ?></div><?php endif; ?>
<main>
