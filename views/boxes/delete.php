<?php
require_once __DIR__ . '/../../includes/autoload.php';
User::requireAdmin();

$id = (int) ($_GET['id'] ?? 0);
$boxModel = new Box();

if ($id > 0) {
    $boxModel->delete($id);
}

header('Location: index.php?success=Box deleted successfully');
exit;
