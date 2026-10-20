<?php
require_once __DIR__ . '/../../includes/autoload.php';
User::requireAdmin();

$id = (int) ($_GET['id'] ?? 0);
$productModel = new Product();
$product = $productModel->find($id);

if ($product) {
    $productModel->delete($id);
    AuditLog::record('product.delete', 'products', $id, 'Deleted product: ' . $product['product_name']);
}

header('Location: index.php?success=Product deleted successfully');
exit;
