<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

$lockModel = new PeriodLock();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['op'] ?? '') === 'create') {
        $from = trim($_POST['lock_from'] ?? '');
        $to = trim($_POST['lock_to'] ?? '');
        $reason = trim($_POST['reason'] ?? '');

        if (!$from || !$to) {
            $errors[] = 'Both From and To dates are required.';
        } elseif ($to < $from) {
            $errors[] = 'The To date cannot be earlier than the From date.';
        } else {
            $ok = $lockModel->add($from, $to, $reason, (int) $_SESSION['user_id']);
            if ($ok) {
                AuditLog::record('period.lock', 'period_locks', null, "Locked {$from} → {$to}: {$reason}");

                // Auto-seal every day in the range
                $sealModel = new DailySeal();
                $sealedCount = 0;
                $tamperedDays = [];
                $cursor = strtotime($from);
                $stop   = strtotime($to);
            while ($cursor <= $stop) {
                $day = date('Y-m-d', $cursor);
                $res = $sealModel->seal($day, (int)$_SESSION['user_id']);
            if ($res['tampered']) $tamperedDays[] = $day;
            else $sealedCount++;
           $cursor = strtotime('+1 day', $cursor);
          }

    $msg = "Period locked. {$sealedCount} day(s) sealed.";
    if ($tamperedDays) {
        $msg .= ' ⚠ Tampering detected on: ' . implode(', ', $tamperedDays);
    }
    header('Location: period_locks.php?success=' . urlencode($msg));
    exit;
}
            $errors[] = 'Failed to create lock.';
        }
    } elseif (($_POST['op'] ?? '') === 'unlock') {
        $id = (int) ($_POST['lock_id'] ?? 0);
        if ($id > 0) {
            $lockModel->remove($id);
            AuditLog::record('period.unlock', 'period_locks', $id, "Removed lock #{$id}");
            header('Location: period_locks.php?success=' . urlencode('Lock removed.'));
            exit;
        }
    }
}

$locks = $lockModel->allLocks();

$pageTitle = 'Period Locks';
include __DIR__ . '/../../includes/header.php';
?>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><i class="bi bi-lock-fill me-1"></i> Lock a New Period</div>
      <div class="card-body">
        <?php if ($errors): ?>
          <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <div class="alert alert-warning small py-2">
          <strong>What this does:</strong> Once locked, no sale, payment, or expense inside the period can be created, edited, or deleted until you unlock it.
        </div>

        <form method="POST" novalidate>
          <input type="hidden" name="op" value="create">
          <div class="mb-3">
            <label class="form-label">Lock From</label>
            <input type="date" name="lock_from" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Lock To</label>
            <input type="date" name="lock_to" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Reason</label>
            <input type="text" name="reason" class="form-control" placeholder="e.g. Month-end closure June 2026">
          </div>
          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-lock"></i> Lock Period
          </button>
        </form>

        <hr>
        <div class="d-flex flex-wrap gap-2">
          <form method="POST" style="display:inline">
            <input type="hidden" name="op" value="create">
            <input type="hidden" name="lock_from" value="<?= date('Y-m-d') ?>">
            <input type="hidden" name="lock_to" value="<?= date('Y-m-d') ?>">
            <input type="hidden" name="reason" value="Today's closure">
            <button class="btn btn-sm btn-outline-primary">Quick-lock Today</button>
          </form>
          <form method="POST" style="display:inline">
            <input type="hidden" name="op" value="create">
            <input type="hidden" name="lock_from" value="<?= date('Y-m-d', strtotime('monday this week')) ?>">
            <input type="hidden" name="lock_to" value="<?= date('Y-m-d', strtotime('sunday this week')) ?>">
            <input type="hidden" name="reason" value="Weekly closure">
            <button class="btn btn-sm btn-outline-primary">Quick-lock This Week</button>
          </form>
          <form method="POST" style="display:inline">
            <input type="hidden" name="op" value="create">
            <input type="hidden" name="lock_from" value="<?= date('Y-m-01') ?>">
            <input type="hidden" name="lock_to" value="<?= date('Y-m-t') ?>">
            <input type="hidden" name="reason" value="Monthly closure">
            <button class="btn btn-sm btn-outline-primary">Quick-lock This Month</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">Active Locks (<?= count($locks) ?>)</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead><tr><th>#</th><th>From</th><th>To</th><th>Reason</th><th>Locked By</th><th>When</th><th>Action</th></tr></thead>
            <tbody>
              <?php if (empty($locks)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No locks yet.</td></tr>
              <?php else: foreach ($locks as $i => $l): ?>
                <tr>
                  <td><?= $i + 1 ?></td>
                  <td><?= formatDate($l['lock_from']) ?></td>
                  <td><?= formatDate($l['lock_to']) ?></td>
                  <td><?= e($l['reason'] ?: '—') ?></td>
                  <td><?= e($l['locker_name'] ?? '—') ?></td>
                  <td class="text-muted small"><?= date('d M Y H:i', strtotime($l['locked_at'])) ?></td>
                  <td>
                    <form method="POST" onsubmit="return confirm('Remove this lock? Period will become editable again.')">
                      <input type="hidden" name="op" value="unlock">
                      <input type="hidden" name="lock_id" value="<?= $l['id'] ?>">
                      <button class="btn btn-sm btn-outline-danger"><i class="bi bi-unlock"></i> Unlock</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
