<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireLogin();

$reportModel = new Report();
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');
$rows = $reportModel->stockOutReport($from ?: null, $to ?: null);

$pageTitle = 'Stock Out Report';
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print flex-wrap gap-2">
  <form method="GET" class="d-flex gap-2 align-items-center">
    <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
    <span>to</span>
    <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
    <button class="btn btn-outline-secondary"><i class="bi bi-funnel"></i> Filter</button>
  </form>
  <div>
    <button onclick="window.print()" class="btn btn-outline-primary"><i class="bi bi-printer"></i> Print</button>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
  </div>
</div>

<div class="card">
  <div class="card-header">Stock Out Report</div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>#</th><th>Beverage</th><th>Quantity</th><th>Reason</th><th>Date</th><th>Recorded By</th></tr></thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No records found.</td></tr>
          <?php else: foreach ($rows as $i => $h): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><?= e($h['item_name']) ?></td>
              <td><?= (int) $h['quantity'] ?></td>
              <td><?= e($h['reason']) ?></td>
              <td><?= formatDate($h['transaction_date']) ?></td>
              <td><?= e($h['recorded_by']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
