<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

$stockAuditModel = new StockAudit();
$productId = isset($_GET['product_id']) ? (int) $_GET['product_id'] : null;
$from = trim((string) ($_GET['from'] ?? ''));
$to = trim((string) ($_GET['to'] ?? ''));
$showOnlyMismatches = isset($_GET['mismatches']) && $_GET['mismatches'] === '1';
$rows = $stockAuditModel->search($productId, $from ?: null, $to ?: null);
if ($showOnlyMismatches) {
    $rows = array_values(array_filter($rows, fn($row) => (int) $row['difference'] !== 0));
}

$productModel = new Product();
$products = $productModel->getActiveProducts();
$summary = $stockAuditModel->summary();
$pageTitle = 'Stock Audit Report';
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
  <button onclick="window.print()" class="btn btn-outline-primary"><i class="bi bi-printer"></i> Print</button>
  <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="text-muted">Total audits</div><h3 class="mb-0"><?= (int) $summary['total_audits'] ?></h3></div></div></div>
  <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="text-muted">Mismatches</div><h3 class="mb-0 text-danger"><?= (int) $summary['mismatches'] ?></h3></div></div></div>
  <div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="text-muted">Corrected</div><h3 class="mb-0 text-success"><?= (int) $summary['corrected'] ?></h3></div></div></div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-3"><label class="form-label">Bevarage</label><select name="product_id" class="form-select"><option value="">All Beverages</option><?php foreach ($products as $product): ?><option value="<?= $product['id'] ?>" <?= $productId === (int) $product['id'] ? 'selected' : '' ?>><?= e($product['product_name']) ?></option><?php endforeach; ?></select></div>
      <div class="col-md-2"><label class="form-label">From</label><input type="date" name="from" class="form-control" value="<?= e($from) ?>"></div>
      <div class="col-md-2"><label class="form-label">To</label><input type="date" name="to" class="form-control" value="<?= e($to) ?>"></div>
      <div class="col-md-2"><label class="form-label">Filter</label><select name="mismatches" class="form-select"><option value="0">All</option><option value="1" <?= $showOnlyMismatches ? 'selected' : '' ?>>Mismatches only</option></select></div>
      <div class="col-md-3 d-grid"><button class="btn btn-primary" type="submit">Apply Filters</button></div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">Audit History</div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>Date</th><th>Beverage</th><th>System</th><th>Physical</th><th>Diff.</th><th>Reason</th><th>Corrected</th><th>Audited By</th><th>Action</th></tr></thead>
        <tbody>
          <?php if (empty($rows)): ?><tr><td colspan="9" class="text-center text-muted py-4">No audit records match the selected filters.</td></tr>
          <?php else: foreach ($rows as $row): ?>
            <tr>
              <td><?= formatDate($row['audit_date']) ?></td>
              <td><?= e($row['product_name']) ?></td>
              <td><?= (int) $row['system_qty'] ?></td>
              <td><?= (int) $row['physical_qty'] ?></td>
              <td class="fw-bold <?= (int) $row['difference'] === 0 ? 'text-success' : 'text-danger' ?>"><?= (int) $row['difference'] ?></td>
              <td><?= e($row['reason'] ?: '—') ?></td>
              <td><?= $row['corrected'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-warning text-dark">No</span>' ?></td>
              <td><?= e($row['audited_by'] ?: '—') ?></td>
              <td><?php if (!$row['corrected']): ?><a href="stock_audit_correct.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-primary">Apply Correction</a><?php else: ?><span class="text-muted">Complete</span><?php endif; ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
