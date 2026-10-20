<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireLogin();

$productModel = new Product();
$keyword = trim($_GET['q'] ?? '');
$showOnlyLow = !empty($_GET['low']);

$products = $keyword !== '' ? $productModel->search($keyword) : $productModel->all('product_name ASC');
if ($showOnlyLow) {
    $products = array_values(array_filter($products, fn($p) => (int)$p['current_stock'] <= LOW_STOCK_THRESHOLD));
}

$lowCount = 0;
foreach ($productModel->all('product_name ASC') as $p) {
    if ((int)$p['current_stock'] <= LOW_STOCK_THRESHOLD) $lowCount++;
}

$pageTitle = 'Available Stock';
include __DIR__ . '/../../includes/header.php';
?>

<?php if ($lowCount > 0): ?>
  <div class="alert alert-warning d-flex align-items-center gap-2">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <div><strong><?= $lowCount ?></strong> product(s) low on stock. Tell the boss to refill.
      <a href="?low=1" class="alert-link ms-1">View low only →</a>
    </div>
  </div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <form method="GET" class="d-flex gap-2" style="max-width:400px">
    <input type="text" name="q" class="form-control" placeholder="Search product..." value="<?= e($keyword) ?>">
    <button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
  </form>
  <div class="btn-group">
    <a href="?" class="btn <?= !$showOnlyLow ? 'btn-primary' : 'btn-outline-primary' ?>">All</a>
    <a href="?low=1" class="btn <?= $showOnlyLow ? 'btn-warning' : 'btn-outline-warning' ?>">
      Low Stock (<?= $lowCount ?>)
    </a>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <i class="bi bi-box-seam me-1"></i>Available Stock
    <span class="text-muted small ms-2"><?= count($products) ?> product(s)</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr>
          <th>#</th><th>Product</th><th>Size</th><th>Category</th>
          <th class="text-end">Selling Price</th>
          <th class="text-center">Available</th><th>Level</th>
        </tr></thead>
        <tbody>
          <?php if (empty($products)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No products.</td></tr>
          <?php else: foreach ($products as $i => $p):
            $stock = (int)$p['current_stock'];
            if ($stock <= 0)              { $cls = 'bg-danger'; $lbl = 'Out of stock'; }
            elseif ($stock <= LOW_STOCK_THRESHOLD) { $cls = 'bg-warning'; $lbl = 'Low'; }
            else                          { $cls = 'bg-success'; $lbl = 'Available'; }
          ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><strong><?= e($p['product_name']) ?></strong></td>
              <td><?= e($p['size']) ?></td>
              <td class="small"><?= ucwords(str_replace('_',' ', e($p['category']))) ?></td>
              <td class="text-end"><?= formatMoney($p['selling_price']) ?></td>
              <td class="text-center fw-bold" style="font-size:1.05rem"><?= $stock ?></td>
              <td><span class="badge <?= $cls ?>"><?= $lbl ?></span></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>