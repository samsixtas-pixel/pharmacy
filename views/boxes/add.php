<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireLogin();

$errors = [];
$old = ['box_name' => '', 'current_quantity' => '', 'description' => '', 'status' => 'active'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;

    $v = new Validator();
    $v->required($_POST['box_name'] ?? '', 'Box name')
      ->maxLength($_POST['box_name'] ?? '', 150, 'Box name')
      ->required($_POST['current_quantity'] ?? '', 'Current quantity')
      ->numeric($_POST['current_quantity'] ?? '', 'Current quantity')
      ->minValue($_POST['current_quantity'] ?? 0, 0, 'Current quantity');

    if ($v->fails()) {
        $errors = $v->getErrors();
    } else {
        $boxModel = new Box();
        $ok = $boxModel->addBox(
            trim($_POST['box_name']),
            (int) $_POST['current_quantity'],
            trim($_POST['description'] ?? ''),
            $_POST['status'] ?? 'active'
        );

        if ($ok) {
            header('Location: index.php?success=Box added successfully');
            exit;
        }
        $errors[] = 'Failed to add box. Please try again.';
    }
}

$pageTitle = 'Add Box';
include __DIR__ . '/../../includes/header.php';
?>

<div class="card" style="max-width:650px">
  <div class="card-header">Add New Box</div>
  <div class="card-body">
    <?php if ($errors): ?>
      <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <div class="mb-3">
        <label class="form-label">Box Name</label>
        <input type="text" name="box_name" class="form-control" value="<?= e($old['box_name']) ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Current Quantity</label>
        <input type="number" min="0" name="current_quantity" class="form-control" value="<?= e((string)$old['current_quantity']) ?>" required>
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
      <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save Box</button>
      <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
