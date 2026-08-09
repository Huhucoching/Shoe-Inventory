<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_staff_or_admin();

$pdo = getPDO();
$currentUser = current_user();
$staffId = (int) ($currentUser['id'] ?? 0);
$products = get_products_for_stock($pdo);

$errors = [];

if (is_post()) {
    $productId = to_positive_int($_POST['product_id'] ?? null);
    $quantity = to_positive_int($_POST['quantity'] ?? null);
    $reason = trim((string) ($_POST['reason'] ?? 'Manual stock out'));

    if (!has_valid_post_csrf()) {
        $errors[] = 'Invalid request token.';
    }

    if ($productId === null || $quantity === null) {
        $errors[] = 'Please select a valid product and quantity.';
    }

    if (empty($errors)) {
        try {
            record_stock_movement($pdo, $productId, $quantity, 'OUT', $reason, $staffId);
            set_flash('success', 'Stock out transaction recorded.');
            redirect(base_url('inventory/stock_history.php'));
        } catch (RuntimeException $e) {
            $message = $e->getMessage() === 'Insufficient stock.'
                ? 'Cannot remove more than available stock.'
                : 'Could not complete stock out transaction.';
            $errors[] = $message;
        }
    }
}

$pageTitle = 'Stock Out';
$activeMenu = 'inventory';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card card-surface">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">Remove Inventory</h2>
                    <a href="<?= e(base_url('inventory/stock_history.php')) ?>" class="btn btn-outline-secondary btn-sm">History</a>
                </div>

                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endforeach; ?>

                <form method="post" class="row g-3">
                    <?= csrf_input() ?>
                    <div class="col-12">
                        <label class="form-label">Product</label>
                        <select name="product_id" class="form-select" required>
                            <option value="">Select product</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?= e((string) $product['id']) ?>">
                                    <?= e($product['product_code']) ?> - <?= e($product['shoe_name']) ?> (Available: <?= e((string) $product['stock_quantity']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Quantity</label>
                        <input type="number" min="1" name="quantity" class="form-control" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Reason</label>
                        <input type="text" name="reason" class="form-control" value="Manual stock out">
                    </div>
                    <div class="col-12">
                        <button class="btn btn-danger">Record Stock Out</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card card-surface">
            <div class="card-body">
                <h2 class="h5 mb-3">When to Use Stock Out</h2>
                <ul class="mb-0">
                    <li>Damaged or returned items that should leave sellable stock.</li>
                    <li>Manual stock correction outside of sales transactions.</li>
                    <li>All stock out entries are audit logged by staff account.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php';
