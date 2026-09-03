<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => app_cookie_path(),
        'secure' => app_cookie_secure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

enforce_https_if_required();

try {
    $pdo = db();
    sync_mysql_session_timezone($pdo, date_default_timezone_get());
    ensure_multi_tenant_schema($pdo);
    ensure_user_schema($pdo);
    ensure_user_permissions_schema($pdo);
    ensure_one_owner_per_tenant_schema($pdo);
    ensure_user_email_schema($pdo);
    ensure_user_profile_schema($pdo);
    ensure_user_status_schema($pdo);
    ensure_loan_status_schema($pdo);
    ensure_user_force_logout_schema($pdo);
    ensure_user_theme_schema($pdo);
    ensure_password_reset_tokens_schema($pdo);
    ensure_remember_tokens_schema($pdo);
    ensure_collection_user_schema($pdo);
    ensure_collection_payment_ref_schema($pdo);
    ensure_flexible_collection_schema($pdo);
    repair_loan_installment_counts_from_history($pdo);
    ensure_routes_schema($pdo);
    ensure_loan_assignment_schema($pdo);
    ensure_loan_issued_date_schema($pdo);
    ensure_loan_end_date_schema($pdo);
    ensure_loan_interest_rate_type_schema($pdo);
    ensure_loan_interest_rate_months_schema($pdo);
    ensure_customer_documents_schema($pdo);
    ensure_customer_docs_guard_file(customer_documents_upload_dir_abs());
    ensure_public_upload_guard_file(profile_avatar_upload_dir_abs());
    ensure_public_upload_guard_file(business_icon_upload_dir_abs());
    ensure_customer_note_schema($pdo);
    ensure_system_settings_schema($pdo);
    ensure_holidays_schema($pdo);
    ensure_activity_logs_schema($pdo);
} catch (PDOException $e) {
    render_database_unavailable_page();
}

$configuredTimezone = trim(system_setting($pdo, 'timezone', ''));
if ($configuredTimezone !== '' && in_array($configuredTimezone, timezone_identifiers_list(), true)) {
    date_default_timezone_set($configuredTimezone);
    sync_mysql_session_timezone($pdo, $configuredTimezone);
}
remember_login_from_cookie($pdo);
$flash = get_flash();

$scriptBaseName = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$publicScripts = [
    'login.php',
    'register.php',
    'f3fd7t3.php',
    'forgot_password.php',
    'reset_password.php',
    'auth_login.php',
    'auth_owner_login.php',
    'tenant_register.php',

    'auth_forgot_password.php',
    'auth_reset_password.php',
    'auth_setup_superadmin.php',
];

if (!in_array($scriptBaseName, $publicScripts, true)) {
    if (!is_logged_in()) {
        redirect('login.php');
    }

    $current = current_user();
    $refreshStmt = $pdo->prepare(
        "SELECT u.id, u.tenant_id, u.full_name, u.username, u.email, u.role, u.status, u.avatar_path, u.theme_preference, u.force_logout_at,
                t.name AS tenant_name, t.status AS tenant_status
         FROM users u
         LEFT JOIN tenants t ON t.id = u.tenant_id
         WHERE u.id = :id
         LIMIT 1"
    );
    $refreshStmt->execute(['id' => (int) $current['id']]);
    $latestUser = $refreshStmt->fetch();

    if (!$latestUser) {
        remember_forget_user($pdo, (int) $current['id']);
        logout_user();
        set_flash('error', 'Your account was removed. Please login again.');
        redirect('login.php');
    }

    if ((string) ($latestUser['status'] ?? 'active') !== 'active') {
        remember_forget_user($pdo, (int) $latestUser['id']);
        logout_user();
        set_flash('error', 'Your account is inactive. Please contact owner.');
        redirect('login.php');
    }

    if (!is_owner($latestUser) && !tenant_status_allows_access((string) ($latestUser['tenant_status'] ?? ''))) {
        remember_forget_user($pdo, (int) $latestUser['id']);
        logout_user();
        set_flash('error', tenant_blocked_login_message((string) ($latestUser['tenant_status'] ?? '')));
        redirect('login.php');
    }

    $forceLogoutAt = strtotime((string) ($latestUser['force_logout_at'] ?? ''));
    $sessionLoginAt = (int) ($_SESSION['auth_login_at'] ?? 0);
    if ($forceLogoutAt !== false && $forceLogoutAt > 0 && $sessionLoginAt < $forceLogoutAt) {
        remember_forget_user($pdo, (int) $latestUser['id']);
        logout_user();
        set_flash('error', 'Please login again.');
        redirect('login.php');
    }

    $_SESSION['auth_user'] = [
        'id' => (int) $latestUser['id'],
        'tenant_id' => isset($latestUser['tenant_id']) ? (int) $latestUser['tenant_id'] : null,
        'tenant_name' => (string) ($latestUser['tenant_name'] ?? ''),
        'tenant_status' => (string) ($latestUser['tenant_status'] ?? ''),
        'full_name' => (string) $latestUser['full_name'],
        'username' => (string) $latestUser['username'],
        'email' => (string) ($latestUser['email'] ?? ''),
        'role' => (string) $latestUser['role'],
        'status' => (string) ($latestUser['status'] ?? 'active'),
        'avatar_path' => (string) ($latestUser['avatar_path'] ?? ''),
        'theme_preference' => normalize_theme_preference((string) ($latestUser['theme_preference'] ?? 'dark')),
    ];
}
