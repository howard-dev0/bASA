<?php
// api/community/hub.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../includes/functions.php';

$pdo = $GLOBALS['pdo'];

require_role(['student', 'faculty', 'librarian', 'super_admin']);

$user_id   = (int) $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$is_staff  = in_array($user_role, ['librarian', 'super_admin'], true);

// Handle multipart/form-data for Librarian Gallery Picture Upload (16 4F + 6 3F slots)
if (isset($_POST['action']) && $_POST['action'] === 'save_gallery_slot') {
    if (!$is_staff) {
        echo json_encode(['success' => false, 'message' => 'Only Librarians and Super Admins can edit gallery pictures.']);
        exit();
    }

    $slot_id  = (int) ($_POST['id'] ?? 0);
    $title    = trim($_POST['title'] ?? '');
    $zone_tag = trim($_POST['zone_tag'] ?? '');
    $caption  = trim($_POST['caption'] ?? '');

    if ($slot_id <= 0 || $title === '') {
        echo json_encode(['success' => false, 'message' => 'Picture title is required.']);
        exit();
    }

    $new_image_path = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['image'];
        if ($file['size'] > 5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'Picture size must be under 5MB.']);
            exit();
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) {
            echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, and WEBP images are allowed.']);
            exit();
        }

        $ext = $allowed[$mime];
        $dir = __DIR__ . '/../../uploads/gallery/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = 'floor_slot_' . $slot_id . '_' . time() . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $dir . $filename)) {
            $new_image_path = 'uploads/gallery/' . $filename;
        }
    }

    if ($new_image_path !== null) {
        $pdo->prepare("UPDATE library_gallery SET title = ?, zone_tag = ?, caption = ?, image_path = ? WHERE id = ?")
            ->execute([$title, $zone_tag, $caption, $new_image_path, $slot_id]);
    } else {
        $pdo->prepare("UPDATE library_gallery SET title = ?, zone_tag = ?, caption = ? WHERE id = ?")
            ->execute([$title, $zone_tag, $caption, $slot_id]);
    }

    log_action($pdo, $user_id, 'GALLERY_EDIT', "Updated library gallery picture slot #{$slot_id} ('{$title}').");

    echo json_encode([
        'success' => true,
        'message' => "Gallery slot '{$title}' updated successfully!"
    ]);
    exit();
}

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? ($_GET['action'] ?? 'init');

// 1. INITIALIZE GALLERY (16 4F + 6 3F), KEEP IN TOUCH, & SUGGESTION BOX
if ($action === 'init') {
    $gallery_4f = $pdo->query("SELECT * FROM library_gallery WHERE floor_level = '4F' ORDER BY slot_number ASC")->fetchAll();
    $gallery_3f = $pdo->query("SELECT * FROM library_gallery WHERE floor_level = '3F' ORDER BY slot_number ASC")->fetchAll();

    // Fetch Keep in Touch contact details
    $contact = [
        'contact_email'    => 'library.archives@bcp.edu.ph',
        'contact_phone'    => '(02) 8442-8800 / +63 917 890 1234',
        'contact_location' => '3rd & 4th Floor Academic Building, Bestlink College of the Philippines',
        'contact_hours'    => 'Monday – Saturday: 7:30 AM – 6:00 PM',
        'contact_facebook' => 'facebook.com/BCPOfficialLibrary'
    ];
    $stmt_c = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'contact_%'");
    while ($row = $stmt_c->fetch()) {
        $contact[$row['setting_key']] = $row['setting_value'];
    }

    // Fetch Suggestions (Staff sees all; Students see their own + reviewed/implemented community suggestions)
    if ($is_staff) {
        $stmt_s = $pdo->query("
            SELECT ls.*, u.school_id, u.full_name, u.display_name, u.program_code
            FROM library_suggestions ls
            JOIN users u ON ls.user_id = u.id
            ORDER BY FIELD(ls.status, 'pending', 'reviewed', 'implemented'), ls.created_at DESC
            LIMIT 40
        ");
        $suggestions = $stmt_s->fetchAll();
    } else {
        $stmt_s = $pdo->prepare("
            SELECT ls.*, u.school_id, u.full_name, u.display_name, u.program_code
            FROM library_suggestions ls
            JOIN users u ON ls.user_id = u.id
            WHERE ls.user_id = ? OR ls.status IN ('reviewed', 'implemented')
            ORDER BY ls.created_at DESC
            LIMIT 25
        ");
        $stmt_s->execute([$user_id]);
        $suggestions = $stmt_s->fetchAll();
    }

    foreach ($suggestions as &$sg) {
        $sg['is_mine'] = ((int)$sg['user_id'] === $user_id);
        if ((int)$sg['is_anonymous'] === 1 && !$is_staff && !$sg['is_mine']) {
            $sg['author_label'] = 'Anonymous BCP Researcher';
        } else {
            $name = !empty($sg['display_name']) ? $sg['display_name'] : $sg['full_name'];
            $sg['author_label'] = $name . ($sg['program_code'] ? " ({$sg['program_code']})" : '');
            if ((int)$sg['is_anonymous'] === 1 && $is_staff) {
                $sg['author_label'] .= ' [Posted Anonymously]';
            }
        }
    }

    echo json_encode([
        'success'     => true,
        'is_staff'    => $is_staff,
        'gallery_4f'  => $gallery_4f,
        'gallery_3f'  => $gallery_3f,
        'contact'     => $contact,
        'suggestions' => $suggestions
    ]);
    exit();
}

// 2. SUBMIT NEW SUGGESTION TO SUGGESTION BOX
if ($action === 'submit_suggestion') {
    $category     = trim($input['category'] ?? 'General Feedback');
    $subject      = trim($input['subject'] ?? '');
    $message      = trim($input['message'] ?? '');
    $is_anonymous = !empty($input['is_anonymous']) ? 1 : 0;

    if ($subject === '' || $message === '') {
        echo json_encode(['success' => false, 'message' => 'Please enter both a subject and your suggestion message.']);
        exit();
    }

    $stmt = $pdo->prepare("
        INSERT INTO library_suggestions (user_id, category, subject, message, is_anonymous, status)
        VALUES (?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([$user_id, $category, $subject, $message, $is_anonymous]);

    log_action($pdo, $user_id, 'SUGGESTION_SUBMIT', "Submitted suggestion to Suggestion Box: '{$subject}' ({$category}).");

    echo json_encode([
        'success' => true,
        'message' => 'Thank you! Your suggestion has been dropped into the bASA Library Suggestion Box.'
    ]);
    exit();
}

// 3. LIBRARIAN / SUPER ADMIN: REPLY TO OR UPDATE SUGGESTION STATUS
if ($action === 'reply_suggestion') {
    if (!$is_staff) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized action.']);
        exit();
    }

    $suggestion_id   = (int) ($input['id'] ?? 0);
    $status          = trim($input['status'] ?? 'reviewed');
    $librarian_reply = trim($input['librarian_reply'] ?? '');

    if (!in_array($status, ['pending', 'reviewed', 'implemented'], true)) {
        $status = 'reviewed';
    }

    $pdo->prepare("UPDATE library_suggestions SET status = ?, librarian_reply = ? WHERE id = ?")
        ->execute([$status, $librarian_reply ?: null, $suggestion_id]);

    log_action($pdo, $user_id, 'SUGGESTION_REPLY', "Updated suggestion #{$suggestion_id} status to " . strtoupper($status) . ".");

    echo json_encode([
        'success' => true,
        'message' => 'Suggestion status and Librarian response saved!'
    ]);
    exit();
}

// 4. DELETE SUGGESTION (Student owns it OR Staff moderation)
if ($action === 'delete_suggestion') {
    $suggestion_id = (int) ($input['id'] ?? 0);

    $stmt = $pdo->prepare("SELECT id, user_id FROM library_suggestions WHERE id = ? LIMIT 1");
    $stmt->execute([$suggestion_id]);
    $sg = $stmt->fetch();

    if (!$sg) {
        echo json_encode(['success' => false, 'message' => 'Suggestion not found.']);
        exit();
    }

    if ((int)$sg['user_id'] !== $user_id && !$is_staff) {
        echo json_encode(['success' => false, 'message' => 'You can only remove your own suggestions.']);
        exit();
    }

    $pdo->prepare("DELETE FROM library_suggestions WHERE id = ?")->execute([$suggestion_id]);
    log_action($pdo, $user_id, 'SUGGESTION_DELETE', "Deleted suggestion #{$suggestion_id}.");

    echo json_encode(['success' => true, 'message' => 'Suggestion removed.']);
    exit();
}

// 5. LIBRARIAN / SUPER ADMIN: UPDATE "KEEP IN TOUCH" CONTACT INFO
if ($action === 'save_contact') {
    if (!$is_staff) {
        echo json_encode(['success' => false, 'message' => 'Only Librarians and Super Admins can edit Reach Us details.']);
        exit();
    }

    $keys = ['contact_email', 'contact_phone', 'contact_location', 'contact_hours', 'contact_facebook'];
    $stmt = $pdo->prepare("
        INSERT INTO system_settings (setting_key, setting_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");

    foreach ($keys as $k) {
        if (isset($input[$k])) {
            $stmt->execute([$k, trim($input[$k])]);
        }
    }

    log_action($pdo, $user_id, 'CONTACT_INFO_EDIT', 'Updated Keep in Touch / Reach Us library contact details.');

    echo json_encode([
        'success' => true,
        'message' => 'Keep in Touch (Reach Us) details updated!'
    ]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);