<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_platform_owner('pages/tenants.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('pages/tenants.php');
}
require_csrf('pages/tenants.php');

$tenantId = (int) ($_POST['tenant_id'] ?? 0);
$status = trim((string) ($_POST['status'] ?? ''));

if ($tenantId <= 0 || !in_array($status, ['approved', 'rejected', 'suspended'], true)) {
    set_flash('error', 'Invalid business action.');
    redirect('pages/tenants.php');
}

$timestampColumn = match ($status) {
    'approved' => 'approved_at',
    'rejected' => 'rejected_at',
    'suspended' => 'suspended_at',
};

$stmt = $pdo->prepare(
    "UPDATE tenants
     SET status = :status,
         {$timestampColumn} = NOW()
     WHERE id = :tenant_id
       AND status <> 'deleted'"
);
$stmt->execute([
    'status' => $status,
    'tenant_id' => $tenantId,
]);

if ($status !== 'approved') {
    $usersStmt = $pdo->prepare('SELECT id FROM users WHERE tenant_id = :tenant_id');
    $usersStmt->execute(['tenant_id' => $tenantId]);
    foreach ($usersStmt->fetchAll(PDO::FETCH_COLUMN) as $userId) {
        force_logout_user_everywhere($pdo, (int) $userId);
    }
}

log_activity($pdo, 'tenant.status_changed', 'Business status changed.', [
    'tenant_id' => $tenantId,
    'status' => $status,
]);

set_flash('success', 'Business status updated.');
redirect('pages/tenants.php');
