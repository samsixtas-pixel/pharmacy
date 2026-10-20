<?php
require_once __DIR__ . '/includes/autoload.php';

$userModel = new User();
$userModel->destroySession();

header('Location: login.php');
exit;
