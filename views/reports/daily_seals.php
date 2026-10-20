<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

$sealModel = new DailySeal();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = trim($_POST['seal_date'] ?? '');
    if (!$date) {
        $errors[] = 'Date required.';
    } else {
        $res = $sealModel->seal($date, (int)$_SESSION['user_id']);
        AuditLog::record('daily.seal', 'daily_seals', null,
            'Sealed ' . $date . ' — master ' . substr($res['existing']['master_hash'] ?? 'new', 0, 12));
        if ($res['tampered']) {
            header('Location: daily_seals.php?error=' . urlencode('TAMPERING DETECTED on ' . $date . '! Data changed after seal.'));
        } else {
            header('Location: daily_seals.php?success=' . urlencode('Sealed ' . $date));
        }
        exit;
    }
}

$from = trim($_GET['from'] ?? date('Y-m-01'));
$to   = trim($_GET['to'] ?? date('Y-m-d'));

$seals = $sealModel->allSeals($from, $to);

// Live-verify each seal in the list
$verifyMap = [];
foreach ($seals as $s) {
    $v = $sealModel->verify($s['seal_date']);
    $verifyMap[$s['seal_date']] = $v['status'];
}

$pageTitle = 'Daily Seals';
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 no-print">
  <form method="GET" class="d-flex gap-2 align-items-center">
    <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
    <span>to</span>
    <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
    <button class="btn btn-outline-secondary"><i class="bi bi-funnel"></i> Filter</button>
  </form>
  <button onclick="window.print()" class="btn btn-outline-primary"><i class="bi bi-printer"></i> Print</button>
</div>

<div class="alert alert-info">
  <i class="bi bi-shield-lock-fill me-1"></i>
  <strong>What is a daily seal?</strong> A cryptographic fingerprint of the day's data.
  If anyone changes ANY record on a sealed day, the fingerprint changes — and this page shows <span class="badge bg-danger">TAMPERED</span>.
</div>

<?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="card mb-3">
  <div class="card-header">Seal a Specific Day</div>
  <div class="card-body">
    <form method="POST" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label">Date to Seal</label>
        <input type="date" name="seal_date" class="form-control" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
      </div>
      <div class="col-md-3">
        <button class="btn btn-primary"><i class="bi bi-shield-check"></i> Seal This Day</button>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">Seals in Range (<?= count($seals) ?>)</div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr>
          <th>#</th><th>Date</th><th>Sales Count</th><th>Sales Total</th>
          <th>Master Hash (first 16)</th><th>Sealed By</th><th>Status</th>
        </tr></thead>
        <tbody>
          <?php if (empty($seals)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No seals in this range.</td></tr>
          <?php else: foreach ($seals as $i => $s):
            $status = $verifyMap[$s['seal_date']] ?? 'sealed';
            $badge = match($status) {
                'tampered' => '<span class="badge bg-danger">TAMPERED</span>',
                'sealed'   => '<span class="badge bg-success">Verified</span>',
                default    => '<span class="badge bg-secondary">' . $status . '</span>',
            };
          ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><?= formatDate($s['seal_date']) ?></td>
              <td><?= (int)$s['sales_count'] ?></td>
              <td><?= formatMoney($s['sales_total']) ?></td>
              <td class="font-monospace small"><?= substr($s['master_hash'], 0, 16) ?>…</td>
              <td><?= e($s['sealed_by_name'] ?? '—') ?></td>
              <td><?= $badge ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>