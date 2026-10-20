<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

$from = trim($_GET['from'] ?? date('Y-m-01'));
$to = trim($_GET['to'] ?? date('Y-m-d'));

$db = Database::getConnection();
$stmt = $db->prepare(
    "SELECT u.id, u.full_name, u.username,
            COUNT(s.id) AS total_sales,
            COALESCE(SUM(s.total_amount), 0) AS total_revenue,
            COALESCE(SUM(s.profit), 0) AS total_profit,
            COALESCE(SUM(s.amount_paid), 0) AS cash_collected,
            COALESCE(SUM(s.remaining_balance), 0) AS debt_created
     FROM users u
     LEFT JOIN sales s ON s.user_id = u.id AND s.sale_date BETWEEN :from AND :to
     WHERE u.role = 'staff'
     GROUP BY u.id
     ORDER BY total_revenue DESC"
);
$stmt->execute(['from' => $from, 'to' => $to]);
$workers = $stmt->fetchAll();

$stmt = $db->prepare(
    "SELECT u.id,
            COALESCE(SUM(p.amount), 0) AS debt_payments_received,
            COUNT(p.id) AS debt_payment_count
     FROM users u
     LEFT JOIN payments p ON p.user_id = u.id AND p.payment_date BETWEEN :from AND :to
     WHERE u.role = 'staff'
     GROUP BY u.id"
);
$stmt->execute(['from' => $from, 'to' => $to]);
$debtPayments = [];
foreach ($stmt->fetchAll() as $row) {
    $debtPayments[(int) $row['id']] = $row;
}

$pageTitle = 'Worker Performance';
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 no-print">
  <form method="GET" class="d-flex gap-2 align-items-center">
    <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
    <span>to</span>
    <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
    <button class="btn btn-outline-secondary"><i class="bi bi-funnel"></i> Filter</button>
  </form>
  <button onclick="window.print()" class="btn btn-outline-primary"><i class="bi bi-printer"></i> Print</button>
</div>

<div class="card">
  <div class="card-header">Worker Performance — <?= formatDate($from) ?> to <?= formatDate($to) ?></div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr>
          <th>#</th><th>Worker</th><th>Sales</th><th>Revenue</th><th>Profit</th><th>Cash Collected</th><th>Debt Payments</th><th>New Debt</th>
        </tr></thead>
        <tbody>
          <?php if (empty($workers)): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">No workers found.</td></tr>
          <?php else: foreach ($workers as $index => $worker):
            $payment = $debtPayments[(int) $worker['id']] ?? ['debt_payments_received' => 0, 'debt_payment_count' => 0];
          ?>
            <tr>
              <td><?= $index + 1 ?></td>
              <td><strong><?= e($worker['full_name']) ?></strong><br><span class="text-muted small">@<?= e($worker['username']) ?></span></td>
              <td><?= (int) $worker['total_sales'] ?></td>
              <td><?= formatMoney($worker['total_revenue']) ?></td>
              <td class="fw-bold" style="color:#0D6E5D"><?= formatMoney($worker['total_profit']) ?></td>
              <td><?= formatMoney($worker['cash_collected']) ?></td>
              <td><?= formatMoney($payment['debt_payments_received']) ?><br><span class="text-muted small"><?= (int) $payment['debt_payment_count'] ?> payment(s)</span></td>
              <td class="text-danger"><?= formatMoney($worker['debt_created']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
