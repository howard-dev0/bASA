<?php
// config/session.php
require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/audit_logger.php';

$pdo = $GLOBALS['pdo'] ?? null;

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
        if ($pdo && is_callable('log_action')) {
            call_user_func('log_action', $pdo, $_SESSION['user_id'], 'SESSION_TIMEOUT', 'Session expired due to 15 minutes of inactivity.');
        }
        session_unset();
        session_destroy();
        
        $timeout_url = BASE_URL . '/login.php?timeout=1';
        if (headers_sent()) {
            echo "<script>window.location.href='{$timeout_url}';</script>";
        } else {
            header("Location: {$timeout_url}");
        }
        exit();
    }
    $_SESSION['last_activity'] = time();
}

function require_role(array $allowed_roles): void {
    $pdo = $GLOBALS['pdo'] ?? null;

    // 1. If completely logged out, send to Login
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        $login_url = BASE_URL . '/login.php';
        if (headers_sent()) {
            echo "<script>window.location.href='{$login_url}';</script>";
        } else {
            header("Location: {$login_url}");
        }
        exit();
    }

    // 2. If logged in but accessing an unauthorized page
    if (!in_array($_SESSION['role'], $allowed_roles, true)) {
        if ($pdo && is_callable('log_action')) {
            call_user_func('log_action', $pdo, $_SESSION['user_id'], 'UNAUTHORIZED_ACCESS', 'Attempted access to restricted route: ' . $_SERVER['REQUEST_URI']);
        }
        
        // Bounce them to the main router, which safely puts them back on their own dashboard
        $redirect_url = BASE_URL . '/index.php';
        if (headers_sent()) {
            // HTML already started, use JS redirect
            echo "<script>window.location.replace('{$redirect_url}');</script>";
        } else {
            // Pure PHP redirect
            header("Location: {$redirect_url}");
        }
        exit();
    }
}