<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_staff_or_admin();

$pageTitle = 'Dashboard';
$activeMenu = 'dashboard';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card p-3 h-100">
            <div class="stat-label">Total Products</div>
            <div class="stat-value" id="statTotalProducts">0</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card p-3 h-100">
            <div class="stat-label">Total Stock</div>
            <div class="stat-value" id="statTotalStock">0</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card p-3 h-100">
            <div class="stat-label">Total Sales Today</div>
            <div class="stat-value" id="statSalesToday">$0.00</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card p-3 h-100">
            <div class="stat-label">Low Stock Alerts</div>
            <div class="stat-value" id="statLowStock">0</div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card card-surface h-100">
            <div class="card-body">
                <h2 class="h6 mb-3">Monthly Sales Trend</h2>
                <div class="chart-canvas-wrap">
                    <canvas id="monthlySalesChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card card-surface h-100">
            <div class="card-body">
                <h2 class="h6 mb-3">Top Selling Shoes</h2>
                <div class="chart-canvas-wrap">
                    <canvas id="topSellingChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card card-surface h-100">
            <div class="card-body">
                <h2 class="h6 mb-3">Inventory by Category</h2>
                <div class="chart-canvas-wrap">
                    <canvas id="inventoryCategoryChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card card-surface h-100">
            <div class="card-body">
                <h2 class="h6 mb-3">Sales Distribution</h2>
                <div class="chart-canvas-wrap">
                    <canvas id="salesDistributionChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$extraScripts = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
(() => {
    const cards = {
        totalProducts: document.getElementById("statTotalProducts"),
        totalStock: document.getElementById("statTotalStock"),
        salesToday: document.getElementById("statSalesToday"),
        lowStock: document.getElementById("statLowStock"),
    };

    function money(value) {
        return "₱" +  Number(value || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    const chartPalette = ["#0f766e", "#f59e0b", "#1d4ed8", "#ef4444", "#06b6d4", "#16a34a", "#fb7185", "#8b5cf6"];

    function colorForLabel(label) {
        let hash = 0;
        for (let i = 0; i < label.length; i++) {
            hash = (hash * 31 + label.charCodeAt(i)) >>> 0;
        }
        return chartPalette[hash % chartPalette.length];
    }

    function createBarChart(id, labels, values, horizontal = false, colors = null) {
        return new Chart(document.getElementById(id), {
            type: "bar",
            data: {
                labels,
                datasets: [{
                    label: "Value",
                    data: values,
                    backgroundColor: colors || chartPalette,
                    borderRadius: 8,
                }],
            },
            options: {
                indexAxis: horizontal ? "y" : "x",
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                },
                scales: {
                    y: { beginAtZero: true },
                },
            },
        });
    }

    function createLineChart(id, labels, values) {
        const pointRadii = values.map(function(v) { return v > 0 ? 5 : 0; });
        const pointHover = values.map(function(v) { return v > 0 ? 7 : 0; });
        return new Chart(document.getElementById(id), {
            type: "line",
            data: {
                labels,
                datasets: [{
                    label: "Sales",
                    data: values,
                    borderColor: "#0f766e",
                    backgroundColor: "rgba(15, 118, 110, 0.12)",
                    tension: 0.32,
                    fill: true,
                    pointRadius: pointRadii,
                    pointHoverRadius: pointHover,
                    pointBackgroundColor: "#0f766e",
                    spanGaps: true,
                }],
            },
            options: {
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
                    x: {
                        ticks: { maxRotation: 45, autoSkip: true, maxTicksLimit: 12 },
                        grid: { display: false },
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(v) {
                                return v >= 1000 ? "\u20B1" + (v / 1000).toFixed(0) + "k" : "\u20B1" + v;
                            }
                        }
                    },
                },
            },
        });
    }

    function createDoughnutChart(id, labels, values) {
        return new Chart(document.getElementById(id), {
            type: "doughnut",
            data: {
                labels,
                datasets: [{
                    data: values,
                    backgroundColor: labels.map(colorForLabel),
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
            },
        });
    }

    async function loadDashboard() {
        try {
            const payload = await window.App.fetchJSON("' . e(base_url('api/dashboard_data.php')) . '");

            cards.totalProducts.textContent = String(payload.cards.total_products || 0);
            cards.totalStock.textContent = String(payload.cards.total_stock || 0);
            cards.salesToday.textContent = money(payload.cards.sales_today || 0);
            cards.lowStock.textContent = String(payload.cards.low_stock_alerts || 0);

            createLineChart("monthlySalesChart", payload.monthly_sales.labels || [], payload.monthly_sales.values || []);
            const topColors = (payload.top_selling.category_names || []).map(colorForLabel);
            createBarChart("topSellingChart", payload.top_selling.labels || [], payload.top_selling.values || [], true, topColors);
            createDoughnutChart("inventoryCategoryChart", payload.inventory_by_category.labels || [], payload.inventory_by_category.values || []);
            createDoughnutChart("salesDistributionChart", payload.sales_distribution.labels || [], payload.sales_distribution.values || []);
        } catch (error) {
            console.error(error);
        }
    }

    loadDashboard();
})();
</script>';
require_once __DIR__ . '/../includes/footer.php';
