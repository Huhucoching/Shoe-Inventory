<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$errors = [];

if (is_post()) {
    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

    if (!has_valid_post_csrf()) {
        $errors[] = 'Invalid request token.';
    }

    if ($fullName === '' || $username === '' || $email === '' || $password === '') {
        $errors[] = 'All fields are required.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please provide a valid email address.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Password confirmation does not match.';
    }

    if (empty($errors)) {
        $check = getPDO()->prepare('SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1');
        $check->execute([
            'username' => $username,
            'email' => $email,
        ]);

        if ($check->fetch()) {
            $errors[] = 'Username or email is already in use.';
        }
    }

    if (empty($errors)) {
        $insert = getPDO()->prepare('INSERT INTO users (full_name, username, email, password_hash, role, is_active) VALUES (:full_name, :username, :email, :password_hash, :role, 1)');
        $insert->execute([
            'full_name' => $fullName,
            'username' => $username,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'staff',
        ]);

        set_flash('success', 'Staff account created successfully.');
        redirect(base_url('users/manage_staff.php'));
    }
}

$pageTitle = 'Register Staff';
$activeMenu = 'users';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card card-surface">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">New Staff Account</h2>
                    <a href="<?= e(base_url('users/manage_staff.php')) ?>" class="btn btn-outline-secondary btn-sm">Back</a>
                </div>

                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endforeach; ?>

                <form method="post" class="row g-3" novalidate autocomplete="off">
                    <?= csrf_input() ?>
                    <input type="text" name="fake_username" autocomplete="username" class="d-none" tabindex="-1" aria-hidden="true">
                    <input type="password" name="fake_password" autocomplete="current-password" class="d-none" tabindex="-1" aria-hidden="true">
                    <div class="col-md-6">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control" value="<?= e($_POST['full_name'] ?? '') ?>" required autocomplete="off">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" value="<?= e($_POST['username'] ?? '') ?>" required autocomplete="new-username" autocapitalize="off" spellcheck="false">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? '') ?>" required autocomplete="off" autocapitalize="off" spellcheck="false">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required autocomplete="new-password">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" required autocomplete="new-password">
                    </div>
                    <div class="col-12">
                        <button class="btn btn-success">Register Now</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php';
