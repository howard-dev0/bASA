<?php
// config/session.php
require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/audit_logger.php';

$pdo = $GLOBALS['pdo'] ?? null;

// Dynamically fetch session timeout from Database, fallback to 900 (15 mins)
$dynamic_timeout = 900;
if ($pdo) {
    try {
        $stmt_to = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'session_timeout_seconds' LIMIT 1");
        if ($stmt_to) {
            $val = $stmt_to->fetchColumn();
            if ($val !== false) $dynamic_timeout = (int)$val;
        }
    } catch (\Throwable $e) {}
}
if (!defined('DYNAMIC_SESSION_TIMEOUT')) {
    define('DYNAMIC_SESSION_TIMEOUT', $dynamic_timeout);
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start(); 
}

// Automatic Session Timeout Validation
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > DYNAMIC_SESSION_TIMEOUT)) {
        if ($pdo && is_callable('log_action')) {
            call_user_func('log_action', $pdo, $_SESSION['user_id'], 'SESSION_TIMEOUT', 'Session expired due to inactivity.');
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
    // Update last activity time on every page load
    $_SESSION['last_activity'] = time();
}

function require_role(array $allowed_roles): void {
    $pdo = $GLOBALS['pdo'] ?? null;

    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        $login_url = BASE_URL . '/login.php';
        if (headers_sent()) {
            echo "<script>window.location.href='{$login_url}';</script>";
        } else {
            header("Location: {$login_url}");
        }
        exit();
    }

    if (!in_array($_SESSION['role'], $allowed_roles, true)) {
        if ($pdo && is_callable('log_action')) {
            call_user_func('log_action', $pdo, $_SESSION['user_id'], 'UNAUTHORIZED_ACCESS', 'Attempted access to restricted route: ' . $_SERVER['REQUEST_URI']);
        }
        $redirect_url = BASE_URL . '/index.php';
        if (headers_sent()) {
            echo "<script>window.location.replace('{$redirect_url}');</script>";
        } else {
            header("Location: {$redirect_url}");
        }
        exit();
    }
}