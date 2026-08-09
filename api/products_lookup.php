<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_staff_or_admin();

header('Content-Type: application/json; charset=utf-8');

$stmt = getPDO()->query('SELECT id, product_code, shoe_name, brand, size, color, selling_price, stock_quantity FROM products WHERE is_active = 1 ORDER BY shoe_name ASC');
$products = $stmt->fetchAll();

echo json_encode(['rows' => $products], JSON_THROW_ON_ERROR);
