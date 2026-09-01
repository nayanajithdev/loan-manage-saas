<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
if (is_owner()) {
    redirect('pages/tenants.php');
}
require_permission('routes.create', 'pages/routes.php');
$tenantId = require_tenant_context('pages/routes.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('pages/routes.php');
}
require_csrf('pages/routes.php');

$name = trim((string) ($_POST['name'] ?? ''));
$name = preg_replace('/\s+/', ' ', $name) ?? $name;
$name = mb_substr($name, 0, 120);

if ($name === '') {
    set_flash('error', 'Route name is required.');
    redirect('pages/routes.php');
}

if (route_name_exists($pdo, $name)) {
    set_flash('error', 'Route name already exists.');
    redirect('pages/routes.php');
}

try {
    $stmt = $pdo->prepare('INSERT INTO routes (tenant_id, name) VALUES (:tenant_id, :name)');
    $stmt->execute([
        'tenant_id' => $tenantId,
        'name' => $name,
    ]);
    $routeId = (int) $pdo->lastInsertId();

    log_activity($pdo, 'route.created', 'Route created: ' . $name . '.', [
        'route_id' => $routeId,
        'name' => $name,
    ]);
    set_flash('success', 'Route created successfully.');
} catch (Throwable $e) {
    log_activity($pdo, 'route.create_failed', 'Route creation failed.', [
        'name' => $name,
        'reason' => $e->getMessage(),
    ]);
    set_flash('error', 'Failed to create route. Please try again.');
}

redirect('pages/routes.php');