<?php
/**
 * repair_schema.php
 * -------------------------------------------------------
 * Scans the database and adds any column/table that
 * earlier phases introduced but is missing from your DB.
 * Safe to run multiple times. Never deletes data.
 *
 * USAGE: visit http://localhost/pharmacy/tools/repair_schema.php
 * Then DELETE this file when done.
 * -------------------------------------------------------
 */
require_once __DIR__ . '/../includes/autoload.php';
require_once __DIR__ . '/../includes/functions.php';
User::requireAdmin();

$db = Database::getConnection();
$dbName = 'pharmacy_ims';
$log = [];
$errors = [];

/** Check if a column exists */
function hasColumn(PDO $db, string $table, string $column): bool {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c"
    );
    $stmt->execute(['t' => $table, 'c' => $column]);
    return (int)$stmt->fetchColumn() > 0;
}

/** Check if a table exists */
function hasTable(PDO $db, string $table): bool {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = :t"
    );
    $stmt->execute(['t' => $table]);
    return (int)$stmt->fetchColumn() > 0;
}

/** Add column if missing */
function ensureColumn(PDO $db, string $table, string $column, string $definition, array &$log, array &$errors) {
    try {
        if (!hasTable($db, $table)) {
            $log[] = "SKIPPED: table `{$table}` does not exist (will create below if needed)";
            return;
        }
        if (hasColumn($db, $table, $column)) {
            $log[] = "OK: {$table}.{$column} already exists";
            return;
        }
        $db->exec("ALTER TABLE `{$table}` ADD COLUMN {$definition}");
        $log[] = "ADDED: {$table}.{$column}";
    } catch (Throwable $e) {
        $errors[] = "FAILED adding {$table}.{$column}: " . $e->getMessage();
    }
}

/** Create table if missing */
function ensureTable(PDO $db, string $table, string $createSQL, array &$log, array &$errors) {
    try {
        if (hasTable($db, $table)) {
            $log[] = "OK: table `{$table}` already exists";
            return;
        }
        $db->exec($createSQL);
        $log[] = "CREATED: table `{$table}`";
    } catch (Throwable $e) {
        $errors[] = "FAILED creating {$table}: " . $e->getMessage();
    }
}

// ============================================================
// 1) SALES — Phase 2.1 + Phase 5.1
// ============================================================
ensureColumn($db, 'sales', 'discount',
    "`discount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `quantity`", $log, $errors);
ensureColumn($db, 'sales', 'cost_price',
    "`cost_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `discount`", $log, $errors);
ensureColumn($db, 'sales', 'total_cost',
    "`total_cost` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `cost_price`", $log, $errors);
ensureColumn($db, 'sales', 'profit',
    "`profit` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `total_amount`", $log, $errors);

// ============================================================
// 2) PRODUCTS — Phase 1 (rename) + Phase 7 (sku/barcode)
// ============================================================
ensureColumn($db, 'products', 'category',
    "`category` ENUM('wines','whiskeys_spirits') NOT NULL DEFAULT 'whiskeys_spirits' AFTER `product_name`", $log, $errors);
ensureColumn($db, 'products', 'size',
    "`size` VARCHAR(50) NULL AFTER `category`", $log, $errors);
ensureColumn($db, 'products', 'cost_price',
    "`cost_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `size`", $log, $errors);
ensureColumn($db, 'products', 'sku',
    "`sku` VARCHAR(50) NULL AFTER `product_name`", $log, $errors);
ensureColumn($db, 'products', 'barcode',
    "`barcode` VARCHAR(80) NULL AFTER `sku`", $log, $errors);

// ============================================================
// 3) USERS — Phase 11A (2FA)
// ============================================================
ensureColumn($db, 'users', 'totp_secret',
    "`totp_secret` VARCHAR(64) NULL AFTER `password`", $log, $errors);
ensureColumn($db, 'users', 'totp_enabled',
    "`totp_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `totp_secret`", $log, $errors);
ensureColumn($db, 'users', 'totp_backup_codes',
    "`totp_backup_codes` TEXT NULL AFTER `totp_enabled`", $log, $errors);

// ============================================================
// 4) TABLES INTRODUCED IN EACH PHASE
// ============================================================
ensureTable($db, 'audit_log', "
CREATE TABLE `audit_log` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    username VARCHAR(50) NULL,
    action VARCHAR(50) NOT NULL,
    entity VARCHAR(50) NULL,
    entity_id INT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_created (created_at),
    INDEX idx_user (user_id),
    INDEX idx_action (action)
) ENGINE=InnoDB", $log, $errors);

ensureTable($db, 'cash_declarations', "
CREATE TABLE `cash_declarations` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    declaration_date DATE NOT NULL,
    expected_cash DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    declared_cash DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    difference DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    notes TEXT NULL,
    admin_verified TINYINT(1) NOT NULL DEFAULT 0,
    verified_by INT NULL,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_user_date (user_id, declaration_date),
    INDEX idx_date (declaration_date)
) ENGINE=InnoDB", $log, $errors);

ensureTable($db, 'period_locks', "
CREATE TABLE `period_locks` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lock_from DATE NOT NULL,
    lock_to DATE NOT NULL,
    reason VARCHAR(255) NULL,
    locked_by INT NULL,
    locked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (locked_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_range (lock_from, lock_to)
) ENGINE=InnoDB", $log, $errors);

ensureTable($db, 'stock_audits', "
CREATE TABLE `stock_audits` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    audit_date DATE NOT NULL,
    product_id INT NOT NULL,
    system_qty INT NOT NULL,
    physical_qty INT NOT NULL,
    difference INT NOT NULL,
    reason VARCHAR(255) NULL,
    corrected TINYINT(1) NOT NULL DEFAULT 0,
    user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_date (audit_date),
    INDEX idx_product (product_id)
) ENGINE=InnoDB", $log, $errors);

ensureTable($db, 'customer_credit', "
CREATE TABLE `customer_credit` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(150) NOT NULL UNIQUE,
    credit_limit DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB", $log, $errors);

ensureTable($db, 'suppliers', "
CREATE TABLE `suppliers` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_name VARCHAR(150) NOT NULL,
    phone VARCHAR(50) NULL,
    email VARCHAR(120) NULL,
    address VARCHAR(255) NULL,
    notes TEXT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB", $log, $errors);

ensureTable($db, 'purchases', "
CREATE TABLE `purchases` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    invoice_no VARCHAR(80) NULL,
    purchase_date DATE NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    payment_status ENUM('paid','partial','unpaid') NOT NULL DEFAULT 'unpaid',
    notes TEXT NULL,
    user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_date (purchase_date),
    INDEX idx_supplier (supplier_id)
) ENGINE=InnoDB", $log, $errors);

ensureTable($db, 'purchase_items', "
CREATE TABLE `purchase_items` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    purchase_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_cost DECIMAL(12,2) NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
    INDEX idx_purchase (purchase_id)
) ENGINE=InnoDB", $log, $errors);

ensureTable($db, 'price_history', "
CREATE TABLE `price_history` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    old_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    new_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    old_selling DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    new_selling DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    changed_by INT NULL,
    reason VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_product (product_id)
) ENGINE=InnoDB", $log, $errors);

ensureTable($db, 'daily_seals', "
CREATE TABLE `daily_seals` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seal_date DATE NOT NULL UNIQUE,
    sales_hash VARCHAR(64) NOT NULL,
    payments_hash VARCHAR(64) NOT NULL,
    declarations_hash VARCHAR(64) NOT NULL,
    expenses_hash VARCHAR(64) NOT NULL,
    audit_hash VARCHAR(64) NOT NULL,
    master_hash VARCHAR(64) NOT NULL,
    sales_count INT NOT NULL DEFAULT 0,
    sales_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    sealed_by INT NULL,
    sealed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sealed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_seal_date (seal_date)
) ENGINE=InnoDB", $log, $errors);

// ============================================================
// Render results
// ============================================================
$pageTitle = 'Schema Repair';
include __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width:900px">
  <div class="card-header">
    <i class="bi bi-wrench-adjustable me-1"></i> Schema Repair Results
  </div>
  <div class="card-body">

    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <strong><?= count($errors) ?> error(s):</strong>
        <ul class="mb-0 mt-2">
          <?php foreach ($errors as $e): ?><li><code><?= e($e) ?></code></li><?php endforeach; ?>
        </ul>
      </div>
    <?php else: ?>
      <div class="alert alert-success">
        <i class="bi bi-check2-circle me-1"></i>
        <strong>All good.</strong> No errors. Your database now matches the code.
      </div>
    <?php endif; ?>

    <h6 class="mt-3">Change Log</h6>
    <ul class="small mb-0 font-monospace" style="max-height:400px;overflow-y:auto">
      <?php foreach ($log as $line): ?>
        <li><?= e($line) ?></li>
      <?php endforeach; ?>
    </ul>

    <hr>
    <div class="d-flex gap-2">
      <a href="<?= BASE_URL ?>dashboard.php" class="btn btn-primary">
        <i class="bi bi-house"></i> Back to Dashboard
      </a>
      <a href="repair_schema.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-repeat"></i> Re-run
      </a>
    </div>

    <div class="alert alert-warning mt-3 small">
      <strong>Security:</strong> After you're done, <strong>delete</strong> this file
      (<code>tools/repair_schema.php</code>) from the server. It's a one-time repair tool.
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>