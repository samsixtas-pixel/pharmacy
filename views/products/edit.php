<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

$productModel = new Product();
$id = (int) ($_GET['id'] ?? 0);
$product = $productModel->find($id);
$settings = new Settings();
$pricesLocked = $settings->isPricesLocked();

if (!$product) {
    header('Location: index.php?error=Product not found');
    exit;
}

$errors = [];
$old = $product;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;

    $v = new Validator();
    $v->required($_POST['product_name'] ?? '', 'Product name')
      ->maxLength($_POST['product_name'] ?? '', 150, 'Product name')
      ->required($_POST['category'] ?? '', 'Category')
      ->required($_POST['cost_price'] ?? '', 'Cost price')
      ->numeric($_POST['cost_price'] ?? '', 'Cost price')
      ->minValue($_POST['cost_price'] ?? 0, 0, 'Cost price')
      ->required($_POST['selling_price'] ?? '', 'Selling price')
      ->numeric($_POST['selling_price'] ?? '', 'Selling price')
      ->minValue($_POST['selling_price'] ?? 0, 0, 'Selling price')
      ->required($_POST['current_stock'] ?? '', 'Current stock')
      ->numeric($_POST['current_stock'] ?? '', 'Current stock')
      ->minValue($_POST['current_stock'] ?? 0, 0, 'Current stock');

    if ($v->fails()) {
        $errors = $v->getErrors();
    } else {
      // Block price changes when locked
      if ($pricesLocked) {
            $newCostCheck = (float)($_POST['cost_price'] ?? 0);
            $newSellCheck = (float)($_POST['selling_price'] ?? 0);
          if ($newCostCheck !== (float)$product['cost_price'] || $newSellCheck !== (float)$product['selling_price']) {
              $errors[] = 'Prices are currently locked. Unlock them in Pricing Control to change prices.';
            }
        }

        $priceHistory = new PriceHistory();
        $oldCost    = (float)$product['cost_price'];
        $oldSelling = (float)$product['selling_price'];
        $newCost    = (float)$_POST['cost_price'];
        $newSelling = (float)$_POST['selling_price'];
        $ok = $productModel->updateProduct(
            $id,
            trim($_POST['product_name']),
            $_POST['category'],
            trim($_POST['size'] ?? ''),
            (float) $_POST['cost_price'],
            (float) $_POST['selling_price'],
            (int) $_POST['current_stock'],
            trim($_POST['description'] ?? ''),
            $_POST['status'] ?? 'active',
            trim($_POST['sku'] ?? '') ?: null,
            trim($_POST['barcode'] ?? '') ?: null,
            (int)($_POST['pack_size'] ?? 1)
        );

        if ($ok) {
            $priceHistory->log(
            $id,
            $oldCost, $newCost,
            $oldSelling, $newSelling,
            (int)$_SESSION['user_id'],
            'Manual edit from Products page'
            );
            AuditLog::record('product.update', 'products', $id, 'Updated product: ' . trim($_POST['product_name']));
            header('Location: index.php?success=Product updated successfully');
            exit;
        }
        $errors[] = 'Failed to update product. Please try again.';
    }
}

$pageTitle = 'Edit Product';
include __DIR__ . '/../../includes/header.php';
?>

<div class="card" style="max-width:650px">
  <div class="card-header">Edit Product</div>
  <div class="card-body">
    <?php if ($errors): ?>
      <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <div class="mb-3">
        <label class="form-label">Product Name</label>
        <input type="text" name="product_name" class="form-control" value="<?= e($old['product_name']) ?>" required>
      </div>

      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">SKU</label>
          <input type="text" name="sku" class="form-control" value="<?= e($old['sku'] ?? '') ?>" maxlength="50" placeholder="Optional unique code">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Barcode</label>
          <input type="text" name="barcode" class="form-control" value="<?= e($old['barcode'] ?? '') ?>" maxlength="80" placeholder="Optional unique barcode">
        </div>
      </div>

      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Category</label>
          <select name="category" class="form-select" required>
            <option value="wines"             <?= $old['category']==='wines'?'selected':'' ?>>Wines</option>
            <option value="whiskeys_spirits" <?= $old['category']==='whiskeys_spirits'?'selected':'' ?>>Whiskeys &amp; Spirits</option>
          </select>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Size (e.g. 750ml, 375ml, 200ml, 1ltr)</label>
          <input type="text" name="size" class="form-control" value="<?= e($old['size']) ?>">
        </div>
      </div>

      <div class="col-md-6 mb-3">
          <label class="form-label">Cost Price (TZS)</label>
          <input type="number" step="0.01" min="0" name="cost_price" class="form-control"
              value="<?= e((string)$old['cost_price']) ?>" required <?= $pricesLocked ? 'disabled' : '' ?>>
      </div>
      <div class="col-md-6 mb-3">
          <label class="form-label">Selling Price (TZS)</label>
          <input type="number" step="0.01" min="0" name="selling_price" class="form-control"
              value="<?= e((string)$old['selling_price']) ?>" required <?= $pricesLocked ? 'disabled' : '' ?>>
      </div>
      <?php if ($pricesLocked): ?>
      <div class="col-12 mb-2">
      <div class="alert alert-warning py-2 small mb-0">
          <i class="bi bi-lock-fill"></i> Prices are locked. Unlock them in <strong>Pricing Control</strong> to change them.
      </div>
      </div>
      <?php endif; ?>
      <div class="mb-3">
        <label class="form-label">Current Stock</label>
        <input type="number" min="0" name="current_stock" class="form-control" value="<?= e((string)$old['current_stock']) ?>" required>
      </div>

      <div class="mb-3">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="3"><?= e($old['description']) ?></textarea>
      </div>

      <div class="mb-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="active" <?= $old['status'] === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="inactive" <?= $old['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
      </div>

      <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Update Product</button>
      <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
