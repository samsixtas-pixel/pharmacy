<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

$ph = new PriceHistory();
$productModel = new Product();

$productId = (int)($_GET['product_id'] ?? 0) ?: null;
$from = trim($_GET['from'] ?? date('Y-m-01'));
$to   = trim($_GET['to'] ?? date('Y-m-d'));

$rows = $ph->search($productId, $from, $to);
$products = $productModel->all('product_name ASC');

$pageTitle = 'Price History';
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 no-print">
  <form method="GET" class="d-flex gap-2 flex-wrap align-items-center">
    <select name="product_id" class="form-select" style="max-width:260px">
      <option value="">All products</option>
      <?php foreach ($products as $p): ?>
        <option value="<?= $p['id'] ?>" <?= $productId === (int)$p['id'] ? 'selected' : '' ?>>
          <?= e($p['product_name']) ?> <?= e($p['size']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
    <span>to</span>
    <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
    <button class="btn btn-outline-secondary"><i class="bi bi-funnel"></i> Filter</button>
  </form>
  <div>
    <button onclick="window.print()" class="btn btn-outline-primary"><i class="bi bi-printer"></i> Print</button>
    <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to Pricing</a>
  </div>
</div>

<div class="card">
  <div class="card-header">Price Changes (<?= count($rows) ?>)</div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr>
          <th>When</th><th>Product</th>
          <th class="text-end">Cost (old → new)</th>
          <th class="text-end">Selling (old → new)</th>
          <th>By</th>
          <th>Reason</th>
        </tr></thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No price changes in this range.</td></tr>
          <?php else: foreach ($rows as $r):
            $costUp   = (float)$r['new_cost'] > (float)$r['old_cost'];
            $costDown = (float)$r['new_cost'] < (float)$r['old_cost'];
            $sellUp   = (float)$r['new_selling'] > (float)$r['old_selling'];
            $sellDown = (float)$r['new_selling'] < (float)$r['old_selling'];
          ?>
            <tr>
              <td class="small"><?= date('d M Y, H:i', strtotime($r['created_at'])) ?></td>
              <td><strong><?= e($r['product_name']) ?></strong> <span class="text-muted small"><?= e($r['size']) ?></span></td>
              <td class="text-end small">
                <?= formatMoney($r['old_cost']) ?> →
                <span class="<?= $costUp ? 'text-danger' : ($costDown ? 'text-success' : 'text-muted') ?> fw-bold">
                  <?= formatMoney($r['new_cost']) ?>
                  <?php if ($costUp): ?><i class="bi bi-arrow-up"></i><?php elseif ($costDown): ?><i class="bi bi-arrow-down"></i><?php endif; ?>
                </span>
              </td>
              <td class="text-end small">
                <?= formatMoney($r['old_selling']) ?> →
                <span class="<?= $sellUp ? 'text-success' : ($sellDown ? 'text-danger' : 'text-muted') ?> fw-bold">
                  <?= formatMoney($r['new_selling']) ?>
                  <?php if ($sellUp): ?><i class="bi bi-arrow-up"></i><?php elseif ($sellDown): ?><i class="bi bi-arrow-down"></i><?php endif; ?>
                </span>
              </td>
              <td class="small"><?= e($r['changed_by_name'] ?? '—') ?></td>
              <td class="small"><?= e($r['reason']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>