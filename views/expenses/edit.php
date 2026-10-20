<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['expense_date'])) {
    PeriodLock::ensureUnlocked($_POST['expense_date'], 'edit an expense in');
}

$expenseModel = new Expense();
$id = (int) ($_GET['id'] ?? 0);
$expense = $expenseModel->find($id);

if (!$expense) {
    header('Location: index.php?error=Expense not found');
    exit;
}

$errors = [];
$old = $expense;
$categories = Expense::categories();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;

    $v = new Validator();
    $v->required($_POST['title'] ?? '', 'Title')
      ->maxLength($_POST['title'] ?? '', 150, 'Title')
      ->required($_POST['category'] ?? '', 'Category')
      ->required($_POST['amount'] ?? '', 'Amount')
      ->numeric($_POST['amount'] ?? '', 'Amount')
      ->minValue($_POST['amount'] ?? 0, 0.01, 'Amount')
      ->required($_POST['expense_date'] ?? '', 'Date');

    if (!array_key_exists($_POST['category'] ?? '', $categories)) {
        $errors[] = 'Invalid category selected.';
    }

    if ($v->fails()) {
        $errors = array_merge($errors, $v->getErrors());
    }

    if (empty($errors)) {
        $ok = $expenseModel->updateExpense(
            $id,
            trim($_POST['title']),
            $_POST['category'],
            (float) $_POST['amount'],
            $_POST['expense_date'],
            trim($_POST['notes'] ?? '')
        );

        if ($ok) {
            AuditLog::record('expense.update', 'expenses', $id, 'Updated expense: ' . trim($_POST['title']));
            header('Location: index.php?success=Expense updated successfully');
            exit;
        }
        $errors[] = 'Failed to update expense. Please try again.';
    }
}

$pageTitle = 'Edit Expense';
include __DIR__ . '/../../includes/header.php';
?>

<div class="card" style="max-width:650px">
  <div class="card-header">Edit Expense</div>
  <div class="card-body">
    <?php if ($errors): ?>
      <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <div class="mb-3">
        <label class="form-label">Title</label>
        <input type="text" name="title" class="form-control" value="<?= e($old['title']) ?>" required>
      </div>

      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Category</label>
          <select name="category" class="form-select" required>
            <?php foreach ($categories as $key => $label): ?>
              <option value="<?= $key ?>" <?= $old['category'] === $key ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Amount (TZS)</label>
          <input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="<?= e((string)$old['amount']) ?>" required>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label">Date</label>
        <input type="date" name="expense_date" class="form-control" value="<?= e($old['expense_date']) ?>" required>
      </div>

      <div class="mb-3">
        <label class="form-label">Notes</label>
        <textarea name="notes" class="form-control" rows="3"><?= e($old['notes']) ?></textarea>
      </div>

      <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Update Expense</button>
      <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
