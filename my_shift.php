<?php
require_once __DIR__ . '/includes/autoload.php';
require_once __DIR__ . '/includes/functions.php';
User::requireLogin();

$uid  = (int)$_SESSION['user_id'];
$date = trim($_GET['date'] ?? date('Y-m-d'));
$db   = Database::getConnection();

// -------- Navigation dates --------
$prevDate = date('Y-m-d', strtotime($date . ' -1 day'));
$nextDate = date('Y-m-d', strtotime($date . ' +1 day'));
$isToday  = ($date === date('Y-m-d'));
$isFuture = ($date > date('Y-m-d'));

// -------- Sales --------
$stmt = $db->prepare(
    "SELECT s.*, p.product_name, p.size FROM sales s
     JOIN products p ON s.product_id = p.id
     WHERE s.user_id = :uid AND s.sale_date = :d
     ORDER BY s.id DESC"
);
$stmt->execute(['uid' => $uid, 'd' => $date]);
$sales = $stmt->fetchAll();

// -------- Debt payments --------
$stmt = $db->prepare(
    "SELECT pay.*, s.customer_name FROM payments pay
     JOIN sales s ON pay.sale_id = s.id
     WHERE pay.user_id = :uid AND pay.payment_date = :d
     ORDER BY pay.id DESC"
);
$stmt->execute(['uid' => $uid, 'd' => $date]);
$payments = $stmt->fetchAll();

// -------- Debt created --------
$stmt = $db->prepare(
    "SELECT s.*, p.product_name, p.size FROM sales s
     JOIN products p ON s.product_id = p.id
     WHERE s.user_id = :uid AND s.sale_date = :d
       AND s.payment_status IN ('partial','unpaid')
     ORDER BY s.id DESC"
);
$stmt->execute(['uid' => $uid, 'd' => $date]);
$debtsCreated = $stmt->fetchAll();

// -------- Expenses --------
$stmt = $db->prepare(
    "SELECT * FROM expenses WHERE user_id = :uid AND expense_date = :d ORDER BY id DESC"
);
$stmt->execute(['uid' => $uid, 'd' => $date]);
$expenses = $stmt->fetchAll();

// -------- Expired reports --------
$expModel = new ExpiredProduct();
$myExpired = $expModel->search(['user_id' => $uid, 'from' => $date, 'to' => $date]);

// -------- Declaration --------
$declModel = new CashDeclaration();
$decl = $declModel->findByUserAndDate($uid, $date);
$expected = $declModel->computeExpected($uid, $date);

// -------- Totals --------
$totalSales  = array_sum(array_column($sales, 'total_amount'));
$totalCash   = array_sum(array_column($sales, 'amount_paid'));
$totalDebtPays = array_sum(array_column($payments, 'amount'));
$totalNewDebt = array_sum(array_column($debtsCreated, 'remaining_balance'));
$totalExpenses = array_sum(array_column($expenses, 'amount'));
$totalLoss = 0;
foreach ($myExpired as $x) if ($x['status'] === 'approved') $totalLoss += (float)$x['cost_at_time'] * (int)$x['quantity'];

// -------- Last 14 days summary (for history list) --------
$stmt = $db->prepare(
    "SELECT sale_date,
            COUNT(*) AS sales_count,
            COALESCE(SUM(total_amount),0) AS revenue,
            COALESCE(SUM(amount_paid),0) AS cash
     FROM sales
     WHERE user_id = :uid AND sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
     GROUP BY sale_date
     ORDER BY sale_date DESC"
);
$stmt->execute(['uid' => $uid]);
$history = $stmt->fetchAll();

$pageTitle = 'My Shift';
include __DIR__ . '/includes/header.php';
?>

<!-- ============ NAVIGATION ============ -->
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <div class="d-flex gap-2">
    <a href="?date=<?= $prevDate ?>" class="btn btn-outline-secondary">
      <i class="bi bi-chevron-left"></i> Previous
    </a>
    <form method="GET" class="d-flex gap-2">
      <input type="date" name="date" class="form-control" value="<?= e($date) ?>" max="<?= date('Y-m-d') ?>" onchange="this.form.submit()">
    </form>
    <?php if (!$isToday): ?>
      <a href="?date=<?= $nextDate ?>" class="btn btn-outline-secondary" <?= $isFuture ? 'disabled' : '' ?>>
        Next <i class="bi bi-chevron-right"></i>
      </a>
    <?php endif; ?>
  </div>
  <?php if ($isToday): ?>
    <span class="badge bg-success" style="font-size:.85rem;padding:.5rem 1rem">
      <i class="bi bi-record-circle"></i> Ongoing Shift
    </span>
  <?php else: ?>
    <span class="badge bg-secondary" style="font-size:.85rem;padding:.5rem 1rem">
      <?= date('D d M Y', strtotime($date)) ?>
    </span>
  <?php endif; ?>
</div>

<!-- ============ SUMMARY ============ -->
<div class="row g-2 mb-3">
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="icon" style="background:#0D6E5D"><i class="bi bi-bag-check-fill"></i></div>
      <div><div class="label">My Sales</div><div class="value"><?= count($sales) ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card money-card">
      <div class="icon" style="background:#118057"><i class="bi bi-cash-stack"></i></div>
      <div><div class="label">Cash Collected</div><div class="value"><?= formatMoney($totalCash + $totalDebtPays) ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card money-card">
      <div class="icon" style="background:#C98012"><i class="bi bi-wallet2"></i></div>
      <div><div class="label">Expenses</div><div class="value"><?= formatMoney($totalExpenses) ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card money-card">
      <div class="icon" style="background:#B91C1C"><i class="bi bi-person-badge-fill"></i></div>
      <div><div class="label">New Debt</div><div class="value"><?= formatMoney($totalNewDebt) ?></div></div>
    </div>
  </div>
</div>

<!-- ============ CASH DECLARATION STATUS ============ -->
<?php if ($decl): ?>
  <div class="alert alert-success d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <i class="bi bi-check2-circle me-1"></i>
      <strong>Cash declared:</strong> <?= formatMoney($decl['declared_cash']) ?>
      &nbsp;|&nbsp; Expected: <?= formatMoney($decl['expected_cash']) ?>
      &nbsp;|&nbsp; Difference: <strong><?= formatMoney($decl['difference']) ?></strong>
      <?php if (!$decl['admin_verified']): ?>
        <span class="badge bg-warning ms-2">Awaiting boss approval</span>
      <?php else: ?>
        <span class="badge bg-success ms-2">Approved</span>
      <?php endif; ?>
    </div>
    <?php if ($isToday && !$decl['admin_verified']): ?>
      <a href="<?= BASE_URL ?>views/cash/declare.php" class="btn btn-sm btn-outline-success">Update</a>
    <?php endif; ?>
  </div>
<?php elseif ($isToday): ?>
  <div class="alert alert-warning d-flex justify-content-between align-items-center">
    <div><i class="bi bi-exclamation-triangle-fill me-1"></i> You haven't declared cash for today yet.</div>
    <a href="<?= BASE_URL ?>views/cash/declare.php" class="btn btn-sm btn-warning">Declare Now</a>
  </div>
<?php endif; ?>

<!-- ============ TABS / SECTIONS ============ -->
<ul class="nav nav-tabs mb-3" role="tablist">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabSales">Sales (<?= count($sales) ?>)</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabPayments">Debt Payments (<?= count($payments) ?>)</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabDebts">Debts Created (<?= count($debtsCreated) ?>)</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabExpired">Expired Reports (<?= count($myExpired) ?>)</button></li>
</ul>

<div class="tab-content mb-4">

  <!-- Sales -->
  <div class="tab-pane fade show active" id="tabSales">
    <div class="card">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead><tr><th>Time</th><th>Customer</th><th>Product</th><th>Qty</th><th class="text-end">Total</th><th class="text-end">Paid</th><th>Status</th></tr></thead>
            <tbody>
              <?php if (empty($sales)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No sales on this date.</td></tr>
              <?php else: foreach ($sales as $s): ?>
                <tr>
                  <td class="small text-muted"><?= date('H:i', strtotime($s['created_at'])) ?></td>
                  <td><?= e($s['customer_name']) ?></td>
                  <td><?= e($s['product_name']) ?> <span class="text-muted small"><?= e($s['size']) ?></span></td>
                  <td><?= (int)$s['quantity'] ?></td>
                  <td class="text-end"><?= formatMoney($s['total_amount']) ?></td>
                  <td class="text-end"><?= formatMoney($s['amount_paid']) ?></td>
                  <td><?= statusBadge($s['payment_status']) ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Payments -->
  <div class="tab-pane fade" id="tabPayments">
    <div class="card">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead><tr><th>Time</th><th>Customer</th><th class="text-end">Amount</th></tr></thead>
            <tbody>
              <?php if (empty($payments)): ?>
                <tr><td colspan="3" class="text-center text-muted py-4">No debt payments recorded.</td></tr>
              <?php else: foreach ($payments as $p): ?>
                <tr>
                  <td class="small text-muted"><?= date('H:i', strtotime($p['created_at'])) ?></td>
                  <td><?= e($p['customer_name']) ?></td>
                  <td class="text-end"><?= formatMoney($p['amount']) ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Debts created -->
  <div class="tab-pane fade" id="tabDebts">
    <div class="card">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead><tr><th>Time</th><th>Customer</th><th>Phone</th><th>Product</th><th>Qty</th><th class="text-end">Balance</th></tr></thead>
            <tbody>
              <?php if (empty($debtsCreated)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No debts created.</td></tr>
              <?php else: foreach ($debtsCreated as $d): ?>
                <tr>
                  <td class="small text-muted"><?= date('H:i', strtotime($d['created_at'])) ?></td>
                  <td><?= e($d['customer_name']) ?></td>
                  <td class="small"><?= e($d['customer_phone'] ?? '—') ?></td>
                  <td><?= e($d['product_name']) ?></td>
                  <td><?= (int)$d['quantity'] ?></td>
                  <td class="text-end text-danger fw-bold"><?= formatMoney($d['remaining_balance']) ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Expired -->
  <div class="tab-pane fade" id="tabExpired">
    <div class="card">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead><tr><th>Time</th><th>Product</th><th>Qty</th><th>Reason</th><th>Status</th></tr></thead>
            <tbody>
              <?php if (empty($myExpired)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No expired reports submitted.</td></tr>
              <?php else: foreach ($myExpired as $x): ?>
                <tr>
                  <td class="small text-muted"><?= date('H:i', strtotime($x['requested_at'])) ?></td>
                  <td><?= e($x['product_name']) ?></td>
                  <td><?= (int)$x['quantity'] ?></td>
                  <td><span class="badge bg-secondary"><?= e(ExpiredProduct::reasons()[$x['reason']] ?? $x['reason']) ?></span></td>
                  <td>
                    <?php if ($x['status'] === 'pending'): ?><span class="badge bg-warning">Pending</span>
                    <?php elseif ($x['status'] === 'approved'): ?><span class="badge bg-success">Approved</span>
                    <?php else: ?><span class="badge bg-danger">Rejected</span><?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ============ PAST SHIFTS ============ -->
<div class="card">
  <div class="card-header"><i class="bi bi-clock-history me-1"></i>Past 30 Days — Your Shifts</div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>Date</th><th>Sales</th><th class="text-end">Revenue</th><th class="text-end">Cash Collected</th><th class="no-print">Action</th></tr></thead>
        <tbody>
          <?php if (empty($history)): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">No history in the last 30 days.</td></tr>
          <?php else: foreach ($history as $h): ?>
            <tr class="<?= $h['sale_date'] === $date ? 'table-success' : '' ?>">
              <td><?= formatDate($h['sale_date']) ?> <?= $h['sale_date'] === date('Y-m-d') ? '<span class="badge bg-success ms-1">Today</span>' : '' ?></td>
              <td><?= (int)$h['sales_count'] ?></td>
              <td class="text-end"><?= formatMoney($h['revenue']) ?></td>
              <td class="text-end"><?= formatMoney($h['cash']) ?></td>
              <td class="no-print">
                <a href="?date=<?= e($h['sale_date']) ?>" class="btn btn-sm btn-outline-primary">
                  <i class="bi bi-eye"></i> View
                </a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>