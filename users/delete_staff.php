<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$staffId = to_positive_int($_GET['id'] ?? null);

if ($staffId === null) {
    set_flash('error', 'Invalid staff account selected.');
    redirect(base_url('users/manage_staff.php'));
}

$pdo = getPDO();
$stmt = $pdo->prepare('SELECT id, full_name, username, email, role, is_active, created_at FROM users WHERE id = :id AND role = :role LIMIT 1');
$stmt->execute([
    'id' => $staffId,
    'role' => 'staff',
]);
$staff = $stmt->fetch();

if (!$staff) {
    set_flash('error', 'Staff account not found.');
    redirect(base_url('users/manage_staff.php'));
}

if (is_post()) {
    if (!has_valid_post_csrf()) {
        set_flash('error', 'Invalid request token.');
        redirect(base_url('users/manage_staff.php'));
    }

    $delete = $pdo->prepare('DELETE FROM users WHERE id = :id AND role = :role');
    $delete->execute([
        'id' => $staffId,
        'role' => 'staff',
    ]);

    set_flash('success', 'Staff account "' . $staff['full_name'] . '" has been deleted.');
    redirect(base_url('users/manage_staff.php'));
}

$pageTitle = 'Delete Staff';
$pageSubtitle = 'Deleting staff confirmation';
$activeMenu = 'users';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-xl-6 col-lg-7">
        <div class="card card-surface border-danger">
            <div class="card-body p-4">
                <h2 class="h5 mb-1 text-danger">Delete Staff Account</h2>
                <p class="text-secondary mb-4">This action permanently removes the staff account from the system.</p>

                <div class="rounded p-3 mb-4" style="background:var(--bs-light,#f8f9fa);">
                    <div class="fw-semibold mb-1"><?= e($staff['full_name']) ?></div>
                    <div class="text-secondary small">Username: <?= e($staff['username']) ?></div>
                    <div class="text-secondary small">Email: <?= e($staff['email']) ?></div>
                    <div class="text-secondary small">Created: <?= e(date('Y-m-d', strtotime((string) $staff['created_at']))) ?></div>
                </div>

                <p class="mb-4">Are you sure you want to delete <strong><?= e($staff['full_name']) ?></strong>?</p>

                <form method="post">
                    <?= csrf_input() ?>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-danger">Yes, Delete Staff</button>
                        <a href="<?= e(base_url('users/manage_staff.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php';
