<?php
require_once __DIR__ . '/../../includes/autoload.php';
User::requireAdmin();

$id = (int) ($_GET['id'] ?? 0);

if ($id === (int) $_SESSION['user_id']) {
    header('Location: index.php?error=You cannot delete your own account');
    exit;
}

$userModel = new User();
if ($id > 0) {
    $userModel->delete($id);
    AuditLog::record('user.delete', 'users', $id, 'Deleted user ID: ' . $id);
}

header('Location: index.php?success=User deleted successfully');
exit;
