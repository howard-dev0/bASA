<?php
// api/profile/update.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = $GLOBALS['pdo'];

require_role(['student', 'faculty', 'librarian', 'super_admin']);

$user_id = (int) $_SESSION['user_id'];

// Global helper to verify PIN if the user has one enabled
function verify_profile_pin($pdo, $user_id, $provided_pin) {
    $stmt = $pdo->prepare("SELECT profile_pin FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $pin_hash = $stmt->fetchColumn();

    if (!empty($pin_hash)) {
        if (empty($provided_pin) || !password_verify($provided_pin, $pin_hash)) {
            echo json_encode(['success' => false, 'message' => 'Invalid Profile Lock PIN. Security change blocked.']);
            exit();
        }
    }
}

// Handle multipart/form-data for Profile Photo Upload
if (isset($_POST['action']) && $_POST['action'] === 'upload_photo') {
    verify_profile_pin($pdo, $user_id, $_POST['profile_pin'] ?? '');

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
    $allowed_mimes = ['image/jpeg' => 'jpg', 'image/png'  => 'png', 'image/webp' => 'webp'];

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

// 0. VERIFY PIN ENDPOINT (For unlocking UI smoothly)
if ($action === 'verify_pin') {
    $pin = trim($input['pin'] ?? '');
    $stmt = $pdo->prepare("SELECT profile_pin FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $pin_hash = $stmt->fetchColumn();

    if (empty($pin_hash)) {
        echo json_encode(['success' => true, 'message' => 'No PIN set.']);
        exit();
    }

    if (password_verify($pin, $pin_hash)) {
        echo json_encode(['success' => true, 'message' => 'Security Unlocked!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid Profile Lock PIN.']);
    }
    exit();
}

// 1. FETCH CURRENT USER PROFILE DATA
if ($action === 'get_profile') {
    $stmt = $pdo->prepare("
        SELECT id, school_id, first_name, middle_name, middle_initial, last_name, suffix,
               full_name, display_name, email, contact_no, role, program_code, photo_path,
               ga_enabled, profile_pin, created_at
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

    $profile['has_pin'] = !empty($profile['profile_pin']);
    unset($profile['profile_pin']); // Never expose hash to frontend

    echo json_encode(['success' => true, 'profile' => $profile]);
    exit();
}

// 2. UPDATE STUDENT-EDITABLE FIELDS
if ($action === 'update_personal') {
    verify_profile_pin($pdo, $user_id, $input['profile_pin'] ?? '');

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

    echo json_encode(['success' => true, 'message' => 'Display Name and Contact Number updated!']);
    exit();
}

// 3. SET UP / ENABLE PROFILE LOCK PIN
if ($action === 'setup_pin') {
    $password = trim($input['password'] ?? '');
    $new_pin  = trim($input['new_pin'] ?? '');

    if (!preg_match('/^\d{4,6}$/', $new_pin)) {
        echo json_encode(['success' => false, 'message' => 'PIN must be a 4 to 6-digit number.']);
        exit();
    }

    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $hash = $stmt->fetchColumn();

    if (!$hash || !password_verify($password, $hash)) {
        echo json_encode(['success' => false, 'message' => 'Incorrect current password.']);
        exit();
    }

    $pin_hash = password_hash($new_pin, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET profile_pin = ? WHERE id = ?")->execute([$pin_hash, $user_id]);

    log_action($pdo, $user_id, 'PROFILE_PIN_ENABLED', 'Enabled Profile Lock PIN.');
    echo json_encode(['success' => true, 'message' => 'Profile Lock PIN enabled! Your personal info is now protected.']);
    exit();
}

// 4. REMOVE / DISABLE PROFILE LOCK PIN
if ($action === 'remove_pin') {
    // Rely strictly on the PIN itself to authorize its own removal
    verify_profile_pin($pdo, $user_id, $input['profile_pin'] ?? '');

    $pdo->prepare("UPDATE users SET profile_pin = NULL WHERE id = ?")->execute([$user_id]);

    log_action($pdo, $user_id, 'PROFILE_PIN_DISABLED', 'Removed Profile Lock PIN.');
    echo json_encode(['success' => true, 'message' => 'Profile Lock PIN has been successfully removed.']);
    exit();
}

// 5. INITIALIZE 1ST-TIME GOOGLE AUTHENTICATOR SETUP
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

    echo json_encode(['success' => true, 'secret' => $secret, 'otpauth_uri' => $otpauth]);
    exit();
}

// 6. VERIFY & ACTIVATE GOOGLE AUTHENTICATOR
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

    echo json_encode(['success' => true, 'message' => 'Google Authenticator 2FA is now linked to your account!']);
    exit();
}

// 7. STANDARD CHANGE PASSWORD
if ($action === 'change_password') {
    verify_profile_pin($pdo, $user_id, $input['profile_pin'] ?? '');

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
        echo json_encode(['success' => false, 'message' => 'Invalid Current Password!']);
        exit();
    }

    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$new_hash, $user_id]);

    log_action($pdo, $user_id, 'PASSWORD_CHANGE', 'Changed account password using current password.');

    echo json_encode(['success' => true, 'message' => 'Your password has been changed successfully!']);
    exit();
}

// 8. SEND EMAIL OTP OR SMS OTP
if ($action === 'send_otp') {
    verify_profile_pin($pdo, $user_id, $input['profile_pin'] ?? '');

    $channel = trim($input['channel'] ?? 'email');
    if (!in_array($channel, ['email', 'sms'], true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid verification channel.']);
        exit();
    }

    $stmt = $pdo->prepare("SELECT school_id, full_name, email, contact_no FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $u = $stmt->fetch();

    if ($channel === 'sms' && empty($u['contact_no'])) {
        echo json_encode(['success' => false, 'message' => 'Please save a Contact Number on your profile first before requesting an SMS.']);
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

// 9. RESET FORGOTTEN PASSWORD USING VERIFICATION CODE
if ($action === 'reset_forgot_password') {
    verify_profile_pin($pdo, $user_id, $input['profile_pin'] ?? '');

    $new_password     = $input['new_password'] ?? '';
    $confirm_password = $input['confirm_password'] ?? '';
    $auth_method      = trim($input['auth_method'] ?? 'email'); 
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
            echo json_encode(['success' => false, 'message' => 'Google Authenticator is not linked on your account yet.']);
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

    echo json_encode(['success' => true, 'message' => 'Password reset successfully via ' . strtoupper($auth_method) . ' verification!']);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Unknown profile action.']);