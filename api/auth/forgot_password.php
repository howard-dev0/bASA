<?php
// api/auth/forgot_password.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = $GLOBALS['pdo'];

// Rate Limit Check for Public OTP Verification
function check_public_otp_lockout() {
    if (isset($_SESSION['public_otp_lockout']) && time() < $_SESSION['public_otp_lockout']) {
        $left = $_SESSION['public_otp_lockout'] - time();
        echo json_encode(['success' => false, 'message' => "Too many invalid attempts. Try again in {$left} seconds."]);
        exit();
    }
}

function handle_public_otp_failure($msg) {
    $_SESSION['public_otp_attempts'] = ($_SESSION['public_otp_attempts'] ?? 0) + 1;
    if ($_SESSION['public_otp_attempts'] >= 5) {
        $_SESSION['public_otp_lockout'] = time() + 300;
    }
    echo json_encode(['success' => false, 'message' => $msg]);
    exit();
}

$input      = json_decode(file_get_contents('php://input'), true) ?? [];
$action     = $input['action'] ?? '';
$identifier = trim($input['identifier'] ?? '');

function mask_email(string $email): string {
    $parts = explode('@', $email);
    if (count($parts) !== 2) return $email;
    $name = $parts[0];
    $domain = $parts[1];
    if (strlen($name) <= 2) return '*' . substr($name, -1) . '@' . $domain;
    return substr($name, 0, 2) . str_repeat('*', strlen($name) - 3) . substr($name, -1) . '@' . $domain;
}

function mask_phone(string $phone): string {
    $clean = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($clean) < 7) return $phone;
    return substr($clean, 0, 4) . str_repeat('*', strlen($clean) - 7) . substr($clean, -3);
}

if ($identifier === '') {
    echo json_encode(['success' => false, 'message' => 'Please enter your BCP School ID or Email first.']);
    exit();
}

$stmt = $pdo->prepare("
    SELECT id, school_id, full_name, email, contact_no, ga_secret, ga_enabled, otp_code, otp_expires_at, otp_channel
    FROM users
    WHERE school_id = :id OR email = :email
    LIMIT 1
");
$stmt->execute([':id' => $identifier, ':email' => $identifier]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'No account found matching that School ID or Email.']);
    exit();
}

// 1. FIND ACCOUNT
if ($action === 'find_account') {
    echo json_encode([
        'success' => true,
        'account' => [
            'full_name'    => $user['full_name'],
            'email_masked' => mask_email($user['email']),
            'phone_masked' => !empty($user['contact_no']) ? mask_phone($user['contact_no']) : null,
            'ga_enabled'   => (int) $user['ga_enabled'] === 1
        ]
    ]);
    exit();
}

// 2. SEND OTP FOR FORGOTTEN PASSWORD
if ($action === 'send_otp') {
    $channel = trim($input['channel'] ?? 'email');
    if (!in_array($channel, ['email', 'sms'], true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid verification channel.']);
        exit();
    }

    if ($channel === 'sms' && empty($user['contact_no'])) {
        echo json_encode(['success' => false, 'message' => 'No contact number is registered on this account. Please use Email OTP or Authenticator.']);
        exit();
    }

    $otp_code   = (string) random_int(100000, 999999);
    $expires_at = date('Y-m-d H:i:s', time() + 300);

    $pdo->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ?, otp_channel = ? WHERE id = ?")
        ->execute([$otp_code, $expires_at, $channel, $user['id']]);

    $dest = ($channel === 'email') ? $user['email'] : $user['contact_no'];
    if ($channel === 'email') {
        @mail($user['email'], "bASA Password Reset Code: {$otp_code}", "Your 6-digit password reset code is: {$otp_code}", "From: noreply@bcp.edu.ph");
    }

    log_action($pdo, (int)$user['id'], 'LOGIN_FORGOT_OTP', "Dispatched password recovery OTP via " . strtoupper($channel) . " for #{$user['school_id']}.");

    echo json_encode([
        'success'  => true,
        'demo_otp' => $otp_code,
        'message'  => "6-digit verification code sent via " . strtoupper($channel) . " API!"
    ]);
    exit();
}

// 3. VERIFY OTP & RESET PASSWORD
if ($action === 'reset_password') {
    check_public_otp_lockout();

    $auth_method      = trim($input['auth_method'] ?? 'email');
    $otp_input        = trim($input['otp_code'] ?? '');
    $new_password     = $input['new_password'] ?? '';
    $confirm_password = $input['confirm_password'] ?? '';

    if (strlen($new_password) < 6 || $new_password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'Passwords must match and be at least 6 chars.']);
        exit();
    }

    if ($auth_method === 'ga') {
        if ((int)$user['ga_enabled'] !== 1 || empty($user['ga_secret'])) {
            handle_public_otp_failure('Google Authenticator is not set up on this account. Please use Email or SMS verification.');
        }
        if (!verify_ga_totp($user['ga_secret'], $otp_input)) {
            handle_public_otp_failure('Invalid 6-digit Google Authenticator code!');
        }
    } else {
        if (empty($user['otp_code']) || $user['otp_channel'] !== $auth_method) {
            handle_public_otp_failure('Please request a verification code first.');
        }
        if (strtotime($user['otp_expires_at']) < time()) {
            handle_public_otp_failure('Verification code has expired.');
        }
        if (!hash_equals((string)$user['otp_code'], $otp_input)) {
            handle_public_otp_failure('Invalid 6-digit verification code!');
        }
    }

    unset($_SESSION['public_otp_attempts'], $_SESSION['public_otp_lockout']);

    $hash = password_hash($new_password, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password_hash = ?, otp_code = NULL, otp_expires_at = NULL WHERE id = ?")
        ->execute([$hash, $user['id']]);

    log_action($pdo, (int)$user['id'], 'LOGIN_PW_RESET', "Reset password from login portal via " . strtoupper($auth_method) . ".");

    echo json_encode([
        'success' => true,
        'message' => 'Password reset successful! You can now sign in with your new password.'
    ]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid request.']);