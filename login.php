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
    <title>Login | <?= e(APP_NAME) ?></title>
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
    <section class="auth-card">
        <h1><?= e($loginBusinessName) ?></h1>
        <p class="auth-sub">Business login</p>

        <?php if ($flash): ?>
            <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('actions/auth_login.php')) ?>" class="form-grid auth-form-grid">
            <?= csrf_input() ?>
            <div class="field full">
                <label>Username</label>
                <input type="text" name="username" autocomplete="username" required autofocus>
            </div>
            <div class="field full">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <div class="field full auth-links-row">
                <label class="choice-check auth-stay-check">
                    <input type="checkbox" name="stay_logged_in" value="1">
                    <span class="choice-check-box" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                    </span>
                    <span class="choice-check-label">Stay logged in</span>
                </label>
                <a class="auth-link" href="<?= e(url('forgot_password.php')) ?>">Forgot password?</a>
            </div>
            <div class="field full" style="align-self:end;">
                <button type="submit" class="btn btn-primary">Login</button>
            </div>
            <div class="field full auth-register-row">
                <span>Need a business account?</span>
                <a class="auth-link" href="<?= e(url('register.php')) ?>">Register business</a>
            </div>
        </form>
    </section>
</div>
</body>
</html>
