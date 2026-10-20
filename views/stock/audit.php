<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

$productModel = new Product();
$stockAuditModel = new StockAudit();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = (int) ($_POST['product_id'] ?? 0);
    $systemQty = (int) ($_POST['system_qty'] ?? 0);
    $physicalQty = (int) ($_POST['physical_qty'] ?? 0);
    $reason = trim((string) ($_POST['reason'] ?? ''));

    $v = new Validator();
    $v->required($_POST['product_id'] ?? '', 'Medicine')
      ->required($_POST['system_qty'] ?? '', 'System quantity')
      ->required($_POST['physical_qty'] ?? '', 'Physical quantity')
      ->numeric($_POST['system_qty'] ?? '', 'System quantity')
      ->numeric($_POST['physical_qty'] ?? '', 'Physical quantity')
      ->minValue($_POST['system_qty'] ?? 0, 0, 'System quantity')
      ->minValue($_POST['physical_qty'] ?? 0, 0, 'Physical quantity');

    if ($v->fails()) {
        $errors = $v->getErrors();
    } elseif (!$productModel->find($productId)) {
        $errors[] = 'Selected product does not exist.';
    } else {
        $ok = $stockAuditModel->record($productId, $systemQty, $physicalQty, $reason, (int) $_SESSION['user_id']);
        if ($ok) {
            AuditLog::record('stock_audit.create', 'stock_audits', (int) $stockAuditModel->lastInsertId(), 'Recorded physical count for product #' . $productId);
            header('Location: audit.php?success=Stock audit recorded successfully');
            exit;
        }
        $errors[] = 'Unable to save the stock audit.';
    }
}

$products = $productModel->getActiveProducts();
$audits = $stockAuditModel->search();
$pageTitle = 'Stock Audit';
include __DIR__ . '/../../includes/header.php';
?>

<div class="row g-4">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">Record Physical Count</div>
      <div class="card-body">
        <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <form method="POST" novalidate>
          <div class="mb-3">
            <label class="form-label">Beverages</label>
            <select name="product_id" class="form-select" required>
              <option value="">-- Select beverage --</option>
              <?php foreach ($products as $product): ?>
                <option value="<?= $product['id'] ?>" <?= ($_POST['product_id'] ?? '') == $product['id'] ? 'selected' : '' ?>><?= e($product['product_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">System Quantity</label>
            <input type="number" name="system_qty" min="0" class="form-control" value="<?= (int) ($_POST['system_qty'] ?? 0) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Physical Quantity</label>
            <input type="number" name="physical_qty" min="0" class="form-control" value="<?= (int) ($_POST['physical_qty'] ?? 0) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Reason</label>
            <input type="text" name="reason" class="form-control" maxlength="255" value="<?= e($_POST['reason'] ?? '') ?>" placeholder="Damage, missing stock, counting error...">
          </div>
          <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> Save Audit</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Recent Audits</span>
        <a href="../reports/stock_audit_report.php" class="btn btn-sm btn-outline-primary">Open Report</a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead><tr><th>Audit Date</th><th>Beverage</th><th>System</th><th>Physical</th><th>Difference</th><th>Corrected</th><th>Reason</th></tr></thead>
            <tbody>
              <?php if (empty($audits)): ?><tr><td colspan="7" class="text-center text-muted py-4">No audits recorded yet.</td></tr>
              <?php else: foreach ($audits as $audit): ?>
                <tr>
                  <td><?= formatDate($audit['audit_date']) ?></td>
                  <td><?= e($audit['product_name']) ?></td>
                  <td><?= (int) $audit['system_qty'] ?></td>
                  <td><?= (int) $audit['physical_qty'] ?></td>
                  <td class="fw-bold <?= (int) $audit['difference'] === 0 ? 'text-success' : 'text-danger' ?>"><?= (int) $audit['difference'] ?></td>
                  <td><?= $audit['corrected'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-warning text-dark">No</span>' ?></td>
                  <td><?= e($audit['reason'] ?: '—') ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
