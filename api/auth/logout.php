<?php
// api/auth/logout.php
require_once __DIR__ . '/../../config/session.php';

$pdo = $GLOBALS['pdo'];

if (isset($_SESSION['user_id'])) {
    log_action($pdo, $_SESSION['user_id'], 'AUTH_LOGOUT', "User {$_SESSION['school_id']} signed out.");
}
session_unset();
session_destroy();
header('Location: ' . BASE_URL . '/login.php');
exit();