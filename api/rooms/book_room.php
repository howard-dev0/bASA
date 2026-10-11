<?php
// api/rooms/book_room.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = $GLOBALS['pdo'];

require_role(['student', 'faculty', 'librarian', 'super_admin']);

$user_id   = (int) $_SESSION['user_id'];
$school_id = (string) $_SESSION['school_id'];
$input     = json_decode(file_get_contents('php://input'), true) ?? [];
$action    = $input['action'] ?? ($_GET['action'] ?? 'init');

$today    = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));

// 1. INITIALIZE PAGE DATA
if ($action === 'init') {
    $selected_date = $_GET['date'] ?? $today;
    if (!in_array($selected_date, [$today, $tomorrow], true)) {
        $selected_date = $today;
    }

    $stmt_rooms = $pdo->query("
        SELECT id, space_code, space_name, min_capacity, max_capacity, current_status
        FROM library_spaces
        WHERE space_type = 'discussion_room'
        ORDER BY space_code ASC
    ");
    $rooms = $stmt_rooms->fetchAll();

    $stmt_taken = $pdo->prepare("
        SELECT rr.id, rr.space_id, rr.start_time, rr.end_time, rr.status, ls.space_code
        FROM room_reservations rr
        JOIN library_spaces ls ON rr.space_id = ls.id
        WHERE rr.reservation_date = ? AND rr.status IN ('pending', 'occupied')
        ORDER BY rr.start_time ASC
    ");
    $stmt_taken->execute([$selected_date]);
    $taken_slots = $stmt_taken->fetchAll();

    $stmt_my = $pdo->prepare("
        SELECT rr.*, ls.space_code, ls.space_name, u.full_name AS rep_name, u.school_id AS rep_school_id
        FROM room_reservations rr
        JOIN library_spaces ls ON rr.space_id = ls.id
        JOIN users u ON rr.representative_id = u.id
        LEFT JOIN room_reservation_members rrm ON rr.id = rrm.reservation_id
        WHERE (rr.representative_id = ? OR rrm.user_id = ?)
        GROUP BY rr.id
        ORDER BY rr.reservation_date DESC, rr.start_time DESC
        LIMIT 10
    ");
    $stmt_my->execute([$user_id, $user_id]);
    $my_bookings = $stmt_my->fetchAll();

    $stmt_mem = $pdo->prepare("
        SELECT u.school_id, u.full_name, u.program_code, rrm.is_present
        FROM room_reservation_members rrm
        JOIN users u ON rrm.user_id = u.id
        WHERE rrm.reservation_id = ?
        ORDER BY rrm.id ASC
    ");
    foreach ($my_bookings as &$b) {
        $stmt_mem->execute([$b['id']]);
        $b['members'] = $stmt_mem->fetchAll();
    }

    echo json_encode([
        'success'     => true,
        'today'       => $today,
        'tomorrow'    => $tomorrow,
        'rooms'       => $rooms,
        'taken_slots' => $taken_slots,
        'my_bookings' => $my_bookings
    ]);
    exit();
}

// 2. LOOKUP A 9-DIGIT STUDENT ID BEFORE TAGGING
if ($action === 'lookup_member') {
    $target_id = trim($input['school_id'] ?? '');

    if (!is_valid_student_id($target_id)) {
        echo json_encode(['success' => false, 'message' => 'Student ID must be a 9-digit number (e.g. 240103538).']);
        exit();
    }

    if ($target_id === $school_id) {
        echo json_encode(['success' => false, 'message' => 'You are already included as the Group Representative.']);
        exit();
    }

    $stmt = $pdo->prepare("
        SELECT id, school_id, full_name, program_code, status
        FROM users
        WHERE school_id = ? AND role IN ('student', 'faculty')
        LIMIT 1
    ");
    $stmt->execute([$target_id]);
    $member = $stmt->fetch();

    if (!$member) {
        echo json_encode(['success' => false, 'message' => "Student ID #{$target_id} is not registered in bASA."]);
        exit();
    }

    if ($member['status'] !== 'active') {
        echo json_encode(['success' => false, 'message' => "Student #{$target_id} account is currently {$member['status']}."]);
        exit();
    }

    echo json_encode(['success' => true, 'member' => $member]);
    exit();
}

// 3. CREATE NEW DISCUSSION ROOM RESERVATION (6-8 PAX POLICY)
if ($action === 'create_booking') {
    $space_id         = (int) ($input['space_id'] ?? 0);
    $reservation_date = trim($input['reservation_date'] ?? '');
    $time_slot        = trim($input['time_slot'] ?? '');
    $purpose          = trim($input['purpose'] ?? '');
    $member_ids       = $input['member_school_ids'] ?? [];

    if (!in_array($reservation_date, [$today, $tomorrow], true)) {
        echo json_encode(['success' => false, 'message' => 'Rule Violation: Reservations can only be made for Today or up to 1 day in advance.']);
        exit();
    }

    $slot_parts = explode('|', $time_slot);
    if (count($slot_parts) !== 2) {
        echo json_encode(['success' => false, 'message' => 'Please select a valid 2-hour reservation time slot.']);
        exit();
    }
    [$start_time, $end_time] = $slot_parts;

    if ($purpose === '') {
        echo json_encode(['success' => false, 'message' => 'Please enter your group research or discussion purpose.']);
        exit();
    }

    $clean_member_ids = array_values(array_unique(array_map('trim', $member_ids)));
    $clean_member_ids = array_values(array_filter($clean_member_ids, fn($id) => $id !== '' && $id !== $school_id));

    $total_group_size = count($clean_member_ids) + 1;
    if ($total_group_size < 6 || $total_group_size > 8) {
        echo json_encode([
            'success' => false,
            'message' => "Capacity Rule: A minimum of 6 and maximum of 8 persons is required. You currently have {$total_group_size} member(s)."
        ]);
        exit();
    }

    $stmt_quota = $pdo->prepare("
        SELECT id, status FROM room_reservations
        WHERE representative_id = ? AND reservation_date = ?
        LIMIT 1
    ");
    $stmt_quota->execute([$user_id, $reservation_date]);
    if ($stmt_quota->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => "Quota Rule: A representative is only allowed to book 1 discussion room per day ({$reservation_date}). Click 'Remove Completed' on your finished booking first to re-test."
        ]);
        exit();
    }

    $stmt_space = $pdo->prepare("SELECT id, space_code, space_name, current_status FROM library_spaces WHERE id = ? AND space_type = 'discussion_room' LIMIT 1");
    $stmt_space->execute([$space_id]);
    $space = $stmt_space->fetch();
    if (!$space || $space['current_status'] === 'maintenance') {
        echo json_encode(['success' => false, 'message' => 'Selected Discussion Room is unavailable or under maintenance.']);
        exit();
    }

    $stmt_conflict = $pdo->prepare("
        SELECT id FROM room_reservations
        WHERE space_id = ?
          AND reservation_date = ?
          AND status IN ('pending', 'occupied')
          AND (start_time < ? AND end_time > ?)
        LIMIT 1
    ");
    $stmt_conflict->execute([$space_id, $reservation_date, $end_time, $start_time]);
    if ($stmt_conflict->fetch()) {
        echo json_encode(['success' => false, 'message' => "{$space['space_name']} is already reserved during that time slot."]);
        exit();
    }

    $resolved_user_ids = [$user_id];
    $stmt_u = $pdo->prepare("SELECT id, school_id, status FROM users WHERE school_id = ? LIMIT 1");

    foreach ($clean_member_ids as $mid) {
        if (!is_valid_student_id($mid)) {
            echo json_encode(['success' => false, 'message' => "Invalid 9-digit Student ID format: {$mid}"]);
            exit();
        }
        $stmt_u->execute([$mid]);
        $u_row = $stmt_u->fetch();
        if (!$u_row || $u_row['status'] !== 'active') {
            echo json_encode(['success' => false, 'message' => "Student ID #{$mid} is not found or not active."]);
            exit();
        }
        $resolved_user_ids[] = (int) $u_row['id'];
    }

    try {
        $pdo->beginTransaction();

        $stmt_ins = $pdo->prepare("
            INSERT INTO room_reservations 
                (space_id, representative_id, reservation_date, start_time, end_time, purpose, present_headcount, status)
            VALUES (?, ?, ?, ?, ?, ?, 0, 'pending')
        ");
        $stmt_ins->execute([$space_id, $user_id, $reservation_date, $start_time, $end_time, $purpose]);
        $reservation_id = (int) $pdo->lastInsertId();

        $stmt_mem_ins = $pdo->prepare("
            INSERT INTO room_reservation_members (reservation_id, user_id, is_present)
            VALUES (?, ?, FALSE)
        ");
        foreach ($resolved_user_ids as $uid) {
            $stmt_mem_ins->execute([$reservation_id, $uid]);
        }

        if ($reservation_date === $today && $space['current_status'] === 'available') {
            $pdo->prepare("UPDATE library_spaces SET current_status = 'reserved' WHERE id = ?")->execute([$space_id]);
        }

        log_action($pdo, $user_id, 'ROOM_RESERVE', "Booked {$space['space_code']} for {$reservation_date} ({$start_time}-{$end_time}) with {$total_group_size} members.");
        $pdo->commit();

        echo json_encode([
            'success'        => true,
            'reservation_id' => $reservation_id,
            'message'        => "{$space['space_name']} reserved! Remember: At least 6 group members must be physically present at the Help Desk at {$start_time}."
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Could not save reservation: ' . $e->getMessage()]);
    }
    exit();
}

// 4. CANCEL PENDING RESERVATION
if ($action === 'cancel_booking') {
    $reservation_id = (int) ($input['reservation_id'] ?? 0);

    $stmt = $pdo->prepare("SELECT id, space_id, status FROM room_reservations WHERE id = ? AND representative_id = ? LIMIT 1");
    $stmt->execute([$reservation_id, $user_id]);
    $booking = $stmt->fetch();

    if (!$booking || $booking['status'] !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'Only pending reservations can be cancelled by the representative.']);
        exit();
    }

    $pdo->prepare("DELETE FROM room_reservations WHERE id = ?")->execute([$reservation_id]);

    $stmt_rem = $pdo->prepare("SELECT COUNT(*) FROM room_reservations WHERE space_id = ? AND reservation_date = CURDATE() AND status IN ('pending', 'occupied')");
    $stmt_rem->execute([$booking['space_id']]);
    if ((int)$stmt_rem->fetchColumn() === 0) {
        $pdo->prepare("UPDATE library_spaces SET current_status = 'available' WHERE id = ? AND current_status = 'reserved'")->execute([$booking['space_id']]);
    }

    log_action($pdo, $user_id, 'ROOM_CANCEL', "Cancelled room reservation #{$reservation_id}");
    echo json_encode(['success' => true, 'message' => 'Reservation cancelled and slot released.']);
    exit();
}

// 5. MARK DISCUSSION ROOM SESSION AS COMPLETED (Student Side)
if ($action === 'complete_booking') {
    $reservation_id = (int) ($input['reservation_id'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT rr.id, rr.space_id, rr.status, ls.space_code, ls.space_name
        FROM room_reservations rr
        JOIN library_spaces ls ON rr.space_id = ls.id
        LEFT JOIN room_reservation_members rrm ON rr.id = rrm.reservation_id
        WHERE rr.id = ? AND (rr.representative_id = ? OR rrm.user_id = ?)
        LIMIT 1
    ");
    $stmt->execute([$reservation_id, $user_id, $user_id]);
    $booking = $stmt->fetch();

    if (!$booking || !in_array($booking['status'], ['occupied', 'pending'], true)) {
        echo json_encode(['success' => false, 'message' => 'Only active or occupied reservations can be marked as completed.']);
        exit();
    }

    $pdo->prepare("UPDATE room_reservations SET status = 'completed' WHERE id = ?")->execute([$reservation_id]);
    $pdo->prepare("UPDATE library_spaces SET current_status = 'available' WHERE id = ?")->execute([$booking['space_id']]);

    log_action($pdo, $user_id, 'ROOM_COMPLETED', "Student completed session for {$booking['space_code']} (Reservation #{$reservation_id}).");

    echo json_encode([
        'success' => true,
        'message' => "{$booking['space_name']} session marked as COMPLETED! You can now click Remove Completed."
    ]);
    exit();
}

// 6. REMOVE COMPLETED / FINISHED DISCUSSION ROOM RECORD (Student Side)
if ($action === 'remove_booking') {
    $reservation_id = (int) ($input['reservation_id'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT rr.id, rr.space_id, rr.status, ls.space_code
        FROM room_reservations rr
        JOIN library_spaces ls ON rr.space_id = ls.id
        LEFT JOIN room_reservation_members rrm ON rr.id = rrm.reservation_id
        WHERE rr.id = ? AND (rr.representative_id = ? OR rrm.user_id = ?)
        LIMIT 1
    ");
    $stmt->execute([$reservation_id, $user_id, $user_id]);
    $booking = $stmt->fetch();

    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'Reservation record not found.']);
        exit();
    }

    if (!in_array($booking['status'], ['completed', 'cancelled', 'no_show', 'violation'], true)) {
        echo json_encode(['success' => false, 'message' => 'Please mark the session as Completed first before removing it.']);
        exit();
    }

    $pdo->prepare("DELETE FROM room_reservations WHERE id = ?")->execute([$reservation_id]);
    log_action($pdo, $user_id, 'ROOM_REMOVE', "Student removed completed reservation #{$reservation_id} ({$booking['space_code']}).");

    echo json_encode([
        'success' => true,
        'message' => 'Completed discussion room reservation removed.'
    ]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid request action.']);