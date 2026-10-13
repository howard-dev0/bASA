<?php
// api/auth/ping.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/session.php';

if (isset($_SESSION['user_id'])) {
    $_SESSION['last_activity'] = time();
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}