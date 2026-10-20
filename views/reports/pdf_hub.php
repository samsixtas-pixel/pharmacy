<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireLogin();
AuditLog::record('reports.pdf_hub', null, null, 'Opened PDF reports hub');

$pageTitle = 'PDF Reports Hub';
include __DIR__ . '/../../includes/header.php';

$pdfs = [
  ['title' => 'Monthly Sales PDF', 'desc' => 'Sales over a chosen month range', 'icon' => 'bi-file-earmark-pdf', 'bg' => '#f11818', 'link' => 'sales_pdf.php'],
  ['title' => 'Balance Sheet PDF', 'desc' => 'Income statement, balance sheet and trial balance', 'icon' => 'bi-journal-text', 'bg' => '#A8680A', 'link' => 'balance_sheet_pdf.php'],
  ['title'=>'Trial Balance (Live)','desc'=>'Real-time debit vs credit verification','icon'=>'bi-journal-check', 'bg' => '#eac12a','link'=>'trial_balance.php'],
  ['title' => 'Daily Handover PDF', 'desc' => 'Shift summary with cash and notes', 'icon' => 'bi-file-earmark-check', 'bg' => '#118057', 'link' => 'handover_pdf.php'],
  ['title' => 'Customer Statement PDF', 'desc' => 'Statements for one customer', 'icon' => 'bi-person-lines-fill', 'bg' => '#B91C1C', 'link' => 'customer_statement_pdf.php'],
  ['title' => 'Box Report PDF', 'desc' => 'Inventory movement and box summary', 'icon' => 'bi-box2', 'bg' => '#2D3E37', 'link' => 'box_report.php'],
  ['title' => 'Daily Reconciliation', 'desc' => 'Cash vs declaration and expected totals', 'icon' => 'bi-cash-coin', 'bg' => '#35ac81', 'link' => 'daily_reconciliation.php'],
];

if (User::isAdmin()) {
  $pdfs[] = ['title' => 'Worker Ledger PDF', 'desc' => 'Per-worker transaction ledger', 'icon' => 'bi-journal-text', 'bg' => '#0D6348', 'link' => 'worker_ledger.php'];
  $pdfs[] = ['title' => 'Daily Seals', 'desc' => 'Daily seal and tamper checks', 'icon' => 'bi-shield-check', 'bg' => '#0D6348', 'link' => 'daily_seals.php'];
}
?>

<div class="row g-3">
  <?php foreach ($pdfs as $p): ?>
    <div class="col-12 col-sm-6 col-lg-4">
      <a href="<?= $p['link'] ?>" class="report-card text-decoration-none" target="_blank">
        <div class="r-icon" style="background:<?= $p['bg'] ?>">
          <i class="bi <?= $p['icon'] ?>"></i>
        </div>
        <div>
          <div class="r-title"><?= $p['title'] ?></div>
          <div class="r-desc"><?= $p['desc'] ?></div>
        </div>
      </a>
    </div>
  <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
