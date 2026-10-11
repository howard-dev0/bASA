<?php
// student/index.php
$page_title = 'Student Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_role(['student', 'faculty']);

$pdo     = $GLOBALS['pdo'];
$user_id = (int) $_SESSION['user_id'];

$stmt_loans = $pdo->prepare("
    SELECT bt.id, bt.borrowed_at, bt.due_date, bt.fine_amount, bt.status,
           ia.accession_no, ia.placement_code,
           ci.title, ci.authors
    FROM borrow_transactions bt
    JOIN item_accessions ia ON bt.accession_id = ia.id
    JOIN catalog_items ci ON ia.catalog_id = ci.id
    WHERE bt.borrower_id = ? AND bt.status IN ('active', 'overdue')
    ORDER BY bt.due_date ASC
");
$stmt_loans->execute([$user_id]);
$active_loans = $stmt_loans->fetchAll();

$stmt_fines = $pdo->prepare("SELECT COALESCE(SUM(fine_amount), 0.00) FROM borrow_transactions WHERE borrower_id = ? AND fine_status = 'unpaid'");
$stmt_fines->execute([$user_id]);
$unpaid_fines = $stmt_fines->fetchColumn();

$stmt_favs = $pdo->prepare("SELECT COUNT(*) FROM catalog_favorites WHERE user_id = ?");
$stmt_favs->execute([$user_id]);
$favorites_count = $stmt_favs->fetchColumn();

$stmt_room = $pdo->prepare("
    SELECT rr.id, rr.reservation_date, rr.start_time, rr.end_time, rr.status, ls.space_name
    FROM room_reservations rr
    JOIN library_spaces ls ON rr.space_id = ls.id
    LEFT JOIN room_reservation_members rrm ON rr.id = rrm.reservation_id
    WHERE (rr.representative_id = ? OR rrm.user_id = ?)
      AND rr.reservation_date >= CURDATE()
      AND rr.status IN ('pending', 'occupied')
    GROUP BY rr.id
    LIMIT 1
");
$stmt_room->execute([$user_id, $user_id]);
$upcoming_room = $stmt_room->fetch();

require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="bg-theme-card border border-theme-border border-l-4 border-l-theme-accent rounded-2xl p-6 mb-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-4 shadow-sm">
    <div>
        <span class="px-2.5 py-0.5 text-[10px] font-extrabold uppercase rounded-full bg-theme-accent/15 text-theme-accent">
            BCP Research & Archive Portal
        </span>
        <h1 class="text-2xl font-extrabold text-theme-text mt-2">
            Welcome, <?= e($_SESSION['full_name']) ?>!
        </h1>
        <p class="text-xs text-theme-muted font-mono mt-1">
            Student ID: <span class="text-theme-accent font-bold"><?= e($_SESSION['school_id']) ?></span> • Program: <span class="text-theme-text font-bold"><?= e($_SESSION['program_code'] ?? 'Faculty') ?></span>
        </p>
    </div>

    <div class="flex flex-wrap gap-2.5">
        <a href="<?= BASE_URL ?>/student/dynamic_id.php"
           class="bg-theme-accent hover:bg-theme-accentHover text-white font-bold text-xs px-4 py-2.5 rounded-xl shadow transition">
            Open 30s Dynamic QR Pass
        </a>
        <a href="<?= BASE_URL ?>/student/catalog.php"
           class="bg-theme-main hover:bg-theme-hover text-theme-text border border-theme-border font-semibold text-xs px-4 py-2.5 rounded-xl transition">
            Explore 4F Map & OPAC
        </a>
    </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-theme-card border border-theme-border p-5 rounded-2xl">
        <p class="text-xs font-bold uppercase tracking-wider text-theme-muted">Active Borrowed Books</p>
        <p class="text-3xl font-extrabold text-theme-text mt-2"><?= count($active_loans) ?></p>
        <p class="text-xs text-theme-muted mt-1">Return via Help Desk Webcam</p>
    </div>

    <div class="bg-theme-card border border-theme-border p-5 rounded-2xl">
        <p class="text-xs font-bold uppercase tracking-wider text-theme-muted">Discussion Rooms A–G</p>
        <?php if ($upcoming_room): ?>
            <p class="text-lg font-extrabold text-emerald-500 mt-2"><?= e($upcoming_room['space_name']) ?></p>
            <p class="text-xs text-theme-muted mt-1"><?= e($upcoming_room['reservation_date']) ?> @ <?= e(substr($upcoming_room['start_time'], 0, 5)) ?></p>
        <?php else: ?>
            <p class="text-lg font-bold text-theme-muted mt-2">No Active Slot</p>
            <a href="<?= BASE_URL ?>/student/room_reservation.php" class="text-xs text-theme-accent font-bold hover:underline mt-1 inline-block">
                + Book Room (6–8 Pax)
            </a>
        <?php endif; ?>
    </div>

    <div class="bg-theme-card border border-theme-border p-5 rounded-2xl">
        <p class="text-xs font-bold uppercase tracking-wider text-theme-muted">Saved Favorites</p>
        <p class="text-3xl font-extrabold text-amber-500 mt-2"><?= (int) $favorites_count ?></p>
        <p class="text-xs text-theme-muted mt-1">Starred Books & Capstones</p>
    </div>

    <div class="bg-theme-card border border-theme-border p-5 rounded-2xl">
        <p class="text-xs font-bold uppercase tracking-wider text-theme-muted">Unpaid Fines</p>
        <p class="text-3xl font-extrabold <?= $unpaid_fines > 0 ? 'text-red-500' : 'text-emerald-500' ?> mt-2">
            ₱<?= number_format((float)$unpaid_fines, 2) ?>
        </p>
        <p class="text-xs text-theme-muted mt-1">Auto-computed overdue rate</p>
    </div>
</div>

<div class="bg-theme-card border border-theme-border rounded-2xl overflow-hidden">
    <div class="p-5 border-b border-theme-border flex items-center justify-between gap-2">
        <div>
            <h3 class="text-base font-extrabold text-theme-text">Currently Borrowed Literature</h3>
            <p class="text-xs text-theme-muted">Present the physical book and your Return QR at the 4th Floor Help Desk webcam.</p>
        </div>
        <a href="<?= BASE_URL ?>/student/my_books.php"
           class="px-3.5 py-2 rounded-xl bg-emerald-500/20 text-emerald-400 hover:bg-emerald-500 hover:text-slate-950 font-extrabold text-xs transition shrink-0">
            🔄 Return Book (Show QR) →
        </a>
    </div>

    <?php if (empty($active_loans)): ?>
        <div class="p-10 text-center text-theme-muted text-sm">
            No active borrowed books right now.
        </div>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="bg-theme-main text-theme-muted text-xs uppercase tracking-wider border-b border-theme-border">
                        <th class="py-3 px-5">Title</th>
                        <th class="py-3 px-5">Accession QR</th>
                        <th class="py-3 px-5">4F Shelf Code</th>
                        <th class="py-3 px-5">Due Date</th>
                        <th class="py-3 px-5">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-theme-border">
                    <?php foreach ($active_loans as $loan): 
                        $is_overdue = ($loan['status'] === 'overdue' || strtotime($loan['due_date']) < time());
                    ?>
                        <tr>
                            <td class="py-3.5 px-5 font-bold text-theme-text"><?= e($loan['title']) ?></td>
                            <td class="py-3.5 px-5 font-mono text-xs text-theme-muted"><?= e($loan['accession_no']) ?></td>
                            <td class="py-3.5 px-5">
                                <span class="px-2.5 py-1 rounded bg-theme-accent/15 text-theme-accent font-mono text-xs font-bold">
                                    <?= e($loan['placement_code']) ?>
                                </span>
                            </td>
                            <td class="py-3.5 px-5 text-xs font-mono <?= $is_overdue ? 'text-red-400 font-bold' : 'text-theme-text' ?>">
                                <?= e(date('M d, Y h:i A', strtotime($loan['due_date']))) ?>
                            </td>
                            <td class="py-3.5 px-5">
                                <span class="px-2.5 py-1 text-[10px] font-extrabold uppercase rounded-full <?= $is_overdue ? 'bg-red-500/20 text-red-400' : 'bg-emerald-500/20 text-emerald-500' ?>">
                                    <?= $is_overdue ? 'OVERDUE' : 'ACTIVE' ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>