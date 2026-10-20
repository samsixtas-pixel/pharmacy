<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

$productModel = new Product();
$settings = new Settings();

$errors = [];
$success = '';

// ---------- Save all prices ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') === 'save_all') {
    if ($settings->isPricesLocked()) {
        $errors[] = 'Prices are currently locked. Unlock them first.';
    } else {
        $ids    = $_POST['id']       ?? [];
        $costs  = $_POST['cost']     ?? [];
        $sells  = $_POST['selling']  ?? [];
        $reason = trim($_POST['reason'] ?? 'Bulk price update');

        $updates = [];
        foreach ($ids as $i => $id) {
            $updates[] = [
                'id'      => (int)$id,
                'cost'    => (float)($costs[$i] ?? 0),
                'selling' => (float)($sells[$i] ?? 0),
                'reason'  => $reason,
            ];
        }
        $changed = $productModel->bulkUpdatePrices($updates, (int)$_SESSION['user_id']);

        AuditLog::record('pricing.bulk_update', 'products', null,
            "Bulk price update: {$changed} product(s) changed. Reason: {$reason}");

        header('Location: index.php?success=' . urlencode($changed . ' product(s) updated.'));
        exit;
    }
}

// ---------- Lock / unlock prices ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') === 'toggle_lock') {
    $new = $settings->isPricesLocked() ? '0' : '1';
    $settings->set('prices_locked', $new);
    AuditLog::record('pricing.lock', null, null, 'Prices ' . ($new === '1' ? 'LOCKED' : 'UNLOCKED'));
    header('Location: index.php?success=' . urlencode('Prices ' . ($new === '1' ? 'locked' : 'unlocked') . '.'));
    exit;
}

// ---------- Bulk markup tool ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') === 'apply_markup') {
    if ($settings->isPricesLocked()) {
        $errors[] = 'Prices are locked. Unlock them first.';
    } else {
        $pct = (float)($_POST['markup_pct'] ?? 0);
        $applyTo = $_POST['apply_to'] ?? 'selling_from_cost'; // or 'cost_from_selling'
        $selectedIds = $_POST['selected_ids'] ?? [];

        if ($pct <= 0) {
            $errors[] = 'Markup % must be positive.';
        } elseif (empty($selectedIds)) {
            $errors[] = 'Select at least one product.';
        } else {
            $updates = [];
            foreach ($selectedIds as $pid) {
                $p = $productModel->find((int)$pid);
                if (!$p) continue;
                $cost = (float)$p['cost_price'];
                $sell = (float)$p['selling_price'];

                if ($applyTo === 'selling_from_cost') {
                    $newSell = round($cost * (1 + $pct / 100), 2);
                    $updates[] = ['id' => $p['id'], 'cost' => $cost, 'selling' => $newSell, 'reason' => "Markup {$pct}% on cost"];
                } else {
                    $newCost = round($sell / (1 + $pct / 100), 2);
                    $updates[] = ['id' => $p['id'], 'cost' => $newCost, 'selling' => $sell, 'reason' => "Back-calc cost from {$pct}% markup"];
                }
            }
            $changed = $productModel->bulkUpdatePrices($updates, (int)$_SESSION['user_id']);
            AuditLog::record('pricing.markup', 'products', null,
                "Applied {$pct}% markup to {$changed} product(s)");
            header('Location: index.php?success=' . urlencode("Applied markup to {$changed} product(s)."));
            exit;
        }
    }
}

// ---------- Filter/search ----------
$keyword  = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');
$onlyIssues = (int)($_GET['only_issues'] ?? 0);

if ($keyword !== '') {
    $products = $productModel->search($keyword);
} else {
    $products = $productModel->all('product_name ASC');
}
if ($category !== '') {
    $products = array_values(array_filter($products, fn($p) => $p['category'] === $category));
}
if ($onlyIssues) {
    $products = array_values(array_filter($products, function ($p) {
        return ((float)$p['selling_price'] === 0.0) ||
               ((float)$p['selling_price'] < (float)$p['cost_price']);
    }));
}

if (!empty($_GET['success'])) $success = $_GET['success'];

$isLocked = $settings->isPricesLocked();
$defaultMarkup = (float)$settings->get('default_markup_pct', '30');

$categories = [
    'beers'            => 'Beers',
    'wines'            => 'Wines',
    'whiskeys_spirits' => 'Whiskeys & Spirits',
    'soft_drinks'      => 'Soft Drinks',
    'energy'           => 'Energy Drinks',
    'water'            => 'Water',
];

$pageTitle = 'Pricing Control';
include __DIR__ . '/../../includes/header.php';
?>

<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<?php if ($isLocked): ?>
  <div class="alert alert-warning d-flex justify-content-between align-items-center">
    <div><i class="bi bi-lock-fill me-1"></i> <strong>Prices are LOCKED.</strong> Editing is disabled.</div>
    <form method="POST" style="display:inline">
      <input type="hidden" name="op" value="toggle_lock">
      <button class="btn btn-sm btn-warning"><i class="bi bi-unlock"></i> Unlock</button>
    </form>
  </div>
<?php else: ?>
  <div class="alert alert-success d-flex justify-content-between align-items-center">
    <div><i class="bi bi-unlock-fill me-1"></i> Prices are unlocked — you can edit and save.</div>
    <form method="POST" style="display:inline">
      <input type="hidden" name="op" value="toggle_lock">
      <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-lock"></i> Lock Prices</button>
    </form>
  </div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <form method="GET" class="d-flex gap-2 flex-wrap align-items-center">
    <input type="text" name="q" class="form-control" style="max-width:220px" placeholder="Search product..." value="<?= e($keyword) ?>">
    <select name="category" class="form-select" style="max-width:180px">
      <option value="">All Categories</option>
      <?php foreach ($categories as $k => $v): ?>
        <option value="<?= $k ?>" <?= $category === $k ? 'selected' : '' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
    <label class="mb-0 d-flex align-items-center gap-1">
      <input type="checkbox" name="only_issues" value="1" <?= $onlyIssues ? 'checked' : '' ?>>
      Only price issues
    </label>
    <button class="btn btn-outline-secondary"><i class="bi bi-funnel"></i> Filter</button>
  </form>
  <a href="history.php" class="btn btn-outline-primary"><i class="bi bi-clock-history"></i> Price History</a>
</div>

<div class="alert alert-info small">
  <strong>Only prices can be edited here.</strong> Product name, stock, category, and status are still managed on the Products page.
  Every change is logged in <strong>Audit Log</strong> and <strong>Price History</strong>.
</div>

<form method="POST" id="pricingForm">
  <input type="hidden" name="op" value="save_all">

  <!-- Bulk markup toolbar -->
  <div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-2 align-items-end">
      <div>
        <label class="form-label mb-1 small">Bulk markup %</label>
        <input type="number" step="0.1" min="0.1" name="markup_pct" class="form-control" style="max-width:120px" value="<?= number_format($defaultMarkup, 1) ?>">
      </div>
      <div>
        <label class="form-label mb-1 small">Apply</label>
        <select name="apply_to" class="form-select" style="max-width:220px">
          <option value="selling_from_cost">Selling ← Cost × (1 + %)</option>
          <option value="cost_from_selling">Cost ← Selling ÷ (1 + %)</option>
        </select>
      </div>
      <button type="button" class="btn btn-outline-primary" onclick="applyMarkupToSelected()">
        <i class="bi bi-percent"></i> Apply to selected rows
      </button>
      <button type="button" class="btn btn-outline-secondary" onclick="toggleAllChecked()">
        <i class="bi bi-check2-square"></i> Toggle Select All
      </button>
    </div>
  </div>

  <!-- Main editable table -->
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span>Products (<?= count($products) ?>)</span>
      <span class="text-muted small">Red = selling below cost or missing</span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive" style="max-height:70vh">
        <table class="table table-hover mb-0" id="pricingTable">
          <thead style="position: sticky; top: 0; z-index: 2;">
            <tr>
              <th style="width:32px"><input type="checkbox" id="checkAll" onchange="toggleAllChecked(this)"></th>
              <th>Product</th>
              <th style="width:90px">Category</th>
              <th style="width:70px">Size</th>
              <th style="width:110px" class="text-end">Cost (TZS)</th>
              <th style="width:110px" class="text-end">Selling (TZS)</th>
              <th style="width:80px" class="text-end">Margin</th>
              <th style="width:80px" class="text-end">Markup</th>
              <th style="width:60px" class="text-end">Stock</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($products)): ?>
              <tr><td colspan="9" class="text-center text-muted py-4">No products match your filter.</td></tr>
            <?php else: foreach ($products as $p):
              $cost = (float)$p['cost_price'];
              $sell = (float)$p['selling_price'];
              $margin = $sell > 0 ? (($sell - $cost) / $sell) * 100 : 0;
              $markup = $cost > 0 ? (($sell - $cost) / $cost) * 100 : 0;
              $problem = ($sell <= 0) || ($sell < $cost);
            ?>
              <tr class="<?= $problem ? 'table-danger' : '' ?>" data-id="<?= $p['id'] ?>">
                <td>
                  <input type="checkbox" name="selected_ids[]" value="<?= $p['id'] ?>" class="row-check">
                  <input type="hidden" name="id[]" value="<?= $p['id'] ?>">
                </td>
                <td>
                  <strong><?= e($p['product_name']) ?></strong>
                  <?php if ($problem): ?>
                    <span class="badge bg-danger ms-1"><?= $sell <= 0 ? 'No price' : 'Loss' ?></span>
                  <?php endif; ?>
                </td>
                <td class="small"><?= e($categories[$p['category']] ?? $p['category']) ?></td>
                <td class="small"><?= e($p['size']) ?></td>
                <td>
                  <input type="number" step="0.01" min="0" name="cost[]"
                         value="<?= number_format($cost, 2, '.', '') ?>"
                         class="form-control form-control-sm text-end price-input"
                         data-role="cost" oninput="recalcRow(this)" <?= $isLocked ? 'disabled' : '' ?>>
                </td>
                <td>
                  <input type="number" step="0.01" min="0" name="selling[]"
                         value="<?= number_format($sell, 2, '.', '') ?>"
                         class="form-control form-control-sm text-end price-input"
                         data-role="selling" oninput="recalcRow(this)" <?= $isLocked ? 'disabled' : '' ?>>
                </td>
                <td class="text-end small margin-cell <?= $problem ? 'text-danger fw-bold' : '' ?>">
                  <?= number_format($margin, 1) ?>%
                </td>
                <td class="text-end small markup-cell">
                  <?= number_format($markup, 1) ?>%
                </td>
                <td class="text-end small text-muted"><?= (int)$p['current_stock'] ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <?php if (!$isLocked && !empty($products)): ?>
    <div class="card mb-3">
      <div class="card-body d-flex flex-wrap gap-2 align-items-end">
        <div class="flex-grow-1" style="min-width:260px">
          <label class="form-label mb-1 small">Reason for this change (optional)</label>
          <input type="text" name="reason" class="form-control" placeholder="e.g. Supplier price increase, market shift">
        </div>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save All Prices</button>
      </div>
    </div>
  <?php endif; ?>
</form>

<script>
function recalcRow(input) {
  const row = input.closest('tr');
  const cost = parseFloat(row.querySelector('[data-role="cost"]').value || 0);
  const sell = parseFloat(row.querySelector('[data-role="selling"]').value || 0);
  const margin = sell > 0 ? ((sell - cost) / sell * 100) : 0;
  const markup = cost > 0 ? ((sell - cost) / cost * 100) : 0;

  row.querySelector('.margin-cell').textContent = margin.toFixed(1) + '%';
  row.querySelector('.markup-cell').textContent = markup.toFixed(1) + '%';

  const problem = sell <= 0 || sell < cost;
  row.classList.toggle('table-danger', problem);
}
function toggleAllChecked(cb) {
  const checked = cb ? cb.checked : !document.getElementById('checkAll').checked;
  document.getElementById('checkAll').checked = checked;
  document.querySelectorAll('.row-check').forEach(c => c.checked = checked);
}
function applyMarkupToSelected() {
  const pct = parseFloat(document.querySelector('[name="markup_pct"]').value || 0);
  const mode = document.querySelector('[name="apply_to"]').value;
  if (pct <= 0) { Swal.fire({icon:'warning', title:'Enter a markup %'}); return; }

  const rows = document.querySelectorAll('.row-check:checked');
  if (rows.length === 0) { Swal.fire({icon:'info', title:'Select products first'}); return; }

  rows.forEach(chk => {
    const row = chk.closest('tr');
    const costIn = row.querySelector('[data-role="cost"]');
    const sellIn = row.querySelector('[data-role="selling"]');
    const cost = parseFloat(costIn.value || 0);
    const sell = parseFloat(sellIn.value || 0);

    if (mode === 'selling_from_cost') {
      sellIn.value = (cost * (1 + pct / 100)).toFixed(2);
    } else {
      costIn.value = (sell / (1 + pct / 100)).toFixed(2);
    }
    recalcRow(sellIn);
  });
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>