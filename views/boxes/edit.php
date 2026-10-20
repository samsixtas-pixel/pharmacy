<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireLogin();

$boxModel = new Box();
$id = (int) ($_GET['id'] ?? 0);
$box = $boxModel->find($id);

if (!$box) {
    header('Location: index.php?error=Box not found');
    exit;
}

$errors = [];
$old = $box;

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
        $ok = $boxModel->updateBox(
            $id,
            trim($_POST['box_name']),
            (int) $_POST['current_quantity'],
            trim($_POST['description'] ?? ''),
            $_POST['status'] ?? 'active'
        );

        if ($ok) {
            header('Location: index.php?success=Box updated successfully');
            exit;
        }
        $errors[] = 'Failed to update box. Please try again.';
    }
}

$pageTitle = 'Edit Box';
include __DIR__ . '/../../includes/header.php';
?>

<div class="card" style="max-width:650px">
  <div class="card-header">Edit Box</div>
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
      <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Update Box</button>
      <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
