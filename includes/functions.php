<?php
// includes/functions.php
require_once __DIR__ . '/../config/constants.php';

function e(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function is_dev_mode(?object $pdo = null): bool {
    $pdo = $pdo ?? ($GLOBALS['pdo'] ?? null);
    if (!$pdo) return false;
    try {
        $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'dev_mode_quick_fills' LIMIT 1");
        if ($stmt) {
            $val = $stmt->fetchColumn();
            if ($val !== false) {
                return ($val === '1');
            }
        }
    } catch (\Throwable $e) {}
    return false;
}

function is_valid_student_id(string $school_id): bool {
    return (bool) preg_match('/^\d{9}$/', trim($school_id));
}

function compose_full_name(string $first, ?string $mi, string $last, ?string $suffix): string {
    $parts = [trim($first)];
    if ($mi !== null && trim($mi) !== '') {
        $clean_mi = rtrim(trim($mi), '.') . '.';
        $parts[] = $clean_mi;
    }
    $parts[] = trim($last);
    if ($suffix !== null && trim($suffix) !== '') {
        $parts[] = trim($suffix);
    }
    return trim(implode(' ', array_filter($parts)));
}

function generate_student_totp(string $school_id, string $user_secret): array {
    $current_time = time();
    $time_window  = (int) floor($current_time / TOTP_WINDOW);
    $seconds_left = TOTP_WINDOW - ($current_time % TOTP_WINDOW);

    $data_to_sign = $school_id . '|' . $time_window . '|' . $user_secret;
    $signature    = hash_hmac('sha256', $data_to_sign, SYSTEM_HMAC_KEY);

    return [
        'qr_payload'   => "BASA-USR|{$school_id}|{$time_window}|{$signature}",
        'seconds_left' => $seconds_left,
        'window'       => $time_window
    ];
}

function verify_student_totp(string $qr_payload, object $pdo): array {
    $parts = explode('|', trim($qr_payload));
    if (count($parts) !== 4 || $parts[0] !== 'BASA-USR') {
        return ['valid' => false, 'message' => 'Invalid QR code format.'];
    }

    [, $school_id, $scanned_window, $scanned_sig] = $parts;
    $scanned_window = (int) $scanned_window;

    $stmt = $pdo->prepare("SELECT id, school_id, full_name, role, program_code, photo_path, totp_secret, status FROM users WHERE school_id = ? LIMIT 1");
    $stmt->execute([$school_id]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['valid' => false, 'message' => 'Student record not found.'];
    }

    if ($user['status'] !== 'active') {
        return ['valid' => false, 'message' => 'Account is currently ' . strtoupper($user['status']) . '.'];
    }

    $current_window = (int) floor(time() / TOTP_WINDOW);

    foreach ([$current_window, $current_window - 1] as $valid_window) {
        if ($scanned_window === $valid_window) {
            $expected_data = $user['school_id'] . '|' . $valid_window . '|' . $user['totp_secret'];
            $expected_sig  = hash_hmac('sha256', $expected_data, SYSTEM_HMAC_KEY);

            if (hash_equals($expected_sig, $scanned_sig)) {
                unset($user['totp_secret']);
                return ['valid' => true, 'user' => $user];
            }
        }
    }

    return ['valid' => false, 'message' => 'QR Code Expired! Screenshots are prohibited. Please scan live from dashboard.'];
}

// =========================================================================
// RFC 6238 GOOGLE AUTHENTICATOR (BASE32 TOTP) HELPERS
// =========================================================================
function generate_base32_secret(int $length = 16): string {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    for ($i = 0; $i < $length; $i++) {
        $secret .= $chars[random_int(0, 31)];
    }
    return $secret;
}

function base32_decode_binary(string $b32): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
    $bits = '';
    $output = '';

    for ($i = 0, $len = strlen($b32); $i < $len; $i++) {
        $val = strpos($alphabet, $b32[$i]);
        if ($val === false) continue;
        $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
    }

    for ($i = 0, $len = strlen($bits); $i + 8 <= $len; $i += 8) {
        $output .= chr(bindec(substr($bits, $i, 8)));
    }
    return $output;
}

function get_ga_totp_code(string $base32_secret, int $time_slice): string {
    $secret_key = base32_decode_binary($base32_secret);
    $time_bytes = pack('N*', 0) . pack('N*', $time_slice);
    $hmac       = hash_hmac('sha1', $time_bytes, $secret_key, true);
    $offset     = ord(substr($hmac, -1)) & 0x0F;
    $hash_part  = substr($hmac, $offset, 4);
    $value      = unpack('N', $hash_part)[1] & 0x7FFFFFFF;
    $modulo     = 1000000;
    return str_pad((string) ($value % $modulo), 6, '0', STR_PAD_LEFT);
}

function verify_ga_totp(string $base32_secret, string $code, int $discrepancy = 1): bool {
    $code = preg_replace('/\s+/', '', $code);
    if (strlen($code) !== 6 || !ctype_digit($code)) {
        return false;
    }
    $current_slice = (int) floor(time() / 30);
    for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
        if (hash_equals(get_ga_totp_code($base32_secret, $current_slice + $i), $code)) {
            return true;
        }
    }
    return false;
}

// =========================================================================
// FIREBASE PHONE AUTH (E.164 FORMATTER & SERVER-SIDE ID TOKEN VERIFIER)
// =========================================================================
function format_ph_phone_e164(string $phone): string {
    $digits = preg_replace('/[^0-9]/', '', $phone);
    if (str_starts_with($digits, '09') && strlen($digits) === 11) {
        return '+63' . substr($digits, 1);
    }
    if (str_starts_with($digits, '9') && strlen($digits) === 10) {
        return '+63' . $digits;
    }
    if (str_starts_with($digits, '639') && strlen($digits) === 12) {
        return '+' . $digits;
    }
    return '+' . $digits;
}

function verify_firebase_phone_token(string $id_token, string $expected_phone): bool {
    if ($id_token === '' || !function_exists('curl_init')) {
        return false;
    }

    $url = 'https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . rawurlencode(FIREBASE_API_KEY);
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode(['idToken' => $id_token]),
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0
    ]);

    $response  = curl_exec($ch);
    $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200 || !$response) {
        return false;
    }

    $data = json_decode($response, true);
    $firebase_phone = $data['users'][0]['phoneNumber'] ?? '';
    if ($firebase_phone === '') {
        return false;
    }

    $expected_e164 = format_ph_phone_e164($expected_phone);
    return hash_equals($expected_e164, $firebase_phone);
}

// =========================================================================
// MAILERSEND REST API EMAIL DISPATCHER
// =========================================================================
function send_mailersend_email(string $to_email, string $to_name, string $subject, string $text_body, string $html_body = ''): array {
    if (!function_exists('curl_init')) {
        return ['success' => false, 'http_code' => 0, 'error' => 'PHP cURL extension is not enabled.'];
    }

    $payload = [
        'from' => [
            'email' => MAILERSEND_FROM_EMAIL,
            'name'  => MAILERSEND_FROM_NAME
        ],
        'to' => [
            [
                'email' => trim($to_email),
                'name'  => trim($to_name) !== '' ? trim($to_name) : 'BCP Library User'
            ]
        ],
        'subject' => $subject,
        'text'    => $text_body,
        'html'    => $html_body !== '' ? $html_body : nl2br(e($text_body))
    ];

    $ch = curl_init('https://api.mailersend.com/v1/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-Requested-With: XMLHttpRequest',
            'Authorization: Bearer ' . MAILERSEND_API_TOKEN
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0
    ]);

    $response  = curl_exec($ch);
    $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($http_code >= 200 && $http_code < 300) {
        return ['success' => true, 'http_code' => $http_code, 'error' => null];
    }

    $api_msg = $curl_err;
    if ($response) {
        $decoded = json_decode($response, true);
        if (!empty($decoded['message'])) {
            $api_msg = $decoded['message'];
        }
    }

    return [
        'success'   => false,
        'http_code' => $http_code,
        'error'     => $api_msg ?: "HTTP {$http_code}"
    ];
}

function send_basa_otp_email(string $to_email, string $to_name, string $school_id, string $otp_code): array {
    $subject = "bASA Library Password Reset OTP: {$otp_code} 📬";
    $text    = "Hello {$to_name} (#{$school_id}),\n\nYour 6-digit bASA Library password reset verification code is: {$otp_code}\n\nThis code is valid for 5 minutes. If you did not request this, please ignore this email.";

    $safe_name = e($to_name);
    $safe_id   = e($school_id);
    $safe_otp  = e($otp_code);

    $html = "
    <div style='font-family: Arial, sans-serif; max-width: 520px; margin: 0 auto; background: #0f172a; color: #f8fafc; padding: 28px; border-radius: 16px; border: 1px solid #334155;'>
        <div style='border-bottom: 1px solid #334155; padding-bottom: 14px; margin-bottom: 20px;'>
            <span style='background: #0089d0; color: #ffffff; font-size: 11px; font-weight: bold; padding: 4px 10px; border-radius: 99px; text-transform: uppercase;'>bASA • Bestlink Library & Archives</span>
            <h2 style='margin: 12px 0 4px 0; color: #ffffff; font-size: 20px;'>Password Recovery Verification</h2>
            <p style='margin: 0; color: #94a3b8; font-size: 12px;'>Account ID: #{$safe_id}</p>
        </div>
        <p style='font-size: 14px; color: #e2e8f0; line-height: 1.6;'>Hello <strong>{$safe_name}</strong>,</p>
        <p style='font-size: 13px; color: #cbd5e1; line-height: 1.6;'>Use the 6-digit verification code below to reset your bASA Library account password. This code expires in <strong>5 minutes</strong>:</p>
        <div style='background: #1e293b; border: 2px dashed #0089d0; border-radius: 12px; padding: 18px; text-align: center; margin: 22px 0;'>
            <span style='font-family: monospace; font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #38bdf8;'>{$safe_otp}</span>
        </div>
        <p style='font-size: 11px; color: #64748b; margin-top: 20px; border-top: 1px solid #1e293b; padding-top: 12px;'>
            Sent via MailerSend API • Bestlink College of the Philippines Library & Archives
        </p>
    </div>";

    return send_mailersend_email($to_email, $to_name, $subject, $text, $html);
}