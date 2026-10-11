<?php
// api/catalog/interact.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = $GLOBALS['pdo'];

require_role(['student', 'faculty', 'librarian', 'super_admin']);

$user_id   = (int) $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$input     = json_decode(file_get_contents('php://input'), true) ?? [];
$action    = $input['action'] ?? ($_GET['action'] ?? '');

// 1. TOGGLE FAVORITE / STAR
if ($action === 'toggle_favorite') {
    $catalog_id = (int) ($input['catalog_id'] ?? 0);
    if ($catalog_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid item.']);
        exit();
    }

    $check = $pdo->prepare("SELECT id FROM catalog_favorites WHERE user_id = ? AND catalog_id = ?");
    $check->execute([$user_id, $catalog_id]);

    if ($check->fetch()) {
        $pdo->prepare("DELETE FROM catalog_favorites WHERE user_id = ? AND catalog_id = ?")->execute([$user_id, $catalog_id]);
        $favorited = false;
    } else {
        $pdo->prepare("INSERT INTO catalog_favorites (user_id, catalog_id) VALUES (?, ?)")->execute([$user_id, $catalog_id]);
        $favorited = true;
    }

    $cnt = $pdo->prepare("SELECT COUNT(*) FROM catalog_favorites WHERE catalog_id = ?");
    $cnt->execute([$catalog_id]);

    echo json_encode([
        'success'      => true,
        'is_favorited' => $favorited,
        'total_stars'  => (int) $cnt->fetchColumn()
    ]);
    exit();
}

// 2. FETCH REVIEWS FOR AN ITEM (Includes can_delete flag for current user)
if ($action === 'get_reviews') {
    $catalog_id = (int) ($_GET['catalog_id'] ?? 0);
    $stmt = $pdo->prepare("
        SELECT cr.id, cr.user_id, cr.rating, cr.comment, cr.created_at,
               u.full_name, u.school_id, u.program_code, u.role
        FROM catalog_reviews cr
        JOIN users u ON cr.user_id = u.id
        WHERE cr.catalog_id = ?
        ORDER BY cr.created_at DESC
    ");
    $stmt->execute([$catalog_id]);
    $reviews = $stmt->fetchAll();

    foreach ($reviews as &$rev) {
        $rev['can_delete'] = ((int)$rev['user_id'] === $user_id) || in_array($user_role, ['librarian', 'super_admin'], true);
    }

    echo json_encode(['success' => true, 'reviews' => $reviews]);
    exit();
}

// 3. POST A NEW REVIEW / COMMENT
if ($action === 'post_review') {
    $catalog_id = (int) ($input['catalog_id'] ?? 0);
    $rating     = max(1, min(5, (int) ($input['rating'] ?? 5)));
    $comment    = trim($input['comment'] ?? '');

    if ($catalog_id <= 0 || $comment === '') {
        echo json_encode(['success' => false, 'message' => 'Please write a comment before submitting.']);
        exit();
    }

    $stmt = $pdo->prepare("INSERT INTO catalog_reviews (user_id, catalog_id, rating, comment) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $catalog_id, $rating, $comment]);

    log_action($pdo, $user_id, 'CATALOG_REVIEW', "Posted {$rating}-star review on catalog item #{$catalog_id}");
    echo json_encode(['success' => true, 'message' => 'Review posted!']);
    exit();
}

// 4. DELETE / REMOVE A COMMENT (Student owns comment OR Staff moderation)
if ($action === 'delete_review') {
    $review_id = (int) ($input['review_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT id, user_id, catalog_id FROM catalog_reviews WHERE id = ? LIMIT 1");
    $stmt->execute([$review_id]);
    $rev = $stmt->fetch();

    if (!$rev) {
        echo json_encode(['success' => false, 'message' => 'Comment not found.']);
        exit();
    }

    $is_owner = ((int)$rev['user_id'] === $user_id);
    $is_staff = in_array($user_role, ['librarian', 'super_admin'], true);

    if (!$is_owner && !$is_staff) {
        echo json_encode(['success' => false, 'message' => 'You can only remove your own comments.']);
        exit();
    }

    $pdo->prepare("DELETE FROM catalog_reviews WHERE id = ?")->execute([$review_id]);
    log_action($pdo, $user_id, 'REVIEW_DELETE', "Removed comment #{$review_id} on catalog item #{$rev['catalog_id']}");

    echo json_encode(['success' => true, 'message' => 'Comment removed.']);
    exit();
}

// 5. GENERATE IN-LIBRARY 30s EXPRESS BORROW QR
if ($action === 'generate_express_qr') {
    $accession_no = trim($input['accession_no'] ?? '');
    $stmt_acc = $pdo->prepare("SELECT status FROM item_accessions WHERE accession_no = ? LIMIT 1");
    $stmt_acc->execute([$accession_no]);
    $acc = $stmt_acc->fetch();

    if (!$acc || $acc['status'] !== 'available') {
        echo json_encode(['success' => false, 'message' => 'This physical copy is not currently available.']);
        exit();
    }

    $stmt_u = $pdo->prepare("SELECT school_id, totp_secret FROM users WHERE id = ? LIMIT 1");
    $stmt_u->execute([$user_id]);
    $user = $stmt_u->fetch();

    $current_time = time();
    $time_window  = (int) floor($current_time / TOTP_WINDOW);
    $seconds_left = TOTP_WINDOW - ($current_time % TOTP_WINDOW);

    $data_to_sign = $user['school_id'] . '|' . $accession_no . '|' . $time_window . '|' . $user['totp_secret'];
    $signature    = hash_hmac('sha256', $data_to_sign, SYSTEM_HMAC_KEY);

    echo json_encode([
        'success'      => true,
        'qr_payload'   => "BASA-EXP|{$user['school_id']}|{$accession_no}|{$time_window}|{$signature}",
        'accession_no' => $accession_no,
        'seconds_left' => $seconds_left
    ]);
    exit();
}

// 6. GENERATE 30s BOOK RETURN QR
if ($action === 'generate_return_qr') {
    $accession_no = trim($input['accession_no'] ?? '');
    if ($accession_no === '') {
        echo json_encode(['success' => false, 'message' => 'Missing accession number.']);
        exit();
    }

    $stmt_u = $pdo->prepare("SELECT school_id, totp_secret FROM users WHERE id = ? LIMIT 1");
    $stmt_u->execute([$user_id]);
    $user = $stmt_u->fetch();

    $current_time = time();
    $time_window  = (int) floor($current_time / TOTP_WINDOW);
    $seconds_left = TOTP_WINDOW - ($current_time % TOTP_WINDOW);

    $data_to_sign = $user['school_id'] . '|' . $accession_no . '|' . $time_window . '|' . $user['totp_secret'];
    $signature    = hash_hmac('sha256', $data_to_sign, SYSTEM_HMAC_KEY);

    echo json_encode([
        'success'      => true,
        'qr_payload'   => "BASA-RET|{$user['school_id']}|{$accession_no}|{$time_window}|{$signature}",
        'accession_no' => $accession_no,
        'seconds_left' => $seconds_left
    ]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);