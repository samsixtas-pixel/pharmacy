<?php
/**
 * auto_backup.php — run via cron / Task Scheduler
 * Example (Linux):  0 22 * * *  php /var/www/pharmacy/views/settings/auto_backup.php
 * Example (Windows Task Scheduler): daily 22:00 → php.exe auto_backup.php
 * Keeps the last 30 backups.
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once __DIR__ . '/../../includes/autoload.php';

$backupDir = __DIR__ . '/../../backups';
if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);

$db = Database::getConnection();
$dbName = 'pharmacy_ims';
$filename = 'auto_backup_' . $dbName . '_' . date('Y-m-d_His') . '.sql';
$path = $backupDir . '/' . $filename;

$fp = fopen($path, 'w');
fwrite($fp, "-- Auto Backup: " . $dbName . "  " . date('Y-m-d H:i:s') . "\n\n");
fwrite($fp, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");

$tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    $create = $db->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
    fwrite($fp, "DROP TABLE IF EXISTS `{$table}`;\n" . $create['Create Table'] . ";\n\n");

    $rows = $db->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($rows)) {
        $cols = array_keys($rows[0]);
        $colList = '`' . implode('`,`', $cols) . '`';
        foreach (array_chunk($rows, 100) as $chunk) {
            $vals = [];
            foreach ($chunk as $row) {
                $line = [];
                foreach ($row as $v) $line[] = $v === null ? 'NULL' : $db->quote((string)$v);
                $vals[] = '(' . implode(',', $line) . ')';
            }
            fwrite($fp, "INSERT INTO `{$table}` ({$colList}) VALUES\n" . implode(",\n", $vals) . ";\n");
        }
        fwrite($fp, "\n");
    }
}
fwrite($fp, "SET FOREIGN_KEY_CHECKS = 1;\n");
fclose($fp);

// Retention: delete backups older than 30 days
foreach (glob($backupDir . '/auto_backup_*.sql') as $f) {
    if (filemtime($f) < strtotime('-30 days')) unlink($f);
}

// ---- Phase 11B: Cloud upload ----
$cloudMsg = '';
if (defined('CLOUD_SYNC_ENABLED') && CLOUD_SYNC_ENABLED) {
    $cloud = new CloudSync();
    if ($cloud->isConfigured()) {
        $res = $cloud->upload($path, $filename);
        if ($res['ok']) {
            $cloudMsg = " [Cloud: uploaded {$res['path']}]";
        } else {
            $cloudMsg = " [Cloud FAILED: {$res['error']}]";
        }
    } else {
        $cloudMsg = ' [Cloud: not configured]';
    }
}

echo "[OK] Backup created: {$filename}{$cloudMsg}\n";
