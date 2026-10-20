<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireLogin();

$reportModel = new Report();
$keyword = trim($_GET['q'] ?? '');
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');
$rows = $reportModel->debtReport($from ?: null, $to ?: null, $keyword);

$totalOutstanding = array_sum(array_column($rows, 'remaining_balance'));

$pageTitle = 'Debt Report';
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print flex-wrap gap-2">
  <form method="GET" class="d-flex gap-2 flex-wrap align-items-center">
    <input type="text" name="q" class="form-control" placeholder="Search customer..." value="<?= e($keyword) ?>">
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
  <div class="card-header d-flex justify-content-between">
    <span>Debt Report</span>
    <span class="text-muted small">Total Outstanding: <?= formatMoney($totalOutstanding) ?></span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>#</th><th>Customer</th><th>Beverage</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">No outstanding debts found.</td></tr>
          <?php else: foreach ($rows as $i => $s): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><?= e($s['customer_name']) ?></td>
              <td><?= e($s['product_name']) ?></td>
              <td><?= formatMoney($s['total_amount']) ?></td>
              <td><?= formatMoney($s['amount_paid']) ?></td>
              <td><?= formatMoney($s['remaining_balance']) ?></td>
              <td><?= statusBadge($s['payment_status']) ?></td>
              <td><?= formatDate($s['sale_date']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
