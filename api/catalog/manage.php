<?php
// api/catalog/manage.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = $GLOBALS['pdo'];

require_role(['librarian', 'super_admin']);

$staff_id = (int) $_SESSION['user_id'];
$input    = json_decode(file_get_contents('php://input'), true) ?? [];
$action   = $input['action'] ?? ($_GET['action'] ?? 'list');

function generate_next_accession(object $pdo, string $material_type, string $subj_prog, int $pub_year): string {
    if ($material_type === 'capstone_research') {
        $prefix = "BCP-RES-{$subj_prog}-{$pub_year}-";
        $pad    = 3;
    } else {
        $prefix = "BCP-BOK-{$subj_prog}-";
        $pad    = 5;
    }

    $stmt = $pdo->prepare("SELECT accession_no FROM item_accessions WHERE accession_no LIKE ? ORDER BY accession_no DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetchColumn();

    $next_num = 1;
    if ($last) {
        $parts = explode('-', $last);
        $last_seq = (int) end($parts);
        $next_num = $last_seq + 1;
    }

    return $prefix . str_pad((string)$next_num, $pad, '0', STR_PAD_LEFT);
}

// 1. LIST ALL CATALOG ITEMS + PHYSICAL ACCESSIONS
if ($action === 'list') {
    $items = $pdo->query("
        SELECT ci.*
        FROM catalog_items ci
        ORDER BY ci.id DESC
    ")->fetchAll();

    $stmt_acc = $pdo->prepare("
        SELECT id, accession_no, shelf_id, shelf_side, shelf_section, shelf_level, placement_code, status
        FROM item_accessions
        WHERE catalog_id = ?
        ORDER BY accession_no ASC
    ");

    foreach ($items as &$item) {
        $stmt_acc->execute([$item['id']]);
        $item['accessions'] = $stmt_acc->fetchAll();
    }

    echo json_encode(['success' => true, 'items' => $items]);
    exit();
}

// 2. CREATE NEW BOOK OR CAPSTONE + INITIAL PHYSICAL COPIES
if ($action === 'create_item') {
    $material_type      = $input['material_type'] ?? 'learning_book';
    $isbn_or_doc_id     = trim($input['isbn_or_doc_id'] ?? '');
    $title              = trim($input['title'] ?? '');
    $authors            = trim($input['authors'] ?? '');
    $subject_or_program = trim($input['subject_or_program'] ?? 'IT');
    $publisher          = trim($input['publisher'] ?? 'Bestlink College of the Philippines');
    $publish_year       = (int) ($input['publish_year'] ?? date('Y'));
    $abstract           = trim($input['abstract_or_summary'] ?? '');

    $shelf_id      = trim($input['shelf_id'] ?? 'SH02');
    $shelf_side    = trim($input['shelf_side'] ?? 'F');
    $shelf_section = trim($input['shelf_section'] ?? 'S1');
    $shelf_level   = trim($input['shelf_level'] ?? 'L1');
    $copies_count  = max(1, min(10, (int) ($input['copies_count'] ?? 1)));

    if ($title === '' || $authors === '' || $isbn_or_doc_id === '') {
        echo json_encode(['success' => false, 'message' => 'Title, Authors, and ISBN/Document ID are required.']);
        exit();
    }

    $research_cluster = null;
    if ($material_type === 'capstone_research') {
        foreach (BCP_PROGRAMS as $cCode => $cData) {
            if (isset($cData['programs'][$subject_or_program])) {
                $research_cluster = $cCode;
                break;
            }
        }
    }

    if (in_array($shelf_id, ['SH00', 'SH10', 'SH11', 'SH12', 'SH13', 'SH14'], true)) {
        $shelf_side = 'F';
    }

    $placement_code = "{$shelf_id}-{$shelf_side}-{$shelf_section}-{$shelf_level}";

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO catalog_items
                (material_type, isbn_or_doc_id, title, authors, subject_or_program, research_cluster, publisher, publish_year, abstract_or_summary)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $material_type, $isbn_or_doc_id, $title, $authors,
            $subject_or_program, $research_cluster, $publisher, $publish_year, $abstract
        ]);
        $catalog_id = (int) $pdo->lastInsertId();

        $created_codes = [];
        $stmt_acc = $pdo->prepare("
            INSERT INTO item_accessions
                (catalog_id, accession_no, shelf_id, shelf_side, shelf_section, shelf_level, placement_code, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'available')
        ");

        for ($i = 0; $i < $copies_count; $i++) {
            $acc_no = generate_next_accession($pdo, $material_type, $subject_or_program, $publish_year);
            $stmt_acc->execute([$catalog_id, $acc_no, $shelf_id, $shelf_side, $shelf_section, $shelf_level, $placement_code]);
            $created_codes[] = $acc_no;
        }

        log_action($pdo, $staff_id, 'CATALOG_CREATE', "Added '{$title}' with {$copies_count} copy/copies (" . implode(', ', $created_codes) . ") at {$placement_code}.");
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Cataloged '{$title}' at {$placement_code}! Generated QR Accession(s): " . implode(', ', $created_codes)
        ]);
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error (check if ISBN/Doc ID already exists): ' . $e->getMessage()]);
    }
    exit();
}

// 3. ADD AN EXTRA COPY TO EXISTING LITERATURE
if ($action === 'add_copy') {
    $catalog_id = (int) ($input['catalog_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM catalog_items WHERE id = ? LIMIT 1");
    $stmt->execute([$catalog_id]);
    $item = $stmt->fetch();

    if (!$item) {
        echo json_encode(['success' => false, 'message' => 'Catalog item not found.']);
        exit();
    }

    $stmt_first = $pdo->prepare("SELECT * FROM item_accessions WHERE catalog_id = ? LIMIT 1");
    $stmt_first->execute([$catalog_id]);
    $first = $stmt_first->fetch();

    $shelf_id       = $first['shelf_id'] ?? 'SH01';
    $shelf_side     = $first['shelf_side'] ?? 'F';
    $shelf_section  = $first['shelf_section'] ?? 'S1';
    $shelf_level    = $first['shelf_level'] ?? 'L1';
    $placement_code = "{$shelf_id}-{$shelf_side}-{$shelf_section}-{$shelf_level}";

    $acc_no = generate_next_accession($pdo, $item['material_type'], $item['subject_or_program'], (int)$item['publish_year']);

    $pdo->prepare("
        INSERT INTO item_accessions (catalog_id, accession_no, shelf_id, shelf_side, shelf_section, shelf_level, placement_code, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'available')
    ")->execute([$catalog_id, $acc_no, $shelf_id, $shelf_side, $shelf_section, $shelf_level, $placement_code]);

    log_action($pdo, $staff_id, 'ACCESSION_ADD', "Added new physical copy {$acc_no} at {$placement_code}.");

    echo json_encode(['success' => true, 'message' => "Added new physical copy {$acc_no} at {$placement_code}."]);
    exit();
}

// 4. DELETE LITERATURE ITEM (Only if no copies have active borrow transactions)
if ($action === 'delete_item') {
    $catalog_id = (int) ($input['catalog_id'] ?? 0);

    $stmt = $pdo->prepare("SELECT title FROM catalog_items WHERE id = ? LIMIT 1");
    $stmt->execute([$catalog_id]);
    $item = $stmt->fetch();

    if (!$item) {
        echo json_encode(['success' => false, 'message' => 'Catalog item not found.']);
        exit();
    }

    // Check if any copy is currently borrowed
    $stmt_borrowed = $pdo->prepare("
        SELECT COUNT(*) FROM item_accessions
        WHERE catalog_id = ? AND status = 'borrowed'
    ");
    $stmt_borrowed->execute([$catalog_id]);
    if ((int)$stmt_borrowed->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete literature while a physical copy is currently borrowed.']);
        exit();
    }

    try {
        $pdo->beginTransaction();
        // Delete past borrow transactions referencing this item's accessions so FK doesn't block
        $pdo->prepare("
            DELETE bt FROM borrow_transactions bt
            JOIN item_accessions ia ON bt.accession_id = ia.id
            WHERE ia.catalog_id = ?
        ")->execute([$catalog_id]);

        $pdo->prepare("DELETE FROM catalog_items WHERE id = ?")->execute([$catalog_id]);
        log_action($pdo, $staff_id, 'CATALOG_DELETE', "Deleted catalog item #{$catalog_id} ('{$item['title']}').");
        $pdo->commit();

        echo json_encode(['success' => true, 'message' => "Deleted '{$item['title']}' from the catalogue."]);
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Could not delete item: ' . $e->getMessage()]);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Invalid catalog management action.']);