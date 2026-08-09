<?php
declare(strict_types=1);

/**
 * Starts a session if one is not already active.
 */
function ensure_session_started(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function base_url(string $path = ''): string
{
    $base = rtrim(BASE_URL, '/');

    if ($path === '') {
        return $base;
    }

    return $base . '/' . ltrim($path, '/');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function set_flash(string $type, string $message): void
{
    ensure_session_started();
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message,
    ];
}

function get_flash_messages(): array
{
    ensure_session_started();
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);

    return $messages;
}

/**
 * Generates and stores a CSRF token per session.
 */
function csrf_token(): string
{
    ensure_session_started();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(?string $token): bool
{
    ensure_session_started();

    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function post_csrf_token(): ?string
{
    $token = $_POST['csrf_token'] ?? null;

    return is_string($token) ? $token : null;
}

function has_valid_post_csrf(): bool
{
    return verify_csrf(post_csrf_token());
}

function to_positive_int(mixed $value): ?int
{
    if (is_string($value) && trim($value) === '') {
        return null;
    }

    $result = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    return $result === false ? null : (int) $result;
}

function to_non_negative_int(mixed $value): ?int
{
    if (is_string($value) && trim($value) === '') {
        return null;
    }

    $result = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

    return $result === false ? null : (int) $result;
}

function to_decimal(mixed $value): ?float
{
    if (is_string($value)) {
        $value = str_replace(',', '', trim($value));
    }

    if ($value === '' || $value === null) {
        return null;
    }

    if (!is_numeric($value)) {
        return null;
    }

    return round((float) $value, 2);
}

function format_currency(float $value): string
{
    return '₱' . number_format($value, 2);
}

function normalize_search(?string $value): string
{
    return trim((string) $value);
}

/**
 * Returns cache-busted app logo URL when any known logo file exists.
 * Checks logo files in priority order within assets/images directory.
 */
function app_logo_url(): string
{
    $candidates = [
        'triple-l-logo.png',
        'triple-l-logo.webp',
        'triple-l-logo.jpg',
        'triple-l-logo.jpeg',
        'triplel-logo.png',
        'triplel-logo.webp',
        'triplel-logo.jpg',
        'triplel-logo.jpeg',
        'logo.png',
        'logo.webp',
        'logo.jpg',
        'logo.jpeg',
    ];

    foreach ($candidates as $logoFile) {
        $logoPath = __DIR__ . '/../assets/images/' . $logoFile;
        if (file_exists($logoPath)) {
            return base_url('assets/images/' . $logoFile . '?v=' . filemtime($logoPath));
        }
    }

    return '';
}

/**
 * Fetch all products for selection with current stock quantities.
 *
 * @param PDO $pdo Database connection
 * @return array<int, array>
 */
function get_products_for_stock(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, product_code, shoe_name, brand, stock_quantity FROM products WHERE is_active = 1 ORDER BY shoe_name ASC');
    return $stmt->fetchAll();
}

/**
 * Fetch all categories ordered by name.
 *
 * @param PDO $pdo Database connection
 * @return array<int, array>
 */
function get_all_categories(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, name FROM categories ORDER BY name ASC');
    return $stmt->fetchAll();
}

/**
 * Fetch products for filter dropdowns (id, code, name only).
 *
 * @param PDO $pdo Database connection
 * @return array<int, array>
 */
function get_products_for_filter(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, product_code, shoe_name FROM products WHERE is_active = 1 ORDER BY shoe_name ASC');
    return $stmt->fetchAll();
}

/**
 * Fetch all distinct brands ordered alphabetically.
 *
 * @param PDO $pdo Database connection
 * @return array<int, array>
 */
function get_all_brands(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT DISTINCT brand FROM products WHERE is_active = 1 ORDER BY brand ASC');
    return $stmt->fetchAll();
}

/**
 * Records a stock movement (in or out) with transaction safety.
 * Validates product existence and (for OUT) sufficient quantity.
 *
 * @param PDO $pdo Database connection
 * @param int $productId Product ID to adjust
 * @param int $quantity Quantity to adjust
 * @param string $movementType 'IN' or 'OUT'
 * @param string $reason Movement reason for audit log
 * @param int $staffId Staff member performing the movement
 *
 * @throws RuntimeException if product not found, insufficient stock (OUT only), or transaction fails
 */
function record_stock_movement(PDO $pdo, int $productId, int $quantity, string $movementType, string $reason, int $staffId): void
{
    $pdo->beginTransaction();

    try {
        // Lock and fetch current stock
        $lock = $pdo->prepare('SELECT id, stock_quantity FROM products WHERE id = :id LIMIT 1 FOR UPDATE');
        $lock->execute(['id' => $productId]);
        $product = $lock->fetch();

        if (!$product) {
            throw new RuntimeException('Product not found.');
        }

        // Check sufficient stock for OUT movements
        if ($movementType === 'OUT' && (int) $product['stock_quantity'] < $quantity) {
            throw new RuntimeException('Insufficient stock.');
        }

        // Update stock based on movement type
        $operation = ($movementType === 'IN') ? '+' : '-';
        $update = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity {$operation} :quantity WHERE id = :id");
        $update->execute([
            'quantity' => $quantity,
            'id' => $productId,
        ]);

        // Record movement
        $movement = $pdo->prepare('INSERT INTO stock_movements (product_id, quantity, movement_type, reason, staff_id, created_at) VALUES (:product_id, :quantity, :movement_type, :reason, :staff_id, NOW())');
        $movement->execute([
            'product_id' => $productId,
            'quantity' => $quantity,
            'movement_type' => $movementType,
            'reason' => $reason !== '' ? $reason : ($movementType === 'IN' ? 'Stock replenishment' : 'Manual stock out'),
            'staff_id' => $staffId,
        ]);

        $pdo->commit();
    } catch (Throwable $throwable) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $throwable;
    }
}
