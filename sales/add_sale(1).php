<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_staff_or_admin();

$errors = [];
$pdo = getPDO();
$currentUser = current_user();
$staffId = (int) ($currentUser['id'] ?? 0);

if (is_post()) {
    if (!has_valid_post_csrf()) {
        $errors[] = 'Invalid request token.';
    }

    $productIdsRaw = $_POST['product_id'] ?? [];
    $quantitiesRaw = $_POST['quantity'] ?? [];

    if (!is_array($productIdsRaw) || !is_array($quantitiesRaw)) {
        $errors[] = 'Invalid sale payload.';
    }

    $lineMap = [];

    if (empty($errors)) {
        $max = max(count($productIdsRaw), count($quantitiesRaw));
        for ($i = 0; $i < $max; $i++) {
            $productId = to_positive_int($productIdsRaw[$i] ?? null);
            $quantity = to_positive_int($quantitiesRaw[$i] ?? null);

            if ($productId === null || $quantity === null) {
                continue;
            }

            $lineMap[$productId] = ($lineMap[$productId] ?? 0) + $quantity;
        }

        if (empty($lineMap)) {
            $errors[] = 'Add at least one valid sale item.';
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $saleTotal = 0.0;
            $lineDetails = [];

            $lockStmt = $pdo->prepare('SELECT id, shoe_name, stock_quantity, selling_price FROM products WHERE id = :id LIMIT 1 FOR UPDATE');

            foreach ($lineMap as $productId => $qty) {
                $lockStmt->execute(['id' => $productId]);
                $product = $lockStmt->fetch();

                if (!$product) {
                    throw new RuntimeException('A selected product no longer exists.');
                }

                if ((int) $product['stock_quantity'] < $qty) {
                    throw new RuntimeException('Insufficient stock for ' . $product['shoe_name'] . '.');
                }

                $price = (float) $product['selling_price'];
                $lineTotal = $price * $qty;

                $lineDetails[] = [
                    'product_id' => $productId,
                    'quantity' => $qty,
                    'price' => $price,
                    'line_total' => $lineTotal,
                ];

                $saleTotal += $lineTotal;
            }

            $saleInsert = $pdo->prepare('INSERT INTO sales (total_amount, processed_by, created_at) VALUES (:total_amount, :processed_by, NOW())');
            $saleInsert->execute([
                'total_amount' => $saleTotal,
                'processed_by' => $staffId,
            ]);

            $saleId = (int) $pdo->lastInsertId();

            $itemInsert = $pdo->prepare('INSERT INTO sales_items (sale_id, product_id, quantity, price, total) VALUES (:sale_id, :product_id, :quantity, :price, :total)');
            $stockUpdate = $pdo->prepare('UPDATE products SET stock_quantity = stock_quantity - :quantity WHERE id = :product_id');
            $moveInsert = $pdo->prepare('INSERT INTO stock_movements (product_id, quantity, movement_type, reason, staff_id, created_at) VALUES (:product_id, :quantity, :movement_type, :reason, :staff_id, NOW())');

            foreach ($lineDetails as $line) {
                $itemInsert->execute([
                    'sale_id' => $saleId,
                    'product_id' => $line['product_id'],
                    'quantity' => $line['quantity'],
                    'price' => $line['price'],
                    'total' => $line['line_total'],
                ]);

                $stockUpdate->execute([
                    'quantity' => $line['quantity'],
                    'product_id' => $line['product_id'],
                ]);

                $moveInsert->execute([
                    'product_id' => $line['product_id'],
                    'quantity' => $line['quantity'],
                    'movement_type' => 'OUT',
                    'reason' => 'Sold via Sale #' . $saleId,
                    'staff_id' => $staffId,
                ]);
            }

            $pdo->commit();
            set_flash('success', 'Sale recorded successfully. Sale ID: #' . $saleId);
            redirect(base_url('sales/sales_history.php'));
        } catch (Throwable $throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $throwable->getMessage();
        }
    }
}

$pageTitle = 'Record Sale';
$activeMenu = 'sell';
$preselectedProductId = to_positive_int($_GET['product_id'] ?? null);
require_once __DIR__ . '/../includes/header.php';
?>
<div class="row g-3">
    <div class="col-xl-9">
        <div class="card card-surface">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 mb-0">New Sales Transaction</h2>
                </div>

                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-danger"><?= e($error) ?></div>
                <?php endforeach; ?>

                <form method="post" id="saleForm">
                    <?= csrf_input() ?>
                    <div class="table-responsive">
                        <table class="table align-middle" id="saleItemsTable">
                            <thead>
                            <tr>
                                <th style="min-width: 280px;">Product</th>
                                <th style="width: 140px;">Qty</th>
                                <th style="width: 160px;">Price</th>
                                <th style="width: 160px;">Total</th>
                                <th style="width: 100px;"></th>
                            </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="addRowBtn"><i class="bi bi-plus-circle"></i> Add Item</button>

                    <div class="mt-4 d-flex justify-content-between align-items-center">
                        <div class="h5 mb-0">Grand Total: <span id="grandTotal">₱0.00</span></div>
                        <button class="btn btn-success" type="submit">Submit Sale</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-3">
        <div class="card card-surface">
            <div class="card-body">
                <h2 class="h6">Sales Notes</h2>
                <ul class="small mb-0">
                    <li>Stock is deducted automatically on submit.</li>
                    <li>Only available stock can be sold.</li>
                    <li>Each item creates a stock out movement record.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php
$productsLookupUrl = json_encode(base_url('api/products_lookup.php'), JSON_THROW_ON_ERROR);
$preselectedIdJs = json_encode($preselectedProductId, JSON_THROW_ON_ERROR);

$extraScripts = '<script>
(() => {
    const tableBody = document.querySelector("#saleItemsTable tbody");
    const saleForm = document.getElementById("saleForm");
    const addRowBtn = document.getElementById("addRowBtn");
    const grandTotalEl = document.getElementById("grandTotal");

    if (!tableBody || !saleForm || !addRowBtn || !grandTotalEl) {
        return;
    }

    let products = [];

    function esc(value) {
        return String(value ?? "")
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/\'/g, "&#039;");
    }

    function money(v) {
        return "\u20B1" + Number(v || 0).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function buildOptions(selectedId) {
        selectedId = selectedId || "";
        const defaultOpt = \'<option value="">Select product</option>\';
        const opts = products.map(function(p) {
            const selected = String(p.id) === String(selectedId) ? "selected" : "";
            const label = p.product_code + " - " + p.shoe_name + " (" + p.brand + ") [Stock: " + p.stock_quantity + "]";
            return \'<option \' + selected + \' value="\' + esc(p.id) + \'" data-price="\' + esc(p.selling_price) + \'" data-stock="\' + esc(p.stock_quantity) + \'">\' + esc(label) + \'</option>\';
        }).join("");

        return defaultOpt + opts;
    }

    function updateRow(row) {
        const select = row.querySelector("select[name=\'product_id[]\']");
        const qtyInput = row.querySelector("input[name=\'quantity[]\']");
        const priceInput = row.querySelector(".item-price");
        const totalEl = row.querySelector(".item-total");

        const selectedOption = select.options[select.selectedIndex];
        const price = Number(selectedOption && selectedOption.dataset.price || 0);
        const stock = Number(selectedOption && selectedOption.dataset.stock || 0);
        const qty = Number(qtyInput.value || 0);

        qtyInput.setAttribute("max", String(stock));
        priceInput.value = money(price);

        const lineTotal = price * qty;
        totalEl.textContent = money(lineTotal);
        row.dataset.lineTotal = String(lineTotal);

        computeGrandTotal();
    }

    function computeGrandTotal() {
        let sum = 0;
        tableBody.querySelectorAll("tr").forEach(function(row) {
            sum += Number(row.dataset.lineTotal || 0);
        });
        grandTotalEl.textContent = money(sum);
    }

    function addRow(selectedId, qty) {
        selectedId = selectedId || "";
        qty = qty || 0;
        const tr = document.createElement("tr");
        tr.innerHTML = \'<td><select name="product_id[]" class="form-select" required>\' + buildOptions(selectedId) + \'</select></td>\' +
            \'<td><input type="number" min="1" value="\' + esc(qty) + \'" name="quantity[]" class="form-control" required></td>\' +
            \'<td><input type="text" class="form-control item-price" value="\u20B10.00" readonly></td>\' +
            \'<td class="item-total">\u20B10.00</td>\' +
            \'<td><button type="button" class="btn btn-outline-danger btn-sm remove-row">Remove</button></td>\';

        const select = tr.querySelector("select[name=\'product_id[]\']");
        const qtyInput = tr.querySelector("input[name=\'quantity[]\']");

        select.addEventListener("change", function() { updateRow(tr); });
        qtyInput.addEventListener("input", function() { updateRow(tr); });

        tr.querySelector(".remove-row").addEventListener("click", function() {
            tr.remove();
            computeGrandTotal();
        });

        tableBody.appendChild(tr);
        updateRow(tr);
    }

    saleForm.addEventListener("submit", function(event) {
        if (!tableBody.querySelector("tr")) {
            event.preventDefault();
            window.alert("Add at least one sale item.");
        }
    });

    async function init() {
        try {
            const payload = await window.App.fetchJSON(' . $productsLookupUrl . ');
            products = payload.rows || [];
        } catch (error) {
            products = [];
        }

        if (' . $preselectedIdJs . ') {
            addRow(' . $preselectedIdJs . ');
        }
    }

    addRowBtn.addEventListener("click", function() { addRow(); });
    init();
})();
</script>';
require_once __DIR__ . '/../includes/footer.php';
