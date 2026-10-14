<?php
// api/profile/update.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = $GLOBALS['pdo'];

require_role(['student', 'faculty', 'librarian', 'super_admin']);

$user_id = (int) $_SESSION['user_id'];

// --- DATABASE-BACKED BRUTE FORCE PROTECTION ---
function check_db_lockout($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT security_lockout_until FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $lockout = $stmt->fetchColumn();

    if ($lockout && strtotime($lockout) > time()) {
        $left = strtotime($lockout) - time();
        echo json_encode(['success' => false, 'message' => "Too many invalid attempts. Try again in {$left} seconds."]);
        exit();
    }
}

function record_db_failure($pdo, $user_id, $message) {
    $stmt = $pdo->prepare("SELECT security_attempts FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $attempts = (int) $stmt->fetchColumn() + 1;

    $lockout = null;
    if ($attempts >= 5) {
        $lockout = date('Y-m-d H:i:s', time() + 300);
    }

    $pdo->prepare("UPDATE users SET security_attempts = ?, security_lockout_until = ? WHERE id = ?")
        ->execute([$attempts, $lockout, $user_id]);

    echo json_encode(['success' => false, 'message' => $message]);
    exit();
}

function clear_db_failures($pdo, $user_id) {
    $pdo->prepare("UPDATE users SET security_attempts = 0, security_lockout_until = NULL WHERE id = ?")
        ->execute([$user_id]);
}

function verify_profile_pin($pdo, $user_id, $provided_pin) {
    check_db_lockout($pdo, $user_id);

    $stmt = $pdo->prepare("SELECT profile_pin FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $pin_hash = $stmt->fetchColumn();

    if (!empty($pin_hash)) {
        if (empty($provided_pin) || !password_verify($provided_pin, $pin_hash)) {
            record_db_failure($pdo, $user_id, 'Invalid Profile Lock PIN. Security change blocked.');
        }
        clear_db_failures($pdo, $user_id);
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
    $max_size = 3 * 1024 * 1024;
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

    echo json_encode(['success' => true, 'photo_url' => BASE_URL . '/' . $rel_path, 'photo_path' => $rel_path, 'message' => 'Profile picture updated successfully!']);
    exit();
}

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? ($_GET['action'] ?? 'get_profile');

// 0. VERIFY PIN ENDPOINT
if ($action === 'verify_pin') {
    check_db_lockout($pdo, $user_id);

    $pin = trim($input['pin'] ?? '');
    $stmt = $pdo->prepare("SELECT profile_pin FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $pin_hash = $stmt->fetchColumn();

    if (empty($pin_hash)) {
        echo json_encode(['success' => true, 'message' => 'No PIN set.']);
        exit();
    }

    if (password_verify($pin, $pin_hash)) {
        clear_db_failures($pdo, $user_id);
        echo json_encode(['success' => true, 'message' => 'Security Unlocked!']);
    } else {
        record_db_failure($pdo, $user_id, 'Invalid Profile Lock PIN.');
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
    unset($profile['profile_pin']);

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

    $pdo->prepare("UPDATE users SET display_name = ?, contact_no = ? WHERE id = ?")->execute([$display_name, $contact_no, $user_id]);
    $_SESSION['display_name'] = $display_name;

    log_action($pdo, $user_id, 'PROFILE_UPDATE', "Updated Display Name to '{$display_name}' and Contact No. to '{$contact_no}'.");
    echo json_encode(['success' => true, 'message' => 'Display Name and Contact Number updated!']);
    exit();
}

// 3. SET UP / ENABLE PROFILE LOCK PIN
if ($action === 'setup_pin') {
    check_db_lockout($pdo, $user_id);

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
        record_db_failure($pdo, $user_id, 'Incorrect current password.');
    }
    clear_db_failures($pdo, $user_id);

    $pin_hash = password_hash($new_pin, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET profile_pin = ? WHERE id = ?")->execute([$pin_hash, $user_id]);

    log_action($pdo, $user_id, 'PROFILE_PIN_ENABLED', 'Enabled Profile Lock PIN.');
    echo json_encode(['success' => true, 'message' => 'Profile Lock PIN enabled! Your personal info is now protected.']);
    exit();
}

// 4. REMOVE / DISABLE PROFILE LOCK PIN
if ($action === 'remove_pin') {
    verify_profile_pin($pdo, $user_id, $input['profile_pin'] ?? '');

    $pdo->prepare("UPDATE users SET profile_pin = NULL WHERE id = ?")->execute([$user_id]);

    log_action($pdo, $user_id, 'PROFILE_PIN_DISABLED', 'Removed Profile Lock PIN.');
    echo json_encode(['success' => true, 'message' => 'Profile Lock PIN has been successfully removed.']);
    exit();
}

// 5. INITIALIZE 1ST-TIME GOOGLE AUTHENTICATOR SETUP
if ($action === 'init_ga_setup') {
    verify_profile_pin($pdo, $user_id, $input['profile_pin'] ?? '');

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
    verify_profile_pin($pdo, $user_id, $input['profile_pin'] ?? '');
    check_db_lockout($pdo, $user_id);
    
    $code = trim($input['code'] ?? '');

    $stmt = $pdo->prepare("SELECT ga_secret FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $secret = $stmt->fetchColumn();

    if (empty($secret)) {
        echo json_encode(['success' => false, 'message' => 'Please initialize Google Authenticator setup first.']);
        exit();
    }

    if (!verify_ga_totp($secret, $code)) {
        record_db_failure($pdo, $user_id, 'Invalid 6-digit Authenticator code. Ensure your phone time is synced.');
    }
    clear_db_failures($pdo, $user_id);

    $pdo->prepare("UPDATE users SET ga_enabled = 1 WHERE id = ?")->execute([$user_id]);
    log_action($pdo, $user_id, '2FA_GA_ENABLED', 'Enabled Google Authenticator 3rd-party 2FA.');

    echo json_encode(['success' => true, 'message' => 'Google Authenticator 2FA is now linked to your account!']);
    exit();
}

// 7. STANDARD CHANGE PASSWORD
if ($action === 'change_password') {
    verify_profile_pin($pdo, $user_id, $input['profile_pin'] ?? '');
    check_db_lockout($pdo, $user_id);

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
        record_db_failure($pdo, $user_id, 'Invalid Current Password!');
    }
    clear_db_failures($pdo, $user_id);

    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$new_hash, $user_id]);

    log_action($pdo, $user_id, 'PASSWORD_CHANGE', 'Changed account password using current password.');

    echo json_encode(['success' => true, 'message' => 'Your password has been changed successfully!']);
    exit();
}

// 8. SEND EMAIL OTP (MAILERSEND API) OR SMS OTP (STRICTLY TO REGISTERED CONTACT NO.)
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
        echo json_encode(['success' => false, 'message' => 'Please save a Contact Number on your profile first before requesting an SMS verification code.']);
        exit();
    }

    $otp_code   = (string) random_int(100000, 999999);
    $expires_at = date('Y-m-d H:i:s', time() + 300);

    $pdo->prepare("UPDATE users SET otp_code = ?, otp_expires_at = ?, otp_channel = ? WHERE id = ?")
        ->execute([$otp_code, $expires_at, $channel, $user_id]);

    $destination = ($channel === 'email') ? $u['email'] : $u['contact_no'];
    $dev_mode    = is_dev_mode($pdo);
    $api_note    = '';
    $phone_e164  = null;

    if ($channel === 'email') {
        $mail_res = send_basa_otp_email($u['email'], $u['full_name'], $u['school_id'], $otp_code);
        if ($mail_res['success']) {
            $api_note = ' (Delivered via MailerSend API)';
        } else {
            if (!$dev_mode) {
                echo json_encode(['success' => false, 'message' => 'MailerSend API Notice: ' . $mail_res['error']]);
                exit();
            }
            $api_note = ' (MailerSend Trial Note: ' . $mail_res['error'] . ')';
        }
    } else {
        $phone_e164 = format_ph_phone_e164((string)$u['contact_no']);
        $api_note   = " (Dispatching via Firebase SMS to {$phone_e164})";
    }

    log_action($pdo, $user_id, 'FORGOT_PW_OTP_SENT', "Sent Forgot Password OTP via " . strtoupper($channel) . " API to {$destination}{$api_note}.");

    echo json_encode([
        'success'      => true,
        'channel'      => $channel,
        'destination'  => $destination,
        'phone_e164'   => $phone_e164,
        'demo_otp'     => $dev_mode ? $otp_code : null,
        'fallback_otp' => $dev_mode ? $otp_code : null,
        'message'      => "Verification code initiated via " . strtoupper($channel) . " to {$destination}!" . $api_note
    ]);
    exit();
}

// 9. RESET FORGOTTEN PASSWORD USING VERIFICATION CODE
if ($action === 'reset_forgot_password') {
    verify_profile_pin($pdo, $user_id, $input['profile_pin'] ?? '');
    check_db_lockout($pdo, $user_id);

    $new_password      = $input['new_password'] ?? '';
    $confirm_password  = $input['confirm_password'] ?? '';
    $auth_method       = trim($input['auth_method'] ?? 'email'); 
    $otp_input         = trim($input['otp_code'] ?? '');
    $firebase_id_token = trim($input['firebase_id_token'] ?? '');

    if (strlen($new_password) < 6 || $new_password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'Passwords must match and be at least 6 chars long.']);
        exit();
    }

    $stmt = $pdo->prepare("SELECT contact_no, ga_secret, ga_enabled, otp_code, otp_expires_at, otp_channel FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$user_id]);
    $u = $stmt->fetch();

    if ($auth_method === 'ga') {
        if ((int)$u['ga_enabled'] !== 1 || empty($u['ga_secret'])) {
            record_db_failure($pdo, $user_id, 'Google Authenticator is not linked on your account yet.');
        }
        if (!verify_ga_totp($u['ga_secret'], $otp_input)) {
            record_db_failure($pdo, $user_id, 'Invalid 6-digit Google Authenticator code!');
        }
    } elseif ($auth_method === 'sms' && $firebase_id_token !== '') {
        if (empty($u['contact_no']) || !verify_firebase_phone_token($firebase_id_token, (string)$u['contact_no'])) {
            record_db_failure($pdo, $user_id, 'Firebase SMS token verification failed or does not match registered phone number.');
        }
    } elseif (in_array($auth_method, ['email', 'sms'], true)) {
        if (empty($u['otp_code']) || $u['otp_channel'] !== $auth_method) {
            record_db_failure($pdo, $user_id, 'Please request a verification code first.');
        }
        if (strtotime($u['otp_expires_at']) < time()) {
            record_db_failure($pdo, $user_id, 'Verification code has expired. Please request a new code.');
        }
        if (!hash_equals((string)$u['otp_code'], $otp_input)) {
            record_db_failure($pdo, $user_id, 'Invalid 6-digit verification code!');
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid verification method selected.']);
        exit();
    }

    clear_db_failures($pdo, $user_id);

    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password_hash = ?, otp_code = NULL, otp_expires_at = NULL WHERE id = ?")
        ->execute([$new_hash, $user_id]);

    log_action($pdo, $user_id, 'PASSWORD_RESET_OTP', "Reset forgotten password verified via " . strtoupper($auth_method) . ".");

    echo json_encode(['success' => true, 'message' => 'Password reset successfully via ' . strtoupper($auth_method) . ' verification!']);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Unknown profile action.']);