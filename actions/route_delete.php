<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
if (is_owner()) {
    redirect('pages/tenants.php');
}
require_permission('routes.delete', 'pages/routes.php');
require_tenant_context('pages/routes.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('pages/routes.php');
}
require_csrf('pages/routes.php');

$routeId = (int) ($_POST['route_id'] ?? 0);
if ($routeId <= 0) {
    set_flash('error', 'Invalid route selected.');
    redirect('pages/routes.php');
}

try {
    $pdo->beginTransaction();

    $routeStmt = $pdo->prepare('SELECT id, name FROM routes WHERE id = :id AND ' . tenant_scope_sql() . ' FOR UPDATE');
    $routeStmt->execute(tenant_scope_params(['id' => $routeId]));
    $route = $routeStmt->fetch();
    if (!$route) {
        throw new RuntimeException('Route not found.');
    }

    $loanCount = route_loan_count($pdo, $routeId);
    if ($loanCount > 0) {
        throw new RuntimeException('Route has linked loans.');
    }

    $deleteStmt = $pdo->prepare('DELETE FROM routes WHERE id = :id AND ' . tenant_scope_sql());
    $deleteStmt->execute(tenant_scope_params(['id' => $routeId]));

    $pdo->commit();

    log_activity($pdo, 'route.deleted', 'Route deleted: ' . (string) $route['name'] . '.', [
        'route_id' => $routeId,
        'name' => (string) $route['name'],
    ]);
    set_flash('success', 'Route deleted successfully.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ($e->getMessage() === 'Route has linked loans.') {
        set_flash('error', 'Cannot delete this route because loans are assigned to it.');
    } else {
        log_activity($pdo, 'route.delete_failed', 'Route delete failed.', [
            'route_id' => $routeId,
            'reason' => $e->getMessage(),
        ]);
        set_flash('error', 'Failed to delete route. Please try again.');
    }
}

redirect('pages/routes.php');