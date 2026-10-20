<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

$stockAuditModel = new StockAudit();
$id = (int) ($_GET['id'] ?? 0);
$audit = $stockAuditModel->find($id);
if (!$audit) {
    header('Location: stock_audit_report.php?error=Audit not found');
    exit;
}

if ($audit['corrected']) {
    header('Location: stock_audit_report.php?error=This audit has already been corrected');
    exit;
}

$ok = $stockAuditModel->applyCorrection($id, (int) $_SESSION['user_id']);
if ($ok) {
    AuditLog::record('stock_audit.correct', 'stock_audits', $id, 'Applied physical stock correction to product #' . (int) $audit['product_id']);
    header('Location: stock_audit_report.php?success=Stock correction applied');
} else {
    header('Location: stock_audit_report.php?error=Unable to apply stock correction');
}
exit;
