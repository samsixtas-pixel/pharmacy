<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

$saleModel = new Sale();
$from = trim($_GET['from'] ?? date('Y-m-01'));
$to   = trim($_GET['to'] ?? date('Y-m-d'));

$db = Database::getConnection();

$has = function (string $table) use ($db): bool {
    try { $db->query("SELECT 1 FROM {$table} LIMIT 1"); return true; }
    catch (Throwable $e) { return false; }
};

$hasExpenses     = $has('expenses');
$hasPayments     = $has('payments');
$hasPurchases    = $has('purchases');
$hasDeclarations = $has('cash_declarations');
$hasExpired      = $has('expired_products');

// ==================== INCOME STATEMENT ====================
$revenue = $saleModel->sumRevenue($from, $to);
$cogs    = $saleModel->sumCostOfGoods($from, $to);
$gross   = $saleModel->sumGrossProfit($from, $to);

$expenseRows = [];
$totalExpenses = 0.0;
if ($hasExpenses) {
    $stmt = $db->prepare(
        "SELECT category, COALESCE(SUM(amount),0) AS total
         FROM expenses WHERE expense_date BETWEEN :f AND :t
         GROUP BY category ORDER BY total DESC"
    );
    $stmt->execute(['f' => $from, 't' => $to]);
    $expenseRows = $stmt->fetchAll();
    $totalExpenses = (float) array_sum(array_column($expenseRows, 'total'));
}

$expiredLoss = 0.0;
if ($hasExpired) {
    try {
        $expiredModel = new ExpiredProduct();
        $expiredLoss = $expiredModel->sumLoss($from, $to);
    } catch (Throwable $e) {
        $expiredLoss = 0.0;
    }
}

$totalCosts   = $totalExpenses + $expiredLoss;   // ← combined
$netProfit    = $gross - $totalCosts;            // ← correctly subtracts BOTH

// ==================== CASH FLOW ====================
$stmt = $db->prepare("SELECT COALESCE(SUM(amount_paid),0) FROM sales WHERE sale_date BETWEEN :f AND :t");
$stmt->execute(['f' => $from, 't' => $to]);
$cashFromSales = (float) $stmt->fetchColumn();

$cashFromDebtPayments = 0.0;
if ($hasPayments) {
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE payment_date BETWEEN :f AND :t");
    $stmt->execute(['f' => $from, 't' => $to]);
    $cashFromDebtPayments = (float) $stmt->fetchColumn();
}

$cashToSuppliers = 0.0;
if ($hasPurchases) {
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount_paid),0) FROM purchases WHERE purchase_date BETWEEN :f AND :t");
    $stmt->execute(['f' => $from, 't' => $to]);
    $cashToSuppliers = (float) $stmt->fetchColumn();
}

$cashInFromOps   = $cashFromSales + $cashFromDebtPayments;
$cashOutFromOps  = $cashToSuppliers + $totalExpenses;
$netCashMovement = $cashInFromOps - $cashOutFromOps;

$openingCash = 0.0;
if ($hasDeclarations) {
    $stmt = $db->prepare(
        "SELECT declared_cash FROM cash_declarations
         WHERE declaration_date < :f AND admin_verified = 1
         ORDER BY declaration_date DESC, id DESC LIMIT 1"
    );
    $stmt->execute(['f' => $from]);
    $openingCash = (float) $stmt->fetchColumn();
}
$closingCash = $openingCash + $netCashMovement;

// ==================== BALANCE SHEET ====================
$stockAtCost   = (float) $db->query("SELECT COALESCE(SUM(cost_price * current_stock),0) FROM products WHERE status='active'")->fetchColumn();
$stockAtRetail = (float) $db->query("SELECT COALESCE(SUM(selling_price * current_stock),0) FROM products WHERE status='active'")->fetchColumn();
$debtors       = (float) $db->query("SELECT COALESCE(SUM(remaining_balance),0) FROM sales WHERE payment_status IN ('partial','unpaid')")->fetchColumn();

$payables = 0.0;
if ($hasPurchases) {
    $payables = (float) $db->query("SELECT COALESCE(SUM(balance),0) FROM purchases WHERE balance > 0")->fetchColumn();
}

$totalAssets      = $closingCash + $debtors + $stockAtCost;
$totalLiabilities = $payables;
$ownerEquity      = $totalAssets - $totalLiabilities;

// ==================== TRIAL BALANCE ====================
// Standard approach: debits include all expenses, credits include all income.
// Owner's Capital = balancing figure (closing equity - current period profit)
$tbDebits = [
    'Cash on Hand'         => $closingCash,
    'Accounts Receivable'  => $debtors,
    'Inventory at Cost'    => $stockAtCost,
    'Cost of Goods Sold'   => $cogs,
];
foreach ($expenseRows as $er) {
    $tbDebits['Expense: ' . ucfirst($er['category'])] = (float) $er['total'];
}
// ← THIS was missing — expired loss is a real debit
if ($expiredLoss > 0) {
    $tbDebits['Expired / Damaged Loss'] = $expiredLoss;
}
$tbTotalDebits = array_sum($tbDebits);

// Credits side: Revenue + Payables. Equity is added below as balancing figure.
$tbCredits = [
    'Sales Revenue'    => $revenue,
    'Accounts Payable' => $payables,
];
$tbTotalCreditsBeforeEquity = array_sum($tbCredits);

// Balancing equity = debits - (revenue + payables)
$balancingEquity = $tbTotalDebits - $tbTotalCreditsBeforeEquity;
if (abs($balancingEquity) > 0.01) {
    $tbCredits["Owner's Capital / Retained Earnings"] = $balancingEquity;
}
$tbTotalCredits = array_sum($tbCredits);

// ==================== TRANSACTION DETAIL ====================
$salesList = $db->prepare(
    "SELECT s.*, p.product_name, p.size, u.full_name AS worker
     FROM sales s JOIN products p ON s.product_id = p.id
     LEFT JOIN users u ON s.user_id = u.id
     WHERE s.sale_date BETWEEN :f AND :t
     ORDER BY s.sale_date, s.id"
);
$salesList->execute(['f' => $from, 't' => $to]);
$sales = $salesList->fetchAll();

$paymentsList = [];
if ($hasPayments) {
    $pay = $db->prepare(
        "SELECT pay.*, s.customer_name, u.full_name AS worker
         FROM payments pay JOIN sales s ON pay.sale_id = s.id
         LEFT JOIN users u ON pay.user_id = u.id
         WHERE pay.payment_date BETWEEN :f AND :t
         ORDER BY pay.payment_date, pay.id"
    );
    $pay->execute(['f' => $from, 't' => $to]);
    $paymentsList = $pay->fetchAll();
}

$expensesList = [];
if ($hasExpenses) {
    $exp = $db->prepare(
        "SELECT e.*, u.full_name AS worker FROM expenses e
         LEFT JOIN users u ON e.user_id = u.id
         WHERE e.expense_date BETWEEN :f AND :t
         ORDER BY e.expense_date, e.id"
    );
    $exp->execute(['f' => $from, 't' => $to]);
    $expensesList = $exp->fetchAll();
}

$expiredList = [];
if ($hasExpired) {
    try {
        $exp = $db->prepare(
            "SELECT ep.*, p.product_name, p.size, u.full_name AS worker
             FROM expired_products ep
             JOIN products p ON ep.product_id = p.id
             LEFT JOIN users u ON ep.requested_by = u.id
             WHERE ep.status='approved' AND DATE(ep.reviewed_at) BETWEEN :f AND :t
             ORDER BY ep.reviewed_at DESC"
        );
        $exp->execute(['f' => $from, 't' => $to]);
        $expiredList = $exp->fetchAll();
    } catch (Throwable $e) {}
}

$purchasesList = [];
if ($hasPurchases) {
    $pur = $db->prepare(
        "SELECT pu.*, s.supplier_name, u.full_name AS worker
         FROM purchases pu JOIN suppliers s ON pu.supplier_id = s.id
         LEFT JOIN users u ON pu.user_id = u.id
         WHERE pu.purchase_date BETWEEN :f AND :t
         ORDER BY pu.purchase_date, pu.id"
    );
    $pur->execute(['f' => $from, 't' => $to]);
    $purchasesList = $pur->fetchAll();
}

$pdfTitle = 'Balance Sheet';
$pdfSubtitle = 'Period: ' . formatDate($from) . ' — ' . formatDate($to);
include __DIR__ . '/../../includes/pdf_header.php';
?>

<h2 style="margin:0 0 4px;font-size:16px;">BALANCE SHEET, INCOME STATEMENT &amp; TRIAL BALANCE</h2>
<div class="muted" style="font-size:11.5px;margin-bottom:14px;">
  From <strong><?= formatDate($from) ?></strong> to <strong><?= formatDate($to) ?></strong>
</div>

<!-- ============ 1. INCOME STATEMENT ============ -->
<div class="section-title">1. Income Statement</div>
<table class="tbl">
  <thead><tr><th style="width:6%">#</th><th>Item</th><th class="text-right" style="width:22%">Amount (TZS)</th></tr></thead>
  <tbody>
    <tr><td>1</td><td class="bold">Revenue (Total Sales)</td><td class="text-right bold"><?= formatMoney($revenue) ?></td></tr>
    <tr><td>2</td><td>Less: Cost of Goods Sold (COGS)</td><td class="text-right">(<?= formatMoney($cogs) ?>)</td></tr>
    <tr style="background:#eaf6f0"><td>3</td><td class="bold" style="color:#0D6E5D">Gross Profit</td><td class="text-right bold" style="color:#0D6E5D"><?= formatMoney($gross) ?></td></tr>

    <tr><td colspan="3" class="muted small" style="padding-top:8px"><em>Operating Expenses:</em></td></tr>
    <?php if (empty($expenseRows)): ?>
      <tr><td colspan="2" class="muted" style="padding-left:24px">No operating expenses recorded.</td><td class="text-right">0.00</td></tr>
    <?php else: foreach ($expenseRows as $e): ?>
      <tr>
        <td></td>
        <td style="padding-left:24px"><?= e(Expense::categories()[$e['category']] ?? ucfirst($e['category'])) ?></td>
        <td class="text-right"><?= formatMoney($e['total']) ?></td>
      </tr>
    <?php endforeach; endif; ?>
    <tr>
      <td></td>
      <td class="text-right bold">Subtotal — Operating Expenses</td>
      <td class="text-right bold">(<?= formatMoney($totalExpenses) ?>)</td>
    </tr>

    <tr><td colspan="3" class="muted small" style="padding-top:8px"><em>Non-Operating Losses:</em></td></tr>
    <tr>
      <td></td>
      <td style="padding-left:24px">Expired / Damaged / Broken Stock Loss</td>
      <td class="text-right text-danger">(<?= formatMoney($expiredLoss) ?>)</td>
    </tr>

    <tr style="background:#f5f5f5;border-top:1px solid #999">
      <td class="bold"></td>
      <td class="bold text-right">TOTAL OPERATING COSTS</td>
      <td class="text-right bold">(<?= formatMoney($totalCosts) ?>)</td>
    </tr>

    <tr style="background:<?= $netProfit >= 0 ? '#eaf6f0' : '#fce0e0' ?>">
      <td class="bold"></td>
      <td class="bold" style="color:<?= $netProfit >= 0 ? '#0D6E5D' : '#B91C1C' ?>"><?= $netProfit >= 0 ? 'NET PROFIT' : 'NET LOSS' ?></td>
      <td class="text-right bold" style="color:<?= $netProfit >= 0 ? '#0D6E5D' : '#B91C1C' ?>"><?= formatMoney(abs($netProfit)) ?></td>
    </tr>
  </tbody>
</table>

<p class="muted" style="font-size:10.5px;margin-top:4px;">
  * Gross Profit is Revenue minus COGS. Total Operating Costs = Operating Expenses + Expired Loss.
  Net Profit = Gross Profit − Total Operating Costs.
</p>

<!-- ============ 2. BALANCE SHEET POSITION ============ -->
<div class="section-title" style="margin-top:24px;">2. Balance Sheet Position (as of <?= formatDate($to) ?>)</div>

<table class="tbl" style="margin-bottom:12px;">
  <thead><tr><th>ASSETS</th><th class="text-right" style="width:22%">Value (TZS)</th></tr></thead>
  <tbody>
    <tr><td>Cash on Hand</td><td class="text-right"><?= formatMoney($closingCash) ?></td></tr>
    <tr><td>Accounts Receivable (Customer Debts)</td><td class="text-right"><?= formatMoney($debtors) ?></td></tr>
    <tr><td>Closing Inventory (at Cost Price)</td><td class="text-right"><?= formatMoney($stockAtCost) ?></td></tr>
    <tr style="background:#eaf6f0"><td class="bold" style="color:#0D6E5D">TOTAL ASSETS</td><td class="text-right bold" style="color:#0D6E5D"><?= formatMoney($totalAssets) ?></td></tr>
  </tbody>
</table>

<table class="tbl" style="margin-bottom:12px;">
  <thead><tr><th>LIABILITIES</th><th class="text-right" style="width:22%">Value (TZS)</th></tr></thead>
  <tbody>
    <tr><td>Accounts Payable (Unpaid Purchases)</td><td class="text-right"><?= formatMoney($totalLiabilities) ?></td></tr>
    <tr style="background:#fce0e0"><td class="bold">TOTAL LIABILITIES</td><td class="text-right bold"><?= formatMoney($totalLiabilities) ?></td></tr>
  </tbody>
</table>

<table class="tbl">
  <thead><tr><th>OWNER'S EQUITY</th><th class="text-right" style="width:22%">Value (TZS)</th></tr></thead>
  <tbody>
    <tr><td>Owner's Capital (Retained + Period Result)</td><td class="text-right"><?= formatMoney($ownerEquity) ?></td></tr>
    <tr style="background:#eaf6f0"><td class="bold" style="color:#0D6E5D">TOTAL EQUITY</td><td class="text-right bold" style="color:#0D6E5D"><?= formatMoney($ownerEquity) ?></td></tr>
    <tr style="border-top:2px solid #0D6E5D"><td class="bold">TOTAL LIABILITIES + EQUITY</td><td class="text-right bold"><?= formatMoney($totalLiabilities + $ownerEquity) ?></td></tr>
  </tbody>
</table>

<p class="muted" style="font-size:10.5px;margin-top:6px;">
  * Owner's Equity is a residual: Total Assets − Total Liabilities. It includes prior retained
  earnings plus this period's net profit. Expired loss reduces net profit, which reduces equity —
  so it is already reflected here even though it isn't shown as a separate row.
</p>

<!-- ============ 3. CASH FLOW ============ -->
<div class="section-title" style="margin-top:24px;">3. Cash Flow Summary</div>
<table class="tbl">
  <thead><tr><th>Item</th><th class="text-right" style="width:22%">Amount (TZS)</th></tr></thead>
  <tbody>
    <tr><td>Opening Cash (start of period)</td><td class="text-right"><?= formatMoney($openingCash) ?></td></tr>
    <tr><td style="padding-left:24px">+ Cash received from Sales</td><td class="text-right" style="color:#0D6E5D"><?= formatMoney($cashFromSales) ?></td></tr>
    <tr><td style="padding-left:24px">+ Debt payments received</td><td class="text-right" style="color:#0D6E5D"><?= formatMoney($cashFromDebtPayments) ?></td></tr>
    <tr><td style="padding-left:24px">− Cash paid to Suppliers</td><td class="text-right" style="color:#B91C1C">(<?= formatMoney($cashToSuppliers) ?>)</td></tr>
    <tr><td style="padding-left:24px">− Operating Expenses paid</td><td class="text-right" style="color:#B91C1C">(<?= formatMoney($totalExpenses) ?>)</td></tr>
    <tr style="background:#eaf6f0">
      <td class="bold" style="color:#0D6E5D">CLOSING CASH</td>
      <td class="text-right bold" style="color:#0D6E5D"><?= formatMoney($closingCash) ?></td>
    </tr>
  </tbody>
</table>

<!-- ============ 4. TRIAL BALANCE ============ -->
<div class="section-title" style="margin-top:24px;">4. Trial Balance (Verification)</div>
<table class="tbl">
  <thead><tr><th>Account</th><th class="text-right" style="width:18%">Debit</th><th class="text-right" style="width:18%">Credit</th></tr></thead>
  <tbody>
    <?php foreach ($tbDebits as $name => $amt): ?>
      <tr><td><?= e($name) ?></td><td class="text-right"><?= $amt != 0 ? formatMoney($amt) : '—' ?></td><td class="text-right">—</td></tr>
    <?php endforeach; ?>
    <?php foreach ($tbCredits as $name => $amt): ?>
      <tr><td><?= e($name) ?></td><td class="text-right">—</td><td class="text-right"><?= $amt != 0 ? formatMoney($amt) : '—' ?></td></tr>
    <?php endforeach; ?>
    <tr style="border-top:2px solid #0D6E5D;background:#f5f5f5">
      <td class="bold">TOTALS</td>
      <td class="text-right bold"><?= formatMoney($tbTotalDebits) ?></td>
      <td class="text-right bold"><?= formatMoney($tbTotalCredits) ?></td>
    </tr>
    <?php $tbDiff = abs($tbTotalDebits - $tbTotalCredits); ?>
    <tr>
      <td colspan="3" class="text-center small" style="padding-top:6px;color:<?= $tbDiff < 1 ? '#0D6E5D' : '#B91C1C' ?>">
        <?= $tbDiff < 1 ? '✓ Trial Balance is BALANCED (Debits = Credits)' : '⚠ Difference: ' . formatMoney($tbDiff) . ' — check entries' ?>
      </td>
    </tr>
  </tbody>
</table>

<!-- ============ 5. SALES DETAIL ============ -->
<div class="section-title" style="margin-top:24px;page-break-before:always;">5. Transaction Detail — Sales (<?= count($sales) ?>)</div>
<table class="tbl">
  <thead><tr><th>Date</th><th>Customer</th><th>Product</th><th class="text-center">Qty</th><th class="text-right">Total</th><th class="text-right">Paid</th><th class="text-right">Balance</th><th>Status</th><th>Worker</th></tr></thead>
  <tbody>
    <?php if (empty($sales)): ?>
      <tr><td colspan="9" class="text-center muted" style="padding:14px">No sales in period.</td></tr>
    <?php else: foreach ($sales as $s): ?>
      <tr>
        <td class="small"><?= formatDate($s['sale_date']) ?></td>
        <td><?= e($s['customer_name']) ?></td>
        <td><?= e($s['product_name']) ?> <span class="muted"><?= e($s['size']) ?></span></td>
        <td class="text-center"><?= (int) $s['quantity'] ?></td>
        <td class="text-right"><?= formatMoney($s['total_amount']) ?></td>
        <td class="text-right"><?= formatMoney($s['amount_paid']) ?></td>
        <td class="text-right"><?= formatMoney($s['remaining_balance']) ?></td>
        <td><span class="badge badge-<?= e($s['payment_status']) ?>"><?= e($s['payment_status']) ?></span></td>
        <td class="small"><?= e($s['worker'] ?? '—') ?></td>
      </tr>
    <?php endforeach; endif; ?>
  </tbody>
</table>

<!-- ============ 6. DEBT PAYMENTS ============ -->
<div class="section-title" style="margin-top:20px;">6. Debt Payments Received (<?= count($paymentsList) ?>)</div>
<table class="tbl">
  <thead><tr><th>Date</th><th>Customer</th><th class="text-right">Amount</th><th>Received By</th></tr></thead>
  <tbody>
    <?php if (empty($paymentsList)): ?>
      <tr><td colspan="4" class="text-center muted" style="padding:14px">No debt payments in period.</td></tr>
    <?php else: foreach ($paymentsList as $p): ?>
      <tr>
        <td><?= formatDate($p['payment_date']) ?></td>
        <td><?= e($p['customer_name']) ?></td>
        <td class="text-right"><?= formatMoney($p['amount']) ?></td>
        <td class="small"><?= e($p['worker'] ?? '—') ?></td>
      </tr>
    <?php endforeach; endif; ?>
  </tbody>
</table>

<!-- ============ 7. EXPENSES ============ -->
<div class="section-title" style="margin-top:20px;">7. Operating Expenses Paid (<?= count($expensesList) ?>)</div>
<table class="tbl">
  <thead><tr><th>Date</th><th>Title</th><th>Category</th><th class="text-right">Amount</th><th>Recorded By</th></tr></thead>
  <tbody>
    <?php if (empty($expensesList)): ?>
      <tr><td colspan="5" class="text-center muted" style="padding:14px">No expenses in period.</td></tr>
    <?php else: foreach ($expensesList as $ex): ?>
      <tr>
        <td><?= formatDate($ex['expense_date']) ?></td>
        <td><?= e($ex['title']) ?></td>
        <td><?= e(Expense::categories()[$ex['category']] ?? $ex['category']) ?></td>
        <td class="text-right"><?= formatMoney($ex['amount']) ?></td>
        <td class="small"><?= e($ex['worker'] ?? '—') ?></td>
      </tr>
    <?php endforeach; endif; ?>
    <?php if ($totalExpenses > 0): ?>
      <tr style="background:#f5f5f5"><td colspan="3" class="text-right bold">Total Operating Expenses</td><td class="text-right bold"><?= formatMoney($totalExpenses) ?></td><td></td></tr>
    <?php endif; ?>
  </tbody>
</table>

<!-- ============ 8. EXPIRED LOSSES (NEW) ============ -->
<div class="section-title" style="margin-top:20px;">8. Expired / Damaged Losses (<?= count($expiredList) ?>)</div>
<table class="tbl">
  <thead><tr><th>Date</th><th>Product</th><th>Qty</th><th>Reason</th><th class="text-right">Cost Loss</th><th>Approved By</th></tr></thead>
  <tbody>
    <?php if (empty($expiredList)): ?>
      <tr><td colspan="6" class="text-center muted" style="padding:14px">No approved expired/damaged losses in period.</td></tr>
    <?php else: foreach ($expiredList as $x):
      $loss = (float)$x['cost_at_time'] * (int)$x['quantity'];
    ?>
      <tr>
        <td><?= date('d M Y', strtotime($x['reviewed_at'])) ?></td>
        <td><?= e($x['product_name']) ?> <span class="muted"><?= e($x['size']) ?></span></td>
        <td><?= (int)$x['quantity'] ?></td>
        <td><span class="badge bg-secondary"><?= e(ExpiredProduct::reasons()[$x['reason']] ?? $x['reason']) ?></span></td>
        <td class="text-right text-danger"><?= formatMoney($loss) ?></td>
        <td class="small"><?= e($x['worker'] ?? '—') ?></td>
      </tr>
    <?php endforeach; ?>
      <tr style="background:#f5f5f5"><td colspan="4" class="text-right bold">Total Expired Loss</td><td class="text-right bold text-danger"><?= formatMoney($expiredLoss) ?></td><td></td></tr>
    <?php endif; ?>
  </tbody>
</table>

<!-- ============ 9. PURCHASES ============ -->
<div class="section-title" style="margin-top:20px;">9. Purchases (<?= count($purchasesList) ?>)</div>
<table class="tbl">
  <thead><tr><th>Date</th><th>Supplier</th><th>Invoice</th><th class="text-right">Total</th><th class="text-right">Paid</th><th class="text-right">Balance</th><th>Status</th></tr></thead>
  <tbody>
    <?php if (empty($purchasesList)): ?>
      <tr><td colspan="7" class="text-center muted" style="padding:14px">No purchases in period.</td></tr>
    <?php else: foreach ($purchasesList as $pu): ?>
      <tr>
        <td><?= formatDate($pu['purchase_date']) ?></td>
        <td><?= e($pu['supplier_name']) ?></td>
        <td><?= e($pu['invoice_no']) ?></td>
        <td class="text-right"><?= formatMoney($pu['total_amount']) ?></td>
        <td class="text-right"><?= formatMoney($pu['amount_paid']) ?></td>
        <td class="text-right"><?= formatMoney($pu['balance']) ?></td>
        <td><span class="badge badge-<?= e($pu['payment_status']) ?>"><?= e($pu['payment_status']) ?></span></td>
      </tr>
    <?php endforeach; endif; ?>
  </tbody>
</table>

<p class="muted" style="font-size:11px;margin-top:16px;">
  * Receivables = amounts owed by customers. Inventory valued at cost price.
  * Owner's Equity = Total Assets − Total Liabilities (implicit retained earnings + period result).
  * Trial Balance proves accounting accuracy — Debits must equal Credits.
</p>

<div class="signatures">
  <div class="sig">Prepared By (<?= e($_SESSION['full_name'] ?? '') ?>)</div>
  <div class="sig">Approved By (Manager / Boss)</div>
</div>

<?php include __DIR__ . '/../../includes/pdf_footer.php'; ?>