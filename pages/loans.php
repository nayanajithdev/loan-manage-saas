<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
if (is_owner()) {
    redirect('pages/tenants.php');
}
require_permission('loans.view');
require_tenant_context();

$pageTitle = 'Loans';
$activePage = 'loans';

$allowedStatuses = ['active', 'closed'];
$status = strtolower(trim((string) ($_GET['status'] ?? 'active')));
if (!in_array($status, $allowedStatuses, true)) {
    $status = 'active';
}

$search = trim((string) ($_GET['q'] ?? ''));
$search = mb_substr($search, 0, 120);
$routeId = max(0, (int) ($_GET['route_id'] ?? 0));
if ($routeId > 0 && !route_exists($pdo, $routeId)) {
    $routeId = 0;
}
$routeOptions = route_options($pdo);

$loanStatusCounts = array_fill_keys($allowedStatuses, 0);
$statusCountStmt = $pdo->prepare("SELECT status, COUNT(*) AS loan_count FROM loans WHERE status IN ('active', 'closed') AND " . tenant_scope_sql() . " GROUP BY status");
$statusCountStmt->execute(tenant_scope_params());
foreach ($statusCountStmt->fetchAll() as $statusRow) {
    $statusKey = (string) ($statusRow['status'] ?? '');
    if (array_key_exists($statusKey, $loanStatusCounts)) {
        $loanStatusCounts[$statusKey] = (int) ($statusRow['loan_count'] ?? 0);
    }
}
$formatLoanStatusOption = static function (string $label, int $count): string {
    return $label . ' - ' . str_pad((string) $count, 2, '0', STR_PAD_LEFT);
};

$sql = "SELECT l.*, c.full_name, COALESCE(r.name, 'No route') AS route_name,
            COALESCE((SELECT SUM(li.due_amount - li.paid_amount) FROM loan_installments li WHERE li.loan_id = l.id AND li.status IN ('pending', 'partial', 'overdue')), 0) AS outstanding_amount,
            COALESCE((SELECT COUNT(*) FROM loan_installments li WHERE li.loan_id = l.id AND li.status IN ('pending', 'partial', 'overdue')), 0) AS remaining_installment_count,
            (SELECT MAX(co.collected_on) FROM collections co WHERE co.loan_id = l.id) AS closed_on
        FROM loans l
        JOIN customers c ON c.id = l.customer_id
        LEFT JOIN routes r ON r.id = l.route_id AND r.tenant_id = l.tenant_id
        WHERE l.status = :status
          AND " . tenant_scope_sql('l') . "";

$params = tenant_scope_params(['status' => $status]);
if ($search !== '') {
    $searchLike = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
    $sql .= " AND (l.loan_number LIKE :search_loan ESCAPE '\\\\' OR c.full_name LIKE :search_name ESCAPE '\\\\' OR c.nic LIKE :search_nic ESCAPE '\\\\')";
    $params['search_loan'] = $searchLike;
    $params['search_name'] = $searchLike;
    $params['search_nic'] = $searchLike;
}
if ($routeId > 0) {
    $sql .= ' AND l.route_id = :route_id';
    $params['route_id'] = $routeId;
}

$sql .= ' ORDER BY l.id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$loans = $stmt->fetchAll();
$canCreateLoan = can('loans.create');

$renderLoansHead = static function (string $status): string {
    ob_start(); ?>
    <tr>
        <th>Loan No</th>
        <th>Customer</th>
        <th>Route</th>
        <th>Principal</th>
        <th>Total</th>
        <th>Collected</th>
        <?php if ($status === 'closed'): ?>
            <th>Loan Closed Date</th>
        <?php else: ?>
            <th>Balance</th>
            <th>Inst. Left</th>
        <?php endif; ?>
    </tr>
    <?php return (string) ob_get_clean();
};

$renderLoansBody = static function (array $loans, PDO $pdo, string $status): string {
    $columnCount = $status === 'closed' ? 7 : 8;
    ob_start();
    if (!$loans): ?>
        <tr><td colspan="<?= e((string) $columnCount) ?>">No loans yet.</td></tr>
    <?php else: ?>
        <?php foreach ($loans as $loan): ?>
            <?php $balance = max(0, (float) $loan['outstanding_amount']); ?>
            <?php $collectedAmount = max(0, round((float) $loan['total_amount'] - $balance, 2)); ?>
            <?php $remainingInstallments = (int) ($loan['remaining_installment_count'] ?? 0); ?>
            <?php $closedOn = trim((string) ($loan['closed_on'] ?? '')); ?>
            <?php $selectUrl = url('pages/loan_edit.php?loan_id=' . (int) $loan['id']); ?>
            <tr class="table-row-clickable" data-select-url="<?= e($selectUrl) ?>">
                <td><?= e($loan['loan_number']) ?></td>
                <td><?= e($loan['full_name']) ?></td>
                <td><?= e((string) ($loan['route_name'] ?? 'No route')) ?></td>
                <td><?= e(money_label($pdo, (float) $loan['principal_amount'])) ?></td>
                <td><?= e(money_label($pdo, (float) $loan['total_amount'])) ?></td>
                <td><?= e(money_label($pdo, $collectedAmount)) ?></td>
                <?php if ($status === 'closed'): ?>
                    <td><?= e($closedOn !== '' ? display_date($closedOn) : '-') ?></td>
                <?php else: ?>
                    <td><?= $balance <= 0 ? '---' : e(money_label($pdo, $balance)) ?></td>
                    <td>
                        <?php if ($remainingInstallments <= 0): ?>
                            <span class="badge badge-success">Completed</span>
                        <?php else: ?>
                            <?= e((string) $remainingInstallments) ?> left (<?= e((string) $loan['installment_frequency']) ?>)
                        <?php endif; ?>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
    <?php endif;
    return (string) ob_get_clean();
};

$renderLoansCards = static function (array $loans, PDO $pdo, string $status): string {
    ob_start();
    if (!$loans): ?>
        <div class="loan-mobile-empty">No loans yet.</div>
    <?php else: ?>
        <?php foreach ($loans as $loan): ?>
            <?php
            $balance = max(0, (float) $loan['outstanding_amount']);
            $collectedAmount = max(0, round((float) $loan['total_amount'] - $balance, 2));
            $closedOn = trim((string) ($loan['closed_on'] ?? ''));
            $selectUrl = url('pages/loan_edit.php?loan_id=' . (int) $loan['id']);
            ?>
            <article class="loan-mobile-card table-row-clickable" data-select-url="<?= e($selectUrl) ?>">
                <div class="loan-mobile-card-head">
                    <strong class="loan-mobile-number"><?= e((string) $loan['loan_number']) ?></strong>
                    <strong class="loan-mobile-customer"><?= e((string) $loan['full_name']) ?></strong>
                </div>
                <div class="loan-mobile-card-body">
                    <div class="loan-mobile-metric">
                        <span>Route</span>
                        <strong><?= e((string) ($loan['route_name'] ?? 'No route')) ?></strong>
                    </div>
                    <div class="loan-mobile-metric">
                        <span>Principal</span>
                        <strong><?= e(money_label($pdo, (float) $loan['principal_amount'])) ?></strong>
                    </div>
                    <div class="loan-mobile-metric">
                        <span>Total</span>
                        <strong><?= e(money_label($pdo, (float) $loan['total_amount'])) ?></strong>
                    </div>
                    <div class="loan-mobile-metric is-collected">
                        <span>Collected</span>
                        <strong><?= e(money_label($pdo, $collectedAmount)) ?></strong>
                    </div>
                    <?php if ($status === 'closed'): ?>
                        <div class="loan-mobile-metric">
                            <span>Closed</span>
                            <strong><?= e($closedOn !== '' ? display_date($closedOn) : '-') ?></strong>
                        </div>
                    <?php else: ?>
                        <div class="loan-mobile-metric is-balance">
                            <span>Balance</span>
                            <strong><?= $balance <= 0 ? '---' : e(money_label($pdo, $balance)) ?></strong>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif;
    return (string) ob_get_clean();
};

$isAjax = (
    isset($_GET['loans_ajax']) &&
    $_GET['loans_ajax'] === '1' &&
    strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
);
if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'targets' => [
            '#loans-table-head' => $renderLoansHead($status),
            '#loans-table-body' => $renderLoansBody($loans, $pdo, $status),
            '#loans-mobile-cards' => $renderLoansCards($loans, $pdo, $status),
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require __DIR__ . '/../includes/layout_start.php';
?>

<div class="loans-page-toolbar">
    <form id="loan-filter-form" class="loan-filter-form" method="get" action="<?= e(url('pages/loans.php')) ?>">
        <div class="field loan-status-field">
            <label class="sr-only">Status</label>
            <select name="status" id="loan-status-filter" class="loan-status-select is-<?= e($status) ?>">
                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>><?= e($formatLoanStatusOption('Active Loans', $loanStatusCounts['active'])) ?></option>
                <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>><?= e($formatLoanStatusOption('Closed Loans', $loanStatusCounts['closed'])) ?></option>
            </select>
        </div>
        <div class="field loan-route-field">
            <label class="sr-only">Route</label>
            <select name="route_id" id="loan-route-filter" class="loan-route-select">
                <option value="0" <?= $routeId === 0 ? 'selected' : '' ?>>All routes</option>
                <?php foreach ($routeOptions as $route): ?>
                    <?php $optionRouteId = (int) ($route['id'] ?? 0); ?>
                    <option value="<?= e((string) $optionRouteId) ?>" <?= $routeId === $optionRouteId ? 'selected' : '' ?>><?= e((string) $route['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field loan-search-field">
            <label class="sr-only">Search loans</label>
            <div class="search-control">
                <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search..." aria-label="Search by loan number, customer name or ID number">
                <button type="submit" class="btn search-submit" aria-label="Search loans">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21 21-4.34-4.34"/><circle cx="11" cy="11" r="8"/></svg>
                </button>
            </div>
        </div>
        <a class="btn loan-filter-reset" href="<?= e(url('pages/loans.php')) ?>">
            <span class="btn-icon-inline" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x-icon lucide-x"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            </span>
            Reset
        </a>
    </form>
    <?php if ($canCreateLoan): ?>
        <div class="loans-page-actions">
            <a class="btn btn-primary loan-create-action" href="<?= e(url('pages/loan_create.php')) ?>">
                <span class="btn-icon-inline" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-circle-plus-icon lucide-circle-plus"><circle cx="12" cy="12" r="10"/><path d="M8 12h8"/><path d="M12 8v8"/></svg>
                </span>
                New Loan
            </a>
        </div>
    <?php endif; ?>
</div>

<div class="loans-mobile-toolbar">
    <form class="loan-mobile-filter-form" method="get" action="<?= e(url('pages/loans.php')) ?>">
        <div class="loan-mobile-toolbar-row loan-mobile-toolbar-primary">
            <?php if ($canCreateLoan): ?>
                <a class="btn btn-primary loan-create-action" href="<?= e(url('pages/loan_create.php')) ?>">
                    <span class="btn-icon-inline" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-circle-plus-icon lucide-circle-plus"><circle cx="12" cy="12" r="10"/><path d="M8 12h8"/><path d="M12 8v8"/></svg>
                    </span>
                    New Loan
                </a>
            <?php endif; ?>
            <div class="field loan-mobile-status-field">
                <label class="sr-only">Status</label>
                <select name="status" class="loan-status-select is-<?= e($status) ?>" data-loan-mobile-filter>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>><?= e($formatLoanStatusOption('Active Loans', $loanStatusCounts['active'])) ?></option>
                    <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>><?= e($formatLoanStatusOption('Closed Loans', $loanStatusCounts['closed'])) ?></option>
                </select>
            </div>
        </div>
        <div class="loan-mobile-toolbar-row loan-mobile-toolbar-secondary">
            <div class="field loan-mobile-route-field">
                <label class="sr-only">Route</label>
                <select name="route_id" class="loan-route-select" data-loan-mobile-filter>
                    <option value="0" <?= $routeId === 0 ? 'selected' : '' ?>>All routes</option>
                    <?php foreach ($routeOptions as $route): ?>
                        <?php $optionRouteId = (int) ($route['id'] ?? 0); ?>
                        <option value="<?= e((string) $optionRouteId) ?>" <?= $routeId === $optionRouteId ? 'selected' : '' ?>><?= e((string) $route['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field loan-mobile-search-field">
                <label class="sr-only">Search loans</label>
                <div class="search-control">
                    <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search..." aria-label="Search by loan number, customer name or ID number">
                    <button type="submit" class="btn search-submit" aria-label="Search loans">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21 21-4.34-4.34"/><circle cx="11" cy="11" r="8"/></svg>
                    </button>
                </div>
            </div>
            <a class="btn loan-mobile-filter-reset" href="<?= e(url('pages/loans.php')) ?>">
                <span class="btn-icon-inline" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x-icon lucide-x"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </span>
                Reset
            </a>
        </div>
    </form>
</div>

<section class="panel loans-list-panel">
    <div class="table-wrap">
        <table class="zebra-table loans-table loans-table-<?= e($status) ?>">
            <thead id="loans-table-head">
            <?= $renderLoansHead($status) ?>
            </thead>
            <tbody id="loans-table-body">
            <?= $renderLoansBody($loans, $pdo, $status) ?>
            </tbody>
        </table>
    </div>
    <div class="loan-mobile-card-list" id="loans-mobile-cards">
        <?= $renderLoansCards($loans, $pdo, $status) ?>
    </div>
</section>

<script>
(() => {
  document.querySelectorAll('[data-loan-mobile-filter]').forEach((mobileFilter) => {
    mobileFilter.addEventListener('change', () => {
      if (mobileFilter.form instanceof HTMLFormElement) {
        mobileFilter.form.submit();
      }
    });
  });

  const form = document.getElementById('loan-filter-form');
  const status = document.getElementById('loan-status-filter');
  const route = document.getElementById('loan-route-filter');
  const tbody = document.getElementById('loans-table-body');
  if (!form || !status || !route || !tbody) return;

  const syncLoanTableStatusClass = () => {
    const loansTable = tbody.closest('table');
    if (!loansTable) return;
    loansTable.classList.toggle('loans-table-active', status.value === 'active');
    loansTable.classList.toggle('loans-table-closed', status.value === 'closed');
  };

  const loadRows = async () => {
    const params = new URLSearchParams(new FormData(form));
    params.set('loans_ajax', '1');
    const requestUrl = `${form.getAttribute('action') || window.location.pathname}?${params.toString()}`;

    try {
      const res = await fetch(requestUrl, {
        method: 'GET',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        cache: 'no-store'
      });
      if (!res.ok) return;
      const data = await res.json();
      if (data && data.targets && data.targets['#loans-table-body'] !== undefined) {
        const tableHead = document.getElementById('loans-table-head');
        if (tableHead && data.targets['#loans-table-head'] !== undefined) {
          tableHead.innerHTML = String(data.targets['#loans-table-head']);
        }
        tbody.innerHTML = String(data.targets['#loans-table-body']);
        const mobileCards = document.getElementById('loans-mobile-cards');
        if (mobileCards && data.targets['#loans-mobile-cards'] !== undefined) {
          mobileCards.innerHTML = String(data.targets['#loans-mobile-cards']);
        }
        syncLoanTableStatusClass();
        if (typeof window.applyMobileTableStack === 'function') window.applyMobileTableStack();
      }
    } catch (_error) {
      // Keep current rows if request fails.
    }
  };

  status.addEventListener('change', () => {
    status.classList.toggle('is-active', status.value === 'active');
    status.classList.toggle('is-closed', status.value === 'closed');
    syncLoanTableStatusClass();
    loadRows();
  });

  route.addEventListener('change', loadRows);
})();
</script>

<?php require __DIR__ . '/../includes/layout_end.php';