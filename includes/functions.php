<?php
// includes/functions.php
require_once __DIR__ . '/../config/constants.php';

function e(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function is_valid_student_id(string $school_id): bool {
    return (bool) preg_match('/^\d{9}$/', trim($school_id));
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