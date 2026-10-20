<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

$productModel  = new Product();
$supplierModel = new Supplier();
$purchaseModel = new Purchase();

$errors = [];
$success = '';

// -------------- POST: create order --------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') === 'create') {
    $supplierId  = (int)($_POST['supplier_id'] ?? 0);
    $supplierNew = trim($_POST['supplier_new'] ?? '');
    $invoiceNo   = trim($_POST['invoice_no'] ?? '');
    $date        = $_POST['purchase_date'] ?? date('Y-m-d');
    $status      = ($_POST['status'] ?? 'received') === 'pending' ? 'pending' : 'received';
    $amountPaid  = (float)($_POST['amount_paid'] ?? 0);
    $notes       = trim($_POST['notes'] ?? '');
    $updateCost  = !empty($_POST['update_cost']);

    // Build items
    $itemPids   = $_POST['item_product_id']   ?? [];
    $itemQtys   = $_POST['item_quantity']     ?? [];
    $itemCrates = $_POST['item_crates']       ?? [];
    $itemPacks  = $_POST['item_pack_size']    ?? [];
    $itemCosts  = $_POST['item_unit_cost']    ?? [];

    $items = [];
    foreach ($itemPids as $k => $pid) {
        $pid = (int)$pid;
        if ($pid <= 0) continue;

        $cr   = (int)($itemCrates[$k] ?? 0);
        $pk   = max(1, (int)($itemPacks[$k] ?? 1));
        $rawQ = (int)($itemQtys[$k] ?? 0);
        $cost = (float)($itemCosts[$k] ?? 0);

        // If user entered crates, total units = crates × pack_size
        $totalQty = $cr > 0 ? $cr * $pk : $rawQ;
        if ($totalQty <= 0) continue;

        $items[] = [
            'product_id'      => $pid,
            'quantity'        => $totalQty,
            'quantity_crates' => $cr,
            'pack_size'       => $pk,
            'unit_cost'       => $cost,
        ];
    }

    // Validate supplier — if new name typed, create it
    if ($supplierId <= 0 && $supplierNew !== '') {
        $supplierModel->addSupplier($supplierNew, '', '', '', '', 'active');
        $supplierId = (int)$supplierModel->lastInsertId();
    }
    if ($supplierId <= 0) $errors[] = 'Select or create a supplier.';
    if (!$date)           $errors[] = 'Purchase date required.';
    if (empty($items))    $errors[] = 'Add at least one item.';

    if (!$errors) {
        PeriodLock::ensureUnlocked($date, 'create a stock-in order in');
        $id = $purchaseModel->createPurchase($supplierId, $invoiceNo, $date, $amountPaid, $notes, $items, $updateCost, (int)$_SESSION['user_id'], $status);
        if ($id) {
            AuditLog::record('purchase.create', 'purchases', $id,
                'Stock In: ' . count($items) . ' item(s), status=' . $status . ', paid=' . number_format($amountPaid, 2));
            header('Location: stock_in.php?success=' . urlencode(($status === 'received' ? 'Order received — stock updated.' : 'Order saved as pending.')));
            exit;
        }
        $errors[] = 'Failed to save. Please try again.';
    }
}

// -------------- POST: mark received --------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') === 'receive') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0 && $purchaseModel->markReceived($id, (int)$_SESSION['user_id'])) {
        AuditLog::record('purchase.receive', 'purchases', $id, 'Marked as received — stock updated');
        header('Location: stock_in.php?success=' . urlencode('Order received. Stock updated.'));
        exit;
    }
    $errors[] = 'Could not mark as received.';
}

if (!empty($_GET['success'])) $success = $_GET['success'];

// -------------- Data for the page --------------
$suppliers = $supplierModel->getActive();
$products  = $productModel->getActiveProducts();

$jsProducts = array_map(fn($p) => [
    'id'        => (int)$p['id'],
    'name'      => $p['product_name'] . ($p['size'] ? ' ' . $p['size'] : ''),
    'cost'      => (float)$p['cost_price'],
    'pack_size' => (int)($p['pack_size'] ?? 1),
    'stock'     => (int)$p['current_stock'],
], $products);

$pendingOrders  = $purchaseModel->allWithSupplier(null, null, null);
$pendingOrders  = array_values(array_filter($pendingOrders, fn($o) => ($o['status'] ?? 'received') === 'pending'));
$recentReceived = array_values(array_filter($purchaseModel->allWithSupplier(date('Y-m-01'), date('Y-m-d'), null), fn($o) => ($o['status'] ?? 'received') === 'received'));
$recentReceived = array_slice($recentReceived, 0, 20);

$pageTitle = 'Stock In / Purchases';
include __DIR__ . '/../../includes/header.php';
?>

<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<form method="POST" id="orderForm">
  <input type="hidden" name="op" value="create">

  <div class="card mb-3">
    <div class="card-header"><i class="bi bi-cart-plus-fill me-1"></i>New Stock-In Order</div>
    <div class="card-body">

      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Supplier</label>
          <select name="supplier_id" class="form-select">
            <option value="">-- Type new supplier name below --</option>
            <?php foreach ($suppliers as $s): ?>
              <option value="<?= $s['id'] ?>"><?= e($s['supplier_name']) ?></option>
            <?php endforeach; ?>
          </select>
          <input type="text" name="supplier_new" class="form-control mt-2" placeholder="...or type a new supplier name">
          <small class="text-muted">Leave both blank only if you don't track supplier.</small>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Invoice No (optional)</label>
          <input type="text" name="invoice_no" class="form-control" placeholder="e.g. INV-2026-0451">
        </div>
      </div>

      <div class="row">
        <div class="col-md-4 mb-3">
          <label class="form-label">Order Date</label>
          <input type="date" name="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">Amount Paid Now (TZS)</label>
          <input type="number" step="0.01" min="0" name="amount_paid" class="form-control" value="0">
        </div>
        <div class="col-md-4 mb-3">
          <label class="form-label">Order Status</label>
          <select name="status" class="form-select">
            <option value="received">Received — stock arrives now</option>
            <option value="pending">Pending — still on the way</option>
          </select>
        </div>
      </div>

      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" name="update_cost" id="updateCost" value="1" checked>
        <label class="form-check-label" for="updateCost">
          Update product cost prices from this order (recommended)
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
      <button type="button" class="btn btn-sm btn-outline-primary" onclick="addItem()">
        <i class="bi bi-plus-lg"></i> Add Item
      </button>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table mb-0" id="itemsTable">
          <thead>
            <tr>
              <th style="width:30%">Product</th>
              <th style="width:14%">Mode</th>
              <th style="width:12%">Qty</th>
              <th style="width:16%">Unit Price</th>
              <th style="width:18%">Subtotal</th>
              <th style="width:10%"></th>
            </tr>
          </thead>
          <tbody id="itemsBody"></tbody>
          <tfoot>
            <tr>
              <td colspan="4" class="text-end fw-bold">Grand Total</td>
              <td class="fw-bold" id="grandTotal" style="font-size:1.15rem;color:#0D6E5D">0.00</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>

  <button type="submit" class="btn btn-primary btn-lg">
    <i class="bi bi-check-circle-fill"></i> Save Order
  </button>
</form>

<hr class="my-4">

<!-- ============ PENDING ORDERS ============ -->
<?php if (!empty($pendingOrders)): ?>
<div class="card mb-3">
  <div class="card-header" style="color:#C98012">
    <i class="bi bi-hourglass-split me-1"></i>Pending Orders (<?= count($pendingOrders) ?>) — Not Yet Received
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>Date</th><th>Supplier</th><th>Invoice</th><th class="text-end">Total</th><th>Status</th><th class="no-print">Action</th></tr></thead>
        <tbody>
          <?php foreach ($pendingOrders as $o): ?>
            <tr class="table-warning">
              <td><?= formatDate($o['purchase_date']) ?></td>
              <td><?= e($o['supplier_name']) ?></td>
              <td><?= e($o['invoice_no'] ?: '—') ?></td>
              <td class="text-end fw-bold"><?= formatMoney($o['total_amount']) ?></td>
              <td><span class="badge bg-warning">Pending</span></td>
              <td class="no-print">
                <a href="<?= BASE_URL ?>views/purchases/view.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-outline-secondary">
                  <i class="bi bi-eye"></i> View
                </a>
                <form method="POST" class="d-inline" onsubmit="return confirm('Mark this order as received? Stock will increase for all items.')">
                  <input type="hidden" name="op" value="receive">
                  <input type="hidden" name="id" value="<?= $o['id'] ?>">
                  <button class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i> Received</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ============ RECENT RECEIVED ============ -->
<div class="card">
  <div class="card-header">
    <i class="bi bi-clock-history me-1"></i>Received Orders — This Month
    <a href="<?= BASE_URL ?>views/purchases/index.php" class="btn btn-sm btn-outline-secondary ms-auto">Full History →</a>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>Date</th><th>Supplier</th><th>Invoice</th><th class="text-end">Total</th><th class="text-end">Paid</th><th>Status</th><th class="no-print"></th></tr></thead>
        <tbody>
          <?php if (empty($recentReceived)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No received orders this month yet.</td></tr>
          <?php else: foreach ($recentReceived as $o): ?>
            <tr>
              <td><?= formatDate($o['purchase_date']) ?></td>
              <td><?= e($o['supplier_name']) ?></td>
              <td><?= e($o['invoice_no'] ?: '—') ?></td>
              <td class="text-end"><?= formatMoney($o['total_amount']) ?></td>
              <td class="text-end"><?= formatMoney($o['amount_paid']) ?></td>
              <td>
                <span class="badge bg-success">Received</span>
                <?php if ($o['payment_status'] === 'partial'): ?><span class="badge bg-warning">Partial Pay</span><?php endif; ?>
                <?php if ($o['payment_status'] === 'unpaid'): ?><span class="badge bg-danger">Unpaid</span><?php endif; ?>
              </td>
              <td class="no-print">
                <a href="<?= BASE_URL ?>views/purchases/view.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-outline-secondary">
                  <i class="bi bi-eye"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
const PRODUCTS = <?= json_encode($jsProducts) ?>;

function addItem() {
  const tbody = document.getElementById('itemsBody');
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td>
      <input type="text" class="form-control product-lookup" placeholder="Type product..." autocomplete="off" required>
      <input type="hidden" name="item_product_id[]" class="product-id-field">
      <input type="hidden" name="item_pack_size[]" class="pack-size-field" value="1">
      <div class="product-dropdown" style="display:none"></div>
    </td>
    <td>
      <select class="form-select mode-select">
        <option value="units">Units</option>
        <option value="crates" style="display:none">Crates</option>
      </select>
    </td>
    <td>
      <input type="number" min="1" name="item_quantity[]" class="form-control qty-field" value="1">
      <input type="number" min="1" name="item_crates[]" class="form-control crates-field" style="display:none" value="1">
    </td>
    <td>
      <input type="number" step="0.01" min="0" name="item_unit_cost[]" class="form-control cost-field" value="0">
    </td>
    <td class="subtotal fw-bold text-end" style="padding-top:1rem">0.00</td>
    <td>
      <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove(); recalc()">
        <i class="bi bi-x"></i>
      </button>
    </td>
  `;
  tbody.appendChild(tr);
  attachRowEvents(tr);
}

function attachRowEvents(tr) {
  const lookupInput = tr.querySelector('.product-lookup');
  const hiddenId    = tr.querySelector('.product-id-field');
  const packField   = tr.querySelector('.pack-size-field');
  const dropdown    = tr.querySelector('.product-dropdown');
  const modeSel     = tr.querySelector('.mode-select');
  const qtyField    = tr.querySelector('.qty-field');
  const cratesField = tr.querySelector('.crates-field');
  const costField   = tr.querySelector('.cost-field');

  lookupInput.addEventListener('input', () => {
    const q = lookupInput.value.toLowerCase().trim();
    if (q.length < 1) { dropdown.style.display = 'none'; return; }
    const matches = PRODUCTS.filter(p => p.name.toLowerCase().includes(q)).slice(0, 8);
    if (!matches.length) {
      dropdown.innerHTML = '<div class="product-dropdown-empty">No matches</div>';
      dropdown.style.display = 'block';
      return;
    }
    dropdown.innerHTML = matches.map(p => `
      <div class="product-dropdown-item" data-id="${p.id}" data-cost="${p.cost}" data-pack="${p.pack_size}" data-name="${p.name.replace(/"/g,'&quot;')}">
        <strong>${p.name}</strong>
        <span class="text-muted small ms-2">TZS ${p.cost.toLocaleString()}</span>
        ${p.pack_size > 1 ? `<span class="badge bg-info ms-1">${p.pack_size}/crate</span>` : ''}
      </div>
    `).join('');
    dropdown.style.display = 'block';
  });

  dropdown.addEventListener('click', (e) => {
    const item = e.target.closest('.product-dropdown-item');
    if (!item) return;
    const pack = parseInt(item.dataset.pack);
    hiddenId.value = item.dataset.id;
    packField.value = pack;
    lookupInput.value = item.dataset.name;
    costField.value = item.dataset.cost;
    dropdown.style.display = 'none';

    // Enable Crates mode if pack > 1
    if (pack > 1) {
      modeSel.querySelector('option[value="crates"]').style.display = '';
    } else {
      modeSel.value = 'units';
      modeSel.querySelector('option[value="crates"]').style.display = 'none';
      qtyField.style.display = '';
      cratesField.style.display = 'none';
    }
    recalc();
  });

  document.addEventListener('click', (e) => {
    if (!lookupInput.contains(e.target) && !dropdown.contains(e.target)) {
      dropdown.style.display = 'none';
    }
  });

  modeSel.addEventListener('change', () => {
    if (modeSel.value === 'crates') {
      qtyField.style.display = 'none';
      cratesField.style.display = '';
    } else {
      qtyField.style.display = '';
      cratesField.style.display = 'none';
    }
    recalc();
  });

  qtyField.addEventListener('input', recalc);
  cratesField.addEventListener('input', recalc);
  costField.addEventListener('input', recalc);
}

function recalc() {
  let grand = 0;
  document.querySelectorAll('#itemsBody tr').forEach(tr => {
    const pack = parseInt(tr.querySelector('.pack-size-field').value || 1);
    const mode = tr.querySelector('.mode-select').value;
    const qtyIn = parseInt(tr.querySelector('.qty-field').value || 0);
    const cratesIn = parseInt(tr.querySelector('.crates-field').value || 0);
    const cost = parseFloat(tr.querySelector('.cost-field').value || 0);

    const totalUnits = mode === 'crates' ? (cratesIn * pack) : qtyIn;
    const sub = totalUnits * cost;
    tr.querySelector('.subtotal').textContent = sub.toLocaleString('en-US', { minimumFractionDigits: 2 });
    grand += sub;
  });
  document.getElementById('grandTotal').textContent = grand.toLocaleString('en-US', { minimumFractionDigits: 2 });
}

// Start with one empty row
addItem();
</script>

<style>
.product-dropdown {
  position: absolute;
  z-index: 200;
  background: #fff;
  border: 1px solid var(--border-2);
  border-radius: var(--r);
  box-shadow: 0 8px 24px rgba(6,51,40,.15);
  max-height: 300px;
  overflow-y: auto;
  min-width: 320px;
}
.product-dropdown-item {
  padding: .55rem .8rem;
  cursor: pointer;
  border-bottom: 1px solid var(--border);
}
.product-dropdown-item:last-child { border-bottom: 0; }
.product-dropdown-item:hover { background: var(--g50); }
.product-dropdown-empty { padding: .8rem; text-align: center; color: var(--muted); font-size: .85rem; }
td { position: relative; }
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>