<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_platform_owner('pages/tenants.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('pages/tenant_create.php');
}
require_csrf('pages/tenant_create.php');

$name = trim((string) ($_POST['name'] ?? ''));
$slug = trim((string) ($_POST['slug'] ?? ''));
$ownerName = trim((string) ($_POST['owner_name'] ?? ''));
$ownerEmail = mb_strtolower(trim((string) ($_POST['owner_email'] ?? '')));
$phone = trim((string) ($_POST['phone'] ?? ''));
$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$status = trim((string) ($_POST['status'] ?? 'pending'));
$notes = trim((string) ($_POST['notes'] ?? ''));
$rememberTenantCreateInput = static function () use (&$name, &$slug, &$ownerName, &$ownerEmail, &$phone, &$username, &$status, &$notes): void {
    $_SESSION['tenant_create_old_input'] = [
        'name' => $name,
        'slug' => $slug,
        'owner_name' => $ownerName,
        'owner_email' => $ownerEmail,
        'phone' => $phone,
        'username' => $username,
        'status' => $status,
        'notes' => $notes,
    ];
};

if ($name === '' || $ownerName === '' || $ownerEmail === '' || $username === '' || $password === '') {
    $rememberTenantCreateInput();
    set_flash('error', 'Business name, owner details, username, and password are required.');
    redirect('pages/tenant_create.php');
}

if (!filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
    $rememberTenantCreateInput();
    set_flash('error', 'Please enter a valid owner email.');
    redirect('pages/tenant_create.php');
}

if (!password_meets_policy($password)) {
    $rememberTenantCreateInput();
    set_flash('error', password_policy_message());
    redirect('pages/tenant_create.php');
}

if (!in_array($status, ['pending', 'approved'], true)) {
    $status = 'pending';
}

$slug = $slug !== '' ? tenant_slug_from_name($slug) : tenant_slug_from_name($name);

$existsStmt = $pdo->prepare('SELECT id FROM tenants WHERE slug = :slug LIMIT 1');
$existsStmt->execute(['slug' => $slug]);
if ($existsStmt->fetch()) {
    $rememberTenantCreateInput();
    set_flash('error', 'Business slug already exists.');
    redirect('pages/tenant_create.php');
}

$usernameStmt = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
$usernameStmt->execute(['username' => $username]);
if ($usernameStmt->fetch()) {
    $rememberTenantCreateInput();
    set_flash('error', 'Username already exists.');
    redirect('pages/tenant_create.php');
}

$emailStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
$emailStmt->execute(['email' => $ownerEmail]);
if ($emailStmt->fetch()) {
    $rememberTenantCreateInput();
    set_flash('error', 'Email already exists.');
    redirect('pages/tenant_create.php');
}

$pdo->beginTransaction();
try {
    $tenantStmt = $pdo->prepare(
        'INSERT INTO tenants (name, slug, owner_name, owner_email, phone, status, approved_at, notes)
         VALUES (:name, :slug, :owner_name, :owner_email, :phone, :status, :approved_at, :notes)'
    );
    $tenantStmt->execute([
        'name' => $name,
        'slug' => $slug,
        'owner_name' => $ownerName,
        'owner_email' => $ownerEmail,
        'phone' => $phone !== '' ? $phone : null,
        'status' => $status,
        'approved_at' => $status === 'approved' ? date('Y-m-d H:i:s') : null,
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
         VALUES (:tenant_id, :setting_key, :setting_value, :updated_by_user_id)'
    );
    foreach ([
        'business_name' => $name,
        'business_note' => 'Loan management workspace',
    ] as $settingKey => $settingValue) {
        $settingsStmt->execute([
            'tenant_id' => $tenantId,
            'setting_key' => $settingKey,
            'setting_value' => $settingValue,
            'updated_by_user_id' => (int) (current_user()['id'] ?? 0),
        ]);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    $rememberTenantCreateInput();
    set_flash('error', 'Failed to create business. Please try again.');
    redirect('pages/tenant_create.php');
}

log_activity($pdo, 'tenant.created', 'Business created: ' . $name . '.', [
    'tenant_id' => $tenantId,
    'tenant_slug' => $slug,
    'status' => $status,
]);

unset($_SESSION['tenant_create_old_input']);
set_flash('success', 'Business created successfully.');
redirect('pages/tenants.php');
