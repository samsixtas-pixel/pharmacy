<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireLogin();

$reportModel = new Report();
$keyword = trim($_GET['q'] ?? '');
$rows = $reportModel->productReport($keyword);

$pageTitle = 'Product Report';
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
  <form method="GET" class="d-flex" style="max-width:350px">
    <input type="text" name="q" class="form-control me-2" placeholder="Search beverage..." value="<?= e($keyword) ?>">
    <button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
  </form>
  <div>
    <button onclick="window.print()" class="btn btn-outline-primary"><i class="bi bi-printer"></i> Print</button>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
  </div>
</div>

<div class="card">
  <div class="card-header">Product Report <?= $keyword ? '— filtered by "' . e($keyword) . '"' : '' ?></div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>#</th><th>Beverage Name</th><th>Selling Price</th><th>Current Stock</th><th>Status</th></tr></thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">No products found.</td></tr>
          <?php else: foreach ($rows as $i => $p): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><?= e($p['product_name']) ?></td>
              <td><?= formatMoney($p['selling_price']) ?></td>
              <td><?= (int) $p['current_stock'] ?></td>
              <td><?= statusBadge($p['status']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
