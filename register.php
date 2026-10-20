<?php
require_once __DIR__ . '/includes/autoload.php';
require_once __DIR__ . '/includes/functions.php';

if (User::isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$old = ['full_name' => '', 'username' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;
    $userModel = new User();

    $v = new Validator();
    $v->required($_POST['full_name'] ?? '', 'Full name')
      ->maxLength($_POST['full_name'] ?? '', 100, 'Full name')
      ->required($_POST['username'] ?? '', 'Username')
      ->maxLength($_POST['username'] ?? '', 50, 'Username')
      ->required($_POST['password'] ?? '', 'Password')
      ->required($_POST['confirm_password'] ?? '', 'Confirm password');

    if ($v->fails()) {
        $errors = $v->getErrors();
    } elseif (strlen($_POST['password']) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    } elseif ($_POST['password'] !== $_POST['confirm_password']) {
        $errors[] = 'Passwords do not match.';
    } elseif ($userModel->usernameExists(trim($_POST['username']))) {
        $errors[] = 'This username is already taken. Please choose another.';
    } else {
        // Self-registration always creates a 'staff' account.
        // Admin accounts can only be created via the Users management page by an existing Admin.
        $ok = $userModel->addUser(
            trim($_POST['full_name']),
            trim($_POST['username']),
            $_POST['password'],
            'staff'
        );

        if ($ok) {
            header('Location: login.php?success=' . urlencode('Account created successfully. Please log in.'));
            exit;
        }
        $errors[] = 'Failed to create account. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register | <?= APP_NAME ?></title>
<link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>assets/images/favicon.ico">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body>
<div class="login-wrapper">
  <div class="login-card" style="max-width:440px">
    <div class="login-brand">
      <img src="<?= BASE_URL ?>assets/images/logo-transparent.png" alt="Zain Herbal Products" class="login-logo">
      <h4>MAPNGO LOUNGE</h4>
      <p class="text-muted mb-0">Create a new staff account</p>
    </div>

    <?php if ($errors): ?>
      <div class="alert alert-danger py-2"><ul class="mb-0 ps-3"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <form method="POST" action="register.php" novalidate>
      <div class="mb-3">
        <label class="form-label">Full Name</label>
        <input type="text" name="full_name" class="form-control" value="<?= e($old['full_name']) ?>" required autofocus>
      </div>
      <div class="mb-3">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control" value="<?= e($old['username']) ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" minlength="6" required>
        <small class="text-muted">At least 6 characters.</small>
      </div>
      <div class="mb-3">
        <label class="form-label">Confirm Password</label>
        <input type="password" name="confirm_password" class="form-control" minlength="6" required>
      </div>
      <button type="submit" class="btn btn-primary w-100">
        <i class="bi bi-person-plus"></i> Create Account
      </button>
    </form>

    <hr>
    <p class="text-muted small mb-0 text-center">
      Already have an account? <a href="login.php">Sign in</a>
    </p>
    <p class="text-muted small mb-0 text-center mt-1">
      New accounts are created with <strong>Staff</strong> access.
    </p>
  </div>
</div>
</body>
</html>
