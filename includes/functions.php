<?php
// includes/functions.php
require_once __DIR__ . '/../config/constants.php';

function e(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
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