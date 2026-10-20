<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireLogin();

$isAdmin = User::isAdmin();
$uid = (int)$_SESSION['user_id'];
$db = Database::getConnection();

$scope = $_GET['scope'] ?? 'today';
$showAll = $scope === 'all';

if (!$isAdmin) {
    // Staff: today's sales by default, optionally all their own
    $sql = "SELECT s.*, p.product_name, p.size, u.full_name AS sold_by
            FROM sales s
            JOIN products p ON s.product_id = p.id
            LEFT JOIN users u ON s.user_id = u.id
            WHERE s.user_id = :uid";
    $params = ['uid' => $uid];
    if (!$showAll) {
        $sql .= " AND s.sale_date = CURDATE()";
    }
    $sql .= " ORDER BY s.sale_date DESC, s.id DESC LIMIT 500";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $sales = $stmt->fetchAll();
} else {
    // Admin: see all (today or all)
    $sql = "SELECT s.*, p.product_name, p.size, u.full_name AS sold_by
            FROM sales s
            JOIN products p ON s.product_id = p.id
            LEFT JOIN users u ON s.user_id = u.id";
    if (!$showAll) {
        $sql .= " WHERE s.sale_date = CURDATE()";
    }
    $sql .= " ORDER BY s.sale_date DESC, s.id DESC LIMIT 500";
    $sales = $db->query($sql)->fetchAll();
}

$total = array_sum(array_column($sales, 'total_amount'));
$paid  = array_sum(array_column($sales, 'amount_paid'));

$pageTitle = 'Sales';
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div class="btn-group">
    <a href="?scope=today" class="btn <?= !$showAll ? 'btn-primary' : 'btn-outline-primary' ?>">
      <i class="bi bi-calendar-day"></i> Today
    </a>
    <a href="?scope=all" class="btn <?= $showAll ? 'btn-primary' : 'btn-outline-primary' ?>">
      <i class="bi bi-clock-history"></i> <?= $isAdmin ? 'All Sales' : 'My History' ?>
    </a>
  </div>
  <a href="add.php" class="btn btn-success btn-lg">
    <i class="bi bi-plus-circle-fill"></i> New Sale
  </a>
</div>

<div class="row g-2 mb-3">
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="icon" style="background:#0D6E5D"><i class="bi bi-bag-check-fill"></i></div>
      <div><div class="label">Sales Count</div><div class="value"><?= count($sales) ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card money-card">
      <div class="icon" style="background:#118057"><i class="bi bi-journal-text"></i></div>
      <div><div class="label">Total Value</div><div class="value"><?= formatMoney($total) ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card money-card">
      <div class="icon" style="background:#0D6E5D"><i class="bi bi-cash-stack"></i></div>
      <div><div class="label">Cash Collected</div><div class="value"><?= formatMoney($paid) ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card money-card">
      <div class="icon" style="background:#C94040"><i class="bi bi-person-badge-fill"></i></div>
      <div><div class="label">On Credit</div><div class="value"><?= formatMoney($total - $paid) ?></div></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr>
          <th>Time</th><th>Customer</th><th>Product</th><th>Qty</th>
          <th class="text-end">Total</th><th class="text-end">Paid</th><th class="text-end">Balance</th>
          <th>Status</th><th class="no-print">Receipt</th>
        </tr></thead>
        <tbody>
          <?php if (empty($sales)): ?>
            <tr><td colspan="9" class="text-center text-muted py-4">
              <?= $showAll ? 'No sales yet.' : 'No sales today yet.' ?>
              <a href="add.php" class="alert-link ms-2">Record one →</a>
            </td></tr>
          <?php else: foreach ($sales as $s): ?>
            <tr>
              <td class="small text-muted">
                <?= $showAll ? formatDate($s['sale_date']) . ' ' : '' ?><?= date('H:i', strtotime($s['created_at'])) ?>
              </td>
              <td>
                <?= e($s['customer_name']) ?>
                <?php if (!empty($s['customer_phone'])): ?>
                  <br><span class="text-muted small"><?= e($s['customer_phone']) ?></span>
                <?php endif; ?>
              </td>
              <td><?= e($s['product_name']) ?> <span class="text-muted small"><?= e($s['size']) ?></span></td>
              <td><?= (int)$s['quantity'] ?></td>
              <td class="text-end"><?= formatMoney($s['total_amount']) ?></td>
              <td class="text-end"><?= formatMoney($s['amount_paid']) ?></td>
              <td class="text-end text-danger"><?= formatMoney($s['remaining_balance']) ?></td>
              <td><?= statusBadge($s['payment_status']) ?></td>
              <td class="no-print">
                <a href="receipt.php?id=<?= $s['id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                  <i class="bi bi-receipt"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>