<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

$userModel = new User();
$users = $userModel->all('full_name ASC');

$pageTitle = 'Users';
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-end mb-3">
  <a href="add.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Add User</a>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>#</th><th>Full Name</th><th>Username</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
          <?php if (empty($users)): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No users found.</td></tr>
          <?php else: foreach ($users as $i => $u): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><?= e($u['full_name']) ?></td>
              <td><?= e($u['username']) ?></td>
              <td><span class="badge bg-secondary text-uppercase"><?= e($u['role']) ?></span></td>
              <td><?= statusBadge($u['status']) ?></td>
              <td>
                <a href="edit.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                <a href="delete.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-danger btn-delete" data-name="<?= e($u['username']) ?>" title="Delete"><i class="bi bi-trash"></i></a>
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



