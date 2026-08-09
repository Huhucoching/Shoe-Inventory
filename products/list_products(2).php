<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_staff_or_admin();

$categories = get_all_categories(getPDO());
$brands = get_all_brands(getPDO());

$pageTitle = 'Products';
$activeMenu = 'products';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="card card-surface mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Search Products</label>
                <input type="search" id="filterSearch" class="form-control" placeholder="Search by name, ID, brand, size, color">
            </div>
            <div class="col-md-3">
                <label class="form-label">Category</label>
                <select id="filterCategory" class="form-select">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e((string) $category['id']) ?>"><?= e($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Brand</label>
                <select id="filterBrand" class="form-select">
                    <option value="">All brands</option>
                    <?php foreach ($brands as $brand): ?>
                        <option value="<?= e($brand['brand']) ?>"><?= e($brand['brand']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-grid">
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addProductModal"><i class="bi bi-plus-circle"></i> Add Product</button>
            </div>
        </div>
     
            <div class="mt-3 d-flex gap-2 flex-wrap">
                <a href="<?= e(base_url('products/manage_categories.php')) ?>" class="btn btn-outline-secondary btn-sm">Manage Categories</a>
            </div>
       
    </div>
</div>

<div class="table-panel">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
            <tr>
                <th>Image</th>
                <th>ID</th>
                <th>Product</th>
                <th>Brand</th>
                <th>Category</th>
                <th>Size (EU) / Color</th>
                <th>Buy Price</th>
                <th>Sell Price</th>
                <th>Stock</th>
                <th>Date Added</th>
                <th class="text-end">Actions</th>
            </tr>
            </thead>
            <tbody id="productsTableBody">
            <tr><td colspan="11" class="text-center py-4 text-secondary">Loading products...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<nav class="mt-3">
    <ul class="pagination" id="productsPagination"></ul>
</nav>

<div class="modal fade" id="addProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="<?= e(base_url('products/add_product.php')) ?>" enctype="multipart/form-data">
                <div class="modal-body">
                    <?= csrf_input() ?>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Product ID (optional)</label>
                            <input type="text" name="product_code" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Shoe Name</label>
                            <input type="text" name="shoe_name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Brand</label>
                            <input type="text" name="brand" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Category</label>
                            <select name="category_id" class="form-select" required>
                                <option value=""></option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= e((string) $category['id']) ?>"><?= e($category['name']) ?></option>
                                <?php endforeach; ?>
                                <option value="other">Other (Add new category)</option>
                            </select>
                            <input type="text" name="new_category" id="newCategoryInput" class="form-control mt-2" placeholder="Enter new category" style="display:none;" autocomplete="off">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Size (EU)</label>
                            <input type="text" name="size" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Color</label>
                            <input type="text" name="color" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Purchase Price</label>
                            <input type="number" step="0.01" min="0" name="purchase_price" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Selling Price</label>
                            <input type="number" step="0.01" min="0" name="selling_price" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Initial Stock Quantity</label>
                            <input type="number" min="0" name="stock_quantity" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Product Image <span class="text-secondary fw-normal">(optional, JPEG/PNG/WebP, max 2 MB)</span></label>
                            <input type="file" name="product_image" class="form-control" accept="image/jpeg,image/png,image/webp">
                            <div id="addImgPreview" class="mt-2"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Add Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraScripts = '<script>
(() => {
    const state = {
        page: 1,
        perPage: 10,
        threshold: ' . (int) LOW_STOCK_THRESHOLD . ',
        isAdmin: ' . (is_admin() ? 'true' : 'false') . ',
    };

    const tableBody = document.getElementById("productsTableBody");
    const pagination = document.getElementById("productsPagination");
    const filterSearch = document.getElementById("filterSearch");
    const filterCategory = document.getElementById("filterCategory");
    const filterBrand = document.getElementById("filterBrand");

    function esc(value) {
        return String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/\"/g, "&quot;")
            .replace(/\'/g, "&#039;");
    }

    function formatMoney(value) {
        const num = Number(value || 0);
        return "₱" + num.toFixed(2) .replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    async function loadProducts(page = 1) {
        state.page = page;
        tableBody.innerHTML = `<tr><td colspan="11" class="text-center py-4 text-secondary">Loading...</td></tr>`;

        const params = new URLSearchParams({
            page: String(state.page),
            per_page: String(state.perPage),
            search: filterSearch.value.trim(),
            category_id: filterCategory.value,
            brand: filterBrand.value,
        });

        try {
            const data = await window.App.fetchJSON("' . e(base_url('api/products_table.php')) . '?" + params.toString());
            renderRows(data.rows || []);
            renderPagination(data.meta || { page: 1, total_pages: 1 });
            state.threshold = Number(data.low_stock_threshold || state.threshold);
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="11" class="text-center py-4 text-danger">Failed to load products.</td></tr>`;
            pagination.innerHTML = "";
        }
    }

    function renderRows(rows) {
        if (!rows.length) {
            tableBody.innerHTML = `<tr><td colspan="11" class="text-center py-4 text-secondary">No products found.</td></tr>`;
            return;
        }

        const baseImgUrl = ' . json_encode(e(base_url('assets/images/')), JSON_THROW_ON_ERROR) . ';

        tableBody.innerHTML = rows.map((row) => {
            const stock = Number(row.stock_quantity || 0);
            let stockBadge;
            if (stock === 0) {
                stockBadge = `<span class="badge bg-danger-subtle text-danger">Out of Stock</span>`;
            } else if (stock <= state.threshold) {
                stockBadge = `<span class="badge badge-stock-low">Low (${stock})</span>`;
            } else {
                stockBadge = `<span class="badge badge-stock-ok">${stock}</span>`;
            }

            const thumbHtml = row.image_path
                ? `<img src="${baseImgUrl}${esc(row.image_path)}" alt="" style="width:44px;height:44px;object-fit:cover;border-radius:6px;">`
                : `<span style="display:inline-block;width:44px;height:44px;background:#e2e8f0;border-radius:6px;line-height:44px;text-align:center;color:#94a3b8;font-size:1.2rem;">&#128247;</span>`;

            return `<tr${stock === 0 ? \' class="product-out-of-stock"\' : \'\'}>
                <td>${thumbHtml}</td>
                <td>${esc(row.product_code)}</td>
                <td>${esc(row.shoe_name)}</td>
                <td>${esc(row.brand)}</td>
                <td>${esc(row.category_name)}</td>
                <td>${esc(row.size)} / ${esc(row.color)}</td>
                <td>${formatMoney(row.purchase_price)}</td>
                <td>${formatMoney(row.selling_price)}</td>
                <td>${stockBadge}</td>
                <td>${esc(String(row.date_added).slice(0, 10))}</td>
                <td class="text-end d-flex gap-1 justify-content-end flex-wrap">
                    <a class="btn btn-sm btn-outline-primary" href="' . e(base_url('products/edit_product.php')) . '?id=${encodeURIComponent(row.id)}">Edit</a>
                    ${(state.isAdmin && stock <= 0) ? `<a class="btn btn-sm btn-outline-danger" href="' . e(base_url('products/delete_product.php')) . '?id=${encodeURIComponent(row.id)}">Delete</a>` : ""}
                </td>
            </tr>`;
        }).join("");
    }

    function renderPagination(meta) {
        const page = Number(meta.page || 1);
        const totalPages = Number(meta.total_pages || 1);
        let html = "";

        const addLink = (label, targetPage, disabled = false, active = false) => {
            html += `<li class="page-item ${disabled ? "disabled" : ""} ${active ? "active" : ""}">
                        <a class="page-link" href="#" data-page="${targetPage}">${label}</a>
                    </li>`;
        };

        addLink("Prev", page - 1, page <= 1);

        for (let i = 1; i <= totalPages; i += 1) {
            if (i === 1 || i === totalPages || Math.abs(i - page) <= 2) {
                addLink(String(i), i, false, i === page);
            }
        }

        addLink("Next", page + 1, page >= totalPages);

        pagination.innerHTML = html;
    }

    let timer;
    [filterSearch, filterCategory, filterBrand].forEach((el) => {
        el.addEventListener("input", () => {
            clearTimeout(timer);
            timer = setTimeout(() => loadProducts(1), 250);
        });
        el.addEventListener("change", () => loadProducts(1));
    });

    pagination.addEventListener("click", (event) => {
        event.preventDefault();
        const target = event.target.closest("a[data-page]");
        if (!target || target.parentElement.classList.contains("disabled") || target.parentElement.classList.contains("active")) {
            return;
        }
        const targetPage = Number(target.getAttribute("data-page"));
        if (Number.isNaN(targetPage) || targetPage < 1) {
            return;
        }
        loadProducts(targetPage);
    });

    loadProducts(1);

    // Image file preview for add modal
    document.addEventListener(\'DOMContentLoaded\', () => {
        const addImgInput = document.querySelector(\'#addProductModal input[name="product_image"]\');
        const addImgPreview = document.getElementById(\'addImgPreview\');
        if (addImgInput && addImgPreview) {
            addImgInput.addEventListener(\'change\', () => {
                const file = addImgInput.files[0];
                if (file) {
                    const url = URL.createObjectURL(file);
                    addImgPreview.innerHTML = `<img src="${url}" style="height:80px;border-radius:8px;object-fit:cover;" alt="preview">`;
                } else {
                    addImgPreview.innerHTML = \'\';
                }
            });
        }
    });
})();
</script>';
require_once __DIR__ . '/../includes/footer.php';
