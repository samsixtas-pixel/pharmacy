<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireLogin();

$isAdmin = User::isAdmin();
$saleModel = new Sale();
$db = Database::getConnection();
$errors = [];
$success = '';

// ---------- POST: settle a debt (partial/full payment) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sale_id'])) {
    $saleId = (int)$_POST['sale_id'];
    $amount = (float)($_POST['amount'] ?? 0);
    $date   = $_POST['payment_date'] ?? date('Y-m-d');

    if ($amount <= 0) $errors[] = 'Payment amount must be positive.';
    else {
        PeriodLock::ensureUnlocked($date, 'record a payment in');
        $ok = $saleModel->applyPayment($saleId, $amount, $date, (int)$_SESSION['user_id']);
        if ($ok) {
            AuditLog::record('payment.create', 'sales', $saleId,
                'Debt payment: ' . number_format($amount, 2));
            header('Location: index.php?success=' . urlencode('Payment recorded.'));
            exit;
        }
        $errors[] = 'Failed to record payment. It may exceed the balance.';
    }
}

if (!empty($_GET['success'])) $success = $_GET['success'];

// ---------- Filter: my debts or all (staff see all too) ----------
$scope = $_GET['scope'] ?? 'outstanding';

if ($scope === 'settled') {
    $sql = "SELECT s.*, p.product_name, p.size, u.full_name AS worker_name
            FROM sales s JOIN products p ON s.product_id = p.id
            LEFT JOIN users u ON s.user_id = u.id
            WHERE s.payment_status = 'paid'
            ORDER BY s.sale_date DESC, s.id DESC LIMIT 200";
} else {
    $sql = "SELECT s.*, p.product_name, p.size, u.full_name AS worker_name
            FROM sales s JOIN products p ON s.product_id = p.id
            LEFT JOIN users u ON s.user_id = u.id
            WHERE s.payment_status IN ('partial','unpaid') AND s.remaining_balance > 0
            ORDER BY s.sale_date ASC, s.id ASC";
}

$rows = $db->query($sql)->fetchAll();

$totalOutstanding = 0;
$ageBuckets = ['recent' => 0, 'warn' => 0, 'old' => 0, 'critical' => 0];
foreach ($rows as $r) {
    if ($scope !== 'settled') {
        $totalOutstanding += (float)$r['remaining_balance'];
        $days = (int)floor((time() - strtotime($r['sale_date'])) / 86400);
        if ($days <= 7)        $ageBuckets['recent']++;
        elseif ($days <= 30)   $ageBuckets['warn']++;
        elseif ($days <= 60)   $ageBuckets['old']++;
        else                   $ageBuckets['critical']++;
    }
}

$pageTitle = 'Debts';
include __DIR__ . '/../../includes/header.php';
?>

<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<?php if ($success): ?>
  <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div class="btn-group">
    <a href="?scope=outstanding" class="btn <?= $scope !== 'settled' ? 'btn-primary' : 'btn-outline-primary' ?>">
      <i class="bi bi-hourglass-split"></i> Outstanding
    </a>
    <a href="?scope=settled" class="btn <?= $scope === 'settled' ? 'btn-primary' : 'btn-outline-primary' ?>">
      <i class="bi bi-check2-circle"></i> Settled
    </a>
  </div>
  <a href="add.php" class="btn btn-warning btn-lg">
    <i class="bi bi-person-plus-fill"></i> Record New Debt
  </a>
</div>

<?php if ($scope !== 'settled'): ?>
<div class="row g-2 mb-3">
  <div class="col-6 col-md-3">
    <div class="stat-card money-card">
      <div class="icon" style="background:#C94040"><i class="bi bi-cash-stack"></i></div>
      <div><div class="label">Total Outstanding</div><div class="value"><?= formatMoney($totalOutstanding) ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="icon" style="background:#0D6E5D"><i class="bi bi-check2"></i></div>
      <div><div class="label">≤ 7 days</div><div class="value"><?= $ageBuckets['recent'] ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="icon" style="background:#C98012"><i class="bi bi-exclamation-triangle"></i></div>
      <div><div class="label">8 – 30 days</div><div class="value"><?= $ageBuckets['warn'] ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="icon" style="background:#B91C1C"><i class="bi bi-exclamation-octagon"></i></div>
      <div><div class="label">31+ days</div><div class="value"><?= $ageBuckets['old'] + $ageBuckets['critical'] ?></div></div>
    </div>
  </div>
</div>

<?php if ($ageBuckets['critical'] > 0): ?>
  <div class="alert alert-danger">
    <i class="bi bi-exclamation-octagon-fill me-1"></i>
    <strong><?= $ageBuckets['critical'] ?></strong> debt(s) older than 60 days. Remind the customers and report to the boss.
  </div>
<?php endif; ?>

<?php if ($ageBuckets['old'] > 0): ?>
  <div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle-fill me-1"></i>
    <strong><?= $ageBuckets['old'] ?></strong> debt(s) between 31 and 60 days. Follow up soon.
  </div>
<?php endif; ?>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <?= $scope === 'settled' ? 'Settled Debts' : 'Outstanding Debts' ?>
    <span class="text-muted small ms-2"><?= count($rows) ?> record(s)</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr>
          <th>Date</th><th>Customer</th><th>Phone</th><th>Product</th><th>Qty</th>
          <th class="text-end">Total</th><th class="text-end">Paid</th><th class="text-end">Balance</th>
          <th>Age</th><th>Worker</th>
          <?php if ($scope !== 'settled'): ?><th class="no-print">Action</th><?php endif; ?>
        </tr></thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="11" class="text-center text-muted py-4">
              <?= $scope === 'settled' ? 'No settled debts yet.' : 'No outstanding debts. Everyone has paid.' ?>
            </td></tr>
          <?php else: foreach ($rows as $d):
            $days = (int)floor((time() - strtotime($d['sale_date'])) / 86400);
            $rowClass = '';
            if ($scope !== 'settled') {
              if ($days > 60)      $rowClass = 'table-danger';
              elseif ($days > 30)  $rowClass = 'table-warning';
            }
          ?>
            <tr class="<?= $rowClass ?>">
              <td class="small"><?= formatDate($d['sale_date']) ?><br><span class="text-muted"><?= date('H:i', strtotime($d['created_at'])) ?></span></td>
              <td><strong><?= e($d['customer_name']) ?></strong></td>
              <td class="small"><?= e($d['customer_phone'] ?? '—') ?></td>
              <td><?= e($d['product_name']) ?> <span class="text-muted small"><?= e($d['size']) ?></span></td>
              <td><?= (int)$d['quantity'] ?></td>
              <td class="text-end"><?= formatMoney($d['total_amount']) ?></td>
              <td class="text-end"><?= formatMoney($d['amount_paid']) ?></td>
              <td class="text-end fw-bold text-danger"><?= formatMoney($d['remaining_balance']) ?></td>
              <td>
                <?php if ($scope === 'settled'): ?>
                  <span class="badge bg-success">Paid</span>
                <?php else: ?>
                  <span class="badge <?= $days > 60 ? 'bg-danger' : ($days > 30 ? 'bg-warning' : 'bg-secondary') ?>">
                    <?= $days ?>d
                  </span>
                <?php endif; ?>
              </td>
              <td class="small"><?= e($d['worker_name'] ?? '—') ?></td>
              <?php if ($scope !== 'settled'): ?>
              <td class="no-print">
                <button type="button" class="btn btn-sm btn-primary"
                        data-bs-toggle="modal" data-bs-target="#payModal"
                        data-sale-id="<?= $d['id'] ?>"
                        data-customer="<?= e($d['customer_name']) ?>"
                        data-balance="<?= $d['remaining_balance'] ?>">
                  <i class="bi bi-cash"></i> Settle
                </button>
              </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Settle Modal -->
<div class="modal fade" id="payModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-cash-coin"></i> Settle Debt</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="sale_id" id="modalSaleId">
          <div class="mb-3">
            <label class="form-label">Customer</label>
            <input type="text" id="modalCustomer" class="form-control" readonly>
          </div>
          <div class="mb-3">
            <label class="form-label">Remaining Balance</label>
            <input type="text" id="modalBalance" class="form-control" readonly>
          </div>
          <div class="mb-3">
            <label class="form-label">Payment Amount (TZS)</label>
            <input type="number" step="0.01" min="0.01" name="amount" id="modalAmount" class="form-control form-control-lg" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Payment Date</label>
            <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Payment</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.getElementById('payModal').addEventListener('show.bs.modal', function (event) {
  const btn = event.relatedTarget;
  document.getElementById('modalSaleId').value = btn.dataset.saleId;
  document.getElementById('modalCustomer').value = btn.dataset.customer;
  document.getElementById('modalBalance').value = parseFloat(btn.dataset.balance).toLocaleString('en-US', {minimumFractionDigits:2});
  const amountInput = document.getElementById('modalAmount');
  amountInput.max = btn.dataset.balance;
  amountInput.value = btn.dataset.balance;
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>