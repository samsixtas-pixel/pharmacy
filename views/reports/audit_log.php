<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

$auditModel = new AuditLog();
$keyword = trim($_GET['q'] ?? '');
$action = trim($_GET['action'] ?? '');
$userId = (int) ($_GET['user_id'] ?? 0) ?: null;
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');

$rows = $auditModel->search($keyword, $userId, $action, $from ?: null, $to ?: null);
$actions = $auditModel->distinctActions();

$db = Database::getConnection();
$users = $db->query("SELECT id, full_name, username FROM users ORDER BY full_name ASC")->fetchAll();

$pageTitle = 'Audit Log';
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 no-print">
  <form method="GET" class="d-flex gap-2 flex-wrap align-items-center">
    <input type="text" name="q" class="form-control" style="max-width:200px" placeholder="Search details..." value="<?= e($keyword) ?>">
    <select name="action" class="form-select" style="max-width:180px">
      <option value="">All Actions</option>
      <?php foreach ($actions as $item): ?>
        <option value="<?= e($item) ?>" <?= $action === $item ? 'selected' : '' ?>><?= e($item) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="user_id" class="form-select" style="max-width:180px">
      <option value="">All Users</option>
      <?php foreach ($users as $user): ?>
        <option value="<?= (int) $user['id'] ?>" <?= $userId === (int) $user['id'] ? 'selected' : '' ?>><?= e($user['full_name']) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
    <span>to</span>
    <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
    <button class="btn btn-outline-secondary"><i class="bi bi-funnel"></i> Filter</button>
  </form>
  <button onclick="window.print()" class="btn btn-outline-primary"><i class="bi bi-printer"></i> Print</button>
</div>

<div class="card">
  <div class="card-header d-flex justify-content-between">
    <span>Audit Log — <?= count($rows) ?> record(s)</span>
    <span class="text-muted small">Showing the 500 most recent matching entries</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr>
          <th>#</th><th>When</th><th>User</th><th>Action</th><th>Entity</th><th>Details</th><th>IP</th>
        </tr></thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No audit records found.</td></tr>
          <?php else: foreach ($rows as $index => $row): ?>
            <tr>
              <td><?= $index + 1 ?></td>
              <td><?= date('d M Y, H:i:s', strtotime($row['created_at'])) ?></td>
              <td>
                <strong><?= e($row['full_name'] ?? $row['username'] ?? '—') ?></strong><br>
                <span class="text-muted small">@<?= e($row['username'] ?? $row['owner_username'] ?? '') ?></span>
              </td>
              <td><span class="badge bg-secondary"><?= e($row['action']) ?></span></td>
              <td><?= e($row['entity']) ?><?= $row['entity_id'] ? ' #' . (int) $row['entity_id'] : '' ?></td>
              <td><?= e($row['details']) ?></td>
              <td class="text-muted small"><?= e($row['ip_address']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
