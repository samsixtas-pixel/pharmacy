<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirm = trim($_POST['confirm_word'] ?? '');
    $file = $_FILES['sqlfile'] ?? null;

    if ($confirm !== 'RESTORE') $errors[] = 'You must type RESTORE exactly to proceed.';
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) $errors[] = 'Please upload a valid .sql file.';
    elseif (!preg_match('/\.sql$/i', $file['name'])) $errors[] = 'Only .sql files are allowed.';

    if (!$errors) {
        // Safety: save a snapshot of current DB first
        $backupDir = __DIR__ . '/../../backups';
        if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
        $preRestore = $backupDir . '/pre_restore_' . date('Y-m-d_His') . '.sql';

        $db = Database::getConnection();
        $fp = fopen($preRestore, 'w');
        fwrite($fp, "-- Pre-restore snapshot\nSET FOREIGN_KEY_CHECKS=0;\n\n");
        foreach ($db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN) as $table) {
            $create = $db->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
            fwrite($fp, "DROP TABLE IF EXISTS `{$table}`;\n" . $create['Create Table'] . ";\n\n");
            $rows = $db->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
            if ($rows) {
                $cols = '`' . implode('`,`', array_keys($rows[0])) . '`';
                foreach (array_chunk($rows, 100) as $chunk) {
                    $vals = [];
                    foreach ($chunk as $row) {
                        $line = [];
                        foreach ($row as $v) $line[] = $v === null ? 'NULL' : $db->quote((string)$v);
                        $vals[] = '(' . implode(',', $line) . ')';
                    }
                    fwrite($fp, "INSERT INTO `{$table}` ({$cols}) VALUES\n" . implode(",\n", $vals) . ";\n");
                }
            }
        }
        fclose($fp);

        // Run the restore
        $sql = file_get_contents($file['tmp_name']);
        if ($sql === false || strlen($sql) < 10) {
            $errors[] = 'Could not read the uploaded file.';
        } else {
            try {
                // Split on ";\n" heuristically but skip within strings — simpler: rely on PDO::exec for the whole thing
                // MySQL PDO can execute multi-statements if we set ATTR_EMULATE_PREPARES
                $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
                $db->exec($sql);
                $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

                AuditLog::record('backup.restore', null, null, 'Restored from ' . $file['name'] . ' (pre-restore snapshot: ' . basename($preRestore) . ')');
                $success = 'Database restored successfully from ' . $file['name'] . '. Pre-restore snapshot saved.';
            } catch (Throwable $e) {
                $errors[] = 'Restore failed: ' . $e->getMessage() . ' — a snapshot was saved at ' . basename($preRestore);
            }
        }
    }
}

$pageTitle = 'Restore from Backup';
include __DIR__ . '/../../includes/header.php';
?>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header" style="color:#B91C1C"><i class="bi bi-exclamation-octagon-fill me-1"></i> DANGER ZONE</div>
      <div class="card-body">
        <div class="alert alert-danger">
          <strong>Read carefully:</strong>
          <ul class="mb-0 mt-2">
            <li>Restoring will <strong>overwrite</strong> the entire current database.</li>
            <li>A safety snapshot will be saved <strong>before</strong> restore so you can undo.</li>
            <li>Only restore from a file you personally created and trust.</li>
          </ul>
        </div>

        <?php if ($errors): ?><div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>

        <form method="POST" enctype="multipart/form-data" novalidate>
          <div class="mb-3">
            <label class="form-label">Backup File (.sql)</label>
            <input type="file" name="sqlfile" accept=".sql" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Type <code>RESTORE</code> to confirm</label>
            <input type="text" name="confirm_word" class="form-control" placeholder="RESTORE" required>
          </div>
          <button class="btn btn-danger"><i class="bi bi-arrow-counterclockwise"></i> Restore Database</button>
          <a href="backup.php" class="btn btn-outline-secondary">Cancel</a>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card">
      <div class="card-header">Best Practice</div>
      <div class="card-body text-muted small">
        <p>1. Backup before every restore — always.</p>
        <p>2. Only restore from files you created.</p>
        <p>3. After a restore, log out and log back in.</p>
        <p class="mb-0">4. If something looks wrong, the <code>pre_restore_*.sql</code> file in <code>/backups</code> undoes it.</p>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>