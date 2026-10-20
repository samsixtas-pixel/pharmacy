<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

$errors = [];
$old = ['pack_size' => 1, 'product_name' => '', 'category' => 'whiskeys_spirits', 'size' => '', 'cost_price' => '', 'selling_price' => '', 'current_stock' => '', 'description' => '', 'status' => 'active'];

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
        $productModel = new Product();
       $ok = $productModel->addProduct(
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
            AuditLog::record(
                'product.create',
                'products',
                (int) $productModel->lastInsertId(),
                'Added product: ' . trim($_POST['product_name'])
            );
            header('Location: index.php?success=Product added successfully');
            exit;
        }
        $errors[] = 'Failed to add product. Please try again.';
    }
}

$pageTitle = 'Add Product';
include __DIR__ . '/../../includes/header.php';
?>

<div class="card" style="max-width:650px">
  <div class="card-header">Add New Product</div>
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
        <div class="col-md-4 mb-3">
          <label class="form-label">Category</label>
            <select name="category" class="form-select" required>
              <option value="beers"            <?= $old['category']==='beers'?'selected':'' ?>>Beers</option>
              <option value="wines"            <?= $old['category']==='wines'?'selected':'' ?>>Wines</option>
              <option value="whiskeys_spirits" <?= $old['category']==='whiskeys_spirits'?'selected':'' ?>>Whiskeys &amp; Spirits</option>
              <option value="soft_drinks"      <?= $old['category']==='soft_drinks'?'selected':'' ?>>Soft Drinks</option>
              <option value="energy"           <?= $old['category']==='energy'?'selected':'' ?>>Energy Drinks</option>
              <option value="water"            <?= $old['category']==='water'?'selected':'' ?>>Water</option>
            </select>
        </div>
        <div class="col-md-4 mb-3">
           <label class="form-label">Size (e.g. 750ml, 375ml)</label>
            <input type="text" name="size" class="form-control" value="<?= e($old['size']) ?>">
        </div>
      <div class="col-md-4 mb-3">
            <label class="form-label">Pack Size <small class="text-muted">(units per crate)</small></label>
            <input type="number" min="1" name="pack_size" class="form-control"
                  value="<?= e((string)($old['pack_size'] ?? 1)) ?>">
            <small class="text-muted">20 for beer crates, 6 for wine cases, 1 for single items</small>
      </div>
    </div>

      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Cost Price (TZS)</label>
          <input type="number" step="0.01" min="0" name="cost_price" class="form-control" value="<?= e((string)$old['cost_price']) ?>" required>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Selling Price (TZS)</label>
          <input type="number" step="0.01" min="0" name="selling_price" class="form-control" value="<?= e((string)$old['selling_price']) ?>" required>
        </div>
      </div>

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

      <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Product</button>
      <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
