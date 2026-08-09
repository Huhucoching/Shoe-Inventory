<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_admin();


$errors = [];

if (is_post()) {
    $action = (string) ($_POST['action'] ?? '');

    if (!has_valid_post_csrf()) {
        $errors[] = 'Invalid request token.';
    } else {
        if ($action === 'update') {
            $staffId = to_positive_int($_POST['staff_id'] ?? null);
            $fullName = trim((string) ($_POST['full_name'] ?? ''));
            $username = trim((string) ($_POST['username'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if ($staffId === null || $fullName === '' || $username === '' || $email === '') {
                $errors[] = 'Please complete all required fields.';
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email address.';
            }

            if ($password !== '' && strlen($password) < 8) {
                $errors[] = 'New password must be at least 8 characters.';
            }

            if (empty($errors)) {
                $check = getPDO()->prepare('SELECT id FROM users WHERE (username = :username OR email = :email) AND id <> :id LIMIT 1');
                $check->execute([
                    'username' => $username,
                    'email' => $email,
                    'id' => $staffId,
                ]);

                if ($check->fetch()) {
                    $errors[] = 'Username or email already exists.';
                }
            }

            if (empty($errors)) {
                if ($password !== '') {
                    $update = getPDO()->prepare('UPDATE users SET full_name = :full_name, username = :username, email = :email, is_active = :is_active, password_hash = :password_hash WHERE id = :id AND role = :role');
                    $update->execute([
                        'full_name' => $fullName,
                        'username' => $username,
                        'email' => $email,
                        'is_active' => $isActive,
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'id' => $staffId,
                        'role' => 'staff',
                    ]);
                } else {
                    $update = getPDO()->prepare('UPDATE users SET full_name = :full_name, username = :username, email = :email, is_active = :is_active WHERE id = :id AND role = :role');
                    $update->execute([
                        'full_name' => $fullName,
                        'username' => $username,
                        'email' => $email,
                        'is_active' => $isActive,
                        'id' => $staffId,
                        'role' => 'staff',
                    ]);
                }

                set_flash('success', 'Staff account updated.');
                redirect(base_url('users/manage_staff.php'));
            }
        }
    }
}

$stmt = getPDO()->query('SELECT id, full_name, username, email, role, is_active, created_at FROM users WHERE role = "staff" ORDER BY id DESC');
$staffUsers = $stmt->fetchAll();

$pageTitle = 'Manage Staff';
$activeMenu = 'users';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 mb-0">Staff Accounts</h2>
    <a href="<?= e(base_url('users/register_staff.php')) ?>" class="btn btn-success btn-sm"><i class="bi bi-person-plus"></i> Register Staff</a>
</div>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endforeach; ?>

<div class="table-panel">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
            <tr>
                 <!-- <th>#</th> -->
                <th>Name</th>
                <th>Username</th>
                <th>Email</th>
                <th>Status</th>
                <th>Created</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($staffUsers)): ?>
                <tr><td colspan="7" class="text-center text-secondary py-4">No staff accounts found.</td></tr>
            <?php endif; ?>
            <?php foreach ($staffUsers as $staff): ?>
                <tr>
                        <!-- <td><?= e((string) $staff['id']) ?></td> -->
                    <td><?= e($staff['full_name']) ?></td>
                    <td><?= e($staff['username']) ?></td>
                    <td><?= e($staff['email']) ?></td>
                    <td>
                        <?php if ((int) $staff['is_active'] === 1): ?>
                            <span class="badge bg-success-subtle text-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-danger-subtle text-danger">Disabled</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e(date('Y-m-d', strtotime((string) $staff['created_at']))) ?></td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editStaff<?= e((string) $staff['id']) ?>">Edit</button>
                        <a href="<?= e(base_url('users/delete_staff.php?id=' . (int) $staff['id'])) ?>" class="btn btn-sm btn-outline-danger">Delete</a>
                    </td>
                </tr>

                <div class="modal fade" id="editStaff<?= e((string) $staff['id']) ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Staff - <?= e($staff['full_name']) ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="post">
                                <div class="modal-body">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="staff_id" value="<?= e((string) $staff['id']) ?>">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Full Name</label>
                                            <input type="text" name="full_name" class="form-control" value="<?= e($staff['full_name']) ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Username</label>
                                            <input type="text" name="username" class="form-control" value="<?= e($staff['username']) ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Email</label>
                                            <input type="email" name="email" class="form-control" value="<?= e($staff['email']) ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">New Password (optional)</label>
                                            <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current password">
                                        </div>
                                        <div class="col-12">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="is_active" id="active<?= e((string) $staff['id']) ?>" <?= (int) $staff['is_active'] === 1 ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="active<?= e((string) $staff['id']) ?>">Account is active</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php';
