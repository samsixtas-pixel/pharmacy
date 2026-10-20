<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

$customerName = trim($_GET['customer'] ?? '');
if ($customerName === '') { die('Customer name required'); }

$db = Database::getConnection();

// Sales history
$stmt = $db->prepare(
    "SELECT s.*, p.product_name, p.size FROM sales s
     JOIN products p ON s.product_id = p.id
     WHERE s.customer_name = :n
     ORDER BY s.sale_date ASC, s.id ASC"
);
$stmt->execute(['n' => $customerName]);
$sales = $stmt->fetchAll();

// Payment history
$stmt = $db->prepare(
    "SELECT pay.*, s.customer_name FROM payments pay
     JOIN sales s ON pay.sale_id = s.id
     WHERE s.customer_name = :n
     ORDER BY pay.payment_date ASC, pay.id ASC"
);
$stmt->execute(['n' => $customerName]);
$payments = $stmt->fetchAll();

$totalInvoiced = array_sum(array_column($sales, 'total_amount'));
$totalPaid     = array_sum(array_column($sales, 'amount_paid')) + array_sum(array_column($payments, 'amount'));
$currentDebt   = array_sum(array_column($sales, 'remaining_balance'));

$pdfTitle = 'Customer Statement';
$pdfSubtitle = $customerName;
include __DIR__ . '/../../includes/pdf_header.php';
?>

<h2 style="margin:0 0 4px;font-size:16px;">CUSTOMER ACCOUNT STATEMENT</h2>
<div class="muted" style="font-size:11.5px;margin-bottom:14px;">
  Customer: <strong><?= e($customerName) ?></strong>
  &nbsp;•&nbsp; Statement Date: <strong><?= date('d M Y') ?></strong>
</div>

<div class="section-title">Account Summary</div>
<table class="tbl">
  <tbody>
    <tr><td style="width:60%">Total Invoiced (all sales)</td><td class="text-right"><?= formatMoney($totalInvoiced) ?></td></tr>
    <tr><td>Total Paid (sales + debt payments)</td><td class="text-right"><?= formatMoney($totalPaid) ?></td></tr>
    <tr style="background:#FCE0E0"><td class="bold">CURRENT OUTSTANDING BALANCE</td><td class="text-right bold" style="color:#B91C1C"><?= formatMoney($currentDebt) ?></td></tr>
  </tbody>
</table>

<div class="section-title">Transaction History</div>
<table class="tbl">
  <thead>
    <tr>
      <th style="width:12%">Date</th>
      <th style="width:10%">Type</th>
      <th>Details</th>
      <th style="width:13%" class="text-right">Amount</th>
      <th style="width:13%" class="text-right">Paid</th>
      <th style="width:13%" class="text-right">Balance</th>
    </tr>
  </thead>
  <tbody>
    <?php
    // Merge sales & payments into one chronological stream
    $events = [];
    foreach ($sales as $s) {
        $events[] = [
            'date'  => $s['sale_date'],
            'type'  => 'Sale',
            'detail'=> $s['quantity'] . 'x ' . $s['product_name'] . ' (' . $s['size'] . ')',
            'amount'=> (float)$s['total_amount'],
            'paid'  => (float)$s['amount_paid'],
            'balance'=> (float)$s['remaining_balance'],
        ];
    }
    foreach ($payments as $p) {
        $events[] = [
            'date'  => $p['payment_date'],
            'type'  => 'Payment',
            'detail'=> 'Debt payment received',
            'amount'=> 0,
            'paid'  => (float)$p['amount'],
            'balance'=> 0,
        ];
    }
    usort($events, fn($a, $b) => strcmp($a['date'], $b['date']));
    ?>
    <?php if (empty($events)): ?>
      <tr><td colspan="6" class="text-center muted" style="padding:18px">No transactions.</td></tr>
    <?php else: foreach ($events as $ev): ?>
      <tr>
        <td><?= formatDate($ev['date']) ?></td>
        <td><?= $ev['type'] ?></td>
        <td><?= e($ev['detail']) ?></td>
        <td class="text-right"><?= $ev['amount'] > 0 ? formatMoney($ev['amount']) : '—' ?></td>
        <td class="text-right" style="color:#0D6E5D"><?= $ev['paid'] > 0 ? formatMoney($ev['paid']) : '—' ?></td>
        <td class="text-right" style="color:#B91C1C"><?= $ev['balance'] > 0 ? formatMoney($ev['balance']) : '—' ?></td>
      </tr>
    <?php endforeach; endif; ?>
  </tbody>
</table>

<p class="muted" style="font-size:11px;margin-top:12px;">
  Please settle the outstanding balance at your earliest convenience. Thank you for your business.
</p>

<div class="signatures">
  <div class="sig">Prepared By (<?= e($_SESSION['full_name'] ?? '') ?>)</div>
  <div class="sig">Customer Signature</div>
</div>

<?php include __DIR__ . '/../../includes/pdf_footer.php'; ?>