<?php
require_once __DIR__ . '/includes/autoload.php';

if (User::isLoggedIn()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
