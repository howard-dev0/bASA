<?php
// api/rooms/checkin_group.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = $GLOBALS['pdo'];

require_role(['librarian', 'super_admin']);

$staff_id = (int) $_SESSION['user_id'];
$input    = json_decode(file_get_contents('php://input'), true) ?? [];
$action   = $input['action'] ?? ($_GET['action'] ?? 'list');

// 1. FETCH ALL SPACES & RESERVATIONS FOR SELECTED DATE
if ($action === 'list') {
    $date = $_GET['date'] ?? date('Y-m-d');

    $spaces = $pdo->query("
        SELECT * FROM library_spaces ORDER BY space_type ASC, space_code ASC
    ")->fetchAll();

    $stmt_res = $pdo->prepare("
        SELECT rr.*, ls.space_code, ls.space_name,
               u.school_id AS rep_school_id, u.full_name AS rep_name, u.program_code AS rep_program
        FROM room_reservations rr
        JOIN library_spaces ls ON rr.space_id = ls.id
        JOIN users u ON rr.representative_id = u.id
        WHERE rr.reservation_date = ?
        ORDER BY FIELD(rr.status, 'occupied', 'pending', 'completed', 'violation', 'no_show', 'cancelled'), rr.start_time ASC
    ");
    $stmt_res->execute([$date]);
    $reservations = $stmt_res->fetchAll();

    $stmt_mem = $pdo->prepare("
        SELECT rrm.id AS rm_id, rrm.user_id, rrm.is_present, rrm.checked_in_at,
               u.school_id, u.full_name, u.program_code, u.photo_path
        FROM room_reservation_members rrm
        JOIN users u ON rrm.user_id = u.id
        WHERE rrm.reservation_id = ?
        ORDER BY rrm.id ASC
    ");

    foreach ($reservations as &$r) {
        $stmt_mem->execute([$r['id']]);
        $r['members'] = $stmt_mem->fetchAll();
    }

    echo json_encode([
        'success'      => true,
        'spaces'       => $spaces,
        'reservations' => $reservations
    ]);
    exit();
}

// 2. TOGGLE INDIVIDUAL MEMBER PHYSICAL PRESENCE
if ($action === 'toggle_member') {
    $reservation_id = (int) ($input['reservation_id'] ?? 0);
    $member_user_id = (int) ($input['user_id'] ?? 0);

    $stmt = $pdo->prepare("SELECT id, is_present FROM room_reservation_members WHERE reservation_id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$reservation_id, $member_user_id]);
    $row = $stmt->fetch();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Group member record not found.']);
        exit();
    }

    $new_state = $row['is_present'] ? 0 : 1;
    $checked_time = $new_state ? date('Y-m-d H:i:s') : null;

    $pdo->prepare("UPDATE room_reservation_members SET is_present = ?, checked_in_at = ? WHERE id = ?")
        ->execute([$new_state, $checked_time, $row['id']]);

    $stmt_cnt = $pdo->prepare("SELECT COUNT(*) FROM room_reservation_members WHERE reservation_id = ? AND is_present = 1");
    $stmt_cnt->execute([$reservation_id]);
    $headcount = (int) $stmt_cnt->fetchColumn();

    $pdo->prepare("UPDATE room_reservations SET present_headcount = ? WHERE id = ?")
        ->execute([$headcount, $reservation_id]);

    echo json_encode([
        'success'           => true,
        'is_present'        => (bool) $new_state,
        'present_headcount' => $headcount
    ]);
    exit();
}

// 3. MARK ALL MEMBERS PRESENT
if ($action === 'mark_all_present') {
    $reservation_id = (int) ($input['reservation_id'] ?? 0);
    $now = date('Y-m-d H:i:s');

    $pdo->prepare("UPDATE room_reservation_members SET is_present = 1, checked_in_at = ? WHERE reservation_id = ?")
        ->execute([$now, $reservation_id]);

    $stmt_cnt = $pdo->prepare("SELECT COUNT(*) FROM room_reservation_members WHERE reservation_id = ? AND is_present = 1");
    $stmt_cnt->execute([$reservation_id]);
    $headcount = (int) $stmt_cnt->fetchColumn();

    $pdo->prepare("UPDATE room_reservations SET present_headcount = ? WHERE id = ?")
        ->execute([$headcount, $reservation_id]);

    echo json_encode(['success' => true, 'present_headcount' => $headcount]);
    exit();
}

// 4. UNLOCK ROOM & APPROVE CHECK-IN (>= 6 PRESENT)
if ($action === 'approve_checkin') {
    $reservation_id = (int) ($input['reservation_id'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT rr.*, ls.space_code, ls.space_name
        FROM room_reservations rr
        JOIN library_spaces ls ON rr.space_id = ls.id
        WHERE rr.id = ? LIMIT 1
    ");
    $stmt->execute([$reservation_id]);
    $res = $stmt->fetch();

    if (!$res) {
        echo json_encode(['success' => false, 'message' => 'Reservation not found.']);
        exit();
    }

    if ((int)$res['present_headcount'] < 6) {
        echo json_encode([
            'success' => false,
            'message' => "Policy Step 4 Violation: Only {$res['present_headcount']} member(s) marked present. A minimum of 6 members must be physically present at the Help Desk to unlock {$res['space_name']}."
        ]);
        exit();
    }

    $pdo->prepare("UPDATE room_reservations SET status = 'occupied', verified_by_id = ? WHERE id = ?")
        ->execute([$staff_id, $reservation_id]);

    $pdo->prepare("UPDATE library_spaces SET current_status = 'occupied' WHERE id = ?")
        ->execute([$res['space_id']]);

    log_action($pdo, $staff_id, 'ROOM_CHECKIN', "Verified {$res['present_headcount']} members present and unlocked {$res['space_code']} (Reservation #{$reservation_id}).");

    echo json_encode([
        'success' => true,
        'message' => "{$res['space_name']} is now OCCUPIED ({$res['present_headcount']} members verified)."
    ]);
    exit();
}

// 5. COMPLETE SESSION / MARK NO-SHOW / LOG RULE VIOLATION
if ($action === 'update_reservation_status') {
    $reservation_id  = (int) ($input['reservation_id'] ?? 0);
    $new_status      = trim($input['status'] ?? '');
    $violation_notes = trim($input['violation_notes'] ?? '');

    if (!in_array($new_status, ['completed', 'no_show', 'violation'], true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status update.']);
        exit();
    }

    $stmt = $pdo->prepare("SELECT rr.*, ls.space_code FROM room_reservations rr JOIN library_spaces ls ON rr.space_id = ls.id WHERE rr.id = ? LIMIT 1");
    $stmt->execute([$reservation_id]);
    $res = $stmt->fetch();

    if (!$res) {
        echo json_encode(['success' => false, 'message' => 'Reservation not found.']);
        exit();
    }

    $pdo->prepare("UPDATE room_reservations SET status = ?, violation_notes = ?, verified_by_id = ? WHERE id = ?")
        ->execute([$new_status, $violation_notes ?: null, $staff_id, $reservation_id]);

    $pdo->prepare("UPDATE library_spaces SET current_status = 'available' WHERE id = ?")
        ->execute([$res['space_id']]);

    $event_type = $new_status === 'violation' ? 'ROOM_VIOLATION' : 'ROOM_STATUS';
    $desc = "Updated {$res['space_code']} Reservation #{$reservation_id} to " . strtoupper($new_status) . ($violation_notes ? " — Notes: {$violation_notes}" : "");
    log_action($pdo, $staff_id, $event_type, $desc);

    echo json_encode(['success' => true, 'message' => "Reservation marked as " . strtoupper($new_status) . " and {$res['space_code']} released."]);
    exit();
}

// 6. REMOVE / DELETE COMPLETED RESERVATION RECORD (Librarian Side)
if ($action === 'delete_reservation') {
    $reservation_id = (int) ($input['reservation_id'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT rr.*, ls.space_code
        FROM room_reservations rr
        JOIN library_spaces ls ON rr.space_id = ls.id
        WHERE rr.id = ? LIMIT 1
    ");
    $stmt->execute([$reservation_id]);
    $res = $stmt->fetch();

    if (!$res) {
        echo json_encode(['success' => false, 'message' => 'Reservation record not found.']);
        exit();
    }

    $pdo->prepare("DELETE FROM room_reservations WHERE id = ?")->execute([$reservation_id]);

    $stmt_rem = $pdo->prepare("SELECT COUNT(*) FROM room_reservations WHERE space_id = ? AND reservation_date = CURDATE() AND status IN ('pending', 'occupied')");
    $stmt_rem->execute([$res['space_id']]);
    if ((int)$stmt_rem->fetchColumn() === 0) {
        $pdo->prepare("UPDATE library_spaces SET current_status = 'available' WHERE id = ? AND current_status IN ('reserved', 'occupied')")->execute([$res['space_id']]);
    }

    log_action($pdo, $staff_id, 'ROOM_RECORD_DELETE', "Librarian removed {$res['status']} reservation #{$reservation_id} ({$res['space_code']}).");

    echo json_encode([
        'success' => true,
        'message' => "Removed {$res['space_code']} reservation record."
    ]);
    exit();
}

// 7. DIRECT SPACE STATUS TOGGLE
if ($action === 'toggle_space_status') {
    $space_id   = (int) ($input['space_id'] ?? 0);
    $new_status = trim($input['current_status'] ?? 'available');

    if (!in_array($new_status, ['available', 'reserved', 'occupied', 'maintenance'], true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid space status.']);
        exit();
    }

    $pdo->prepare("UPDATE library_spaces SET current_status = ? WHERE id = ?")
        ->execute([$new_status, $space_id]);

    echo json_encode(['success' => true, 'message' => 'Space status updated.']);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);