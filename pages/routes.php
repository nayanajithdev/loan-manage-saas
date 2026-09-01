<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
if (is_owner()) {
    redirect('pages/tenants.php');
}
require_permission('routes.view');
require_tenant_context();

$pageTitle = 'Routes';
$activePage = 'routes';
$canCreateRoute = can('routes.create');
$canEditRoute = can('routes.edit');
$canDeleteRoute = can('routes.delete');
$editRouteId = $canEditRoute ? max(0, (int) ($_GET['edit_route_id'] ?? 0)) : 0;

$stmt = $pdo->prepare(
    "SELECT
        r.id,
        r.name,
        r.created_at,
        COUNT(l.id) AS loan_count
     FROM routes r
     LEFT JOIN loans l ON l.route_id = r.id AND l.tenant_id = r.tenant_id
     WHERE " . tenant_scope_sql('r') . "
     GROUP BY r.id, r.name, r.created_at
     ORDER BY r.name ASC, r.id ASC"
);
$stmt->execute(tenant_scope_params());
$routes = $stmt->fetchAll();

$editRoute = null;
if ($editRouteId > 0) {
    foreach ($routes as $route) {
        if ((int) ($route['id'] ?? 0) === $editRouteId) {
            $editRoute = $route;
            break;
        }
    }
}
$isEditMode = $editRoute !== null;
$formAction = $isEditMode ? 'actions/route_update.php' : 'actions/route_save.php';
$formTitle = $isEditMode ? 'Edit Route' : 'Create Route';
$formButtonLabel = $isEditMode ? 'Update Route' : 'Create Route';
$formCanSubmit = $isEditMode ? $canEditRoute : $canCreateRoute;
$routeNameValue = $isEditMode ? (string) ($editRoute['name'] ?? '') : '';

require __DIR__ . '/../includes/layout_start.php';
?>

<div class="routes-layout">
    <section class="panel route-create-panel">
        <div class="panel-head">
            <h2 class="panel-title"><?= e($formTitle) ?></h2>
        </div>
        <form class="form-grid route-form" method="post" action="<?= e(url($formAction)) ?>">
            <?= csrf_input() ?>
            <?php if ($isEditMode): ?>
                <input type="hidden" name="route_id" value="<?= e((string) $editRouteId) ?>">
            <?php endif; ?>
            <div class="field full">
                <label>Route Name</label>
                <input type="text" name="name" maxlength="120" value="<?= e($routeNameValue) ?>" required <?= $formCanSubmit ? '' : 'disabled' ?>>
            </div>
            <div class="form-actions route-form-actions">
                <?php if ($isEditMode): ?>
                    <a class="btn" href="<?= e(url('pages/routes.php')) ?>">Cancel</a>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary" <?= $formCanSubmit ? '' : 'disabled' ?>><?= e($formButtonLabel) ?></button>
            </div>
        </form>
    </section>

    <section class="panel routes-list-panel">
        <div class="panel-head routes-list-head">
            <h2 class="panel-title">Routes</h2>
        </div>
        <div class="table-wrap">
            <table class="zebra-table routes-table">
                <thead>
                <tr>
                    <th>Route Name</th>
                    <th>Loans</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$routes): ?>
                    <tr><td colspan="3">No routes yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($routes as $route): ?>
                        <?php
                        $rowRouteId = (int) ($route['id'] ?? 0);
                        $loanCount = (int) ($route['loan_count'] ?? 0);
                        $isSelected = $isEditMode && $rowRouteId === $editRouteId;
                        ?>
                        <tr class="<?= $isSelected ? 'is-selected' : '' ?>">
                            <td data-label="Route Name"><?= e((string) $route['name']) ?></td>
                            <td data-label="Loans"><?= e((string) $loanCount) ?></td>
                            <td data-label="Action">
                                <div class="route-action-buttons">
                                    <?php if ($canEditRoute): ?>
                                        <a class="btn" href="<?= e(url('pages/routes.php?edit_route_id=' . $rowRouteId)) ?>">Edit</a>
                                    <?php endif; ?>
                                    <?php if ($canDeleteRoute && $loanCount === 0): ?>
                                        <form class="inline-form" method="post" action="<?= e(url('actions/route_delete.php')) ?>" data-confirm="Delete this route?" data-inline-confirm="1" data-inline-confirm-label="Delete">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="route_id" value="<?= e((string) $rowRouteId) ?>">
                                            <button class="btn btn-danger" type="submit">Delete</button>
                                        </form>
                                    <?php elseif ($loanCount > 0): ?>
                                        <span class="muted-text">Loans linked</span>
                                    <?php elseif (!$canEditRoute): ?>
                                        <span class="muted-text">Protected</span>
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
</div>

<?php require __DIR__ . '/../includes/layout_end.php';