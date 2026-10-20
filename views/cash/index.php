<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

$declModel = new CashDeclaration();
$errors = [];
$success = '';
$sealMsg = '';

// ---- Handle confirm receipt ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['op'] ?? '') === 'confirm') {
    $id = (int)($_POST['id'] ?? 0);
    $received = (float)($_POST['received_amount'] ?? 0);
    $note = trim($_POST['admin_note'] ?? '');

    if ($id <= 0) $errors[] = 'Invalid declaration.';
    elseif ($received < 0) $errors[] = 'Received amount cannot be negative.';
    else {
        $res = $declModel->confirmReceipt($id, (int)$_SESSION['user_id'], $received, $note);
        if ($res['ok']) {
            AuditLog::record('cash.confirm', 'cash_declarations', $id,
                'Boss confirmed receipt: ' . number_format($received, 2) .
                ' | day ' . $res['date'] . ' auto-sealed');
            $sealMsg = 'Day ' . formatDate($res['date']) . ' has been auto-sealed.';
            header('Location: index.php?success=' . urlencode('Receipt confirmed.') . '&seal=' . urlencode($sealMsg));
            exit;
        }
        $errors[] = $res['error'];
    }
}

if (!empty($_GET['success'])) $success = $_GET['success'];
if (!empty($_GET['seal']))    $sealMsg = $_GET['seal'];

$from = trim($_GET['from'] ?? date('Y-m-01'));
$to   = trim($_GET['to'] ?? date('Y-m-d'));
$userId = (int)($_GET['user_id'] ?? 0) ?: null;
$onlyDisc = (int)($_GET['only_disc'] ?? 0);
$onlyPending = (int)($_GET['only_pending'] ?? 0);

$rows = $declModel->search($from, $to, $userId, $onlyDisc);
if ($onlyPending) {
    $rows = array_values(array_filter($rows, fn($r) => (int)$r['admin_verified'] === 0));
}

$db = Database::getConnection();
$workers = $db->query("SELECT id, full_name, username FROM users WHERE role='staff' ORDER BY full_name")->fetchAll();

$pageTitle = 'Cash Declarations';
include __DIR__ . '/../../includes/header.php';
?>

<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($sealMsg): ?><div class="alert alert-info"><i class="bi bi-shield-check"></i> <?= e($sealMsg) ?></div><?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 no-print">
  <form method="GET" class="d-flex gap-2 flex-wrap align-items-center">
    <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
    <span>to</span>
    <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
    <select name="user_id" class="form-select" style="max-width:200px">
      <option value="">All Workers</option>
      <?php foreach ($workers as $w): ?>
        <option value="<?= $w['id'] ?>" <?= $userId === (int)$w['id'] ? 'selected' : '' ?>><?= e($w['full_name']) ?></option>
      <?php endforeach; ?>
    </select>
    <label class="mb-0 d-flex align-items-center gap-1">
      <input type="checkbox" name="only_pending" value="1" <?= $onlyPending ? 'checked' : '' ?>> Pending only
    </label>
    <label class="mb-0 d-flex align-items-center gap-1">
      <input type="checkbox" name="only_disc" value="1" <?= $onlyDisc ? 'checked' : '' ?>> Discrepancies only
    </label>
    <button class="btn btn-outline-secondary"><i class="bi bi-funnel"></i> Filter</button>
  </form>
  <button onclick="window.print()" class="btn btn-outline-primary"><i class="bi bi-printer"></i> Print</button>
</div>

<div class="card">
  <div class="card-header">
    Cash Declarations <span class="text-muted small ms-2"><?= count($rows) ?> record(s)</span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr>
          <th>Date</th><th>Worker</th><th class="text-end">Opening</th>
          <th class="text-end">Expected</th>
          <th class="text-end">Cash In Hand</th><th class="text-end">Mobile</th>
          <th class="text-end">Total Declared</th>
          <th class="text-end">Difference</th>
          <th>Reason</th>
          <th>Status</th>
          <th class="no-print">Action</th>
        </tr></thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="11" class="text-center text-muted py-4">No declarations found.</td></tr>
          <?php else: foreach ($rows as $r):
            $diff = (float)$r['difference'];
            $diffClass = abs($diff) < 0.01 ? 'text-muted' : ($diff < 0 ? 'text-danger fw-bold' : 'text-success fw-bold');
          ?>
            <tr>
              <td class="small"><?= formatDate($r['declaration_date']) ?></td>
              <td><strong><?= e($r['worker_name']) ?></strong><br><span class="text-muted small">@<?= e($r['worker_username']) ?></span></td>
              <td class="text-end small"><?= formatMoney($r['opening_cash'] ?? 0) ?></td>
              <td class="text-end small"><?= formatMoney($r['expected_cash']) ?></td>
              <td class="text-end fw-bold"><?= formatMoney($r['cash_in_hand'] ?? 0) ?></td>
              <td class="text-end small"><?= formatMoney($r['mobile_money'] ?? 0) ?></td>
              <td class="text-end fw-bold"><?= formatMoney($r['declared_cash']) ?></td>
              <td class="text-end <?= $diffClass ?>">
                <?= $diff == 0 ? '0.00' : ($diff > 0 ? '+ ' : '− ') . formatMoney(abs($diff)) ?>
              </td>
              <td class="small"><?= e($r['difference_reason'] ?? '') ?></td>
              <td>
                <?php if ($r['admin_verified']): ?>
                  <span class="badge bg-success">Confirmed</span><br>
                  <span class="text-muted small">Received: <?= formatMoney($r['received_amount'] ?? 0) ?></span>
                <?php else: ?>
                  <span class="badge bg-warning">Pending</span>
                <?php endif; ?>
              </td>
              <td class="no-print">
                <?php if (!$r['admin_verified']): ?>
                  <button type="button" class="btn btn-sm btn-primary"
                          data-bs-toggle="modal" data-bs-target="#confirmModal"
                          data-id="<?= $r['id'] ?>"
                          data-worker="<?= e($r['worker_name']) ?>"
                          data-date="<?= formatDate($r['declaration_date']) ?>"
                          data-declared="<?= $r['declared_cash'] ?>"
                          data-cash="<?= $r['cash_in_hand'] ?? 0 ?>"
                          data-mobile="<?= $r['mobile_money'] ?? 0 ?>"
                          data-diff="<?= $r['difference'] ?>">
                    <i class="bi bi-check2-circle"></i> Confirm Receipt
                  </button>
                <?php else: ?>
                  <span class="text-muted small">By <?= e($r['verifier_name'] ?? '—') ?><br>
                    <?= $r['received_at'] ? date('d M H:i', strtotime($r['received_at'])) : '' ?>
                  </span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Confirm Receipt Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="op" value="confirm">
        <input type="hidden" name="id" id="confirmId">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-shield-check"></i> Confirm Cash Receipt</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-info small mb-3">
            <i class="bi bi-info-circle"></i> Confirming will auto-seal this day. Once sealed, the day becomes tamper-evident on the <strong>Daily Seals</strong> page.
          </div>

          <p><strong id="confirmWorker"></strong> — <span id="confirmDate"></span></p>
          <table class="table table-sm mb-3">
            <tr><td>Cash in hand</td><td class="text-end" id="confirmCash"></td></tr>
            <tr><td>Mobile money</td><td class="text-end" id="confirmMobile"></td></tr>
            <tr><td><strong>Total declared</strong></td><td class="text-end fw-bold" id="confirmDeclared"></td></tr>
            <tr><td>Difference</td><td class="text-end" id="confirmDiff"></td></tr>
          </table>

          <div class="mb-3">
            <label class="form-label fw-bold">Amount You Actually Received (TZS)</label>
            <input type="number" step="0.01" min="0" name="received_amount" id="receivedAmount"
                   class="form-control form-control-lg" required>
            <small class="text-muted">Usually equals total declared. Change if you received less (e.g. gave change back).</small>
          </div>

          <div class="mb-3">
            <label class="form-label">Admin Note (optional)</label>
            <input type="text" name="admin_note" class="form-control" placeholder="e.g. Received all, gave 5,000 change for tomorrow">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success"><i class="bi bi-shield-check"></i> Confirm &amp; Seal Day</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.getElementById('confirmModal').addEventListener('show.bs.modal', function (event) {
  const btn = event.relatedTarget;
  document.getElementById('confirmId').value = btn.dataset.id;
  document.getElementById('confirmWorker').textContent = btn.dataset.worker;
  document.getElementById('confirmDate').textContent = btn.dataset.date;

  const cash = parseFloat(btn.dataset.cash || 0);
  const mobile = parseFloat(btn.dataset.mobile || 0);
  const declared = parseFloat(btn.dataset.declared || 0);
  const diff = parseFloat(btn.dataset.diff || 0);

  document.getElementById('confirmCash').textContent = cash.toLocaleString('en-US', {minimumFractionDigits:2});
  document.getElementById('confirmMobile').textContent = mobile.toLocaleString('en-US', {minimumFractionDigits:2});
  document.getElementById('confirmDeclared').textContent = declared.toLocaleString('en-US', {minimumFractionDigits:2});
  const diffEl = document.getElementById('confirmDiff');
  diffEl.textContent = (diff === 0 ? '0.00' : (diff > 0 ? '+ ' : '− ') + Math.abs(diff).toLocaleString('en-US', {minimumFractionDigits:2}));
  diffEl.className = 'text-end ' + (Math.abs(diff) < 0.01 ? 'text-muted' : 'text-danger fw-bold');

  document.getElementById('receivedAmount').value = declared.toFixed(2);
});
</script>

<script>
// Phase 6 — Double-confirm before sealing
document.addEventListener('DOMContentLoaded', function () {
  const confirmForm = document.querySelector('#confirmModal form');
  if (!confirmForm) return;

  confirmForm.addEventListener('submit', function (e) {
    if (!e.submitter) return; // ignore if submitted programmatically
    const ok = confirm(
      '🔒 FINAL WARNING\n\n' +
      'This will confirm receipt AND permanently SEAL this day.\n' +
      'Sealed days become tamper-evident and CANNOT be modified.\n\n' +
      'Are you absolutely sure?'
    );
    if (!ok) {
      e.preventDefault();
    }
  });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>