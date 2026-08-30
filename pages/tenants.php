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

$summary = [
    'pending' => 0,
    'approved' => 0,
    'suspended' => 0,
    'rejected' => 0,
    'deleted' => 0,
];
$summaryStmt = $pdo->query("SELECT status, COUNT(*) AS tenant_count FROM tenants GROUP BY status");
foreach ($summaryStmt->fetchAll() as $row) {
    $status = (string) ($row['status'] ?? '');
    if (array_key_exists($status, $summary)) {
        $summary[$status] = (int) $row['tenant_count'];
    }
}

require __DIR__ . '/../includes/layout_start.php';
?>

<section class="card-grid dashboard-stat-grid">
    <article class="stat-card">
        <p class="stat-label">Pending Approval</p>
        <p class="stat-value"><?= e((string) $summary['pending']) ?></p>
    </article>
    <article class="stat-card">
        <p class="stat-label">Approved Tenants</p>
        <p class="stat-value"><?= e((string) $summary['approved']) ?></p>
    </article>
    <article class="stat-card">
        <p class="stat-label">Suspended</p>
        <p class="stat-value"><?= e((string) $summary['suspended']) ?></p>
    </article>
</section>

<section class="dashboard-two-col">
    <article class="panel">
        <div class="panel-head">
            <h2 class="panel-title">Create Tenant</h2>
        </div>
        <form method="post" action="<?= e(url('actions/tenant_save.php')) ?>" class="form-grid tenant-create-form">
            <?= csrf_input() ?>
            <div class="form-field">
                <label for="tenant-name">Business Name</label>
                <input id="tenant-name" type="text" name="name" maxlength="150" required>
            </div>
            <div class="form-field">
                <label for="tenant-slug">Slug</label>
                <input id="tenant-slug" type="text" name="slug" maxlength="80" placeholder="auto-created if blank">
            </div>
            <div class="form-field">
                <label for="tenant-owner-name">Owner Name</label>
                <input id="tenant-owner-name" type="text" name="owner_name" maxlength="150" required>
            </div>
            <div class="form-field">
                <label for="tenant-owner-email">Owner Email</label>
                <input id="tenant-owner-email" type="email" name="owner_email" maxlength="190" required>
            </div>
            <div class="form-field">
                <label for="tenant-phone">Phone</label>
                <input id="tenant-phone" type="text" name="phone" maxlength="40">
            </div>
            <div class="form-field">
                <label for="tenant-username">Admin Username</label>
                <input id="tenant-username" type="text" name="username" maxlength="80" required>
            </div>
            <div class="form-field">
                <label for="tenant-password">Admin Password</label>
                <input id="tenant-password" type="password" name="password" minlength="6" required>
            </div>
            <div class="form-field">
                <label for="tenant-status">Initial Status</label>
                <select id="tenant-status" name="status">
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                </select>
            </div>
            <div class="form-field form-field-full">
                <label for="tenant-notes">Notes</label>
                <textarea id="tenant-notes" name="notes" rows="3"></textarea>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Create Tenant</button>
            </div>
        </form>
    </article>

    <article class="panel">
        <div class="panel-head">
            <h2 class="panel-title">Filter</h2>
        </div>
        <div class="filter-pills">
            <a class="btn <?= $statusFilter === '' ? 'btn-primary' : '' ?>" href="<?= e(url('pages/tenants.php')) ?>">All</a>
            <?php foreach ($validStatuses as $status): ?>
                <a class="btn <?= $statusFilter === $status ? 'btn-primary' : '' ?>" href="<?= e(url('pages/tenants.php?status=' . $status)) ?>">
                    <?= e(ucfirst($status)) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </article>
</section>

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
