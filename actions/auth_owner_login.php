<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(owner_secret_login_path());
}
require_csrf(owner_secret_login_path());

$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$stayLoggedIn = (string) ($_POST['stay_logged_in'] ?? '') === '1';

$lockStatus = auth_login_lock_status($username);
if (!empty($lockStatus['locked'])) {
    $retryAfterSeconds = max(1, (int) ($lockStatus['retry_after'] ?? 60));
    $retryAfterMinutes = (int) ceil($retryAfterSeconds / 60);
    log_activity($pdo, 'auth.owner_login_throttled', 'SaaS admin login blocked by rate-limit lockout.', [
        'username' => $username,
        'retry_after_seconds' => $retryAfterSeconds,
    ]);
    set_flash('error', 'Too many failed attempts. Try again in ' . $retryAfterMinutes . ' minute(s).');
    redirect(owner_secret_login_path());
}

if ($username === '' || $password === '') {
    auth_login_register_failure($username);
    log_activity($pdo, 'auth.owner_login_failed', 'SaaS admin login failed: missing username or password.', [
        'username' => $username,
    ]);
    set_flash('error', 'Username and password are required.');
    redirect(owner_secret_login_path());
}

$stmt = $pdo->prepare(
    "SELECT id, tenant_id, full_name, username, email, password_hash, role, status, avatar_path, theme_preference
     FROM users
     WHERE username = :username
     LIMIT 1"
);
$stmt->execute(['username' => $username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, (string) $user['password_hash']) || !is_owner($user)) {
    $result = auth_login_register_failure($username);
    log_activity($pdo, 'auth.owner_login_failed', 'SaaS admin login failed: invalid username or password.', [
        'username' => $username,
        'locked' => !empty($result['locked']) ? 1 : 0,
    ]);
    if (!empty($result['locked'])) {
        $retryAfterSeconds = max(1, (int) ($result['retry_after'] ?? 60));
        $retryAfterMinutes = (int) ceil($retryAfterSeconds / 60);
        set_flash('error', 'Too many failed attempts. Try again in ' . $retryAfterMinutes . ' minute(s).');
    } else {
        set_flash('error', 'Invalid username or password.');
    }
    redirect(owner_secret_login_path());
}

if ((string) ($user['status'] ?? 'active') !== 'active') {
    log_activity($pdo, 'auth.owner_login_blocked_inactive', 'SaaS admin login blocked: inactive user.', [
        'username' => $username,
        'user_id' => (int) ($user['id'] ?? 0),
    ]);
    set_flash('error', 'Your account is inactive.');
    redirect(owner_secret_login_path());
}

auth_login_clear_failures($username);
login_user($user);
if ($stayLoggedIn) {
    remember_store_login($pdo, (int) $user['id']);
} else {
    remember_forget_current($pdo);
}
log_activity($pdo, 'auth.owner_login', 'SaaS admin logged in.', [
    'user_id' => (int) $user['id'],
    'username' => (string) $user['username'],
], (int) $user['id']);
set_flash('success', 'Login successful.');
redirect('pages/tenants.php');
