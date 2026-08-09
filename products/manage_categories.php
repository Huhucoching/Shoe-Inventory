<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_staff_or_admin();

$errors = [];
$pdo = getPDO();

if (is_post()) {
    $action = (string) ($_POST['action'] ?? '');

    if (!has_valid_post_csrf()) {
        $errors[] = 'Invalid request token.';
    } elseif ($action === 'add') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));

        if ($name === '') {
            $errors[] = 'Category name is required.';
        }

        if (empty($errors)) {
            try {
                $insert = $pdo->prepare('INSERT INTO categories (name, description) VALUES (:name, :description)');
                $insert->execute([
                    'name' => $name,
                    'description' => $description !== '' ? $description : null,
                ]);
                set_flash('success', 'Category added.');
                redirect(base_url('products/manage_categories.php'));
            } catch (PDOException $exception) {
                $errors[] = (int) $exception->errorInfo[1] === 1062 ? 'Category already exists.' : 'Unable to add category.';
            }
        }
    } elseif ($action === 'update') {
        $categoryId = to_positive_int($_POST['category_id'] ?? null);
        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));

        if ($categoryId === null || $name === '') {
            $errors[] = 'Invalid category details.';
        }

        if (empty($errors)) {
            try {
                $update = $pdo->prepare('UPDATE categories SET name = :name, description = :description WHERE id = :id');
                $update->execute([
                    'name' => $name,
                    'description' => $description !== '' ? $description : null,
                    'id' => $categoryId,
                ]);
                set_flash('success', 'Category updated.');
                redirect(base_url('products/manage_categories.php'));
            } catch (PDOException $exception) {
                $errors[] = (int) $exception->errorInfo[1] === 1062 ? 'Category already exists.' : 'Unable to update category.';
            }
        }
    } elseif ($action === 'delete') {
        $categoryId = to_positive_int($_POST['category_id'] ?? null);

        if ($categoryId === null) {
            $errors[] = 'Invalid category selected.';
        } else {
            $usage = $pdo->prepare('SELECT COUNT(*) FROM products WHERE category_id = :id');
            $usage->execute(['id' => $categoryId]);
            if ((int) $usage->fetchColumn() > 0) {
                $errors[] = 'Category cannot be deleted because products are linked to it.';
            } else {
                $delete = $pdo->prepare('DELETE FROM categories WHERE id = :id');
                $delete->execute(['id' => $categoryId]);
                set_flash('success', 'Category deleted.');
                redirect(base_url('products/manage_categories.php'));
            }
        }
    }
}

$stmt = $pdo->query('SELECT id, name, description, created_at FROM categories ORDER BY id ASC');
$categories = $stmt->fetchAll();

$pageTitle = 'Manage Categories';
$activeMenu = 'products';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card card-surface">
            <div class="card-body">
                <h2 class="h5 mb-3">Add Category</h2>
                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endforeach; ?>
                <form method="post" class="row g-3">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="add">
                    <div class="col-12">
                        <label class="form-label">Category Name</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-success">Save Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="table-panel">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th class="text-end">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($categories as $rowIndex => $category): ?>
                        <tr>
                            <td><?= $rowIndex + 1 ?></td>
                            <td><?= e($category['name']) ?></td>
                            <td><?= e((string) $category['description']) ?></td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editCategory<?= e((string) $category['id']) ?>">Edit</button>
                                <form method="post" class="d-inline">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="category_id" value="<?= e((string) $category['id']) ?>">
                                    <button class="btn btn-sm btn-outline-danger" data-confirm="Delete this category?" type="submit">Delete</button>
                                </form>
                            </td>
                        </tr>

                        <div class="modal fade" id="editCategory<?= e((string) $category['id']) ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Edit Category</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <form method="post">
                                        <div class="modal-body">
                                            <?= csrf_input() ?>
                                            <input type="hidden" name="action" value="update">
                                            <input type="hidden" name="category_id" value="<?= e((string) $category['id']) ?>">
                                            <div class="mb-3">
                                                <label class="form-label">Name</label>
                                                <input type="text" class="form-control" name="name" value="<?= e($category['name']) ?>" required>
                                            </div>
                                            <div>
                                                <label class="form-label">Description</label>
                                                <textarea class="form-control" name="description" rows="3"><?= e((string) $category['description']) ?></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button class="btn btn-primary" type="submit">Save</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($categories)): ?>
                        <tr><td colspan="4" class="text-center text-secondary py-4">No categories found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php';
