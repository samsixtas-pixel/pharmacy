<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

$expModel = new ExpiredProduct();
$errors = [];
$success = '';

// -------- Handle approve / reject --------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $op = $_POST['op'] ?? '';
    $notes = trim($_POST['admin_notes'] ?? '');

    if ($id > 0 && $op === 'approve') {
        if ($expModel->approve($id, (int)$_SESSION['user_id'], $notes)) {
            AuditLog::record('expired.approve', 'expired_products', $id, 'Approved. ' . $notes);
            header('Location: stock_out.php?success=' . urlencode('Approved. Stock reduced and loss recorded.'));
            exit;
        }
        $errors[] = 'Failed to approve.';
    } elseif ($id > 0 && $op === 'reject') {
        if ($expModel->reject($id, (int)$_SESSION['user_id'], $notes)) {
            AuditLog::record('expired.reject', 'expired_products', $id, 'Rejected. ' . $notes);
            header('Location: stock_out.php?success=' . urlencode('Request rejected.'));
            exit;
        }
        $errors[] = 'Failed to reject.';
    }
}

if (!empty($_GET['success'])) $success = $_GET['success'];

$pending = $expModel->search(['status' => 'pending']);
$history = $expModel->search(['status' => 'approved']);
$history = array_slice($history, 0, 30);
$lossThisMonth = $expModel->sumLoss(date('Y-m-01'), date('Y-m-t'));
$lossByReason = $expModel->lossByReason(date('Y-m-01'), date('Y-m-t'));
$reasons = ExpiredProduct::reasons();

$pageTitle = 'Stock Out / Requests';
include __DIR__ . '/../../includes/header.php';
?>

<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

<div class="row g-2 mb-3">
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="icon" style="background:#C98012"><i class="bi bi-hourglass-split"></i></div>
      <div><div class="label">Pending Requests</div><div class="value"><?= count($pending) ?></div></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card money-card">
      <div class="icon" style="background:#B91C1C"><i class="bi bi-graph-down-arrow"></i></div>
      <div><div class="label">This Month Loss</div><div class="value"><?= formatMoney($lossThisMonth) ?></div></div>
    </div>
  </div>
  <div class="col-12 col-md-6">
    <div class="stat-card">
      <div class="icon" style="background:#0D6E5D"><i class="bi bi-pie-chart"></i></div>
      <div style="flex:1">
        <div class="label">Loss by Reason (this month)</div>
        <div style="font-size:.82rem">
          <?php if (empty($lossByReason)): ?>
            <span class="text-muted">No losses this month.</span>
          <?php else: foreach ($lossByReason as $r): ?>
            <div><strong><?= e($reasons[$r['reason']] ?? $r['reason']) ?>:</strong> <?= (int)$r['count'] ?> item(s) → <?= formatMoney($r['loss']) ?></div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Pending requests -->
<div class="card mb-3">
  <div class="card-header" style="color:#C98012">
    <i class="bi bi-hourglass-split me-1"></i>Pending Requests (<?= count($pending) ?>)
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr>
          <th>When</th><th>Worker</th><th>Product</th><th>Qty</th>
          <th>Reason</th><th>Explain</th><th>Est. Loss</th><th class="no-print">Action</th>
        </tr></thead>
        <tbody>
          <?php if (empty($pending)): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">No pending requests. All good!</td></tr>
          <?php else: foreach ($pending as $r): ?>
            <tr>
              <td class="small"><?= date('d M H:i', strtotime($r['requested_at'])) ?></td>
              <td><?= e($r['requester_name'] ?? '—') ?></td>
              <td><?= e($r['product_name']) ?> <span class="text-muted small"><?= e($r['size']) ?></span></td>
              <td><?= (int)$r['quantity'] ?></td>
              <td><span class="badge bg-secondary"><?= e($reasons[$r['reason']] ?? $r['reason']) ?></span></td>
              <td class="small"><?= e($r['notes']) ?></td>
              <td class="text-danger fw-bold"><?= formatMoney((float)$r['cost_at_time'] * (int)$r['quantity']) ?></td>
              <td class="no-print">
                <button type="button" class="btn btn-sm btn-success"
                        data-bs-toggle="modal" data-bs-target="#reviewModal"
                        data-id="<?= $r['id'] ?>" data-op="approve"
                        data-product="<?= e($r['product_name']) ?> x<?= (int)$r['quantity'] ?>">
                  <i class="bi bi-check-lg"></i> Approve
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger"
                        data-bs-toggle="modal" data-bs-target="#reviewModal"
                        data-id="<?= $r['id'] ?>" data-op="reject"
                        data-product="<?= e($r['product_name']) ?> x<?= (int)$r['quantity'] ?>">
                  <i class="bi bi-x"></i> Reject
                </button>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Recent approved -->
<div class="card">
  <div class="card-header"><i class="bi bi-clock-history me-1"></i>Recently Approved</div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>Date</th><th>Worker</th><th>Product</th><th>Qty</th><th>Reason</th><th>Loss</th><th>Reviewed By</th></tr></thead>
        <tbody>
          <?php if (empty($history)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No approvals yet.</td></tr>
          <?php else: foreach ($history as $r): ?>
            <tr>
              <td class="small"><?= date('d M H:i', strtotime($r['reviewed_at'] ?? $r['requested_at'])) ?></td>
              <td><?= e($r['requester_name'] ?? '—') ?></td>
              <td><?= e($r['product_name']) ?></td>
              <td><?= (int)$r['quantity'] ?></td>
              <td><span class="badge bg-secondary"><?= e($reasons[$r['reason']] ?? $r['reason']) ?></span></td>
              <td class="text-danger"><?= formatMoney((float)$r['cost_at_time'] * (int)$r['quantity']) ?></td>
              <td class="small"><?= e($r['reviewer_name'] ?? '—') ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Review modal -->
<div class="modal fade" id="reviewModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" id="reviewForm">
        <div class="modal-header">
          <h5 class="modal-title" id="reviewTitle">Review Request</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="reviewId">
          <input type="hidden" name="op" id="reviewOp">
          <p id="reviewSummary" class="fw-bold"></p>
          <div class="mb-3">
            <label class="form-label">Notes (optional)</label>
            <textarea name="admin_notes" class="form-control" rows="2" placeholder="Reason for approval or rejection"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="reviewSubmit">Confirm</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.getElementById('reviewModal').addEventListener('show.bs.modal', function (event) {
  const btn = event.relatedTarget;
  document.getElementById('reviewId').value = btn.dataset.id;
  document.getElementById('reviewOp').value = btn.dataset.op;
  document.getElementById('reviewSummary').textContent = btn.dataset.product;
  const op = btn.dataset.op;
  document.getElementById('reviewTitle').textContent = op === 'approve' ? 'Approve Request' : 'Reject Request';
  const submit = document.getElementById('reviewSubmit');
  submit.className = 'btn ' + (op === 'approve' ? 'btn-success' : 'btn-danger');
  submit.textContent = op === 'approve' ? 'Approve & Reduce Stock' : 'Reject';
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>