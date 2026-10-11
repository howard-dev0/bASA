<?php
// config/session.php
require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/audit_logger.php';

$pdo = $GLOBALS['pdo'];

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start(); 
}

// 15-Minute Automatic Session Timeout
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        if (is_callable('log_action')) {
            call_user_func('log_action', $pdo, $_SESSION['user_id'], 'SESSION_TIMEOUT', 'Session expired due to 15 minutes of inactivity.');
        }
        session_unset();
        session_destroy();
        header('Location: ' . BASE_URL . '/login.php?timeout=1');
        exit();
    }
    $_SESSION['last_activity'] = time();
}

function require_role(array $allowed_roles): void {
    $pdo = $GLOBALS['pdo'];

    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        header('Location: ' . BASE_URL . '/login.php');
        exit();
    }

    if (!in_array($_SESSION['role'], $allowed_roles, true)) {
        if (is_callable('log_action')) {
            call_user_func('log_action', $pdo, $_SESSION['user_id'], 'UNAUTHORIZED_ACCESS', 'Attempted access to restricted route: ' . $_SERVER['REQUEST_URI']);
        }
        header('Location: ' . BASE_URL . '/index.php?error=forbidden');
        exit();
    }
}