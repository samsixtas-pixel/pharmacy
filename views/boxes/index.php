<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireLogin();

$boxModel = new Box();
$keyword = trim($_GET['q'] ?? '');
$boxes = $keyword !== '' ? $boxModel->search($keyword) : $boxModel->all('box_name ASC');

$pageTitle = 'Box Management';
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <form method="GET" class="d-flex" style="max-width:350px">
    <input type="text" name="q" class="form-control me-2" placeholder="Search box..." value="<?= e($keyword) ?>">
    <button class="btn btn-outline-secondary">Refresh<i class="bi bi-search"></i></button>
  </form>
  <a href="add.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add Box</a>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr>
          <th>#</th><th>Box Name</th><th>Current Quantity</th><th>Status</th><th>Actions</th>
        </tr></thead>
        <tbody>
          <?php if (empty($boxes)): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">No boxes found.</td></tr>
          <?php else: foreach ($boxes as $i => $b): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><?= e($b['box_name']) ?></td>
              <td><?= (int) $b['current_quantity'] ?></td>
              <td><?= statusBadge($b['status']) ?></td>
              <td>
                <a href="edit.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                <?php if (User::isAdmin()): ?>
                <a href="delete.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-outline-danger btn-delete" data-name="<?= e($b['box_name']) ?>" title="Delete"><i class="bi bi-trash"></i></a>
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
