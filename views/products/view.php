<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

$productModel = new Product();
$id = (int) ($_GET['id'] ?? 0);
$product = $productModel->find($id);


if (!$product) {
    header('Location: index.php?error=Product not found');
    exit;
}

$pageTitle = 'Product Details';
include __DIR__ . '/../../includes/header.php';
?>

<div class="card" style="max-width:650px">
  <div class="card-header d-flex justify-content-between">
    <span>Product Details</span>
    <a href="edit.php?id=<?= $product['id'] ?>" class="btn btn-sm btn-primary"><i class="bi bi-pencil"></i> Edit</a>
  </div>
  <div class="card-body">
    <table class="table table-borderless mb-0">
      <tr><th width="200">Product Name</th><td><?= e($product['product_name']) ?></td></tr>
      <tr><th>SKU</th><td><?= e($product['sku'] ?? '—') ?></td></tr>
      <tr><th>Barcode</th><td><?= e($product['barcode'] ?? '—') ?></td></tr>
      <tr><th>Category</th><td><span class="badge bg-secondary text-capitalize"><?= str_replace('_',' ', e($product['category'])) ?></span></td></tr>
      <tr><th>Size</th><td><?= e($product['size']) ?></td></tr>
      <tr><th>Cost Price</th><td><?= formatMoney($product['cost_price']) ?></td></tr>
      <tr><th>Selling Price</th><td><?= formatMoney($product['selling_price']) ?></td></tr>
      <tr><th>Current Stock</th><td><?= (int) $product['current_stock'] ?></td></tr>
      <tr><th>Description</th><td><?= nl2br(e($product['description'])) ?></td></tr>
      <tr><th>Status</th><td><?= statusBadge($product['status']) ?></td></tr>
      <tr><th>Added On</th><td><?= formatDate($product['created_at']) ?></td></tr>
    </table>
  </div>
</div>
<a href="index.php" class="btn btn-outline-secondary mt-3"><i class="bi bi-arrow-left"></i> Back to Products</a>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
