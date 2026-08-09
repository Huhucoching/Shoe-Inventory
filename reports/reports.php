<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_staff_or_admin();

$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
$productId = to_positive_int($_GET['product_id'] ?? null);
$categoryId = to_positive_int($_GET['category_id'] ?? null);

$exportPdfUrl = base_url('reports/export_pdf.php?' . http_build_query([
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'product_id' => $productId,
    'category_id' => $categoryId,
]));

$pdo = getPDO();

$products = get_products_for_filter($pdo);

$categories = get_all_categories($pdo);

$salesWhere = [];
$salesParams = [];

if ($dateFrom !== '') {
    $salesWhere[] = 'DATE(s.created_at) >= :date_from';
    $salesParams['date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $salesWhere[] = 'DATE(s.created_at) <= :date_to';
    $salesParams['date_to'] = $dateTo;
}
if ($productId !== null) {
    $salesWhere[] = 'si.product_id = :product_id';
    $salesParams['product_id'] = $productId;
}
if ($categoryId !== null) {
    $salesWhere[] = 'p.category_id = :category_id';
    $salesParams['category_id'] = $categoryId;
}

$salesWhereSql = $salesWhere ? 'WHERE ' . implode(' AND ', $salesWhere) : '';

$dailySql = 'SELECT DATE(s.created_at) AS sale_date, COUNT(DISTINCT s.id) AS sale_count, SUM(si.total) AS total_sales
             FROM sales_items si
             JOIN sales s ON s.id = si.sale_id
             JOIN products p ON p.id = si.product_id
             ' . $salesWhereSql . '
             GROUP BY DATE(s.created_at)
             ORDER BY sale_date DESC
             LIMIT 31';
$dailyStmt = $pdo->prepare($dailySql);
$dailyStmt->execute($salesParams);
$dailyRows = $dailyStmt->fetchAll();

$monthlySql = 'SELECT DATE_FORMAT(s.created_at, "%Y-%m") AS sale_month, COUNT(DISTINCT s.id) AS sale_count, SUM(si.total) AS total_sales
               FROM sales_items si
               JOIN sales s ON s.id = si.sale_id
               JOIN products p ON p.id = si.product_id
               ' . $salesWhereSql . '
               GROUP BY DATE_FORMAT(s.created_at, "%Y-%m")
               ORDER BY sale_month DESC
               LIMIT 12';
$monthlyStmt = $pdo->prepare($monthlySql);
$monthlyStmt->execute($salesParams);
$monthlyRows = $monthlyStmt->fetchAll();

$productWhere = [];
$productParams = [];

if ($productId !== null) {
    $productWhere[] = 'p.id = :product_id';
    $productParams['product_id'] = $productId;
}
if ($categoryId !== null) {
    $productWhere[] = 'p.category_id = :category_id';
    $productParams['category_id'] = $categoryId;
}
if ($dateFrom !== '') {
    $productWhere[] = 'DATE(p.date_added) >= :date_from';
    $productParams['date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $productWhere[] = 'DATE(p.date_added) <= :date_to';
    $productParams['date_to'] = $dateTo;
}

$productWhereSql = $productWhere ? 'WHERE ' . implode(' AND ', $productWhere) : '';

$inventoryValueSql = 'SELECT p.product_code, p.shoe_name, p.brand, c.name AS category_name,
                             p.stock_quantity, p.purchase_price, p.selling_price,
                             (p.stock_quantity * p.purchase_price) AS stock_cost,
                             (p.stock_quantity * p.selling_price) AS stock_value
                      FROM products p
                      JOIN categories c ON c.id = p.category_id
                      ' . $productWhereSql . '
                      ORDER BY p.shoe_name ASC';
$inventoryValueStmt = $pdo->prepare($inventoryValueSql);
$inventoryValueStmt->execute($productParams);
$inventoryValueRows = $inventoryValueStmt->fetchAll();

$lowStockSql = 'SELECT p.product_code, p.shoe_name, p.brand, c.name AS category_name, p.stock_quantity
                FROM products p
                JOIN categories c ON c.id = p.category_id
                ' . ($productWhere ? $productWhereSql . ' AND p.stock_quantity <= :threshold' : 'WHERE p.stock_quantity <= :threshold') . '
                ORDER BY p.stock_quantity ASC';
$lowStockParams = $productParams;
$lowStockParams['threshold'] = LOW_STOCK_THRESHOLD;
$lowStockStmt = $pdo->prepare($lowStockSql);
$lowStockStmt->execute($lowStockParams);
$lowStockRows = $lowStockStmt->fetchAll();

$movementWhere = [];
$movementParams = [];
if ($dateFrom !== '') {
    $movementWhere[] = 'DATE(sm.created_at) >= :date_from';
    $movementParams['date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $movementWhere[] = 'DATE(sm.created_at) <= :date_to';
    $movementParams['date_to'] = $dateTo;
}
if ($productId !== null) {
    $movementWhere[] = 'sm.product_id = :product_id';
    $movementParams['product_id'] = $productId;
}
if ($categoryId !== null) {
    $movementWhere[] = 'p.category_id = :category_id';
    $movementParams['category_id'] = $categoryId;
}

$movementWhereSql = $movementWhere ? 'WHERE ' . implode(' AND ', $movementWhere) : '';
$movementSql = 'SELECT sm.created_at, sm.movement_type, sm.quantity, sm.reason,
                       p.product_code, p.shoe_name, c.name AS category_name, u.full_name AS staff_name
                FROM stock_movements sm
                JOIN products p ON p.id = sm.product_id
                JOIN categories c ON c.id = p.category_id
                JOIN users u ON u.id = sm.staff_id
                ' . $movementWhereSql . '
                ORDER BY sm.created_at DESC
                LIMIT 200';
$movementStmt = $pdo->prepare($movementSql);
$movementStmt->execute($movementParams);
$movementRows = $movementStmt->fetchAll();

$topSellingSql = 'SELECT p.brand, SUM(si.quantity) AS qty, MAX(c2.name) AS category_name
                  FROM sales_items si
                  JOIN sales s ON s.id = si.sale_id
                  JOIN products p ON p.id = si.product_id
                  LEFT JOIN categories c2 ON c2.id = p.category_id
                  ' . ($salesWhere ? 'JOIN categories c ON c.id = p.category_id ' . $salesWhereSql : '') . '
                  GROUP BY p.brand
                  ORDER BY qty DESC
                  LIMIT 8';
$topSellingStmt = $pdo->prepare($topSellingSql);
$topSellingStmt->execute($salesParams);
$topSellingRows = $topSellingStmt->fetchAll();

$salesDistributionSql = 'SELECT c.name AS category_name, SUM(si.total) AS revenue
                         FROM sales_items si
                         JOIN sales s ON s.id = si.sale_id
                         JOIN products p ON p.id = si.product_id
                         JOIN categories c ON c.id = p.category_id
                         ' . $salesWhereSql . '
                         GROUP BY c.id, c.name
                         ORDER BY revenue DESC';
$salesDistributionStmt = $pdo->prepare($salesDistributionSql);
$salesDistributionStmt->execute($salesParams);
$salesDistributionRows = $salesDistributionStmt->fetchAll();

$inventoryCategorySql = 'SELECT c.name AS category_name, SUM(p.stock_quantity) AS stock_total
                         FROM categories c
                         LEFT JOIN products p ON p.category_id = c.id
                         GROUP BY c.id, c.name
                         ORDER BY c.name ASC';
$inventoryCategoryRows = $pdo->query($inventoryCategorySql)->fetchAll();

$monthlyChartRows = array_reverse($monthlyRows);

$pageTitle = 'Reports & Analytics';
$activeMenu = 'reports';
$mainClass = 'reports-main';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="reports-filters-sticky">
    <div class="card card-surface mb-3">
        <div class="card-body">
            <h2 class="h5 mb-3">Report Filters</h2>
            <form method="get" class="row g-2" id="reportFiltersForm">
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
                    <select name="product_id" id="productFilter" class="form-select">
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
                    <select name="category_id" id="categoryFilter" class="form-select">
                        <option value="">All categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= e((string) $category['id']) ?>" <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1 d-grid js-fallback-submit">
                    <label class="form-label">&nbsp;</label>
                    <button class="btn btn-outline-primary">Apply</button>
                </div>
                <div class="col-md-2 d-grid">
                    <label class="form-label">&nbsp;</label>
                    <a href="<?= e($exportPdfUrl) ?>" target="_blank" rel="noopener" class="btn btn-outline-danger no-print">Export PDF</a>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="reports-scroll-area">

<div class="row g-3 mb-3">
    <div class="col-xl-6">
        <div class="card card-surface h-100">
            <div class="card-body">
                <h2 class="h6 mb-3">Monthly Sales Trend</h2>
                <div class="chart-canvas-wrap">
                    <canvas id="reportMonthlySales"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card card-surface h-100">
            <div class="card-body">
                <h2 class="h6 mb-3">Top Selling Shoes</h2>
                <div class="chart-canvas-wrap">
                    <canvas id="reportTopSelling"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card card-surface h-100">
            <div class="card-body">
                <h2 class="h6 mb-3">Inventory per Category</h2>
                <div class="chart-canvas-wrap">
                    <canvas id="reportInventoryCategory"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card card-surface h-100">
            <div class="card-body">
                <h2 class="h6 mb-3">Sales Distribution</h2>
                <div class="chart-canvas-wrap">
                    <canvas id="reportSalesDistribution"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12">
        <div class="table-panel">
            <div class="p-3 border-bottom"><strong>Daily Sales Report</strong></div>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead><tr><th>Date</th><th>Sales Count</th><th>Total Sales</th></tr></thead>
                    <tbody>
                    <?php foreach ($dailyRows as $row): ?>
                        <tr>
                            <td><?= e((string) $row['sale_date']) ?></td>
                            <td><?= e((string) $row['sale_count']) ?></td>
                            <td><?= e(format_currency((float) $row['total_sales'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($dailyRows)): ?>
                        <tr><td colspan="3" class="text-center text-secondary py-3">No daily sales data.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="table-panel">
            <div class="p-3 border-bottom"><strong>Monthly Sales Report</strong></div>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead><tr><th>Month</th><th>Sales Count</th><th>Total Sales</th></tr></thead>
                    <tbody>
                    <?php foreach ($monthlyRows as $row): ?>
                        <tr>
                            <td><?= e((string) $row['sale_month']) ?></td>
                            <td><?= e((string) $row['sale_count']) ?></td>
                            <td><?= e(format_currency((float) $row['total_sales'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($monthlyRows)): ?>
                        <tr><td colspan="3" class="text-center text-secondary py-3">No monthly sales data.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="table-panel">
            <div class="p-3 border-bottom"><strong>Inventory Value Report</strong></div>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Stock</th>
                        <th>Purchase Price</th>
                        <th>Selling Price</th>
                        <th>Stock Cost</th>
                        <th>Stock Value</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($inventoryValueRows as $row): ?>
                        <tr>
                            <td><?= e($row['product_code']) ?> - <?= e($row['shoe_name']) ?> (<?= e($row['brand']) ?>)</td>
                            <td><?= e($row['category_name']) ?></td>
                            <td><?= e((string) $row['stock_quantity']) ?></td>
                            <td><?= e(format_currency((float) $row['purchase_price'])) ?></td>
                            <td><?= e(format_currency((float) $row['selling_price'])) ?></td>
                            <td><?= e(format_currency((float) $row['stock_cost'])) ?></td>
                            <td><?= e(format_currency((float) $row['stock_value'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($inventoryValueRows)): ?>
                        <tr><td colspan="7" class="text-center text-secondary py-3">No inventory value data.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="table-panel">
            <div class="p-3 border-bottom"><strong>Low Stock Report (Threshold: <?= e((string) LOW_STOCK_THRESHOLD) ?>)</strong></div>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead><tr><th>Product</th><th>Category</th><th>Stock</th></tr></thead>
                    <tbody>
                    <?php foreach ($lowStockRows as $row): ?>
                        <tr>
                            <td><?= e($row['product_code']) ?> - <?= e($row['shoe_name']) ?> (<?= e($row['brand']) ?>)</td>
                            <td><?= e($row['category_name']) ?></td>
                            <td><span class="badge badge-stock-low"><?= e((string) $row['stock_quantity']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($lowStockRows)): ?>
                        <tr><td colspan="3" class="text-center text-secondary py-3">No low stock products found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="table-panel">
            <div class="p-3 border-bottom"><strong>Stock Movement History</strong></div>
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead><tr><th>Date</th><th>Type</th><th>Product</th><th>Category</th><th>Qty</th><th>Reason</th><th>Staff</th></tr></thead>
                    <tbody>
                    <?php foreach ($movementRows as $row): ?>
                        <tr>
                            <td><?= e(date('Y-m-d H:i', strtotime((string) $row['created_at']))) ?></td>
                            <td><?= e($row['movement_type']) ?></td>
                            <td><?= e($row['product_code']) ?> - <?= e($row['shoe_name']) ?></td>
                            <td><?= e($row['category_name']) ?></td>
                            <td><?= e((string) $row['quantity']) ?></td>
                            <td><?= e((string) $row['reason']) ?></td>
                            <td><?= e($row['staff_name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($movementRows)): ?>
                        <tr><td colspan="7" class="text-center text-secondary py-3">No stock movement records.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</div>

<script>
    (function () {
        var filterForm = document.getElementById('reportFiltersForm');
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
                if (categoryFilter && categoryFilter.value !== '') {
                    categoryFilter.value = '';
                }

                submitFilters();
            });
        }

        if (categoryFilter) {
            categoryFilter.addEventListener('change', function () {
                if (productFilter && productFilter.value !== '') {
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

<?php
$chartData = [
    'monthlySales' => [
        'labels' => array_map(
            static fn(array $row): string => date('M Y', strtotime($row['sale_month'] . '-01')),
            $monthlyChartRows
        ),
        'values' => array_map(static fn(array $row): float => (float) $row['total_sales'], $monthlyChartRows),
    ],
    'topSelling' => [
        'labels' => array_map(static fn(array $row): string => (string) $row['brand'], $topSellingRows),
        'values' => array_map(static fn(array $row): int => (int) $row['qty'], $topSellingRows),
        'category_names' => array_map(static fn(array $row): string => (string) ($row['category_name'] ?? ''), $topSellingRows),
    ],
    'inventoryCategory' => [
        'labels' => array_map(static fn(array $row): string => (string) $row['category_name'], $inventoryCategoryRows),
        'values' => array_map(static fn(array $row): int => (int) ($row['stock_total'] ?? 0), $inventoryCategoryRows),
    ],
    'salesDistribution' => [
        'labels' => array_map(static fn(array $row): string => (string) $row['category_name'], $salesDistributionRows),
        'values' => array_map(static fn(array $row): float => (float) ($row['revenue'] ?? 0), $salesDistributionRows),
    ],
];

$extraScripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
(() => {
    const data = ' . json_encode($chartData, JSON_THROW_ON_ERROR) . ';
    const palette = ["#0f766e", "#f59e0b", "#1d4ed8", "#d15050", "#06b6d4", "#16a34a", "#fb7185", "#8b5cf6" , "#213b4c"];

    function colorForLabel(label) {
        let hash = 0;
        for (let i = 0; i < label.length; i++) {
            hash = (hash * 10 + label.charCodeAt(i)) >>> 0;
        }
        return palette[hash % palette.length];
    }

    const lineOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(ctx) {
                        return " \u20B1" + Number(ctx.parsed.y).toLocaleString("en-PH", { minimumFractionDigits: 2 });
                    }
                }
            }
        },
        scales: {
            x: { ticks: { maxRotation: 45, autoSkip: true, maxTicksLimit: 12 }, grid: { display: false } },
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(v) {
                        return v >= 1000 ? "\u20B1" + (v / 1000).toFixed(0) + "k" : "\u20B1" + v;
                    }
                }
            },
        },
    };

    new Chart(document.getElementById("reportMonthlySales"), {
        type: "line",
        data: {
            labels: data.monthlySales.labels,
            datasets: [{
                data: data.monthlySales.values,
                borderColor: "#0f766e",
                backgroundColor: "rgba(15, 118, 110, 0.12)",
                fill: true,
                tension: 0.3,
                pointRadius: data.monthlySales.values.map(function(v) { return v > 0 ? 5 : 0; }),
                pointHoverRadius: data.monthlySales.values.map(function(v) { return v > 0 ? 7 : 0; }),
                pointBackgroundColor: "#0f766e",
                spanGaps: true,
            }],
        },
        options: lineOptions,
    });

    new Chart(document.getElementById("reportTopSelling"), {
        type: "bar",
        data: {
            labels: data.topSelling.labels,
            datasets: [{ data: data.topSelling.values, backgroundColor: (data.topSelling.category_names || []).map(colorForLabel), borderRadius: 8 }],
        },
        options: {
            indexAxis: "y",
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true } },
        },
    });

    new Chart(document.getElementById("reportInventoryCategory"), {
        type: "doughnut",
        data: {
            labels: data.inventoryCategory.labels,
            datasets: [{ data: data.inventoryCategory.values, backgroundColor: data.inventoryCategory.labels.map(colorForLabel) }],
        },
        options: { responsive: true, maintainAspectRatio: false },
    });

    new Chart(document.getElementById("reportSalesDistribution"), {
        type: "pie",
        data: {
            labels: data.salesDistribution.labels,
            datasets: [{ data: data.salesDistribution.values, backgroundColor: data.salesDistribution.labels.map(colorForLabel) }],
        },
        options: { responsive: true, maintainAspectRatio: false },
    });
})();
</script>';
require_once __DIR__ . '/../includes/footer.php';
