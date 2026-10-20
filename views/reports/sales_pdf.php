<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

$reportModel = new Report();
$from = trim($_GET['from'] ?? date('Y-m-01'));
$to = trim($_GET['to'] ?? date('Y-m-d'));
$keyword = trim($_GET['q'] ?? '');
$rows = $reportModel->salesReport($from ?: null, $to ?: null, $keyword);
$totalRevenue = array_sum(array_column($rows, 'total_amount'));
$totalCost = array_sum(array_column($rows, 'total_cost'));
$totalProfit = array_sum(array_column($rows, 'profit'));
$totalPaid = array_sum(array_column($rows, 'amount_paid'));
$totalBalance = array_sum(array_column($rows, 'remaining_balance'));

$pdfTitle = 'Sales Report';
$pdfSubtitle = 'Period: ' . formatDate($from) . ' — ' . formatDate($to);
include __DIR__ . '/../../includes/pdf_header.php';
?>
<h2 style="margin:0 0 4px;font-size:16px;">SALES REPORT</h2>
<div class="muted" style="font-size:11.5px;margin-bottom:14px;">From <strong><?= formatDate($from) ?></strong> to <strong><?= formatDate($to) ?></strong><?= $keyword ? ' — Customer filter: "' . e($keyword) . '"' : '' ?>&nbsp;•&nbsp;<?= count($rows) ?> sale(s)</div>
<div class="section-title">Sales Transactions</div>
<table class="tbl"><thead><tr><th style="width:5%">#</th><th style="width:11%">Date</th><th>Customer</th><th>Product</th><th style="width:6%" class="text-center">Qty</th><th class="text-right">Total</th><th class="text-right">Profit</th><th class="text-right">Paid</th><th class="text-right">Balance</th><th style="width:9%" class="text-center">Status</th></tr></thead><tbody>
<?php if (empty($rows)): ?><tr><td colspan="10" class="text-center muted" style="padding:24px">No sales in this period.</td></tr><?php else: foreach ($rows as $i => $s): ?><tr><td><?= $i + 1 ?></td><td><?= formatDate($s['sale_date']) ?></td><td><?= e($s['customer_name']) ?></td><td><?= e($s['product_name']) ?></td><td class="text-center"><?= (int) $s['quantity'] ?></td><td class="text-right"><?= formatMoney($s['total_amount']) ?></td><td class="text-right"><?= formatMoney($s['profit']) ?></td><td class="text-right"><?= formatMoney($s['amount_paid']) ?></td><td class="text-right"><?= formatMoney($s['remaining_balance']) ?></td><td class="text-center"><span class="badge badge-<?= e($s['payment_status']) ?>"><?= e($s['payment_status']) ?></span></td></tr><?php endforeach; endif; ?>
</tbody></table>
<div class="totals"><div class="row"><span>Total Revenue</span><span><?= formatMoney($totalRevenue) ?></span></div><div class="row"><span>Cost of Goods Sold</span><span><?= formatMoney($totalCost) ?></span></div><div class="row"><span>Gross Profit</span><span><?= formatMoney($totalProfit) ?></span></div><div class="row"><span>Cash Collected</span><span><?= formatMoney($totalPaid) ?></span></div><div class="row grand"><span>Outstanding Balance</span><span><?= formatMoney($totalBalance) ?></span></div></div>
<div class="signatures"><div class="sig">Prepared By (<?= e($_SESSION['full_name'] ?? '') ?>)</div><div class="sig">Approved By (Manager)</div></div>
<?php include __DIR__ . '/../../includes/pdf_footer.php'; ?>
