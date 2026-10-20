<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireLogin();

$productModel = new Product();
$keyword = trim($_GET['q'] ?? '');
$products = $keyword !== '' ? $productModel->search($keyword) : $productModel->all('product_name ASC');

$pageTitle = 'Products';
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <form method="GET" class="d-flex" style="max-width:350px">
    <input type="text" name="q" class="form-control me-2" placeholder="Search product..." value="<?= e($keyword) ?>">
    <button class="btn btn-outline-secondary">Refresh<i class="bi bi-search"></i></button>
  </form>
  <a href="add.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Product</a>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr>
          <th>#</th><th>Product</th><th>SKU</th><th>Barcode</th><th>Category</th><th>Size</th><th>Cost</th><th>Selling Price</th><th>Stock</th><th>Status</th><th>Actions</th>
        </tr></thead>
        <tbody>
          <?php if (empty($products)): ?>
            <tr><td colspan="11" class="text-center text-muted py-4">No products found.</td></tr>
          <?php else: foreach ($products as $i => $p): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><?= e($p['product_name']) ?></td>
              <td><?= e($p['sku'] ?? '—') ?></td>
              <td><?= e($p['barcode'] ?? '—') ?></td>
              <td><span class="badge bg-warning text-capitalize"><?= str_replace('_',' ', e($p['category'])) ?></span></td>
              <td><?= e($p['size']) ?></td>
              <td><?= formatMoney($p['cost_price']) ?></td>
              <td><?= formatMoney($p['selling_price']) ?></td>
              <td><?= (int) $p['current_stock'] ?></td>
              <td><?= statusBadge($p['status']) ?></td>
              <td>
                <a href="view.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye">view</i></a>
                <a href="edit.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil">edit</i></a>
                <?php if (User::isAdmin()): ?>
                <a href="delete.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-danger btn-delete" data-name="<?= e($p['product_name']) ?>" title="Delete"><i class="bi bi-trash">Delete</i></a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
