<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_staff_or_admin();

$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
$productId = to_positive_int($_GET['product_id'] ?? null);
$categoryId = to_positive_int($_GET['category_id'] ?? null);

$where = [];
$params = [];

if ($dateFrom !== '') {
    $where[] = 'DATE(s.created_at) >= :date_from';
    $params['date_from'] = $dateFrom;
}

if ($dateTo !== '') {
    $where[] = 'DATE(s.created_at) <= :date_to';
    $params['date_to'] = $dateTo;
}

if ($productId !== null) {
    $where[] = 'si.product_id = :product_id';
    $params['product_id'] = $productId;
}

if ($categoryId !== null) {
    $where[] = 'p.category_id = :category_id';
    $params['category_id'] = $categoryId;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$pdo = getPDO();

$products = get_products_for_filter($pdo);

$categories = get_all_categories($pdo);

$sql = 'SELECT s.id AS sale_id, s.created_at, s.total_amount,
               p.product_code, p.shoe_name, p.brand,
               c.name AS category_name,
               si.quantity, si.price, si.total,
               u.full_name AS staff_name
        FROM sales_items si
        JOIN sales s ON s.id = si.sale_id
        JOIN products p ON p.id = si.product_id
        JOIN categories c ON c.id = p.category_id
        JOIN users u ON u.id = s.processed_by
        ' . $whereSql . '
        ORDER BY s.created_at DESC
        LIMIT 700';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();      

$totalRevenue = 0.0;
foreach ($rows as $row) {
    $totalRevenue += (float) $row['total'];
}

$pageTitle = 'Sales History';
$activeMenu = 'sales';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="card card-surface mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <h2 class="h5 mb-0">Sales History</h2>
        </div>

        <form class="row g-2" method="get" id="salesHistoryFiltersForm">
            <div class="col-md-2">
                <label class="form-label">Date From</label>
                <input type="date" name="date_from" id="dateFromFilter" class="form-control" value="<?= e($dateFrom) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Date To</label>
                <input type="date" name="date_to" id="dateToFilter" class="form-control" value="<?= e($dateTo) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Product</label>
                <select class="form-select" name="product_id" id="productFilter">
                    <option value="">All products</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= e((string) $product['id']) ?>" <?= $productId === (int) $product['id'] ? 'selected' : '' ?>>
                            <?= e($product['product_code']) ?> - <?= e($product['shoe_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Category</label>
                <select class="form-select" name="category_id" id="categoryFilter">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e((string) $category['id']) ?>" <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1 d-grid js-fallback-submit">
                <label class="form-label">&nbsp;</label>
                <button class="btn btn-outline-primary">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="stat-card p-3">
            <div class="stat-label">Total Line Items</div>
            <div class="stat-value"><?= e((string) count($rows)) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card p-3">
            <div class="stat-label">Revenue (Filtered)</div>
            <div class="stat-value"><?= e(format_currency($totalRevenue)) ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card p-3">
            <div class="stat-label">Distinct Sales</div>
            <div class="stat-value"><?= e((string) count(array_unique(array_column($rows, 'sale_id')))) ?></div>
        </div>
    </div>
</div>

<div class="table-panel">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
            <tr>
                <th>Sale ID</th>
                <th>Date</th>
                <th>Product</th>
                <th>Category</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Total</th>
                <th>Staff</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td>#<?= e((string) $row['sale_id']) ?></td>
                    <td><?= e(date('Y-m-d H:i', strtotime((string) $row['created_at']))) ?></td>
                    <td><?= e($row['product_code']) ?> - <?= e($row['shoe_name']) ?> (<?= e($row['brand']) ?>)</td>
                    <td><?= e($row['category_name']) ?></td>
                    <td><?= e((string) $row['quantity']) ?></td>
                    <td><?= e(format_currency((float) $row['price'])) ?></td>
                    <td><?= e(format_currency((float) $row['total'])) ?></td>
                    <td><?= e($row['staff_name']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($rows)): ?>
                <tr><td colspan="8" class="text-center text-secondary py-4">No sales records found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
    (function () {
        var filterForm = document.getElementById('salesHistoryFiltersForm');
        var dateFromFilter = document.getElementById('dateFromFilter');
        var dateToFilter = document.getElementById('dateToFilter');
        var productFilter = document.getElementById('productFilter');
        var categoryFilter = document.getElementById('categoryFilter');

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
