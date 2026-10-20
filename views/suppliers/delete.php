<?php
require_once __DIR__ . '/../../includes/autoload.php';
User::requireAdmin();

$id = (int) ($_GET['id'] ?? 0);
$m = new Supplier();
$s = $m->find($id);
if ($s) {
    $m->delete($id);
    AuditLog::record('supplier.delete', 'suppliers', $id, 'Deleted supplier: ' . $s['supplier_name']);
}
header('Location: index.php?success=Supplier deleted successfully');
exit;