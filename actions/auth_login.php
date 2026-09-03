<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('login.php');
}
require_csrf('login.php');

if (!has_superadmin($pdo)) {
    log_activity($pdo, 'auth.login_blocked', 'Login blocked because no owner exists.');
    set_flash('error', 'Business login is not available yet.');
    redirect('login.php');
}

$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$stayLoggedIn = (string) ($_POST['stay_logged_in'] ?? '') === '1';
$normalizedUsernameForRateLimit = strtolower($username);

// Check lockout before running password verification.
$lockStatus = auth_login_lock_status($normalizedUsernameForRateLimit);
if (!empty($lockStatus['locked'])) {
    $retryAfterSeconds = max(1, (int) ($lockStatus['retry_after'] ?? 60));
    $retryAfterMinutes = (int) ceil($retryAfterSeconds / 60);
    log_activity($pdo, 'auth.login_throttled', 'Login blocked by rate-limit lockout.', [
        'username' => $username,
        'retry_after_seconds' => $retryAfterSeconds,
    ]);
    set_flash('error', 'Too many failed attempts. Try again in ' . $retryAfterMinutes . ' minute(s).');
    redirect('login.php');
}

if ($username === '' || $password === '') {
    auth_login_register_failure($normalizedUsernameForRateLimit);
    log_activity($pdo, 'auth.login_failed', 'Login failed: missing username or password.', [
        'username' => $username,
    ]);
    set_flash('error', 'Username and password are required.');
    redirect('login.php');
}

$stmt = $pdo->prepare(
    "SELECT u.id, u.tenant_id, u.full_name, u.username, u.email, u.password_hash, u.role, u.status, u.avatar_path, u.theme_preference,
            t.name AS tenant_name, t.status AS tenant_status
     FROM users u
     LEFT JOIN tenants t ON t.id = u.tenant_id
     WHERE u.username = :username
     LIMIT 1"
);
$stmt->execute(['username' => $username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, (string) $user['password_hash'])) {
    $result = auth_login_register_failure($normalizedUsernameForRateLimit);
    log_activity($pdo, 'auth.login_failed', 'Login failed: invalid username or password.', [
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
    redirect('login.php');
}

if (is_owner($user)) {
    auth_login_register_failure($normalizedUsernameForRateLimit);
    log_activity($pdo, 'auth.login_blocked_owner_path', 'SaaS admin login blocked on business login page.', [
        'username' => $username,
        'user_id' => (int) ($user['id'] ?? 0),
    ]);
    set_flash('error', 'Invalid username or password.');
    redirect('login.php');
}

if ((string) ($user['status'] ?? 'active') !== 'active') {
    log_activity($pdo, 'auth.login_blocked_inactive', 'Login blocked: inactive user.', [
        'username' => $username,
        'user_id' => (int) ($user['id'] ?? 0),
    ]);
    set_flash('error', 'Your account is inactive. Please contact owner.');
    redirect('login.php');
}

if (!tenant_status_allows_access((string) ($user['tenant_status'] ?? ''))) {
    log_activity($pdo, 'auth.login_blocked_tenant', 'Login blocked: business is not approved.', [
        'username' => $username,
        'user_id' => (int) ($user['id'] ?? 0),
        'tenant_id' => (int) ($user['tenant_id'] ?? 0),
        'tenant_status' => (string) ($user['tenant_status'] ?? ''),
    ], (int) ($user['id'] ?? 0));
    set_flash('error', tenant_blocked_login_message((string) ($user['tenant_status'] ?? '')));
    redirect('login.php');
}

auth_login_clear_failures($normalizedUsernameForRateLimit);
login_user($user);
if ($stayLoggedIn) {
    remember_store_login($pdo, (int) $user['id']);
} else {
    remember_forget_current($pdo);
}
log_activity($pdo, 'auth.login', 'User logged in.', [
    'user_id' => (int) $user['id'],
    'username' => (string) $user['username'],
    'role' => role_display_name((string) $user['role']),
], (int) $user['id']);
set_flash('success', 'Login successful.');
redirect(authenticated_landing_path($user));
