<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

$pageTitle = 'Database Backup';
include __DIR__ . '/../../includes/header.php';
?>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header"><i class="bi bi-hdd-fill me-1"></i> Database Backup</div>
      <div class="card-body">
        <p class="text-muted">
          Download a complete SQL dump of all tables. Store it somewhere safe —
          this file can fully restore your beverage business data if anything happens.
        </p>

        <div class="alert alert-info small">
          <strong>What's included:</strong> users, products, boxes, box movements, stock movements,
          sales, payments, expenses, audit log, cash declarations, period locks, stock audits,
          customers' credit, suppliers, purchases, purchase items, price history.
        </div>

        <a href="backup_download.php" class="btn btn-primary btn-lg">
          <i class="bi bi-download"></i> Download Backup (.sql)
        </a>

        <a href="restore.php" class="btn btn-outline-danger">
          <i class="bi bi-arrow-counterclockwise"></i> Restore from Backup
        </a>

        <hr>
        <h6 class="mt-3">Recommended routine</h6>
        <ul class="text-muted small mb-0">
          <li>Download a backup <strong>at the end of every working day</strong> after closing the shift.</li>
          <li>Save the file with a clear name, e.g. <code>backup_<?= date('Y-m-d') ?>.sql</code>.</li>
          <li>Keep copies in <strong>two places</strong> — e.g. a USB drive + cloud storage (Google Drive).</li>
          <li>Lock the day's period (see <strong>Period Locks</strong>) after backing up.</li>
        </ul>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><i class="bi bi-info-circle me-1"></i> About Backups</div>
      <div class="card-body">
        <p class="small text-muted mb-2">
          The backup file is a plain SQL text file. To restore, use phpMyAdmin → <strong>Import</strong>.
        </p>
        <p class="small text-muted mb-0">
          Filename includes a timestamp so multiple backups never collide.
        </p>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>