<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_staff_or_admin();

if (!is_post()) {
    redirect(base_url('products/list_products.php'));
}

if (!has_valid_post_csrf()) {
    set_flash('error', 'Invalid request token.');
    redirect(base_url('products/list_products.php'));
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

if ($productCode === '') {
    $productCode = 'SH-' . date('YmdHis') . '-' . random_int(10, 99);
}

$errors = [];

if ($shoeName === '' || $brand === '' || $size === '' || $color === '') {
    $errors[] = 'Please complete all product text fields.';
}

if ($categoryId === null) {
    $errors[] = 'Please select a valid category.';
}

if ($purchasePrice === null || $sellingPrice === null || $stockQuantity === null) {
    $errors[] = 'Prices and stock quantity must be valid numbers.';
}

if ($purchasePrice !== null && $purchasePrice < 0) {
    $errors[] = 'Purchase price cannot be negative.';
}

if ($sellingPrice !== null && $sellingPrice < 0) {
    $errors[] = 'Selling price cannot be negative.';
}

if ($errors !== []) {
    set_flash('error', implode(' ', $errors));
    redirect(base_url('products/list_products.php'));
}

$pdo = getPDO();
$user = current_user();

try {
    $pdo->beginTransaction();

    $insert = $pdo->prepare(
        'INSERT INTO products
        (product_code, shoe_name, brand, category_id, size, color, purchase_price, selling_price, stock_quantity, date_added)
        VALUES
        (:product_code, :shoe_name, :brand, :category_id, :size, :color, :purchase_price, :selling_price, :stock_quantity, NOW())'
    );

    $insert->execute([
        'product_code' => $productCode,
        'shoe_name' => $shoeName,
        'brand' => $brand,
        'category_id' => $categoryId,
        'size' => $size,
        'color' => $color,
        'purchase_price' => $purchasePrice,
        'selling_price' => $sellingPrice,
        'stock_quantity' => $stockQuantity,
    ]);

    $productId = (int) $pdo->lastInsertId();

    // Handle product image upload
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
                $pdo->prepare('UPDATE products SET image_path = :image_path WHERE id = :id')
                    ->execute(['image_path' => 'products/' . $filename, 'id' => $productId]);
            }
        }
    }

    if ($stockQuantity > 0) {
        $move = $pdo->prepare('INSERT INTO stock_movements (product_id, quantity, movement_type, reason, staff_id, created_at) VALUES (:product_id, :quantity, :movement_type, :reason, :staff_id, NOW())');
        $move->execute([
            'product_id' => $productId,
            'quantity' => $stockQuantity,
            'movement_type' => 'IN',
            'reason' => 'Initial stock setup',
            'staff_id' => (int) $user['id'],
        ]);
    }

    $pdo->commit();
    set_flash('success', 'Product added successfully.');
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if ((int) $exception->errorInfo[1] === 1062) {
        set_flash('error', 'Product code already exists. Please use a unique Product ID.');
    } else {
        set_flash('error', 'Unable to add product right now.');
    }
}

redirect(base_url('products/list_products.php'));
