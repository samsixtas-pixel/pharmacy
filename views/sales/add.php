<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireLogin();

$productModel = new Product();
$saleModel    = new Sale();
$errors = [];
$success = '';

// ---------------- POST: record sale ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $quantity  = (int)($_POST['quantity'] ?? 0);
    $amountPaid = (float)($_POST['amount_paid'] ?? 0);
    $isDebt    = !empty($_POST['is_debt']);
    $customer  = $isDebt ? trim($_POST['customer_name'] ?? '') : 'Walk-in';
    $phone     = $isDebt ? trim($_POST['customer_phone'] ?? '') : null;

    // No future sales allowed (prevents fake revenue)
    $saleDate = Security::safePastDate(date('Y-m-d'), 0);

    if ($productId <= 0) $errors[] = 'Please select a product.';
    if ($quantity <= 0)  $errors[] = 'Quantity must be at least 1.';
    if ($isDebt && $customer === '') $errors[] = 'Customer name required for debts.';
    if ($isDebt && $phone === '')    $errors[] = 'Customer phone required for debts.';

    if (!$errors) {
        $product = $productModel->find($productId);
        if (!$product) {
            $errors[] = 'Product not found.';
        } elseif ($quantity > (int)$product['current_stock']) {
            $errors[] = 'Not enough stock (available: ' . (int)$product['current_stock'] . ').';
        } else {
            $gross = (float)$product['selling_price'] * $quantity;
            $paid  = $isDebt ? min($amountPaid, $gross) : $gross;   // full sale auto-paid unless debt

              $ok = $saleModel->addSale(
                $customer,
                $productId,
                $quantity,
                (float)$product['cost_price'],
                (float)$product['selling_price'],
                0.0,
                $paid,
                $saleDate,
                (int)$_SESSION['user_id'],
                $phone
            );

            if ($ok) {
                $newId = (int)$saleModel->lastInsertId();
                AuditLog::record('sale.create', 'sales', $newId,
                    ($isDebt ? 'DEBT: ' : 'SALE: ') . $quantity . 'x ' . $product['product_name'] .
                    ' | paid ' . number_format($paid, 2) .
                    ($isDebt ? ' | customer ' . $customer : ''));
                header('Location: add.php?success=' . urlencode($isDebt ? 'Debt recorded.' : 'Sale recorded.'));
                exit;
            }
            $errors[] = 'Failed to record sale. Please try again.';
        }
    }
}

if (!empty($_GET['success'])) $success = $_GET['success'];

$products = $productModel->getActiveProducts();

// Today's sales for this staff (top of page)
$db = Database::getConnection();
$stmt = $db->prepare(
    "SELECT s.*, p.product_name, p.size
     FROM sales s JOIN products p ON s.product_id = p.id
     WHERE s.user_id = :uid AND s.sale_date = CURDATE()
     ORDER BY s.id DESC"
);
$stmt->execute(['uid' => (int)$_SESSION['user_id']]);
$todaySales = $stmt->fetchAll();
$todayTotal = array_sum(array_column($todaySales, 'total_amount'));

$pageTitle = 'Fast Sale';
include __DIR__ . '/../../includes/header.php';
?>

<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<?php if ($success): ?>
  <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<!-- ============ TODAY'S SALES (top of page) ============ -->
<div class="card mb-3">
  <div class="card-header d-flex justify-content-between">
    <span><i class="bi bi-clock-history me-1"></i>Today's Sales — <strong><?= count($todaySales) ?></strong></span>
    <span class="text-success fw-bold"><?= formatMoney($todayTotal) ?></span>
  </div>
  <div class="card-body p-0" style="max-height:180px;overflow-y:auto">
    <table class="table table-sm table-hover mb-0">
      <thead><tr><th>Time</th><th>Product</th><th>Qty</th><th class="text-end">Total</th></tr></thead>
      <tbody>
        <?php if (empty($todaySales)): ?>
          <tr><td colspan="4" class="text-center text-muted py-2 small">No sales yet today.</td></tr>
        <?php else: foreach ($todaySales as $s): ?>
          <tr>
            <td class="small text-muted"><?= date('H:i', strtotime($s['created_at'])) ?></td>
            <td><?= e($s['product_name']) ?> <span class="text-muted small"><?= e($s['size']) ?></span></td>
            <td><?= (int)$s['quantity'] ?></td>
            <td class="text-end fw-bold"><?= formatMoney($s['total_amount']) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ============ FAST SALE FORM ============ -->
<div class="card">
  <div class="card-header"><i class="bi bi-lightning-charge-fill me-1"></i>Record Sale</div>
  <div class="card-body">

    <form method="POST" id="saleForm" novalidate>
      <input type="hidden" name="product_id" id="productId" value="">

      <!-- Product Typeahead -->
      <div class="mb-3 position-relative">
        <label class="form-label">Product — start typing</label>
        <input type="text" id="productSearch" class="form-control form-control-lg"
               placeholder="Type product name..." autocomplete="off" autofocus>
        <div id="productResults" class="typeahead-results" style="display:none"></div>
        <small id="productPicked" class="text-muted">No product selected.</small>
      </div>

      <!-- Quantity Quick Buttons -->
      <div class="mb-3">
        <label class="form-label">Quantity</label>
        <div class="qty-buttons mb-2">
          <?php foreach ([1,2,3,4,5,6,12,20] as $q): ?>
            <button type="button" class="qty-btn" data-qty="<?= $q ?>"><?= $q ?></button>
          <?php endforeach; ?>
        </div>
        <input type="number" min="1" name="quantity" id="quantityInput"
               class="form-control form-control-lg" value="1" required>
      </div>

      <!-- Total (auto) -->
      <div class="mb-3">
        <label class="form-label">Total (auto)</label>
        <input type="text" id="totalDisplay" class="form-control form-control-lg fw-bold text-end"
               readonly value="0.00" style="font-size:1.5rem;color:#0D6E5D">
      </div>

      <!-- Debt toggle -->
      <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" id="isDebt" name="is_debt" value="1">
        <label class="form-check-label fw-bold" for="isDebt">
          <i class="bi bi-person-badge"></i> This is a DEBT (customer pays later)
        </label>
      </div>

      <!-- Debt fields (hidden by default) -->
      <div id="debtFields" style="display:none">
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Customer Name *</label>
            <input type="text" name="customer_name" id="customerName" class="form-control form-control-lg" placeholder="e.g. Juma">
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Phone Number *</label>
            <input type="tel" name="customer_phone" id="customerPhone" class="form-control form-control-lg" placeholder="0712 345 678">
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Amount Paid Now (TZS)</label>
          <input type="number" step="0.01" min="0" name="amount_paid" id="amountPaid" class="form-control form-control-lg" value="0">
          <small class="text-muted">Leave 0 for full debt. Enter partial amount if they paid something.</small>
        </div>
      </div>

      <button type="submit" class="btn btn-success btn-lg w-100">
        <i class="bi bi-check-circle-fill"></i> Record Sale
      </button>
    </form>

  </div>
</div>

<script>
// =========== PRODUCTS DATA ===========
const PRODUCTS = <?= json_encode(array_map(fn($p) => [
    'id'    => (int)$p['id'],
    'name'  => $p['product_name'] . ($p['size'] ? ' ' . $p['size'] : ''),
    'price' => (float)$p['selling_price'],
    'stock' => (int)$p['current_stock'],
], $products)) ?>;

const searchInput = document.getElementById('productSearch');
const resultsBox  = document.getElementById('productResults');
const pickedEl    = document.getElementById('productPicked');
const hiddenId    = document.getElementById('productId');
const qtyInput    = document.getElementById('quantityInput');
const totalEl     = document.getElementById('totalDisplay');
const isDebtCb    = document.getElementById('isDebt');
const debtFields  = document.getElementById('debtFields');
const amountPaid  = document.getElementById('amountPaid');

let pickedPrice = 0;

// =========== TYPEAHEAD ===========
searchInput.addEventListener('input', function () {
  const q = searchInput.value.toLowerCase().trim();
  if (q.length < 1) { resultsBox.style.display = 'none'; return; }
  const matches = PRODUCTS.filter(p => p.name.toLowerCase().includes(q)).slice(0, 8);
  if (matches.length === 0) {
    resultsBox.innerHTML = '<div class="typeahead-empty">No products match</div>';
    resultsBox.style.display = 'block';
    return;
  }
  resultsBox.innerHTML = matches.map(p => `
    <div class="typeahead-item" data-id="${p.id}" data-price="${p.price}" data-name="${p.name.replace(/"/g,'&quot;')}">
      <strong>${p.name}</strong>
      <span class="ms-2 text-muted small"><?php echo 'TZS'; ?> ${p.price.toLocaleString()}</span>
      <span class="ms-2 badge ${p.stock > 5 ? 'bg-success' : (p.stock > 0 ? 'bg-warning' : 'bg-danger')}">stock ${p.stock}</span>
    </div>
  `).join('');
  resultsBox.style.display = 'block';
});

resultsBox.addEventListener('click', function (e) {
  const item = e.target.closest('.typeahead-item');
  if (!item) return;
  hiddenId.value = item.dataset.id;
  pickedPrice = parseFloat(item.dataset.price);
  searchInput.value = item.dataset.name;
  pickedEl.textContent = 'Selected: ' + item.dataset.name + ' — TZS ' + pickedPrice.toLocaleString();
  pickedEl.className = 'text-success small';
  resultsBox.style.display = 'none';
  qtyInput.focus();
  recalc();
});

document.addEventListener('click', function (e) {
  if (!searchInput.contains(e.target) && !resultsBox.contains(e.target)) {
    resultsBox.style.display = 'none';
  }
});

// =========== QUICK QTY BUTTONS ===========
document.querySelectorAll('.qty-btn').forEach(btn => {
  btn.addEventListener('click', function () {
    qtyInput.value = btn.dataset.qty;
    document.querySelectorAll('.qty-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    recalc();
  });
});

qtyInput.addEventListener('input', recalc);

function recalc() {
  const q = parseInt(qtyInput.value || 0);
  const total = pickedPrice * q;
  totalEl.value = total.toLocaleString('en-US', { minimumFractionDigits: 2 });
  if (isDebtCb.checked) amountPaid.max = total;
}

// =========== DEBT TOGGLE ===========
isDebtCb.addEventListener('change', function () {
  debtFields.style.display = isDebtCb.checked ? 'block' : 'none';
  if (isDebtCb.checked) {
    document.getElementById('customerName').focus();
  }
});

// =========== SUBMIT GUARD ===========
document.getElementById('saleForm').addEventListener('submit', function (e) {
  if (!hiddenId.value) {
    e.preventDefault();
    Swal.fire({ icon: 'warning', title: 'Pick a product', text: 'Type the product name and click one of the suggestions.' });
    searchInput.focus();
    return;
  }
  if (isDebtCb.checked) {
    if (!document.getElementById('customerName').value.trim()) {
      e.preventDefault();
      Swal.fire({ icon: 'warning', title: 'Customer name required for debts' });
      return;
    }
    if (!document.getElementById('customerPhone').value.trim()) {
      e.preventDefault();
      Swal.fire({ icon: 'warning', title: 'Customer phone required for debts' });
      return;
    }
  }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>