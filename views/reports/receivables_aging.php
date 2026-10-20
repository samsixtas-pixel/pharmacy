<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

$db = Database::getConnection();

$sql = "SELECT
            customer_name,
            SUM(CASE WHEN DATEDIFF(CURDATE(), sale_date) BETWEEN 0  AND 30 THEN remaining_balance ELSE 0 END) AS bucket_0_30,
            SUM(CASE WHEN DATEDIFF(CURDATE(), sale_date) BETWEEN 31 AND 60 THEN remaining_balance ELSE 0 END) AS bucket_31_60,
            SUM(CASE WHEN DATEDIFF(CURDATE(), sale_date) BETWEEN 61 AND 90 THEN remaining_balance ELSE 0 END) AS bucket_61_90,
            SUM(CASE WHEN DATEDIFF(CURDATE(), sale_date) > 90 THEN remaining_balance ELSE 0 END) AS bucket_90_plus,
            SUM(remaining_balance) AS total_debt,
            MIN(sale_date) AS oldest_sale,
            COUNT(*) AS invoice_count
        FROM sales
        WHERE payment_status IN ('partial','unpaid') AND remaining_balance > 0
        GROUP BY customer_name
        ORDER BY total_debt DESC";

$rows = $db->query($sql)->fetchAll();

$totals = ['bucket_0_30'=>0,'bucket_31_60'=>0,'bucket_61_90'=>0,'bucket_90_plus'=>0,'total_debt'=>0];
foreach ($rows as $r) {
    $totals['bucket_0_30']   += (float)$r['bucket_0_30'];
    $totals['bucket_31_60']  += (float)$r['bucket_31_60'];
    $totals['bucket_61_90']  += (float)$r['bucket_61_90'];
    $totals['bucket_90_plus'] += (float)$r['bucket_90_plus'];
    $totals['total_debt']    += (float)$r['total_debt'];
}

$pageTitle = 'Receivables Aging';
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
  <p class="text-muted mb-0">How long each customer's outstanding debt has been unpaid.</p>
  <button onclick="window.print()" class="btn btn-outline-primary"><i class="bi bi-printer"></i> Print</button>
</div>

<div class="card mb-3">
  <div class="card-header">Aging Summary</div>
  <div class="card-body">
    <div class="row text-center">
      <div class="col"><div class="text-muted small">0–30 days</div><div class="fw-bold"><?= formatMoney($totals['bucket_0_30']) ?></div></div>
      <div class="col"><div class="text-muted small">31–60 days</div><div class="fw-bold"><?= formatMoney($totals['bucket_31_60']) ?></div></div>
      <div class="col"><div class="text-muted small">61–90 days</div><div class="fw-bold"><?= formatMoney($totals['bucket_61_90']) ?></div></div>
      <div class="col"><div class="text-muted small">90+ days</div><div class="fw-bold text-danger"><?= formatMoney($totals['bucket_90_plus']) ?></div></div>
      <div class="col border-start"><div class="text-muted small">Total Outstanding</div><div class="fw-bold text-danger"><?= formatMoney($totals['total_debt']) ?></div></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">Receivables by Customer</div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr>
          <th>#</th><th>Customer</th><th>0–30</th><th>31–60</th><th>61–90</th><th>90+</th><th>Total Debt</th><th>Oldest</th><th>Invoices</th>
        </tr></thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="9" class="text-center text-muted py-4">No outstanding debt. Excellent!</td></tr>
          <?php else: foreach ($rows as $i => $r): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><strong><?= e($r['customer_name']) ?></strong></td>
              <td><?= formatMoney($r['bucket_0_30']) ?></td>
              <td><?= formatMoney($r['bucket_31_60']) ?></td>
              <td><?= formatMoney($r['bucket_61_90']) ?></td>
              <td class="text-danger fw-bold"><?= formatMoney($r['bucket_90_plus']) ?></td>
              <td class="fw-bold"><?= formatMoney($r['total_debt']) ?></td>
              <td><?= formatDate($r['oldest_sale']) ?></td>
              <td><?= (int)$r['invoice_count'] ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>