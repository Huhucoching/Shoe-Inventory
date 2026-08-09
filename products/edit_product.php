<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_staff_or_admin();

$productId = to_positive_int($_GET['id'] ?? null);

if ($productId === null) {
    set_flash('error', 'Invalid product selected.');
    redirect(base_url('products/list_products.php'));
}

$pdo = getPDO();
$stmt = $pdo->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $productId]);
$product = $stmt->fetch();

if (!$product) {
    set_flash('error', 'Product not found.');
    redirect(base_url('products/list_products.php'));
}

$categories = get_all_categories($pdo);

$errors = [];


if (is_post()) {
    if (!has_valid_post_csrf()) {
        $errors[] = 'Invalid request token.';
    }

    $shoeName = trim((string) ($_POST['shoe_name'] ?? ''));
    $brand = trim((string) ($_POST['brand'] ?? ''));
    $categoryId = to_positive_int($_POST['category_id'] ?? null);
    $size = trim((string) ($_POST['size'] ?? ''));
    $color = trim((string) ($_POST['color'] ?? ''));
    $purchasePrice = to_decimal($_POST['purchase_price'] ?? null);
    $sellingPrice = to_decimal($_POST['selling_price'] ?? null);
    $stockQuantity = to_non_negative_int($_POST['stock_quantity'] ?? null);
    $productCode = strtoupper(trim((string) ($_POST['product_code'] ?? '')));

    if ($shoeName === '' || $brand === '' || $size === '' || $color === '' || $productCode === '') {
        $errors[] = 'All product fields are required.';
    }

    if ($categoryId === null || $purchasePrice === null || $sellingPrice === null || $stockQuantity === null) {
        $errors[] = 'Please provide valid numeric values and select category.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
        $currentUser = current_user();
        $staffId = (int) ($currentUser['id'] ?? 0);

            // Handle product image upload
            $newImagePath = $product['image_path'] ?? null;
            if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
                $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
                $fileType = mime_content_type($_FILES['product_image']['tmp_name']);
                if (in_array($fileType, $allowedMimes, true) && $_FILES['product_image']['size'] <= 2 * 1024 * 1024) {
                    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$fileType];
                    $destDir = __DIR__ . '/../assets/images/products/';
                    if (!is_dir($destDir)) {
                        mkdir($destDir, 0755, true);
                    }
                    $filename = $productId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
                    if (move_uploaded_file($_FILES['product_image']['tmp_name'], $destDir . $filename)) {
                        // Remove old image if exists
                        if (!empty($product['image_path'])) {
                            $oldFile = __DIR__ . '/../assets/images/' . $product['image_path'];
                            if (is_file($oldFile)) {
                                unlink($oldFile);
                            }
                        }
                        $newImagePath = 'products/' . $filename;
                    }
                }
            }

            $update = $pdo->prepare(
                'UPDATE products SET
                    product_code = :product_code,
                    shoe_name = :shoe_name,
                    brand = :brand,
                    category_id = :category_id,
                    size = :size,
                    color = :color,
                    purchase_price = :purchase_price,
                    selling_price = :selling_price,
                    stock_quantity = :stock_quantity,
                    image_path = :image_path
                WHERE id = :id'
            );

            $update->execute([
                'product_code' => $productCode,
                'shoe_name' => $shoeName,
                'brand' => $brand,
                'category_id' => $categoryId,
                'size' => $size,
                'color' => $color,
                'purchase_price' => $purchasePrice,
                'selling_price' => $sellingPrice,
                'stock_quantity' => $stockQuantity,
                'image_path' => $newImagePath,
                'id' => $productId,
            ]);

            $previousStock = (int) $product['stock_quantity'];
            $difference = $stockQuantity - $previousStock;

            if ($difference !== 0) {
                $movement = $pdo->prepare('INSERT INTO stock_movements (product_id, quantity, movement_type, reason, staff_id, created_at) VALUES (:product_id, :quantity, :movement_type, :reason, :staff_id, NOW())');
                $movement->execute([
                    'product_id' => $productId,
                    'quantity' => abs($difference),
                    'movement_type' => $difference > 0 ? 'IN' : 'OUT',
                    'reason' => 'Stock adjusted via product edit',
                            'staff_id' => $staffId,
                ]);
            }

            $pdo->commit();
            set_flash('success', 'Product updated successfully.');
            redirect(base_url('products/list_products.php'));
        } catch (PDOException $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

            if ((int) $exception->errorInfo[1] === 1062) {
                $errors[] = 'Product code must be unique.';
            } else {
                $errors[] = 'Unable to update product right now.';
            }
        }
    }

    if (!empty($errors)) {
        $product = [
            'id' => $productId,
            'product_code' => $productCode,
            'shoe_name' => $shoeName,
            'brand' => $brand,
            'category_id' => $categoryId,
            'size' => $size,
            'color' => $color,
            'purchase_price' => $purchasePrice,
            'selling_price' => $sellingPrice,
            'stock_quantity' => $stockQuantity,
        ];
    }
}

$pageTitle = 'Edit Product';
$activeMenu = 'products';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center">
    <div class="col-xl-10">
        <div class="card card-surface">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">Edit Product #<?= e((string) $productId) ?></h2>
                    <a href="<?= e(base_url('products/list_products.php')) ?>" class="btn btn-outline-secondary btn-sm">Back</a>
                </div>

                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endforeach; ?>

                <form method="post" class="row g-3" enctype="multipart/form-data">
                    <?= csrf_input() ?>
                    <div class="col-md-4">
                        <label class="form-label">Product ID</label>
                        <input type="text" name="product_code" class="form-control" value="<?= e((string) $product['product_code']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Shoe Name</label>
                        <input type="text" name="shoe_name" class="form-control" value="<?= e((string) $product['shoe_name']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Brand</label>
                        <input type="text" name="brand" class="form-control" value="<?= e((string) $product['brand']) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-select" required>
                            <option value=""></option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= e((string) $category['id']) ?>" <?= (int) $category['id'] === (int) $product['category_id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Size (EU)</label>
                        <input type="text" name="size" class="form-control" value="<?= e((string) $product['size']) ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Color</label>
                        <input type="text" name="color" class="form-control" value="<?= e((string) $product['color']) ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Purchase Price</label>
                        <input type="number" step="0.01" min="0" name="purchase_price" class="form-control" value="<?= e((string) $product['purchase_price']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Selling Price</label>
                        <input type="number" step="0.01" min="0" name="selling_price" class="form-control" value="<?= e((string) $product['selling_price']) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Stock Quantity</label>
                        <input type="number" min="0" name="stock_quantity" class="form-control" value="<?= e((string) $product['stock_quantity']) ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Product Image <span class="text-secondary fw-normal">(optional, JPEG/PNG/WebP, max 2 MB)</span></label>
                        <?php if (!empty($product['image_path'])): ?>
                            <div class="mb-2">
                                <img src="<?= e(base_url('assets/images/' . $product['image_path'])) ?>" alt="Current product image" style="height:80px;border-radius:8px;object-fit:cover;">
                                <small class="text-secondary ms-2">Current image. Upload a new one to replace it.</small>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="product_image" class="form-control" accept="image/jpeg,image/png,image/webp">
                        <div id="editImgPreview" class="mt-2"></div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Save Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php';
