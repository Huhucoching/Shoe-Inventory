<?php
declare(strict_types=1);

$pageTitle = $pageTitle ?? 'Dashboard';
$activeMenu = $activeMenu ?? '';
$user = current_user();

if ($user === null) {
    redirect(base_url('auth/login.php'));
}

$flashMessages = get_flash_messages();
$brandLogoUrl = app_logo_url();
$sidebarLogoUrl = base_url('assets/images/triple-l-logo 2.png');
$mainClass = trim((string) ($mainClass ?? ''));
$pageSubtitle = trim((string) ($pageSubtitle ?? 'Operational insight and stock control'));

$flashMap = [
    'success' => 'success',
    'error' => 'danger',
    'warning' => 'warning',
    'info' => 'info',
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
    <?php if ($brandLogoUrl !== ''): ?>
        <link rel="icon" type="image/png" href="<?= e($brandLogoUrl) ?>">
        <link rel="apple-touch-icon" href="<?= e($brandLogoUrl) ?>">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/style.css')) ?>">
</head>
<body>
<div class="app-bg-layer"></div>
<div class="app-shell d-flex">
    <aside class="sidebar offcanvas-lg offcanvas-start text-bg-dark" tabindex="-1" id="sidebarNav">
        <div class="offcanvas-header border-bottom border-secondary-subtle">
            <img class="app-brand-logo app-brand-logo-sidebar" src="<?= e($sidebarLogoUrl) ?>" alt="Triple L">
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#sidebarNav" aria-label="Close"></button>
        </div>  
        <div class="offcanvas-body d-flex flex-column p-3">
            <div class="brand-tag mb-4">
                <img class="brand-tag-logo" src="<?= e($sidebarLogoUrl) ?>" alt="Triple L">
            </div>
            <nav class="nav flex-column gap-1 sidebar-nav">
                <a class="nav-link <?= $activeMenu === 'dashboard' ? 'active' : '' ?>" href="<?= e(base_url('dashboard/dashboard.php')) ?>"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
                <a class="nav-link <?= $activeMenu === 'products' ? 'active' : '' ?>" href="<?= e(base_url('products/list_products.php')) ?>"><i class="bi bi-bag-fill"></i> Products</a>
                <a class="nav-link <?= $activeMenu === 'sell' ? 'active' : '' ?>" href="<?= e(base_url('sales/add_sale.php')) ?>"><i class="bi bi-cash-coin"></i> Sell Product</a>
                <?php if (is_admin()): ?>
                    <a class="nav-link <?= $activeMenu === 'inventory' ? 'active' : '' ?>" href="<?= e(base_url('inventory/stock_history.php')) ?>"><i class="bi bi-box-seam"></i> Inventory</a>
                    <a class="nav-link <?= $activeMenu === 'sales' ? 'active' : '' ?>" href="<?= e(base_url('sales/sales_history.php')) ?>"><i class="bi bi-receipt-cutoff"></i> Sales</a>
                    <a class="nav-link <?= $activeMenu === 'reports' ? 'active' : '' ?>" href="<?= e(base_url('reports/reports.php')) ?>"><i class="bi bi-bar-chart-fill"></i> Reports</a>
                    <a class="nav-link <?= $activeMenu === 'users' ? 'active' : '' ?>" href="<?= e(base_url('users/manage_staff.php')) ?>"><i class="bi bi-people-fill"></i> Staff</a>
                    <a class="nav-link text-danger" href="<?= e(base_url('admin_reset.php')) ?>"><i class="bi bi-exclamation-triangle"></i> Reset System</a>
                <?php endif; ?>
                <a class="nav-link" href="<?= e(base_url('auth/logout.php')) ?>"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </nav>
            <div class="mt-auto small text-white-50 pt-3">
                Logged in as
                <div class="fw-semibold text-white"><?= e($user['full_name']) ?> (<?= e(ucfirst($user['role'])) ?>)</div>
            </div>
        </div>
    </aside>

    <div class="content-wrapper flex-grow-1">
        <header class="topbar d-flex align-items-center justify-content-between px-3 px-md-4 py-3">
            <div class="d-flex align-items-center gap-2 topbar-main">
                <button class="btn btn-topbar d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarNav" aria-controls="sidebarNav">
                    <i class="bi bi-list"></i>
                </button>
                <div>
                    <h1 class="h4 mb-0 page-heading"><?= e($pageTitle) ?></h1>
                    <small class="text-secondary"><?= e($pageSubtitle) ?></small>
                </div>
            </div>
            <div class="topbar-meta d-flex align-items-center gap-3 ms-auto">
                <span class="badge bg-light text-dark border"><?= e(date('D, d M Y')) ?></span>
            </div>
        </header>

        <main class="container-fluid px-3 px-md-4 pb-4<?= $mainClass !== '' ? ' ' . e($mainClass) : '' ?>">
            <?php foreach ($flashMessages as $message): ?>
                <?php $class = $flashMap[$message['type']] ?? 'secondary'; ?>
                <div class="alert alert-<?= e($class) ?> alert-dismissible fade show" role="alert">
                    <?= e($message['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endforeach; ?>
