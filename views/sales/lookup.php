<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireLogin();

header('Content-Type: application/json');
$code = trim((string) ($_GET['code'] ?? ''));
if ($code === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Code is required']);
    exit;
}

$productModel = new Product();
$product = $productModel->findByCode($code);
if (!$product) {
    http_response_code(404);
    echo json_encode(['error' => 'Product not found']);
    exit;
}

echo json_encode([
    'id' => (int) $product['id'],
    'product_name' => $product['product_name'],
    'sku' => $product['sku'] ?? '',
    'barcode' => $product['barcode'] ?? '',
    'selling_price' => (float) $product['selling_price'],
    'current_stock' => (int) $product['current_stock'],
    'status' => $product['status'],
]);
