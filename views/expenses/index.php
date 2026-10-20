<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireLogin();

$expenseModel = new Expense();
$keyword  = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');

$expenses = $expenseModel->search($keyword, null, null, $category);
$totalAmount = array_sum(array_column($expenses, 'amount'));
$categories = Expense::categories();

$pageTitle = 'Expenses';
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
  <form method="GET" class="d-flex gap-2 flex-wrap align-items-center">
    <input type="text" name="q" class="form-control" style="max-width:220px" placeholder="Search title..." value="<?= e($keyword) ?>">
    <select name="category" class="form-select" style="max-width:170px">
      <option value="">All Categories</option>
      <?php foreach ($categories as $key => $label): ?>
        <option value="<?= $key ?>" <?= $category === $key ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-outline-secondary"><i class="bi bi-search"></i> Search</button>
  </form>
  <a href="add.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Expense</a>
</div>

<div class="card">
  <div class="card-header d-flex justify-content-between">
    <span>Expenses (Matumizi)</span>
    <span class="text-muted small">Total: <?= formatMoney($totalAmount) ?></span>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr>
          <th>#</th><th>Title</th><th>Category</th><th>Amount</th><th>Date</th><th>Recorded By</th><th>Actions</th>
        </tr></thead>
        <tbody>
          <?php if (empty($expenses)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No expenses found.</td></tr>
          <?php else: foreach ($expenses as $i => $exp): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><?= e($exp['title']) ?></td>
              <td><span class="badge bg-secondary text-capitalize"><?= e($categories[$exp['category']] ?? $exp['category']) ?></span></td>
              <td><?= formatMoney($exp['amount']) ?></td>
              <td><?= formatDate($exp['expense_date']) ?></td>
              <td><?= e($exp['recorded_by'] ?? '—') ?></td>
              <td>
                <a href="edit.php?id=<?= $exp['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                <?php if (User::isAdmin()): ?>
                <a href="delete.php?id=<?= $exp['id'] ?>" class="btn btn-sm btn-outline-danger btn-delete" data-name="<?= e($exp['title']) ?>" title="Delete"><i class="bi bi-trash"></i></a>
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
