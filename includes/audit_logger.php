<?php
// includes/audit_logger.php

function log_action(object $pdo, ?int $user_id, string $event_type, string $description): void {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, event_type, event_description, ip_address)
            VALUES (:user_id, :event_type, :description, :ip)
        ");
        $stmt->execute([
            ':user_id'     => $user_id,
            ':event_type'  => $event_type,
            ':description' => $description,
            ':ip'          => $ip
        ]);
    } catch (\Throwable $e) {
        error_log("Audit Logger Error: " . $e->getMessage());
    }
}