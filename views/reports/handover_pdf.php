<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

$date = trim($_GET['date'] ?? date('Y-m-d'));
$db = Database::getConnection();

$stmt = $db->prepare(
    "SELECT COUNT(*) AS sale_count,
            COALESCE(SUM(total_amount),0) AS revenue,
            COALESCE(SUM(total_cost),0) AS cogs,
            COALESCE(SUM(profit),0) AS gross,
            COALESCE(SUM(discount),0) AS discounts,
            COALESCE(SUM(amount_paid),0) AS cash_from_sales,
            COALESCE(SUM(remaining_balance),0) AS new_debt
     FROM sales WHERE sale_date = :d"
);
$stmt->execute(['d' => $date]);
$day = $stmt->fetch();

$stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE payment_date = :d");
$stmt->execute(['d' => $date]);
$debtCash = (float) $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date = :d");
$stmt->execute(['d' => $date]);
$expenses = (float) $stmt->fetchColumn();

$stmt = $db->prepare(
    "SELECT u.id, u.full_name, u.username,
            COUNT(s.id) AS sales_count,
            COALESCE(SUM(s.total_amount),0) AS revenue,
            COALESCE(SUM(s.amount_paid),0) AS cash_sales,
            COALESCE(SUM(s.discount),0) AS discounts,
            COALESCE(SUM(s.remaining_balance),0) AS new_debt
     FROM users u
     LEFT JOIN sales s ON s.user_id = u.id AND s.sale_date = :d
     WHERE u.role = 'staff'
     GROUP BY u.id ORDER BY revenue DESC"
);
$stmt->execute(['d' => $date]);
$workers = $stmt->fetchAll();

$declModel = new CashDeclaration();
$decls = $declModel->search($date, $date, null, 0);
$declByUser = [];
foreach ($decls as $d) {
    $declByUser[(int) $d['user_id']] = $d;
}

$expected = (float) $day['cash_from_sales'] + $debtCash;
$cashNet = $expected - $expenses;

$pdfTitle = 'Shift Handover';
$pdfSubtitle = 'Date: ' . formatDate($date);
include __DIR__ . '/../../includes/pdf_header.php';
?>

<h2 style="margin:0 0 4px;font-size:16px;">DAILY SHIFT HANDOVER SHEET</h2>
<div class="muted" style="font-size:11.5px;margin-bottom:14px;">
  Date: <strong><?= formatDate($date) ?></strong>
  &nbsp;•&nbsp; Generated: <?= date('d M Y, H:i') ?>
</div>

<div class="section-title">1. Daily Totals</div>
<table class="tbl">
  <thead><tr><th>Metric</th><th class="text-right" style="width:22%">Value</th></tr></thead>
  <tbody>
    <tr><td>Number of sales</td><td class="text-right"><?= (int) $day['sale_count'] ?></td></tr>
    <tr><td>Total revenue (after discounts)</td><td class="text-right"><?= formatMoney($day['revenue']) ?></td></tr>
    <tr><td>Cost of goods sold</td><td class="text-right">(<?= formatMoney($day['cogs']) ?>)</td></tr>
    <tr style="background:#EAF6F0"><td class="bold" style="color:#0D6E5D">Gross profit</td><td class="text-right bold" style="color:#0D6E5D"><?= formatMoney($day['gross']) ?></td></tr>
    <tr><td>Discounts given</td><td class="text-right"><?= formatMoney($day['discounts']) ?></td></tr>
    <tr><td>Cash received from sales</td><td class="text-right"><?= formatMoney($day['cash_from_sales']) ?></td></tr>
    <tr><td>Debt payments received</td><td class="text-right"><?= formatMoney($debtCash) ?></td></tr>
    <tr style="border-top:2px solid #0D6E5D"><td class="bold">Expected cash in hand</td><td class="text-right bold"><?= formatMoney($expected) ?></td></tr>
    <tr><td>Expenses paid in cash</td><td class="text-right">(<?= formatMoney($expenses) ?>)</td></tr>
    <tr style="border-top:2px solid #0D6E5D"><td class="bold" style="color:#0D6E5D">NET CASH TO HAND OVER</td><td class="text-right bold" style="color:#0D6E5D"><?= formatMoney($cashNet) ?></td></tr>
    <tr><td>New debt created</td><td class="text-right"><?= formatMoney($day['new_debt']) ?></td></tr>
  </tbody>
</table>

<div class="section-title">2. Per-Worker Breakdown &amp; Declarations</div>
<table class="tbl">
  <thead>
    <tr><th>Worker</th><th class="text-center">Sales</th><th class="text-right">Revenue</th><th class="text-right">Cash</th><th class="text-right">Discounts</th><th class="text-right">Declared</th><th class="text-right">Diff.</th></tr>
  </thead>
  <tbody>
    <?php if (empty($workers)): ?>
      <tr><td colspan="7" class="text-center muted" style="padding:18px">No worker activity on this date.</td></tr>
    <?php else: foreach ($workers as $w):
      $decl = $declByUser[(int) $w['id']] ?? null;
      $declared = $decl ? (float) $decl['declared_cash'] : null;
      $declDiff = $decl ? (float) $decl['difference'] : null;
    ?>
      <tr>
        <td><strong><?= e($w['full_name']) ?></strong><br><span class="muted" style="font-size:10.5px;">@<?= e($w['username']) ?></span></td>
        <td class="text-center"><?= (int) $w['sales_count'] ?></td>
        <td class="text-right"><?= formatMoney($w['revenue']) ?></td>
        <td class="text-right"><?= formatMoney($w['cash_sales']) ?></td>
        <td class="text-right"><?= formatMoney($w['discounts']) ?></td>
        <td class="text-right"><?= $declared !== null ? formatMoney($declared) : '<span class="muted">—</span>' ?></td>
        <td class="text-right" style="color:<?= $declDiff === null ? '#666' : ($declDiff == 0 ? '#666' : ($declDiff < 0 ? '#B91C1C' : '#0D6E5D')) ?>;">
          <?= $declDiff === null ? '—' : ($declDiff == 0 ? '0.00' : ($declDiff > 0 ? '+ ' : '− ') . formatMoney(abs($declDiff))) ?>
        </td>
      </tr>
    <?php endforeach; endif; ?>
  </tbody>
</table>

<p class="muted" style="font-size:11px;margin-top:10px;">
  * "Declared" is the physical cash the worker reported handing over. "Diff." is Declared − Expected (positive = surplus, negative = shortage).
</p>

<div class="signatures">
  <div class="sig">Worker Signature</div>
  <div class="sig">Boss / Manager Signature</div>
</div>

<?php include __DIR__ . '/../../includes/pdf_footer.php'; ?>
