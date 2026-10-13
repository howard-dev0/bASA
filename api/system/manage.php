<?php
// api/system/manage.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = $GLOBALS['pdo'];

require_role(['super_admin']);

$admin_id = (int) $_SESSION['user_id'];
$input    = json_decode(file_get_contents('php://input'), true) ?? [];
$action   = $input['action'] ?? ($_GET['action'] ?? 'init');

if ($action === 'init') {
    $users = $pdo->query("
        SELECT id, school_id, first_name, middle_name, middle_initial, last_name, suffix,
               full_name, display_name, email, contact_no, role, program_code, status, ga_enabled, created_at
        FROM users
        ORDER BY FIELD(role, 'super_admin', 'librarian', 'faculty', 'student'), id ASC
    ")->fetchAll();

    $settings = [
        'daily_fine_rate'      => '10.00',
        'loan_duration_days'   => '3',
        'dev_mode_quick_fills' => true
    ];
    $stmt_s = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt_s->fetch()) {
        if ($row['setting_key'] === 'dev_mode_quick_fills') {
            $settings['dev_mode_quick_fills'] = ($row['setting_value'] === '1');
        } else {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }

    $logs = $pdo->query("
        SELECT al.*, u.school_id, u.full_name
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.id DESC
        LIMIT 40
    ")->fetchAll();

    echo json_encode([
        'success'  => true,
        'users'    => $users,
        'settings' => $settings,
        'logs'     => $logs
    ]);
    exit();
}

if ($action === 'create_user') {
    $school_id      = trim($input['school_id'] ?? '');
    $first_name     = trim($input['first_name'] ?? '');
    $middle_name    = trim($input['middle_name'] ?? '');
    $middle_initial = trim($input['middle_initial'] ?? '');
    $last_name      = trim($input['last_name'] ?? '');
    $suffix         = trim($input['suffix'] ?? '');
    $display_name   = trim($input['display_name'] ?? '');
    $email          = trim($input['email'] ?? '');
    $contact_no     = trim($input['contact_no'] ?? '');
    $role           = trim($input['role'] ?? 'student');
    $program_code   = trim($input['program_code'] ?? '') ?: null;
    $password       = $input['password'] ?? 'password';

    if ($school_id === '' || $first_name === '' || $last_name === '' || $email === '') {
        echo json_encode(['success' => false, 'message' => 'ID, First Name, Last Name, and Email are required.']);
        exit();
    }

    if ($role === 'student' && !is_valid_student_id($school_id)) {
        echo json_encode(['success' => false, 'message' => 'Student ID must be a 9-digit number (e.g. 240103543).']);
        exit();
    }

    if ($middle_initial === '' && $middle_name !== '') {
        $middle_initial = strtoupper(substr($middle_name, 0, 1)) . '.';
    }

    $full_name = compose_full_name($first_name, $middle_initial, $last_name, $suffix);
    if ($display_name === '') {
        $display_name = $first_name;
    }

    $hash   = password_hash($password, PASSWORD_DEFAULT);
    $secret = 'SEC_' . strtoupper(bin2hex(random_bytes(8)));

    try {
        $stmt = $pdo->prepare("
            INSERT INTO users
                (school_id, first_name, middle_name, middle_initial, last_name, suffix, full_name, display_name, email, contact_no, password_hash, role, program_code, totp_secret, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        $stmt->execute([
            $school_id, $first_name, $middle_name, $middle_initial, $last_name, $suffix,
            $full_name, $display_name, $email, $contact_no, $hash, $role, $program_code, $secret
        ]);

        log_action($pdo, $admin_id, 'USER_CREATE', "Created {$role} account #{$school_id} ({$full_name}).");
        echo json_encode(['success' => true, 'message' => "Account #{$school_id} ({$full_name}) created!"]);
    } catch (\Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'School ID or Email already exists.']);
    }
    exit();
}

if ($action === 'update_user') {
    $target_id      = (int) ($input['id'] ?? 0);
    $school_id      = trim($input['school_id'] ?? '');
    $first_name     = trim($input['first_name'] ?? '');
    $middle_name    = trim($input['middle_name'] ?? '');
    $middle_initial = trim($input['middle_initial'] ?? '');
    $last_name      = trim($input['last_name'] ?? '');
    $suffix         = trim($input['suffix'] ?? '');
    $display_name   = trim($input['display_name'] ?? '');
    $email          = trim($input['email'] ?? '');
    $contact_no     = trim($input['contact_no'] ?? '');
    $role           = trim($input['role'] ?? 'student');
    $program_code   = trim($input['program_code'] ?? '') ?: null;
    $status         = trim($input['status'] ?? 'active');
    $new_password   = trim($input['new_password'] ?? '');

    if ($target_id <= 0 || $school_id === '' || $first_name === '' || $last_name === '' || $email === '') {
        echo json_encode(['success' => false, 'message' => 'ID, First Name, Last Name, and Email are required.']);
        exit();
    }

    if ($middle_initial === '' && $middle_name !== '') {
        $middle_initial = strtoupper(substr($middle_name, 0, 1)) . '.';
    }

    $full_name = compose_full_name($first_name, $middle_initial, $last_name, $suffix);

    try {
        $stmt = $pdo->prepare("
            UPDATE users
            SET school_id = ?, first_name = ?, middle_name = ?, middle_initial = ?, last_name = ?, suffix = ?,
                full_name = ?, display_name = ?, email = ?, contact_no = ?, role = ?, program_code = ?, status = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $school_id, $first_name, $middle_name, $middle_initial, $last_name, $suffix,
            $full_name, $display_name, $email, $contact_no, $role, $program_code, $status, $target_id
        ]);

        if ($new_password !== '') {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $target_id]);
        }

        log_action($pdo, $admin_id, 'USER_EDIT', "Super Admin updated account #{$school_id} ({$full_name}).");
        echo json_encode(['success' => true, 'message' => "Account #{$school_id} ({$full_name}) updated!"]);
    } catch (\Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'Update failed (ID or Email conflict): ' . $e->getMessage()]);
    }
    exit();
}

if ($action === 'delete_user') {
    $target_id = (int) ($input['user_id'] ?? 0);

    if ($target_id === $admin_id) {
        echo json_encode(['success' => false, 'message' => 'You cannot delete your own active Super Admin account.']);
        exit();
    }

    $stmt = $pdo->prepare("SELECT school_id, full_name FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$target_id]);
    $u = $stmt->fetch();

    if (!$u) {
        echo json_encode(['success' => false, 'message' => 'Account not found.']);
        exit();
    }

    $stmt_active = $pdo->prepare("SELECT COUNT(*) FROM borrow_transactions WHERE borrower_id = ? AND status IN ('active', 'overdue')");
    $stmt_active->execute([$target_id]);
    if ((int)$stmt_active->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete account while user still has an active unreturned book loan.']);
        exit();
    }

    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM room_reservation_members WHERE user_id = ?")->execute([$target_id]);
        $pdo->prepare("DELETE FROM room_reservations WHERE representative_id = ?")->execute([$target_id]);
        $pdo->prepare("UPDATE room_reservations SET verified_by_id = NULL WHERE verified_by_id = ?")->execute([$target_id]);
        $pdo->prepare("DELETE FROM borrow_transactions WHERE borrower_id = ?")->execute([$target_id]);
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$target_id]);

        log_action($pdo, $admin_id, 'USER_DELETE', "Super Admin permanently deleted account #{$u['school_id']} ({$u['full_name']}).");
        $pdo->commit();

        echo json_encode(['success' => true, 'message' => "Account #{$u['school_id']} ({$u['full_name']}) permanently deleted."]);
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Could not delete account: ' . $e->getMessage()]);
    }
    exit();
}

if ($action === 'save_settings') {
    $daily_fine = max(0, (float) ($input['daily_fine_rate'] ?? 10.00));
    $loan_days  = max(1, min(30, (int) ($input['loan_duration_days'] ?? 3)));
    $dev_mode   = !empty($input['dev_mode_quick_fills']) ? '1' : '0';

    $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute(['daily_fine_rate', number_format($daily_fine, 2, '.', '')]);
    $stmt->execute(['loan_duration_days', (string) $loan_days]);
    $stmt->execute(['dev_mode_quick_fills', $dev_mode]);

    log_action($pdo, $admin_id, 'SETTINGS_UPDATE', "Updated fine rate to ₱{$daily_fine}, loan duration to {$loan_days}, dev_mode to {$dev_mode}.");
    echo json_encode(['success' => true, 'message' => 'System settings updated successfully!']);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid system action.']);