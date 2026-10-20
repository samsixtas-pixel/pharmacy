<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

$from = trim($_GET['from'] ?? date('Y-m-01'));
$to   = trim($_GET['to'] ?? date('Y-m-d'));

$db = Database::getConnection();
$saleModel = new Sale();

// ==== Income statement pieces ====
$revenue = $saleModel->sumRevenue($from, $to);
$cogs    = $saleModel->sumCostOfGoods($from, $to);

$stmt = $db->prepare(
    "SELECT category, COALESCE(SUM(amount),0) AS total FROM expenses
     WHERE expense_date BETWEEN :f AND :t GROUP BY category ORDER BY total DESC"
);
$stmt->execute(['f' => $from, 't' => $to]);
$expenseRows = $stmt->fetchAll();
$totalExpenses = array_sum(array_column($expenseRows, 'total'));

// Expired losses
$expModel = new ExpiredProduct();
$expiredLoss = $expModel->sumLoss($from, $to);

// ==== Balance sheet pieces ====
$closingCash = (float)$db->query(
    "SELECT COALESCE(SUM(received_amount),0) FROM cash_declarations
     WHERE admin_verified = 1 AND declaration_date BETWEEN " . $db->quote($from) . " AND " . $db->quote($to)
)->fetchColumn();

$debtors = (float)$db->query(
    "SELECT COALESCE(SUM(remaining_balance),0) FROM sales WHERE payment_status IN ('partial','unpaid')"
)->fetchColumn();

$stockAtCost = (float)$db->query(
    "SELECT COALESCE(SUM(cost_price * current_stock),0) FROM products WHERE status='active'"
)->fetchColumn();

$payables = (float)$db->query(
    "SELECT COALESCE(SUM(balance),0) FROM purchases WHERE balance > 0"
)->fetchColumn();

// ==== Trial Balance accounts ====
$debits = [
    'Cash on Hand'                => $closingCash,
    'Accounts Receivable'         => $debtors,
    'Inventory at Cost'           => $stockAtCost,
    'Cost of Goods Sold'          => $cogs,
];
foreach ($expenseRows as $er) {
    $debits['Expense: ' . ucfirst($er['category'])] = (float)$er['total'];
}
if ($expiredLoss > 0) {
    $debits['Loss: Expired/Damaged'] = $expiredLoss;
}

$credits = [
    'Sales Revenue'               => $revenue,
    'Accounts Payable'            => $payables,
];

$totalDebits  = array_sum($debits);
$totalCredits = array_sum($credits);

// Balancing figure → Owner's Equity (residual)
if (abs($totalDebits - $totalCredits) > 0.01) {
    $credits["Owner's Capital / Retained Earnings"] = $totalDebits - $totalCredits;
    $totalCredits = array_sum($credits);
}

$isBalanced = abs($totalDebits - $totalCredits) < 0.01;
$diff = abs($totalDebits - $totalCredits);

$pageTitle = 'Trial Balance';
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 no-print">
  <form method="GET" class="d-flex gap-2 align-items-center">
    <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
    <span>to</span>
    <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
    <button class="btn btn-outline-secondary"><i class="bi bi-funnel"></i> Filter</button>
  </form>
  <div>
    <button onclick="window.print()" class="btn btn-outline-primary"><i class="bi bi-printer"></i> Print</button>
    <a href="<?= BASE_URL ?>views/reports/pdf_hub.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
  </div>
</div>

<?php if ($isBalanced): ?>
  <div class="alert alert-success d-flex align-items-center gap-2">
    <i class="bi bi-check2-circle" style="font-size:1.5rem"></i>
    <div><strong>Trial Balance is BALANCED.</strong> Debits = Credits = <?= formatMoney($totalDebits) ?>. The books are accurate.</div>
  </div>
<?php else: ?>
  <div class="alert alert-danger d-flex align-items-center gap-2">
    <i class="bi bi-exclamation-octagon-fill" style="font-size:1.5rem"></i>
    <div><strong>Difference of <?= formatMoney($diff) ?></strong> between debits and credits. Please investigate.</div>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-header d-flex justify-content-between">
    <span><i class="bi bi-journal-check me-1"></i>Trial Balance — <?= formatDate($from) ?> to <?= formatDate($to) ?></span>
    <span class="text-muted small"><?= date('d M Y H:i') ?></span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr>
          <th>Account</th>
          <th class="text-end" style="width:18%">Debit (TZS)</th>
          <th class="text-end" style="width:18%">Credit (TZS)</th>
        </tr></thead>
        <tbody>
          <?php foreach ($debits as $name => $amt): ?>
            <tr>
              <td><?= e($name) ?></td>
              <td class="text-end"><?= $amt != 0 ? formatMoney($amt) : '—' ?></td>
              <td class="text-end">—</td>
            </tr>
          <?php endforeach; ?>
          <?php foreach ($credits as $name => $amt): ?>
            <tr>
              <td><?= e($name) ?></td>
              <td class="text-end">—</td>
              <td class="text-end"><?= $amt != 0 ? formatMoney($amt) : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="background:#0D6E5D;color:#fff;font-weight:700;font-size:1.1rem">
            <td>TOTALS</td>
            <td class="text-end"><?= formatMoney($totalDebits) ?></td>
            <td class="text-end"><?= formatMoney($totalCredits) ?></td>
          </tr>
          <tr>
            <td colspan="3" class="text-center fw-bold py-2"
                style="color:<?= $isBalanced ? '#0D6E5D' : '#B91C1C' ?>">
              <?= $isBalanced
                  ? '✓ Balanced — Debits equal Credits'
                  : '⚠ Imbalance: ' . formatMoney($diff) . ' — check entries' ?>
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>

<div class="row g-3 mt-3 no-print">
  <div class="col-md-4">
    <div class="stat-card money-card">
      <div class="icon" style="background:#0D6E5D"><i class="bi bi-graph-up-arrow"></i></div>
      <div><div class="label">Revenue</div><div class="value"><?= formatMoney($revenue) ?></div></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="stat-card money-card">
      <div class="icon" style="background:#C98012"><i class="bi bi-box-seam"></i></div>
      <div><div class="label">Inventory at Cost</div><div class="value"><?= formatMoney($stockAtCost) ?></div></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="stat-card money-card">
      <div class="icon" style="background:#B91C1C"><i class="bi bi-cash-stack"></i></div>
      <div><div class="label">Payables</div><div class="value"><?= formatMoney($payables) ?></div></div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>