<?php
// api/auth/generate_totp.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = $GLOBALS['pdo'];

require_role(['student', 'faculty']);

$stmt = $pdo->prepare("SELECT school_id, totp_secret FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(['success' => false]);
    exit();
}

$totp = generate_student_totp($user['school_id'], $user['totp_secret']);
echo json_encode([
    'success'      => true,
    'qr_payload'   => $totp['qr_payload'],
    'seconds_left' => $totp['seconds_left']
]);