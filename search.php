<?php
require_once __DIR__ . '/includes/autoload.php';
require_once __DIR__ . '/includes/functions.php';
User::requireLogin();

$q = trim($_GET['q'] ?? '');
$results = ['products' => [], 'customers' => [], 'sales' => [], 'purchases' => [], 'suppliers' => []];

if ($q !== '') {
    $db = Database::getConnection();
    $like = "%{$q}%";

    // Products
    $stmt = $db->prepare(
        "SELECT * FROM products
         WHERE product_name LIKE :q OR sku LIKE :q OR barcode LIKE :q
         ORDER BY product_name ASC LIMIT 20"
    );
    $stmt->execute(['q' => $like]);
    $results['products'] = $stmt->fetchAll();

    // Customers (distinct names from sales)
    $stmt = $db->prepare(
        "SELECT customer_name,
                COALESCE(SUM(remaining_balance),0) AS debt,
                COUNT(*) AS sales_count
         FROM sales
         WHERE customer_name LIKE :q
         GROUP BY customer_name
         ORDER BY customer_name ASC LIMIT 20"
    );
    $stmt->execute(['q' => $like]);
    $results['customers'] = $stmt->fetchAll();

    // Sales
    $stmt = $db->prepare(
        "SELECT s.*, p.product_name FROM sales s
         JOIN products p ON s.product_id = p.id
         WHERE s.customer_name LIKE :q OR p.product_name LIKE :q
         ORDER BY s.sale_date DESC, s.id DESC LIMIT 20"
    );
    $stmt->execute(['q' => $like]);
    $results['sales'] = $stmt->fetchAll();

    if (User::isAdmin()) {
        // Purchases
        $stmt = $db->prepare(
            "SELECT pu.*, s.supplier_name FROM purchases pu
             JOIN suppliers s ON pu.supplier_id = s.id
             WHERE s.supplier_name LIKE :q OR pu.invoice_no LIKE :q
             ORDER BY pu.purchase_date DESC LIMIT 20"
        );
        $stmt->execute(['q' => $like]);
        $results['purchases'] = $stmt->fetchAll();

        // Suppliers
        $stmt = $db->prepare(
            "SELECT * FROM suppliers WHERE supplier_name LIKE :q OR phone LIKE :q OR email LIKE :q
             ORDER BY supplier_name ASC LIMIT 20"
        );
        $stmt->execute(['q' => $like]);
        $results['suppliers'] = $stmt->fetchAll();
    }
}

$totalResults = array_sum(array_map('count', $results));

$pageTitle = 'Search';
include __DIR__ . '/includes/header.php';
?>

<div class="card mb-3">
  <div class="card-body">
    <form method="GET" class="d-flex gap-2">
      <input type="text" name="q" class="form-control form-control-lg" value="<?= e($q) ?>" placeholder="Search products, customers, sales, purchases..." autofocus>
      <button class="btn btn-primary btn-lg"><i class="bi bi-search"></i> Search</button>
    </form>
  </div>
</div>

<?php if ($q === ''): ?>
  <div class="alert alert-info">
    <i class="bi bi-info-circle me-1"></i>
    Type anything above. Examples: a product name (<em>Jameson</em>), a customer name, a SKU, an invoice number.
  </div>
<?php elseif ($totalResults === 0): ?>
  <div class="alert alert-warning">
    <i class="bi bi-search me-1"></i> No results for "<strong><?= e($q) ?></strong>".
  </div>
<?php else: ?>

  <p class="text-muted mb-3"><strong><?= $totalResults ?></strong> result(s) for "<strong><?= e($q) ?></strong>".</p>

  <?php if (!empty($results['products'])): ?>
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-prescription2 me-1"></i> Products (<?= count($results['products']) ?>)</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead><tr><th>Name</th><th>Category</th><th>Size</th><th>Selling</th><th>Stock</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($results['products'] as $p): ?>
                <tr>
                  <td><strong><?= e($p['product_name']) ?></strong> <?php if ($p['sku']): ?><span class="text-muted small">(<?= e($p['sku']) ?>)</span><?php endif; ?></td>
                  <td><span class="badge bg-secondary text-capitalize"><?= str_replace('_',' ',e($p['category'])) ?></span></td>
                  <td><?= e($p['size']) ?></td>
                  <td><?= formatMoney($p['selling_price']) ?></td>
                  <td><?= (int)$p['current_stock'] ?></td>
                  <td><a href="<?= BASE_URL ?>views/products/view.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!empty($results['customers'])): ?>
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-people-fill me-1"></i> Customers (<?= count($results['customers']) ?>)</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead><tr><th>Name</th><th>Sales</th><th>Current Debt</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($results['customers'] as $c): ?>
                <tr>
                  <td><strong><?= e($c['customer_name']) ?></strong></td>
                  <td><?= (int)$c['sales_count'] ?></td>
                  <td class="<?= $c['debt'] > 0 ? 'text-danger fw-bold' : 'text-muted' ?>"><?= formatMoney($c['debt']) ?></td>
                  <td>
                    <a href="<?= BASE_URL ?>views/reports/customer_statement_pdf.php?customer=<?= urlencode($c['customer_name']) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-file-earmark-text"></i></a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!empty($results['sales'])): ?>
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-bag-check-fill me-1"></i> Sales (<?= count($results['sales']) ?>)</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead><tr><th>Date</th><th>Customer</th><th>Product</th><th>Qty</th><th>Total</th><th>Status</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($results['sales'] as $s): ?>
                <tr>
                  <td><?= formatDate($s['sale_date']) ?></td>
                  <td><?= e($s['customer_name']) ?></td>
                  <td><?= e($s['product_name']) ?></td>
                  <td><?= (int)$s['quantity'] ?></td>
                  <td><?= formatMoney($s['total_amount']) ?></td>
                  <td><?= statusBadge($s['payment_status']) ?></td>
                  <td><a href="<?= BASE_URL ?>views/sales/receipt.php?id=<?= $s['id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-receipt"></i></a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!empty($results['purchases'])): ?>
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-cart-check me-1"></i> Purchases (<?= count($results['purchases']) ?>)</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead><tr><th>Date</th><th>Supplier</th><th>Invoice</th><th>Total</th><th>Balance</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($results['purchases'] as $pu): ?>
                <tr>
                  <td><?= formatDate($pu['purchase_date']) ?></td>
                  <td><?= e($pu['supplier_name']) ?></td>
                  <td><?= e($pu['invoice_no']) ?></td>
                  <td><?= formatMoney($pu['total_amount']) ?></td>
                  <td class="text-danger"><?= formatMoney($pu['balance']) ?></td>
                  <td><a href="<?= BASE_URL ?>views/purchases/view.php?id=<?= $pu['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!empty($results['suppliers'])): ?>
    <div class="card mb-3">
      <div class="card-header"><i class="bi bi-truck me-1"></i> Suppliers (<?= count($results['suppliers']) ?>)</div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead><tr><th>Name</th><th>Phone</th><th>Email</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($results['suppliers'] as $sp): ?>
                <tr>
                  <td><strong><?= e($sp['supplier_name']) ?></strong></td>
                  <td><?= e($sp['phone']) ?></td>
                  <td><?= e($sp['email']) ?></td>
                  <td><a href="<?= BASE_URL ?>views/suppliers/view.php?id=<?= $sp['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endif; ?>

<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>