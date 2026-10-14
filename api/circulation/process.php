<?php
// api/circulation/process.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = $GLOBALS['pdo'];

require_role(['librarian', 'super_admin']);

$staff_id = (int) $_SESSION['user_id'];
$input    = json_decode(file_get_contents('php://input'), true) ?? [];
$action   = $input['action'] ?? ($_GET['action'] ?? '');

// Fetch system settings (fine rate & loan duration)
$settings = [];
$stmt_set = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
while ($r = $stmt_set->fetch()) {
    $settings[$r['setting_key']] = $r['setting_value'];
}
$daily_fine_rate    = (float) ($settings['daily_fine_rate'] ?? 10.00);
$loan_duration_days = (int) ($settings['loan_duration_days'] ?? 3);

// Helper: Attach Student Unpaid Balance / Debt Details
function attach_student_debt(object $pdo, array $student): array {
    $stmt_bal = $pdo->prepare("
        SELECT COALESCE(SUM(fine_amount), 0.00)
        FROM borrow_transactions
        WHERE borrower_id = ? AND fine_status = 'unpaid' AND fine_amount > 0
    ");
    $stmt_bal->execute([$student['id']]);
    $student['unpaid_balance'] = (float) $stmt_bal->fetchColumn();

    $stmt_items = $pdo->prepare("
        SELECT bt.id, bt.borrowed_at, bt.due_date, bt.returned_at, bt.fine_amount,
               ia.accession_no, ci.title
        FROM borrow_transactions bt
        JOIN item_accessions ia ON bt.accession_id = ia.id
        JOIN catalog_items ci ON ia.catalog_id = ci.id
        WHERE bt.borrower_id = ? AND bt.fine_status = 'unpaid' AND bt.fine_amount > 0
        ORDER BY bt.id DESC
    ");
    $stmt_items->execute([$student['id']]);
    $student['unpaid_items'] = $stmt_items->fetchAll();

    return $student;
}

// Helper: Fetch Book Accession + Active Loan Details
function get_accession_details(object $pdo, string $accession_no, float $daily_fine_rate): ?array {
    $stmt = $pdo->prepare("
        SELECT ia.*, ci.material_type, ci.isbn_or_doc_id, ci.title, ci.authors,
               ci.subject_or_program, ci.publish_year, ci.cover_image
        FROM item_accessions ia
        JOIN catalog_items ci ON ia.catalog_id = ci.id
        WHERE ia.accession_no = ?
        LIMIT 1
    ");
    $stmt->execute([$accession_no]);
    $acc = $stmt->fetch();
    if (!$acc) return null;

    if ($acc['status'] === 'borrowed') {
        $stmt_tx = $pdo->prepare("
            SELECT bt.*, u.school_id AS borrower_school_id, u.full_name AS borrower_name, u.program_code
            FROM borrow_transactions bt
            JOIN users u ON bt.borrower_id = u.id
            WHERE bt.accession_id = ? AND bt.status IN ('active', 'overdue')
            ORDER BY bt.id DESC LIMIT 1
        ");
        $stmt_tx->execute([$acc['id']]);
        $tx = $stmt_tx->fetch();

        if ($tx) {
            $due_ts = strtotime($tx['due_date']);
            $now_ts = time();
            $overdue_days = 0;
            $computed_fine = 0.00;
            if ($now_ts > $due_ts) {
                $overdue_days  = (int) ceil(($now_ts - $due_ts) / 86400);
                $computed_fine = round($overdue_days * $daily_fine_rate, 2);
            }
            $tx['overdue_days']  = $overdue_days;
            $tx['computed_fine'] = $computed_fine;
            $acc['active_loan']  = $tx;
        }
    }
    return $acc;
}

// 1. SMART QR DECODER
if ($action === 'scan_decode') {
    $raw_code = trim($input['qr_code'] ?? '');
    if ($raw_code === '') {
        echo json_encode(['success' => false, 'message' => 'Empty scan payload.']);
        exit();
    }

    if (str_starts_with($raw_code, 'BASA-EXP|') || str_starts_with($raw_code, 'BASA-RET|')) {
        $is_return_qr = str_starts_with($raw_code, 'BASA-RET|');
        $parts = explode('|', $raw_code);
        if (count($parts) !== 5) {
            echo json_encode(['success' => false, 'message' => 'Malformed QR code.']);
            exit();
        }
        [, $school_id, $accession_no, $scanned_window, $scanned_sig] = $parts;
        $scanned_window = (int) $scanned_window;

        $stmt_u = $pdo->prepare("SELECT id, school_id, full_name, role, program_code, photo_path, totp_secret, status FROM users WHERE school_id = ? LIMIT 1");
        $stmt_u->execute([$school_id]);
        $user = $stmt_u->fetch();

        if (!$user || $user['status'] !== 'active') {
            echo json_encode(['success' => false, 'message' => 'Student account not found or inactive.']);
            exit();
        }

        $current_window = (int) floor(time() / TOTP_WINDOW);
        $sig_valid = false;
        foreach ([$current_window, $current_window - 1] as $valid_w) {
            if ($scanned_window === $valid_w) {
                $data_to_sign = $user['school_id'] . '|' . $accession_no . '|' . $valid_w . '|' . $user['totp_secret'];
                $expected_sig = hash_hmac('sha256', $data_to_sign, SYSTEM_HMAC_KEY);
                if (hash_equals($expected_sig, $scanned_sig)) {
                    $sig_valid = true;
                    break;
                }
            }
        }

        if (!$sig_valid) {
            echo json_encode(['success' => false, 'message' => 'QR Token Expired! Please ask the student to show their live screen.']);
            exit();
        }

        unset($user['totp_secret']);
        $user = attach_student_debt($pdo, $user);

        $book = get_accession_details($pdo, $accession_no, $daily_fine_rate);
        if (!$book) {
            echo json_encode(['success' => false, 'message' => "Book accession {$accession_no} not found."]);
            exit();
        }

        $label = $is_return_qr ? 'Return QR Verified!' : 'Express Borrow QR Verified!';
        echo json_encode([
            'success'   => true,
            'scan_type' => 'express_combo',
            'student'   => $user,
            'book'      => $book,
            'message'   => "{$label} Student #{$user['school_id']} + {$accession_no} loaded."
        ]);
        exit();
    }

    if (str_starts_with($raw_code, 'BASA-USR|')) {
        $verification = verify_student_totp($raw_code, $pdo);
        if (!$verification['valid']) {
            echo json_encode(['success' => false, 'message' => $verification['message']]);
            exit();
        }
        $student = attach_student_debt($pdo, $verification['user']);
        $debt_note = $student['unpaid_balance'] > 0 ? " (Unpaid Balance: ₱" . number_format($student['unpaid_balance'], 2) . ")" : "";
        echo json_encode([
            'success'   => true,
            'scan_type' => 'student_qr',
            'student'   => $student,
            'message'   => "Live 30s TOTP Verified for {$student['full_name']} (#{$student['school_id']}){$debt_note}."
        ]);
        exit();
    }

    if (str_starts_with($raw_code, 'SIM-USR|')) {
        $sim_id = trim(substr($raw_code, 8));
        $stmt_u = $pdo->prepare("SELECT school_id, totp_secret FROM users WHERE school_id = ? LIMIT 1");
        $stmt_u->execute([$sim_id]);
        $u_sec = $stmt_u->fetch();
        if (!$u_sec) {
            echo json_encode(['success' => false, 'message' => "Student #{$sim_id} not found."]);
            exit();
        }
        $live_totp = generate_student_totp($u_sec['school_id'], $u_sec['totp_secret']);
        $verification = verify_student_totp($live_totp['qr_payload'], $pdo);
        $student = attach_student_debt($pdo, $verification['user']);
        $debt_note = $student['unpaid_balance'] > 0 ? " (Unpaid Balance: ₱" . number_format($student['unpaid_balance'], 2) . ")" : "";
        echo json_encode([
            'success'   => true,
            'scan_type' => 'student_qr',
            'student'   => $student,
            'message'   => "Simulated Live 30s TOTP Scan for {$student['full_name']} (#{$sim_id}){$debt_note}."
        ]);
        exit();
    }

    $book = get_accession_details($pdo, $raw_code, $daily_fine_rate);
    if ($book) {
        echo json_encode([
            'success'   => true,
            'scan_type' => 'book_qr',
            'book'      => $book,
            'message'   => "Loaded {$book['accession_no']} ({$book['title']}) — Status: " . strtoupper($book['status'])
        ]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => "Unrecognized QR or Accession Code: {$raw_code}"]);
    exit();
}

// 2. COMMIT BOOK CHECKOUT
if ($action === 'checkout') {
    $student_id   = (int) ($input['student_id'] ?? 0);
    $accession_id = (int) ($input['accession_id'] ?? 0);
    $custom_days  = (int) ($input['loan_days'] ?? $loan_duration_days);
    if ($custom_days < 1 || $custom_days > 14) $custom_days = $loan_duration_days;

    $stmt_u = $pdo->prepare("SELECT id, school_id, full_name, status FROM users WHERE id = ? LIMIT 1");
    $stmt_u->execute([$student_id]);
    $student = $stmt_u->fetch();
    if (!$student || $student['status'] !== 'active') {
        echo json_encode(['success' => false, 'message' => 'Valid, active student scan is required before checkout.']);
        exit();
    }

    $stmt_a = $pdo->prepare("
        SELECT ia.*, ci.title 
        FROM item_accessions ia 
        JOIN catalog_items ci ON ia.catalog_id = ci.id 
        WHERE ia.id = ? LIMIT 1
    ");
    $stmt_a->execute([$accession_id]);
    $acc = $stmt_a->fetch();

    if (!$acc || $acc['status'] !== 'available') {
        echo json_encode(['success' => false, 'message' => 'This book copy is not currently available for checkout.']);
        exit();
    }

    $borrowed_at = date('Y-m-d H:i:s');
    $due_date    = date('Y-m-d H:i:s', strtotime("+{$custom_days} days"));

    try {
        $pdo->beginTransaction();

        $stmt_ins = $pdo->prepare("
            INSERT INTO borrow_transactions (accession_id, borrower_id, issued_by_id, borrowed_at, due_date, status)
            VALUES (?, ?, ?, ?, ?, 'active')
        ");
        $stmt_ins->execute([$acc['id'], $student['id'], $staff_id, $borrowed_at, $due_date]);

        $pdo->prepare("UPDATE item_accessions SET status = 'borrowed' WHERE id = ?")
            ->execute([$acc['id']]);

        log_action($pdo, $staff_id, 'BOOK_BORROW', "Issued {$acc['accession_no']} ({$acc['title']}) to Student #{$student['school_id']}. Due: {$due_date}");
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Checkout Complete! {$acc['accession_no']} issued to {$student['full_name']} (Due: " . date('M d, Y', strtotime($due_date)) . ")."
        ]);
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

// 3. CLOSED-LOOP BOOK RETURN & CUSTOM DAMAGED FINE SETTLEMENT
if ($action === 'return_book') {
    $accession_id   = (int) ($input['accession_id'] ?? 0);
    $fine_action    = trim($input['fine_status'] ?? 'none');
    $book_condition = trim($input['book_condition'] ?? 'good');
    $damaged_fee    = max(0.00, (float) ($input['damaged_fee'] ?? 300.00));

    $stmt_tx = $pdo->prepare("
        SELECT bt.*, ia.accession_no, ia.placement_code, ci.title, u.school_id, u.full_name
        FROM borrow_transactions bt
        JOIN item_accessions ia ON bt.accession_id = ia.id
        JOIN catalog_items ci ON ia.catalog_id = ci.id
        JOIN users u ON bt.borrower_id = u.id
        WHERE bt.accession_id = ? AND bt.status IN ('active', 'overdue')
        ORDER BY bt.id DESC LIMIT 1
    ");
    $stmt_tx->execute([$accession_id]);
    $tx = $stmt_tx->fetch();

    if (!$tx) {
        echo json_encode(['success' => false, 'message' => 'No active borrow transaction found for this book copy.']);
        exit();
    }

    $due_ts = strtotime($tx['due_date']);
    $now_ts = time();
    $fine_amount = 0.00;

    // Calculate Overdue
    if ($now_ts > $due_ts) {
        $overdue_days = (int) ceil(($now_ts - $due_ts) / 86400);
        $fine_amount  = round($overdue_days * $daily_fine_rate, 2);
    }

    // Apply Librarian-specified Damaged Penalty Fee
    $penalty_fee = ($book_condition === 'damaged') ? round($damaged_fee, 2) : 0.00;
    $total_fine  = $fine_amount + $penalty_fee;

    $final_fine_status = 'none';
    if ($total_fine > 0) {
        $final_fine_status = in_array($fine_action, ['paid', 'unpaid', 'waived'], true) ? $fine_action : 'unpaid';
    }

    $returned_at = date('Y-m-d H:i:s');
    $new_book_status = ($book_condition === 'damaged') ? 'damaged' : 'available';

    try {
        $pdo->beginTransaction();

        $stmt_upd = $pdo->prepare("
            UPDATE borrow_transactions
            SET received_by_id = ?, returned_at = ?, fine_amount = ?, fine_status = ?, status = 'returned'
            WHERE id = ?
        ");
        $stmt_upd->execute([$staff_id, $returned_at, $total_fine, $final_fine_status, $tx['id']]);

        $pdo->prepare("UPDATE item_accessions SET status = ? WHERE id = ?")
            ->execute([$new_book_status, $accession_id]);

        $formatted_penalty = number_format($penalty_fee, 2);
        $cond_text = ($book_condition === 'damaged') ? "DAMAGED (₱{$formatted_penalty} Penalty)" : "Good";
        $event = ($final_fine_status === 'waived') ? 'FINE_WAIVED' : 'BOOK_RETURN';
        log_action($pdo, $staff_id, $event, "Returned {$tx['accession_no']} from Student #{$tx['school_id']}. Cond: {$cond_text}. Fine: ₱{$total_fine} ({$final_fine_status}). Restored to {$tx['placement_code']}.");

        $pdo->commit();

        // Refresh scanned student's unpaid balance if they are loaded
        $stmt_u = $pdo->prepare("SELECT id, school_id, full_name, role, program_code, photo_path, status FROM users WHERE id = ? LIMIT 1");
        $stmt_u->execute([$tx['borrower_id']]);
        $updated_student = $stmt_u->fetch();
        if ($updated_student) {
            $updated_student = attach_student_debt($pdo, $updated_student);
        }

        if ($book_condition === 'damaged') {
            echo json_encode([
                'success' => true,
                'student' => $updated_student,
                'message' => "Return Processed! ₱{$formatted_penalty} Damaged Penalty applied ({$final_fine_status}). {$tx['accession_no']} is marked as DAMAGED."
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'student' => $updated_student,
                'message' => "Return Complete! {$tx['accession_no']} is now AVAILABLE. Reshelve at {$tx['placement_code']}."
            ]);
        }
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Return failed: ' . $e->getMessage()]);
    }
    exit();
}

// 4. SETTLE STUDENT UNPAID BALANCE / DEBT VIA STUDENT QR
if ($action === 'settle_student_debt') {
    $student_id     = (int) ($input['student_id'] ?? 0);
    $settlement     = trim($input['settlement_type'] ?? 'paid'); // 'paid' or 'waived'
    $transaction_id = (int) ($input['transaction_id'] ?? 0);      // 0 = settle all unpaid

    if (!in_array($settlement, ['paid', 'waived'], true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid settlement action.']);
        exit();
    }

    $stmt_u = $pdo->prepare("SELECT id, school_id, full_name, role, program_code, photo_path, status FROM users WHERE id = ? LIMIT 1");
    $stmt_u->execute([$student_id]);
    $student = $stmt_u->fetch();

    if (!$student) {
        echo json_encode(['success' => false, 'message' => 'Student account not found.']);
        exit();
    }

    try {
        $pdo->beginTransaction();

        if ($transaction_id > 0) {
            $stmt_amt = $pdo->prepare("SELECT fine_amount FROM borrow_transactions WHERE id = ? AND borrower_id = ? AND fine_status = 'unpaid' LIMIT 1");
            $stmt_amt->execute([$transaction_id, $student_id]);
            $settled_amount = (float) $stmt_amt->fetchColumn();

            $pdo->prepare("UPDATE borrow_transactions SET fine_status = ?, received_by_id = COALESCE(received_by_id, ?) WHERE id = ? AND borrower_id = ?")
                ->execute([$settlement, $staff_id, $transaction_id, $student_id]);
        } else {
            $stmt_amt = $pdo->prepare("SELECT COALESCE(SUM(fine_amount), 0.00) FROM borrow_transactions WHERE borrower_id = ? AND fine_status = 'unpaid'");
            $stmt_amt->execute([$student_id]);
            $settled_amount = (float) $stmt_amt->fetchColumn();

            $pdo->prepare("UPDATE borrow_transactions SET fine_status = ?, received_by_id = COALESCE(received_by_id, ?) WHERE borrower_id = ? AND fine_status = 'unpaid'")
                ->execute([$settlement, $staff_id, $student_id]);
        }

        $event = ($settlement === 'waived') ? 'DEBT_WAIVED' : 'DEBT_PAID';
        $formatted_amt = number_format($settled_amount, 2);
        log_action($pdo, $staff_id, $event, "Settled ₱{$formatted_amt} unpaid balance as " . strtoupper($settlement) . " for Student #{$student['school_id']} ({$student['full_name']}).");

        $pdo->commit();

        $student = attach_student_debt($pdo, $student);

        echo json_encode([
            'success' => true,
            'student' => $student,
            'message' => "Student Balance Updated! ₱{$formatted_amt} marked as " . strtoupper($settlement) . " for {$student['full_name']}."
        ]);
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to settle balance: ' . $e->getMessage()]);
    }
    exit();
}

// 5. FETCH RECENT CIRCULATION LOGS
if ($action === 'recent_activity') {
    $stmt = $pdo->query("
        SELECT bt.id, bt.borrowed_at, bt.due_date, bt.returned_at, bt.fine_amount, bt.fine_status, bt.status,
               u.school_id, u.full_name,
               ia.accession_no, ia.placement_code,
               ci.title
        FROM borrow_transactions bt
        JOIN users u ON bt.borrower_id = u.id
        JOIN item_accessions ia ON bt.accession_id = ia.id
        JOIN catalog_items ci ON ia.catalog_id = ci.id
        ORDER BY bt.id DESC
        LIMIT 10
    ");
    echo json_encode(['success' => true, 'logs' => $stmt->fetchAll()]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid circulation action.']);