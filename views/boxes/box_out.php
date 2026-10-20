<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireLogin();

$boxModel = new Box();
$boxOutModel = new BoxOut();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $v = new Validator();
    $v->required($_POST['box_id'] ?? '', 'Box')
      ->required($_POST['quantity'] ?? '', 'Quantity')
      ->numeric($_POST['quantity'] ?? '', 'Quantity')
      ->minValue($_POST['quantity'] ?? 0, 1, 'Quantity')
      ->required($_POST['transaction_date'] ?? '', 'Date');

    if ($v->fails()) {
        $errors = $v->getErrors();
    } else {
        $box = $boxModel->find((int) $_POST['box_id']);
        if ($box && (int) $_POST['quantity'] > (int) $box['current_quantity']) {
            $errors[] = 'Quantity exceeds current box quantity (' . $box['current_quantity'] . ' available).';
        } else {
            $ok = $boxOutModel->record(
                (int) $_POST['box_id'],
                (int) $_POST['quantity'],
                trim($_POST['reason'] ?? ''),
                $_POST['transaction_date'],
                $_SESSION['user_id']
            );

            if ($ok) {
                AuditLog::record(
                    'box.out',
                    'box_out',
                    (int) $boxOutModel->lastInsertId(),
                    'Box Out: -' . (int) $_POST['quantity'] . ' for box #' . (int) $_POST['box_id']
                    . ' — ' . trim($_POST['reason'] ?? '')
                );
                header('Location: box_out.php?success=Box stock removed successfully');
                exit;
            }
            $errors[] = 'Failed to record box out. Please try again.';
        }
    }
}

$boxes = $boxModel->getActiveBoxes();
$history = $boxOutModel->getHistory();

$pageTitle = 'Box Out';
include __DIR__ . '/../../includes/header.php';
?>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">Remove Box Stock (Box Out)</div>
      <div class="card-body">
        <?php if ($errors): ?>
          <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form method="POST" id="paymentForm" data-offline-url="index.php">
          <div class="mb-3">
            <label class="form-label">Box</label>
            <select name="box_id" class="form-select" required>
              <option value="">-- Select Box --</option>
              <?php foreach ($boxes as $b): ?>
                <option value="<?= $b['id'] ?>"><?= e($b['box_name']) ?> (current: <?= (int)$b['current_quantity'] ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Quantity</label>
            <input type="number" min="1" name="quantity" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Date</label>
            <input type="date" name="transaction_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Reason</label>
            <input type="text" name="reason" class="form-control" placeholder="e.g. Used for packing order">
          </div>
          <br><button type="submit" class="btn btn-warning w-100"><i class="bi bi-dash-circle"></i> Record Box Out</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">Box Out History</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead><tr><th>Box</th><th>Quantity</th><th>Reason</th><th>Date</th><th>Recorded By</th></tr></thead>
            <tbody>
              <?php if (empty($history)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No box-out records yet.</td></tr>
              <?php else: foreach ($history as $h): ?>
                <tr>
                  <td><?= e($h['item_name']) ?></td>
                  <td><?= (int) $h['quantity'] ?></td>
                  <td><?= e($h['reason']) ?></td>
                  <td><?= formatDate($h['transaction_date']) ?></td>
                  <td><?= e($h['recorded_by']) ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('paymentForm').addEventListener('submit', async function (e) {
  if (navigator.onLine) return;
  e.preventDefault();
  const formData = new URLSearchParams(new FormData(e.target)).toString();
  await window.OfflineQueue.enqueue({ url: 'index.php', body: formData, meta: { type: 'payment' } });
  window.OfflineQueue.exportQueueAsDownload();
  const n = await window.OfflineQueue.count();
  window.OfflineQueue.updateSyncBadge(n, 'pending');
  bootstrap.Modal.getInstance(document.getElementById('payModal')).hide();
  Swal.fire({ icon: 'success', title: 'Saved Offline', timer: 1800, showConfirmButton: false });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
