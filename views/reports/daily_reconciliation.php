<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

$date = trim($_GET['date'] ?? date('Y-m-d'));
$db = Database::getConnection();

$stmt = $db->prepare(
    "SELECT COALESCE(SUM(total_amount), 0) AS revenue,
            COALESCE(SUM(amount_paid), 0) AS cash_in_from_sales,
            COALESCE(SUM(remaining_balance), 0) AS new_debt,
            COUNT(*) AS sale_count
     FROM sales WHERE sale_date = :date"
);
$stmt->execute(['date' => $date]);
$saleTotals = $stmt->fetch();

$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0), COUNT(*) FROM payments WHERE payment_date = :date");
$stmt->execute(['date' => $date]);
[$debtCash, $debtCount] = $stmt->fetch(PDO::FETCH_NUM);

$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date = :date");
$stmt->execute(['date' => $date]);
$expenses = (float) $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COALESCE(SUM(profit), 0) FROM sales WHERE sale_date = :date");
$stmt->execute(['date' => $date]);
$grossProfit = (float) $stmt->fetchColumn();

$expectedCash = (float) $saleTotals['cash_in_from_sales'] + (float) $debtCash;
$netProfit = $grossProfit - $expenses;
$cashAtHand = $expectedCash - $expenses;

$stmt = $db->prepare(
    "SELECT u.full_name, u.username,
            COUNT(s.id) AS sales_count,
            COALESCE(SUM(s.amount_paid), 0) AS cash_collected
     FROM users u
     LEFT JOIN sales s ON s.user_id = u.id AND s.sale_date = :date
     WHERE u.role = 'staff'
     GROUP BY u.id
     ORDER BY cash_collected DESC"
);
$stmt->execute(['date' => $date]);
$workers = $stmt->fetchAll();

$pageTitle = 'Daily Cash Reconciliation';
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 no-print">
  <form method="GET" class="d-flex gap-2 align-items-center">
    <input type="date" name="date" class="form-control" value="<?= e($date) ?>">
    <button class="btn btn-outline-secondary"><i class="bi bi-funnel"></i> Filter</button>
  </form>
  <button onclick="window.print()" class="btn btn-outline-primary"><i class="bi bi-printer"></i> Print</button>
</div>

<div class="card mb-3">
  <div class="card-header">Cash Reconciliation — <?= formatDate($date) ?></div>
  <div class="card-body">
    <table class="table table-borderless mb-0">
      <tr><td style="width:60%">Sales recorded</td><td class="text-end fw-bold"><?= (int) $saleTotals['sale_count'] ?></td></tr>
      <tr><td>Total sales value</td><td class="text-end fw-bold"><?= formatMoney($saleTotals['revenue']) ?></td></tr>
      <tr><td>Cash received from sales</td><td class="text-end fw-bold"><?= formatMoney($saleTotals['cash_in_from_sales']) ?></td></tr>
      <tr><td>Debt payments received (<?= (int) $debtCount ?>)</td><td class="text-end fw-bold"><?= formatMoney($debtCash) ?></td></tr>
      <tr style="border-top:1px solid #ddd"><td class="fw-bold">EXPECTED CASH IN HAND</td><td class="text-end fw-bold" style="color:#0D6E5D"><?= formatMoney($expectedCash) ?></td></tr>
      <tr><td>Expenses paid in cash</td><td class="text-end text-danger">− <?= formatMoney($expenses) ?></td></tr>
      <tr style="border-top:2px solid #0D6E5D"><td class="fw-bold">NET CASH THAT SHOULD BE IN HAND</td><td class="text-end fw-bold" style="color:#0D6E5D;font-size:1.1rem"><?= formatMoney($cashAtHand) ?></td></tr>
      <tr><td>Gross profit</td><td class="text-end"><?= formatMoney($grossProfit) ?></td></tr>
      <tr><td class="fw-bold">Net profit after expenses</td><td class="text-end fw-bold" style="color:<?= $netProfit >= 0 ? '#0D6E5D' : '#B91C1C' ?>"><?= formatMoney($netProfit) ?></td></tr>
      <tr><td>New debt created</td><td class="text-end text-warning"><?= formatMoney($saleTotals['new_debt']) ?></td></tr>
    </table>
  </div>
</div>

<div class="card">
  <div class="card-header">Cash by Worker</div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>#</th><th>Worker</th><th>Sales</th><th>Cash Collected</th></tr></thead>
        <tbody>
          <?php if (empty($workers)): ?>
            <tr><td colspan="4" class="text-center text-muted py-4">No workers found.</td></tr>
          <?php else: foreach ($workers as $index => $worker): ?>
            <tr>
              <td><?= $index + 1 ?></td>
              <td><strong><?= e($worker['full_name']) ?></strong> <span class="text-muted small">@<?= e($worker['username']) ?></span></td>
              <td><?= (int) $worker['sales_count'] ?></td>
              <td><?= formatMoney($worker['cash_collected']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
