<?php
require_once __DIR__ . '/../../includes/autoload.php';
require_once __DIR__ . '/../../includes/functions.php';
User::requireAdmin();

$userModel = new User();
$errors = [];
$old = ['full_name' => '', 'username' => '', 'role' => 'staff'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;

    $v = new Validator();
    $v->required($_POST['full_name'] ?? '', 'Full name')
      ->required($_POST['username'] ?? '', 'Username')
      ->required($_POST['password'] ?? '', 'Password')
      ->required($_POST['role'] ?? '', 'Role');

    if ($v->fails()) {
        $errors = $v->getErrors();
    } elseif ($userModel->usernameExists(trim($_POST['username']))) {
        $errors[] = 'This username is already taken.';
    } else {
        $ok = $userModel->addUser(
            trim($_POST['full_name']),
            trim($_POST['username']),
            $_POST['password'],
            $_POST['role']
        );

        if ($ok) {
            AuditLog::record(
                'user.create',
                'users',
                (int) $userModel->lastInsertId(),
                'Created user: ' . trim($_POST['username']) . ' (' . $_POST['role'] . ')'
            );
            header('Location: index.php?success=User added successfully');
            exit;
        }
        $errors[] = 'Failed to add user. Please try again.';
    }
}

$pageTitle = 'Add User';
include __DIR__ . '/../../includes/header.php';
?>

<div class="card" style="max-width:600px">
  <div class="card-header">Add New User</div>
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
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Role</label>
        <select name="role" class="form-select">
          <option value="staff" <?= $old['role'] === 'staff' ? 'selected' : '' ?>>Staff</option>
          <option value="admin" <?= $old['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
        </select>
      </div>
      <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Save User</button>
      <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
