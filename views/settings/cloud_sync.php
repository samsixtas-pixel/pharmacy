<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

$cloud = new CloudSync();
$success = '';
$error = '';
$testResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['op'] ?? '') === 'test') {
        $testResult = $cloud->testConnection();
    } elseif (($_POST['op'] ?? '') === 'upload') {
        // Upload the most recent local backup
        $dir = __DIR__ . '/../../backups';
        $files = glob($dir . '/*.sql');
        if (!$files) {
            $error = 'No local backups found. Create one first from the Backup page.';
        } else {
            usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
            $latest = $files[0];
            $res = $cloud->upload($latest);
            if ($res['ok']) {
                AuditLog::record('cloud.upload', null, null, 'Uploaded ' . basename($latest) . ' → ' . $res['path']);
                $success = 'Uploaded ' . basename($latest) . ' to Dropbox (' . $res['path'] . ').';
            } else {
                $error = 'Upload failed: ' . $res['error'];
            }
        }
    }
}

// Local backups list
$dir = __DIR__ . '/../../backups';
$files = is_dir($dir) ? glob($dir . '/*.sql') : [];
usort($files, fn($a, $b) => filemtime($b) - filemtime($a));

$pageTitle = 'Cloud Sync';
include __DIR__ . '/../../includes/header.php';
?>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header"><i class="bi bi-cloud-arrow-up-fill me-1"></i> Dropbox Cloud Sync</div>
      <div class="card-body">
        <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

        <?php if (!$cloud->isConfigured()): ?>
          <div class="alert alert-warning">
            <strong>Not configured.</strong> Set <code>DROPBOX_TOKEN</code> in <code>config/config.php</code>.
            See the setup guide if you need help.
          </div>
        <?php else: ?>
          <div class="alert alert-success">
            <i class="bi bi-check2-circle me-1"></i> Dropbox token is set.
          </div>

          <div class="d-flex flex-wrap gap-2 mb-3">
            <form method="POST" class="d-inline">
              <input type="hidden" name="op" value="test">
              <button class="btn btn-outline-primary"><i class="bi bi-wifi"></i> Test Connection</button>
            </form>
            <form method="POST" class="d-inline">
              <input type="hidden" name="op" value="upload">
              <button class="btn btn-primary"><i class="bi bi-cloud-upload"></i> Upload Latest Backup</button>
            </form>
          </div>

          <?php if ($testResult): ?>
            <?php if ($testResult['ok']): ?>
              <div class="alert alert-success">
                Connected as <strong><?= e($testResult['name']) ?></strong> (<?= e($testResult['email']) ?>)
              </div>
            <?php else: ?>
              <div class="alert alert-danger">Connection failed: <?= e($testResult['error']) ?></div>
            <?php endif; ?>
          <?php endif; ?>
        <?php endif; ?>

        <hr>
        <h6>How the auto-sync works</h6>
        <p class="text-muted small">
          A scheduled task (cron / Task Scheduler) calls <code>auto_backup.php</code> every day at closing time.
          That script creates a local backup AND uploads it to Dropbox. Both files stay — you always have a local copy plus a cloud copy.
        </p>
        <p class="text-muted small mb-0">
          See <strong>INSTALLATION.md</strong> for the exact cron/Task Scheduler command.
        </p>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card">
      <div class="card-header">Local Backups (<?= count($files) ?>)</div>
      <div class="card-body p-0">
        <div class="table-responsive" style="max-height:500px">
          <table class="table table-hover mb-0">
            <thead><tr><th>File</th><th>Size</th><th>Age</th></tr></thead>
            <tbody>
              <?php if (empty($files)): ?>
                <tr><td colspan="3" class="text-center text-muted py-4">No local backups yet.</td></tr>
              <?php else: foreach (array_slice($files, 0, 20) as $f): ?>
                <tr>
                  <td class="small font-monospace"><?= e(basename($f)) ?></td>
                  <td class="small"><?= number_format(filesize($f) / 1024, 1) ?> KB</td>
                  <td class="small text-muted"><?= round((time() - filemtime($f)) / 3600, 1) ?>h</td>
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