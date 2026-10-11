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

// 1. FETCH USERS, SETTINGS & AUDIT LOGS
if ($action === 'init') {
    $users = $pdo->query("
        SELECT id, school_id, full_name, email, role, program_code, status, created_at
        FROM users
        ORDER BY FIELD(role, 'super_admin', 'librarian', 'faculty', 'student'), id ASC
    ")->fetchAll();

    $settings = [];
    $stmt_s = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt_s->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
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

// 2. CREATE NEW USER / LIBRARIAN ACCOUNT
if ($action === 'create_user') {
    $school_id    = trim($input['school_id'] ?? '');
    $full_name    = trim($input['full_name'] ?? '');
    $email        = trim($input['email'] ?? '');
    $role         = trim($input['role'] ?? 'librarian');
    $program_code = trim($input['program_code'] ?? '') ?: null;
    $password     = $input['password'] ?? 'password';

    if ($school_id === '' || $full_name === '' || $email === '') {
        echo json_encode(['success' => false, 'message' => 'ID, Full Name, and Email are required.']);
        exit();
    }

    if ($role === 'student' && !is_valid_student_id($school_id)) {
        echo json_encode(['success' => false, 'message' => 'Student ID must be a 9-digit number (e.g. 240103543).']);
        exit();
    }

    $hash   = password_hash($password, PASSWORD_DEFAULT);
    $secret = 'SEC_' . strtoupper(bin2hex(random_bytes(8)));

    try {
        $stmt = $pdo->prepare("
            INSERT INTO users (school_id, full_name, email, password_hash, role, program_code, totp_secret, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        $stmt->execute([$school_id, $full_name, $email, $hash, $role, $program_code, $secret]);

        log_action($pdo, $admin_id, 'USER_CREATE', "Created {$role} account #{$school_id} ({$full_name}).");
        echo json_encode(['success' => true, 'message' => "Account #{$school_id} ({$full_name}) created!"]);
    } catch (\Throwable $e) {
        echo json_encode(['success' => false, 'message' => 'ID or Email already exists.']);
    }
    exit();
}

// 3. TOGGLE USER ACCOUNT STATUS (ACTIVE / SUSPENDED)
if ($action === 'toggle_user_status') {
    $target_id = (int) ($input['user_id'] ?? 0);
    if ($target_id === $admin_id) {
        echo json_encode(['success' => false, 'message' => 'You cannot suspend your own Super Admin account.']);
        exit();
    }

    $stmt = $pdo->prepare("SELECT id, school_id, status FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$target_id]);
    $u = $stmt->fetch();

    if (!$u) {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
        exit();
    }

    $new_status = ($u['status'] === 'active') ? 'suspended' : 'active';
    $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$new_status, $target_id]);

    log_action($pdo, $admin_id, 'USER_STATUS', "Changed account #{$u['school_id']} status to " . strtoupper($new_status));
    echo json_encode(['success' => true, 'message' => "Account #{$u['school_id']} is now " . strtoupper($new_status) . "."]);
    exit();
}

// 4. UPDATE GLOBAL SYSTEM SETTINGS (Fine Rate & Loan Duration)
if ($action === 'save_settings') {
    $daily_fine = max(0, (float) ($input['daily_fine_rate'] ?? 10.00));
    $loan_days  = max(1, min(30, (int) ($input['loan_duration_days'] ?? 3)));

    $stmt = $pdo->prepare("UPDATE system_settings SET setting_value = ? WHERE setting_key = ?");
    $stmt->execute([number_format($daily_fine, 2, '.', ''), 'daily_fine_rate']);
    $stmt->execute([(string) $loan_days, 'loan_duration_days']);

    log_action($pdo, $admin_id, 'SETTINGS_UPDATE', "Updated daily_fine_rate to ₱{$daily_fine} and loan_duration_days to {$loan_days} days.");
    echo json_encode(['success' => true, 'message' => 'Library policy settings updated!']);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid system action.']);