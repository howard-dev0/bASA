<?php
// api/auth/login.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/session.php';

$pdo = $GLOBALS['pdo'];

// Brute-Force Lockout Check
if (isset($_SESSION['login_lockout']) && time() < $_SESSION['login_lockout']) {
    $left = $_SESSION['login_lockout'] - time();
    echo json_encode(['success' => false, 'message' => "Too many failed attempts. Try again in {$left} seconds."]);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$identifier = trim($input['identifier'] ?? '');
$password   = $input['password'] ?? '';

if ($identifier === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Please enter both ID/Email and password.']);
    exit();
}

$stmt = $pdo->prepare("SELECT id, school_id, full_name, email, password_hash, role, program_code, status FROM users WHERE school_id = :id OR email = :email LIMIT 1");
$stmt->execute([':id' => $identifier, ':email' => $identifier]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    // Record Failed Attempt
    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
    if ($_SESSION['login_attempts'] >= 5) {
        $_SESSION['login_lockout'] = time() + 300; // 5 minute lockout
    }

    log_action($pdo, $user['id'] ?? null, 'AUTH_FAILED', "Failed login attempt for identifier: {$identifier}");
    echo json_encode(['success' => false, 'message' => 'Invalid School ID/Email or password.']);
    exit();
}

if ($user['status'] !== 'active') {
    echo json_encode(['success' => false, 'message' => 'Account status is ' . $user['status'] . '.']);
    exit();
}

// Success: Clear Lockouts
unset($_SESSION['login_attempts'], $_SESSION['login_lockout']);

session_regenerate_id(true);
$_SESSION['user_id']       = (int) $user['id'];
$_SESSION['school_id']     = $user['school_id'];
$_SESSION['full_name']     = $user['full_name'];
$_SESSION['role']          = $user['role'];
$_SESSION['program_code']  = $user['program_code'];
$_SESSION['last_activity'] = time();

log_action($pdo, $_SESSION['user_id'], 'AUTH_LOGIN', "User {$user['school_id']} ({$user['role']}) logged in.");

echo json_encode(['success' => true, 'redirect' => BASE_URL . '/index.php']);