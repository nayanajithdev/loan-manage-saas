<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect(authenticated_landing_path());
}

$faviconPath = business_icon_path($pdo);
$loginBusinessName = trim(system_setting($pdo, 'business_name', APP_NAME));
if ($loginBusinessName === '') {
    $loginBusinessName = APP_NAME;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Business | <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <?php if ($faviconPath !== ''): ?>
        <link rel="icon" href="<?= e(url($faviconPath)) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body class="auth-body">
<div class="auth-shell">
    <section class="auth-card auth-card-wide">
        <h1><?= e($loginBusinessName) ?></h1>
        <p class="auth-sub">Business registration</p>

        <?php if ($flash): ?>
            <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('actions/tenant_register.php')) ?>" class="form-grid auth-form-grid">
            <?= csrf_input() ?>
            <div class="field full">
                <label>Business Name</label>
                <input type="text" name="name" required autofocus>
            </div>
            <div class="field full">
                <label>Owner Name</label>
                <input type="text" name="owner_name" required>
            </div>
            <div class="field full">
                <label>Owner Email</label>
                <input type="email" name="owner_email" required>
            </div>
            <div class="field full">
                <label>Phone</label>
                <input type="text" name="phone">
            </div>
            <div class="field full">
                <label>Owner Username</label>
                <input type="text" name="username" required>
            </div>
            <div class="field full">
                <label>Password</label>
                <input type="password" name="password" required minlength="8">
            </div>
            <div class="field full">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" required minlength="8">
            </div>

            <div class="field full" style="align-self:end;">
                <button type="submit" class="btn btn-primary">Register Business</button>
            </div>
            <div class="field full auth-register-row">
                <span>Already approved?</span>
                <a class="auth-link" href="<?= e(url('login.php')) ?>">Login</a>
            </div>
        </form>
    </section>
</div>
</body>
</html>
