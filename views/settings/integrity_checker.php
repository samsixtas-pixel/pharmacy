<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

$db = Database::getConnection();
$issues = [];

// 1. Product stock check: current_stock == sum(stock_in) - sum(stock_out) - sum(sales qty)
$sql = "SELECT p.id, p.product_name, p.size, p.current_stock,
               COALESCE((SELECT SUM(quantity) FROM stock_in  WHERE product_id = p.id),0) AS total_in,
               COALESCE((SELECT SUM(quantity) FROM stock_out WHERE product_id = p.id),0) AS total_out,
               COALESCE((SELECT SUM(quantity) FROM sales     WHERE product_id = p.id),0) AS total_sold
        FROM products p
        WHERE p.status = 'active'
        ORDER BY p.product_name";
$rows = $db->query($sql)->fetchAll();
foreach ($rows as $r) {
    $expected = (int)$r['total_in'] - (int)$r['total_out'] - (int)$r['total_sold'];
    $actual   = (int)$r['current_stock'];
    if ($expected !== $actual) {
        $issues[] = [
            'type'    => 'Stock mismatch',
            'entity'  => $r['product_name'] . ' ' . $r['size'],
            'detail'  => "System says {$actual}, ledger says {$expected} (in={$r['total_in']} out={$r['total_out']} sold={$r['total_sold']})",
            'link'    => BASE_URL . 'views/stock/audit.php',
        ];
    }
}

// 2. Sale total sanity: total_amount == selling_price*qty - discount
$sql = "SELECT id, customer_name, selling_price, quantity, discount, total_amount
        FROM sales
        WHERE ABS((selling_price * quantity - discount) - total_amount) > 0.01
        LIMIT 20";
foreach ($db->query($sql) as $r) {
    $issues[] = [
        'type'   => 'Sale math error',
        'entity' => 'Sale #' . $r['id'] . ' — ' . $r['customer_name'],
        'detail' => 'Stored total (' . number_format($r['total_amount'], 2) . ') does not match price×qty−discount',
        'link'   => BASE_URL . 'views/sales/index.php',
    ];
}

// 3. Payment balance: amount_paid + remaining == total_amount
$sql = "SELECT id, customer_name, total_amount, amount_paid, remaining_balance
        FROM sales
        WHERE ABS((amount_paid + remaining_balance) - total_amount) > 0.01
        LIMIT 20";
foreach ($db->query($sql) as $r) {
    $issues[] = [
        'type'   => 'Balance math error',
        'entity' => 'Sale #' . $r['id'] . ' — ' . $r['customer_name'],
        'detail' => 'Paid (' . number_format($r['amount_paid'], 2) . ') + balance (' . number_format($r['remaining_balance'], 2) . ') ≠ total (' . number_format($r['total_amount'], 2) . ')',
        'link'   => BASE_URL . 'views/sales/index.php',
    ];
}

// 4. Tampered seals (past 60 days)
$sealModel = new DailySeal();
$sql = "SELECT seal_date FROM daily_seals WHERE seal_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) ORDER BY seal_date DESC";
foreach ($db->query($sql) as $r) {
    $v = $sealModel->verify($r['seal_date']);
    if ($v['status'] === 'tampered') {
        $issues[] = [
            'type'   => 'SEAL BROKEN',
            'entity' => formatDate($r['seal_date']),
            'detail' => 'Data was modified after this day was sealed. Investigate immediately.',
            'link'   => BASE_URL . 'views/reports/daily_seals.php',
        ];
    }
}

// 5. Duplicate barcodes / SKUs (should be impossible thanks to UNIQUE, but check anyway)
$sql = "SELECT sku, COUNT(*) c FROM products WHERE sku IS NOT NULL GROUP BY sku HAVING c > 1";
foreach ($db->query($sql) as $r) {
    $issues[] = ['type'=>'Duplicate SKU', 'entity'=>$r['sku'], 'detail'=>'Used by ' . $r['c'] . ' products', 'link'=>BASE_URL.'views/products/index.php'];
}
$sql = "SELECT barcode, COUNT(*) c FROM products WHERE barcode IS NOT NULL GROUP BY barcode HAVING c > 1";
foreach ($db->query($sql) as $r) {
    $issues[] = ['type'=>'Duplicate barcode', 'entity'=>$r['barcode'], 'detail'=>'Used by ' . $r['c'] . ' products', 'link'=>BASE_URL.'views/products/index.php'];
}

// 6. Orphan sales (product deleted but FK integrity would prevent it — still, sanity check)
$sql = "SELECT COUNT(*) FROM sales s LEFT JOIN products p ON s.product_id = p.id WHERE p.id IS NULL";
$orphans = (int) $db->query($sql)->fetchColumn();
if ($orphans > 0) {
    $issues[] = ['type'=>'Orphan sales', 'entity'=>"$orphans record(s)", 'detail'=>'Sales pointing to non-existent products', 'link'=>BASE_URL.'views/sales/index.php'];
}

$pageTitle = 'Data Integrity Checker';
include __DIR__ . '/../../includes/header.php';
?>

<div class="alert alert-<?= empty($issues) ? 'success' : 'warning' ?> d-flex justify-content-between align-items-center">
  <div>
    <i class="bi <?= empty($issues) ? 'bi-check2-circle' : 'bi-exclamation-triangle-fill' ?> me-1"></i>
    <strong><?= count($issues) ?> issue(s) found.</strong>
    <?= empty($issues) ? 'System is fully consistent — everything adds up.' : 'Review the list below and address each item.' ?>
  </div>
  <a href="integrity_checker.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-clockwise"></i> Re-run Check</a>
</div>

<div class="card">
  <div class="card-header">Issues</div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>Type</th><th>Entity</th><th>Details</th><th>Action</th></tr></thead>
        <tbody>
          <?php if (empty($issues)): ?>
            <tr><td colspan="4" class="text-center text-muted py-4">
              <i class="bi bi-shield-check" style="font-size:2rem;color:#118057"></i><br>
              All checks passed.
            </td></tr>
          <?php else: foreach ($issues as $i): ?>
            <tr>
              <td><span class="badge bg-<?= $i['type']==='SEAL BROKEN' ? 'danger' : 'warning' ?>"><?= e($i['type']) ?></span></td>
              <td><?= e($i['entity']) ?></td>
              <td class="small"><?= e($i['detail']) ?></td>
              <td><a href="<?= $i['link'] ?>" class="btn btn-sm btn-outline-primary">Investigate</a></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>