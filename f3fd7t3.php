<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$requestPath = current_request_app_path();
if ($requestPath !== owner_secret_login_path() && $requestPath !== owner_secret_login_path() . '.php') {
    http_response_code(404);
    exit('Not found');
}

if (is_logged_in()) {
    redirect(authenticated_landing_path());
}

$ownerExists = has_superadmin($pdo);
$faviconPath = business_icon_path($pdo);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SaaS Admin Access | <?= e(APP_NAME) ?></title>
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
        <h1><?= $ownerExists ? 'SaaS Admin Login' : 'Setup SaaS Admin' ?></h1>
        <p class="auth-sub"><?= $ownerExists ? 'SaaS admin access' : 'Create the SaaS admin account once.' ?></p>

        <?php if ($flash): ?>
            <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endif; ?>

        <?php if ($ownerExists): ?>
            <form method="post" action="<?= e(url('actions/auth_owner_login.php')) ?>" class="form-grid auth-form-grid">
                <?= csrf_input() ?>
                <div class="field full">
                    <label>Username</label>
                    <input type="text" name="username" required autofocus>
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
            </form>
        <?php else: ?>
            <form method="post" action="<?= e(url('actions/auth_setup_superadmin.php')) ?>" class="form-grid auth-form-grid">
                <?= csrf_input() ?>
                <div class="field full">
                    <label>Full Name</label>
                    <input type="text" name="full_name" required autofocus>
                </div>
                <div class="field full">
                    <label>Username</label>
                    <input type="text" name="username" required>
                </div>
                <div class="field full">
                    <label>Email</label>
                    <input type="email" name="email" required>
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
                    <button type="submit" class="btn btn-primary">Create SaaS Admin</button>
                </div>
            </form>
        <?php endif; ?>
    </section>
</div>
</body>
</html>
