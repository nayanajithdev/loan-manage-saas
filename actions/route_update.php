<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
if (is_owner()) {
    redirect('pages/tenants.php');
}
require_permission('routes.edit', 'pages/routes.php');
require_tenant_context('pages/routes.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('pages/routes.php');
}
require_csrf('pages/routes.php');

$routeId = (int) ($_POST['route_id'] ?? 0);
$name = trim((string) ($_POST['name'] ?? ''));
$name = preg_replace('/\s+/', ' ', $name) ?? $name;
$name = mb_substr($name, 0, 120);
$editRedirect = 'pages/routes.php?edit_route_id=' . $routeId;

if ($routeId <= 0 || !route_exists($pdo, $routeId)) {
    set_flash('error', 'Invalid route selected.');
    redirect('pages/routes.php');
}

if ($name === '') {
    set_flash('error', 'Route name is required.');
    redirect($editRedirect);
}

if (route_name_exists($pdo, $name, $routeId)) {
    set_flash('error', 'Route name already exists.');
    redirect($editRedirect);
}

try {
    $stmt = $pdo->prepare('UPDATE routes SET name = :name WHERE id = :id AND ' . tenant_scope_sql());
    $stmt->execute(tenant_scope_params([
        'name' => $name,
        'id' => $routeId,
    ]));

    log_activity($pdo, 'route.updated', 'Route renamed: ' . $name . '.', [
        'route_id' => $routeId,
        'name' => $name,
    ]);
    set_flash('success', 'Route updated successfully.');
} catch (Throwable $e) {
    log_activity($pdo, 'route.update_failed', 'Route update failed.', [
        'route_id' => $routeId,
        'reason' => $e->getMessage(),
    ]);
    set_flash('error', 'Failed to update route. Please try again.');
    redirect($editRedirect);
}

redirect('pages/routes.php');