<?php
require_once __DIR__ . '/includes/autoload.php';
require_once __DIR__ . '/includes/functions.php';
User::requireLogin();

$isAdmin = User::isAdmin();
$uid     = (int) $_SESSION['user_id'];
$today   = date('Y-m-d');

// =====================================================
// Backup detection (must be top of file)
// =====================================================
$lastBackupTs = 0;
$backupDir = __DIR__ . '/backups';
if (is_dir($backupDir)) {
    foreach (glob($backupDir . '/*.sql') as $f) {
        $t = filemtime($f);
        if ($t > $lastBackupTs) $lastBackupTs = $t;
    }
}
$backupHoursAgo = $lastBackupTs ? round((time() - $lastBackupTs) / 3600, 1) : null;

// =====================================================
// STAFF DASHBOARD
// =====================================================
if (!$isAdmin) {
    $db = Database::getConnection();

    $stmt = $db->prepare(
        "SELECT s.*, p.product_name, p.size FROM sales s
         JOIN products p ON s.product_id = p.id
         WHERE s.user_id = :uid AND s.sale_date = :d
         ORDER BY s.id DESC"
    );
    $stmt->execute(['uid' => $uid, 'd' => $today]);
    $mySales = $stmt->fetchAll();

    $myTotal = array_sum(array_column($mySales, 'total_amount'));
    $myCash  = array_sum(array_column($mySales, 'amount_paid'));
    $myCount = count($mySales);

    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(remaining_balance),0) FROM sales
         WHERE user_id = :uid AND sale_date = :d AND payment_status IN ('partial','unpaid')"
    );
    $stmt->execute(['uid' => $uid, 'd' => $today]);
    $myNewDebt = (float) $stmt->fetchColumn();

    $declModel = new CashDeclaration();
    $myDecl = $declModel->findByUserAndDate($uid, $today);

    $productModel = new Product();
    $lowStock = $productModel->getLowStockProducts();

    $pageTitle = 'Dashboard';
    include __DIR__ . '/includes/header.php';
    ?>

    <div class="d-flex flex-wrap gap-2 mb-3">
      <a href="<?= BASE_URL ?>views/sales/add.php" class="btn btn-primary btn-lg">
        <i class="bi bi-plus-circle-fill"></i> New Sale
      </a>
      <a href="<?= BASE_URL ?>views/debts/add.php" class="btn btn-warning btn-lg">
        <i class="bi bi-person-plus-fill"></i> Record Debt
      </a>
      <a href="<?= BASE_URL ?>views/cash/declare.php" class="btn btn-outline-primary btn-lg">
        <i class="bi bi-cash-coin"></i> Declare Cash
      </a>
    </div>

    <?php if ($lowStock): ?>
      <div class="alert alert-warning d-flex align-items-center gap-2 small">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div><strong><?= count($lowStock) ?></strong> product(s) low on stock — tell the boss to refill.</div>
      </div>
    <?php endif; ?>

    <div class="row g-2 mb-3">
      <div class="col-6 col-md-3">
        <div class="stat-card">
          <div class="icon" style="background:#0D6E5D"><i class="bi bi-bag-check-fill"></i></div>
          <div><div class="label">My Sales Today</div><div class="value"><?= $myCount ?></div></div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-card money-card">
          <div class="icon" style="background:#118057"><i class="bi bi-cash-stack"></i></div>
          <div><div class="label">Cash Collected</div><div class="value"><?= formatMoney($myCash) ?></div></div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-card money-card">
          <div class="icon" style="background:#C98012"><i class="bi bi-journal-text"></i></div>
          <div><div class="label">Total Sales Value</div><div class="value"><?= formatMoney($myTotal) ?></div></div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="stat-card money-card">
          <div class="icon" style="background:#B91C1C"><i class="bi bi-person-badge-fill"></i></div>
          <div><div class="label">New Debt Created</div><div class="value"><?= formatMoney($myNewDebt) ?></div></div>
        </div>
      </div>
    </div>

    <?php if (!$myDecl): ?>
      <div class="alert alert-warning d-flex justify-content-between align-items-center">
        <div><i class="bi bi-exclamation-triangle-fill me-1"></i> You haven't declared cash for today yet.</div>
        <a href="<?= BASE_URL ?>views/cash/declare.php" class="btn btn-warning btn-sm">Declare Now</a>
      </div>
    <?php else: ?>
      <div class="alert alert-success d-flex justify-content-between align-items-center">
        <div><i class="bi bi-check2-circle me-1"></i> Cash declared today: <strong><?= formatMoney($myDecl['declared_cash']) ?></strong>
          <?php if (!$myDecl['admin_verified']): ?>(awaiting boss approval)<?php else: ?>(approved)<?php endif; ?>
        </div>
        <a href="<?= BASE_URL ?>views/cash/declare.php" class="btn btn-outline-success btn-sm">Update</a>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-header d-flex justify-content-between">
        <span><i class="bi bi-clock-history me-1"></i>My Sales Today</span>
        <span class="text-muted small"><?= $myCount ?> sale(s) — <?= formatMoney($myTotal) ?></span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead><tr><th>Time</th><th>Product</th><th>Qty</th><th>Total</th><th>Paid</th><th>Balance</th></tr></thead>
            <tbody>
              <?php if (empty($mySales)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No sales yet today. Tap <strong>New Sale</strong> above.</td></tr>
              <?php else: foreach ($mySales as $s): ?>
                <tr>
                  <td class="small"><?= date('H:i', strtotime($s['created_at'])) ?></td>
                  <td><?= e($s['product_name']) ?> <span class="text-muted small"><?= e($s['size']) ?></span></td>
                  <td><?= (int) $s['quantity'] ?></td>
                  <td><?= formatMoney($s['total_amount']) ?></td>
                  <td><?= formatMoney($s['amount_paid']) ?></td>
                  <td class="text-danger"><?= formatMoney($s['remaining_balance']) ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

// =====================================================
// ADMIN DASHBOARD
// =====================================================
$productModel  = new Product();
$saleModel     = new Sale();
$expenseModel  = new Expense();

$totalProducts      = $productModel->countAll();
$totalStock         = $productModel->sumStock();
$totalSales         = $saleModel->countAll();
$paidSales          = $saleModel->countByStatus('paid');
$partialSales       = $saleModel->countByStatus('partial');
$unpaidSales        = $saleModel->countByStatus('unpaid');
$outstandingBalance = $saleModel->sumOutstandingBalance();
$expensesToday      = $expenseModel->sumToday();

$todayRevenue      = $saleModel->sumRevenue($today, $today);
$todayGross        = $saleModel->sumGrossProfit($today, $today);
$todayNetProfit    = $todayGross - $expensesToday;

$thisMonthGross    = $saleModel->sumGrossProfit(date('Y-m-01'), date('Y-m-t'));
$thisMonthExpenses = $expenseModel->sumThisMonth();
$thisMonthNetProfit = $thisMonthGross - $thisMonthExpenses;

$todaySalesCount = $saleModel->countToday();
$todaySalesTotal = $saleModel->sumTodayTotal();
$todaySales      = array_slice($saleModel->getToday(), 0, 5);
$lowStockProducts = $productModel->getLowStockProducts();
$debts            = $saleModel->getOutstandingDebts();
$allTimeGrossProfit = $saleModel->sumGrossProfit();
$allTimeExpenses    = $expenseModel->sumAll();
$allTimeNetProfit   = $allTimeGrossProfit - $allTimeExpenses;

$declModel = new CashDeclaration();
$pendingAlerts = $declModel->pendingAlerts();

// Charts data
$trend = $saleModel->dailyTrend(7);
$maxRevenue = 1;
foreach ($trend as $t) { if ((float) $t['revenue'] > $maxRevenue) $maxRevenue = (float) $t['revenue']; }

$topProducts = $saleModel->topProducts(5, date('Y-m-01'), date('Y-m-d'));
$maxTopRev = 1;
foreach ($topProducts as $tp) { if ((float) $tp['revenue'] > $maxTopRev) $maxTopRev = (float) $tp['revenue']; }

$catSplit = $saleModel->categorySplit(date('Y-m-01'), date('Y-m-d'));

// Auto-backup trigger
$needsAutoBackup = false;
if ($lastBackupTs === 0 || $backupHoursAgo > 48) {
    if (empty($_SESSION['auto_backup_triggered'])) {
        $needsAutoBackup = true;
        $_SESSION['auto_backup_triggered'] = time();
    }
}

$pageTitle = 'Dashboard';
include __DIR__ . '/includes/header.php';
?>

<?php if ($needsAutoBackup): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  Swal.fire({
    icon: 'info',
    title: 'Auto-backup triggered',
    html: 'Your backup is overdue (>48 hours).<br>Downloading a backup automatically in 3 seconds...',
    timer: 3000,
    timerProgressBar: true,
    showConfirmButton: false,
    allowOutsideClick: false,
  }).then(() => {
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.src = '<?= BASE_URL ?>views/settings/backup_download.php?auto=1';
    document.body.appendChild(iframe);
    setTimeout(() => iframe.remove(), 15000);
  });
});
</script>
<?php endif; ?>

<?php if ($lastBackupTs === 0 || $backupHoursAgo > 24): ?>
<div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
  <div>
    <i class="bi bi-hdd-fill me-1"></i>
    <strong>Backup overdue:</strong>
    <?= $lastBackupTs === 0 ? 'No backup has ever been created.' : 'Last backup was ' . $backupHoursAgo . ' hours ago.' ?>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= BASE_URL ?>views/settings/backup.php" class="btn btn-sm btn-outline-warning">Manual Backup</a>
    <a href="<?= BASE_URL ?>views/settings/backup_download.php" class="btn btn-sm btn-warning">
      <i class="bi bi-download"></i> Download Now
    </a>
  </div>
</div>
<?php endif; ?>

<?php if ($pendingAlerts['unverified'] > 0 || $pendingAlerts['discrepancies'] > 0): ?>
<div class="alert alert-danger d-flex justify-content-between align-items-center flex-wrap gap-2">
  <div>
    <i class="bi bi-exclamation-triangle-fill me-1"></i>
    <strong>Attention required:</strong>
    <?php if ($pendingAlerts['unverified'] > 0): ?> <?= $pendingAlerts['unverified'] ?> unverified cash declaration(s).<?php endif; ?>
    <?php if ($pendingAlerts['discrepancies'] > 0): ?> <?= $pendingAlerts['discrepancies'] ?> discrepancy record(s).<?php endif; ?>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= BASE_URL ?>views/cash/index.php" class="btn btn-sm btn-outline-danger">Review Declarations</a>
    <a href="<?= BASE_URL ?>views/reports/suspicious.php" class="btn btn-sm btn-danger">Suspicious Report</a>
  </div>
</div>
<?php endif; ?>

<div class="d-flex flex-wrap gap-2 mb-3">
  <a href="<?= BASE_URL ?>views/reports/pdf_hub.php" class="btn btn-primary btn-sm">
    <i class="bi bi-file-earmark-pdf"></i> PDF Reports Hub
  </a>
  <a href="<?= BASE_URL ?>views/pricing/index.php" class="btn btn-outline-warning btn-sm">
    <i class="bi bi-currency-exchange"></i> Update Prices
  </a>
  <a href="<?= BASE_URL ?>views/settings/backup.php" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-hdd-fill"></i> Backup
  </a>
</div>

<div class="row g-2 mb-3">
  <div class="col-6 col-md-4 col-lg-3"><div class="stat-card"><div class="icon" style="background:#0D6E5D"><i class="bi bi-prescription2"></i></div><div><div class="label">Products</div><div class="value"><?= $totalProducts ?></div></div></div></div>
  <div class="col-6 col-md-4 col-lg-3"><div class="stat-card"><div class="icon" style="background:#C98012"><i class="bi bi-stack"></i></div><div><div class="label">Total Stock</div><div class="value"><?= $totalStock ?></div></div></div></div>
  <div class="col-6 col-md-4 col-lg-3"><div class="stat-card"><div class="icon" style="background:#0D6E5D"><i class="bi bi-bag-check-fill"></i></div><div><div class="label">Total Sales</div><div class="value"><?= $totalSales ?></div></div></div></div>
  <div class="col-6 col-md-4 col-lg-3"><div class="stat-card"><div class="icon" style="background:#1A9E86"><i class="bi bi-sun"></i></div><div><div class="label">Today's Sales</div><div class="value"><?= $todaySalesCount ?> <small>(<?= formatMoney($todaySalesTotal) ?>)</small></div></div></div></div>
  <div class="col-6 col-md-4 col-lg-3"><div class="stat-card"><div class="icon" style="background:#1A9E86"><i class="bi bi-check-circle-fill"></i></div><div><div class="label">Paid</div><div class="value"><?= $paidSales ?></div></div></div></div>
  <div class="col-6 col-md-4 col-lg-3"><div class="stat-card"><div class="icon" style="background:#C98012"><i class="bi bi-hourglass-split"></i></div><div><div class="label">Partial</div><div class="value"><?= $partialSales ?></div></div></div></div>
  <div class="col-6 col-md-4 col-lg-3"><div class="stat-card"><div class="icon" style="background:#C94040"><i class="bi bi-x-circle-fill"></i></div><div><div class="label">Unpaid</div><div class="value"><?= $unpaidSales ?></div></div></div></div>
  <div class="col-12 col-sm-6 col-md-4 col-lg-3"><div class="stat-card money-card"><div class="icon" style="background:#C94040"><i class="bi bi-cash-stack"></i></div><div><div class="label">Outstanding</div><div class="value"><?= formatMoney($outstandingBalance) ?></div></div></div></div>
  <div class="col-6 col-md-4 col-lg-3"><div class="stat-card"><div class="icon" style="background:#B87C14"><i class="bi bi-exclamation-triangle-fill"></i></div><div><div class="label">Low Stock</div><div class="value"><?= count($lowStockProducts) ?></div></div></div></div>
  <div class="col-12 col-sm-6 col-md-4 col-lg-3"><div class="stat-card money-card"><div class="icon" style="background:#7C5CBF"><i class="bi bi-wallet2"></i></div><div><div class="label">Today Expenses</div><div class="value"><?= formatMoney($expensesToday) ?></div></div></div></div>
  <div class="col-12 col-sm-6 col-md-4 col-lg-3"><div class="stat-card money-card"><div class="icon" style="background:<?= $todayNetProfit >= 0 ? '#0D6E5D' : '#C94040' ?>"><i class="bi bi-graph-up-arrow"></i></div><div><div class="label">Today Net</div><div class="value" style="color:<?= $todayNetProfit >= 0 ? '#0D6E5D' : '#C94040' ?>"><?= formatMoney($todayNetProfit) ?></div></div></div></div>
  <div class="col-12 col-sm-6 col-md-4 col-lg-3"><div class="stat-card money-card"><div class="icon" style="background:<?= $thisMonthNetProfit >= 0 ? '#0D6E5D' : '#C94040' ?>"><i class="bi bi-calendar-check"></i></div><div><div class="label">This Month Net</div><div class="value" style="color:<?= $thisMonthNetProfit >= 0 ? '#0D6E5D' : '#C94040' ?>"><?= formatMoney($thisMonthNetProfit) ?></div></div></div></div>
  <div class="col-12 col-sm-6 col-md-4 col-lg-3"><div class="stat-card money-card"><div class="icon" style="background:<?= $allTimeNetProfit >= 0 ? '#0D6E5D' : '#B91C1C' ?>"><i class="bi bi-graph-up-arrow"></i></div><div><div class="label">All-Time Net</div><div class="value"><?= formatMoney($allTimeNetProfit) ?></div></div></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-sun me-1"></i>Today's Sales (<?= $todaySalesCount ?>)</span>
        <a href="<?= BASE_URL ?>views/sales/index.php" class="btn btn-sm btn-outline-primary">View All</a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead><tr><th>Product</th><th>Qty</th><th>Total</th><th>Paid</th></tr></thead>
            <tbody>
              <?php if (empty($todaySales)): ?>
                <tr><td colspan="4" class="text-center text-muted py-3">No sales today yet.</td></tr>
              <?php else: foreach ($todaySales as $s): ?>
                <tr>
                  <td><?= e($s['product_name']) ?></td>
                  <td><?= (int) $s['quantity'] ?></td>
                  <td><?= formatMoney($s['total_amount']) ?></td>
                  <td><?= formatMoney($s['amount_paid']) ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>Low Stock (<?= count($lowStockProducts) ?>)</span>
        <a href="<?= BASE_URL ?>views/products/index.php?low=1" class="btn btn-sm btn-outline-primary">View All</a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead><tr><th>Product</th><th>Stock</th></tr></thead>
            <tbody>
              <?php if (empty($lowStockProducts)): ?>
                <tr><td colspan="2" class="text-center text-muted py-3">All stock healthy.</td></tr>
              <?php else: foreach (array_slice($lowStockProducts, 0, 5) as $p): ?>
                <tr>
                  <td><?= e($p['product_name']) ?> <span class="text-muted small"><?= e($p['size']) ?></span></td>
                  <td class="text-danger fw-bold"><?= (int) $p['current_stock'] ?></td>
                </tr>
              <?php endforeach; endif; ?>
              <?php if (count($lowStockProducts) > 5): ?>
                <tr><td colspan="2" class="text-center text-muted small py-2">
                  + <?= count($lowStockProducts) - 5 ?> more — <a href="<?= BASE_URL ?>views/products/index.php?low=1">view all</a>
                </td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-person-lines-fill me-1"></i>Outstanding Debts (<?= count($debts) ?>)</span>
        <a href="<?= BASE_URL ?>views/debts/index.php" class="btn btn-sm btn-outline-primary">View All</a>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead><tr><th>Customer</th><th>Balance</th><th>Status</th></tr></thead>
            <tbody>
              <?php if (empty($debts)): ?>
                <tr><td colspan="3" class="text-center text-muted py-3">No outstanding debts.</td></tr>
              <?php else: foreach (array_slice($debts, 0, 5) as $d): ?>
                <tr>
                  <td><?= e($d['customer_name']) ?></td>
                  <td class="text-danger fw-bold"><?= formatMoney($d['remaining_balance']) ?></td>
                  <td><?= statusBadge($d['payment_status']) ?></td>
                </tr>
              <?php endforeach; endif; ?>
              <?php if (count($debts) > 5): ?>
                <tr><td colspan="3" class="text-center text-muted small py-2">
                  + <?= count($debts) - 5 ?> more — <a href="<?= BASE_URL ?>views/debts/index.php">view all</a>
                </td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card">
      <div class="card-header"><i class="bi bi-graph-up me-1"></i>Sales — Last 7 Days</div>
      <div class="card-body">
        <div class="chart-bars">
          <?php foreach ($trend as $t): $w = $maxRevenue > 0 ? ((float) $t['revenue'] / $maxRevenue) * 100 : 0; ?>
            <div class="bar-row">
              <div class="bar-label"><?= date('D d M', strtotime($t['sale_date'])) ?></div>
              <div class="bar-track"><div class="bar-fill" style="width:<?= number_format($w, 2) ?>%"></div></div>
              <div class="bar-value"><?= formatMoney($t['revenue']) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>