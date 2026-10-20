<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireLogin();

$productModel = new Product();
$saleModel = new Sale();
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer  = trim($_POST['customer_name'] ?? '');
    $phone     = trim($_POST['customer_phone'] ?? '');
    $productId = (int)($_POST['product_id'] ?? 0);
    $quantity  = (int)($_POST['quantity'] ?? 0);
    $amountPaid = (float)($_POST['amount_paid'] ?? 0);

    if ($customer === '')  $errors[] = 'Customer name is required.';
    if ($phone === '')     $errors[] = 'Customer phone is required.';
    if ($productId <= 0)   $errors[] = 'Please select a product.';
    if ($quantity <= 0)    $errors[] = 'Quantity must be at least 1.';

    if (!$errors) {
        $product = $productModel->find($productId);
        if (!$product) {
            $errors[] = 'Product not found.';
        } elseif ($quantity > (int)$product['current_stock']) {
            $errors[] = 'Not enough stock (available: ' . (int)$product['current_stock'] . ').';
        } else {
            $gross = (float)$product['selling_price'] * $quantity;
            $paid  = max(0, min($amountPaid, $gross));

            $ok = $saleModel->addSale(
                $customer,
                $productId,
                $quantity,
                (float)$product['cost_price'],
                (float)$product['selling_price'],
                0.0,
                $paid,
                date('Y-m-d'),
                (int)$_SESSION['user_id'],
                $phone
            );

            if ($ok) {
                $newId = (int)$saleModel->lastInsertId();
                AuditLog::record('debt.create', 'sales', $newId,
                    'DEBT: ' . $quantity . 'x ' . $product['product_name'] .
                    ' | customer ' . $customer . ' (' . $phone . ')' .
                    ' | paid ' . number_format($paid, 2));
                header('Location: index.php?success=' . urlencode('Debt recorded for ' . $customer . '.'));
                exit;
            }
            $errors[] = 'Failed to record debt. Please try again.';
        }
    }
}

if (!empty($_GET['success'])) $success = $_GET['success'];

$products = $productModel->getActiveProducts();

$pageTitle = 'Record Debt';
include __DIR__ . '/../../includes/header.php';
?>

<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<?php if ($success): ?>
  <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<div class="card">
  <div class="card-header"><i class="bi bi-person-plus-fill me-1"></i>Record Customer Debt</div>
  <div class="card-body">
    <form method="POST" id="debtForm" novalidate>
      <input type="hidden" name="product_id" id="productId" value="">

      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Customer Name *</label>
          <input type="text" name="customer_name" class="form-control form-control-lg"
                 placeholder="e.g. Juma" required autofocus>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Phone Number *</label>
          <input type="tel" name="customer_phone" class="form-control form-control-lg"
                 placeholder="0712 345 678" required>
        </div>
      </div>

      <div class="mb-3 position-relative">
        <label class="form-label">Product — start typing</label>
        <input type="text" id="productSearch" class="form-control form-control-lg"
               placeholder="Type product name..." autocomplete="off">
        <div id="productResults" class="typeahead-results" style="display:none"></div>
        <small id="productPicked" class="text-muted">No product selected.</small>
      </div>

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

      <div class="mb-3">
        <label class="form-label">Total (auto)</label>
        <input type="text" id="totalDisplay" class="form-control form-control-lg fw-bold text-end"
               readonly value="0.00" style="font-size:1.5rem;color:#0D6E5D">
      </div>

      <div class="mb-3">
        <label class="form-label">Amount Paid Now (TZS)</label>
        <input type="number" step="0.01" min="0" name="amount_paid" id="amountPaid"
               class="form-control form-control-lg" value="0">
        <small class="text-muted">Leave 0 for full debt. Enter partial amount if they paid something.</small>
      </div>

      <button type="submit" class="btn btn-warning btn-lg w-100">
        <i class="bi bi-check-circle-fill"></i> Record Debt
      </button>
      <a href="index.php" class="btn btn-outline-secondary w-100 mt-2">Cancel</a>
    </form>
  </div>
</div>

<script>
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
const amountPaid  = document.getElementById('amountPaid');
let pickedPrice = 0;

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
      <span class="ms-2 text-muted small">TZS ${p.price.toLocaleString()}</span>
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
  amountPaid.max = total;
}

document.getElementById('debtForm').addEventListener('submit', function (e) {
  if (!hiddenId.value) {
    e.preventDefault();
    Swal.fire({ icon: 'warning', title: 'Pick a product', text: 'Type the product name and click a suggestion.' });
    searchInput.focus();
  }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>