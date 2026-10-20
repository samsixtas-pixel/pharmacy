<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireLogin();
AuditLog::record('reports.view', null, null, 'Opened reports hub');

$pageTitle = 'Reports';
include __DIR__ . '/../../includes/header.php';

$reports = [
  ['title'=>'Product Report',   'desc'=>'Beverages with stock & price',    'icon'=>'bi-prescription2',          'bg'=>'#0D6348', 'link'=>'product_report.php'],
  ['title'=>'Box Report',       'desc'=>'Packaging boxes & quantities',    'icon'=>'bi-box2',                   'bg'=>'#2D3E37', 'link'=>'box_report.php'],
  ['title'=>'Box In Report',    'desc'=>'History of incoming boxes',       'icon'=>'bi-box-arrow-in-down-left', 'bg'=>'#0A4A38', 'link'=>'box_in_report.php'],
  ['title'=>'Box Out Report',   'desc'=>'History of outgoing boxes',       'icon'=>'bi-box-arrow-up-right',     'bg'=>'#0A4A38', 'link'=>'box_out_report.php'],
  ['title'=>'Stock In Report',  'desc'=>'Incoming Beverage stock history', 'icon'=>'bi-cart-plus-fill',         'bg'=>'#118057', 'link'=>'stock_in_report.php'],
  ['title'=>'Stock Out Report', 'desc'=>'Outgoing Beverage stock history', 'icon'=>'bi-cart-dash-fill',         'bg'=>'#A8680A', 'link'=>'stock_out_report.php'],
  ['title'=>'Sales Report',     'desc'=>'All sales transactions',          'icon'=>'bi-bag-check-fill',         'bg'=>'#0D6348', 'link'=>'sales_report.php'],
  ['title'=>'Debt Report',      'desc'=>'Outstanding customer balances',   'icon'=>'bi-person-badge-fill',      'bg'=>'#B91C1C', 'link'=>'debt_report.php'],
  ['title'=>'Sales PDF (Range)','desc'=>'Printable professional sales PDF','icon'=>'bi-file-earmark-pdf', 'bg'=>'#0D6348', 'link'=>'sales_pdf.php'],
];

if (User::isAdmin()) {
  $reports[] = ['title'=>'Stock Audit Report','desc'=>'Physical count differences and corrections','icon'=>'bi-clipboard-check','bg'=>'#0D6348','link'=>'stock_audit_report.php'];
  $reports[] = ['title'=>'Expense Report',   'desc'=>'All recorded operating costs', 'icon'=>'bi-wallet2',           'bg'=>'#7C5CBF', 'link'=>'expense_report.php'];
  $reports[] = ['title'=>'Profit & Loss',    'desc'=>'Real net profit / loss engine','icon'=>'bi-graph-up-arrow',    'bg'=>'#0D6348', 'link'=>'profit_loss_report.php'];
  $reports[] = ['title'=>'Balance Sheet PDF','desc'=>'Income statement + balance sheet PDF','icon'=>'bi-journal-text','bg'=>'#A8680A', 'link'=>'balance_sheet_pdf.php'];
  $reports[] = ['title'=>'Audit Log',        'desc'=>'Every action tracked forever', 'icon'=>'bi-shield-lock-fill',  'bg'=>'#B91C1C', 'link'=>'audit_log.php'];
  $reports[] = ['title'=>'Worker Performance','desc'=>'Sales & cash by each worker', 'icon'=>'bi-people-fill',       'bg'=>'#0D6348', 'link'=>'worker_performance.php'];
  $reports[] = ['title'=>'Worker Ledger',    'desc'=>'Full per-worker history',      'icon'=>'bi-journal-text',      'bg'=>'#0D6348', 'link'=>'worker_ledger.php'];
  $reports[] = ['title'=>'Handover PDF',    'desc'=>'Daily shift handover with signature', 'icon'=>'bi-file-earmark-check', 'bg'=>'#118057', 'link'=>'handover_pdf.php'];
  $reports[] = ['title'=>'Daily Cash Recon', 'desc'=>'Expected vs declared cash',    'icon'=>'bi-cash-coin',         'bg'=>'#118057', 'link'=>'daily_reconciliation.php'];
  $reports[] = ['title'=>'Daily Seals',    'desc'=>'Tamper-evident day checksums', 'icon'=>'bi-shield-check',  'bg'=>'#0D6348', 'link'=>'daily_seals.php'];
  $reports[] = ['title'=>'Receivables Aging', 'desc'=>'Who owes 0-30/31-60/90+ days', 'icon'=>'bi-hourglass-split','bg'=>'#B91C1C', 'link'=>'receivables_aging.php'];
  $reports[] = ['title'=>'Suspicious Activity','desc'=>'Below-cost, discounts, deletions', 'icon'=>'bi-exclamation-octagon-fill', 'bg'=>'#B91C1C', 'link'=>'suspicious.php'];
}
?>

<div class="row g-3">
  <?php foreach ($reports as $r): ?>
  <div class="col-12 col-sm-6 col-lg-4">
    <a href="<?= $r['link'] ?>" class="report-card text-decoration-none">
      <div class="r-icon" style="background:<?= $r['bg'] ?>">
        <i class="bi <?= $r['icon'] ?>"></i>
      </div>
      <div>
        <div class="r-title"><?= $r['title'] ?></div>
        <div class="r-desc"><?= $r['desc'] ?></div>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
