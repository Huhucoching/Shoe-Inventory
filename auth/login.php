<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

if (current_user() !== null) {
    redirect(base_url('dashboard/dashboard.php'));
}

$errors = [];
$identity = '';

if (is_post()) {
    $identity = trim((string) ($_POST['identity'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!has_valid_post_csrf()) {
        $errors[] = 'Invalid request token. Please try again.';
    }

    if ($identity === '' || $password === '') {
        $errors[] = 'Username/Email and password are required.';
    }

    if (empty($errors)) {
        $sql = 'SELECT id, full_name, username, email, password_hash, role, is_active
                FROM users
                WHERE (username = :username OR email = :email)
                LIMIT 1';
        $stmt = getPDO()->prepare($sql);
        $stmt->execute([
            'username' => $identity,
            'email' => $identity,
        ]);
        $user = $stmt->fetch();

        if ($user && (int) $user['is_active'] === 1 && password_verify($password, $user['password_hash'])) {
            login_user($user);
            set_flash('success', 'Welcome back, ' . $user['full_name'] . '.');
            redirect(base_url('dashboard/dashboard.php'));
        }

        $errors[] = 'Invalid login credentials.';
    }
}

$bodyBgPath = __DIR__ . '/../assets/images/body-bg.jpg.png';
$bodyBgVersion = file_exists($bodyBgPath) ? (string) filemtime($bodyBgPath) : '1';
$bodyBgUrl = base_url('assets/images/body-bg.jpg.png?v=' . $bodyBgVersion);

$loginCardBgPath = __DIR__ . '/../assets/images/login-card-bg.jpg';
$loginCardBgVersion = file_exists($loginCardBgPath) ? (string) filemtime($loginCardBgPath) : '1';
$loginCardBgUrl = base_url('assets/images/login-card-bg.jpg?v=' . $loginCardBgVersion);

$brandLogoUrl = app_logo_url();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?= e(APP_NAME) ?></title>
    <?php if ($brandLogoUrl !== ''): ?>
        <link rel="icon" type="image/png" href="<?= e($brandLogoUrl) ?>">
        <link rel="apple-touch-icon" href="<?= e($brandLogoUrl) ?>">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Space+Grotesk:wght@500;600;700&family=Bebas+Neue&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/style.css')) ?>">
    <style>
        body.auth-page {
            min-height: 100vh;
            background-color: #0f172a;
            background-image: linear-gradient(160deg, rgba(2, 6, 23, 0.55) 0%, rgba(2, 6, 23, 0.38) 100%),
                url("<?= e($bodyBgUrl) ?>");
            background-position: center;
            background-size: cover;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }
        .auth-wrap {
            min-height: 100dvh;
            display: grid;
            place-items: center;
            padding: 1.5rem;
        }
        .auth-card {
            width: 100%;
            max-width: 500px;
            background-color: #0f172a;
            background-image: linear-gradient(160deg, rgba(2, 6, 23, 0.48) 0%, rgba(2, 6, 23, 0.36) 42%, rgba(2, 6, 23, 0.58) 100%),
                url("<?= e($loginCardBgUrl) ?>");
            background-position: center;
            background-size: cover;
            background-repeat: no-repeat;
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 1.25rem;
            box-shadow: 0 20px 42px rgba(15, 23, 42, 0.08);
        }
        .auth-card .auth-brand {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            color: #f8fafc;
            text-shadow: 0 2px 16px rgba(2, 6, 23, 0.6);
        }
        .auth-card .text-secondary {
            color: rgba(241, 245, 249, 0.92) !important;
            text-shadow: 0 2px 12px rgba(2, 6, 23, 0.55);
        }
        .auth-card .form-label {
            color: #f8fafc;
            font-weight: 500;
            text-shadow: 0 2px 10px rgba(2, 6, 23, 0.5);
        }
        .auth-card .form-control {
            background-color: rgba(148, 163, 184, 0.28);
            border-color: rgba(226, 232, 240, 0.45);
            color: #f8fafc;
            caret-color: #f8fafc;
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
        }
        .auth-card .form-control::placeholder {
            color: rgba(226, 232, 240, 0.74);
        }
        .auth-card .form-control:focus {
            background-color: rgba(148, 163, 184, 0.36);
            color: #ffffff;
            border-color: rgba(251, 191, 36, 0.78);
            box-shadow: 0 0 0 0.2rem rgba(249, 115, 22, 0.25);
        }
        .auth-card .form-control:-webkit-autofill,
        .auth-card .form-control:-webkit-autofill:hover,
        .auth-card .form-control:-webkit-autofill:focus,
        .auth-card .form-control:-webkit-autofill:active {
            -webkit-box-shadow: 0 0 0 1000px rgba(148, 163, 184, 0.36) inset !important;
            box-shadow: 0 0 0 1000px rgba(148, 163, 184, 0.36) inset !important;
            -webkit-text-fill-color: #f8fafc !important;
            transition: background-color 9999s ease-in-out 0s;
        }
        .auth-card .password-wrap {
            position: relative;
        }
        .auth-card .password-wrap .form-control {
            padding-right: 2.9rem;
        }
        .auth-card .password-toggle {
            position: absolute;
            top: 50%;
            right: 0.65rem;
            transform: translateY(-50%);
            width: 1.75rem;
            height: 1.75rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 999px;
            padding: 0;
            background: transparent;
            color: rgba(248, 250, 252, 0.86);
            z-index: 2;
            transition: color 0.2s ease, background-color 0.2s ease;
        }
        .auth-card .password-toggle:hover,
        .auth-card .password-toggle:focus {
            color: #ffffff;
            background-color: rgba(255, 255, 255, 0.14);
        }
        .auth-card .password-toggle:focus-visible {
            outline: 2px solid rgba(251, 191, 36, 0.8);
            outline-offset: 1px;
        }
        .auth-card .password-toggle i {
            font-size: 1.05rem;
            pointer-events: none;
        }
        .auth-card .btn-login {
            background: rgba(255, 255, 255, 0.22);
            border: 1px solid rgba(255, 255, 255, 0.52);
            color: #f8fafc;
            font-weight: 700;
            letter-spacing: 0.02em;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            box-shadow: 0 10px 24px rgba(2, 6, 23, 0.32), inset 0 1px 0 rgba(255, 255, 255, 0.35);
            transition: background-color 0.2s ease, border-color 0.2s ease, transform 0.2s ease, box-shadow 0.2s ease;
        }
        .auth-card .btn-login:hover,
        .auth-card .btn-login:focus {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.72);
            color: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 14px 28px rgba(2, 6, 23, 0.38), inset 0 1px 0 rgba(255, 255, 255, 0.42);
        }
        .auth-card .btn-login:disabled {
            background: rgba(255, 255, 255, 0.16);
            border-color: rgba(255, 255, 255, 0.28);
            color: rgba(248, 250, 252, 0.82);
            box-shadow: none;
            transform: none;
        }
        .site-logo {
            position: fixed;
                top: -3.4rem;
                right: 1.2rem;
            display: inline-flex;
            align-items: center;
            user-select: none;
            z-index: 100;
        }
        .site-logo img {
            display: block;
            width: 320px;
            height: auto;
            filter: drop-shadow(0 4px 18px rgba(0,0,0,0.7)) brightness(1.15) contrast(1.1);
        }
        .site-logo-fallback {
            display: none;
            font-family: 'Bebas Neue', sans-serif;
            font-size: 2.8rem;
            line-height: 1;
            letter-spacing: 0.06em;
            color: #ffffff;
            align-items: baseline;
            gap: 0.16em;
            text-shadow:
                2px 2px 0 rgba(0,0,0,0.55),
                0 0 18px rgba(249, 115, 22, 0.55),
                0 0 40px rgba(249, 115, 22, 0.28);
            transform: skewX(-6deg);
        }
        .site-logo-fallback .logo-triple {
            font-size: 0.58em;
            letter-spacing: 0.07em;
            line-height: 1;
        }
        .site-logo-fallback .logo-l {
            font-size: 1.4em;
            line-height: 1;
        }
        @media (max-width: 768px) {
            .site-logo {
                    top: -2.4rem;
                    right: 0.6rem;
            }
            .site-logo img {
                width: 190px;
            }
        }
    </style>
</head>
<body class="auth-page">
<div class="site-logo">
    <?php if ($brandLogoUrl !== ''): ?>
        <img src="<?= e($brandLogoUrl) ?>" alt="Triple L" onerror="this.style.display='none'; var fb = document.getElementById('siteLogoFallback'); if (fb) { fb.style.display='inline-flex'; }">
    <?php endif; ?>
    <span id="siteLogoFallback" class="site-logo-fallback"<?= $brandLogoUrl === '' ? ' style="display:inline-flex;"' : '' ?>><span class="logo-triple">triple</span><span class="logo-l">L</span></span>
</div>
<div class="auth-wrap">
    <div class="auth-card p-4 p-md-5">
        <div class="text-center mb-4">
            <h1 class="h4 auth-brand mb-1">Shoes Inventory System</h1>
            <p class="text-secondary mb-0">Sign in to continue</p>
        </div>

        <?php foreach ($errors as $error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endforeach; ?>

        <?php foreach (get_flash_messages() as $flash): ?>
            <?php $flashClass = $flash['type'] === 'warning' ? 'warning' : 'info'; ?>
            <div class="alert alert-<?= e($flashClass) ?>"><?= e($flash['message']) ?></div>
        <?php endforeach; ?>

        <form method="post" novalidate autocomplete="off">
            <?= csrf_input() ?>
            <div class="mb-3">
                <label class="form-label">Username or Email</label>
                <input type="text" class="form-control" name="identity" value="<?= e($identity) ?>" required autocomplete="off">
            </div>
            <div class="mb-4">
                <label class="form-label">Password</label>
                <div class="password-wrap">
                    <input id="passwordInput" type="password" class="form-control" name="password" required autocomplete="new-password">
                    <button id="passwordToggle" class="password-toggle" type="button" aria-label="Show password" aria-pressed="false" title="Show password">
                        <i class="bi bi-eye-fill" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
            <button id="loginBtn" class="btn btn-login w-100">Login</button>
        </form>
    </div>
</div>
<script>
    var form = document.querySelector('form');
    var loginBtn = document.getElementById('loginBtn');

    if (form) {
        form.addEventListener('submit', function () {
            if (!loginBtn) {
                return;
            }

            loginBtn.disabled = true;
            loginBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Logging in…';
        });
    }

    var passwordInput = document.getElementById('passwordInput');
    var passwordToggle = document.getElementById('passwordToggle');
    var passwordIcon = passwordToggle ? passwordToggle.querySelector('i') : null;

    function setPasswordVisibility(isVisible) {
        passwordInput.type = isVisible ? 'text' : 'password';
        passwordIcon.className = isVisible ? 'bi bi-eye-slash-fill' : 'bi bi-eye-fill';
        passwordToggle.setAttribute('aria-label', isVisible ? 'Hide password' : 'Show password');
        passwordToggle.setAttribute('title', isVisible ? 'Hide password' : 'Show password');
        passwordToggle.setAttribute('aria-pressed', isVisible ? 'true' : 'false');
    }

    if (passwordInput && passwordToggle && passwordIcon) {
        setPasswordVisibility(passwordInput.type === 'text');

        passwordToggle.addEventListener('click', function () {
            setPasswordVisibility(passwordInput.type === 'password');
        });
    }
</script>
</body>
</html>
