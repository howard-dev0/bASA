<?php
// index.php
require_once __DIR__ . '/config/session.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit();
}

switch ($_SESSION['role']) {
    case 'super_admin':
        header('Location: ' . BASE_URL . '/super_admin/index.php');
        break;
    case 'librarian':
        header('Location: ' . BASE_URL . '/admin/index.php');
        break;
    default:
        header('Location: ' . BASE_URL . '/student/index.php');
        break;
}
exit();