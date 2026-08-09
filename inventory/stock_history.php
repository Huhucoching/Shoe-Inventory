<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_staff_or_admin();

$movementType = strtoupper(trim((string) ($_GET['movement_type'] ?? '')));
$productId = to_positive_int($_GET['product_id'] ?? null);
$categoryId = to_positive_int($_GET['category_id'] ?? null);
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));

$where = [];
$params = [];

if (in_array($movementType, ['IN', 'OUT'], true)) {
    $where[] = 'UPPER(TRIM(sm.movement_type)) = :movement_type';
    $params['movement_type'] = $movementType;
}

if ($productId !== null) {
    $where[] = 'sm.product_id = :product_id';
    $params['product_id'] = $productId;
}

if ($categoryId !== null) {
    $where[] = 'p.category_id = :category_id';
    $params['category_id'] = $categoryId;
}

if ($dateFrom !== '') {
    $where[] = 'DATE(sm.created_at) >= :date_from';
    $params['date_from'] = $dateFrom;
}

if ($dateTo !== '') {
    $where[] = 'DATE(sm.created_at) <= :date_to';
    $params['date_to'] = $dateTo;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$pdo = getPDO();

$products = get_products_for_filter($pdo);

$categories = get_all_categories($pdo);

$sql = 'SELECT sm.id, sm.quantity, sm.movement_type, sm.reason, sm.created_at,
               p.product_code, p.shoe_name, p.brand,
               c.name AS category_name,
               u.full_name AS staff_name
        FROM stock_movements sm
        JOIN products p ON p.id = sm.product_id
        JOIN categories c ON c.id = p.category_id
        JOIN users u ON u.id = sm.staff_id
        ' . $whereSql . '
        ORDER BY sm.created_at DESC
        LIMIT 500';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$movements = $stmt->fetchAll();

$pageTitle = 'Stock Movement History';
$activeMenu = 'inventory';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="card card-surface mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <h2 class="h5 mb-0">Stock Movement History</h2>
            <div class="d-flex gap-2">
                <a href="<?= e(base_url('inventory/stock_in.php')) ?>" class="btn btn-success btn-sm">Stock In</a>
                <a href="<?= e(base_url('inventory/stock_out.php')) ?>" class="btn btn-danger btn-sm">Stock Out</a>
            </div>
        </div>

        <form class="row g-2" method="get" id="stockHistoryFiltersForm">
            <div class="col-md-2">
                <label class="form-label">Type</label>
                <select class="form-select" name="movement_type" id="movementTypeFilter">
                    <option value="">All</option>
                    <option value="IN" <?= $movementType === 'IN' ? 'selected' : '' ?>>IN</option>
                    <option value="OUT" <?= $movementType === 'OUT' ? 'selected' : '' ?>>OUT</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Product</label>
                <select class="form-select" name="product_id" id="productFilter">
                    <option value="">All</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= e((string) $product['id']) ?>" <?= $productId === (int) $product['id'] ? 'selected' : '' ?>>
                            <?= e($product['product_code']) ?> - <?= e($product['shoe_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Category</label>
                <select class="form-select" name="category_id" id="categoryFilter">
                    <option value="">All</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e((string) $category['id']) ?>" <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Date From</label>
                <input type="date" name="date_from" id="dateFromFilter" class="form-control" value="<?= e($dateFrom) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Date To</label>
                <input type="date" name="date_to" id="dateToFilter" class="form-control" value="<?= e($dateTo) ?>">
            </div>
            <div class="col-md-1 d-grid js-fallback-submit">
                <label class="form-label">&nbsp;</label>
                <button class="btn btn-outline-primary">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="table-panel">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Product</th>
                <th>Category</th>
                <th>Quantity</th>
                <th>Reason</th>
                <th>Staff</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($movements as $movement): ?>
                <tr>
                    <td><?= e(date('Y-m-d H:i', strtotime((string) $movement['created_at']))) ?></td>
                    <td>
                        <?php if (strtoupper((string) $movement['movement_type']) === 'IN'): ?>
                            <span class="badge bg-success-subtle text-success">IN</span>
                        <?php else: ?>
                            <span class="badge bg-danger-subtle text-danger">OUT</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($movement['product_code']) ?> - <?= e($movement['shoe_name']) ?> (<?= e($movement['brand']) ?>)</td>
                    <td><?= e($movement['category_name']) ?></td>
                    <td><?= e((string) $movement['quantity']) ?></td>
                    <td><?= e((string) $movement['reason']) ?></td>
                    <td><?= e($movement['staff_name']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($movements)): ?>
                <tr><td colspan="7" class="text-center text-secondary py-4">No stock movement records found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
    (function () {
        var filterForm = document.getElementById('stockHistoryFiltersForm');
        var dateFromFilter = document.getElementById('dateFromFilter');
        var dateToFilter = document.getElementById('dateToFilter');
        var productFilter = document.getElementById('productFilter');
        var categoryFilter = document.getElementById('categoryFilter');
        var movementTypeFilter = document.getElementById('movementTypeFilter');

        if (!filterForm) {
            return;
        }

        var fallbackSubmit = filterForm.querySelector('.js-fallback-submit');
        if (fallbackSubmit) {
            fallbackSubmit.classList.add('d-none');
        }

        function submitFilters() {
            filterForm.submit();
        }

        if (movementTypeFilter) {
            movementTypeFilter.addEventListener('change', submitFilters);
        }

        if (productFilter) {
            productFilter.addEventListener('change', function () {
                if (categoryFilter) {
                    categoryFilter.value = '';
                }

                submitFilters();
            });
        }

        if (categoryFilter) {
            categoryFilter.addEventListener('change', function () {
                if (productFilter) {
                    productFilter.value = '';
                }

                submitFilters();
            });
        }

        if (dateFromFilter) {
            dateFromFilter.addEventListener('change', submitFilters);
        }

        if (dateToFilter) {
            dateToFilter.addEventListener('change', submitFilters);
        }
    })();
</script>
<?php require_once __DIR__ . '/../includes/footer.php';
