<?php
// admin/index.php
$page_title = 'Librarian Operations Dashboard';
require_once __DIR__ . '/../includes/header.php';
require_role(['librarian', 'super_admin']);

$pdo = $GLOBALS['pdo'];

// 1. Inventory Metrics (Shelves SH00-SH14)
$stmt_inv = $pdo->query("
    SELECT 
        COUNT(*) AS total_copies,
        COALESCE(SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END), 0) AS available_copies
    FROM item_accessions
");
$inv_stats = $stmt_inv->fetch();

// 2. Active & Overdue Circulation Metrics
$stmt_circ = $pdo->query("
    SELECT 
        COUNT(*) AS active_loans,
        COALESCE(SUM(CASE WHEN status = 'overdue' OR (status = 'active' AND due_date < NOW()) THEN 1 ELSE 0 END), 0) AS overdue_count
    FROM borrow_transactions
    WHERE status IN ('active', 'overdue')
");
$circ_stats = $stmt_circ->fetch();

// 3. Today's Discussion Room Reservations (Rooms A-G)
$stmt_rooms_today = $pdo->query("
    SELECT COUNT(*) 
    FROM room_reservations 
    WHERE reservation_date = CURDATE() AND status IN ('pending', 'occupied')
");
$rooms_today_count = (int) $stmt_rooms_today->fetchColumn();

// 4. Total Unpaid Fines
$stmt_fines = $pdo->query("
    SELECT COALESCE(SUM(fine_amount), 0.00) 
    FROM borrow_transactions 
    WHERE fine_status = 'unpaid'
");
$total_unpaid_fines = (float) $stmt_fines->fetchColumn();

// 5. Live Status of Discussion Rooms A-G & Cubicles A-F
$stmt_spaces = $pdo->query("
    SELECT id, space_code, space_name, space_type, current_status
    FROM library_spaces
    ORDER BY space_type ASC, space_code ASC
");
$spaces = $stmt_spaces->fetchAll();

// 6. Active & Overdue Borrow Transactions
$stmt_recent_loans = $pdo->query("
    SELECT bt.id, bt.borrowed_at, bt.due_date, bt.status,
           u.school_id, u.full_name, u.program_code,
           ia.accession_no, ia.placement_code,
           ci.title
    FROM borrow_transactions bt
    JOIN users u ON bt.borrower_id = u.id
    JOIN item_accessions ia ON bt.accession_id = ia.id
    JOIN catalog_items ci ON ia.catalog_id = ci.id
    WHERE bt.status IN ('active', 'overdue')
    ORDER BY bt.due_date ASC
    LIMIT 8
");
$active_loans_list = $stmt_recent_loans->fetchAll();

// 7. Today's Discussion Room Check-in Queue
$stmt_room_queue = $pdo->query("
    SELECT rr.id, rr.start_time, rr.end_time, rr.purpose, rr.present_headcount, rr.status,
           ls.space_name,
           u.school_id AS rep_school_id, u.full_name AS rep_name,
           (SELECT COUNT(*) FROM room_reservation_members rrm WHERE rrm.reservation_id = rr.id) AS tagged_members
    FROM room_reservations rr
    JOIN library_spaces ls ON rr.space_id = ls.id
    JOIN users u ON rr.representative_id = u.id
    WHERE rr.reservation_date = CURDATE() AND rr.status IN ('pending', 'occupied')
    ORDER BY rr.start_time ASC
    LIMIT 6
");
$room_queue = $stmt_room_queue->fetchAll();

require_once __DIR__ . '/../includes/sidebar.php';
?>

<!-- Welcome & Quick Actions Banner -->
<div class="bg-theme-card border border-theme-border border-l-4 border-l-theme-accent rounded-2xl p-4 sm:p-6 mb-6 flex flex-col lg:flex-row items-start lg:items-center justify-between gap-4 shadow-xs">
    <div>
        <span class="px-2.5 py-1 text-[10px] font-extrabold uppercase rounded-full bg-theme-accent/15 text-theme-accent">
            4th Floor Help Desk • <?= date('F j, Y') ?>
        </span>
        <h1 class="text-xl sm:text-2xl font-extrabold text-theme-text mt-2">
            Welcome, <?= e($_SESSION['full_name']) ?>
        </h1>
        <p class="text-xs text-theme-muted mt-1">
            Manage shelves <span class="font-mono font-bold text-theme-text">SH00–SH14</span>, scan 30s student QR passes, and verify 6–8 pax check-ins for <span class="font-mono font-bold text-theme-text">Discussion Rooms A–G</span>.
        </p>
    </div>

    <div class="flex flex-col sm:flex-row w-full lg:w-auto gap-2.5 shrink-0">
        <a href="<?= BASE_URL ?>/admin/circulation_desk.php"
           class="bg-theme-accent hover:bg-theme-accentHover text-white font-bold text-xs px-4 py-3 sm:py-2.5 rounded-xl shadow transition flex items-center justify-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
            </svg>
            <span>Open Webcam QR Scanner</span>
        </a>
        <a href="<?= BASE_URL ?>/admin/catalog_manager.php"
           class="bg-theme-main hover:bg-theme-hover text-theme-text border border-theme-border font-bold text-xs px-4 py-3 sm:py-2.5 rounded-xl transition text-center">
            + Add Book / Print QR
        </a>
    </div>
</div>

<!-- 4 Metric Summary Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-theme-card border border-theme-border p-4 sm:p-5 rounded-2xl shadow-xs">
        <p class="text-xs font-bold uppercase tracking-wider text-theme-muted">Available Copies</p>
        <div class="flex items-baseline gap-2 mt-2">
            <p class="text-2xl sm:text-3xl font-extrabold text-theme-text"><?= (int) $inv_stats['available_copies'] ?></p>
            <span class="text-xs text-theme-muted">/ <?= (int) $inv_stats['total_copies'] ?> total</span>
        </div>
        <p class="text-xs text-theme-accent font-semibold mt-1">Shelves SH00 to SH14</p>
    </div>

    <div class="bg-theme-card border border-theme-border p-4 sm:p-5 rounded-2xl shadow-xs">
        <p class="text-xs font-bold uppercase tracking-wider text-theme-muted">Active Book Loans</p>
        <div class="flex items-baseline gap-2 mt-2">
            <p class="text-2xl sm:text-3xl font-extrabold text-theme-text"><?= (int) $circ_stats['active_loans'] ?></p>
            <span class="text-xs font-bold <?= $circ_stats['overdue_count'] > 0 ? 'text-red-500' : 'text-emerald-500' ?>">
                (<?= (int) $circ_stats['overdue_count'] ?> Overdue)
            </span>
        </div>
        <p class="text-xs text-theme-muted mt-1">Closed-loop webcam return</p>
    </div>

    <div class="bg-theme-card border border-theme-border p-4 sm:p-5 rounded-2xl shadow-xs">
        <p class="text-xs font-bold uppercase tracking-wider text-theme-muted">Today's Room Bookings</p>
        <p class="text-2xl sm:text-3xl font-extrabold text-emerald-500 mt-2"><?= $rooms_today_count ?></p>
        <p class="text-xs text-theme-muted mt-1">Rooms A–G (6–8 Pax Policy)</p>
    </div>

    <div class="bg-theme-card border border-theme-border p-4 sm:p-5 rounded-2xl shadow-xs">
        <p class="text-xs font-bold uppercase tracking-wider text-theme-muted">Unpaid Overdue Fines</p>
        <p class="text-2xl sm:text-3xl font-extrabold text-[#D9232A] mt-2">₱<?= number_format($total_unpaid_fines, 2) ?></p>
        <p class="text-xs text-theme-muted mt-1">Settled at Help Desk</p>
    </div>
</div>

<!-- Responsive Main Operations Grid -->
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
    <div class="xl:col-span-2 space-y-6 min-w-0">
        <!-- Active Loans Table -->
        <div class="bg-theme-card border border-theme-border rounded-2xl overflow-hidden shadow-xs">
            <div class="p-4 sm:p-5 border-b border-theme-border flex items-center justify-between gap-2">
                <div>
                    <h2 class="text-sm sm:text-base font-extrabold text-theme-text">Active Circulation Loans</h2>
                    <p class="text-xs text-theme-muted">Books currently checked out by students and faculty</p>
                </div>
                <a href="<?= BASE_URL ?>/admin/circulation_desk.php" class="text-xs font-bold text-theme-accent hover:underline shrink-0">
                    Process Return →
                </a>
            </div>

            <?php if (empty($active_loans_list)): ?>
                <div class="p-8 text-center text-theme-muted text-xs">
                    No active book loans at the moment.
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs min-w-[600px]">
                        <thead>
                            <tr class="bg-theme-main text-theme-muted uppercase tracking-wider border-b border-theme-border text-[10px]">
                                <th class="py-3 px-4 sm:px-5">Borrower</th>
                                <th class="py-3 px-4 sm:px-5">Literature & Accession QR</th>
                                <th class="py-3 px-4 sm:px-5">Shelf Code</th>
                                <th class="py-3 px-4 sm:px-5">Due Date</th>
                                <th class="py-3 px-4 sm:px-5">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-theme-border">
                            <?php foreach ($active_loans_list as $loan): 
                                $is_overdue = ($loan['status'] === 'overdue' || strtotime($loan['due_date']) < time());
                            ?>
                                <tr class="hover:bg-theme-hover/50 transition">
                                    <td class="py-3.5 px-4 sm:px-5">
                                        <p class="font-bold text-theme-text"><?= e($loan['full_name']) ?></p>
                                        <p class="font-mono text-[11px] text-theme-muted"><?= e($loan['school_id']) ?> • <?= e($loan['program_code'] ?? 'STAFF') ?></p>
                                    </td>
                                    <td class="py-3.5 px-4 sm:px-5">
                                        <p class="font-bold text-theme-text"><?= e($loan['title']) ?></p>
                                        <p class="font-mono text-[11px] text-theme-muted"><?= e($loan['accession_no']) ?></p>
                                    </td>
                                    <td class="py-3.5 px-4 sm:px-5">
                                        <span class="px-2.5 py-1 rounded-lg bg-theme-accent/15 text-theme-accent font-mono font-bold">
                                            <?= e($loan['placement_code']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 sm:px-5 font-mono text-theme-text">
                                        <?= e(date('M d, Y', strtotime($loan['due_date']))) ?>
                                    </td>
                                    <td class="py-3.5 px-4 sm:px-5">
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase <?= $is_overdue ? 'bg-red-500/20 text-red-500' : 'bg-emerald-500/20 text-emerald-500' ?>">
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

        <!-- Today's Discussion Room Check-in Queue -->
        <div class="bg-theme-card border border-theme-border rounded-2xl overflow-hidden shadow-xs">
            <div class="p-4 sm:p-5 border-b border-theme-border flex items-center justify-between gap-2">
                <div>
                    <h2 class="text-sm sm:text-base font-extrabold text-theme-text">Discussion Rooms A–G Check-In Queue</h2>
                    <p class="text-xs text-theme-muted">Minimum 6 physically present members required at Help Desk</p>
                </div>
                <a href="<?= BASE_URL ?>/admin/room_monitor.php" class="text-xs font-bold text-theme-accent hover:underline shrink-0">
                    View All Rooms →
                </a>
            </div>

            <?php if (empty($room_queue)): ?>
                <div class="p-8 text-center text-theme-muted text-xs">
                    No Discussion Room reservations scheduled for today.
                </div>
            <?php else: ?>
                <div class="divide-y divide-theme-border">
                    <?php foreach ($room_queue as $rq): ?>
                        <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <span class="px-2.5 py-0.5 rounded bg-theme-accent/15 text-theme-accent font-extrabold text-xs">
                                    <?= e($rq['space_name']) ?>
                                </span>
                                <span class="text-xs font-mono font-bold text-theme-text ml-2">
                                    <?= e(substr($rq['start_time'], 0, 5)) ?> – <?= e(substr($rq['end_time'], 0, 5)) ?>
                                </span>
                                <p class="text-xs text-theme-muted mt-1">
                                    Rep: <strong class="text-theme-text"><?= e($rq['rep_name']) ?></strong> (<?= e($rq['rep_school_id']) ?>)
                                </p>
                            </div>
                            <a href="<?= BASE_URL ?>/admin/room_monitor.php?verify=<?= (int) $rq['id'] ?>"
                               class="px-3.5 py-2 rounded-xl bg-theme-accent text-white text-xs font-bold text-center shrink-0">
                                Verify 6+ Members
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: 4th Floor Rooms A-G & Cubicles A-F Status -->
    <div class="bg-theme-card border border-theme-border rounded-2xl p-4 sm:p-5 shadow-xs h-fit">
        <h2 class="text-sm sm:text-base font-extrabold text-theme-text mb-1">4th Floor Spaces Status</h2>
        <p class="text-xs text-theme-muted mb-4">Live availability of Rooms A–G & Cubicles A–F</p>

        <p class="text-[11px] font-extrabold uppercase tracking-wider text-theme-muted mb-2">Discussion Rooms (6–8 Pax)</p>
        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-2 gap-2.5 mb-5">
            <?php foreach ($spaces as $sp): ?>
                <?php if ($sp['space_type'] === 'discussion_room'): 
                    $badge = match($sp['current_status']) {
                        'available' => 'border-emerald-500/40 bg-emerald-500/10 text-emerald-500',
                        'reserved'  => 'border-amber-500/40 bg-amber-500/10 text-amber-500',
                        default     => 'border-red-500/40 bg-red-500/10 text-red-500',
                    };
                ?>
                    <div class="p-3 rounded-xl border <?= $badge ?> flex items-center justify-between">
                        <div>
                            <p class="text-xs font-extrabold"><?= e($sp['space_code']) ?></p>
                            <p class="text-[10px] uppercase font-bold opacity-80"><?= e($sp['current_status']) ?></p>
                        </div>
                        <span class="text-[10px] font-mono font-bold">6-8p</span>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <p class="text-[11px] font-extrabold uppercase tracking-wider text-theme-muted mb-2">Reading Hub (Cubicles A–F)</p>
        <div class="grid grid-cols-3 gap-2">
            <?php foreach ($spaces as $sp): ?>
                <?php if ($sp['space_type'] === 'reading_cubicle'): 
                    $cBadge = $sp['current_status'] === 'available'
                        ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-500'
                        : 'border-red-500/40 bg-red-500/10 text-red-500';
                ?>
                    <div class="p-2.5 rounded-xl border <?= $cBadge ?> text-center">
                        <p class="text-xs font-extrabold"><?= e(str_replace('CUBICLE-', 'CUB-', $sp['space_code'])) ?></p>
                        <p class="text-[9px] uppercase font-bold"><?= e($sp['current_status']) ?></p>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>