<?php
// api/auth/forgot_password.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = $GLOBALS['pdo'];

$input      = json_decode(file_get_contents('php://input'), true) ?? [];
$action     = $input['action'] ?? '';
$identifier = trim($input['identifier'] ?? '');

function check_user_db_lockout(array $user): void {
    if (!empty($user['security_lockout_until']) && strtotime($user['security_lockout_until']) > time()) {
        $left = strtotime($user['security_lockout_until']) - time();
        echo json_encode(['success' => false, 'message' => "Too many invalid attempts. Try again in {$left} seconds."]);
        exit();
    }
}

function record_user_db_failure(object $pdo, array $user, string $msg): void {
    $attempts = (int) ($user['security_attempts'] ?? 0) + 1;
    $lockout  = ($attempts >= 5) ? date('Y-m-d H:i:s', time() + 300) : null;
    $pdo->prepare("UPDATE users SET security_attempts = ?, security_lockout_until = ? WHERE id = ?")
        ->execute([$attempts, $lockout, $user['id']]);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit();
}

function clear_user_db_failures(object $pdo, int $user_id): void {
    $pdo->prepare("UPDATE users SET security_attempts = 0, security_lockout_until = NULL WHERE id = ?")
        ->execute([$user_id]);
}

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
    SELECT id, school_id, full_name, email, contact_no, ga_secret, ga_enabled,
           otp_code, otp_expires_at, otp_channel, security_attempts, security_lockout_until
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

// 2. SEND OTP FOR FORGOTTEN PASSWORD (VIA MAILERSEND API OR SMS)
if ($action === 'send_otp') {
    check_user_db_lockout($user);

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

    $dev_mode = is_dev_mode($pdo);
    $api_status_note = '';

    if ($channel === 'email') {
        $mail_res = send_basa_otp_email($user['email'], $user['full_name'], $user['school_id'], $otp_code);
        if ($mail_res['success']) {
            $api_status_note = ' (Delivered via MailerSend API)';
        } else {
            if (!$dev_mode) {
                echo json_encode([
                    'success' => false,
                    'message' => 'MailerSend API Notice: ' . $mail_res['error']
                ]);
                exit();
            }
            $api_status_note = ' (MailerSend Trial Note: ' . $mail_res['error'] . ')';
        }
    }

    log_action($pdo, (int)$user['id'], 'LOGIN_FORGOT_OTP', "Dispatched password recovery OTP via " . strtoupper($channel) . " for #{$user['school_id']}{$api_status_note}.");

    echo json_encode([
        'success'  => true,
        'demo_otp' => $dev_mode ? $otp_code : null,
        'message'  => "6-digit verification code sent via " . strtoupper($channel) . "!" . $api_status_note
    ]);
    exit();
}

// 3. VERIFY OTP & RESET PASSWORD
if ($action === 'reset_password') {
    check_user_db_lockout($user);

    $auth_method      = trim($input['auth_method'] ?? 'email');
    $otp_input        = trim($input['otp_code'] ?? '');
    $new_password     = $input['new_password'] ?? '';
    $confirm_password = $input['confirm_password'] ?? '';

    if (strlen($new_password) < 6) {
        echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters long.']);
        exit();
    }
    if ($new_password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'New password and confirmation do not match.']);
        exit();
    }

    if ($auth_method === 'ga') {
        if ((int)$user['ga_enabled'] !== 1 || empty($user['ga_secret'])) {
            record_user_db_failure($pdo, $user, 'Google Authenticator is not set up on this account. Please use Email or SMS verification.');
        }
        if (!verify_ga_totp($user['ga_secret'], $otp_input)) {
            record_user_db_failure($pdo, $user, 'Invalid 6-digit Google Authenticator code!');
        }
    } else {
        if (empty($user['otp_code']) || $user['otp_channel'] !== $auth_method) {
            record_user_db_failure($pdo, $user, 'Please request a verification code first.');
        }
        if (strtotime($user['otp_expires_at']) < time()) {
            record_user_db_failure($pdo, $user, 'Verification code has expired.');
        }
        if (!hash_equals((string)$user['otp_code'], $otp_input)) {
            record_user_db_failure($pdo, $user, 'Invalid 6-digit verification code!');
        }
    }

    clear_user_db_failures($pdo, (int)$user['id']);

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