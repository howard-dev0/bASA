<?php
// student/my_books.php
$page_title = 'My Borrowed Books & Return QR';
require_once __DIR__ . '/../includes/header.php';
require_role(['student', 'faculty']);

$pdo     = $GLOBALS['pdo'];
$user_id = (int) $_SESSION['user_id'];

// Fetch Active & Overdue Borrowed Books
$stmt_active = $pdo->prepare("
    SELECT bt.id, bt.borrowed_at, bt.due_date, bt.fine_amount, bt.status,
           ia.accession_no, ia.placement_code,
           ci.title, ci.authors, ci.subject_or_program, ci.material_type
    FROM borrow_transactions bt
    JOIN item_accessions ia ON bt.accession_id = ia.id
    JOIN catalog_items ci ON ia.catalog_id = ci.id
    WHERE bt.borrower_id = ? AND bt.status IN ('active', 'overdue')
    ORDER BY bt.due_date ASC
");
$stmt_active->execute([$user_id]);
$active_loans = $stmt_active->fetchAll();

// Fetch Returned / Damaged Loan History
$stmt_history = $pdo->prepare("
    SELECT bt.id, bt.borrowed_at, bt.due_date, bt.returned_at, bt.fine_amount, bt.fine_status, bt.status,
           ia.accession_no, ia.placement_code,
           ci.title, ci.authors
    FROM borrow_transactions bt
    JOIN item_accessions ia ON bt.accession_id = ia.id
    JOIN catalog_items ci ON ia.catalog_id = ci.id
    WHERE bt.borrower_id = ? AND bt.status IN ('returned', 'damaged')
    ORDER BY bt.returned_at DESC
    LIMIT 15
");
$stmt_history->execute([$user_id]);
$loan_history = $stmt_history->fetchAll();

require_once __DIR__ . '/../includes/sidebar.php';
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<div x-data="myBorrowedBooksApp()" class="space-y-6">

    <!-- Top Header -->
    <div class="bg-theme-card border border-theme-border border-l-4 border-l-theme-accent rounded-2xl p-4 sm:p-6 flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-xs">
        <div>
            <span class="px-2.5 py-1 text-[10px] font-extrabold uppercase rounded-full bg-theme-accent/15 text-theme-accent">
                Closed-Loop Return Protocol
            </span>
            <h1 class="text-xl sm:text-2xl font-extrabold text-theme-text mt-2">
                My Borrowed Literature & Return Pass
            </h1>
            <p class="text-xs text-theme-muted mt-0.5">
                Click <strong>Return Book (Show QR)</strong> and present the QR along with the physical book to the Librarian at the 4th Floor Help Desk.
            </p>
        </div>

        <a href="<?= BASE_URL ?>/student/catalog.php"
           class="px-4 py-2.5 rounded-xl bg-theme-accent hover:bg-theme-accentHover text-white font-extrabold text-xs shadow transition shrink-0 text-center">
            + Browse OPAC Catalogue
        </a>
    </div>

    <!-- Active Borrowed Books Section -->
    <div class="bg-theme-card border border-theme-border rounded-2xl overflow-hidden shadow-xs">
        <div class="p-4 sm:p-5 border-b border-theme-border flex items-center justify-between">
            <div>
                <h2 class="text-base font-extrabold text-theme-text">Active Borrowed Books (<?= count($active_loans) ?>)</h2>
                <p class="text-xs text-theme-muted">Generate a Return QR when handing the physical copy back to the Help Desk</p>
            </div>
        </div>

        <?php if (empty($active_loans)): ?>
            <div class="p-10 text-center text-theme-muted text-xs">
                You currently have no active borrowed books. Visit the <a href="<?= BASE_URL ?>/student/catalog.php" class="text-theme-accent font-extrabold hover:underline">OPAC & 4F Map</a> to borrow literature.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs min-w-[680px]">
                    <thead>
                        <tr class="bg-theme-main text-theme-muted uppercase text-[10px] tracking-wider border-b border-theme-border">
                            <th class="py-3.5 px-5">Literature Title & Author</th>
                            <th class="py-3.5 px-5">Accession QR Code</th>
                            <th class="py-3.5 px-5">4F Shelf Location</th>
                            <th class="py-3.5 px-5">Borrowed / Due Date</th>
                            <th class="py-3.5 px-5">Status</th>
                            <th class="py-3.5 px-5 text-right">Return Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-theme-border">
                        <?php foreach ($active_loans as $loan): 
                            $is_overdue = ($loan['status'] === 'overdue' || strtotime($loan['due_date']) < time());
                        ?>
                            <tr class="hover:bg-theme-hover/40 transition">
                                <td class="py-4 px-5">
                                    <p class="font-extrabold text-theme-text text-sm"><?= e($loan['title']) ?></p>
                                    <p class="text-theme-muted mt-0.5"><?= e($loan['authors']) ?></p>
                                </td>
                                <td class="py-4 px-5 font-mono font-bold text-theme-accent"><?= e($loan['accession_no']) ?></td>
                                <td class="py-4 px-5">
                                    <span class="px-2.5 py-1 rounded-lg bg-amber-500/15 text-amber-400 font-mono font-extrabold">
                                        <?= e($loan['placement_code']) ?>
                                    </span>
                                </td>
                                <td class="py-4 px-5 font-mono">
                                    <p class="text-theme-muted">Out: <?= e(date('M d, Y', strtotime($loan['borrowed_at']))) ?></p>
                                    <p class="font-bold <?= $is_overdue ? 'text-red-400' : 'text-theme-text' ?>">
                                        Due: <?= e(date('M d, Y h:i A', strtotime($loan['due_date']))) ?>
                                    </p>
                                </td>
                                <td class="py-4 px-5">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase <?= $is_overdue ? 'bg-red-500/20 text-red-400' : 'bg-emerald-500/20 text-emerald-400' ?>">
                                        <?= $is_overdue ? 'OVERDUE' : 'BORROWED' ?>
                                    </span>
                                </td>
                                <td class="py-4 px-5 text-right">
                                    <button type="button"
                                            @click="openReturnQr('<?= e($loan['accession_no']) ?>', '<?= e(addslashes($loan['title'])) ?>', '<?= e($loan['placement_code']) ?>')"
                                            class="px-3.5 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold text-xs shadow transition inline-flex items-center gap-1.5">
                                        <span>🔄 Return Book (Show QR)</span>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Past Returned Books History -->
    <div class="bg-theme-card border border-theme-border rounded-2xl overflow-hidden shadow-xs">
        <div class="p-4 sm:p-5 border-b border-theme-border">
            <h2 class="text-base font-extrabold text-theme-text">Past Returned Literature History</h2>
            <p class="text-xs text-theme-muted">Completed circulation transactions verified by the Help Desk</p>
        </div>

        <?php if (empty($loan_history)): ?>
            <div class="p-8 text-center text-theme-muted text-xs">
                No past returned book records yet.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs min-w-[640px]">
                    <thead>
                        <tr class="bg-theme-main text-theme-muted uppercase text-[10px] tracking-wider border-b border-theme-border">
                            <th class="py-3 px-5">Literature Title</th>
                            <th class="py-3 px-5">Accession QR</th>
                            <th class="py-3 px-5">Returned Date</th>
                            <th class="py-3 px-5">Fine</th>
                            <th class="py-3 px-5">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-theme-border">
                        <?php foreach ($loan_history as $hist): 
                            $fine_amt = (float) $hist['fine_amount'];
                            $fine_badge = match($hist['fine_status']) {
                                'unpaid' => 'bg-red-500/20 text-red-400 border border-red-500/30',
                                'paid'   => 'bg-emerald-500/20 text-emerald-400',
                                'waived' => 'bg-amber-500/20 text-amber-400',
                                default  => 'bg-theme-main text-theme-muted'
                            };
                            $status_badge = $hist['status'] === 'damaged'
                                ? 'bg-red-500/20 text-red-400 border border-red-500/30'
                                : ($hist['fine_status'] === 'unpaid' ? 'bg-amber-500/20 text-amber-400' : 'bg-sky-500/20 text-sky-400');
                        ?>
                            <tr>
                                <td class="py-3 px-5">
                                    <p class="font-bold text-theme-text"><?= e($hist['title']) ?></p>
                                    <p class="text-theme-muted"><?= e($hist['authors']) ?></p>
                                </td>
                                <td class="py-3 px-5 font-mono text-theme-muted"><?= e($hist['accession_no']) ?></td>
                                <td class="py-3 px-5 font-mono text-theme-text"><?= e($hist['returned_at']) ?></td>
                                <td class="py-3 px-5">
                                    <span class="px-2 py-0.5 rounded font-mono text-[10px] font-extrabold uppercase <?= $fine_badge ?>">
                                        ₱<?= number_format($fine_amt, 2) ?><?= $fine_amt > 0 ? ' (' . e($hist['fine_status']) . ')' : '' ?>
                                    </span>
                                </td>
                                <td class="py-3 px-5">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase <?= $status_badge ?>">
                                        <?= $hist['status'] === 'damaged' ? 'DAMAGED' : ($hist['fine_status'] === 'unpaid' ? 'UNPAID FINE' : 'RETURNED') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- RETURN BOOK QR MODAL (With Invert Option) -->
    <div x-show="returnModalOpen" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 backdrop-blur-xs p-4"
         x-transition>
        <div @click.away="closeReturnModal()"
             class="w-full max-w-md bg-theme-card border-2 border-emerald-500 rounded-2xl shadow-2xl overflow-hidden text-center">
            <div class="p-4 bg-emerald-500/15 border-b border-theme-border flex items-center justify-between">
                <span class="px-2.5 py-0.5 rounded-full bg-emerald-500 text-slate-950 text-[10px] font-extrabold uppercase">
                    LIVE BOOK RETURN PASS
                </span>
                <button @click="closeReturnModal()" class="w-8 h-8 rounded-lg bg-theme-hover text-theme-muted font-bold">&times;</button>
            </div>

            <div class="p-6 space-y-4">
                <div>
                    <h3 class="text-base font-extrabold text-theme-text" x-text="activeTitle"></h3>
                    <p class="text-xs font-mono text-emerald-400 font-bold mt-1">
                        Accession: <span x-text="activeAccession"></span> • Shelf: <span x-text="activeShelf"></span>
                    </p>
                </div>

                <div class="flex items-center justify-center gap-4 mb-2">
                    <button type="button" @click="toggleInvert()"
                            :class="qrInverted ? 'bg-indigo-500 text-white' : 'bg-theme-main text-theme-muted border border-theme-border'"
                            class="px-3 py-1.5 rounded-lg text-[10px] font-extrabold uppercase transition shadow-sm">
                        <span x-text="qrInverted ? '◐ Inverted QR' : '◑ Standard QR'"></span>
                    </button>
                </div>

                <div class="inline-block p-4 rounded-2xl border-4 shadow-lg transition-colors duration-300"
                     :class="qrInverted ? 'bg-[#0f172a] border-indigo-500' : 'bg-white border-emerald-500'">
                    <div id="returnQrCanvas" class="w-48 h-48 flex items-center justify-center"></div>
                </div>

                <div>
                    <p class="text-xs font-mono font-bold text-emerald-400">
                        Auto-refreshes in <span x-text="secondsLeft"></span>s
                    </p>
                    <p class="text-[11px] text-theme-muted mt-2">
                        Hand the physical book and show this QR code to the <strong>4th Floor Help Desk Webcam</strong> to clear your loan.
                    </p>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
function myBorrowedBooksApp() {
    return {
        returnModalOpen: false,
        activeAccession: '',
        activeTitle: '',
        activeShelf: '',
        secondsLeft: 30,
        timer: null,
        qrInverted: false,
        lastPayload: '',

        toggleInvert() {
            this.qrInverted = !this.qrInverted;
            if (this.lastPayload) {
                this.renderQr(this.lastPayload);
            }
        },

        renderQr(payload) {
            this.lastPayload = payload;
            const container = document.getElementById('returnQrCanvas');
            if (container) {
                container.innerHTML = '';
                new QRCode(container, {
                    text: payload,
                    width: 192,
                    height: 192,
                    colorDark: this.qrInverted ? "#ffffff" : "#0f172a",
                    colorLight: this.qrInverted ? "#0f172a" : "#ffffff",
                    correctLevel: QRCode.CorrectLevel.M
                });
            }
        },

        async openReturnQr(accessionNo, title, shelfCode) {
            this.activeAccession = accessionNo;
            this.activeTitle = title;
            this.activeShelf = shelfCode;
            this.returnModalOpen = true;
            clearInterval(this.timer);

            const fetchToken = async () => {
                const res = await fetch('<?= BASE_URL ?>/api/catalog/interact.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'generate_return_qr', accession_no: accessionNo })
                });
                const data = await res.json();
                if (data.success) {
                    this.secondsLeft = data.seconds_left;
                    this.renderQr(data.qr_payload);
                }
            };

            await fetchToken();
            this.timer = setInterval(() => {
                this.secondsLeft--;
                if (this.secondsLeft <= 0) fetchToken();
            }, 1000);
        },

        closeReturnModal() {
            this.returnModalOpen = false;
            clearInterval(this.timer);
        }
    };
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>