<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireLogin();

$reportModel = new Report();
$keyword = trim($_GET['q'] ?? '');
$rows = $reportModel->boxReport($keyword);

$pageTitle = 'Box Report';
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
  <form method="GET" class="d-flex" style="max-width:350px">
    <input type="text" name="q" class="form-control me-2" placeholder="Search box..." value="<?= e($keyword) ?>">
    <button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
  </form>
  <div>
    <button onclick="window.print()" class="btn btn-outline-primary"><i class="bi bi-printer"></i> Print</button>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back</a>
  </div>
</div>

<div class="card">
  <div class="card-header">Box Report <?= $keyword ? '— filtered by "' . e($keyword) . '"' : '' ?></div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>#</th><th>Box Name</th><th>Current Quantity</th><th>Status</th></tr></thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="4" class="text-center text-muted py-4">No boxes found.</td></tr>
          <?php else: foreach ($rows as $i => $b): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><?= e($b['box_name']) ?></td>
              <td><?= (int) $b['current_quantity'] ?></td>
              <td><?= statusBadge($b['status']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
