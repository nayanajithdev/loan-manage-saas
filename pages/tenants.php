<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_platform_owner();

$pageTitle = 'Tenants';
$activePage = 'tenants';
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$validStatuses = ['pending', 'approved', 'rejected', 'suspended', 'deleted'];
if (!in_array($statusFilter, $validStatuses, true)) {
    $statusFilter = '';
}

$params = [];
$sql = "SELECT
            t.*,
            COUNT(DISTINCT u.id) AS user_count,
            COUNT(DISTINCT c.id) AS customer_count,
            COUNT(DISTINCT l.id) AS loan_count
        FROM tenants t
        LEFT JOIN users u ON u.tenant_id = t.id
        LEFT JOIN customers c ON c.tenant_id = t.id
        LEFT JOIN loans l ON l.tenant_id = t.id";

if ($statusFilter !== '') {
    $sql .= ' WHERE t.status = :status';
    $params['status'] = $statusFilter;
}

$sql .= " GROUP BY t.id
          ORDER BY FIELD(t.status, 'pending', 'approved', 'suspended', 'rejected', 'deleted'), t.created_at DESC, t.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tenants = $stmt->fetchAll();

require __DIR__ . '/../includes/layout_start.php';
?>

<div class="tenants-list-head tenants-list-toolbar">
    <form method="get" action="<?= e(url('pages/tenants.php')) ?>" class="tenant-filter-form tenant-filter-form-inline">
        <div class="field full">
            <select id="tenant-status-filter" name="status" onchange="this.form.submit()">
                <option value="" <?= $statusFilter === '' ? 'selected' : '' ?>>All tenants</option>
                <?php foreach ($validStatuses as $status): ?>
                    <option value="<?= e($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= e(ucfirst($status)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <noscript>
            <button class="btn btn-primary" type="submit">Apply Filter</button>
        </noscript>
    </form>
    <a class="btn btn-primary" href="<?= e(url('pages/tenant_create.php')) ?>">
        <span class="btn-icon-inline" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
        </span>
        Create Tenant
    </a>
</div>

<section class="panel users-directory-panel">
    <div class="table-wrap">
        <table class="zebra-table users-directory-table">
            <thead>
            <tr>
                <th>Tenant</th>
                <th>Owner</th>
                <th>Status</th>
                <th>Users</th>
                <th>Customers</th>
                <th>Loans</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$tenants): ?>
                <tr><td colspan="8">No tenants found.</td></tr>
            <?php else: ?>
                <?php foreach ($tenants as $tenant): ?>
                    <?php
                    $tenantId = (int) $tenant['id'];
                    $status = (string) $tenant['status'];
                    ?>
                    <tr>
                        <td data-label="Tenant">
                            <strong><?= e((string) $tenant['name']) ?></strong>
                            <div class="muted-text"><?= e((string) $tenant['slug']) ?></div>
                        </td>
                        <td data-label="Owner">
                            <?= e((string) ($tenant['owner_name'] ?? '-')) ?>
                            <div class="muted-text"><?= e((string) ($tenant['owner_email'] ?? '')) ?></div>
                        </td>
                        <td data-label="Status"><span class="badge badge-<?= e(status_badge_class($status)) ?>"><?= e(ucfirst($status)) ?></span></td>
                        <td data-label="Users"><?= e((string) (int) $tenant['user_count']) ?></td>
                        <td data-label="Customers"><?= e((string) (int) $tenant['customer_count']) ?></td>
                        <td data-label="Loans"><?= e((string) (int) $tenant['loan_count']) ?></td>
                        <td data-label="Created"><?= e(display_date(substr((string) $tenant['created_at'], 0, 10))) ?></td>
                        <td data-label="Actions">
                            <div class="tenant-actions">
                                <?php foreach (['approved' => 'Approve', 'rejected' => 'Reject', 'suspended' => 'Suspend'] as $nextStatus => $label): ?>
                                    <?php if ($status !== $nextStatus && $status !== 'deleted'): ?>
                                        <form method="post" action="<?= e(url('actions/tenant_status.php')) ?>">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="tenant_id" value="<?= e((string) $tenantId) ?>">
                                            <input type="hidden" name="status" value="<?= e($nextStatus) ?>">
                                            <button class="btn" type="submit"><?= e($label) ?></button>
                                        </form>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <?php if ($status !== 'deleted'): ?>
                                    <form method="post" action="<?= e(url('actions/tenant_delete.php')) ?>" data-confirm="Delete this tenant? This will hide it and block tenant login.">
                                        <?= csrf_input() ?>
                                        <input type="hidden" name="tenant_id" value="<?= e((string) $tenantId) ?>">
                                        <button class="btn btn-danger" type="submit">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/../includes/layout_end.php';
