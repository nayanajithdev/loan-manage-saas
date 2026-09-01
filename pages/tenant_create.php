<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

require_platform_owner();

$pageTitle = 'Create Tenant';
$activePage = 'tenants';
$oldInput = is_array($_SESSION['tenant_create_old_input'] ?? null) ? $_SESSION['tenant_create_old_input'] : [];
unset($_SESSION['tenant_create_old_input']);
$old = static function (string $key, string $default = '') use ($oldInput): string {
    $value = $oldInput[$key] ?? $default;

    return is_scalar($value) ? (string) $value : $default;
};
$oldStatus = $old('status', 'pending');
if (!in_array($oldStatus, ['pending', 'approved'], true)) {
    $oldStatus = 'pending';
}

require __DIR__ . '/../includes/layout_start.php';
?>

<div class="user-edit-page-toolbar">
    <a class="btn" href="<?= e(url('pages/tenants.php')) ?>">
        <span class="btn-icon-inline" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
        </span>
        Back to Tenants
    </a>
</div>

<section class="panel tenant-create-page-panel">
    <div class="panel-head compact-panel-head">
        <div>
            <h2 class="panel-title">Create Tenant</h2>
            <p class="panel-subtext">Create a tenant workspace and its first tenant owner account.</p>
        </div>
    </div>

    <form method="post" action="<?= e(url('actions/tenant_save.php')) ?>" class="form-grid tenant-create-form">
        <?= csrf_input() ?>
        <div class="form-field">
            <label for="tenant-name">Business Name</label>
            <input id="tenant-name" type="text" name="name" maxlength="150" value="<?= e($old('name')) ?>" required autofocus>
        </div>
        <div class="form-field">
            <label for="tenant-slug">Slug</label>
            <input id="tenant-slug" type="text" name="slug" maxlength="80" value="<?= e($old('slug')) ?>" placeholder="auto-created if blank">
        </div>
        <div class="form-field">
            <label for="tenant-owner-name">Owner Name</label>
            <input id="tenant-owner-name" type="text" name="owner_name" maxlength="150" value="<?= e($old('owner_name')) ?>" required>
        </div>
        <div class="form-field">
            <label for="tenant-owner-email">Owner Email</label>
            <input id="tenant-owner-email" type="email" name="owner_email" maxlength="190" value="<?= e($old('owner_email')) ?>" required>
        </div>
        <div class="form-field">
            <label for="tenant-phone">Phone</label>
            <input id="tenant-phone" type="text" name="phone" maxlength="40" value="<?= e($old('phone')) ?>">
        </div>
        <div class="form-field">
            <label for="tenant-username">Owner Username</label>
            <input id="tenant-username" type="text" name="username" maxlength="80" value="<?= e($old('username')) ?>" required>
        </div>
        <div class="form-field">
            <label for="tenant-password">Admin Password</label>
            <input id="tenant-password" type="password" name="password" minlength="6" required>
        </div>
        <div class="form-field">
            <label for="tenant-status">Initial Status</label>
            <select id="tenant-status" name="status">
                <option value="pending" <?= $oldStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="approved" <?= $oldStatus === 'approved' ? 'selected' : '' ?>>Approved</option>
            </select>
        </div>
        <div class="form-field form-field-full">
            <label for="tenant-notes">Notes</label>
            <textarea id="tenant-notes" name="notes" rows="4"><?= e($old('notes')) ?></textarea>
        </div>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Create Tenant</button>
        </div>
    </form>
</section>

<?php require __DIR__ . '/../includes/layout_end.php';