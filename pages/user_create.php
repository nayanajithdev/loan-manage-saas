<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

if (is_owner()) {
    redirect('pages/tenants.php');
}

require_permission('users.manage');
require_tenant_context();

$pageTitle = 'Create User';
$activePage = 'users';
$createDefaultPermissions = role_default_permissions('manager');

require __DIR__ . '/../includes/layout_start.php';
?>

<div class="user-edit-page-toolbar">
    <a class="btn" href="<?= e(url('pages/users.php')) ?>">
        <span class="btn-icon-inline" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-left-icon lucide-arrow-left"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
        </span>
        Back to Users
    </a>
</div>

<form id="user-create-form" class="user-edit-form" method="post" action="<?= e(url('actions/user_save.php')) ?>" data-permission-role-defaults>
    <?= csrf_input() ?>
    <input type="hidden" name="status" value="active">

    <div class="user-edit-layout">
        <section class="panel user-info-panel">
            <h2 class="panel-title">User Info</h2>

            <div class="form-grid user-info-grid">
                <div class="field">
                    <label>Full Name</label>
                    <input type="text" name="full_name" required>
                </div>
                <div class="field">
                    <label>Username</label>
                    <input type="text" name="username" required>
                </div>
                <div class="field full">
                    <label>Email</label>
                    <input type="email" name="email" required>
                </div>
                <div class="field">
                    <label>Role</label>
                    <select name="role" required data-permission-role-select>
                        <option value="manager">Manager</option>
                        <option value="collector">Collector</option>
                    </select>
                </div>
                <div class="field">
                    <label>Password</label>
                    <input type="password" name="password" minlength="8" required>
                </div>
                <div class="field">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" minlength="8" required>
                </div>
            </div>
        </section>

        <section class="panel user-permissions-panel">
            <?php render_permission_fields($createDefaultPermissions); ?>
        </section>
    </div>
</form>

<div class="user-edit-actionbar">
    <button type="submit" form="user-create-form" class="btn btn-primary">Create User</button>
</div>
<script>
(() => {
    const defaults = <?= json_encode([
        'manager' => role_default_permissions('manager'),
        'collector' => role_default_permissions('collector'),
    ], JSON_THROW_ON_ERROR) ?>;

    document.querySelectorAll('[data-permission-role-defaults]').forEach((form) => {
        const select = form.querySelector('[data-permission-role-select]');
        if (!select) {
            return;
        }

        select.addEventListener('change', () => {
            const allowed = new Set(defaults[select.value] || []);
            form.querySelectorAll('input[name="permissions[]"]').forEach((input) => {
                input.checked = allowed.has(input.value);
            });
        });
    });
})();
</script>

<?php require __DIR__ . '/../includes/layout_end.php';
