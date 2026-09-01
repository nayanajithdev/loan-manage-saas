<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('register.php');
}
require_csrf('register.php');

$name = trim((string) ($_POST['name'] ?? ''));
$slug = trim((string) ($_POST['slug'] ?? ''));
$ownerName = trim((string) ($_POST['owner_name'] ?? ''));
$ownerEmail = mb_strtolower(trim((string) ($_POST['owner_email'] ?? '')));
$phone = trim((string) ($_POST['phone'] ?? ''));
$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$confirmPassword = (string) ($_POST['confirm_password'] ?? '');
$notes = trim((string) ($_POST['notes'] ?? ''));

if ($name === '' || $ownerName === '' || $ownerEmail === '' || $username === '' || $password === '') {
    set_flash('error', 'Business name, owner details, username, and password are required.');
    redirect('register.php');
}

if (!filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
    set_flash('error', 'Please enter a valid owner email.');
    redirect('register.php');
}

if ($password !== $confirmPassword) {
    set_flash('error', 'Passwords do not match.');
    redirect('register.php');
}

if (strlen($password) < 6) {
    set_flash('error', 'Password must be at least 6 characters.');
    redirect('register.php');
}

$slug = $slug !== '' ? tenant_slug_from_name($slug) : tenant_slug_from_name($name);

$existsStmt = $pdo->prepare('SELECT id FROM tenants WHERE slug = :slug LIMIT 1');
$existsStmt->execute(['slug' => $slug]);
if ($existsStmt->fetch()) {
    set_flash('error', 'Tenant slug already exists.');
    redirect('register.php');
}

$userStmt = $pdo->prepare('SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1');
$userStmt->execute([
    'username' => $username,
    'email' => $ownerEmail,
]);
if ($userStmt->fetch()) {
    set_flash('error', 'Owner username or owner email already exists.');
    redirect('register.php');
}

$pdo->beginTransaction();
try {
    $tenantStmt = $pdo->prepare(
        'INSERT INTO tenants (name, slug, owner_name, owner_email, phone, status, notes)
         VALUES (:name, :slug, :owner_name, :owner_email, :phone, :status, :notes)'
    );
    $tenantStmt->execute([
        'name' => $name,
        'slug' => $slug,
        'owner_name' => $ownerName,
        'owner_email' => $ownerEmail,
        'phone' => $phone !== '' ? $phone : null,
        'status' => 'pending',
        'notes' => $notes !== '' ? $notes : null,
    ]);
    $tenantId = (int) $pdo->lastInsertId();

    $insertUser = $pdo->prepare(
        'INSERT INTO users (tenant_id, full_name, username, email, password_hash, role, status)
         VALUES (:tenant_id, :full_name, :username, :email, :password_hash, :role, :status)'
    );
    $insertUser->execute([
        'tenant_id' => $tenantId,
        'full_name' => $ownerName,
        'username' => $username,
        'email' => $ownerEmail,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => 'owner',
        'status' => 'active',
    ]);
    $adminUserId = (int) $pdo->lastInsertId();
    sync_user_permissions($pdo, $adminUserId, role_default_permissions('owner'));

    $settingsStmt = $pdo->prepare(
        'INSERT INTO system_settings (tenant_id, setting_key, setting_value, updated_by_user_id)
         VALUES (:tenant_id, :setting_key, :setting_value, NULL)'
    );
    foreach ([
        'business_name' => $name,
        'business_note' => 'Loan management workspace',
    ] as $settingKey => $settingValue) {
        $settingsStmt->execute([
            'tenant_id' => $tenantId,
            'setting_key' => $settingKey,
            'setting_value' => $settingValue,
        ]);
    }

    $pdo->commit();
} catch (Throwable) {
    $pdo->rollBack();
    set_flash('error', 'Failed to submit tenant registration.');
    redirect('register.php');
}

log_activity($pdo, 'tenant.registered', 'Tenant registration submitted: ' . $name . '.', [
    'tenant_id' => $tenantId,
    'tenant_slug' => $slug,
]);

set_flash('success', 'Registration submitted. You can login after the SaaS Admin approves your tenant.');
redirect('login.php');
