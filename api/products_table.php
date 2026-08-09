<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_staff_or_admin();

header('Content-Type: application/json; charset=utf-8');

$search = normalize_search($_GET['search'] ?? '');
$categoryId = to_positive_int($_GET['category_id'] ?? null);
$brand = normalize_search($_GET['brand'] ?? '');

// Keep pagination values within expected bounds.
$page = max(1, to_positive_int($_GET['page'] ?? null) ?? 1);
$perPage = min(50, max(5, to_positive_int($_GET['per_page'] ?? null) ?? 10));

$filters = [];
$params = [];

$filters[] = 'p.is_active = 1';

if ($search !== '') {
    $filters[] = '(p.shoe_name LIKE :search1 OR p.product_code LIKE :search2 OR p.brand LIKE :search3 OR p.color LIKE :search4 OR p.size LIKE :search5)';
    $searchVal = '%' . $search . '%';
    $params['search1'] = $searchVal;
    $params['search2'] = $searchVal;
    $params['search3'] = $searchVal;
    $params['search4'] = $searchVal;
    $params['search5'] = $searchVal;
}

if ($categoryId !== null) {
    $filters[] = 'p.category_id = :category_id';
    $params['category_id'] = $categoryId;
}

if ($brand !== '') {
    $filters[] = 'p.brand = :brand';
    $params['brand'] = $brand;
}

$pdo = getPDO();

$whereSql = $filters ? 'WHERE ' . implode(' AND ', $filters) : '';
$countSql = 'SELECT COUNT(*) FROM products p ' . $whereSql;
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));

$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$listSql = 'SELECT p.id, p.product_code, p.shoe_name, p.brand, c.name AS category_name, p.size, p.color, p.purchase_price, p.selling_price, p.stock_quantity, p.image_path, p.date_added
            FROM products p
            JOIN categories c ON c.id = p.category_id
            ' . $whereSql . '
            ORDER BY p.date_added DESC
            LIMIT :limit OFFSET :offset';

$listStmt = $pdo->prepare($listSql);

foreach ($params as $key => $value) {
    $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
    $listStmt->bindValue(':' . $key, $value, $type);
}

$listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStmt->execute();
$rows = $listStmt->fetchAll();

$response = [
    'rows' => $rows,
    'meta' => [
        'page' => $page,
        'per_page' => $perPage,
        'total_rows' => $totalRows,
        'total_pages' => $totalPages,
    ],
    'low_stock_threshold' => LOW_STOCK_THRESHOLD,
];

echo json_encode($response, JSON_THROW_ON_ERROR);
