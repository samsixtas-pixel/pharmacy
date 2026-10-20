<?php
/**
 * header.php — MPANGO LOUNGE
 * Role-separated sidebar: staff gets a lean menu; admin gets management tools.
 */
$pageTitle   = $pageTitle  ?? 'Dashboard';
$currentPage = basename($_SERVER['PHP_SELF']);
$currentUri  = $_SERVER['REQUEST_URI'];
$userInitial = strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1));
$isAdmin     = User::isAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>
<link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>assets/images/favicon.ico">
<link rel="manifest" href="<?= BASE_URL ?>manifest.json">
<meta name="theme-color" content="#0D6E5D">
<meta name="csrf-token" content="<?= e(Security::csrfToken()) ?>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=5">
</head>
<body>

<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<div class="wrapper">

  <!-- ═══ SIDEBAR ═══ -->
  <nav class="sidebar" id="sidebar">

    <div class="sidebar-brand">
      <img src="<?= BASE_URL ?>assets/images/logo-transparent.png" alt="<?= APP_NAME ?>" class="brand-logo">
      <div class="brand-text">
        <div class="brand-name">MPANGO <strong>LOUNGE</strong></div>
        <div class="brand-sub"><?= $isAdmin ? 'Management' : 'Sales Desk' ?></div>
      </div>
    </div>

    <?php if (!$isAdmin): ?>
    <!-- ===================== STAFF MENU (7 items) ===================== -->
    <ul class="sidebar-menu">

      <li class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
        <a href="<?= BASE_URL ?>dashboard.php">
          <span class="nav-icon"><i class="bi bi-grid-1x2-fill"></i></span>
          <span>Dashboard</span>
        </a>
      </li>

      <li class="<?= str_contains($currentUri,'/sales') ? 'active' : '' ?>">
        <a href="<?= BASE_URL ?>views/sales/index.php">
          <span class="nav-icon"><i class="bi bi-bag-check-fill"></i></span>
          <span>Sales</span>
        </a>
      </li>

      <li class="<?= str_contains($currentUri,'/debts') ? 'active' : '' ?>">
        <a href="<?= BASE_URL ?>views/debts/index.php">
          <span class="nav-icon"><i class="bi bi-person-badge-fill"></i></span>
          <span>Debts</span>
        </a>
      </li>

      <li class="<?= str_contains($currentUri,'stock_available') ? 'active' : '' ?>">
        <a href="<?= BASE_URL ?>views/stock/stock_available.php">
          <span class="nav-icon"><i class="bi bi-box-seam"></i></span>
          <span>Available Stock</span>
        </a>
      </li>

      <li class="<?= str_contains($currentUri,'/cash/declare') ? 'active' : '' ?>">
        <a href="<?= BASE_URL ?>views/cash/declare.php">
          <span class="nav-icon"><i class="bi bi-cash-coin"></i></span>
          <span>Declare Cash</span>
        </a>
      </li>

      <li class="<?= str_contains($currentUri,'expired') ? 'active' : '' ?>">
        <a href="<?= BASE_URL ?>views/stock/expired.php">
          <span class="nav-icon"><i class="bi bi-exclamation-triangle-fill"></i></span>
          <span>Expired / Damaged</span>
        </a>
      </li>

      <li class="<?= $currentPage === 'my_shift.php' ? 'active' : '' ?>">
        <a href="<?= BASE_URL ?>my_shift.php">
          <span class="nav-icon"><i class="bi bi-person-badge"></i></span>
          <span>My Shift</span>
        </a>
      </li>

    </ul>

    <?php else: ?>
    <!-- ===================== ADMIN MENU ===================== -->
    <ul class="sidebar-menu">

      <li class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
        <a href="<?= BASE_URL ?>dashboard.php">
          <span class="nav-icon"><i class="bi bi-grid-1x2-fill"></i></span>
          <span>Dashboard</span>
        </a>
      </li>

      <li class="<?= str_contains($currentUri,'/products') ? 'active' : '' ?>">
        <a href="<?= BASE_URL ?>views/products/index.php">
          <span class="nav-icon"><i class="bi bi-prescription2"></i></span>
          <span>Products</span>
        </a>
      </li>

      <li class="<?= str_contains($currentUri,'/pricing') ? 'active' : '' ?>">
        <a href="<?= BASE_URL ?>views/pricing/index.php">
          <span class="nav-icon"><i class="bi bi-currency-exchange"></i></span>
          <span>Pricing Control</span>
        </a>
      </li>

      <?php
        $pendingPurchases = 0;
          if (class_exists('Purchase')) {
            try { $pendingPurchases = (new Purchase())->countPending(); }
            catch (Throwable $e) { $pendingPurchases = 0; }
       }
      ?>
      <li class="<?= str_contains($currentUri,'stock/stock_in') || str_contains($currentUri,'/purchases') || str_contains($currentUri,'/suppliers') ? 'active' : '' ?>">
         <a href="<?= BASE_URL ?>views/stock/stock_in.php">
            <span class="nav-icon"><i class="bi bi-cart-plus-fill"></i></span>
            <span>Stock In / Purchases</span>
          <?php if ($pendingPurchases > 0): ?>
          <span class="badge bg-warning ms-auto" style="font-size:.65rem"><?= $pendingPurchases ?></span>
        <?php endif; ?>
        </a>
      </li>

      <?php
        $pendingExpired = 0;
        try { $pendingExpired = (new ExpiredProduct())->pendingCount(); } catch (Throwable $e) {}
      ?>
      <li class="<?= str_contains($currentUri,'stock/stock_out') ? 'active' : '' ?>">
        <a href="<?= BASE_URL ?>views/stock/stock_out.php">
          <span class="nav-icon"><i class="bi bi-cart-dash-fill"></i></span>
          <span>Stock Out / Requests</span>
          <?php if ($pendingExpired > 0): ?>
            <span class="badge bg-danger ms-auto" style="font-size:.65rem"><?= $pendingExpired ?></span>
          <?php endif; ?>
        </a>
      </li>

      <li class="<?= str_contains($currentUri,'/debts') ? 'active' : '' ?>">
        <a href="<?= BASE_URL ?>views/debts/index.php">
          <span class="nav-icon"><i class="bi bi-person-badge-fill"></i></span>
          <span>Debts</span>
        </a>
      </li>

      <li class="<?= str_contains($currentUri,'/expenses') ? 'active' : '' ?>">
        <a href="<?= BASE_URL ?>views/expenses/index.php">
          <span class="nav-icon"><i class="bi bi-wallet2"></i></span>
          <span>Expenses</span>
        </a>
      </li>

      <li class="sidebar-divider">Operations</li>

      <li class="<?= str_contains($currentUri,'daily_reconciliation') || str_contains($currentUri,'period_locks') ? 'active' : '' ?>">
        <a href="<?= BASE_URL ?>views/reports/daily_reconciliation.php">
          <span class="nav-icon"><i class="bi bi-cash-coin"></i></span>
          <span>Cash Reconciliation</span>
        </a>
      </li>

      <li class="<?= str_contains($currentUri,'cash/index') ? 'active' : '' ?>">
        <a href="<?= BASE_URL ?>views/cash/index.php">
          <span class="nav-icon"><i class="bi bi-cash-stack"></i></span>
          <span>Cash Declarations</span>
        </a>
      </li>

      <li class="<?= str_contains($currentUri,'worker_ledger') ? 'active' : '' ?>">
        <a href="<?= BASE_URL ?>views/reports/worker_ledger.php">
          <span class="nav-icon"><i class="bi bi-journal-text"></i></span>
          <span>Worker Ledger</span>
        </a>
      </li>

      <li class="<?= str_contains($currentUri,'worker_performance') ? 'active' : '' ?>">
        <a href="<?= BASE_URL ?>views/reports/worker_performance.php">
          <span class="nav-icon"><i class="bi bi-people-fill"></i></span>
          <span>Worker Performance</span>
        </a>
      </li>

      <li class="sidebar-divider">Reports &amp; PDFs</li>

      <li class="<?= str_contains($currentUri,'pdf_hub') || str_contains($currentUri,'balance_sheet') ? 'active' : '' ?>">
        <a href="<?= BASE_URL ?>views/reports/pdf_hub.php">
          <span class="nav-icon"><i class="bi bi-file-earmark-pdf-fill"></i></span>
          <span>PDF Reports Hub</span>
        </a>
      </li>
      <li class="<?= str_contains($currentUri,'trial_balance') ? 'active' : '' ?>">
        <a href="<?= BASE_URL ?>views/reports/trial_balance.php">
          <span class="nav-icon"><i class="bi bi-journal-check"></i></span>
          <span>Trial Balance</span>
        </a>
      </li>

      <li class="<?= str_contains($currentUri,'daily_seals') ? 'active' : '' ?>">
        <a href="<?= BASE_URL ?>views/reports/daily_seals.php">
          <span class="nav-icon"><i class="bi bi-shield-check"></i></span>
          <span>Daily Seals</span>
        </a>
      </li>

      <li class="<?= str_contains($currentUri,'audit_log') ? 'active' : '' ?>">
        <a href="<?= BASE_URL ?>views/reports/audit_log.php">
          <span class="nav-icon"><i class="bi bi-shield-lock-fill"></i></span>
          <span>Audit Log</span>
        </a>
      </li>

      <li class="<?= str_contains($currentUri,'suspicious') ? 'active' : '' ?>">
        <a href="<?= BASE_URL ?>views/reports/suspicious.php">
          <span class="nav-icon"><i class="bi bi-exclamation-octagon-fill"></i></span>
          <span>Suspicious Activity</span>
        </a>
      </li>

      <li class="sidebar-divider">System</li>

      <li class="<?= str_contains($currentUri,'/users') ? 'active' : '' ?>">
        <a href="<?= BASE_URL ?>views/users/index.php">
          <span class="nav-icon"><i class="bi bi-people-fill"></i></span>
          <span>Users</span>
        </a>
      </li>

      <li class="<?= str_contains($currentUri,'setup_2fa') ? 'active' : '' ?>">
        <a href="<?= BASE_URL ?>views/settings/setup_2fa.php">
          <span class="nav-icon"><i class="bi bi-shield-lock-fill"></i></span>
          <span>Two-Factor Auth</span>
        </a>
      </li>

      <li class="<?= str_contains($currentUri,'/settings/backup') || str_contains($currentUri,'restore') ? 'active' : '' ?>">
        <a href="<?= BASE_URL ?>views/settings/backup.php">
          <span class="nav-icon"><i class="bi bi-hdd-fill"></i></span>
          <span>Backup</span>
        </a>
      </li>

      <li class="<?= str_contains($currentUri,'cloud_sync') ? 'active' : '' ?>">
        <a href="<?= BASE_URL ?>views/settings/cloud_sync.php">
          <span class="nav-icon"><i class="bi bi-cloud-arrow-up-fill"></i></span>
          <span>Cloud Sync</span>
        </a>
      </li>

      <li class="<?= str_contains($currentUri,'offline_log') ? 'active' : '' ?>">
        <a href="<?= BASE_URL ?>views/settings/offline_log.php">
          <span class="nav-icon"><i class="bi bi-cloud-slash"></i></span>
          <span>Offline Log</span>
        </a>
      </li>

      <li class="<?= str_contains($currentUri,'integrity_checker') ? 'active' : '' ?>">
        <a href="<?= BASE_URL ?>views/settings/integrity_checker.php">
          <span class="nav-icon"><i class="bi bi-heart-pulse"></i></span>
          <span>Integrity Check</span>
        </a>
      </li>

    </ul>
    <?php endif; ?>

    <div class="sidebar-footer">
      <a href="<?= BASE_URL ?>logout.php">
        <span class="nav-icon"><i class="bi bi-power"></i></span>
        <span>Logout</span>
      </a>
    </div>

  </nav>

  <!-- ═══ MAIN ═══ -->
  <div class="main">

    <header class="topbar">
      <button class="btn-toggle" id="sidebarToggle" aria-label="Menu">
        <i class="bi bi-list"></i>
      </button>
      <h5 class="topbar-title"><?= e($pageTitle) ?></h5>
      <div id="networkStatus" class="network-status"></div>

      <?php if ($isAdmin): ?>
      <form class="topbar-search d-none d-md-flex" method="GET" action="<?= BASE_URL ?>search.php">
        <i class="bi bi-search"></i>
        <input type="text" name="q" placeholder="Search anything..." autocomplete="off"
               value="<?= isset($_GET['q']) && $currentPage === 'search.php' ? e($_GET['q']) : '' ?>">
      </form>
      <?php endif; ?>

      <?php
        $notifCenter = new NotificationCenter();
        $notifAlerts = $notifCenter->forCurrentUser();
        $notifCount  = $notifCenter->urgentCount($notifAlerts);
      ?>
      <div class="dropdown notif-wrapper">
        <button class="btn-toggle position-relative" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
          <i class="bi bi-bell-fill"></i>
          <?php if ($notifCount > 0): ?>
            <span class="notif-badge"><?= $notifCount > 9 ? '9+' : $notifCount ?></span>
          <?php endif; ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end notif-menu">
          <li class="notif-menu-head">
            <strong>Notifications</strong>
            <?php if (empty($notifAlerts)): ?><span class="text-muted small ms-2">All clear</span><?php endif; ?>
          </li>
          <?php if (empty($notifAlerts)): ?>
            <li class="notif-empty">
              <i class="bi bi-check2-circle text-success"></i>
              Nothing needs your attention right now.
            </li>
          <?php else: foreach ($notifAlerts as $a): ?>
            <li>
              <a class="dropdown-item notif-item notif-<?= e($a['level']) ?>" href="<?= $a['url'] ?>">
                <i class="bi <?= $a['icon'] ?> notif-icon"></i>
                <span><?= e($a['title']) ?></span>
              </a>
            </li>
          <?php endforeach; endif; ?>
        </ul>
      </div>

      <?php
        $lastBackupTs = 0;
        $backupDir = __DIR__ . '/../backups';
        if (is_dir($backupDir)) {
          foreach (glob($backupDir . '/*.sql') as $f) {
            $t = filemtime($f);
            if ($t > $lastBackupTs) $lastBackupTs = $t;
          }
        }
        $backupHoursAgo = $lastBackupTs ? round((time() - $lastBackupTs) / 3600, 1) : null;
      ?>

      <div class="topbar-user">
        <div class="user-avatar"><?= $userInitial ?></div>
        <div class="user-info">
          <span class="user-name"><?= e($_SESSION['full_name'] ?? '') ?></span>
          <span class="user-role"><?= e($_SESSION['role'] ?? '') ?></span>
        </div>
      </div>
    </header>

    <main class="content">
