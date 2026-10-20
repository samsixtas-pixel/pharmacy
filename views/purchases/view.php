<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

$model = new Purchase();
$id = (int)($_GET['id'] ?? 0);
$p = $model->findWithDetails($id);
if (!$p) { header('Location: index.php?error=Purchase not found'); exit; }

// Handle payment recording
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_amount'])) {
    $amt = (float)$_POST['payment_amount'];
    if ($amt <= 0) $errors[] = 'Payment amount must be positive.';
    elseif ($amt > (float)$p['balance']) $errors[] = 'Payment exceeds balance.';
    else {
        $model->applyPayment($id, $amt);
        AuditLog::record('purchase.payment', 'purchases', $id, 'Paid supplier ' . number_format($amt, 2));
        header('Location: view.php?id=' . $id . '&success=' . urlencode('Payment recorded.'));
        exit;
    }
}

$pageTitle = 'Purchase Details';
include __DIR__ . '/../../includes/header.php';
?>

<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="card mb-3">
  <div class="card-header d-flex justify-content-between">
    <span>Purchase #<?= str_pad((string)$p['id'], 5, '0', STR_PAD_LEFT) ?></span>
    <a href="receipt.php?id=<?= $p['id'] ?>" target="_blank" class="btn btn-sm btn-primary"><i class="bi bi-printer"></i> Print PDF</a>
  </div>
  <div class="card-body">
    <div class="row mb-3">
      <div class="col-md-6">
        <strong>Supplier:</strong> <?= e($p['supplier_name']) ?><br>
        <strong>Phone:</strong> <?= e($p['supplier_phone']) ?><br>
        <strong>Invoice:</strong> <?= e($p['invoice_no']) ?><br>
      </div>
      <div class="col-md-6 text-md-end">
        <strong>Date:</strong> <?= formatDate($p['purchase_date']) ?><br>
        <strong>Recorded by:</strong> <?= e($p['recorded_by'] ?? '—') ?><br>
        <strong>Status:</strong> <?= statusBadge($p['payment_status']) ?>
      </div>
    </div>

    <table class="table table-sm">
      <thead><tr><th>#</th><th>Product</th><th>Qty</th><th>Unit Cost</th><th class="text-end">Subtotal</th></tr></thead>
      <tbody>
        <?php foreach ($p['items'] as $i => $it): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td><?= e($it['product_name']) ?> <span class="text-muted small"><?= e($it['size']) ?></span></td>
            <td><?= (int)$it['quantity'] ?></td>
            <td><?= formatMoney($it['unit_cost']) ?></td>
            <td class="text-end"><?= formatMoney($it['subtotal']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr><th colspan="4" class="text-end">Total</th><th class="text-end"><?= formatMoney($p['total_amount']) ?></th></tr>
        <tr><td colspan="4" class="text-end">Amount Paid</td><td class="text-end"><?= formatMoney($p['amount_paid']) ?></td></tr>
        <tr><td colspan="4" class="text-end fw-bold">Balance</td><td class="text-end fw-bold text-danger"><?= formatMoney($p['balance']) ?></td></tr>
      </tfoot>
    </table>

    <?php if ($p['balance'] > 0): ?>
      <hr>
      <h6>Record Additional Payment</h6>
      <form method="POST" class="row g-2 align-items-end">
        <div class="col-md-4">
          <label class="form-label">Amount (TZS)</label>
          <input type="number" step="0.01" min="0.01" name="payment_amount" class="form-control" required>
        </div>
        <div class="col-md-3">
          <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Payment</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>

<a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>

<?php include __DIR__ . '/../../includes/footer.php'; ?>