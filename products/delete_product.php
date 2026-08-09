<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$productId = to_positive_int($_GET['id'] ?? null);

if ($productId === null) {
    set_flash('error', 'Invalid product selected.');
    redirect(base_url('products/list_products.php'));
}

$pdo = getPDO();
$stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id AND is_active = 1 LIMIT 1');
$stmt->execute(['id' => $productId]);
$product = $stmt->fetch();

if (!$product) {
    set_flash('error', 'Product not found.');
    redirect(base_url('products/list_products.php'));
}

if ((int) $product['stock_quantity'] > 0) {
    set_flash('error', 'Only out-of-stock products can be deleted.');
    redirect(base_url('products/list_products.php'));
}

if (is_post()) {
    if (!has_valid_post_csrf()) {
        set_flash('error', 'Invalid request token.');
        redirect(base_url('products/list_products.php'));
    }

    $delete = $pdo->prepare('UPDATE products SET is_active = 0 WHERE id = :id AND stock_quantity <= 0 AND is_active = 1');
    $delete->execute(['id' => $productId]);

    if ($delete->rowCount() < 1) {
        set_flash('error', 'Product cannot be deleted because it is in stock or already inactive.');
        redirect(base_url('products/list_products.php'));
    }

    set_flash('success', 'Product "' . $product['shoe_name'] . '" has been deleted.');
    redirect(base_url('products/list_products.php'));
}

$pageTitle = 'Delete Product';
$activeMenu = 'products';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-xl-6 col-lg-7">
        <div class="card card-surface border-danger">
            <div class="card-body p-4">
                <h2 class="h5 mb-1 text-danger">Delete Product</h2>
                <p class="text-secondary mb-4">This action cannot be undone. The product will be removed from the active inventory.</p>

                <div class="d-flex align-items-center gap-3 mb-4 p-3 rounded" style="background:var(--bs-light,#f8f9fa);">
                    <?php if (!empty($product['image_path'])): ?>
                        <img src="<?= e(base_url('assets/images/' . $product['image_path'])) ?>" alt="" style="width:60px;height:60px;object-fit:cover;border-radius:8px;flex-shrink:0;">
                    <?php else: ?>
                        <span style="display:inline-block;width:60px;height:60px;background:#e2e8f0;border-radius:8px;line-height:60px;text-align:center;color:#94a3b8;font-size:1.5rem;flex-shrink:0;">&#128247;</span>
                    <?php endif; ?>
                    <div>
                        <div class="fw-semibold"><?= e($product['shoe_name']) ?> <span class="text-secondary fw-normal">&mdash; <?= e($product['brand']) ?></span></div>
                        <div class="text-secondary small">ID: <?= e($product['product_code']) ?> &nbsp;|&nbsp; Size: <?= e($product['size']) ?> &nbsp;|&nbsp; Color: <?= e($product['color']) ?></div>
                        <div class="small mt-1">Stock: <strong><?= (int) $product['stock_quantity'] ?></strong></div>
                    </div>
                </div>

                <p class="mb-4">Are you sure you want to delete <strong><?= e($product['shoe_name']) ?></strong>?</p>

                <form method="post">
                    <?= csrf_input() ?>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-danger">Yes, Delete Product</button>
                        <a href="<?= e(base_url('products/list_products.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php';
