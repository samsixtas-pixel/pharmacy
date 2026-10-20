<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

// Avoid any output before headers
while (ob_get_level()) ob_end_clean();

$db = Database::getConnection();
$dbName = 'pharmacy_ims';

$filename = 'backup_' . $dbName . '_' . date('Y-m-d_His') . '.sql';

header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Log the backup event
AuditLog::record('backup.download', null, null, 'Downloaded DB backup: ' . $filename);

// Header comment
echo "-- =====================================================\n";
echo "-- Database Backup: " . $dbName . "\n";
echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
echo "-- By: " . ($_SESSION['full_name'] ?? '') . " (@" . ($_SESSION['username'] ?? '') . ")\n";
echo "-- =====================================================\n\n";
echo "SET NAMES utf8mb4;\n";
echo "SET FOREIGN_KEY_CHECKS = 0;\n\n";

// Fetch all tables
$tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    // Table structure
    $create = $db->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
    echo "-- ----- Structure for {$table} -----\n";
    echo "DROP TABLE IF EXISTS `{$table}`;\n";
    echo $create['Create Table'] . ";\n\n";

    // Table data
    $rows = $db->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($rows)) {
        echo "-- ----- Data for {$table} -----\n";
        $cols = array_keys($rows[0]);
        $colList = '`' . implode('`,`', $cols) . '`';

        $chunks = array_chunk($rows, 100);
        foreach ($chunks as $chunk) {
            $values = [];
            foreach ($chunk as $row) {
                $vals = [];
                foreach ($row as $v) {
                    if ($v === null) $vals[] = 'NULL';
                    else $vals[] = $db->quote((string)$v);
                }
                $values[] = '(' . implode(',', $vals) . ')';
            }
            echo "INSERT INTO `{$table}` ({$colList}) VALUES\n" . implode(",\n", $values) . ";\n";
        }
        echo "\n";
    }
}

echo "SET FOREIGN_KEY_CHECKS = 1;\n";
echo "-- End of backup\n";
exit;