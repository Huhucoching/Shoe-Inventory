<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

ensure_session_started();

function login_user(array $user): void
{
    $_SESSION['user_id'] = (int) $user['id'];
    session_regenerate_id(true);
}

/**
 * Clears auth state and destroys session data.
 */
function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 3600,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

function current_user(): ?array
{
    static $loaded = false;
    static $user = null;

    if ($loaded) {
        return $user;
    }

    $loaded = true;

    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = getPDO()->prepare('SELECT id, full_name, username, email, role, is_active FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => (int) $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || (int) $user['is_active'] !== 1) {
        logout_user();
        $user = null;
    }

    return $user;
}

function require_login(): void
{
    if (current_user() === null) {
        set_flash('warning', 'Please log in to continue.');
        redirect(base_url('auth/login.php'));
    }
}

function require_roles(array $roles): void
{
    require_login();
    $role = current_user()['role'] ?? '';

    if (!in_array($role, $roles, true)) {
        http_response_code(403);
        echo '403 | Access denied';
        exit;
    }
}

function require_admin(): void
{
    require_roles(['admin']);
}

function require_staff_or_admin(): void
{
    require_roles(['admin', 'staff']);
}

function is_admin(): bool
{
    $user = current_user();

    return $user !== null && $user['role'] === 'admin';
}
