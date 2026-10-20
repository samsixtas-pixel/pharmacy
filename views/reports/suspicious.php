<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

$from = trim($_GET['from'] ?? date('Y-m-01'));
$to = trim($_GET['to'] ?? date('Y-m-d'));

$db = Database::getConnection();

$saleModel = new Sale();
$belowCost = $saleModel->salesBelowCost($from, $to);

$stmt = $db->prepare(
    "SELECT u.id, u.full_name, u.username,
            COALESCE(SUM(s.discount),0) AS total_discount,
            COUNT(s.id) AS sale_count,
            COALESCE(SUM(s.total_amount),0) AS revenue
     FROM users u
     LEFT JOIN sales s ON s.user_id = u.id AND s.sale_date BETWEEN :from AND :to
     WHERE u.role = 'staff'
     GROUP BY u.id
     HAVING total_discount > 0
     ORDER BY total_discount DESC"
);
$stmt->execute(['from' => $from, 'to' => $to]);
$discountsByWorker = $stmt->fetchAll();

$stmt = $db->prepare(
    "SELECT u.full_name, u.username, COUNT(*) AS del_count
     FROM audit_log a
     JOIN users u ON a.user_id = u.id
     WHERE a.action LIKE '%delete%'
       AND DATE(a.created_at) BETWEEN :from AND :to
     GROUP BY u.id
     ORDER BY del_count DESC"
);
$stmt->execute(['from' => $from, 'to' => $to]);
$deletionsByWorker = $stmt->fetchAll();

$declModel = new CashDeclaration();
$discrepancies = $declModel->search($from, $to, null, 1);

$pageTitle = 'Suspicious Activity';
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 no-print">
  <form method="GET" class="d-flex gap-2 align-items-center">
    <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
    <span>to</span>
    <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
    <button class="btn btn-outline-secondary"><i class="bi bi-funnel"></i> Filter</button>
  </form>
  <button onclick="window.print()" class="btn btn-outline-primary"><i class="bi bi-printer"></i> Print</button>
</div>

<?php
$totalIssues = count($belowCost) + count($discrepancies) + array_sum(array_column($deletionsByWorker, 'del_count'));
?>
<div class="alert <?= $totalIssues > 0 ? 'alert-danger' : 'alert-success' ?>">
  <strong><?= $totalIssues ?></strong> item(s) need your attention between <?= formatDate($from) ?> and <?= formatDate($to) ?>.
</div>

<div class="card mb-3">
  <div class="card-header" style="color:#B91C1C">
    <i class="bi bi-exclamation-octagon-fill"></i> Sales Made Below Cost Price (<?= count($belowCost) ?>)
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>Date</th><th>Customer</th><th>Product</th><th>Qty</th><th>Cost</th><th>Selling</th><th>Worker</th></tr></thead>
        <tbody>
          <?php if (empty($belowCost)): ?>
            <tr><td colspan="7" class="text-center text-muted py-3">No sales below cost. Excellent!</td></tr>
          <?php else: foreach ($belowCost as $s): ?>
            <tr>
              <td><?= formatDate($s['sale_date']) ?></td>
              <td><?= e($s['customer_name']) ?></td>
              <td><?= e($s['product_name']) ?></td>
              <td><?= (int) $s['quantity'] ?></td>
              <td><?= formatMoney($s['cost_price']) ?></td>
              <td class="text-danger fw-bold"><?= formatMoney($s['selling_price']) ?></td>
              <td><?= e($s['worker_name'] ?? '—') ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header" style="color:#A8680A">
    <i class="bi bi-percent"></i> Discounts Given by Workers
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>Worker</th><th>Sales</th><th>Revenue</th><th>Total Discounts</th><th>Discount %</th></tr></thead>
        <tbody>
          <?php if (empty($discountsByWorker)): ?>
            <tr><td colspan="5" class="text-center text-muted py-3">No discounts given in this period.</td></tr>
          <?php else: foreach ($discountsByWorker as $w):
            $pct = $w['revenue'] > 0 ? ($w['total_discount'] / ($w['revenue'] + $w['total_discount'])) * 100 : 0;
          ?>
            <tr>
              <td><strong><?= e($w['full_name']) ?></strong><br><span class="text-muted small">@<?= e($w['username']) ?></span></td>
              <td><?= (int) $w['sale_count'] ?></td>
              <td><?= formatMoney($w['revenue']) ?></td>
              <td class="text-danger fw-bold"><?= formatMoney($w['total_discount']) ?></td>
              <td><?= number_format($pct, 1) ?>%</td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header" style="color:#B91C1C">
    <i class="bi bi-trash-fill"></i> Deletion Activity
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>Worker</th><th>Deletions</th></tr></thead>
        <tbody>
          <?php if (empty($deletionsByWorker)): ?>
            <tr><td colspan="2" class="text-center text-muted py-3">No deletions in this period.</td></tr>
          <?php else: foreach ($deletionsByWorker as $d): ?>
            <tr>
              <td><strong><?= e($d['full_name']) ?></strong> <span class="text-muted small">@<?= e($d['username']) ?></span></td>
              <td class="fw-bold text-danger"><?= (int) $d['del_count'] ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header" style="color:#B91C1C">
    <i class="bi bi-cash-coin"></i> Cash Discrepancies (<?= count($discrepancies) ?>)
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>Date</th><th>Worker</th><th>Expected</th><th>Declared</th><th>Difference</th><th>Notes</th></tr></thead>
        <tbody>
          <?php if (empty($discrepancies)): ?>
            <tr><td colspan="6" class="text-center text-muted py-3">No discrepancies. Perfect records!</td></tr>
          <?php else: foreach ($discrepancies as $d):
            $diff = (float) $d['difference'];
            $cls = $diff < 0 ? 'text-danger fw-bold' : 'text-success fw-bold';
          ?>
            <tr>
              <td><?= formatDate($d['declaration_date']) ?></td>
              <td><?= e($d['worker_name']) ?></td>
              <td><?= formatMoney($d['expected_cash']) ?></td>
              <td><?= formatMoney($d['declared_cash']) ?></td>
              <td class="<?= $cls ?>"><?= ($diff > 0 ? '+ ' : '− ') . formatMoney(abs($diff)) ?></td>
              <td class="small"><?= e($d['notes']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
