<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_platform_owner('pages/tenants.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('pages/tenants.php');
}
require_csrf('pages/tenants.php');

$tenantId = (int) ($_POST['tenant_id'] ?? 0);
if ($tenantId <= 0) {
    set_flash('error', 'Invalid business.');
    redirect('pages/tenants.php');
}

$stmt = $pdo->prepare(
    "UPDATE tenants
     SET status = 'deleted',
         deleted_at = NOW()
     WHERE id = :tenant_id"
);
$stmt->execute(['tenant_id' => $tenantId]);

$usersStmt = $pdo->prepare('SELECT id FROM users WHERE tenant_id = :tenant_id');
$usersStmt->execute(['tenant_id' => $tenantId]);
foreach ($usersStmt->fetchAll(PDO::FETCH_COLUMN) as $userId) {
    force_logout_user_everywhere($pdo, (int) $userId);
}

log_activity($pdo, 'tenant.deleted', 'Business soft-deleted.', [
    'tenant_id' => $tenantId,
]);

set_flash('success', 'Business deleted.');
redirect('pages/tenants.php');
