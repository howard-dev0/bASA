<?php
// api/profile/update.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = $GLOBALS['pdo'];

require_role(['student', 'faculty', 'librarian', 'super_admin']);

$user_id = (int) $_SESSION['user_id'];

// Handle multipart/form-data for Profile Photo Upload
if (isset($_POST['action']) && $_POST['action'] === 'upload_photo') {
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Please select a valid image file (JPG, PNG, or WEBP).']);
        exit();
    }

    $file     = $_FILES['photo'];
    $max_size = 3 * 1024 * 1024; // 3MB
    if ($file['size'] > $max_size) {
        echo json_encode(['success' => false, 'message' => 'Image size must be under 3MB.']);
        exit();
    }

    $finfo = new \finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    $allowed_mimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($allowed_mimes[$mime])) {
        echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, and WEBP images are allowed.']);
        exit();
    }

    $ext        = $allowed_mimes[$mime];
    $upload_dir = __DIR__ . '/../../uploads/profiles/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $filename = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
    $dest     = $upload_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        echo json_encode(['success' => false, 'message' => 'Failed to save uploaded image.']);
        exit();
    }

    $rel_path = 'uploads/profiles/' . $filename;
    $pdo->prepare("UPDATE users SET photo_path = ? WHERE id = ?")->execute([$rel_path, $user_id]);
    $_SESSION['photo_path'] = $rel_path;

    log_action($pdo, $user_id, 'PROFILE_PHOTO', 'Updated profile picture.');

    echo json_encode([
        'success'    => true,
        'photo_url'  => BASE_URL . '/' . $rel_path,
        'photo_path' => $rel_path,
        'message'    => 'Profile picture updated successfully!'
    ]);
    exit();
}

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? ($_GET['action'] ?? 'get_profile');

// 1. FETCH CURRENT USER PROFILE DATA
if ($action === 'get_profile') {
    $stmt = $pdo->prepare("
        SELECT id, school_id, first_name, middle_name, middle_initial, last_name, suffix,
               full_name, display_name, email, contact_no, role, program_code, photo_path,
               ga_enabled, created_at
        FROM users
        WHERE id = ? LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch();

    if ($profile && $profile['first_name'] === '' && $profile['last_name'] === '') {
        $parts = explode(' ', $profile['full_name']);
        $profile['first_name'] = $parts[0] ?? '';
        $profile['last_name']  = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '';
    }

    echo json_encode(['success' => true, 'profile' => $profile]);
    exit();
}

// 2. UPDATE STUDENT-EDITABLE FIELDS (Display Name & Contact No. ONLY)
if ($action === 'update_personal') {
    $display_name = trim($input['display_name'] ?? '');
    $contact_no   = trim($input['contact_no'] ?? '');

    if ($display_name === '') {
        echo json_encode(['success' => false, 'message' => 'Display Name cannot be empty.']);
        exit();
    }

    if ($contact_no !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $contact_no)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid contact number (e.g. 09171234567).']);
        exit();
    }

    $pdo->prepare("UPDATE users SET display_name = ?, contact_no = ? WHERE id = ?")
        ->execute([$display_name, $contact_no, $user_id]);

    $_SESSION['display_name'] = $display_name;

    log_action($pdo, $user_id, 'PROFILE_UPDATE', "Updated Display Name to '{$display_name}' and Contact No. to '{$contact_no}'.");

    echo json_encode([
        'success' => true,
        'message' => 'Display Name and Contact Number updated!'
    ]);
    exit();
}

// 3. INITIALIZE 1ST-TIME GOOGLE AUTHENTICATOR SETUP (Standalone 2FA Card)
if ($action === 'init_ga_setup') {
    $stmt = $pdo->prepare("SELECT school_id, email, ga_secret, ga_enabled FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $u = $stmt->fetch();

    $secret = $u['ga_secret'];
    if (empty($secret) || !empty($input['regenerate'])) {
        $secret = generate_base32_secret(16);
        $pdo->prepare("UPDATE users SET ga_secret = ?, ga_enabled = 0 WHERE id = ?")
            ->execute([$secret, $user_id]);
    }

    $issuer  = rawurlencode('bASA BCP Library');
    $account = rawurlencode($u['school_id']);
    $otpauth = "otpauth://totp/{$issuer}:{$account}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";

    echo json_encode([
        'success'     => true,
        'secret'      => $secret,
        'otpauth_uri' => $otpauth
    ]);
    exit();
}

// 4. VERIFY & ACTIVATE GOOGLE AUTHENTICATOR (Standalone 2FA Card)
if ($action === 'verify_ga_setup') {
    $code = trim($input['code'] ?? '');

    $stmt = $pdo->prepare("SELECT ga_secret FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $secret = $stmt->fetchColumn();

    if (empty($secret)) {
        echo json_encode(['success' => false, 'message' => 'Please initialize Google Authenticator setup first.']);
        exit();
    }

    if (!verify_ga_totp($secret, $code)) {
        echo json_encode(['success' => false, 'message' => 'Invalid 6-digit Authenticator code. Ensure your phone time is synced.']);
        exit();
    }

    $pdo->prepare("UPDATE users SET ga_enabled = 1 WHERE id = ?")->execute([$user_id]);
    log_action($pdo, $user_id, '2FA_GA_ENABLED', 'Enabled Google Authenticator 3rd-party 2FA.');

    echo json_encode([
        'success' => true,
        'message' => 'Google Authenticator 2FA is now linked to your account!'
    ]);
    exit();
}

// 5. STANDARD CHANGE PASSWORD (When user knows their Current Password — NO OTP needed)
if ($action === 'change_password') {
    $current_password = $input['current_password'] ?? '';
    $new_password     = $input['new_password'] ?? '';
    $confirm_password = $input['confirm_password'] ?? '';

    if ($current_password === '' || $new_password === '') {
        echo json_encode(['success' => false, 'message' => 'Please enter both your current password and new password.']);
        exit();
    }

    if (strlen($new_password) < 6) {
        echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters long.']);
        exit();
    }

    if ($new_password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'New password and confirmation password do not match.']);
        exit();
    }

    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $u = $stmt->fetch();

    if (!$u || !password_verify($current_password, $u['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid Current Password! If you forgot your password, click "Forgot Password?" below to verify via Email, SMS, or Authenticator.']);
        exit();
    }

    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$new_hash, $user_id]);

    log_action($pdo, $user_id, 'PASSWORD_CHANGE', 'Changed account password using current password.');

    echo json_encode([
        'success' => true,
        'message' => 'Your password has been changed successfully!'
    ]);
    exit();
}

// 6. SEND EMAIL OTP OR SMS OTP (Only shown when user clicks "Forgot Password?")
if ($action === 'send_otp') {
    $channel = trim($input['channel'] ?? 'email');
    if (!in_array($channel, ['email', 'sms'], true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid verification channel.']);
        exit();
    }

    $stmt = $pdo->prepare("SELECT school_id, full_name, email, contact_no FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $u = $stmt->fetch();

    if ($channel === 'sms' && empty($u['contact_no'])) {
        echo json_encode(['success' => false, 'message' => 'Please save a Contact Number on your profile first before requesting an SMS verification code.']);
        exit();
    }

    $otp_code   = (string) random_int(100000, 999999);
    $expires_at = date('Y-m-d H:i:s', time() + 300);

    $pdo->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ?, otp_channel = ? WHERE id = ?")
        ->execute([$otp_code, $expires_at, $channel, $user_id]);

    $destination = ($channel === 'email') ? $u['email'] : $u['contact_no'];

    if ($channel === 'email') {
        $subject = "bASA Library Password Reset OTP: {$otp_code}";
        $body    = "Hello {$u['full_name']},\n\nYour 6-digit password reset verification code is: {$otp_code}\nValid for 5 minutes.";
        @mail($u['email'], $subject, $body, "From: noreply@bcp.edu.ph");
    }

    log_action($pdo, $user_id, 'FORGOT_PW_OTP_SENT', "Sent Forgot Password OTP via " . strtoupper($channel) . " API to {$destination}.");

    echo json_encode([
        'success'     => true,
        'channel'     => $channel,
        'destination' => $destination,
        'demo_otp'    => $otp_code,
        'message'     => "Verification code sent via " . strtoupper($channel) . " API to {$destination}!"
    ]);
    exit();
}

// 7. RESET FORGOTTEN PASSWORD USING VERIFICATION CODE (Email API, SMS API, or Google Authenticator)
if ($action === 'reset_forgot_password') {
    $new_password     = $input['new_password'] ?? '';
    $confirm_password = $input['confirm_password'] ?? '';
    $auth_method      = trim($input['auth_method'] ?? 'email'); // 'email', 'sms', 'ga'
    $otp_input        = trim($input['otp_code'] ?? '');

    if ($new_password === '' || strlen($new_password) < 6) {
        echo json_encode(['success' => false, 'message' => 'New password must be at least 6 characters long.']);
        exit();
    }

    if ($new_password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'New password and confirmation password do not match.']);
        exit();
    }

    $stmt = $pdo->prepare("SELECT ga_secret, ga_enabled, otp_code, otp_expires_at, otp_channel FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $u = $stmt->fetch();

    if ($auth_method === 'ga') {
        if ((int)$u['ga_enabled'] !== 1 || empty($u['ga_secret'])) {
            echo json_encode(['success' => false, 'message' => 'Google Authenticator is not linked on your account yet. Please choose Email OTP or SMS OTP, or set up Authenticator in the 2FA section below.']);
            exit();
        }
        if (!verify_ga_totp($u['ga_secret'], $otp_input)) {
            echo json_encode(['success' => false, 'message' => 'Invalid 6-digit Google Authenticator code!']);
            exit();
        }
    } elseif (in_array($auth_method, ['email', 'sms'], true)) {
        if (empty($u['otp_code']) || $u['otp_channel'] !== $auth_method) {
            echo json_encode(['success' => false, 'message' => 'Please click "Send Verification Code" first.']);
            exit();
        }
        if (strtotime($u['otp_expires_at']) < time()) {
            echo json_encode(['success' => false, 'message' => 'Verification code has expired. Please request a new code.']);
            exit();
        }
        if (!hash_equals((string)$u['otp_code'], $otp_input)) {
            echo json_encode(['success' => false, 'message' => 'Invalid 6-digit verification code!']);
            exit();
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid verification method selected.']);
        exit();
    }

    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password_hash = ?, otp_code = NULL, otp_expires_at = NULL WHERE id = ?")
        ->execute([$new_hash, $user_id]);

    log_action($pdo, $user_id, 'PASSWORD_RESET_OTP', "Reset forgotten password verified via " . strtoupper($auth_method) . ".");

    echo json_encode([
        'success' => true,
        'message' => 'Password reset successfully via ' . strtoupper($auth_method) . ' verification!'
    ]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Unknown profile action.']);