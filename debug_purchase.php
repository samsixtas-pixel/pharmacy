<?php
require_once __DIR__ . '/includes/autoload.php';

header('Content-Type: text/plain; charset=utf-8');
echo "=== Purchase Class Diagnostic ===\n\n";

// 1. Does the file exist?
$file = __DIR__ . '/classes/Purchase.php';
echo "1. File path: {$file}\n";
echo "   Exists: " . (file_exists($file) ? "YES" : "NO — this is your problem") . "\n\n";

if (file_exists($file)) {
    echo "2. File size: " . filesize($file) . " bytes\n";
    echo "   Last modified: " . date('Y-m-d H:i:s', filemtime($file)) . "\n\n";

    // 3. Syntax check
    $output = [];
    $return = 0;
    exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $return);
    echo "3. PHP syntax check: " . ($return === 0 ? "PASS ✓" : "FAIL ✗") . "\n";
    foreach ($output as $line) echo "   {$line}\n";
    echo "\n";
}

// 4. Can autoloader find it?
echo "4. Class exists (autoload): " . (class_exists('Purchase') ? "YES ✓" : "NO ✗") . "\n\n";

// 5. Can we instantiate?
if (class_exists('Purchase')) {
    try {
        $p = new Purchase();
        echo "5. new Purchase(): OK ✓\n\n";
    } catch (Throwable $e) {
        echo "5. new Purchase(): FAIL ✗ — " . $e->getMessage() . "\n\n";
    }

    // 6. Can we call countPending?
    try {
        $count = (new Purchase())->countPending();
        echo "6. countPending(): OK ✓ — returned {$count}\n\n";
    } catch (Throwable $e) {
        echo "6. countPending(): FAIL ✗ — " . $e->getMessage() . "\n\n";
    }

    // 7. Can we call allWithSupplier?
    try {
        $rows = (new Purchase())->allWithSupplier();
        echo "7. allWithSupplier(): OK ✓ — " . count($rows) . " rows\n\n";
    } catch (Throwable $e) {
        echo "7. allWithSupplier(): FAIL ✗ — " . $e->getMessage() . "\n\n";
    }

    // 8. Can we call findWithDetails on first row?
    try {
        $rows = (new Purchase())->allWithSupplier();
        if (!empty($rows)) {
            $detail = (new Purchase())->findWithDetails((int)$rows[0]['id']);
            echo "8. findWithDetails(): " . ($detail ? "OK ✓" : "returned false") . "\n\n";
        } else {
            echo "8. findWithDetails(): skipped (no rows to test)\n\n";
        }
    } catch (Throwable $e) {
        echo "8. findWithDetails(): FAIL ✗ — " . $e->getMessage() . "\n\n";
    }
}

// 9. Database structure
echo "9. Database checks:\n";
try {
    $db = Database::getConnection();
    $cols = $db->query("SHOW COLUMNS FROM purchases")->fetchAll(PDO::FETCH_COLUMN);
    echo "   purchases columns: " . implode(", ", $cols) . "\n";
    echo "   Has 'status' column: " . (in_array('status', $cols) ? "YES ✓" : "NO ✗ — run migration") . "\n";
    echo "   Has 'received_at' column: " . (in_array('received_at', $cols) ? "YES ✓" : "NO ✗") . "\n";

    $cols2 = $db->query("SHOW COLUMNS FROM purchase_items")->fetchAll(PDO::FETCH_COLUMN);
    echo "   purchase_items has 'quantity_crates': " . (in_array('quantity_crates', $cols2) ? "YES ✓" : "NO ✗") . "\n";
    echo "   purchase_items has 'pack_size': " . (in_array('pack_size', $cols2) ? "YES ✓" : "NO ✗") . "\n";
} catch (Throwable $e) {
    echo "   DB check FAIL: " . $e->getMessage() . "\n";
}

echo "\n=== End ===\n";