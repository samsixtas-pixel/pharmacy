<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

$saleModel = new Sale();
$from = trim($_GET['from'] ?? date('Y-m-01'));
$to = trim($_GET['to'] ?? date('Y-m-d'));

$revenue = $saleModel->sumRevenue($from, $to);
$cogs = $saleModel->sumCostOfGoods($from, $to);
$grossProfit = $saleModel->sumGrossProfit($from, $to);

$db = Database::getConnection();
$stmt = $db->prepare(
    "SELECT category, COALESCE(SUM(amount), 0) AS total
     FROM expenses
     WHERE expense_date BETWEEN :from AND :to
     GROUP BY category
     ORDER BY total DESC"
);
$stmt->execute(['from' => $from, 'to' => $to]);
$expenseRows = $stmt->fetchAll();
$totalExpenses = array_sum(array_column($expenseRows, 'total'));
// Expired / damaged losses (Phase 3)
$expiredModel = new ExpiredProduct();
$expiredLoss  = $expiredModel->sumLoss($from, $to);

$netProfit = $grossProfit - $totalExpenses - $expiredLoss;

$stockValue = (float) $db->query("SELECT COALESCE(SUM(cost_price * current_stock), 0) FROM products WHERE status='active'")->fetchColumn();
$retailValue = (float) $db->query("SELECT COALESCE(SUM(selling_price * current_stock), 0) FROM products WHERE status='active'")->fetchColumn();
$debtors = (float) $db->query("SELECT COALESCE(SUM(remaining_balance), 0) FROM sales WHERE payment_status IN ('partial', 'unpaid')")->fetchColumn();
$cashIn = (float) $db->query("SELECT COALESCE(SUM(amount_paid), 0) FROM sales WHERE sale_date BETWEEN :f AND :t")->fetchColumn();
$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_date BETWEEN :f AND :t");
$stmt->execute(['f' => $from, 't' => $to]);
$debtPaymentsIn = (float) $stmt->fetchColumn();
$totalCashIn = $cashIn + $debtPaymentsIn;

$pageTitle = 'Profit & Loss Report';
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print flex-wrap gap-2">
  <form method="GET" class="d-flex gap-2 flex-wrap align-items-center">
    <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
    <span>to</span>
    <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
    <button class="btn btn-outline-secondary"><i class="bi bi-funnel"></i> Filter</button>
  </form>
  <button onclick="window.print()" class="btn btn-outline-primary"><i class="bi bi-printer"></i> Print</button>
</div>

<div class="card">
  <div class="card-header">
    <strong>Profit &amp; Loss Report</strong>
    <span class="text-muted ms-2"><?= formatDate($from) ?> to <?= formatDate($to) ?></span>
  </div>
  <div class="card-body">
    <div class="row g-4">
      <div class="col-md-6">
        <h6>Income</h6>
        <table class="table table-borderless mb-0">
          <tr><td>Revenue</td><td class="text-end"><?= formatMoney($revenue) ?></td></tr>
          <tr><td>Gross Profit</td><td class="text-end fw-bold" style="color:#0D6E5D"><?= formatMoney($grossProfit) ?></td></tr>
        </table>
      </div>
      <div class="col-md-6">
        <h6>Operating Expenses</h6>
        <table class="table table-borderless mb-0">
          <tr>
            <td></td>
            <td style="padding-left:24px">Expired / Damaged Product Loss</td>
            <td class="text-right">(<?= formatMoney($expiredLoss) ?>)</td>
          </tr>
          <?php if (empty($expenseRows)): ?>
            <tr><td class="text-muted">No operating expenses recorded.</td></tr>
          <?php else: foreach ($expenseRows as $row): ?>
            <tr><td><?= e($row['category']) ?></td><td class="text-end"><?= formatMoney($row['total']) ?></td></tr>
          <?php endforeach; endif; ?>
          <tr class="border-top"><td class="fw-bold">Total Operating Expenses</td><td class="text-end fw-bold">(<?= formatMoney($totalExpenses) ?>)</td></tr>
          
        </table>
      </div>
    </div>

    <div class="alert mt-4 mb-0 <?= $netProfit >= 0 ? 'alert-success' : 'alert-danger' ?>">
      <strong>Net <?= $netProfit >= 0 ? 'Profit' : 'Loss' ?>:</strong>
      <?= formatMoney(abs($netProfit)) ?>
    </div>

    <div class="row g-4 mt-1">
      <div class="col-md-6">
        <h6>Cash &amp; Debtors</h6>
        <table class="table table-borderless mb-0">
          <tr><td>Cash Received</td><td class="text-end"><?= formatMoney($totalCashIn) ?></td></tr>
          <tr><td>Outstanding Debtors</td><td class="text-end"><?= formatMoney($debtors) ?></td></tr>
        </table>
      </div>
      <div class="col-md-6">
        <h6>Stock Position</h6>
        <table class="table table-borderless mb-0">
          <tr><td>Stock Value at Cost</td><td class="text-end"><?= formatMoney($stockValue) ?></td></tr>
          <tr><td>Retail Value</td><td class="text-end"><?= formatMoney($retailValue) ?></td></tr>
        </table>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
