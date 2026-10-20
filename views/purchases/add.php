<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

$supplierModel = new Supplier();
$productModel  = new Product();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplierId = (int)($_POST['supplier_id'] ?? 0);
    $invoiceNo  = trim($_POST['invoice_no'] ?? '');
    $date       = trim($_POST['purchase_date'] ?? date('Y-m-d'));
    $amountPaid = (float)($_POST['amount_paid'] ?? 0);
    $notes      = trim($_POST['notes'] ?? '');
    $updateCost = !empty($_POST['update_cost']);

    $itemPids  = $_POST['item_product_id'] ?? [];
    $itemQtys  = $_POST['item_quantity']   ?? [];
    $itemCosts = $_POST['item_unit_cost']  ?? [];

    $items = [];
    foreach ($itemPids as $k => $pid) {
        $pid = (int)$pid;
        $qty = (int)($itemQtys[$k] ?? 0);
        $cst = (float)($itemCosts[$k] ?? 0);
        if ($pid > 0 && $qty > 0) {
            $items[] = ['product_id' => $pid, 'quantity' => $qty, 'unit_cost' => $cst];
        }
    }

    if ($supplierId <= 0)    $errors[] = 'Please select a supplier.';
    if (!$date)              $errors[] = 'Purchase date is required.';
    if (empty($items))       $errors[] = 'Add at least one item.';
    if (!$errors) PeriodLock::ensureUnlocked($date, 'create a purchase in');

    if (!$errors) {
        $purchaseModel = new Purchase();
        $id = $purchaseModel->createPurchase($supplierId, $invoiceNo, $date, $amountPaid, $notes, $items, $updateCost, (int)$_SESSION['user_id']);
        if ($id) {
            AuditLog::record('purchase.create', 'purchases', $id,
                'Purchase from supplier #' . $supplierId . ' | items: ' . count($items) .
                ' | total paid: ' . number_format($amountPaid, 2));
            header('Location: view.php?id=' . $id . '&success=' . urlencode('Purchase recorded.'));
            exit;
        }
        $errors[] = 'Failed to save purchase.';
    }
}

$suppliers = $supplierModel->getActive();
$products  = $productModel->getActiveProducts();

$pageTitle = 'New Purchase';
include __DIR__ . '/../../includes/header.php';
?>

<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<form method="POST" novalidate id="purchaseForm">
  <div class="card mb-3">
    <div class="card-header">Purchase Header</div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Supplier</label>
          <select name="supplier_id" class="form-select" required>
            <option value="">-- Select Supplier --</option>
            <?php foreach ($suppliers as $s): ?>
              <option value="<?= $s['id'] ?>"><?= e($s['supplier_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Invoice No (supplier's)</label>
          <input type="text" name="invoice_no" class="form-control" placeholder="e.g. INV-2026-0451">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Purchase Date</label>
          <input type="date" name="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Amount Paid Now (TZS)</label>
          <input type="number" step="0.01" min="0" name="amount_paid" class="form-control" value="0">
        </div>
      </div>
      <div class="form-check mb-2">
        <input class="form-check-input" type="checkbox" name="update_cost" id="updateCost" value="1" checked>
        <label class="form-check-label" for="updateCost">
          Update product cost prices to the values entered below (recommended)
        </label>
      </div>
      <div class="mb-0">
        <label class="form-label">Notes</label>
        <input type="text" name="notes" class="form-control" placeholder="Optional">
      </div>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>Items</span>
      <button type="button" class="btn btn-sm btn-outline-primary" onclick="addItem()"><i class="bi bi-plus-lg"></i> Add Item</button>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table mb-0" id="itemsTable">
          <thead><tr><th style="width:40%">Product</th><th style="width:15%">Qty</th><th style="width:20%">Unit Cost</th><th style="width:20%">Subtotal</th><th></th></tr></thead>
          <tbody id="itemsBody">
            <!-- rows injected by JS -->
          </tbody>
          <tfoot>
            <tr>
              <td colspan="3" class="text-end fw-bold">Grand Total</td>
              <td class="fw-bold" id="grandTotal">0.00</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>

  <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Purchase</button>
  <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
</form>

<script>
const products = <?= json_encode(array_map(fn($p) => [
    'id' => (int)$p['id'],
    'name' => $p['product_name'] . ' ' . $p['size'],
    'cost' => (float)$p['cost_price'],
], $products)) ?>;

function addItem() {
  const tbody = document.getElementById('itemsBody');
  const tr = document.createElement('tr');
  let options = '<option value="">-- Choose --</option>';
  products.forEach(p => options += `<option value="${p.id}" data-cost="${p.cost}">${p.name}</option>`);
  tr.innerHTML = `
    <td><select name="item_product_id[]" class="form-select" onchange="onProduct(this)">${options}</select></td>
    <td><input type="number" min="1" name="item_quantity[]" class="form-control" value="1" oninput="recalc()"></td>
    <td><input type="number" step="0.01" min="0" name="item_unit_cost[]" class="form-control" value="0" oninput="recalc()"></td>
    <td class="subtotal fw-bold">0.00</td>
    <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove(); recalc();"><i class="bi bi-x"></i></button></td>
  `;
  tbody.appendChild(tr);
}
function onProduct(sel) {
  const opt = sel.options[sel.selectedIndex];
  const cost = opt.dataset.cost || 0;
  const row = sel.closest('tr');
  row.querySelector('input[name="item_unit_cost[]"]').value = cost;
  recalc();
}
function recalc() {
  let grand = 0;
  document.querySelectorAll('#itemsBody tr').forEach(tr => {
    const qty  = parseFloat(tr.querySelector('input[name="item_quantity[]"]').value || 0);
    const cost = parseFloat(tr.querySelector('input[name="item_unit_cost[]"]').value || 0);
    const sub  = qty * cost;
    tr.querySelector('.subtotal').textContent = sub.toLocaleString('en-US', { minimumFractionDigits: 2 });
    grand += sub;
  });
  document.getElementById('grandTotal').textContent = grand.toLocaleString('en-US', { minimumFractionDigits: 2 });
}
// Start with one row
addItem();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>