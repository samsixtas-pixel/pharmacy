<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

$userModel = new User();
$id = (int) ($_GET['id'] ?? 0);
$user = $userModel->find($id);

if (!$user) {
    header('Location: index.php?error=User not found');
    exit;
}

$errors = [];
$old = $user;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = array_merge($old, $_POST);

    $v = new Validator();
    $v->required($_POST['full_name'] ?? '', 'Full name')
      ->required($_POST['username'] ?? '', 'Username')
      ->required($_POST['role'] ?? '', 'Role')
      ->required($_POST['status'] ?? '', 'Status');

    if ($v->fails()) {
        $errors = $v->getErrors();
    } elseif ($userModel->usernameExists(trim($_POST['username']), $id)) {
        $errors[] = 'This username is already taken by another user.';
    } else {
        $ok = $userModel->updateUser(
            $id,
            trim($_POST['full_name']),
            trim($_POST['username']),
            $_POST['role'],
            $_POST['status'],
            !empty($_POST['password']) ? $_POST['password'] : null
        );

        if ($ok) {
            AuditLog::record('user.update', 'users', $id, 'Updated user: ' . trim($_POST['username']));
            header('Location: index.php?success=User updated successfully');
            exit;
        }
        $errors[] = 'Failed to update user. Please try again.';
    }
}

$pageTitle = 'Edit User';
include __DIR__ . '/../../includes/header.php';
?>

<div class="card" style="max-width:600px">
  <div class="card-header">Edit User</div>
  <div class="card-body">
    <?php if ($errors): ?>
      <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <form method="POST" novalidate>
      <div class="mb-3">
        <label class="form-label">Full Name</label>
        <input type="text" name="full_name" class="form-control" value="<?= e($old['full_name']) ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control" value="<?= e($old['username']) ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label">New Password</label>
        <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current password">
      </div>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label">Role</label>
          <select name="role" class="form-select">
            <option value="staff" <?= $old['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
            <option value="admin" <?= $old['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
          </select>
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <option value="active" <?= $old['status'] === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $old['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
          </select>
        </div>
      </div>
      <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Update User</button>
      <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
