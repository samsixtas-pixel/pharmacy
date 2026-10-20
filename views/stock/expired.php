<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireLogin();

$productModel = new Product();
$expModel     = new ExpiredProduct();
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $quantity  = (int)($_POST['quantity'] ?? 0);
    $reason    = $_POST['reason'] ?? '';
    $notes     = trim($_POST['notes'] ?? '');

    if ($productId <= 0) $errors[] = 'Please select a product.';
    if ($quantity <= 0)  $errors[] = 'Quantity must be at least 1.';
    if (!array_key_exists($reason, ExpiredProduct::reasons())) $errors[] = 'Invalid reason.';

    if (!$errors) {
        $product = $productModel->find($productId);
        if (!$product) {
            $errors[] = 'Product not found.';
        } elseif ($quantity > (int)$product['current_stock']) {
            $errors[] = 'Quantity exceeds available stock (' . (int)$product['current_stock'] . ').';
        } else {
            $ok = $expModel->submit($productId, $quantity, $reason, $notes, (int)$_SESSION['user_id']);
            if ($ok) {
                AuditLog::record('expired.submit', 'expired_products', (int)$expModel->lastInsertId(),
                    'Submitted: ' . $quantity . 'x product #' . $productId . ' — ' . $reason);
                header('Location: expired.php?success=' . urlencode('Report submitted. Waiting for boss approval.'));
                exit;
            }
            $errors[] = 'Failed to submit. Please try again.';
        }
    }
}

if (!empty($_GET['success'])) $success = $_GET['success'];

$products = $productModel->getActiveProducts();
$myReports = $expModel->search(['user_id' => (int)$_SESSION['user_id']]);
$myReports = array_slice($myReports, 0, 20);
$reasons = ExpiredProduct::reasons();

$pageTitle = 'Expired / Damaged';
include __DIR__ . '/../../includes/header.php';
?>

<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header"><i class="bi bi-exclamation-triangle-fill me-1"></i>Report Expired / Damaged Product</div>
      <div class="card-body">
        <div class="alert alert-info small py-2 mb-3">
          The boss will review your report. If approved, the stock will be reduced and the loss will be recorded.
        </div>
        <form method="POST" novalidate>
          <div class="mb-3">
            <label class="form-label">Product</label>
            <select name="product_id" class="form-select" required>
              <option value="">-- Select product --</option>
              <?php foreach ($products as $p): ?>
                <option value="<?= $p['id'] ?>"><?= e($p['product_name']) ?> <?= e($p['size']) ?> (stock: <?= (int)$p['current_stock'] ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row">
            <div class="col-6 mb-3">
              <label class="form-label">Quantity</label>
              <input type="number" min="1" name="quantity" class="form-control" required>
            </div>
            <div class="col-6 mb-3">
              <label class="form-label">Reason</label>
              <select name="reason" class="form-select" required>
                <?php foreach ($reasons as $k => $v): ?>
                  <option value="<?= $k ?>"><?= $v ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Explain what happened</label>
            <textarea name="notes" class="form-control" rows="3" placeholder="e.g. Bottle fell and broke while moving shelf" required></textarea>
          </div>
          <button class="btn btn-warning w-100"><i class="bi bi-send"></i> Submit Report</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card">
      <div class="card-header">My Recent Reports</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead><tr><th>Date</th><th>Product</th><th>Qty</th><th>Reason</th><th>Status</th></tr></thead>
            <tbody>
              <?php if (empty($myReports)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">You haven't submitted any reports yet.</td></tr>
              <?php else: foreach ($myReports as $r): ?>
                <tr>
                  <td class="small"><?= date('d M H:i', strtotime($r['requested_at'])) ?></td>
                  <td><?= e($r['product_name']) ?> <span class="text-muted small"><?= e($r['size']) ?></span></td>
                  <td><?= (int)$r['quantity'] ?></td>
                  <td><span class="badge bg-secondary"><?= e($reasons[$r['reason']] ?? $r['reason']) ?></span></td>
                  <td>
                    <?php if ($r['status'] === 'pending'): ?><span class="badge bg-warning">Pending</span>
                    <?php elseif ($r['status'] === 'approved'): ?><span class="badge bg-success">Approved</span>
                    <?php else: ?><span class="badge bg-danger">Rejected</span><?php endif; ?>
                  </td>
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