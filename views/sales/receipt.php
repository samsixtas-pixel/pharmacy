<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireLogin();

// IDOR protection — workers can only view their own receipts
$saleOwnerId = null;
try {
    $db = Database::getConnection();
    $stmt = $db->prepare("SELECT user_id FROM sales WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => (int)($_GET['id'] ?? 0)]);
    $saleOwnerId = (int) $stmt->fetchColumn();
} catch (Throwable $e) {}

if ($saleOwnerId !== null) {
    Security::requireOwnershipOrAdmin($saleOwnerId);
}

$db = Database::getConnection();
$stmt = $db->prepare(
    "SELECT s.*, p.product_name, p.category, p.size, u.full_name AS sold_by
     FROM sales s
     JOIN products p ON s.product_id = p.id
     LEFT JOIN users u ON s.user_id = u.id
     WHERE s.id = :id"
);
$stmt->execute(['id' => (int) ($_GET['id'] ?? 0)]);
$sale = $stmt->fetch();

if (!$sale) {
    header('Location: index.php?error=Sale not found');
    exit;
}

$pdfTitle = 'Sales Receipt';
$pdfSubtitle = 'Receipt #' . str_pad((string) $sale['id'], 6, '0', STR_PAD_LEFT);
include __DIR__ . '/../../includes/pdf_header.php';
$statusClass = 'badge-' . $sale['payment_status'];
?>
<h2 style="margin:0 0 4px;font-size:16px;">CUSTOMER RECEIPT</h2>
<div class="muted" style="font-size:11.5px;margin-bottom:16px;">
  Receipt No: <strong>#<?= str_pad((string) $sale['id'], 6, '0', STR_PAD_LEFT) ?></strong>
  &nbsp;•&nbsp; Date: <strong><?= formatDate($sale['sale_date']) ?></strong>
</div>
<div class="section-title">Customer &amp; Transaction Details</div>
<table class="tbl">
  <tr><td style="width:25%" class="muted">Customer</td><td style="width:25%"><strong><?= e($sale['customer_name']) ?></strong></td><td style="width:25%" class="muted">Cashier</td><td style="width:25%"><strong><?= e($sale['sold_by'] ?? '—') ?></strong></td></tr>
  <tr><td class="muted">Payment Status</td><td><span class="badge <?= $statusClass ?>"><?= e($sale['payment_status']) ?></span></td><td class="muted">Recorded</td><td><?= date('d M Y, H:i', strtotime($sale['created_at'])) ?></td></tr>
</table>
<div class="section-title">Items Purchased</div>
<table class="tbl">
  <thead><tr><th style="width:6%">#</th><th>Product</th><th style="width:10%">Size</th><th style="width:8%" class="text-center">Qty</th><th style="width:15%" class="text-right">Unit Price</th><th style="width:17%" class="text-right">Amount</th></tr></thead>
  <tbody><tr><td>1</td><td><strong><?= e($sale['product_name']) ?></strong><br><span class="muted" style="font-size:11px;"><?= e(ucwords(str_replace('_', ' ', $sale['category']))) ?></span></td><td><?= e($sale['size']) ?></td><td class="text-center"><?= (int) $sale['quantity'] ?></td><td class="text-right"><?= formatMoney($sale['selling_price']) ?></td><td class="text-right"><?= formatMoney($sale['total_amount']) ?></td></tr></tbody>
</table>
<div class="totals">
  <div class="row"><span>Subtotal</span><span><?= formatMoney($sale['total_amount']) ?></span></div>
  <div class="row"><span>Amount Paid</span><span><?= formatMoney($sale['amount_paid']) ?></span></div>
  <div class="row grand"><span>Balance Due</span><span><?= formatMoney($sale['remaining_balance']) ?></span></div>
</div>
<div class="signatures"><div class="sig">Customer Signature</div><div class="sig">Cashier Signature (<?= e($sale['sold_by'] ?? '') ?>)</div></div>
<?php include __DIR__ . '/../../includes/pdf_footer.php'; ?>
