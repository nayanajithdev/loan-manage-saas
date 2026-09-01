<?php

declare(strict_types=1);

function url(string $path = ''): string
{
    $path = ltrim($path, '/');

    if (BASE_PATH === '/') {
        return '/' . $path;
    }

    return rtrim(BASE_PATH, '/') . '/' . $path;
}

function owner_secret_login_path(): string
{
    return OWNER_SECRET_PATH;
}

function request_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    $forwardedProto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    if ($forwardedProto === 'https') {
        return true;
    }

    return (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
}

function enforce_https_if_required(): void
{
    if (!FORCE_HTTPS || PHP_SAPI === 'cli' || request_is_https()) {
        return;
    }

    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '') {
        return;
    }

    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    header('Location: https://' . $host . $requestUri, true, 301);
    exit;
}

function current_request_app_path(): string
{
    $requestPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $requestPath = trim(str_replace('\\', '/', $requestPath), '/');
    $basePath = trim(str_replace('\\', '/', (string) BASE_PATH), '/');

    if ($basePath !== '' && str_starts_with($requestPath, $basePath . '/')) {
        $requestPath = substr($requestPath, strlen($basePath) + 1);
    } elseif ($basePath !== '' && $requestPath === $basePath) {
        $requestPath = '';
    }

    return trim($requestPath, '/');
}

function absolute_url(string $path = ''): string
{
    $configured = trim(env_value('APP_URL', ''));
    if ($configured !== '') {
        return rtrim($configured, '/') . '/' . ltrim($path, '/');
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $base = BASE_PATH === '/' ? '' : BASE_PATH;

    return $scheme . '://' . $host . $base . '/' . ltrim($path, '/');
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function role_display_name(string $role): string
{
    return match ($role) {
        'superadmin' => 'SaaS Admin',
        'owner' => 'Owner',
        'manager' => 'Manager',
        'admin' => 'Manager',
        'collector' => 'Collector',
        default => ucfirst($role),
    };
}

function user_role_display_name(array $user, ?PDO $pdo = null): string
{
    if ((string) ($user['role'] ?? '') === 'owner'
        || ((string) ($user['role'] ?? '') === 'admin' && is_tenant_owner($user, $pdo))
    ) {
        return 'Owner';
    }

    return role_display_name((string) ($user['role'] ?? ''));
}
function permission_groups(): array
{
    return [
        'Core Access' => [
            'description' => 'Required starting point and profile access.',
            'permissions' => [
                'dashboard.view' => ['label' => 'Dashboard', 'description' => 'View overview cards, goals, and recent activity.'],
                'profile.manage' => ['label' => 'Profile', 'description' => 'Update own username, avatar, and password.'],
            ],
        ],
        'Collections' => [
            'description' => 'Due collections, payment entry, and collection history.',
            'permissions' => [
                'today_collections.view' => ['label' => 'Today Collection', 'description' => 'View due installments and collection screen.'],
                'collections.record' => ['label' => 'Record Collection', 'description' => 'Save installment payments.'],
                'collections.undo' => ['label' => 'Undo Collection', 'description' => 'Reverse the latest saved collection using stored installment snapshots.'],
                'collections.backdate' => ['label' => 'Backdated Entry', 'description' => 'Record a payment as paid on an earlier date.'],
                'collections.schedule' => ['label' => 'Schedule Next Payment', 'description' => 'Move a customer next payment date without marking bad quality.'],
                'collections.history' => ['label' => 'Collection History', 'description' => 'View saved collection records.'],
            ],
        ],
        'Customers' => [
            'description' => 'Customer records and documents.',
            'permissions' => [
                'customers.view' => ['label' => 'View Customers', 'description' => 'Open customer list and customer details.'],
                'customers.create' => ['label' => 'Create Customers', 'description' => 'Add new customer records.'],
                'customers.edit' => ['label' => 'Edit Customers', 'description' => 'Update existing customer records.'],
                'customers.delete' => ['label' => 'Delete Customers', 'description' => 'Delete customers with no linked loans.'],
                'customers.documents' => ['label' => 'Customer Documents', 'description' => 'Upload, view, and download customer documents.'],
            ],
        ],
        'Loans' => [
            'description' => 'Loan records and repayment schedules.',
            'permissions' => [
                'loans.view' => ['label' => 'View Loans', 'description' => 'Open loan list and loan details.'],
                'loans.create' => ['label' => 'Create Loans', 'description' => 'Create loan records and repayment schedules.'],
                'loans.edit' => ['label' => 'Edit Loans', 'description' => 'Update loan notes, status, and allowed editable fields.'],
                'loans.extend' => ['label' => 'Extend Loans', 'description' => 'Extend active loans with extra amount or end date changes.'],
                'loans.delete' => ['label' => 'Delete Loans', 'description' => 'Delete loans and their schedules.'],
                'routes.view' => ['label' => 'View Routes', 'description' => 'Open route list and route loan counts.'],
                'routes.create' => ['label' => 'Create Routes', 'description' => 'Create collection routes.'],
                'routes.edit' => ['label' => 'Rename Routes', 'description' => 'Rename existing routes.'],
                'routes.delete' => ['label' => 'Delete Routes', 'description' => 'Delete routes that do not have assigned loans.'],
                'collections.loan_records' => ['label' => 'Loan Collection Records', 'description' => 'View the Collection Records tab inside loan details.'],
                'collections.loan_records_edit' => ['label' => 'Edit Collection Records', 'description' => 'Edit saved collection records inside loan details.'],
                'collections.loan_records_delete' => ['label' => 'Delete Collection Records', 'description' => 'Delete saved collection records inside loan details.'],
            ],
        ],
        'Management' => [
            'description' => 'Users, reports, audit logs, and settings.',
            'permissions' => [
                'users.manage' => ['label' => 'Users', 'description' => 'Create, edit, deactivate, and delete users.'],
                'reports.view' => ['label' => 'Reports', 'description' => 'View business and collection reports.'],
                'holidays.manage' => ['label' => 'Holiday Mode', 'description' => 'Mark holidays and shift unpaid collection schedules.'],
                'activity_logs.view' => ['label' => 'Activity Logs', 'description' => 'Review audit history and user actions.'],
                'business_settings.manage' => ['label' => 'Business Settings', 'description' => 'Update business profile and business icon.'],
                'system_settings.view' => ['label' => 'View System Settings', 'description' => 'View system defaults and configuration.'],
                'system_settings.manage' => ['label' => 'Edit System Settings', 'description' => 'Change system defaults and configuration.'],
            ],
        ],
    ];
}

function permission_keys(): array
{
    $keys = [];
    foreach (permission_groups() as $group) {
        foreach ((array) ($group['permissions'] ?? []) as $key => $_meta) {
            $keys[] = (string) $key;
        }
    }

    return $keys;
}

function permission_dependencies(): array
{
    return [
        'collections.record' => ['today_collections.view'],
        'collections.undo' => ['today_collections.view', 'collections.record'],
        'collections.backdate' => ['today_collections.view', 'collections.record'],
        'collections.schedule' => ['today_collections.view'],
        'collections.loan_records' => ['loans.view'],
        'customers.create' => ['customers.view'],
        'customers.edit' => ['customers.view'],
        'customers.delete' => ['customers.view'],
        'customers.documents' => ['customers.view'],
        'loans.create' => ['loans.view'],
        'loans.edit' => ['loans.view'],
        'loans.extend' => ['loans.view'],
        'loans.delete' => ['loans.view'],
        'routes.create' => ['routes.view'],
        'routes.edit' => ['routes.view'],
        'routes.delete' => ['routes.view'],
        'collections.loan_records_edit' => ['collections.loan_records'],
        'collections.loan_records_delete' => ['collections.loan_records_edit'],
        'system_settings.manage' => ['system_settings.view'],
    ];
}

function normalize_permission_keys(array $permissionKeys): array
{
    $validKeys = permission_keys();
    $selected = array_flip(array_intersect(array_unique(array_map('strval', $permissionKeys)), $validKeys));

    do {
        $changed = false;
        foreach (permission_dependencies() as $permissionKey => $requiredKeys) {
            if (!isset($selected[$permissionKey])) {
                continue;
            }

            foreach ((array) $requiredKeys as $requiredKey) {
                if (!isset($selected[(string) $requiredKey])) {
                    unset($selected[$permissionKey]);
                    $changed = true;
                    break;
                }
            }
        }
    } while ($changed);

    $normalized = [];
    foreach ($validKeys as $validKey) {
        if (isset($selected[$validKey])) {
            $normalized[] = $validKey;
        }
    }

    return $normalized;
}

function role_default_permissions(string $role): array
{
    $role = (string) $role;
    $all = permission_keys();

    if ($role === 'superadmin') {
        return $all;
    }

    if ($role === 'owner') {
        return $all;
    }

    if ($role === 'manager' || $role === 'admin') {
        return array_values(array_diff($all, [
            'activity_logs.view',
            'system_settings.manage',
        ]));
    }

    return [
        'dashboard.view',
        'profile.manage',
        'today_collections.view',
        'collections.record',
        'collections.history',
        'collections.loan_records',
        'customers.view',
    ];
}
function collection_note_split(?string $rawNote): array
{
    $note = trim((string) $rawNote);
    if ($note === '') {
        return ['public' => '', 'meta' => null];
    }

    $meta = null;
    if (preg_match('/\[SYSMETA:([A-Za-z0-9+\/=]+)\]\s*$/', $note, $m) === 1) {
        $decoded = base64_decode($m[1], true);
        if ($decoded !== false) {
            $parsed = json_decode($decoded, true);
            if (is_array($parsed)) {
                $meta = $parsed;
            }
        }
        $note = trim((string) preg_replace('/\s*\[SYSMETA:[A-Za-z0-9+\/=]+\]\s*$/', '', $note));
    }

    return ['public' => $note, 'meta' => $meta];
}

function money(float $amount, int $decimals = 2): string
{
    $decimals = $decimals > 0 ? 2 : 0;
    return number_format($amount, $decimals);
}

function money_display_decimals(PDO $pdo): int
{
    return system_setting($pdo, 'display_cents_enabled', '1') === '0' ? 0 : 2;
}

function currency_label(PDO $pdo): string
{
    static $cached = null;

    if ($cached !== null) {
        return $cached;
    }

    $label = trim(system_setting($pdo, 'currency_label', 'LKR'));
    $cached = $label !== '' ? $label : 'LKR';

    return $cached;
}

function money_label(PDO $pdo, float $amount): string
{
    return currency_label($pdo) . ' ' . money($amount, money_display_decimals($pdo));
}

function timezone_offset_for_mysql(string $timezoneIdentifier): string
{
    try {
        $timezone = new DateTimeZone($timezoneIdentifier);
    } catch (Throwable) {
        $timezone = new DateTimeZone('UTC');
    }

    $now = new DateTimeImmutable('now', $timezone);
    $offsetSeconds = $timezone->getOffset($now);
    $sign = $offsetSeconds >= 0 ? '+' : '-';
    $abs = abs($offsetSeconds);
    $hours = intdiv($abs, 3600);
    $minutes = intdiv($abs % 3600, 60);

    return sprintf('%s%02d:%02d', $sign, $hours, $minutes);
}

function sync_mysql_session_timezone(PDO $pdo, ?string $timezoneIdentifier = null): void
{
    $timezoneIdentifier = trim((string) $timezoneIdentifier);
    if ($timezoneIdentifier === '') {
        $timezoneIdentifier = date_default_timezone_get();
    }

    $offset = timezone_offset_for_mysql($timezoneIdentifier);

    try {
        $stmt = $pdo->prepare('SET time_zone = :tz');
        $stmt->execute(['tz' => $offset]);
    } catch (Throwable) {
        // Some hosting environments may block session timezone changes.
        // Keep app running and fallback to server timezone behavior.
    }
}

function poll_interval_ms(PDO $pdo): int
{
    $seconds = (int) system_setting($pdo, 'poll_interval_seconds', '10');
    return max(3000, min(60000, $seconds * 1000));
}

function payment_method_selection_enabled(PDO $pdo): bool
{
    return system_setting($pdo, 'payment_method_selection_enabled', '1') !== '0';
}

function display_date(string $dateValue, ?string $fallback = null): string
{
    static $format = null;
    if ($format === null) {
        $saved = trim(system_setting(db(), 'date_format', 'd/m/Y'));
        $format = $saved !== '' ? $saved : 'd/m/Y';
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $dateValue);
    if (!$date) {
        return $fallback ?? $dateValue;
    }

    return $date->format($format);
}

function display_datetime(string $dateTimeValue, ?string $fallback = null): string
{
    static $dateTimeFormat = null;
    if ($dateTimeFormat === null) {
        $saved = trim(system_setting(db(), 'date_format', 'd/m/Y'));
        $timeFormat = system_setting(db(), 'display_time_seconds_enabled', '1') === '0' ? 'H:i' : 'H:i:s';
        $dateTimeFormat = ($saved !== '' ? $saved : 'd/m/Y') . ' ' . $timeFormat;
    }

    try {
        $date = new DateTimeImmutable($dateTimeValue);
    } catch (Throwable) {
        return $fallback ?? $dateTimeValue;
    }

    return $date->format($dateTimeFormat);
}

function display_time(string $dateTimeValue, ?string $fallback = null): string
{
    static $timeFormat = null;
    if ($timeFormat === null) {
        $timeFormat = system_setting(db(), 'display_time_seconds_enabled', '1') === '0' ? 'H:i' : 'H:i:s';
    }

    try {
        $date = new DateTimeImmutable($dateTimeValue);
    } catch (Throwable) {
        return $fallback ?? $dateTimeValue;
    }

    return $date->format($timeFormat);
}

function today(): string
{
    $fakeToday = null;
    if (defined('LOCAL_APP_CONFIG')) {
        $localConfig = LOCAL_APP_CONFIG;
        if (is_array($localConfig)) {
            $rawFakeToday = trim((string) ($localConfig['fake_today'] ?? ''));
            if ($rawFakeToday !== '') {
                $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $rawFakeToday);
                if ($parsed && $parsed->format('Y-m-d') === $rawFakeToday) {
                    $fakeToday = $rawFakeToday;
                }
            }
        }
    }

    if ($fakeToday !== null) {
        return $fakeToday;
    }

    return date('Y-m-d');
}

function csrf_token(): string
{
    if (!isset($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token']) || $_SESSION['_csrf_token'] === '') {
        try {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        } catch (Throwable) {
            $_SESSION['_csrf_token'] = str_replace('.', '', uniqid('csrf_', true));
        }
    }

    return (string) $_SESSION['_csrf_token'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_is_valid(?string $submittedToken): bool
{
    if (!isset($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
        return false;
    }
    if ($submittedToken === null || $submittedToken === '') {
        return false;
    }

    return hash_equals((string) $_SESSION['_csrf_token'], $submittedToken);
}

function require_csrf(string $redirectPath = 'index.php'): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $token = (string) ($_POST['_csrf'] ?? '');
    if (!csrf_is_valid($token)) {
        set_flash('error', 'Invalid request token. Please try again.');
        redirect($redirectPath);
    }
}

function app_cookie_path(): string
{
    return '/';
}

function app_cookie_secure(): bool
{
    return FORCE_HTTPS || request_is_https();
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function client_ip_address(): string
{
    $candidates = [
        (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''),
        (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''),
        (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
    ];

    foreach ($candidates as $candidate) {
        if ($candidate === '') {
            continue;
        }

        $parts = explode(',', $candidate);
        foreach ($parts as $part) {
            $ip = trim($part);
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return '';
}

function ensure_private_guard_file(string $directoryAbs): void
{
    if (!is_dir($directoryAbs)) {
        return;
    }

    $guardPath = rtrim($directoryAbs, '/\\') . DIRECTORY_SEPARATOR . '.htaccess';
    if (is_file($guardPath)) {
        return;
    }

    $guardContents = "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n";
    @file_put_contents($guardPath, $guardContents);
}

function rate_limit_storage_dir_abs(): string
{
    return __DIR__ . '/../storage/rate_limits';
}

function rate_limit_bucket_file_abs(string $bucket): string
{
    $bucket = strtolower(trim($bucket));
    $bucket = preg_replace('/[^a-z0-9_-]/', '_', $bucket) ?? 'default';
    if ($bucket === '') {
        $bucket = 'default';
    }

    return rate_limit_storage_dir_abs() . '/' . $bucket . '.json';
}

function rate_limit_read_bucket(string $bucket): array
{
    $dir = rate_limit_storage_dir_abs();
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $rootDir = dirname($dir);
    ensure_private_guard_file($rootDir);
    ensure_private_guard_file($dir);

    $file = rate_limit_bucket_file_abs($bucket);
    if (!is_file($file)) {
        return [];
    }

    $raw = @file_get_contents($file);
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function rate_limit_write_bucket(string $bucket, array $data): void
{
    $dir = rate_limit_storage_dir_abs();
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $rootDir = dirname($dir);
    ensure_private_guard_file($rootDir);
    ensure_private_guard_file($dir);

    $file = rate_limit_bucket_file_abs($bucket);
    $json = json_encode($data, JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        return;
    }

    @file_put_contents($file, $json, LOCK_EX);
}

function rate_limit_consume(string $bucket, string $key, int $maxHits, int $windowSeconds): array
{
    $maxHits = max(1, $maxHits);
    $windowSeconds = max(30, $windowSeconds);
    $now = time();
    $windowStart = $now - $windowSeconds;

    $state = rate_limit_read_bucket($bucket);
    $cleaned = [];
    foreach ($state as $hash => $entry) {
        if (!is_array($entry) || !isset($entry['hits']) || !is_array($entry['hits'])) {
            continue;
        }

        $hits = [];
        foreach ($entry['hits'] as $hit) {
            $hitTs = (int) $hit;
            if ($hitTs > $windowStart && $hitTs <= $now + 1) {
                $hits[] = $hitTs;
            }
        }

        if ($hits !== []) {
            $cleaned[$hash] = ['hits' => $hits];
        }
    }

    $keyHash = hash('sha256', strtolower(trim($key)));
    if ($keyHash === '') {
        $keyHash = hash('sha256', 'anonymous');
    }

    $hits = $cleaned[$keyHash]['hits'] ?? [];
    $hitCount = count($hits);
    if ($hitCount >= $maxHits) {
        $oldest = (int) min($hits);
        $retryAfter = max(1, $windowSeconds - max(0, $now - $oldest));
        rate_limit_write_bucket($bucket, $cleaned);
        return [
            'allowed' => false,
            'retry_after' => $retryAfter,
            'remaining' => 0,
        ];
    }

    $hits[] = $now;
    $cleaned[$keyHash] = ['hits' => $hits];
    rate_limit_write_bucket($bucket, $cleaned);

    return [
        'allowed' => true,
        'retry_after' => 0,
        'remaining' => max(0, $maxHits - count($hits)),
    ];
}

function auth_login_limit_config(): array
{
    $local = defined('LOCAL_APP_CONFIG') && is_array(LOCAL_APP_CONFIG) ? LOCAL_APP_CONFIG : [];

    $maxAttempts = (int) ($local['auth_login_max_attempts'] ?? 5);
    $windowSeconds = (int) ($local['auth_login_window_seconds'] ?? 900);
    $lockSeconds = (int) ($local['auth_login_lock_seconds'] ?? 900);

    return [
        'max_attempts' => max(3, min(20, $maxAttempts)),
        'window_seconds' => max(60, min(86400, $windowSeconds)),
        'lock_seconds' => max(60, min(86400, $lockSeconds)),
    ];
}

function auth_login_limit_key(string $username, bool $ipOnly = false): string
{
    $ip = client_ip_address();
    if ($ip === '') {
        $ip = 'unknown';
    }

    if ($ipOnly) {
        return hash('sha256', 'ip|' . strtolower($ip));
    }

    $user = strtolower(trim($username));
    if ($user === '') {
        $user = '(blank)';
    }

    return hash('sha256', 'user|' . $user . '|' . strtolower($ip));
}

function auth_login_limit_prune_state(array $state, int $windowSeconds, int $lockSeconds): array
{
    $now = time();
    $keepAfter = $now - (max($windowSeconds, $lockSeconds) * 2);
    $cleaned = [];

    foreach ($state as $key => $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $failCount = (int) ($entry['fail_count'] ?? 0);
        $firstFailed = (int) ($entry['first_failed'] ?? 0);
        $lastFailed = (int) ($entry['last_failed'] ?? 0);
        $lockUntil = (int) ($entry['lock_until'] ?? 0);

        if ($lockUntil > $now || $lastFailed >= $keepAfter || ($failCount > 0 && $firstFailed >= $keepAfter)) {
            $cleaned[$key] = [
                'fail_count' => max(0, $failCount),
                'first_failed' => max(0, $firstFailed),
                'last_failed' => max(0, $lastFailed),
                'lock_until' => max(0, $lockUntil),
            ];
        }
    }

    return $cleaned;
}

function auth_login_limit_remaining(array $entry, array $config): int
{
    $now = time();
    $failCount = (int) ($entry['fail_count'] ?? 0);
    $firstFailed = (int) ($entry['first_failed'] ?? 0);

    if ($firstFailed <= 0 || ($now - $firstFailed) > (int) $config['window_seconds']) {
        $failCount = 0;
    }

    return max(0, (int) $config['max_attempts'] - $failCount);
}

function auth_login_lock_status(string $username): array
{
    $config = auth_login_limit_config();
    $state = rate_limit_read_bucket('auth_login_lock');
    $pruned = auth_login_limit_prune_state($state, (int) $config['window_seconds'], (int) $config['lock_seconds']);
    if ($pruned !== $state) {
        rate_limit_write_bucket('auth_login_lock', $pruned);
    }

    $now = time();
    $userKey = auth_login_limit_key($username, false);
    $ipKey = auth_login_limit_key($username, true);

    $userEntry = is_array($pruned[$userKey] ?? null) ? $pruned[$userKey] : [];
    $ipEntry = is_array($pruned[$ipKey] ?? null) ? $pruned[$ipKey] : [];

    $userLockUntil = (int) ($userEntry['lock_until'] ?? 0);
    $ipLockUntil = (int) ($ipEntry['lock_until'] ?? 0);
    $lockUntil = max($userLockUntil, $ipLockUntil);

    $userRemaining = auth_login_limit_remaining($userEntry, $config);
    $ipRemaining = auth_login_limit_remaining($ipEntry, $config);

    if ($lockUntil > $now) {
        return [
            'locked' => true,
            'retry_after' => $lockUntil - $now,
            'remaining_attempts' => 0,
        ];
    }

    return [
        'locked' => false,
        'retry_after' => 0,
        'remaining_attempts' => min($userRemaining, $ipRemaining),
    ];
}

function auth_login_register_failure(string $username): array
{
    $config = auth_login_limit_config();
    $state = rate_limit_read_bucket('auth_login_lock');
    $state = auth_login_limit_prune_state($state, (int) $config['window_seconds'], (int) $config['lock_seconds']);

    $now = time();
    $keys = [
        auth_login_limit_key($username, false),
        auth_login_limit_key($username, true),
    ];

    $locked = false;
    $retryAfter = 0;
    $remainingAttempts = (int) $config['max_attempts'];

    foreach ($keys as $key) {
        $entry = is_array($state[$key] ?? null) ? $state[$key] : [
            'fail_count' => 0,
            'first_failed' => 0,
            'last_failed' => 0,
            'lock_until' => 0,
        ];

        $firstFailed = (int) ($entry['first_failed'] ?? 0);
        if ($firstFailed <= 0 || ($now - $firstFailed) > (int) $config['window_seconds']) {
            $entry['fail_count'] = 0;
            $entry['first_failed'] = $now;
        }

        $entry['fail_count'] = ((int) ($entry['fail_count'] ?? 0)) + 1;
        $entry['last_failed'] = $now;
        $entry['lock_until'] = 0;

        if ((int) $entry['fail_count'] >= (int) $config['max_attempts']) {
            $entry['lock_until'] = $now + (int) $config['lock_seconds'];
            $entry['fail_count'] = 0;
            $entry['first_failed'] = 0;
            $locked = true;
            $retryAfter = max($retryAfter, (int) $config['lock_seconds']);
        } else {
            $remainingAttempts = min(
                $remainingAttempts,
                max(0, (int) $config['max_attempts'] - (int) $entry['fail_count'])
            );
        }

        $state[$key] = $entry;
    }

    rate_limit_write_bucket('auth_login_lock', $state);

    return [
        'locked' => $locked,
        'retry_after' => $retryAfter,
        'remaining_attempts' => $locked ? 0 : $remainingAttempts,
    ];
}

function auth_login_clear_failures(string $username): void
{
    $state = rate_limit_read_bucket('auth_login_lock');
    if (!is_array($state) || $state === []) {
        return;
    }

    $userKey = auth_login_limit_key($username, false);
    $ipKey = auth_login_limit_key($username, true);
    unset($state[$userKey], $state[$ipKey]);
    rate_limit_write_bucket('auth_login_lock', $state);
}

function frequency_interval(string $frequency): DateInterval
{
    return match ($frequency) {
        'daily' => new DateInterval('P1D'),
        'weekly' => new DateInterval('P7D'),
        'monthly' => new DateInterval('P1M'),
        default => new DateInterval('P1D'),
    };
}

function installment_count_from_timeframe(string $frequency, int $timeframeValue, string $timeframeUnit): int
{
    $timeframeValue = max($timeframeValue, 1);
    $timeframeUnit = in_array($timeframeUnit, ['days', 'months'], true) ? $timeframeUnit : 'days';

    $totalDays = $timeframeUnit === 'months' ? $timeframeValue * 30 : $timeframeValue;

    $count = match ($frequency) {
        'daily' => $totalDays,
        'weekly' => (int) ceil($totalDays / 7),
        'monthly' => $timeframeUnit === 'months' ? $timeframeValue : (int) ceil($totalDays / 30),
        default => $totalDays,
    };

    return max($count, 1);
}

function normalize_interest_rate_type(string $value): string
{
    return in_array($value, ['amount_based', 'monthly'], true) ? $value : 'amount_based';
}

function normalize_interest_rate_months(int $months): int
{
    return max($months, 1);
}

function loan_total_amount(
    float $principal,
    float $interestRate,
    string $interestRateType,
    int $interestRateMonths = 1
): float {
    $principal = max($principal, 0);
    $interestRate = max($interestRate, 0);
    $interestRateType = normalize_interest_rate_type($interestRateType);
    $interestRateMonths = normalize_interest_rate_months($interestRateMonths);

    $baseInterest = $principal * ($interestRate / 100);
    $multiplier = $interestRateType === 'monthly'
        ? $interestRateMonths
        : 1.0;

    return round($principal + ($baseInterest * $multiplier), 2);
}

function loan_number_digits(string $loanNumber): string
{
    $loanNumber = trim($loanNumber);
    if ($loanNumber === '') {
        return '';
    }

    if (preg_match('/^\d+$/', $loanNumber) === 1) {
        return $loanNumber;
    }

    if (preg_match('/(\d+)$/', $loanNumber, $matches) === 1) {
        return (string) $matches[1];
    }

    return '';
}

function normalize_loan_number_input(string $loanNumber, int $minimumWidth = 3): string
{
    $loanNumber = trim($loanNumber);
    if (preg_match('/^\d+$/', $loanNumber) !== 1) {
        return '';
    }

    $numericValue = (int) ltrim($loanNumber, '0');
    $width = max($minimumWidth, strlen($loanNumber));

    return str_pad((string) $numericValue, $width, '0', STR_PAD_LEFT);
}

function next_loan_number(PDO $pdo): string
{
    $tenantId = current_tenant_id();
    if ($tenantId !== null) {
        $stmt = $pdo->prepare('SELECT loan_number FROM loans WHERE tenant_id = :tenant_id ORDER BY id DESC');
        $stmt->execute(['tenant_id' => $tenantId]);
    } else {
        $stmt = $pdo->query('SELECT loan_number FROM loans ORDER BY id DESC');
    }
    $maxNumber = 0;
    $width = 3;

    foreach ($stmt->fetchAll() as $row) {
        $existingLoanNumber = (string) ($row['loan_number'] ?? '');
        $digits = loan_number_digits($existingLoanNumber);
        if ($digits === '') {
            continue;
        }

        $number = (int) ltrim($digits, '0');
        if ($number >= $maxNumber) {
            $maxNumber = $number;
            $width = preg_match('/^\d+$/', $existingLoanNumber) === 1
                ? max(3, strlen($digits))
                : 3;
        }
    }

    return str_pad((string) ($maxNumber + 1), $width, '0', STR_PAD_LEFT);
}

function loan_number_exists(PDO $pdo, string $loanNumber): bool
{
    $targetDigits = loan_number_digits($loanNumber);
    if ($targetDigits === '') {
        return false;
    }

    $targetNumber = (int) ltrim($targetDigits, '0');
    $tenantId = current_tenant_id();
    if ($tenantId !== null) {
        $stmt = $pdo->prepare('SELECT loan_number FROM loans WHERE tenant_id = :tenant_id');
        $stmt->execute(['tenant_id' => $tenantId]);
    } else {
        $stmt = $pdo->query('SELECT loan_number FROM loans');
    }

    foreach ($stmt->fetchAll() as $row) {
        $existing = (string) ($row['loan_number'] ?? '');
        if ($existing === $loanNumber) {
            return true;
        }

        $existingDigits = loan_number_digits($existing);
        if ($existingDigits !== '' && (int) ltrim($existingDigits, '0') === $targetNumber) {
            return true;
        }
    }

    return false;
}

function next_customer_code(PDO $pdo): string
{
    $tenantId = current_tenant_id();
    if ($tenantId !== null) {
        $stmt = $pdo->prepare('SELECT id FROM customers WHERE tenant_id = :tenant_id ORDER BY id DESC LIMIT 1');
        $stmt->execute(['tenant_id' => $tenantId]);
    } else {
        $stmt = $pdo->query('SELECT id FROM customers ORDER BY id DESC LIMIT 1');
    }
    $lastId = (int) ($stmt->fetchColumn() ?: 0);

    return 'CUST-' . str_pad((string) ($lastId + 1), 5, '0', STR_PAD_LEFT);
}

function customer_id_no_label(?string $nic): string
{
    $nic = trim((string) $nic);

    return $nic !== '' ? $nic : '-';
}

function customer_display_label(array $customer): string
{
    $name = trim((string) ($customer['full_name'] ?? ''));
    $idNo = customer_id_no_label(isset($customer['nic']) ? (string) $customer['nic'] : '');

    if ($name === '') {
        return $idNo;
    }

    return $idNo !== '-' ? $name . ' - ' . $idNo : $name;
}

function status_badge_class(string $status): string
{
    return match ($status) {
        'active', 'paid', 'approved' => 'success',
        'closed' => 'info',
        'partial', 'pending' => 'warning',
        'overdue', 'inactive', 'rejected', 'suspended', 'deleted' => 'danger',
        default => 'neutral',
    };
}

function table_has_column(PDO $pdo, string $tableName, string $columnName): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = :table_name
           AND column_name = :column_name"
    );
    $stmt->execute([
        'table_name' => $tableName,
        'column_name' => $columnName,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

function table_has_index(PDO $pdo, string $tableName, string $indexName): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.statistics
         WHERE table_schema = DATABASE()
           AND table_name = :table_name
           AND index_name = :index_name"
    );
    $stmt->execute([
        'table_name' => $tableName,
        'index_name' => $indexName,
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

function safe_exec_schema(PDO $pdo, string $sql): void
{
    try {
        $pdo->exec($sql);
    } catch (Throwable $e) {
        error_log('Schema migration skipped: ' . $e->getMessage());
    }
}

function tenant_slug_from_name(string $name): string
{
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');

    return $slug !== '' ? substr($slug, 0, 70) : 'tenant';
}

function current_tenant_id(?array $viewer = null): ?int
{
    $viewer = $viewer ?? current_user();
    $tenantId = (int) ($viewer['tenant_id'] ?? 0);

    return $tenantId > 0 ? $tenantId : null;
}

function tenant_setting_scope_id(?array $viewer = null): int
{
    return current_tenant_id($viewer) ?? 0;
}

function tenant_status_allows_access(string $status): bool
{
    return $status === 'approved';
}

function tenant_blocked_login_message(string $status): string
{
    return match ($status) {
        'pending' => 'Your tenant account is pending approval.',
        'rejected' => 'Your tenant account was rejected. Please contact owner.',
        'suspended' => 'Your tenant account is suspended. Please contact owner.',
        'deleted' => 'Your tenant account is not available.',
        default => 'Your tenant account is not approved yet.',
    };
}

function require_platform_owner(string $redirectPath = 'index.php'): void
{
    if (!is_owner()) {
        set_flash('error', 'Only SaaS Admin can access that page.');
        redirect($redirectPath);
    }
}

function require_tenant_context(string $redirectPath = 'pages/tenants.php'): int
{
    $tenantId = current_tenant_id();
    if ($tenantId === null) {
        set_flash('error', 'Open a tenant account to access tenant operations.');
        redirect($redirectPath);
    }

    return $tenantId;
}

function tenant_scope_sql(string $alias = '', string $paramName = 'tenant_id'): string
{
    $prefix = trim($alias) !== '' ? trim($alias) . '.' : '';

    return $prefix . 'tenant_id = :' . $paramName;
}

function tenant_scope_params(?array $params = null, string $paramName = 'tenant_id', ?array $viewer = null): array
{
    $params = $params ?? [];
    $tenantId = current_tenant_id($viewer);
    if ($tenantId !== null) {
        $params[$paramName] = $tenantId;
    }

    return $params;
}

function tenant_where_clause(string $alias = '', string $paramName = 'tenant_id', ?array $viewer = null): string
{
    return current_tenant_id($viewer) !== null ? ' AND ' . tenant_scope_sql($alias, $paramName) : '';
}

function ensure_multi_tenant_schema(PDO $pdo): void
{
    safe_exec_schema($pdo, "CREATE TABLE IF NOT EXISTS tenants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        slug VARCHAR(80) NOT NULL UNIQUE,
        owner_name VARCHAR(150) NULL,
        owner_email VARCHAR(190) NULL,
        phone VARCHAR(40) NULL,
        status ENUM('pending', 'approved', 'rejected', 'suspended', 'deleted') NOT NULL DEFAULT 'pending',
        approved_at DATETIME NULL,
        suspended_at DATETIME NULL,
        rejected_at DATETIME NULL,
        deleted_at DATETIME NULL,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_tenants_status (status),
        INDEX idx_tenants_owner_email (owner_email)
    )");

    if (!table_has_column($pdo, 'users', 'tenant_id')) {
        safe_exec_schema($pdo, 'ALTER TABLE users ADD COLUMN tenant_id INT NULL AFTER id');
    }

    if (!table_has_index($pdo, 'users', 'idx_users_tenant_id')) {
        safe_exec_schema($pdo, 'ALTER TABLE users ADD INDEX idx_users_tenant_id (tenant_id)');
    }

    $tenantTables = [
        'customers' => 'ALTER TABLE customers ADD COLUMN tenant_id INT NULL AFTER id',
        'loans' => 'ALTER TABLE loans ADD COLUMN tenant_id INT NULL AFTER id',
        'loan_installments' => 'ALTER TABLE loan_installments ADD COLUMN tenant_id INT NULL AFTER id',
        'collections' => 'ALTER TABLE collections ADD COLUMN tenant_id INT NULL AFTER id',
        'customer_documents' => 'ALTER TABLE customer_documents ADD COLUMN tenant_id INT NULL AFTER id',
        'holidays' => 'ALTER TABLE holidays ADD COLUMN tenant_id INT NULL AFTER id',
        'activity_logs' => 'ALTER TABLE activity_logs ADD COLUMN tenant_id INT NULL AFTER id',
    ];

    foreach ($tenantTables as $tableName => $alterSql) {
        if (!table_has_column($pdo, $tableName, 'tenant_id')) {
            safe_exec_schema($pdo, $alterSql);
        }
    }

    if (table_has_column($pdo, 'system_settings', 'tenant_id') === false) {
        safe_exec_schema($pdo, 'ALTER TABLE system_settings ADD COLUMN tenant_id INT NOT NULL DEFAULT 0 FIRST');
        safe_exec_schema($pdo, 'ALTER TABLE system_settings DROP PRIMARY KEY, ADD PRIMARY KEY (tenant_id, setting_key)');
    }

    $tenantCount = (int) $pdo->query('SELECT COUNT(*) FROM tenants')->fetchColumn();
    $legacyDataCount = 0;
    if ($tenantCount === 0) {
        foreach ([
            "SELECT COUNT(*) FROM users WHERE role <> 'superadmin'",
            'SELECT COUNT(*) FROM customers',
            'SELECT COUNT(*) FROM loans',
        ] as $legacyCountSql) {
            try {
                $legacyDataCount += (int) $pdo->query($legacyCountSql)->fetchColumn();
            } catch (Throwable) {
                // Missing legacy tables are handled by the normal schema import path.
            }
        }
    }

    if ($tenantCount === 0 && $legacyDataCount > 0) {
        $pdo->prepare(
            "INSERT INTO tenants (name, slug, owner_name, owner_email, status, approved_at)
             VALUES ('Default Tenant', 'default-tenant', 'Default Tenant Admin', NULL, 'approved', NOW())"
        )->execute();
    }

    $defaultTenantId = (int) $pdo->query('SELECT id FROM tenants ORDER BY id ASC LIMIT 1')->fetchColumn();
    if ($defaultTenantId > 0) {
        safe_exec_schema($pdo, "UPDATE users SET tenant_id = {$defaultTenantId} WHERE tenant_id IS NULL AND role <> 'superadmin'");
        safe_exec_schema($pdo, "UPDATE customers SET tenant_id = {$defaultTenantId} WHERE tenant_id IS NULL");
        safe_exec_schema($pdo, "UPDATE loans SET tenant_id = {$defaultTenantId} WHERE tenant_id IS NULL");
        safe_exec_schema($pdo, "UPDATE loan_installments li JOIN loans l ON l.id = li.loan_id SET li.tenant_id = l.tenant_id WHERE li.tenant_id IS NULL");
        safe_exec_schema($pdo, "UPDATE collections c JOIN loans l ON l.id = c.loan_id SET c.tenant_id = l.tenant_id WHERE c.tenant_id IS NULL");
        safe_exec_schema($pdo, "UPDATE customer_documents cd JOIN customers c ON c.id = cd.customer_id SET cd.tenant_id = c.tenant_id WHERE cd.tenant_id IS NULL");
        safe_exec_schema($pdo, "UPDATE holidays SET tenant_id = {$defaultTenantId} WHERE tenant_id IS NULL");
    }

    $indexMap = [
        'customers' => ['idx_customers_tenant_status' => '(tenant_id, status)'],
        'loans' => ['idx_loans_tenant_status' => '(tenant_id, status)'],
        'loan_installments' => ['idx_loan_installments_tenant_status' => '(tenant_id, status, due_date)'],
        'collections' => ['idx_collections_tenant_date' => '(tenant_id, collected_on)'],
        'customer_documents' => ['idx_customer_documents_tenant_id' => '(tenant_id)'],
        'holidays' => ['idx_holidays_tenant_date' => '(tenant_id, holiday_date)'],
        'activity_logs' => ['idx_activity_logs_tenant_created' => '(tenant_id, created_at)'],
    ];

    foreach ($indexMap as $tableName => $indexes) {
        foreach ($indexes as $indexName => $columns) {
            if (!table_has_index($pdo, $tableName, $indexName)) {
                safe_exec_schema($pdo, 'ALTER TABLE ' . $tableName . ' ADD INDEX ' . $indexName . ' ' . $columns);
            }
        }
    }
}

function installment_status_label(string $status, string $dueDate, ?string $todayDate = null): string
{
    $todayDate ??= today();

    if ($status !== 'paid' && $dueDate !== '' && $dueDate < $todayDate) {
        try {
            $due = new DateTimeImmutable($dueDate);
            $today = new DateTimeImmutable($todayDate);
            $daysLate = max(1, (int) $due->diff($today)->format('%a'));

            return $daysLate === 1 ? '1 day late' : $daysLate . ' days late';
        } catch (Throwable) {
            return 'Late';
        }
    }

    return ucfirst($status);
}

function refresh_overdue_installments(PDO $pdo): void
{
    $tenantId = current_tenant_id();
    $stmt = $pdo->prepare(
        "UPDATE loan_installments li
         JOIN loans l ON l.id = li.loan_id
         SET li.status = 'overdue'
         WHERE li.status IN ('pending', 'partial')
           AND li.due_date < :today
           AND li.paid_amount < li.due_amount
           " . tenant_where_clause('l') . "
           AND NOT EXISTS (
               SELECT 1
               FROM holidays h
               WHERE h.holiday_date = li.due_date
                 " . tenant_where_clause('h', 'holiday_tenant_id') . "
           )"
    );
    $params = ['today' => today()];
    if ($tenantId !== null) {
        $params['tenant_id'] = $tenantId;
        $params['holiday_tenant_id'] = $tenantId;
    }
    $stmt->execute($params);
}

function oldest_due_installment_per_loan(array $installments): array
{
    $seenLoans = [];
    $filtered = [];

    foreach ($installments as $installment) {
        $loanId = (int) ($installment['loan_id'] ?? 0);
        if ($loanId <= 0 || isset($seenLoans[$loanId])) {
            continue;
        }

        $seenLoans[$loanId] = true;
        $filtered[] = $installment;
    }

    return $filtered;
}

function collection_date_offset_for_frequency(string $frequency, string $todayDate, string $selectedDate): ?int
{
    if ($selectedDate <= $todayDate) {
        return 0;
    }

    try {
        $today = new DateTimeImmutable($todayDate);
        $selected = new DateTimeImmutable($selectedDate);
    } catch (Throwable) {
        return null;
    }

    $days = (int) $today->diff($selected)->format('%r%a');
    if ($days < 1) {
        return null;
    }

    return match ($frequency) {
        'daily' => $days,
        'weekly' => $days % 7 === 0 ? intdiv($days, 7) : null,
        'monthly' => collection_month_offset($today, $selected),
        default => $days,
    };
}

function collection_month_offset(DateTimeImmutable $today, DateTimeImmutable $selected): ?int
{
    $cursor = $today;
    for ($offset = 1; $offset <= 240; $offset++) {
        $cursor = $cursor->add(new DateInterval('P1M'));
        $cursorDate = $cursor->format('Y-m-d');

        if ($cursorDate === $selected->format('Y-m-d')) {
            return $offset;
        }

        if ($cursorDate > $selected->format('Y-m-d')) {
            return null;
        }
    }

    return null;
}

function collection_due_installments_for_date(PDO $pdo, string $selectedDate, string $todayDate, string $search, string $currentRole, int $currentUserId): array
{
    $isFutureDate = $selectedDate > $todayDate;
    $sql = "SELECT
                li.id,
                li.loan_id,
                li.installment_no,
                li.due_date,
                li.due_amount,
                li.paid_amount,
                li.status,
                l.loan_number,
                l.installment_frequency,
                c.id AS customer_id,
                c.full_name,
                c.phone
            FROM loan_installments li
            JOIN loans l ON l.id = li.loan_id
            JOIN customers c ON c.id = l.customer_id
            WHERE li.status IN ('pending', 'partial', 'overdue')
              AND l.status = 'active'
              AND li.due_amount > li.paid_amount
              " . tenant_where_clause('l') . "";

    $params = tenant_scope_params();
    if (!$isFutureDate) {
        $sql .= ' AND li.due_date <= :selected_date';
        $params['selected_date'] = $selectedDate;
    }


    if ($search !== '') {
        $sql .= " AND (l.loan_number LIKE :q_loan OR c.full_name LIKE :q_name OR c.phone LIKE :q_phone)";
        $searchLike = '%' . $search . '%';
        $params['q_loan'] = $searchLike;
        $params['q_name'] = $searchLike;
        $params['q_phone'] = $searchLike;
    }

    $sql .= ' ORDER BY l.id ASC, li.installment_no ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    if (!$isFutureDate) {
        usort(
            $rows,
            static fn (array $a, array $b): int => [(string) $a['due_date'], (string) $a['full_name'], (int) $a['installment_no']]
                <=> [(string) $b['due_date'], (string) $b['full_name'], (int) $b['installment_no']]
        );

        return oldest_due_installment_per_loan($rows);
    }

    $byLoan = [];
    foreach ($rows as $row) {
        $loanId = (int) ($row['loan_id'] ?? 0);
        if ($loanId <= 0) {
            continue;
        }

        $byLoan[$loanId][] = $row;
    }

    $futureRows = [];
    foreach ($byLoan as $loanRows) {
        usort(
            $loanRows,
            static fn (array $a, array $b): int => ((int) $a['installment_no']) <=> ((int) $b['installment_no'])
        );

        $firstUnpaid = $loanRows[0] ?? null;
        if (!$firstUnpaid) {
            continue;
        }

        $firstDueDate = (string) ($firstUnpaid['due_date'] ?? '');
        if ($firstDueDate <= $todayDate) {
            $offset = collection_date_offset_for_frequency((string) ($firstUnpaid['installment_frequency'] ?? 'daily'), $todayDate, $selectedDate);
            if ($offset === null || !isset($loanRows[$offset])) {
                continue;
            }

            $row = $loanRows[$offset];
            $row['due_date'] = $selectedDate;
            $row['status'] = 'pending';
            $futureRows[] = $row;
            continue;
        }

        foreach ($loanRows as $row) {
            if ((string) ($row['due_date'] ?? '') === $selectedDate) {
                $futureRows[] = $row;
                break;
            }
        }
    }

    usort(
        $futureRows,
        static fn (array $a, array $b): int => [(string) $a['due_date'], (string) $a['full_name'], (int) $a['installment_no']]
            <=> [(string) $b['due_date'], (string) $b['full_name'], (int) $b['installment_no']]
    );

    return $futureRows;
}

function collection_collected_installments_for_date(PDO $pdo, string $selectedDate, string $search, string $currentRole, int $currentUserId): array
{
    $sql = "SELECT
                li.id,
                li.loan_id,
                li.installment_no,
                li.due_date,
                li.due_amount,
                li.paid_amount,
                li.status,
                col.id AS collection_id,
                col.amount AS collected_amount,
                col.collected_on,
                col.created_at AS collected_at,
                col.method,
                col.note,
                col.payment_ref,
                col.meta_json,
                l.loan_number,
                l.installment_frequency,
                c.id AS customer_id,
                c.full_name,
                c.phone,
                COALESCE(u.full_name, 'Unknown') AS collected_by_name
            FROM collections col
            JOIN loan_installments li ON li.id = col.installment_id
            JOIN loans l ON l.id = col.loan_id
            JOIN customers c ON c.id = l.customer_id
            LEFT JOIN users u ON u.id = col.collected_by_user_id
            WHERE col.collected_on = :selected_date
              " . tenant_where_clause('l') . "";

    $params = tenant_scope_params(['selected_date' => $selectedDate]);


    if ($search !== '') {
        $sql .= " AND (l.loan_number LIKE :q_loan OR c.full_name LIKE :q_name OR c.phone LIKE :q_phone)";
        $searchLike = '%' . $search . '%';
        $params['q_loan'] = $searchLike;
        $params['q_name'] = $searchLike;
        $params['q_phone'] = $searchLike;
    }

    $sql .= ' ORDER BY col.created_at DESC, col.id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function schedule_next_installment_date(PDO $pdo, int $loanId, string $scheduledDate): array
{
    if ($loanId <= 0) {
        throw new RuntimeException('Invalid loan for scheduling.');
    }

    $scheduledDate = trim($scheduledDate);
    $scheduledDateObj = DateTimeImmutable::createFromFormat('Y-m-d', $scheduledDate);
    if (!$scheduledDateObj || $scheduledDateObj->format('Y-m-d') !== $scheduledDate) {
        throw new RuntimeException('Invalid next payment date.');
    }

    if ($scheduledDate <= today()) {
        throw new RuntimeException('Next payment date must be after today.');
    }

    $scheduledDate = next_collectible_date($pdo, $scheduledDate);
    $scheduledDateObj = new DateTimeImmutable($scheduledDate);

    $pendingStmt = $pdo->prepare(
        "SELECT *
         FROM loan_installments
         WHERE loan_id = :loan_id
           AND status IN ('pending', 'partial', 'overdue')
         ORDER BY due_date ASC, installment_no ASC
         FOR UPDATE"
    );
    $pendingStmt->execute(['loan_id' => $loanId]);
    $pendingInstallments = $pendingStmt->fetchAll();

    if (!$pendingInstallments) {
        throw new RuntimeException('No pending installment available to schedule.');
    }

    $nextInstallment = $pendingInstallments[0];
    $nextInstallmentId = (int) $nextInstallment['id'];
    $currentDueDate = (string) $nextInstallment['due_date'];
    $paidAmount = round((float) $nextInstallment['paid_amount'], 2);
    $dueAmount = round((float) $nextInstallment['due_amount'], 2);

    if ($currentDueDate === $scheduledDate) {
        return [
            'installment_id' => $nextInstallmentId,
            'installment_no' => (int) $nextInstallment['installment_no'],
            'from_due_date' => $currentDueDate,
            'to_due_date' => $scheduledDate,
            'changed' => false,
        ];
    }

    $currentDueObj = DateTimeImmutable::createFromFormat('Y-m-d', $currentDueDate);
    if (!$currentDueObj || $currentDueObj->format('Y-m-d') !== $currentDueDate) {
        throw new RuntimeException('Invalid installment due date.');
    }

    $deltaDays = (int) $currentDueObj->diff($scheduledDateObj)->format('%r%a');
    $scheduleSnapshots = array_map(
        static fn (array $installment): array => installment_snapshot($installment, true),
        $pendingInstallments
    );
    $updateStmt = $pdo->prepare(
        'UPDATE loan_installments
         SET due_date = :due_date, status = :status
         WHERE id = :id'
    );

    foreach ($pendingInstallments as $index => $installment) {
        $installmentDue = (string) $installment['due_date'];
        $installmentDueObj = DateTimeImmutable::createFromFormat('Y-m-d', $installmentDue);
        if (!$installmentDueObj || $installmentDueObj->format('Y-m-d') !== $installmentDue) {
            continue;
        }

        $newDueDate = $index === 0
            ? $scheduledDate
            : next_collectible_date($pdo, $installmentDueObj->modify(sprintf('%+d days', $deltaDays))->format('Y-m-d'));

        $installmentPaid = round((float) $installment['paid_amount'], 2);
        $installmentDueAmount = round((float) $installment['due_amount'], 2);
        $updatedStatus = $installmentPaid >= $installmentDueAmount
            ? 'paid'
            : ($installmentPaid > 0 ? 'partial' : 'pending');

        $updateStmt->execute([
            'due_date' => $newDueDate,
            'status' => $updatedStatus,
            'id' => (int) $installment['id'],
        ]);
    }

    return [
        'installment_id' => $nextInstallmentId,
        'installment_no' => (int) $nextInstallment['installment_no'],
        'from_due_date' => $currentDueDate,
        'to_due_date' => $scheduledDate,
        'changed' => true,
        'shifted_count' => count($pendingInstallments),
        'installment_snapshots' => $scheduleSnapshots,
    ];
}

function installment_snapshot(array $row, bool $existsBefore = true): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'exists_before' => $existsBefore,
        'row' => [
            'id' => (int) ($row['id'] ?? 0),
            'loan_id' => (int) ($row['loan_id'] ?? 0),
            'installment_no' => (int) ($row['installment_no'] ?? 0),
            'due_date' => (string) ($row['due_date'] ?? ''),
            'due_amount' => round((float) ($row['due_amount'] ?? 0), 2),
            'paid_amount' => round((float) ($row['paid_amount'] ?? 0), 2),
            'paid_on' => isset($row['paid_on']) && (string) $row['paid_on'] !== '' ? (string) $row['paid_on'] : null,
            'status' => (string) ($row['status'] ?? 'pending'),
            'is_flexible_adjustment' => (int) ($row['is_flexible_adjustment'] ?? 0),
            'source_payment_ref' => isset($row['source_payment_ref']) && (string) $row['source_payment_ref'] !== '' ? (string) $row['source_payment_ref'] : null,
            'created_at' => isset($row['created_at']) && (string) $row['created_at'] !== '' ? (string) $row['created_at'] : null,
        ],
    ];
}

function record_loan_collection_payment(
    PDO $pdo,
    array $loan,
    int $selectedInstallmentId,
    float $amount,
    string $collectedOn,
    string $paidOnDate,
    string $method,
    ?string $note,
    ?int $collectorId,
    string $paymentRef,
    bool $allowOverpayment
): array {
    $loanId = (int) ($loan['id'] ?? 0);
    if ($loanId <= 0 || $selectedInstallmentId <= 0 || $amount <= 0) {
        throw new RuntimeException('Invalid collection details.');
    }

    $amount = round($amount, 2);
    $note = trim((string) $note);
    $collectorId = $collectorId !== null && $collectorId > 0 ? $collectorId : null;

    $outstandingStmt = $pdo->prepare(
        "SELECT COALESCE(SUM(due_amount - paid_amount), 0)
         FROM loan_installments
         WHERE loan_id = :loan_id
           AND status IN ('pending', 'partial', 'overdue')
           AND due_amount > paid_amount"
    );
    $outstandingStmt->execute(['loan_id' => $loanId]);
    $outstanding = round((float) $outstandingStmt->fetchColumn(), 2);

    if ($outstanding <= 0.009) {
        throw new RuntimeException('This loan has no pending installments to collect.');
    }

    if (!$allowOverpayment && $amount > $outstanding + 0.009) {
        throw new RuntimeException('Overpayment is disabled. Maximum allowed amount is ' . money_label($pdo, $outstanding) . '.');
    }

    $pendingStmt = $pdo->prepare(
        "SELECT *
         FROM loan_installments
         WHERE loan_id = :loan_id
           AND status IN ('pending', 'partial', 'overdue')
           AND due_amount > paid_amount
         ORDER BY due_date ASC, installment_no ASC
         FOR UPDATE"
    );
    $pendingStmt->execute(['loan_id' => $loanId]);
    $pendingInstallments = $pendingStmt->fetchAll();

    if ($pendingInstallments === []) {
        throw new RuntimeException('No pending installments found.');
    }

    if ((int) $pendingInstallments[0]['id'] !== $selectedInstallmentId) {
        throw new RuntimeException('Only the current installment can be collected.');
    }

    $selected = $pendingInstallments[0];
    $selectedBalance = round((float) $selected['due_amount'] - (float) $selected['paid_amount'], 2);
    if ($selectedBalance <= 0.009) {
        throw new RuntimeException('Selected installment is already collected.');
    }

    $snapshots = [];
    $collectionRowCount = 0;

    $addSnapshot = static function (array $row, bool $existsBefore = true) use (&$snapshots): void {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0 || isset($snapshots[$id])) {
            return;
        }

        $snapshots[$id] = installment_snapshot($row, $existsBefore);
    };

    $insertCollection = $pdo->prepare(
        'INSERT INTO collections (tenant_id, loan_id, installment_id, amount, collected_on, method, note, collected_by_user_id, payment_ref, meta_json)
         VALUES (:tenant_id, :loan_id, :installment_id, :amount, :collected_on, :method, :note, :collected_by_user_id, :payment_ref, NULL)'
    );
    $insertCollection->execute([
        'tenant_id' => require_tenant_context(),
        'loan_id' => $loanId,
        'installment_id' => $selectedInstallmentId,
        'amount' => $amount,
        'collected_on' => $collectedOn,
        'method' => $method,
        'note' => $note === '' ? null : $note,
        'collected_by_user_id' => $collectorId,
        'payment_ref' => $paymentRef,
    ]);
    $collectionRowCount++;

    $addSnapshot($selected, true);
    $completeSelectedStmt = $pdo->prepare(
        "UPDATE loan_installments
         SET due_amount = :due_amount,
             paid_amount = :paid_amount,
             paid_on = :paid_on,
             status = 'paid'
         WHERE id = :id
           AND loan_id = :loan_id"
    );
    $completeSelectedStmt->execute([
        'due_amount' => $amount,
        'paid_amount' => $amount,
        'paid_on' => $paidOnDate,
        'id' => $selectedInstallmentId,
        'loan_id' => $loanId,
    ]);

    $reduceTail = function (float $amountToReduce) use ($pdo, $loanId, $selectedInstallmentId, $paidOnDate, $addSnapshot): float {
        $remaining = round($amountToReduce, 2);
        if ($remaining <= 0.009) {
            return 0.0;
        }

        $tailStmt = $pdo->prepare(
            "SELECT *
             FROM loan_installments
             WHERE loan_id = :loan_id
               AND id <> :selected_id
               AND status IN ('pending', 'partial', 'overdue')
               AND due_amount > paid_amount
             ORDER BY installment_no DESC
             LIMIT 1
             FOR UPDATE"
        );
        $collectionLinkStmt = $pdo->prepare('SELECT COUNT(*) FROM collections WHERE installment_id = :installment_id');
        $deleteTailStmt = $pdo->prepare('DELETE FROM loan_installments WHERE id = :id AND loan_id = :loan_id');
        $closeTailStmt = $pdo->prepare(
            "UPDATE loan_installments
             SET due_amount = paid_amount,
                 paid_on = COALESCE(paid_on, :paid_on),
                 status = 'paid'
             WHERE id = :id
               AND loan_id = :loan_id"
        );
        $shrinkTailStmt = $pdo->prepare(
            'UPDATE loan_installments
             SET due_amount = :due_amount,
                 status = :status
             WHERE id = :id
               AND loan_id = :loan_id'
        );

        while ($remaining > 0.009) {
            $tailStmt->execute([
                'loan_id' => $loanId,
                'selected_id' => $selectedInstallmentId,
            ]);
            $tail = $tailStmt->fetch();
            if (!$tail) {
                break;
            }

            $tailBalance = round((float) $tail['due_amount'] - (float) $tail['paid_amount'], 2);
            if ($tailBalance <= 0.009) {
                break;
            }

            $addSnapshot($tail, true);
            if ($remaining + 0.009 >= $tailBalance) {
                $collectionLinkStmt->execute(['installment_id' => (int) $tail['id']]);
                $hasLinkedCollections = (int) $collectionLinkStmt->fetchColumn() > 0;

                if ($hasLinkedCollections) {
                    $closeTailStmt->execute([
                        'paid_on' => $paidOnDate,
                        'id' => (int) $tail['id'],
                        'loan_id' => $loanId,
                    ]);
                } else {
                    $deleteTailStmt->execute([
                        'id' => (int) $tail['id'],
                        'loan_id' => $loanId,
                    ]);
                }

                $remaining = round($remaining - $tailBalance, 2);
                continue;
            }

            $newDueAmount = round((float) $tail['due_amount'] - $remaining, 2);
            $newStatus = (string) $tail['due_date'] < today() ? 'overdue' : 'pending';
            $shrinkTailStmt->execute([
                'due_amount' => $newDueAmount,
                'status' => $newStatus,
                'id' => (int) $tail['id'],
                'loan_id' => $loanId,
            ]);
            $remaining = 0.0;
        }

        return $remaining;
    };

    $addShortfallToTail = function (float $shortfall) use ($pdo, $loan, $loanId, $selectedInstallmentId, $paymentRef, $collectedOn, $addSnapshot): void {
        $shortfall = round($shortfall, 2);
        if ($shortfall <= 0.009) {
            return;
        }

        $tailStmt = $pdo->prepare(
            "SELECT *
             FROM loan_installments
             WHERE loan_id = :loan_id
               AND id <> :selected_id
               AND status IN ('pending', 'partial', 'overdue')
               AND due_amount > paid_amount
             ORDER BY installment_no DESC
             LIMIT 1
             FOR UPDATE"
        );
        $tailStmt->execute([
            'loan_id' => $loanId,
            'selected_id' => $selectedInstallmentId,
        ]);
        $tail = $tailStmt->fetch();

        if ($tail) {
            $addSnapshot($tail, true);
            $status = (string) $tail['due_date'] < today() ? 'overdue' : 'pending';
            $updateTail = $pdo->prepare(
                'UPDATE loan_installments
                 SET due_amount = :due_amount,
                     status = :status
                 WHERE id = :id
                   AND loan_id = :loan_id'
            );
            $updateTail->execute([
                'due_amount' => round((float) $tail['due_amount'] + $shortfall, 2),
                'status' => $status,
                'id' => (int) $tail['id'],
                'loan_id' => $loanId,
            ]);
            return;
        }

        $lastStmt = $pdo->prepare(
            'SELECT installment_no, due_date
             FROM loan_installments
             WHERE loan_id = :loan_id
             ORDER BY installment_no DESC
             LIMIT 1
             FOR UPDATE'
        );
        $lastStmt->execute(['loan_id' => $loanId]);
        $last = $lastStmt->fetch();

        $nextNo = ((int) ($last['installment_no'] ?? 0)) + 1;
        $baseDate = DateTimeImmutable::createFromFormat('Y-m-d', $collectedOn) ?: new DateTimeImmutable(today());
        $nextDueDate = next_collectible_date($pdo, $baseDate->add(frequency_interval((string) ($loan['installment_frequency'] ?? 'daily')))->format('Y-m-d'));

        $insertTail = $pdo->prepare(
            'INSERT INTO loan_installments
                (tenant_id, loan_id, installment_no, due_date, due_amount, paid_amount, status, is_flexible_adjustment, source_payment_ref)
             VALUES
                (:tenant_id, :loan_id, :installment_no, :due_date, :due_amount, 0, :status, 1, :source_payment_ref)'
        );
        $insertTail->execute([
            'tenant_id' => require_tenant_context(),
            'loan_id' => $loanId,
            'installment_no' => $nextNo,
            'due_date' => $nextDueDate,
            'due_amount' => $shortfall,
            'status' => $nextDueDate < today() ? 'overdue' : 'pending',
            'source_payment_ref' => $paymentRef,
        ]);

        $addSnapshot(['id' => (int) $pdo->lastInsertId()], false);
    };

    $delta = round($amount - $selectedBalance, 2);
    if ($delta > 0.009) {
        $unappliedExtra = $reduceTail($delta);
        if ($unappliedExtra > 0.009 && !$allowOverpayment) {
            throw new RuntimeException('Overpayment is disabled for this system.');
        }
    } elseif ($delta < -0.009) {
        $addShortfallToTail(abs($delta));
    }

    $rescheduledCount = 0;
    $remainingStmt = $pdo->prepare(
        "SELECT *
         FROM loan_installments
         WHERE loan_id = :loan_id
           AND status IN ('pending', 'partial', 'overdue')
           AND due_amount > paid_amount
         ORDER BY installment_no ASC
         FOR UPDATE"
    );
    $remainingStmt->execute(['loan_id' => $loanId]);
    $remainingInstallments = $remainingStmt->fetchAll();

    if ($remainingInstallments !== []) {
        $baseDate = DateTimeImmutable::createFromFormat('Y-m-d', $collectedOn);
        if (!$baseDate || $baseDate->format('Y-m-d') !== $collectedOn) {
            $baseDate = new DateTimeImmutable(today());
        }

        $interval = frequency_interval((string) ($loan['installment_frequency'] ?? 'daily'));
        $nextDueDate = $baseDate->add($interval);
        $updateScheduleStmt = $pdo->prepare(
            'UPDATE loan_installments
             SET due_date = :due_date,
                 status = :status
             WHERE id = :id'
        );

        foreach ($remainingInstallments as $installment) {
            $newDueDate = next_collectible_date($pdo, $nextDueDate->format('Y-m-d'));
            $newStatus = $newDueDate < today() ? 'overdue' : 'pending';

            if ((string) $installment['due_date'] !== $newDueDate || (string) $installment['status'] !== $newStatus) {
                $addSnapshot($installment, true);
                $updateScheduleStmt->execute([
                    'due_date' => $newDueDate,
                    'status' => $newStatus,
                    'id' => (int) $installment['id'],
                ]);
                $rescheduledCount++;
            }

            $nextDueDate = (new DateTimeImmutable($newDueDate))->add($interval);
        }
    }

    $meta = [
        'version' => 2,
        'rule' => 'single_installment_tail_adjustment',
        'selected_installment_id' => $selectedInstallmentId,
        'entered_amount' => $amount,
        'original_selected_balance' => $selectedBalance,
        'rescheduled_remaining_count' => $rescheduledCount,
        'installment_snapshots' => array_values($snapshots),
    ];
    $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE);
    if ($metaJson !== false && $collectionRowCount > 0) {
        $metaStmt = $pdo->prepare(
            'UPDATE collections
             SET meta_json = :meta_json
             WHERE loan_id = :loan_id
               AND payment_ref = :payment_ref'
        );
        $metaStmt->execute([
            'meta_json' => $metaJson,
            'loan_id' => $loanId,
            'payment_ref' => $paymentRef,
        ]);
    }

    $pendingCountStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM loan_installments
         WHERE loan_id = :loan_id
           AND status IN ('pending', 'partial', 'overdue')
           AND due_amount > paid_amount"
    );
    $pendingCountStmt->execute(['loan_id' => $loanId]);

    return [
        'pending_count' => (int) $pendingCountStmt->fetchColumn(),
        'payment_ref' => $paymentRef,
    ];
}

function append_collection_payment_snapshots(PDO $pdo, int $loanId, string $paymentRef, array $snapshots, array $extraMeta = []): void
{
    $paymentRef = trim($paymentRef);
    if ($loanId <= 0 || $paymentRef === '' || $snapshots === []) {
        return;
    }

    $metaStmt = $pdo->prepare(
        'SELECT meta_json
         FROM collections
         WHERE loan_id = :loan_id
           AND payment_ref = :payment_ref
         ORDER BY id ASC
         LIMIT 1'
    );
    $metaStmt->execute([
        'loan_id' => $loanId,
        'payment_ref' => $paymentRef,
    ]);
    $meta = json_decode((string) $metaStmt->fetchColumn(), true);
    if (!is_array($meta)) {
        $meta = [];
    }

    $existingSnapshots = isset($meta['installment_snapshots']) && is_array($meta['installment_snapshots'])
        ? $meta['installment_snapshots']
        : [];
    $seen = [];
    foreach ($existingSnapshots as $snapshot) {
        if (!is_array($snapshot)) {
            continue;
        }
        $data = isset($snapshot['row']) && is_array($snapshot['row'])
            ? $snapshot['row']
            : (isset($snapshot['data']) && is_array($snapshot['data']) ? $snapshot['data'] : $snapshot);
        $id = (int) ($data['id'] ?? $snapshot['id'] ?? 0);
        if ($id > 0) {
            $seen[$id] = true;
        }
    }

    foreach ($snapshots as $snapshot) {
        if (!is_array($snapshot)) {
            continue;
        }
        $data = isset($snapshot['row']) && is_array($snapshot['row'])
            ? $snapshot['row']
            : (isset($snapshot['data']) && is_array($snapshot['data']) ? $snapshot['data'] : $snapshot);
        $id = (int) ($data['id'] ?? $snapshot['id'] ?? 0);
        if ($id <= 0 || isset($seen[$id])) {
            continue;
        }

        $existingSnapshots[] = $snapshot;
        $seen[$id] = true;
    }

    $meta = array_merge($meta, $extraMeta);
    $meta['installment_snapshots'] = $existingSnapshots;

    $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE);
    if ($metaJson === false) {
        return;
    }

    $updateStmt = $pdo->prepare(
        'UPDATE collections
         SET meta_json = :meta_json
         WHERE loan_id = :loan_id
           AND payment_ref = :payment_ref'
    );
    $updateStmt->execute([
        'meta_json' => $metaJson,
        'loan_id' => $loanId,
        'payment_ref' => $paymentRef,
    ]);
}

function undo_collection_payment(PDO $pdo, int $collectionId, int $actorUserId, string $actorRole): array
{
    if ($collectionId <= 0) {
        throw new RuntimeException('Invalid collection selected for undo.');
    }

    $collectionStmt = $pdo->prepare(
        "SELECT
            col.*,
            l.loan_number,
            c.full_name AS customer_name
         FROM collections col
         JOIN loans l ON l.id = col.loan_id
         JOIN customers c ON c.id = l.customer_id
         WHERE col.id = :id
           " . tenant_where_clause('col') . "
         LIMIT 1
         FOR UPDATE"
    );
    $collectionStmt->execute(tenant_scope_params(['id' => $collectionId]));
    $collection = $collectionStmt->fetch();
    if (!$collection) {
        throw new RuntimeException('Collection not found.');
    }

    $loanId = (int) $collection['loan_id'];
    $paymentRef = trim((string) ($collection['payment_ref'] ?? ''));
    if ($loanId <= 0 || $paymentRef === '') {
        throw new RuntimeException('This collection cannot be undone because it does not have a payment reference.');
    }


    $paymentRowsStmt = $pdo->prepare(
        'SELECT *
         FROM collections
         WHERE loan_id = :loan_id
           AND payment_ref = :payment_ref
           ' . tenant_where_clause() . '
         ORDER BY id ASC
         FOR UPDATE'
    );
    $paymentRowsStmt->execute(tenant_scope_params([
        'loan_id' => $loanId,
        'payment_ref' => $paymentRef,
    ]));
    $paymentRows = $paymentRowsStmt->fetchAll();
    if (!$paymentRows) {
        throw new RuntimeException('Collection payment group not found.');
    }

    $maxPaymentId = 0;
    $totalAmount = 0.0;
    $meta = null;
    foreach ($paymentRows as $row) {
        $maxPaymentId = max($maxPaymentId, (int) $row['id']);
        $totalAmount += (float) $row['amount'];
        if ($meta === null && trim((string) ($row['meta_json'] ?? '')) !== '') {
            $meta = json_decode((string) $row['meta_json'], true);
        }
    }

    $latestStmt = $pdo->prepare('SELECT COALESCE(MAX(id), 0) FROM collections WHERE loan_id = :loan_id' . tenant_where_clause());
    $latestStmt->execute(tenant_scope_params(['loan_id' => $loanId]));
    $latestCollectionId = (int) $latestStmt->fetchColumn();
    if ($latestCollectionId !== $maxPaymentId) {
        throw new RuntimeException('Only the latest collection for this loan can be undone safely.');
    }

    if (!is_array($meta) || !isset($meta['installment_snapshots']) || !is_array($meta['installment_snapshots'])) {
        throw new RuntimeException('This collection cannot be undone because its installment snapshots are missing.');
    }

    $snapshots = $meta['installment_snapshots'];
    if ($snapshots === []) {
        throw new RuntimeException('This collection cannot be undone because its installment snapshots are empty.');
    }

    $deleteCollectionsStmt = $pdo->prepare(
        'DELETE FROM collections
         WHERE loan_id = :loan_id
           AND payment_ref = :payment_ref'
    );
    $deleteCollectionsStmt->execute([
        'loan_id' => $loanId,
        'payment_ref' => $paymentRef,
    ]);

    $collectionLinkStmt = $pdo->prepare('SELECT COUNT(*) FROM collections WHERE installment_id = :installment_id');
    $deleteInstallmentStmt = $pdo->prepare('DELETE FROM loan_installments WHERE id = :id AND loan_id = :loan_id');
    $existsInstallmentStmt = $pdo->prepare('SELECT COUNT(*) FROM loan_installments WHERE id = :id');
    $updateInstallmentStmt = $pdo->prepare(
        'UPDATE loan_installments
         SET loan_id = :loan_id,
             installment_no = :installment_no,
             due_date = :due_date,
             due_amount = :due_amount,
             paid_amount = :paid_amount,
             paid_on = :paid_on,
             status = :status,
             is_flexible_adjustment = :is_flexible_adjustment,
             source_payment_ref = :source_payment_ref
         WHERE id = :id'
    );
    $insertInstallmentStmt = $pdo->prepare(
        'INSERT INTO loan_installments
            (id, tenant_id, loan_id, installment_no, due_date, due_amount, paid_amount, paid_on, status, is_flexible_adjustment, source_payment_ref)
         VALUES
            (:id, :tenant_id, :loan_id, :installment_no, :due_date, :due_amount, :paid_amount, :paid_on, :status, :is_flexible_adjustment, :source_payment_ref)'
    );

    foreach ($snapshots as $snapshot) {
        if (!is_array($snapshot)) {
            continue;
        }

        $existsBefore = (bool) ($snapshot['exists_before'] ?? true);
        $row = isset($snapshot['row']) && is_array($snapshot['row'])
            ? $snapshot['row']
            : (isset($snapshot['data']) && is_array($snapshot['data']) ? $snapshot['data'] : $snapshot);
        $installmentId = (int) ($row['id'] ?? $snapshot['id'] ?? 0);
        if ($installmentId <= 0) {
            continue;
        }

        if (!$existsBefore) {
            $collectionLinkStmt->execute(['installment_id' => $installmentId]);
            if ((int) $collectionLinkStmt->fetchColumn() > 0) {
                throw new RuntimeException('Cannot undo because a generated installment has later collections.');
            }

            $deleteInstallmentStmt->execute([
                'id' => $installmentId,
                'loan_id' => $loanId,
            ]);
            continue;
        }

        $snapshotLoanId = (int) ($row['loan_id'] ?? 0);
        if ($snapshotLoanId !== $loanId) {
            throw new RuntimeException('Cannot undo because an installment snapshot belongs to a different loan.');
        }

        $status = (string) ($row['status'] ?? 'pending');
        if (!in_array($status, ['pending', 'partial', 'paid', 'overdue'], true)) {
            $status = 'pending';
        }

        $params = [
            'id' => $installmentId,
            'tenant_id' => require_tenant_context(),
            'loan_id' => $loanId,
            'installment_no' => (int) ($row['installment_no'] ?? 0),
            'due_date' => (string) ($row['due_date'] ?? today()),
            'due_amount' => round((float) ($row['due_amount'] ?? 0), 2),
            'paid_amount' => round((float) ($row['paid_amount'] ?? 0), 2),
            'paid_on' => isset($row['paid_on']) && (string) $row['paid_on'] !== '' ? (string) $row['paid_on'] : null,
            'status' => $status,
            'is_flexible_adjustment' => (int) ($row['is_flexible_adjustment'] ?? 0),
            'source_payment_ref' => isset($row['source_payment_ref']) && (string) $row['source_payment_ref'] !== '' ? (string) $row['source_payment_ref'] : null,
        ];

        if ($params['installment_no'] <= 0 || $params['due_amount'] < 0 || $params['paid_amount'] < 0) {
            throw new RuntimeException('Cannot undo because an installment snapshot is invalid.');
        }

        $existsInstallmentStmt->execute(['id' => $installmentId]);
        if ((int) $existsInstallmentStmt->fetchColumn() > 0) {
            $updateInstallmentStmt->execute($params);
        } else {
            $insertInstallmentStmt->execute($params);
        }
    }

    $pendingCountStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM loan_installments
         WHERE loan_id = :loan_id
           AND status IN ('pending', 'partial', 'overdue')
           AND due_amount > paid_amount"
    );
    $pendingCountStmt->execute(['loan_id' => $loanId]);
    $pendingCount = (int) $pendingCountStmt->fetchColumn();

    if ($pendingCount === 0) {
        $closeLoan = $pdo->prepare("UPDATE loans SET status = 'closed' WHERE id = :id");
        $closeLoan->execute(['id' => $loanId]);
    } else {
        $reopenLoan = $pdo->prepare("UPDATE loans SET status = 'active' WHERE id = :id");
        $reopenLoan->execute(['id' => $loanId]);
    }

    return [
        'loan_id' => $loanId,
        'loan_number' => (string) ($collection['loan_number'] ?? ('#' . $loanId)),
        'customer_name' => (string) ($collection['customer_name'] ?? ''),
        'payment_ref' => $paymentRef,
        'amount' => round($totalAmount, 2),
        'collection_count' => count($paymentRows),
        'restored_installment_count' => count($snapshots),
    ];
}

function dashboard_stats(PDO $pdo, ?array $viewer = null): array
{
    refresh_overdue_installments($pdo);

    $viewerRole = (string) ($viewer['role'] ?? '');
    $viewerId = (int) ($viewer['id'] ?? 0);
    $tenantId = current_tenant_id($viewer);

    $totals = [
        'customers' => 0,
        'active_loans' => 0,
        'today_pending_amount' => 0.0,
        'today_pending_count' => 0,
        'overdue_amount' => 0.0,
        'overdue_count' => 0,
        'overdue_customers' => 0,
        'outstanding_principal' => 0.0,
        'closed_loans_profit' => 0.0,
        'expected_open_profit' => 0.0,
        'daily_collected_amount' => 0.0,
        'daily_profit' => 0.0,
        'total_disbursed' => 0.0,
        'total_collected' => 0.0,
    ];

        $params = [];
        $customerScope = '';
        $loanScope = '';
        $collectionScope = '';
        if ($tenantId !== null) {
            $params['tenant_id'] = $tenantId;
            $customerScope = ' WHERE tenant_id = :tenant_id';
            $loanScope = ' AND l.tenant_id = :tenant_id';
            $collectionScope = ' WHERE tenant_id = :tenant_id';
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM customers' . $customerScope);
        $stmt->execute($params);
        $totals['customers'] = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM loans l WHERE l.status = 'active'" . $loanScope);
        $stmt->execute($params);
        $totals['active_loans'] = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(li.due_amount - li.paid_amount), 0) FROM loan_installments li JOIN loans l ON l.id = li.loan_id WHERE l.status = 'active' AND li.status IN ('pending', 'partial', 'overdue')" . $loanScope);
        $stmt->execute($params);
        $totals['outstanding_principal'] = (float) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(l.total_amount - l.principal_amount), 0) FROM loans l WHERE l.status = 'closed'" . $loanScope);
        $stmt->execute($params);
        $totals['closed_loans_profit'] = (float) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COALESCE(SUM(l.total_amount - l.principal_amount), 0) FROM loans l WHERE l.status <> 'closed'" . $loanScope);
        $stmt->execute($params);
        $totals['expected_open_profit'] = (float) $stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT COALESCE(SUM(principal_amount), 0) FROM loans' . $collectionScope);
        $stmt->execute($params);
        $totals['total_disbursed'] = (float) $stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM collections' . $collectionScope);
        $stmt->execute($params);
        $totals['total_collected'] = (float) $stmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT
                COALESCE(SUM(c.amount), 0) AS collected_amount,
                COALESCE(SUM(
                    CASE
                        WHEN l.total_amount > 0
                        THEN c.amount * ((l.total_amount - l.principal_amount) / l.total_amount)
                        ELSE 0
                    END
                ), 0) AS profit_amount
             FROM collections c
             JOIN loans l ON l.id = c.loan_id
             WHERE c.collected_on = :today" . ($tenantId !== null ? ' AND l.tenant_id = :tenant_id' : '')
        );
        $dailyParams = ['today' => today()];
        if ($tenantId !== null) {
            $dailyParams['tenant_id'] = $tenantId;
        }
        $stmt->execute($dailyParams);
        $dailyProfitRow = $stmt->fetch() ?: [];
        $totals['daily_collected_amount'] = (float) ($dailyProfitRow['collected_amount'] ?? 0);
        $totals['daily_profit'] = (float) ($dailyProfitRow['profit_amount'] ?? 0);


    $todayDueRows = collection_due_installments_for_date($pdo, today(), today(), '', $viewerRole, $viewerId);
    $todayDueAmount = 0.0;
    $todayOverdueAmount = 0.0;
    $todayOverdueCustomers = [];

    foreach ($todayDueRows as $row) {
        $balance = max(0.0, (float) ($row['due_amount'] ?? 0) - (float) ($row['paid_amount'] ?? 0));
        $todayDueAmount += $balance;

        if ((string) ($row['due_date'] ?? '') < today() || (string) ($row['status'] ?? '') === 'overdue') {
            $todayOverdueAmount += $balance;
            $customerId = (int) ($row['customer_id'] ?? 0);
            if ($customerId > 0) {
                $todayOverdueCustomers[$customerId] = true;
            }
        }
    }

    $totals['today_pending_amount'] = $todayDueAmount;
    $totals['today_pending_count'] = count($todayDueRows);
    $totals['overdue_amount'] = $todayOverdueAmount;
    $totals['overdue_count'] = count(array_filter(
        $todayDueRows,
        static fn (array $row): bool => (string) ($row['due_date'] ?? '') < today() || (string) ($row['status'] ?? '') === 'overdue'
    ));
    $totals['overdue_customers'] = count($todayOverdueCustomers);

    return $totals;
}

function ensure_user_schema(PDO $pdo): void
{
    $roleColStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'");
    $roleColumn = $roleColStmt->fetch();

    if (!$roleColumn) {
        $pdo->exec("ALTER TABLE users ADD COLUMN role ENUM('superadmin','owner','manager','collector') NOT NULL DEFAULT 'manager' AFTER password_hash");
        return;
    }

    try {
        ensure_user_permissions_schema($pdo);
        $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('superadmin','admin','owner','manager','collector_l1','collector_l2','collector') NOT NULL DEFAULT 'manager'");
        $pdo->exec("UPDATE users SET role = 'collector' WHERE role IN ('collector_l1', 'collector_l2')");
        $pdo->exec("UPDATE users u
            JOIN (
                SELECT tenant_id, MIN(id) AS owner_id
                FROM users
                WHERE tenant_id IS NOT NULL
                  AND role IN ('admin', 'owner')
                GROUP BY tenant_id
            ) tenant_owner ON tenant_owner.tenant_id = u.tenant_id
            SET u.role = CASE WHEN u.id = tenant_owner.owner_id THEN 'owner' ELSE 'manager' END
            WHERE u.tenant_id IS NOT NULL
              AND u.role IN ('admin', 'owner')");
        $pdo->exec("UPDATE users SET role = 'manager' WHERE role = 'admin'");
        $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('superadmin','owner','manager','collector') NOT NULL DEFAULT 'manager'");
        $ownerPermissionKeys = role_default_permissions('owner');
        $ownerRows = $pdo->query("SELECT id FROM users WHERE role = 'owner'")->fetchAll();
        $ownerPermissionStmt = $pdo->prepare(
            'INSERT INTO user_permissions (user_id, permission_key, allowed)
             VALUES (:user_id, :permission_key, 1)
             ON DUPLICATE KEY UPDATE allowed = VALUES(allowed)'
        );
        foreach ($ownerRows as $ownerRow) {
            $ownerUserId = (int) ($ownerRow['id'] ?? 0);
            if ($ownerUserId <= 0) {
                continue;
            }

            foreach ($ownerPermissionKeys as $permissionKey) {
                $ownerPermissionStmt->execute([
                    'user_id' => $ownerUserId,
                    'permission_key' => $permissionKey,
                ]);
            }
        }
    } catch (Throwable $e) {
        error_log('Failed to normalize users.role enum: ' . $e->getMessage());
    }
}
function ensure_user_permissions_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS user_permissions (
            user_id INT NOT NULL,
            permission_key VARCHAR(80) NOT NULL,
            allowed TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, permission_key),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )"
    );

    $pdo->exec("DELETE FROM user_permissions WHERE permission_key = 'customers.view_all'");

    $validKeys = permission_keys();
    if ($validKeys === []) {
        return;
    }

    $users = $pdo->query('SELECT id, role FROM users')->fetchAll();
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM user_permissions WHERE user_id = :user_id');
    $upsertStmt = $pdo->prepare(
        'INSERT INTO user_permissions (user_id, permission_key, allowed)
         VALUES (:user_id, :permission_key, 1)
         ON DUPLICATE KEY UPDATE allowed = VALUES(allowed)'
    );

    foreach ($users as $user) {
        $userId = (int) $user['id'];
        if ($userId <= 0) {
            continue;
        }

        $countStmt->execute(['user_id' => $userId]);
        if ((int) $countStmt->fetchColumn() > 0) {
            continue;
        }

        foreach (role_default_permissions((string) $user['role']) as $key) {
            if (!in_array($key, $validKeys, true)) {
                continue;
            }
            $upsertStmt->execute([
                'user_id' => $userId,
                'permission_key' => $key,
            ]);
        }
    }

}

function ensure_one_owner_per_tenant_schema(PDO $pdo): void
{
    try {
        $ownerRows = $pdo->query(
            "SELECT id, tenant_id
             FROM users
             WHERE tenant_id IS NOT NULL
               AND role = 'owner'
             ORDER BY tenant_id ASC, id ASC"
        )->fetchAll();

        $seenTenantIds = [];
        $demotedUserIds = [];
        foreach ($ownerRows as $ownerRow) {
            $tenantId = (int) ($ownerRow['tenant_id'] ?? 0);
            $userId = (int) ($ownerRow['id'] ?? 0);
            if ($tenantId <= 0 || $userId <= 0) {
                continue;
            }

            if (!isset($seenTenantIds[$tenantId])) {
                $seenTenantIds[$tenantId] = true;
                continue;
            }

            $demotedUserIds[] = $userId;
        }

        if ($demotedUserIds !== []) {
            $demoteStmt = $pdo->prepare("UPDATE users SET role = 'manager', status = 'active' WHERE id = :id");
            foreach ($demotedUserIds as $demotedUserId) {
                $demoteStmt->execute(['id' => $demotedUserId]);
                sync_user_permissions($pdo, $demotedUserId, role_default_permissions('manager'));
            }
        }

        if (!table_has_column($pdo, 'users', 'owner_tenant_unique_key')) {
            $pdo->exec(
                "ALTER TABLE users
                 ADD COLUMN owner_tenant_unique_key INT
                 GENERATED ALWAYS AS (CASE WHEN role = 'owner' THEN tenant_id ELSE NULL END) STORED
                 AFTER role"
            );
        }

        if (!table_has_index($pdo, 'users', 'uq_users_one_owner_per_tenant')) {
            $pdo->exec('ALTER TABLE users ADD UNIQUE KEY uq_users_one_owner_per_tenant (owner_tenant_unique_key)');
        }
    } catch (Throwable $e) {
        error_log('Failed to enforce one owner per tenant: ' . $e->getMessage());
    }
}
function ensure_user_email_schema(PDO $pdo): void
{
    $emailColStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'email'");
    $emailColumn = $emailColStmt->fetch();

    if (!$emailColumn) {
        $pdo->exec('ALTER TABLE users ADD COLUMN email VARCHAR(190) NULL AFTER username');
    }

    // Normalize blanks before adding a unique key.
    $pdo->exec("UPDATE users SET email = NULL WHERE email IS NOT NULL AND TRIM(email) = ''");

    $indexStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.statistics
         WHERE table_schema = DATABASE() AND table_name = 'users' AND index_name = 'uq_users_email'"
    );
    $indexStmt->execute();
    $hasIndex = (int) $indexStmt->fetchColumn() > 0;
    if (!$hasIndex) {
        $duplicateStmt = $pdo->query(
            "SELECT COUNT(*) FROM (
                SELECT LOWER(TRIM(email)) AS normalized_email
                FROM users
                WHERE email IS NOT NULL AND TRIM(email) <> ''
                GROUP BY LOWER(TRIM(email))
                HAVING COUNT(*) > 1
            ) AS duplicate_emails"
        );
        $hasDuplicates = (int) $duplicateStmt->fetchColumn() > 0;

        if ($hasDuplicates) {
            error_log('Skipped users email unique key migration: duplicate emails exist.');
            return;
        }

        try {
            $pdo->exec('ALTER TABLE users ADD UNIQUE KEY uq_users_email (email)');
        } catch (PDOException $e) {
            // Never break bootstrap on runtime migration mismatch.
            error_log('Failed to add users email unique key at startup: ' . $e->getMessage());
        }
    }
}

function ensure_user_status_schema(PDO $pdo): void
{
    $statusColStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'status'");
    $statusColumn = $statusColStmt->fetch();

    if (!$statusColumn) {
        $pdo->exec("ALTER TABLE users ADD COLUMN status ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER role");
        return;
    }

    $statusType = strtolower((string) ($statusColumn['Type'] ?? ''));
    if (!str_contains($statusType, "'inactive'")) {
        $pdo->exec("ALTER TABLE users MODIFY COLUMN status ENUM('active','inactive') NOT NULL DEFAULT 'active'");
    }
}

function ensure_loan_status_schema(PDO $pdo): void
{
    $statusColStmt = $pdo->query("SHOW COLUMNS FROM loans LIKE 'status'");
    $statusColumn = $statusColStmt->fetch();

    if (!$statusColumn) {
        $pdo->exec("ALTER TABLE loans ADD COLUMN status ENUM('active','closed') NOT NULL DEFAULT 'active'");
        return;
    }

    $pdo->exec("UPDATE loans SET status = 'active' WHERE status NOT IN ('active', 'closed')");

    $statusType = strtolower((string) ($statusColumn['Type'] ?? ''));
    if (str_contains($statusType, "'defaulted'") || !str_contains($statusType, "'closed'")) {
        $pdo->exec("ALTER TABLE loans MODIFY COLUMN status ENUM('active','closed') NOT NULL DEFAULT 'active'");
    }
}

function ensure_user_force_logout_schema(PDO $pdo): void
{
    $forceLogoutColStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'force_logout_at'");
    if (!$forceLogoutColStmt->fetch()) {
        $pdo->exec('ALTER TABLE users ADD COLUMN force_logout_at DATETIME NULL AFTER status');
    }
}

function ensure_user_theme_schema(PDO $pdo): void
{
    $themeColStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'theme_preference'");
    if (!$themeColStmt->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN theme_preference ENUM('dark','light') NOT NULL DEFAULT 'dark' AFTER avatar_path");
    }
}

function ensure_password_reset_tokens_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            requested_ip VARCHAR(45) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_password_reset_tokens_token_hash (token_hash),
            INDEX idx_password_reset_tokens_user_id (user_id),
            INDEX idx_password_reset_tokens_expires_at (expires_at),
            CONSTRAINT fk_password_reset_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )"
    );
}

function ensure_remember_tokens_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS remember_tokens (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            user_agent VARCHAR(255) NULL,
            ip_address VARCHAR(45) NULL,
            last_used_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_remember_tokens_token_hash (token_hash),
            INDEX idx_remember_tokens_user_id (user_id),
            INDEX idx_remember_tokens_expires_at (expires_at),
            CONSTRAINT fk_remember_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )"
    );
}

function ensure_user_profile_schema(PDO $pdo): void
{
    $colStmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'avatar_path'");
    $col = $colStmt->fetch();

    if (!$col) {
        $pdo->exec('ALTER TABLE users ADD COLUMN avatar_path VARCHAR(255) NULL AFTER role');
    }
}

function ensure_collection_user_schema(PDO $pdo): void
{
    $colStmt = $pdo->query("SHOW COLUMNS FROM collections LIKE 'collected_by_user_id'");
    $col = $colStmt->fetch();

    if (!$col) {
        $pdo->exec('ALTER TABLE collections ADD COLUMN collected_by_user_id INT NULL AFTER note');
        $pdo->exec('ALTER TABLE collections ADD INDEX idx_collections_collected_by_user (collected_by_user_id)');
    }
}

function ensure_collection_payment_ref_schema(PDO $pdo): void
{
    $colStmt = $pdo->query("SHOW COLUMNS FROM collections LIKE 'payment_ref'");
    $col = $colStmt->fetch();

    if (!$col) {
        $pdo->exec('ALTER TABLE collections ADD COLUMN payment_ref VARCHAR(50) NULL AFTER collected_by_user_id');
        $pdo->exec('ALTER TABLE collections ADD INDEX idx_collections_payment_ref (payment_ref)');
    }
}

function ensure_flexible_collection_schema(PDO $pdo): void
{
    $metaColStmt = $pdo->query("SHOW COLUMNS FROM collections LIKE 'meta_json'");
    if (!$metaColStmt->fetch()) {
        $pdo->exec('ALTER TABLE collections ADD COLUMN meta_json LONGTEXT NULL AFTER payment_ref');
    }

    $flexColStmt = $pdo->query("SHOW COLUMNS FROM loan_installments LIKE 'is_flexible_adjustment'");
    if (!$flexColStmt->fetch()) {
        $pdo->exec('ALTER TABLE loan_installments ADD COLUMN is_flexible_adjustment TINYINT(1) NOT NULL DEFAULT 0 AFTER status');
    }

    $sourceColStmt = $pdo->query("SHOW COLUMNS FROM loan_installments LIKE 'source_payment_ref'");
    if (!$sourceColStmt->fetch()) {
        $pdo->exec('ALTER TABLE loan_installments ADD COLUMN source_payment_ref VARCHAR(50) NULL AFTER is_flexible_adjustment');
    }

    $indexStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.statistics
         WHERE table_schema = DATABASE()
           AND table_name = 'loan_installments'
           AND index_name = 'idx_loan_installments_flexible'"
    );
    $indexStmt->execute();
    if ((int) $indexStmt->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE loan_installments ADD INDEX idx_loan_installments_flexible (loan_id, is_flexible_adjustment, installment_no)');
    }
}

function repair_loan_installment_counts_from_history(PDO $pdo): void
{
    $maxByLoan = [];

    $currentRows = $pdo->query(
        'SELECT loan_id, MAX(installment_no) AS max_installment_no
         FROM loan_installments
         GROUP BY loan_id'
    )->fetchAll();

    foreach ($currentRows as $row) {
        $loanId = (int) ($row['loan_id'] ?? 0);
        $maxNo = (int) ($row['max_installment_no'] ?? 0);
        if ($loanId > 0 && $maxNo > 0) {
            $maxByLoan[$loanId] = max($maxByLoan[$loanId] ?? 0, $maxNo);
        }
    }

    $metaRows = $pdo->query(
        "SELECT loan_id, meta_json
         FROM collections
         WHERE meta_json IS NOT NULL
           AND meta_json <> ''"
    )->fetchAll();

    foreach ($metaRows as $row) {
        $loanId = (int) ($row['loan_id'] ?? 0);
        if ($loanId <= 0) {
            continue;
        }

        $meta = json_decode((string) ($row['meta_json'] ?? ''), true);
        if (!is_array($meta) || !isset($meta['installment_snapshots']) || !is_array($meta['installment_snapshots'])) {
            continue;
        }

        foreach ($meta['installment_snapshots'] as $snapshot) {
            if (!is_array($snapshot)) {
                continue;
            }

            $data = isset($snapshot['data']) && is_array($snapshot['data']) ? $snapshot['data'] : $snapshot;
            $installmentNo = (int) ($data['installment_no'] ?? 0);
            if ($installmentNo > 0) {
                $maxByLoan[$loanId] = max($maxByLoan[$loanId] ?? 0, $installmentNo);
            }
        }
    }

    if ($maxByLoan === []) {
        return;
    }

    $updateStmt = $pdo->prepare(
        'UPDATE loans
         SET installment_count = :installment_count
         WHERE id = :loan_id
           AND installment_count < :installment_count_check'
    );

    foreach ($maxByLoan as $loanId => $maxNo) {
        $updateStmt->execute([
            'installment_count' => $maxNo,
            'installment_count_check' => $maxNo,
            'loan_id' => $loanId,
        ]);
    }
}

function ensure_routes_schema(PDO $pdo): void
{
    safe_exec_schema($pdo, "CREATE TABLE IF NOT EXISTS routes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tenant_id INT NOT NULL,
        name VARCHAR(120) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_routes_tenant_name (tenant_id, name),
        INDEX idx_routes_tenant_id (tenant_id),
        FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
    )");

    if (!table_has_column($pdo, 'loans', 'route_id')) {
        safe_exec_schema($pdo, 'ALTER TABLE loans ADD COLUMN route_id INT NULL AFTER customer_id');
    }

    if (!table_has_index($pdo, 'loans', 'idx_loans_route')) {
        safe_exec_schema($pdo, 'ALTER TABLE loans ADD INDEX idx_loans_route (route_id)');
    }

    if (!table_has_index($pdo, 'loans', 'idx_loans_tenant_route')) {
        safe_exec_schema($pdo, 'ALTER TABLE loans ADD INDEX idx_loans_tenant_route (tenant_id, route_id)');
    }

    $routeFkStmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'loans'
           AND COLUMN_NAME = 'route_id'
           AND REFERENCED_TABLE_NAME = 'routes'
           AND REFERENCED_COLUMN_NAME = 'id'"
    );
    $routeFkStmt->execute();
    if ((int) $routeFkStmt->fetchColumn() === 0) {
        safe_exec_schema($pdo, 'ALTER TABLE loans ADD CONSTRAINT fk_loans_route_id FOREIGN KEY (route_id) REFERENCES routes(id)');
    }

    $routePermissionKeys = ['routes.view', 'routes.create', 'routes.edit', 'routes.delete'];
    $userRows = $pdo->query("SELECT id FROM users WHERE role IN ('superadmin', 'owner', 'manager', 'admin')")->fetchAll();
    $existingStmt = $pdo->prepare('SELECT COUNT(*) FROM user_permissions WHERE user_id = :user_id AND permission_key IN (\'routes.view\', \'routes.create\', \'routes.edit\', \'routes.delete\')');
    $insertStmt = $pdo->prepare(
        'INSERT INTO user_permissions (user_id, permission_key, allowed)
         VALUES (:user_id, :permission_key, 1)
         ON DUPLICATE KEY UPDATE allowed = VALUES(allowed)'
    );

    foreach ($userRows as $userRow) {
        $userId = (int) ($userRow['id'] ?? 0);
        if ($userId <= 0) {
            continue;
        }

        $existingStmt->execute(['user_id' => $userId]);
        if ((int) $existingStmt->fetchColumn() > 0) {
            continue;
        }

        foreach ($routePermissionKeys as $permissionKey) {
            $insertStmt->execute([
                'user_id' => $userId,
                'permission_key' => $permissionKey,
            ]);
        }
    }
}
function ensure_loan_assignment_schema(PDO $pdo): void
{
    $colStmt = $pdo->query("SHOW COLUMNS FROM loans LIKE 'assigned_user_id'");
    $col = $colStmt->fetch();

    if (!$col) {
        $pdo->exec('ALTER TABLE loans ADD COLUMN assigned_user_id INT NULL AFTER customer_id');
        $pdo->exec('ALTER TABLE loans ADD INDEX idx_loans_assigned_user (assigned_user_id)');
    }
}

function ensure_loan_issued_date_schema(PDO $pdo): void
{
    $colStmt = $pdo->query("SHOW COLUMNS FROM loans LIKE 'issued_date'");
    $col = $colStmt->fetch();

    if (!$col) {
        $pdo->exec('ALTER TABLE loans ADD COLUMN issued_date DATE NULL AFTER assigned_user_id');
        $pdo->exec('UPDATE loans SET issued_date = COALESCE(start_date, DATE(created_at)) WHERE issued_date IS NULL');
    }
}

function loan_original_schedule_end_date(PDO $pdo, int $loanId): ?string
{
    if ($loanId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT MAX(due_date)
         FROM loan_installments
         WHERE loan_id = :loan_id
           ' . tenant_where_clause() . '
           AND COALESCE(is_flexible_adjustment, 0) = 0'
    );
    $stmt->execute(tenant_scope_params(['loan_id' => $loanId]));
    $endDate = $stmt->fetchColumn();

    return is_string($endDate) && $endDate !== '' ? $endDate : null;
}

function sync_loan_planned_end_date(PDO $pdo, int $loanId): ?string
{
    $endDate = loan_original_schedule_end_date($pdo, $loanId);
    if ($endDate === null) {
        return null;
    }

    $stmt = $pdo->prepare('UPDATE loans SET end_date = :end_date WHERE id = :loan_id' . tenant_where_clause());
    $stmt->execute(tenant_scope_params([
        'end_date' => $endDate,
        'loan_id' => $loanId,
    ]));

    return $endDate;
}

function ensure_loan_end_date_schema(PDO $pdo): void
{
    $colStmt = $pdo->query("SHOW COLUMNS FROM loans LIKE 'end_date'");
    $col = $colStmt->fetch();

    if (!$col) {
        $pdo->exec('ALTER TABLE loans ADD COLUMN end_date DATE NULL AFTER first_due_date');
    }

    $stmt = $pdo->query(
        'SELECT id
         FROM loans
         WHERE end_date IS NULL'
    );
    $loanIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($loanIds as $loanId) {
        sync_loan_planned_end_date($pdo, (int) $loanId);
    }
}

function ensure_loan_interest_rate_type_schema(PDO $pdo): void
{
    $colStmt = $pdo->query("SHOW COLUMNS FROM loans LIKE 'interest_rate_type'");
    $col = $colStmt->fetch();

    if (!$col) {
        $pdo->exec("ALTER TABLE loans ADD COLUMN interest_rate_type ENUM('amount_based','monthly') NOT NULL DEFAULT 'amount_based' AFTER interest_rate");
        $pdo->exec("UPDATE loans SET interest_rate_type = 'amount_based' WHERE interest_rate_type IS NULL OR interest_rate_type = ''");
    }
}

function ensure_loan_interest_rate_months_schema(PDO $pdo): void
{
    $colStmt = $pdo->query("SHOW COLUMNS FROM loans LIKE 'interest_rate_months'");
    $col = $colStmt->fetch();

    if (!$col) {
        $pdo->exec('ALTER TABLE loans ADD COLUMN interest_rate_months INT NOT NULL DEFAULT 1 AFTER interest_rate_type');
        $pdo->exec('UPDATE loans SET interest_rate_months = 1 WHERE interest_rate_months IS NULL OR interest_rate_months < 1');
    }
}

function ensure_customer_documents_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS customer_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            stored_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            file_size INT UNSIGNED NOT NULL,
            uploaded_by_user_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_customer_documents_customer_id (customer_id),
            INDEX idx_customer_documents_uploaded_by (uploaded_by_user_id),
            CONSTRAINT fk_customer_documents_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
            CONSTRAINT fk_customer_documents_uploaded_by FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id) ON DELETE SET NULL
        )"
    );
}

function ensure_customer_note_schema(PDO $pdo): void
{
    $colStmt = $pdo->query("SHOW COLUMNS FROM customers LIKE 'note'");
    $col = $colStmt->fetch();

    if (!$col) {
        $pdo->exec('ALTER TABLE customers ADD COLUMN note TEXT NULL AFTER address');
    }
}

function ensure_system_settings_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS system_settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            updated_by_user_id INT NULL,
            INDEX idx_system_settings_updated_by (updated_by_user_id),
            CONSTRAINT fk_system_settings_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
        )"
    );

    $pdo->exec(
        "UPDATE system_settings
         SET setting_value = 'd/m/Y'
         WHERE setting_key = 'date_format'
           AND (setting_value IS NULL OR setting_value = '' OR setting_value = 'd M Y')"
    );
}

function ensure_holidays_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS holidays (
            id INT AUTO_INCREMENT PRIMARY KEY,
            holiday_date DATE NOT NULL,
            note VARCHAR(255) NULL,
            created_by_user_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_holidays_holiday_date (holiday_date),
            INDEX idx_holidays_created_by (created_by_user_id),
            CONSTRAINT fk_holidays_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
        )"
    );
}

function holiday_exists(PDO $pdo, string $date): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM holidays WHERE holiday_date = :holiday_date AND ' . tenant_scope_sql() . ' LIMIT 1');
    $stmt->execute(tenant_scope_params(['holiday_date' => $date]));

    return (bool) $stmt->fetchColumn();
}

function holiday_date_list(PDO $pdo): array
{
    $stmt = $pdo->prepare('SELECT holiday_date FROM holidays WHERE ' . tenant_scope_sql() . ' ORDER BY holiday_date ASC');
    $stmt->execute(tenant_scope_params());
    $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

    return array_values(array_filter(array_map('strval', $dates)));
}

function next_collectible_date(PDO $pdo, string $date): string
{
    $dateObj = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
        return $date;
    }

    for ($guard = 0; $guard < 366; $guard++) {
        $candidate = $dateObj->format('Y-m-d');
        if (!holiday_exists($pdo, $candidate)) {
            return $candidate;
        }

        $dateObj = $dateObj->add(new DateInterval('P1D'));
    }

    throw new RuntimeException('Could not find the next available collection date.');
}

function holiday_marking_validation(PDO $pdo, string $date): array
{
    $dateObj = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
        return ['allowed' => false, 'message' => 'Invalid holiday date.'];
    }

    if (holiday_exists($pdo, $date)) {
        return ['allowed' => false, 'message' => 'Holiday mode is already enabled for this date.'];
    }

    $todayDate = today();
    if ($date > $todayDate) {
        return ['allowed' => true, 'message' => ''];
    }

    if ($date === $todayDate) {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM collections
             WHERE collected_on = :today_date
               AND ' . tenant_scope_sql()
        );
        $stmt->execute(tenant_scope_params(['today_date' => $todayDate]));

        if ((int) $stmt->fetchColumn() > 0) {
            return ['allowed' => false, 'message' => 'Cannot mark today as a holiday because collections already exist today.'];
        }

        return ['allowed' => true, 'message' => ''];
    }

    $endDate = $date;
    if ($date < $todayDate) {
        $endDate = (new DateTimeImmutable($todayDate))->sub(new DateInterval('P1D'))->format('Y-m-d');
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM collections
         WHERE collected_on BETWEEN :start_date AND :end_date
           AND ' . tenant_scope_sql()
    );
    $stmt->execute(tenant_scope_params([
        'start_date' => $date,
        'end_date' => $endDate,
    ]));

    if ((int) $stmt->fetchColumn() > 0) {
        if ($date === $todayDate) {
            return ['allowed' => false, 'message' => 'Cannot mark today as a holiday because collections already exist today.'];
        }

        return ['allowed' => false, 'message' => 'Cannot mark this past date as a holiday because collections exist between that date and today.'];
    }

    return ['allowed' => true, 'message' => ''];
}

function installment_status_for_due_date(string $dueDate, float $dueAmount, float $paidAmount): string
{
    if ($paidAmount >= $dueAmount && $dueAmount > 0) {
        return 'paid';
    }

    if ($paidAmount > 0 && $paidAmount < $dueAmount) {
        return 'partial';
    }

    return $dueDate < today() ? 'overdue' : 'pending';
}

function shift_installments_for_holiday(PDO $pdo, string $holidayDate): int
{
    $stmt = $pdo->prepare(
        "SELECT li.*
         FROM loan_installments li
         JOIN loans l ON l.id = li.loan_id
         WHERE l.status = 'active'
           AND li.status IN ('pending', 'partial', 'overdue')
           AND li.due_amount > li.paid_amount
           AND li.due_date >= :holiday_date
           AND " . tenant_scope_sql('l') . "
         ORDER BY li.loan_id ASC, li.due_date ASC, li.installment_no ASC
         FOR UPDATE"
    );
    $stmt->execute(tenant_scope_params(['holiday_date' => $holidayDate]));
    $rows = $stmt->fetchAll();

    if ($rows === []) {
        return 0;
    }

    $updateStmt = $pdo->prepare(
        'UPDATE loan_installments
         SET due_date = :due_date,
             status = :status
         WHERE id = :id
           AND ' . tenant_scope_sql()
    );

    $shifted = 0;
    $affectedLoanIds = [];
    foreach ($rows as $row) {
        $currentDue = (string) ($row['due_date'] ?? '');
        $currentDueObj = DateTimeImmutable::createFromFormat('Y-m-d', $currentDue);
        if (!$currentDueObj || $currentDueObj->format('Y-m-d') !== $currentDue) {
            continue;
        }

        $newDueDate = next_collectible_date($pdo, $currentDueObj->add(new DateInterval('P1D'))->format('Y-m-d'));
        $dueAmount = round((float) ($row['due_amount'] ?? 0), 2);
        $paidAmount = round((float) ($row['paid_amount'] ?? 0), 2);

        $updateStmt->execute(tenant_scope_params([
            'due_date' => $newDueDate,
            'status' => installment_status_for_due_date($newDueDate, $dueAmount, $paidAmount),
            'id' => (int) $row['id'],
        ]));
        $affectedLoanIds[(int) $row['loan_id']] = true;
        $shifted++;
    }

    foreach (array_keys($affectedLoanIds) as $loanId) {
        sync_loan_planned_end_date($pdo, (int) $loanId);
    }

    return $shifted;
}

function mark_holiday_date(PDO $pdo, string $date, ?string $note, int $userId): array
{
    $date = trim($date);
    $note = trim((string) $note);
    $validation = holiday_marking_validation($pdo, $date);
    if (!($validation['allowed'] ?? false)) {
        throw new RuntimeException((string) ($validation['message'] ?? 'Holiday date is not allowed.'));
    }

    $pdo->beginTransaction();
    try {
        $insertStmt = $pdo->prepare(
            'INSERT INTO holidays (tenant_id, holiday_date, note, created_by_user_id)
             VALUES (:tenant_id, :holiday_date, :note, :created_by_user_id)'
        );
        $insertStmt->execute(tenant_scope_params([
            'holiday_date' => $date,
            'note' => $note === '' ? null : $note,
            'created_by_user_id' => $userId > 0 ? $userId : null,
        ]));

        $shiftedCount = shift_installments_for_holiday($pdo, $date);
        $pdo->commit();

        return [
            'holiday_date' => $date,
            'shifted_count' => $shiftedCount,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

function holiday_history(PDO $pdo, int $limit = 100): array
{
    $limit = max(1, min($limit, 500));
    $stmt = $pdo->prepare(
        "SELECT h.*, u.full_name AS created_by_name
         FROM holidays h
         LEFT JOIN users u ON u.id = h.created_by_user_id
         WHERE " . tenant_scope_sql('h') . "
         ORDER BY h.holiday_date DESC
         LIMIT {$limit}"
    );
    $stmt->execute(tenant_scope_params());

    return $stmt->fetchAll();
}

function ensure_activity_logs_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS activity_logs (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            actor_user_id INT NULL,
            actor_role VARCHAR(20) NULL,
            action_key VARCHAR(80) NOT NULL,
            description VARCHAR(255) NOT NULL,
            meta_json LONGTEXT NULL,
            ip_address VARCHAR(45) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_activity_logs_created_at (created_at),
            INDEX idx_activity_logs_actor (actor_user_id),
            INDEX idx_activity_logs_action_key (action_key),
            CONSTRAINT fk_activity_logs_actor_user FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
        )"
    );
}

function system_settings_all(PDO $pdo): array
{
    $stmt = $pdo->prepare('SELECT setting_key, setting_value FROM system_settings WHERE tenant_id = :tenant_id');
    $stmt->execute(['tenant_id' => tenant_setting_scope_id()]);
    $rows = $stmt->fetchAll();
    $map = [];
    foreach ($rows as $row) {
        $map[(string) $row['setting_key']] = (string) ($row['setting_value'] ?? '');
    }
    return $map;
}

function system_setting(PDO $pdo, string $key, string $default = ''): string
{
    $stmt = $pdo->prepare('SELECT setting_value FROM system_settings WHERE tenant_id = :tenant_id AND setting_key = :setting_key LIMIT 1');
    $stmt->execute([
        'tenant_id' => tenant_setting_scope_id(),
        'setting_key' => $key,
    ]);
    $value = $stmt->fetchColumn();
    return $value === false || $value === null ? $default : (string) $value;
}

function system_settings_save(PDO $pdo, array $settings, ?int $updatedByUserId = null): void
{
    if (empty($settings)) {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO system_settings (tenant_id, setting_key, setting_value, updated_by_user_id)
         VALUES (:tenant_id, :setting_key, :setting_value, :updated_by_user_id)
         ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            updated_by_user_id = VALUES(updated_by_user_id)'
    );

    foreach ($settings as $key => $value) {
        $stmt->bindValue(':tenant_id', tenant_setting_scope_id(), PDO::PARAM_INT);
        $stmt->bindValue(':setting_key', (string) $key, PDO::PARAM_STR);
        $stmt->bindValue(':setting_value', (string) $value, PDO::PARAM_STR);
        $stmt->bindValue(
            ':updated_by_user_id',
            $updatedByUserId !== null && $updatedByUserId > 0 ? $updatedByUserId : null,
            $updatedByUserId !== null && $updatedByUserId > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL
        );
        $stmt->execute();
    }
}

function normalize_uploaded_files(?array $filesInput): array
{
    if ($filesInput === null || !isset($filesInput['name'])) {
        return [];
    }

    if (!is_array($filesInput['name'])) {
        return [[
            'name' => (string) ($filesInput['name'] ?? ''),
            'type' => (string) ($filesInput['type'] ?? ''),
            'tmp_name' => (string) ($filesInput['tmp_name'] ?? ''),
            'error' => (int) ($filesInput['error'] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int) ($filesInput['size'] ?? 0),
        ]];
    }

    $files = [];
    foreach ($filesInput['name'] as $i => $name) {
        $files[] = [
            'name' => (string) $name,
            'type' => (string) ($filesInput['type'][$i] ?? ''),
            'tmp_name' => (string) ($filesInput['tmp_name'][$i] ?? ''),
            'error' => (int) ($filesInput['error'][$i] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int) ($filesInput['size'][$i] ?? 0),
        ];
    }

    return $files;
}

function customer_documents_upload_dir_abs(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'customer_docs';
}

function customer_documents_upload_dir_rel(): string
{
    return 'uploads/customer_docs';
}

function has_uploaded_files(?array $filesInput): bool
{
    foreach (normalize_uploaded_files($filesInput) as $file) {
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            return true;
        }
    }

    return false;
}

function business_icon_upload_dir_abs(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'business_icons';
}

function business_icon_upload_dir_rel(): string
{
    return 'uploads/business_icons';
}

function business_icon_path(PDO $pdo): string
{
    $path = trim(system_setting($pdo, 'business_icon_path', ''));
    if ($path === '') {
        return '';
    }

    $normalized = str_replace('\\', '/', $path);
    $requiredPrefix = business_icon_upload_dir_rel() . '/';
    if (!str_starts_with(strtolower($normalized), strtolower($requiredPrefix))) {
        return '';
    }

    $absPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
    if (!is_file($absPath)) {
        return '';
    }

    return $normalized;
}

function ensure_customer_docs_guard_file(string $uploadDirAbs): void
{
    $guardPath = $uploadDirAbs . DIRECTORY_SEPARATOR . '.htaccess';
    if (is_file($guardPath)) {
        return;
    }

    $guardContents = "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n";
    @file_put_contents($guardPath, $guardContents);
}

function store_customer_documents(PDO $pdo, int $customerId, string $customerCode, ?array $filesInput, int $uploadedByUserId = 0): int
{
    $files = normalize_uploaded_files($filesInput);
    if (empty($files)) {
        return 0;
    }

    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'application/pdf' => 'pdf',
    ];
    $maxBytes = 10 * 1024 * 1024; // 10MB per file

    $uploadDirAbs = customer_documents_upload_dir_abs();
    if (!is_dir($uploadDirAbs) && !mkdir($uploadDirAbs, 0775, true) && !is_dir($uploadDirAbs)) {
        throw new RuntimeException('Failed to create upload directory.');
    }
    ensure_customer_docs_guard_file($uploadDirAbs);

    $insertStmt = $pdo->prepare(
        'INSERT INTO customer_documents (tenant_id, customer_id, original_name, stored_name, file_path, mime_type, file_size, uploaded_by_user_id)
         VALUES (:tenant_id, :customer_id, :original_name, :stored_name, :file_path, :mime_type, :file_size, :uploaded_by_user_id)'
    );

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $movedAbsPaths = [];
    $storedCount = 0;

    try {
        foreach ($files as $file) {
            $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($error !== UPLOAD_ERR_OK) {
                throw new RuntimeException('One or more documents failed to upload.');
            }

            $tmpName = (string) ($file['tmp_name'] ?? '');
            $size = (int) ($file['size'] ?? 0);
            if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                throw new RuntimeException('Invalid uploaded file.');
            }

            if ($size <= 0 || $size > $maxBytes) {
                throw new RuntimeException('Each document must be between 1 byte and 10MB.');
            }

            $mime = (string) $finfo->file($tmpName);
            $ext = $allowedMimes[$mime] ?? null;
            if ($ext === null) {
                throw new RuntimeException('Only image files (JPG, PNG, WEBP, GIF) and PDF are allowed.');
            }

            $originalName = trim((string) ($file['name'] ?? ''));
            $originalName = $originalName === '' ? ('document.' . $ext) : basename($originalName);

            $baseName = pathinfo($originalName, PATHINFO_FILENAME);
            $safeBase = preg_replace('/[^\w\-. ]+/u', '_', $baseName) ?: 'document';
            $safeBase = trim((string) $safeBase);
            $safeExt = $ext;

            $candidateName = $safeBase . '.' . $safeExt;
            $absPath = $uploadDirAbs . DIRECTORY_SEPARATOR . $candidateName;

            if (is_file($absPath)) {
                try {
                    $random = bin2hex(random_bytes(4));
                } catch (Throwable) {
                    $random = str_replace('.', '', uniqid('', true));
                }
                $safeCode = preg_replace('/[^A-Za-z0-9_-]/', '', $customerCode) ?: 'CUST';
                $candidateName = $safeBase . '_' . strtolower($safeCode) . '_' . date('YmdHis') . '_' . $random . '.' . $safeExt;
                $absPath = $uploadDirAbs . DIRECTORY_SEPARATOR . $candidateName;
            }

            $storedName = $candidateName;
            $relPath = customer_documents_upload_dir_rel() . '/' . $storedName;

            if (!move_uploaded_file($tmpName, $absPath)) {
                throw new RuntimeException('Failed to store uploaded document.');
            }
            $movedAbsPaths[] = $absPath;

            $tenantId = current_tenant_id() ?? 0;
            if ($tenantId <= 0) {
                throw new RuntimeException('Missing tenant context for document upload.');
            }

            $insertStmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
            $insertStmt->bindValue(':customer_id', $customerId, PDO::PARAM_INT);
            $insertStmt->bindValue(':original_name', $originalName, PDO::PARAM_STR);
            $insertStmt->bindValue(':stored_name', $storedName, PDO::PARAM_STR);
            $insertStmt->bindValue(':file_path', $relPath, PDO::PARAM_STR);
            $insertStmt->bindValue(':mime_type', $mime, PDO::PARAM_STR);
            $insertStmt->bindValue(':file_size', $size, PDO::PARAM_INT);
            $insertStmt->bindValue(':uploaded_by_user_id', $uploadedByUserId > 0 ? $uploadedByUserId : null, $uploadedByUserId > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $insertStmt->execute();

            $storedCount++;
        }
    } catch (Throwable $e) {
        foreach ($movedAbsPaths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        throw $e;
    }

    return $storedCount;
}

function readable_file_size(int $bytes): string
{
    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / (1024 * 1024), 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' B';
}

function superadmin_count(PDO $pdo): int
{
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'superadmin' AND tenant_id IS NULL");
    return (int) $stmt->fetchColumn();
}

function has_superadmin(PDO $pdo): bool
{
    return superadmin_count($pdo) > 0;
}

function current_user(): ?array
{
    return $_SESSION['auth_user'] ?? null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function authenticated_landing_path(?array $viewer = null): string
{
    $viewer = $viewer ?? current_user();

    if (is_owner($viewer)) {
        return 'pages/tenants.php';
    }

    if (can('dashboard.view', $viewer)) {
        return 'index.php';
    }

    if (can('today_collections.view', $viewer)) {
        return 'pages/today_collections.php';
    }

    return 'pages/about.php';
}

function normalize_theme_preference(string $theme): string
{
    return $theme === 'light' ? 'light' : 'dark';
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['auth_login_at'] = time();
    $_SESSION['auth_user'] = [
        'id' => (int) $user['id'],
        'tenant_id' => isset($user['tenant_id']) ? (int) $user['tenant_id'] : null,
        'tenant_name' => (string) ($user['tenant_name'] ?? ''),
        'tenant_status' => (string) ($user['tenant_status'] ?? ''),
        'full_name' => (string) $user['full_name'],
        'username' => (string) $user['username'],
        'email' => isset($user['email']) ? (string) $user['email'] : '',
        'role' => (string) $user['role'],
        'status' => isset($user['status']) ? (string) $user['status'] : 'active',
        'avatar_path' => isset($user['avatar_path']) ? (string) $user['avatar_path'] : '',
        'theme_preference' => normalize_theme_preference((string) ($user['theme_preference'] ?? 'dark')),
    ];
}

function remember_cookie_name(): string
{
    return 'loan_remember_token';
}

function remember_lifetime_seconds(): int
{
    return 60 * 60 * 24 * 30;
}

function remember_clear_cookie(): void
{
    setcookie(remember_cookie_name(), '', [
        'expires' => time() - 3600,
        'path' => app_cookie_path(),
        'secure' => app_cookie_secure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[remember_cookie_name()]);
}

function remember_store_login(PDO $pdo, int $userId): void
{
    if ($userId <= 0) {
        return;
    }

    try {
        $rawToken = bin2hex(random_bytes(32));
    } catch (Throwable) {
        $rawToken = hash('sha256', uniqid('remember_', true) . mt_rand());
    }

    $expiresAt = (new DateTimeImmutable('now'))
        ->add(new DateInterval('PT' . remember_lifetime_seconds() . 'S'))
        ->format('Y-m-d H:i:s');
    $tokenHash = hash('sha256', $rawToken);
    $userAgent = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

    $pdo->prepare('DELETE FROM remember_tokens WHERE user_id = :user_id AND expires_at < NOW()')->execute(['user_id' => $userId]);
    $stmt = $pdo->prepare(
        'INSERT INTO remember_tokens (user_id, token_hash, expires_at, user_agent, ip_address)
         VALUES (:user_id, :token_hash, :expires_at, :user_agent, :ip_address)'
    );
    $stmt->execute([
        'user_id' => $userId,
        'token_hash' => $tokenHash,
        'expires_at' => $expiresAt,
        'user_agent' => $userAgent,
        'ip_address' => client_ip_address(),
    ]);

    setcookie(remember_cookie_name(), $rawToken, [
        'expires' => time() + remember_lifetime_seconds(),
        'path' => app_cookie_path(),
        'secure' => app_cookie_secure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[remember_cookie_name()] = $rawToken;
}

function remember_forget_current(PDO $pdo): void
{
    $rawToken = (string) ($_COOKIE[remember_cookie_name()] ?? '');
    if ($rawToken !== '') {
        $pdo->prepare('DELETE FROM remember_tokens WHERE token_hash = :token_hash')->execute([
            'token_hash' => hash('sha256', $rawToken),
        ]);
    }

    remember_clear_cookie();
}

function remember_forget_user(PDO $pdo, int $userId): void
{
    if ($userId <= 0) {
        return;
    }

    $pdo->prepare('DELETE FROM remember_tokens WHERE user_id = :user_id')->execute(['user_id' => $userId]);

    $current = current_user();
    if ((int) ($current['id'] ?? 0) === $userId) {
        remember_clear_cookie();
    }
}

function force_logout_user_everywhere(PDO $pdo, int $userId): void
{
    if ($userId <= 0) {
        return;
    }

    $pdo->prepare('UPDATE users SET force_logout_at = NOW() WHERE id = :id')->execute(['id' => $userId]);
    remember_forget_user($pdo, $userId);
}

function remember_login_from_cookie(PDO $pdo): bool
{
    if (is_logged_in()) {
        return true;
    }

    $rawToken = (string) ($_COOKIE[remember_cookie_name()] ?? '');
    if ($rawToken === '' || !ctype_xdigit($rawToken) || strlen($rawToken) !== 64) {
        if ($rawToken !== '') {
            remember_clear_cookie();
        }
        return false;
    }

    $pdo->prepare('DELETE FROM remember_tokens WHERE expires_at < NOW()')->execute();

    $stmt = $pdo->prepare(
        "SELECT rt.id AS remember_id, u.id, u.tenant_id, u.full_name, u.username, u.email, u.role, u.status, u.avatar_path, u.theme_preference,
                t.name AS tenant_name, t.status AS tenant_status
         FROM remember_tokens rt
         JOIN users u ON u.id = rt.user_id
         LEFT JOIN tenants t ON t.id = u.tenant_id
         WHERE rt.token_hash = :token_hash
           AND rt.expires_at > NOW()
         LIMIT 1"
    );
    $stmt->execute(['token_hash' => hash('sha256', $rawToken)]);
    $user = $stmt->fetch();

    if (
        !$user
        || (string) ($user['status'] ?? 'active') !== 'active'
        || (!is_owner($user) && !tenant_status_allows_access((string) ($user['tenant_status'] ?? '')))
    ) {
        remember_forget_current($pdo);
        return false;
    }

    login_user($user);
    $pdo->prepare('UPDATE remember_tokens SET last_used_at = NOW() WHERE id = :id')->execute([
        'id' => (int) $user['remember_id'],
    ]);

    return true;
}

function create_password_reset_token(PDO $pdo, int $userId): ?string
{
    if ($userId <= 0) {
        return null;
    }

    try {
        $token = bin2hex(random_bytes(32));
    } catch (Throwable) {
        return null;
    }
    $tokenHash = hash('sha256', $token);
    $expiresAt = (new DateTimeImmutable('now'))->add(new DateInterval('PT1H'))->format('Y-m-d H:i:s');
    $ip = mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);

    $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = :user_id')->execute(['user_id' => $userId]);
    $stmt = $pdo->prepare(
        'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at, requested_ip)
         VALUES (:user_id, :token_hash, :expires_at, :requested_ip)'
    );
    $stmt->execute([
        'user_id' => $userId,
        'token_hash' => $tokenHash,
        'expires_at' => $expiresAt,
        'requested_ip' => $ip !== '' ? $ip : null,
    ]);

    return $token;
}

function password_reset_row_by_token(PDO $pdo, string $rawToken): ?array
{
    $rawToken = trim($rawToken);
    if ($rawToken === '') {
        return null;
    }

    $tokenHash = hash('sha256', $rawToken);
    $stmt = $pdo->prepare(
        "SELECT prt.id, prt.user_id, prt.expires_at, prt.used_at, u.id AS u_id, u.full_name, u.username, u.email, u.status
         FROM password_reset_tokens prt
         JOIN users u ON u.id = prt.user_id
         WHERE prt.token_hash = :token_hash
         LIMIT 1"
    );
    $stmt->execute(['token_hash' => $tokenHash]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    if (!empty($row['used_at'])) {
        return null;
    }

    $expires = strtotime((string) $row['expires_at']);
    if ($expires === false || $expires < time()) {
        return null;
    }

    return $row;
}

function mark_password_reset_token_used(PDO $pdo, int $tokenId, int $userId): void
{
    if ($tokenId > 0) {
        $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = :id')->execute(['id' => $tokenId]);
    }
    if ($userId > 0) {
        $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = :user_id')->execute(['user_id' => $userId]);
    }
}

function app_mail_config_value(string $envKey, string $localKey, string $default = ''): string
{
    $env = trim(env_value($envKey, ''));
    if ($env !== '') {
        return $env;
    }

    if (defined('LOCAL_APP_CONFIG')) {
        $local = constant('LOCAL_APP_CONFIG');
        if (is_array($local) && array_key_exists($localKey, $local)) {
            $value = $local[$localKey];
            if (is_string($value) || is_int($value) || is_float($value)) {
                $str = trim((string) $value);
                if ($str !== '') {
                    return $str;
                }
            }
        }
    }

    return $default;
}

function app_version(): string
{
    if (defined('APP_VERSION')) {
        $value = trim((string) constant('APP_VERSION'));
        if ($value !== '') {
            return $value;
        }
    }

    return '1.1';
}

function smtp_write_line($socket, string $line): bool
{
    return fwrite($socket, $line . "\r\n") !== false;
}

function smtp_read_response($socket): string
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        // RFC-style multiline replies continue with "123-"
        if (preg_match('/^\d{3}\s/', $line) === 1) {
            break;
        }
    }

    return $response;
}

function smtp_expect_code(string $response, array $expected): bool
{
    if (preg_match('/^(\d{3})/m', $response, $matches) !== 1) {
        return false;
    }

    $code = (int) $matches[1];
    return in_array($code, $expected, true);
}

function smtp_send_mail_message(array $config, string $toEmail, string $subject, string $message, string $fromEmail, string $fromName): bool
{
    $host = trim((string) ($config['host'] ?? ''));
    $port = (int) ($config['port'] ?? 0);
    $encryption = strtolower(trim((string) ($config['encryption'] ?? '')));
    $username = trim((string) ($config['username'] ?? ''));
    $password = (string) ($config['password'] ?? '');

    if ($host === '' || $port <= 0 || $username === '' || $password === '') {
        return false;
    }

    $transportHost = $host;
    if ($encryption === 'ssl') {
        $transportHost = 'ssl://' . $host;
    }

    $socket = @stream_socket_client(
        $transportHost . ':' . $port,
        $errno,
        $errstr,
        20,
        STREAM_CLIENT_CONNECT
    );

    if (!is_resource($socket)) {
        error_log('SMTP connect failed: ' . $errstr . ' (' . $errno . ')');
        return false;
    }

    stream_set_timeout($socket, 20);

    try {
        $response = smtp_read_response($socket);
        if (!smtp_expect_code($response, [220])) {
            return false;
        }

        $ehloHost = preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
        if (!smtp_write_line($socket, 'EHLO ' . $ehloHost)) {
            return false;
        }
        $response = smtp_read_response($socket);
        if (!smtp_expect_code($response, [250])) {
            return false;
        }

        if ($encryption === 'tls') {
            if (!smtp_write_line($socket, 'STARTTLS')) {
                return false;
            }
            $response = smtp_read_response($socket);
            if (!smtp_expect_code($response, [220])) {
                return false;
            }
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                return false;
            }
            if (!smtp_write_line($socket, 'EHLO ' . $ehloHost)) {
                return false;
            }
            $response = smtp_read_response($socket);
            if (!smtp_expect_code($response, [250])) {
                return false;
            }
        }

        if (!smtp_write_line($socket, 'AUTH LOGIN')) {
            return false;
        }
        $response = smtp_read_response($socket);
        if (!smtp_expect_code($response, [334])) {
            return false;
        }

        if (!smtp_write_line($socket, base64_encode($username))) {
            return false;
        }
        $response = smtp_read_response($socket);
        if (!smtp_expect_code($response, [334])) {
            return false;
        }

        if (!smtp_write_line($socket, base64_encode($password))) {
            return false;
        }
        $response = smtp_read_response($socket);
        if (!smtp_expect_code($response, [235])) {
            return false;
        }

        if (!smtp_write_line($socket, 'MAIL FROM:<' . $fromEmail . '>')) {
            return false;
        }
        $response = smtp_read_response($socket);
        if (!smtp_expect_code($response, [250])) {
            return false;
        }

        if (!smtp_write_line($socket, 'RCPT TO:<' . $toEmail . '>')) {
            return false;
        }
        $response = smtp_read_response($socket);
        if (!smtp_expect_code($response, [250, 251])) {
            return false;
        }

        if (!smtp_write_line($socket, 'DATA')) {
            return false;
        }
        $response = smtp_read_response($socket);
        if (!smtp_expect_code($response, [354])) {
            return false;
        }

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $headers = [];
        $headers[] = 'Date: ' . date(DATE_RFC2822);
        $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
        $headers[] = 'To: <' . $toEmail . '>';
        $headers[] = 'Subject: ' . $encodedSubject;
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';

        $normalizedBody = str_replace(["\r\n", "\r"], "\n", $message);
        $normalizedBody = preg_replace('/^\./m', '..', $normalizedBody) ?? $normalizedBody;
        $payload = implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n", "\r\n", $normalizedBody) . "\r\n.";

        if (!smtp_write_line($socket, $payload)) {
            return false;
        }
        $response = smtp_read_response($socket);
        if (!smtp_expect_code($response, [250])) {
            return false;
        }

        smtp_write_line($socket, 'QUIT');
        return true;
    } catch (Throwable $e) {
        error_log('SMTP send exception: ' . $e->getMessage());
        return false;
    } finally {
        fclose($socket);
    }
}

function send_password_reset_email(PDO $pdo, string $toEmail, string $fullName, string $resetLink): bool
{
    $toEmail = trim($toEmail);
    if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $businessName = trim(system_setting($pdo, 'business_name', APP_NAME));
    $subject = $businessName . ' - Password Reset';

    $safeName = trim($fullName) !== '' ? trim($fullName) : 'User';
    $message = "Hello {$safeName},\n\n";
    $message .= "We received a request to reset your password.\n";
    $message .= "Use this secure link to set a new password:\n{$resetLink}\n\n";
    $message .= "This link will expire in 1 hour.\n";
    $message .= "If you did not request this, you can ignore this email.\n\n";
    $message .= "{$businessName}";

    $fromEmail = trim(app_mail_config_value('MAIL_FROM_EMAIL', 'mail_from_email', ''));
    if ($fromEmail === '' || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $host = preg_replace('/:\d+$/', '', $host) ?? 'localhost';
        $fromEmail = 'no-reply@' . $host;
    }

    $fromName = trim(app_mail_config_value('MAIL_FROM_NAME', 'mail_from_name', $businessName));
    $mailDriver = strtolower(app_mail_config_value('MAIL_DRIVER', 'mail_driver', 'mail'));

    if ($mailDriver === 'smtp') {
        $smtpConfig = [
            'host' => app_mail_config_value('MAIL_HOST', 'mail_host', ''),
            'port' => (int) app_mail_config_value('MAIL_PORT', 'mail_port', '465'),
            'encryption' => app_mail_config_value('MAIL_ENCRYPTION', 'mail_encryption', 'ssl'),
            'username' => app_mail_config_value('MAIL_USERNAME', 'mail_username', ''),
            'password' => app_mail_config_value('MAIL_PASSWORD', 'mail_password', ''),
        ];

        $smtpSent = smtp_send_mail_message($smtpConfig, $toEmail, $subject, $message, $fromEmail, $fromName);
        if ($smtpSent) {
            return true;
        }
        error_log('SMTP send failed for password reset email; falling back to mail().');
    }

    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
    $headers[] = 'Reply-To: ' . $fromEmail;

    return @mail($toEmail, $subject, $message, implode("\r\n", $headers));
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function log_activity(PDO $pdo, string $actionKey, string $description, array $meta = [], ?int $actorUserId = null): void
{
    try {
        $authUser = current_user();
        $userId = $actorUserId;
        if ($userId === null && isset($authUser['id'])) {
            $userId = (int) $authUser['id'];
        }

        $role = null;
        if (isset($authUser['role'])) {
            $role = (string) $authUser['role'];
        }

        $metaJson = $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($metaJson === false) {
            $metaJson = null;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO activity_logs (tenant_id, actor_user_id, actor_role, action_key, description, meta_json, ip_address)
             VALUES (:tenant_id, :actor_user_id, :actor_role, :action_key, :description, :meta_json, :ip_address)'
        );
        $tenantId = current_tenant_id($authUser);
        $stmt->bindValue(':tenant_id', $tenantId, $tenantId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':actor_user_id', $userId !== null && $userId > 0 ? $userId : null, $userId !== null && $userId > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':actor_role', $role, $role !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':action_key', mb_substr($actionKey, 0, 80), PDO::PARAM_STR);
        $stmt->bindValue(':description', mb_substr($description, 0, 255), PDO::PARAM_STR);
        $stmt->bindValue(':meta_json', $metaJson, $metaJson !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':ip_address', mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45), PDO::PARAM_STR);
        $stmt->execute();
    } catch (Throwable) {
        // Activity logging should never block business operations.
    }
}

function is_owner(?array $user = null): bool
{
    $user = $user ?? current_user();
    return (string) ($user['role'] ?? '') === 'superadmin' && current_tenant_id($user) === null;
}

function is_tenant_owner(?array $user = null, ?PDO $pdo = null): bool
{
    $user = $user ?? current_user();
    $role = (string) ($user['role'] ?? '');
    if (!$user || !in_array($role, ['owner', 'admin'], true)) {
        return false;
    }

    $tenantId = current_tenant_id($user);
    $userId = (int) ($user['id'] ?? 0);
    if ($tenantId === null || $userId <= 0) {
        return false;
    }

    if ($role === 'owner') {
        return true;
    }

    try {
        $pdo = $pdo ?? db();
        $stmt = $pdo->prepare(
            "SELECT id
             FROM users
             WHERE tenant_id = :tenant_id
               AND role IN ('owner', 'admin')
               AND status = 'active'
             ORDER BY FIELD(role, 'owner', 'admin'), id ASC
             LIMIT 1"
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return (int) ($stmt->fetchColumn() ?: 0) === $userId;
    } catch (Throwable) {
        return false;
    }
}
function user_permission_keys(PDO $pdo, int $userId): array
{
    if ($userId <= 0) {
        return [];
    }

    $stmt = $pdo->prepare('SELECT permission_key FROM user_permissions WHERE user_id = :user_id AND allowed = 1');
    $stmt->execute(['user_id' => $userId]);

    return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function sync_user_permissions(PDO $pdo, int $userId, array $permissionKeys): void
{
    if ($userId <= 0) {
        return;
    }

    $permissionKeys = normalize_permission_keys($permissionKeys);

    $deleteStmt = $pdo->prepare('DELETE FROM user_permissions WHERE user_id = :user_id');
    $deleteStmt->execute(['user_id' => $userId]);

    if ($permissionKeys === []) {
        return;
    }

    $insertStmt = $pdo->prepare(
        'INSERT INTO user_permissions (user_id, permission_key, allowed)
         VALUES (:user_id, :permission_key, 1)'
    );

    foreach ($permissionKeys as $key) {
        $insertStmt->execute([
            'user_id' => $userId,
            'permission_key' => $key,
        ]);
    }
}

function owner_user_id(PDO $pdo): int
{
    $tenantId = current_tenant_id();
    if ($tenantId !== null) {
        $stmt = $pdo->prepare(
            "SELECT id
             FROM users
             WHERE tenant_id = :tenant_id
               AND role IN ('owner', 'admin')
               AND status = 'active'
             ORDER BY FIELD(role, 'owner', 'admin'), id ASC
             LIMIT 1"
        );
        $stmt->execute(['tenant_id' => $tenantId]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    $stmt = $pdo->query(
        "SELECT id
         FROM users
         WHERE role = 'superadmin'
           AND tenant_id IS NULL
         ORDER BY id ASC
         LIMIT 1"
    );

    return (int) ($stmt->fetchColumn() ?: 0);
}

function render_permission_fields(array $selectedKeys, bool $disabled = false): void
{
    $selectedKeys = normalize_permission_keys($selectedKeys);
    $selected = array_flip(array_map('strval', $selectedKeys));
    $dependencies = permission_dependencies();
    ?>
    <div class="permission-panel" data-permission-panel data-permission-locked="<?= $disabled ? '1' : '0' ?>">
        <div class="permission-panel-head">
            <div>
                <h3>Module Permissions</h3>
                <p>Permissions decide actual access. Role is only the user label.</p>
            </div>
        </div>

        <?php foreach (permission_groups() as $groupTitle => $group): ?>
            <section class="permission-section">
                <div class="permission-section-head">
                    <div>
                        <h4><?= e((string) $groupTitle) ?></h4>
                    </div>
                </div>
                <div class="permission-grid">
                    <?php foreach ((array) ($group['permissions'] ?? []) as $permissionKey => $meta): ?>
                        <?php
                        $permissionKey = (string) $permissionKey;
                        $requiredKeys = array_map('strval', (array) ($dependencies[$permissionKey] ?? []));
                        $dependencyBlocked = false;
                        foreach ($requiredKeys as $requiredKey) {
                            if (!isset($selected[$requiredKey])) {
                                $dependencyBlocked = true;
                                break;
                            }
                        }
                        $isChecked = isset($selected[$permissionKey]) && !$dependencyBlocked;
                        $isDisabled = $disabled || $dependencyBlocked;
                        ?>
                        <label class="permission-check<?= $dependencyBlocked ? ' is-disabled' : '' ?>" data-permission-row>
                            <input
                                type="checkbox"
                                name="permissions[]"
                                value="<?= e($permissionKey) ?>"
                                data-permission-key="<?= e($permissionKey) ?>"
                                <?php if ($requiredKeys !== []): ?>
                                    data-permission-depends-on="<?= e(implode(' ', $requiredKeys)) ?>"
                                <?php endif; ?>
                                <?= $isChecked ? 'checked' : '' ?>
                                <?= $isDisabled ? 'disabled' : '' ?>
                            >
                            <span>
                                <strong><?= e((string) ($meta['label'] ?? $permissionKey)) ?></strong>
                                <?php if (!empty($meta['description'])): ?>
                                    <small><?= e((string) $meta['description']) ?></small>
                                <?php endif; ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>

    <script>
    (() => {
        document.querySelectorAll('[data-permission-panel]').forEach((panel) => {
            const isLocked = panel.dataset.permissionLocked === '1';
            const form = panel.closest('form');
            const inputs = Array.from(panel.querySelectorAll('input[data-permission-key]'));
            const byKey = new Map(inputs.map((input) => [input.dataset.permissionKey, input]));

            const syncDependentPermissions = () => {
                inputs.forEach((input) => {
                    const dependencies = (input.dataset.permissionDependsOn || '').split(' ').filter(Boolean);
                    const dependencyBlocked = dependencies.some((key) => !byKey.get(key)?.checked);
                    input.disabled = isLocked || dependencyBlocked;
                    input.closest('[data-permission-row]')?.classList.toggle('is-disabled', dependencyBlocked);
                    if (dependencyBlocked) {
                        input.checked = false;
                    }
                });
            };

            syncDependentPermissions();
            form?.addEventListener('change', () => {
                window.setTimeout(syncDependentPermissions, 0);
            });
        });
    })();
    </script>
    <?php
}
function can(string $permissionKey, ?array $viewer = null): bool
{
    $viewer = $viewer ?? current_user();
    if (!$viewer) {
        return false;
    }

    if ($permissionKey === 'backup.manage' && !is_owner($viewer)) {
        return false;
    }

    if ($permissionKey === 'system_settings.manage' && is_tenant_owner($viewer)) {
        return true;
    }

    if (is_owner($viewer)) {
        return true;
    }

    if (!in_array($permissionKey, permission_keys(), true)) {
        return false;
    }

    $userId = (int) ($viewer['id'] ?? 0);
    if ($userId <= 0) {
        return false;
    }

    static $cache = [];
    if (!array_key_exists($userId, $cache)) {
        try {
            $cache[$userId] = user_permission_keys(db(), $userId);
        } catch (Throwable) {
            $cache[$userId] = [];
        }
    }

    if (!in_array($permissionKey, $cache[$userId], true)) {
        return false;
    }

    static $checking = [];
    $checkingKey = $userId . ':' . $permissionKey;
    if (isset($checking[$checkingKey])) {
        return false;
    }

    $checking[$checkingKey] = true;
    foreach ((array) (permission_dependencies()[$permissionKey] ?? []) as $requiredKey) {
        if (!can((string) $requiredKey, $viewer)) {
            unset($checking[$checkingKey]);
            return false;
        }
    }
    unset($checking[$checkingKey]);

    return true;
}

function can_any(array $permissionKeys, ?array $viewer = null): bool
{
    foreach ($permissionKeys as $permissionKey) {
        if (can((string) $permissionKey, $viewer)) {
            return true;
        }
    }

    return false;
}

function is_collector_role(?string $role): bool
{
    return (string) $role === 'collector';
}


function route_options(PDO $pdo): array
{
    $stmt = $pdo->prepare('SELECT id, name FROM routes WHERE ' . tenant_scope_sql() . ' ORDER BY name ASC, id ASC');
    $stmt->execute(tenant_scope_params());

    return $stmt->fetchAll();
}

function route_exists(PDO $pdo, int $routeId): bool
{
    if ($routeId <= 0) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT 1 FROM routes WHERE id = :id AND ' . tenant_scope_sql() . ' LIMIT 1');
    $stmt->execute(tenant_scope_params(['id' => $routeId]));

    return (bool) $stmt->fetchColumn();
}

function route_name_exists(PDO $pdo, string $name, ?int $excludeRouteId = null): bool
{
    $name = trim($name);
    if ($name === '') {
        return false;
    }

    $sql = 'SELECT 1 FROM routes WHERE name = :name AND ' . tenant_scope_sql();
    $params = tenant_scope_params(['name' => $name]);
    if ($excludeRouteId !== null && $excludeRouteId > 0) {
        $sql .= ' AND id <> :exclude_id';
        $params['exclude_id'] = $excludeRouteId;
    }
    $sql .= ' LIMIT 1';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (bool) $stmt->fetchColumn();
}

function route_loan_count(PDO $pdo, int $routeId): int
{
    if ($routeId <= 0) {
        return 0;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM loans WHERE route_id = :route_id AND ' . tenant_scope_sql());
    $stmt->execute(tenant_scope_params(['route_id' => $routeId]));

    return (int) $stmt->fetchColumn();
}

function normalize_route_id(PDO $pdo, int $routeId): ?int
{
    return $routeId > 0 && route_exists($pdo, $routeId) ? $routeId : null;
}

function can_access_customer(PDO $pdo, int $customerId, ?array $viewer = null): bool
{
    if ($customerId <= 0) {
        return false;
    }

    $viewer = $viewer ?? current_user();
    if (!$viewer || !can('customers.view', $viewer)) {
        return false;
    }

    $stmt = $pdo->prepare("SELECT 1 FROM customers c WHERE c.id = :customer_id " . tenant_where_clause('c') . " LIMIT 1");
    $stmt->execute(['customer_id' => $customerId] + tenant_scope_params());

    return (bool) $stmt->fetchColumn();
}

function require_customer_access(PDO $pdo, int $customerId, string $redirectPath = 'pages/customers.php'): void
{
    if (!can_access_customer($pdo, $customerId)) {
        set_flash('error', 'You do not have permission to access that customer.');
        redirect($redirectPath);
    }
}

function require_permission(string $permissionKey, string $redirectPath = 'index.php'): void
{
    if (!can($permissionKey)) {
        set_flash('error', 'You do not have permission to access that page.');
        redirect($redirectPath);
    }
}

function today_collection_goal(PDO $pdo, ?array $viewer = null): array
{
    $viewerRole = (string) ($viewer['role'] ?? '');
    $viewerId = (int) ($viewer['id'] ?? 0);
    $todayDate = today();
    $tenantId = current_tenant_id($viewer);

    $remainingRows = collection_due_installments_for_date($pdo, $todayDate, $todayDate, '', $viewerRole, $viewerId);
    $remainingNow = 0.0;
    foreach ($remainingRows as $row) {
        $remainingNow += max(0.0, (float) ($row['due_amount'] ?? 0) - (float) ($row['paid_amount'] ?? 0));
    }

    $collectedTowardGoalToday = today_collected_total($pdo, $viewer);
    $scheduledTargetPaidToday = 0.0;

    $paidTargetSql = "SELECT
                c.id AS collection_id,
                c.installment_id,
                c.meta_json,
                li.due_amount,
                li.due_date
            FROM collections c
            LEFT JOIN loan_installments li ON li.id = c.installment_id
            JOIN loans l ON l.id = c.loan_id
            WHERE c.collected_on = :today
              AND li.due_date <= :today_due_date";
    $paidTargetParams = [
        'today' => $todayDate,
        'today_due_date' => $todayDate,
    ];

    if ($tenantId !== null) {
        $paidTargetSql .= ' AND l.tenant_id = :tenant_id';
        $paidTargetParams['tenant_id'] = $tenantId;
    }

    $paidTargetStmt = $pdo->prepare($paidTargetSql);
    $paidTargetStmt->execute($paidTargetParams);
    $seenInstallments = [];
    foreach ($paidTargetStmt->fetchAll() as $row) {
        $installmentId = (int) ($row['installment_id'] ?? 0);
        $key = $installmentId > 0 ? 'i_' . $installmentId : 'c_' . (int) ($row['collection_id'] ?? 0);
        if (isset($seenInstallments[$key])) {
            continue;
        }

        $meta = json_decode((string) ($row['meta_json'] ?? ''), true);
        $scheduledAmount = is_array($meta) ? (float) ($meta['original_selected_balance'] ?? 0) : 0.0;
        if ($scheduledAmount <= 0.009) {
            $scheduledAmount = (float) ($row['due_amount'] ?? 0);
        }

        $scheduledTargetPaidToday += max(0.0, $scheduledAmount);
        $seenInstallments[$key] = true;
    }

    // Target is the scheduled due amount for today, not the raw collected amount.
    // Overpayments increase collected total, but must not inflate the target.
    $target = $remainingNow + $scheduledTargetPaidToday;
    $collected = $collectedTowardGoalToday;
    $remaining = $remainingNow;
    $percentage = $target > 0 ? min(100, ($collected / $target) * 100) : 0;

    return [
        'target' => $target,
        'collected' => $collected,
        'remaining' => $remaining,
        'percentage' => $percentage,
    ];
}

function today_collected_total(PDO $pdo, ?array $viewer = null): float
{
    $todayDate = today();
    $tenantId = current_tenant_id($viewer);
    $sql = 'SELECT COALESCE(SUM(amount), 0) FROM collections WHERE collected_on = :today';
    $params = ['today' => $todayDate];

    if ($tenantId !== null) {
        $sql .= ' AND tenant_id = :tenant_id';
        $params['tenant_id'] = $tenantId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (float) $stmt->fetchColumn();
}

function dashboard_week_start_from_input(string $weekInput): DateTimeImmutable
{
    $weekInput = trim($weekInput);
    if (preg_match('/^(\d{4})-W(\d{2})$/', $weekInput, $matches) === 1) {
        $year = (int) $matches[1];
        $week = (int) $matches[2];
        if ($year >= 1970 && $week >= 1 && $week <= 53) {
            return (new DateTimeImmutable())->setISODate($year, $week, 1);
        }
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekInput) === 1) {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $weekInput);
        if ($date instanceof DateTimeImmutable && $date->format('Y-m-d') === $weekInput) {
            return $date->modify('monday this week');
        }
    }

    return (new DateTimeImmutable(today()))->modify('monday this week');
}

function dashboard_week_input_value(DateTimeImmutable $weekStart): string
{
    return $weekStart->format('o-\WW');
}

function collections_total_chart(PDO $pdo, ?array $viewer = null, string $mode = 'monthly', ?DateTimeImmutable $selectedWeekStart = null): array
{
    $mode = in_array($mode, ['monthly', 'yearly', 'weekly'], true) ? $mode : 'monthly';
    $today = new DateTimeImmutable(today());
    $viewerRole = (string) ($viewer['role'] ?? '');
    $viewerId = (int) ($viewer['id'] ?? 0);
    $tenantId = current_tenant_id($viewer);

    if ($mode === 'weekly') {
        $startDate = $selectedWeekStart instanceof DateTimeImmutable
            ? $selectedWeekStart->modify('monday this week')
            : $today->modify('monday this week');
        $endDate = $startDate->modify('+6 days');
        $labels = [];
        $tooltipLabels = [];
        $keys = [];

        for ($i = 0; $i < 7; $i++) {
            $date = $startDate->modify('+' . $i . ' days');
            $keys[] = $date->format('Y-m-d');
            $labels[] = $date->format('D');
            $tooltipLabels[] = $date->format('D M j');
        }

        $groupExpression = 'c.collected_on';
        $title = 'Weekly Collections';
        $subtitle = display_date($startDate->format('Y-m-d')) . ' - ' . display_date($endDate->format('Y-m-d'));
        $pillSuffix = 'selected week';
    } elseif ($mode === 'yearly') {
        $startDate = $today->setDate((int) $today->format('Y'), 1, 1);
        $endDate = $today->setDate((int) $today->format('Y'), 12, 31);
        $labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $tooltipLabels = $labels;
        $keys = range(1, 12);
        $groupExpression = 'MONTH(c.collected_on)';
        $title = 'This Year Collections';
        $subtitle = $today->format('Y') . ' collection totals';
        $pillSuffix = 'this year';
    } else {
        $startDate = $today->modify('first day of this month');
        $endDate = $today->modify('last day of this month');
        $labels = [];
        $tooltipLabels = [];
        $keys = [];

        for ($date = $startDate; $date <= $endDate; $date = $date->modify('+1 day')) {
            $keys[] = $date->format('Y-m-d');
            $labels[] = $date->format('j');
            $tooltipLabels[] = $date->format('M j');
        }

        $groupExpression = 'c.collected_on';
        $title = 'This Month Collections';
        $subtitle = $startDate->format('F Y') . ' daily collection totals';
        $pillSuffix = 'this month';
    }

    $joins = '';
    $scope = '';
    $params = [
        'start_date' => $startDate->format('Y-m-d'),
        'end_date' => $endDate->format('Y-m-d'),
    ];

    if ($tenantId !== null) {
        $scope = ' AND c.tenant_id = :tenant_id';
        $params['tenant_id'] = $tenantId;
    }

    $stmt = $pdo->prepare(
        "SELECT {$groupExpression} AS period_key, COALESCE(SUM(c.amount), 0) AS total
         FROM collections c
         {$joins}
         WHERE c.collected_on BETWEEN :start_date AND :end_date
         {$scope}
         GROUP BY period_key"
    );
    $stmt->execute($params);

    $totalsByKey = [];
    foreach ($stmt->fetchAll() as $row) {
        $key = $mode === 'yearly' ? (int) $row['period_key'] : (string) $row['period_key'];
        $totalsByKey[$key] = (float) $row['total'];
    }

    $values = [];
    foreach ($keys as $key) {
        $values[] = $totalsByKey[$key] ?? 0.0;
    }

    $maxValue = max([1.0, ...$values]);
    $bars = [];
    foreach ($values as $index => $value) {
        $bars[] = [
            'label' => $labels[$index],
            'tooltip_label' => $tooltipLabels[$index] ?? $labels[$index],
            'value' => $value,
            'height' => $value > 0 ? max(8.0, ($value / $maxValue) * 100) : 2.0,
        ];
    }

    return [
        'mode' => $mode,
        'title' => $title,
        'subtitle' => $subtitle,
        'pill_suffix' => $pillSuffix,
        'week_value' => dashboard_week_input_value($selectedWeekStart instanceof DateTimeImmutable ? $selectedWeekStart : $today->modify('monday this week')),
        'week_start' => $startDate->format('Y-m-d'),
        'bars' => $bars,
        'total' => array_sum($values),
        'max_value' => $maxValue,
    ];
}

function dashboard_collection_chart_html(PDO $pdo, array $chart, string $mode): string
{
    $mode = in_array($mode, ['monthly', 'yearly', 'weekly'], true) ? $mode : 'monthly';
    $monthlyClass = $mode === 'monthly' ? 'active' : '';
    $yearlyClass = $mode === 'yearly' ? 'active' : '';
    $weeklyClass = $mode === 'weekly' ? 'active' : '';
    $weekValue = (string) ($chart['week_value'] ?? dashboard_week_input_value((new DateTimeImmutable(today()))->modify('monday this week')));
    $bars = array_values(is_array($chart['bars'] ?? null) ? $chart['bars'] : []);
    $barCount = max(1, count($bars));
    $total = (float) ($chart['total'] ?? 0);
    $maxValue = max(1.0, (float) ($chart['max_value'] ?? 1.0));
    $axisStep = $mode === 'monthly' ? 4 : 1;
    $chartLabel = match ($mode) {
        'weekly' => 'Weekly collections chart',
        'yearly' => 'Yearly collections chart',
        default => 'Current month collections chart',
    };

    ob_start();
    ?>
    <div class="panel-head collections-chart-head">
        <div>
            <h2 class="panel-title"><?= e((string) $chart['title']) ?></h2>
            <p class="chart-subtitle"><?= e((string) ($chart['subtitle'] ?? '')) ?></p>
        </div>
        <div class="collections-chart-actions">
            <div class="chart-toggle" aria-label="Collection chart range">
                <a class="<?= e($monthlyClass) ?>" href="<?= e(url('index.php?chart=monthly')) ?>">Daily</a>
                <a class="<?= e($yearlyClass) ?>" href="<?= e(url('index.php?chart=yearly')) ?>">Monthly</a>
                <a class="<?= e($weeklyClass) ?>" href="<?= e(url('index.php?chart=weekly&week=' . rawurlencode($weekValue))) ?>">Weekly</a>
            </div>
            <?php if ($mode === 'weekly'): ?>
                <form class="chart-week-form" method="get" action="<?= e(url('index.php')) ?>">
                    <input type="hidden" name="chart" value="weekly">
                    <label class="sr-only" for="dashboard-week-picker">Select week</label>
                    <input type="week" id="dashboard-week-picker" name="week" value="<?= e($weekValue) ?>" aria-label="Select week" onchange="this.form.submit()">
                </form>
            <?php endif; ?>
        </div>
    </div>
    <div class="collection-bar-chart collection-bar-chart-<?= e($mode) ?>" aria-label="<?= e($chartLabel) ?>">
        <div class="collection-chart-plot" style="--chart-count: <?= e((string) $barCount) ?>;">
            <?php foreach ($bars as $bar): ?>
                <?php
                $value = (float) ($bar['value'] ?? 0);
                $height = $value > 0 ? max(4.0, ($value / $maxValue) * 100) : 0.0;
                $label = (string) ($bar['label'] ?? '');
                $tooltipLabel = (string) ($bar['tooltip_label'] ?? $label);
                ?>
                <div
                    class="collection-chart-bar"
                    tabindex="0"
                    aria-label="<?= e($tooltipLabel . ': ' . money_label($pdo, $value)) ?>"
                >
                    <span class="collection-chart-fill" style="height: <?= e(number_format($height, 2, '.', '')) ?>%">
                        <span class="collection-chart-tooltip" role="tooltip">
                            <strong class="collection-chart-tooltip-date"><?= e($tooltipLabel) ?></strong>
                            <span><em>Collected</em><strong><?= e(money_label($pdo, $value)) ?></strong></span>
                        </span>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="collection-chart-axis" style="--chart-count: <?= e((string) $barCount) ?>;">
            <?php foreach ($bars as $index => $bar): ?>
                <span><?= $index % $axisStep === 0 || $index === $barCount - 1 ? e((string) ($bar['label'] ?? '')) : '' ?></span>
            <?php endforeach; ?>
        </div>

        <div class="collection-chart-footer">
            <span class="chart-total-pill collection-chart-total"><?= e(money_label($pdo, $total)) ?> <?= e((string) $chart['pill_suffix']) ?></span>
        </div>
    </div>
    <?php

    return trim((string) ob_get_clean());
}

function dashboard_user_goals(PDO $pdo, ?array $viewer = null): array
{
    $tenantId = current_tenant_id($viewer);
    $tenantScope = $tenantId !== null ? ' AND l.tenant_id = :tenant_id' : '';

    $collectedStmt = $pdo->prepare(
        "SELECT
            u.id,
            u.full_name,
            u.username,
            u.role,
            COALESCE(SUM(c.amount), 0) AS collected
         FROM collections c
         JOIN users u ON u.id = c.collected_by_user_id
         JOIN loans l ON l.id = c.loan_id
         WHERE c.collected_on = CURDATE()
           {$tenantScope}
         GROUP BY u.id, u.full_name, u.username, u.role
         HAVING collected > 0
         ORDER BY collected DESC, u.full_name ASC"
    );
    $params = [];
    if ($tenantId !== null) {
        $params['tenant_id'] = $tenantId;
    }
    $collectedStmt->execute($params);

    $users = $collectedStmt ? $collectedStmt->fetchAll() : [];
    $users = array_map(static function (array $user) use ($pdo): array {
        return [
            'id' => (int) $user['id'],
            'full_name' => (string) $user['full_name'],
            'role' => (string) $user['role'],
            'role_label' => user_role_display_name($user, $pdo),
            'collected' => (float) $user['collected'],
        ];
    }, $users);

    return [
        'total_collected' => array_sum(array_column($users, 'collected')),
        'users' => $users,
    ];
}