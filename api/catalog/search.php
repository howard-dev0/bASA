<?php
// api/catalog/search.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = $GLOBALS['pdo'];

require_role(['student', 'faculty', 'librarian', 'super_admin']);

$user_id = (int) $_SESSION['user_id'];
$input   = json_decode(file_get_contents('php://input'), true) ?? [];

$material_type      = trim($input['material_type'] ?? 'all');
$subject_or_program = trim($input['subject_or_program'] ?? 'all');
$book_age           = trim($input['book_age'] ?? 'all');
$sort_upload        = trim($input['sort_upload'] ?? 'newest');
$include_kw         = trim($input['include_kw'] ?? '');
$exclude_kw         = trim($input['exclude_kw'] ?? '');
$year_from          = (int) ($input['year_from'] ?? 0);
$year_to            = (int) ($input['year_to'] ?? 0);
$shelf_id           = trim($input['shelf_id'] ?? 'all');
$available_only     = !empty($input['available_only']);
$favorites_only     = !empty($input['favorites_only']);

$current_year = (int) date('Y');
$where  = ["1=1"];
$params = [':uid' => $user_id];

if (in_array($material_type, ['learning_book', 'capstone_research'], true)) {
    $where[] = "ci.material_type = :mat_type";
    $params[':mat_type'] = $material_type;
}

if ($subject_or_program !== '' && $subject_or_program !== 'all') {
    $where[] = "ci.subject_or_program = :subj_prog";
    $params[':subj_prog'] = $subject_or_program;
}

if ($book_age === 'new') {
    $where[] = "ci.publish_year >= :age_year";
    $params[':age_year'] = $current_year - 2;
} elseif ($book_age === 'standard') {
    $where[] = "ci.publish_year BETWEEN :age_start AND :age_end";
    $params[':age_start'] = $current_year - 5;
    $params[':age_end']   = $current_year - 3;
} elseif ($book_age === 'archival') {
    $where[] = "ci.publish_year < :age_arch";
    $params[':age_arch'] = $current_year - 5;
}

if ($year_from > 1900) {
    $where[] = "ci.publish_year >= :year_from";
    $params[':year_from'] = $year_from;
}
if ($year_to > 1900) {
    $where[] = "ci.publish_year <= :year_to";
    $params[':year_to'] = $year_to;
}

if ($include_kw !== '') {
    $terms = preg_split('/\s+/', $include_kw);
    foreach ($terms as $idx => $term) {
        $p1 = ":inc_t_{$idx}";
        $p2 = ":inc_a_{$idx}";
        $p3 = ":inc_s_{$idx}";
        $p4 = ":inc_i_{$idx}";
        $where[] = "(ci.title LIKE $p1 OR ci.authors LIKE $p2 OR COALESCE(ci.abstract_or_summary,'') LIKE $p3 OR ci.isbn_or_doc_id LIKE $p4)";
        $likeVal = '%' . $term . '%';
        $params[$p1] = $likeVal;
        $params[$p2] = $likeVal;
        $params[$p3] = $likeVal;
        $params[$p4] = $likeVal;
    }
}

if ($exclude_kw !== '') {
    $ex_terms = preg_split('/\s+/', $exclude_kw);
    foreach ($ex_terms as $idx => $ex) {
        $e1 = ":exc_t_{$idx}";
        $e2 = ":exc_a_{$idx}";
        $e3 = ":exc_s_{$idx}";
        $where[] = "(ci.title NOT LIKE $e1 AND ci.authors NOT LIKE $e2 AND COALESCE(ci.abstract_or_summary,'') NOT LIKE $e3)";
        $notVal = '%' . $ex . '%';
        $params[$e1] = $notVal;
        $params[$e2] = $notVal;
        $params[$e3] = $notVal;
    }
}

if ($shelf_id !== '' && $shelf_id !== 'all') {
    $where[] = "EXISTS (SELECT 1 FROM item_accessions ia_s WHERE ia_s.catalog_id = ci.id AND ia_s.shelf_id = :shelf_id)";
    $params[':shelf_id'] = $shelf_id;
}

if ($available_only) {
    $where[] = "EXISTS (SELECT 1 FROM item_accessions ia_av WHERE ia_av.catalog_id = ci.id AND ia_av.status = 'available')";
}

if ($favorites_only) {
    $where[] = "EXISTS (SELECT 1 FROM catalog_favorites cf_f WHERE cf_f.catalog_id = ci.id AND cf_f.user_id = :fav_uid)";
    $params[':fav_uid'] = $user_id;
}

$secondary_sort = match($sort_upload) {
    'oldest'   => 'ci.created_at ASC',
    'pub_desc' => 'ci.publish_year DESC, ci.created_at DESC',
    'pub_asc'  => 'ci.publish_year ASC, ci.created_at ASC',
    default    => 'ci.created_at DESC'
};

// Available literature first, unavailable literature (0 available copies) at the end
$sql = "
    SELECT 
        ci.*,
        (SELECT COUNT(*) FROM item_accessions ia WHERE ia.catalog_id = ci.id) AS total_copies,
        (SELECT COUNT(*) FROM item_accessions ia WHERE ia.catalog_id = ci.id AND ia.status = 'available') AS available_copies,
        (SELECT COUNT(*) FROM catalog_favorites cf WHERE cf.catalog_id = ci.id) AS total_stars,
        EXISTS(SELECT 1 FROM catalog_favorites cf_u WHERE cf_u.catalog_id = ci.id AND cf_u.user_id = :uid) AS is_favorited,
        COALESCE((SELECT ROUND(AVG(cr.rating), 1) FROM catalog_reviews cr WHERE cr.catalog_id = ci.id), 0) AS avg_rating,
        (SELECT COUNT(*) FROM catalog_reviews cr WHERE cr.catalog_id = ci.id) AS review_count
    FROM catalog_items ci
    WHERE " . implode(' AND ', $where) . "
    ORDER BY (available_copies > 0) DESC, $secondary_sort
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

$stmt_acc = $pdo->prepare("
    SELECT id, accession_no, shelf_id, shelf_side, shelf_section, shelf_level, placement_code, status
    FROM item_accessions
    WHERE catalog_id = ?
    ORDER BY FIELD(status, 'available', 'borrowed', 'reserved', 'lost', 'damaged'), accession_no ASC
");

foreach ($items as &$item) {
    $stmt_acc->execute([$item['id']]);
    $accessions = $stmt_acc->fetchAll();
    $item['accessions']       = $accessions;
    $item['primary_shelf']    = $accessions[0]['shelf_id'] ?? 'SH01';
    $item['primary_code']     = $accessions[0]['placement_code'] ?? 'UNASSIGNED';
    $item['is_favorited']     = (bool) $item['is_favorited'];
    $item['available_copies'] = (int) $item['available_copies'];
    $item['total_copies']     = (int) $item['total_copies'];
    $item['book_age_years']   = max(0, $current_year - (int)$item['publish_year']);
}

echo json_encode([
    'success' => true,
    'count'   => count($items),
    'items'   => $items
]);