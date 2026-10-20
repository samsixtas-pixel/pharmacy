<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';

// Phase 5 — Purchase entry point moved to stock_in.php
header('Location: ' . BASE_URL . 'views/stock/stock_in.php');
exit;
User::requireAdmin();

$purchaseModel = new Purchase();
$supplierModel = new Supplier();

$from = trim($_GET['from'] ?? date('Y-m-01'));
$to   = trim($_GET['to'] ?? date('Y-m-d'));
$supplierId = (int)($_GET['supplier_id'] ?? 0) ?: null;

$rows = $purchaseModel->allWithSupplier($from, $to, $supplierId);
$suppliers = $supplierModel->getActive();
$totalPurchased = $purchaseModel->sumRange($from, $to);

$pageTitle = 'Purchases';
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <form method="GET" class="d-flex gap-2 flex-wrap align-items-center">
    <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
    <span>to</span>
    <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
    <select name="supplier_id" class="form-select" style="max-width:200px">
      <option value="">All Suppliers</option>
      <?php foreach ($suppliers as $s): ?>
        <option value="<?= $s['id'] ?>" <?= $supplierId === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['supplier_name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-outline-secondary"><i class="bi bi-funnel"></i> Filter</button>
  </form>
  <a href="add.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> New Purchase</a>
</div>

<div class="alert alert-info">
  Total purchases in this period: <strong><?= formatMoney($totalPurchased) ?></strong> across <strong><?= count($rows) ?></strong> order(s).
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr>
          <th>#</th><th>Date</th><th>Supplier</th><th>Invoice</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th>Actions</th>
        </tr></thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="9" class="text-center text-muted py-4">No purchases found.</td></tr>
          <?php else: foreach ($rows as $i => $p): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><?= formatDate($p['purchase_date']) ?></td>
              <td><strong><?= e($p['supplier_name']) ?></strong></td>
              <td><?= e($p['invoice_no']) ?></td>
              <td><?= formatMoney($p['total_amount']) ?></td>
              <td><?= formatMoney($p['amount_paid']) ?></td>
              <td class="<?= $p['balance'] > 0 ? 'text-danger fw-bold' : 'text-muted' ?>"><?= formatMoney($p['balance']) ?></td>
              <td><?= statusBadge($p['payment_status']) ?></td>
              <td>
                <a href="view.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                <a href="receipt.php?id=<?= $p['id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Print"><i class="bi bi-receipt"></i></a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>