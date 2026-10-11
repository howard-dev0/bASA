<?php
// admin/circulation_desk.php
$page_title = 'Webcam QR Circulation Desk';
require_once __DIR__ . '/../includes/header.php';
require_role(['librarian', 'super_admin']);

$pdo =$GLOBALS['pdo'];

// Fetch sample accessions for quick defense testing buttons
$sample_accessions =$pdo->query("
    SELECT ia.accession_no, ia.status, ci.title 
    FROM item_accessions ia 
    JOIN catalog_items ci ON ia.catalog_id = ci.id 
    ORDER BY ia.id ASC LIMIT 6
")->fetchAll();

require_once __DIR__ . '/../includes/sidebar.php';
?>
<!-- HTML5-QRCode Webcam Scanner Library -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<div x-data="circulationDeskApp()" x-init="loadRecentActivity()" class="space-y-6">

    <!-- Top Desk Status Banner -->
    <div class="bg-theme-card border border-theme-border border-l-4 border-l-theme-accent rounded-2xl p-4 sm:p-6 flex flex-col lg:flex-row lg:items-center justify-between gap-4 shadow-xs">
        <div>
            <span class="px-2.5 py-1 text-[10px] font-extrabold uppercase rounded-full bg-theme-accent/15 text-theme-accent">
                4th Floor Help Desk • Optical Verification Terminal
            </span>
            <h1 class="text-xl sm:text-2xl font-extrabold text-theme-text mt-2">
                Webcam QR Scanner & Closed-Loop Circulation
            </h1>
            <p class="text-xs text-theme-muted mt-0.5">
                Scan a Student's <strong>30s Dynamic QR Pass</strong> + <strong>Book QR Sticker</strong> (or scan a single <strong>Express Borrow / Return QR</strong>) to issue or return literature.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5 shrink-0">
            <button @click="toggleCamera()"
                    :class="cameraActive ? 'bg-[#D9232A] text-white' : 'bg-theme-accent text-white'"
                    class="px-4 py-2.5 rounded-xl text-xs font-extrabold shadow transition flex items-center gap-2">
                <span x-text="cameraActive ? '⏹ Stop Webcam' : '📷 Start Webcam Scanner'"></span>
            </button>
            <button @click="resetSession()"
                    class="px-3.5 py-2.5 rounded-xl bg-theme-main hover:bg-theme-hover border border-theme-border text-xs font-bold text-theme-muted hover:text-theme-text transition">
                ↺ Clear Desk HUD
            </button>
        </div>
    </div>

    <!-- Status Toast Alert -->
    <div x-show="alertMsg" x-cloak x-transition
         :class="alertSuccess ? 'bg-emerald-500/15 border-emerald-500/40 text-emerald-400' : 'bg-red-500/15 border-red-500/40 text-red-400'"
         class="p-4 rounded-xl border text-xs font-bold flex items-center justify-between">
        <span x-text="alertMsg"></span>
        <button @click="alertMsg = ''" class="text-base font-extrabold">&times;</button>
    </div>

    <!-- MAIN 12-COLUMN CIRCULATION WORKSPACE -->
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">

        <!-- LEFT 5 COLUMNS: WEBCAM OPTICAL VIEWPORT & SIMULATOR -->
        <div class="xl:col-span-5 space-y-4">
            <div class="bg-theme-card border border-theme-border rounded-2xl p-4 sm:p-5 shadow-xs">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-sm font-extrabold text-theme-text">Optical Webcam Viewport</h2>
                    <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase"
                          :class="cameraActive ? 'bg-emerald-500/20 text-emerald-400 animate-pulse' : 'bg-theme-main text-theme-muted'"
                          x-text="cameraActive ? '● CAMERA LIVE' : 'STANDBY'"></span>
                </div>

                <!-- HTML5 QR Reader Container -->
                <div class="w-full min-h-[250px] bg-slate-950 rounded-xl border-2 border-dashed border-theme-border overflow-hidden flex flex-col items-center justify-center relative">
                    <div id="qr-reader" class="w-full"></div>
                    <div x-show="!cameraActive" class="p-6 text-center space-y-2">
                        <p class="text-xs font-bold text-slate-300">Webcam Scanner is in Standby</p>
                        <p class="text-[11px] text-slate-400">Click <strong>Start Webcam Scanner</strong> above to scan via camera, or use the manual/simulator input below.</p>
                    </div>
                </div>

                <!-- Manual / USB Barcode Gun Input -->
                <form @submit.prevent="submitScan(manualInput)" class="mt-4 flex gap-2">
                    <input type="text" x-model="manualInput"
                           placeholder="Paste QR payload or Accession (e.g. BCP-BOK-IT-00001)"
                           class="flex-1 px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border text-xs font-mono text-theme-text outline-none focus:border-theme-accent">
                    <button type="submit"
                            class="px-4 py-2.5 rounded-xl bg-theme-accent text-white text-xs font-extrabold shrink-0">
                        Decode
                    </button>
                </form>

                <!-- CAPSTONE DEFENSE 1-CLICK SCAN SIMULATOR -->
                <div class="mt-4 pt-4 border-t border-theme-border space-y-2.5">
                    <p class="text-[10px] font-extrabold uppercase tracking-wider text-theme-muted">
                        ⚡ Capstone Defense Single-PC Scan Simulator
                    </p>
                    <div class="flex flex-wrap gap-1.5">
                        <button type="button" @click="submitScan('SIM-USR|240103537')"
                                class="px-2.5 py-1.5 rounded-lg bg-sky-500/15 text-sky-400 hover:bg-sky-500 hover:text-white text-[11px] font-bold transition">
                            👤 Scan Student #240103537 (Live 30s TOTP)
                        </button>
                        <button type="button" @click="submitScan('SIM-USR|240103538')"
                                class="px-2.5 py-1.5 rounded-lg bg-sky-500/15 text-sky-400 hover:bg-sky-500 hover:text-white text-[11px] font-bold transition">
                            👤 Scan Student #240103538
                        </button>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <?php foreach ($sample_accessions as$sa): ?>
                            <button type="button" @click="submitScan('<?= e($sa['accession_no']) ?>')"
                                    class="px-2.5 py-1.5 rounded-lg bg-theme-main hover:bg-theme-hover border border-theme-border text-[11px] font-mono font-bold text-theme-text transition">
                                📘 <?= e($sa['accession_no']) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT 7 COLUMNS: TWO-TIER VISUAL COUNTER-VERIFICATION HUD -->
        <div class="xl:col-span-7 space-y-4">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- TIER 1: BORROWER 2x2 IDENTITY VERIFICATION CARD -->
                <div class="bg-theme-card border-2 rounded-2xl p-5 transition"
                     :class="scannedStudent ? 'border-emerald-500/60' : 'border-theme-border'">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-theme-muted">Tier 1 • Borrower Verification</span>
                        <span x-show="scannedStudent" class="px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-400 text-[10px] font-extrabold">
                            ✓ 30s TOTP VERIFIED
                        </span>
                    </div>

                    <template x-if="!scannedStudent">
                        <div class="py-8 text-center text-xs text-theme-muted border border-dashed border-theme-border rounded-xl">
                            Scan Student's 30s Dynamic QR Pass (required for borrowing; optional for book return).
                        </div>
                    </template>

                    <template x-if="scannedStudent">
                        <div class="flex items-center gap-4">
                            <div class="w-16 h-16 rounded-2xl bg-theme-accent text-white font-cartoon font-bold text-2xl flex items-center justify-center shrink-0 shadow-md border-2 border-white/20">
                                <span x-text="scannedStudent.full_name.substring(0,1)"></span>
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-base font-extrabold text-theme-text truncate" x-text="scannedStudent.full_name"></h3>
                                <p class="text-xs font-mono font-bold text-theme-accent mt-0.5" x-text="'#' + scannedStudent.school_id"></p>
                                <p class="text-xs text-theme-muted mt-0.5" x-text="'Program: ' + (scannedStudent.program_code || scannedStudent.role)"></p>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- TIER 2: LITERATURE & ACCESSION STICKER VERIFICATION CARD -->
                <div class="bg-theme-card border-2 rounded-2xl p-5 transition"
                     :class="scannedBook ? 'border-theme-accent' : 'border-theme-border'">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-theme-muted">Tier 2 • Physical Book Verification</span>
                        <template x-if="scannedBook">
                            <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase"
                                  :class="scannedBook.status === 'available' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400'"
                                  x-text="scannedBook.status"></span>
                        </template>
                    </div>

                    <template x-if="!scannedBook">
                        <div class="py-8 text-center text-xs text-theme-muted border border-dashed border-theme-border rounded-xl">
                            Scan the QR sticker on the physical book or capstone (`BCP-BOK-...` or `BCP-RES-...`).
                        </div>
                    </template>

                    <template x-if="scannedBook">
                        <div>
                            <p class="font-mono text-xs font-extrabold text-theme-accent" x-text="scannedBook.accession_no"></p>
                            <h3 class="text-sm font-extrabold text-theme-text mt-0.5 line-clamp-2" x-text="scannedBook.title"></h3>
                            <p class="text-xs text-theme-muted mt-0.5" x-text="scannedBook.authors"></p>
                            <div class="mt-2.5 flex items-center gap-2">
                                <span class="px-2.5 py-0.5 rounded bg-theme-main border border-theme-border font-mono text-[11px] font-bold text-amber-400"
                                      x-text="'Shelf: ' + scannedBook.placement_code"></span>
                                <span class="text-[11px] font-mono text-theme-muted" x-text="scannedBook.subject_or_program"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- ACTION PANEL A: CHECKOUT / ISSUE LITERATURE -->
            <div x-show="scannedBook && scannedBook.status === 'available'" x-cloak x-transition
                 class="bg-theme-card border border-theme-border rounded-2xl p-5 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h3 class="text-base font-extrabold text-theme-text">Ready for Book Checkout</h3>
                        <p class="text-xs text-theme-muted">Verify that the physical book in hand matches the title above before confirming.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <label class="text-xs font-bold text-theme-muted">Loan Days:</label>
                        <select x-model="loanDays" class="px-3 py-1.5 rounded-xl bg-theme-main border border-theme-border text-xs font-bold text-theme-text">
                            <option value="1">1 Day (Overnight)</option>
                            <option value="3">3 Days (Standard)</option>
                            <option value="7">7 Days (Faculty/Thesis)</option>
                        </select>
                    </div>
                </div>

                <button type="button"
                        @click="commitCheckout()"
                        :disabled="!scannedStudent"
                        class="w-full py-3.5 px-5 rounded-xl bg-theme-accent hover:bg-theme-accentHover disabled:opacity-40 text-white font-extrabold text-xs shadow-lg transition">
                    <span x-show="scannedStudent" x-text="'✓ Issue ' + scannedBook?.accession_no + ' to ' + scannedStudent?.full_name"></span>
                    <span x-show="!scannedStudent">⚠️ Please Scan Student's 30s Dynamic QR Pass First</span>
                </button>
            </div>

            <!-- ACTION PANEL B: CLOSED-LOOP RETURN & FINE SETTLEMENT -->
            <div x-show="scannedBook && scannedBook.status === 'borrowed'" x-cloak x-transition
                 class="bg-theme-card border-2 border-amber-500/50 rounded-2xl p-5 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-theme-border">
                    <div>
                        <span class="px-2 py-0.5 rounded bg-amber-500/20 text-amber-400 text-[10px] font-extrabold uppercase">
                            CLOSED-LOOP RETURN VERIFICATION
                        </span>
                        <h3 class="text-base font-extrabold text-theme-text mt-1">
                            Borrowed by <span class="text-theme-accent" x-text="scannedBook?.active_loan?.borrower_name"></span>
                            (<span class="font-mono" x-text="'#' + scannedBook?.active_loan?.borrower_school_id"></span>)
                        </h3>
                        <p class="text-xs text-theme-muted font-mono mt-0.5">
                            Due Date: <span x-text="scannedBook?.active_loan?.due_date"></span>
                        </p>
                    </div>

                    <div class="text-right">
                        <p class="text-xs text-theme-muted uppercase font-bold">Computed Overdue Fine</p>
                        <p class="text-2xl font-extrabold font-mono"
                           :class="scannedBook?.active_loan?.computed_fine > 0 ? 'text-red-400' : 'text-emerald-400'"
                           x-text="'₱' + Number(scannedBook?.active_loan?.computed_fine || 0).toFixed(2)"></p>
                    </div>
                </div>

                <div x-show="scannedBook?.active_loan?.computed_fine > 0" class="flex flex-wrap items-center gap-3">
                    <label class="text-xs font-extrabold text-theme-text">Fine Settlement Status:</label>
                    <select x-model="returnFineStatus" class="px-3 py-2 rounded-xl bg-theme-main border border-theme-border text-xs font-bold text-theme-text">
                        <option value="paid">Paid at Help Desk Counter</option>
                        <option value="unpaid">Log as Unpaid Balance on Student Account</option>
                        <option value="waived">Waive Fine (Logs to Security Audit Trail)</option>
                    </select>
                </div>

                <button type="button"
                        @click="commitReturn()"
                        class="w-full py-3.5 px-5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold text-xs shadow-lg transition">
                    ✓ Confirm Physical Book Return & Restore to Shelf <span class="font-mono" x-text="scannedBook?.placement_code"></span>
                </button>
            </div>

            <!-- RECENT CIRCULATION LOGS TABLE -->
            <div class="bg-theme-card border border-theme-border rounded-2xl overflow-hidden shadow-xs">
                <div class="p-4 border-b border-theme-border flex items-center justify-between">
                    <h3 class="text-sm font-extrabold text-theme-text">Recent Help Desk Transactions</h3>
                    <span class="text-[11px] text-theme-muted">Live Audit Feed</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs min-w-[560px]">
                        <thead>
                            <tr class="bg-theme-main text-theme-muted uppercase text-[10px] border-b border-theme-border">
                                <th class="py-2.5 px-4">Student</th>
                                <th class="py-2.5 px-4">Accession & Title</th>
                                <th class="py-2.5 px-4">Shelf</th>
                                <th class="py-2.5 px-4">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-theme-border">
                            <template x-for="lg in recentLogs" :key="lg.id">
                                <tr>
                                    <td class="py-2.5 px-4">
                                        <p class="font-bold text-theme-text" x-text="lg.full_name"></p>
                                        <p class="font-mono text-[10px] text-theme-muted" x-text="'#' + lg.school_id"></p>
                                    </td>
                                    <td class="py-2.5 px-4">
                                        <p class="font-bold text-theme-text truncate max-w-[200px]" x-text="lg.title"></p>
                                        <p class="font-mono text-[10px] text-theme-accent" x-text="lg.accession_no"></p>
                                    </td>
                                    <td class="py-2.5 px-4 font-mono font-bold text-amber-400" x-text="lg.placement_code"></td>
                                    <td class="py-2.5 px-4">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase"
                                              :class="lg.status === 'returned' ? 'bg-sky-500/20 text-sky-400' : 'bg-emerald-500/20 text-emerald-400'"
                                              x-text="lg.status"></span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
function circulationDeskApp() {
    return {
        cameraActive: false,
        html5QrCode: null,
        manualInput: '',
        scannedStudent: null,
        scannedBook: null,
        loanDays: 3,
        returnFineStatus: 'paid',
        recentLogs: [],
        alertMsg: '',
        alertSuccess: true,

        showToast(msg, ok = true) {
            this.alertMsg = msg;
            this.alertSuccess = ok;
        },

        resetSession() {
            this.scannedStudent = null;
            this.scannedBook = null;
            this.manualInput = '';
            this.alertMsg = '';
        },

        async loadRecentActivity() {
            const res = await fetch('<?= BASE_URL ?>/api/circulation/process.php?action=recent_activity');
            const data = await res.json();
            if (data.success) this.recentLogs = data.logs;
        },

        async toggleCamera() {
            if (this.cameraActive) {
                if (this.html5QrCode) {
                    await this.html5QrCode.stop();
                    this.html5QrCode.clear();
                }
                this.cameraActive = false;
                return;
            }

            try {
                this.html5QrCode = new Html5Qrcode("qr-reader");
                this.cameraActive = true;
                await this.html5QrCode.start(
                    { facingMode: "environment" },
                    { fps: 10, qrbox: { width: 220, height: 220 } },
                    (decodedText) => {
                        this.submitScan(decodedText);
                    },
                    () => {}
                );
            } catch (err) {
                this.cameraActive = false;
                this.showToast("Camera permission denied or no webcam detected. Use the simulator buttons below.", false);
            }
        },

        async submitScan(codeString) {
            if (!codeString) return;
            const res = await fetch('<?= BASE_URL ?>/api/circulation/process.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'scan_decode', qr_code: codeString })
            });
            const data = await res.json();
            this.showToast(data.message, data.success);

            if (data.success) {
                if (data.scan_type === 'student_qr') {
                    this.scannedStudent = data.student;
                } else if (data.scan_type === 'book_qr') {
                    this.scannedBook = data.book;
                } else if (data.scan_type === 'express_combo') {
                    this.scannedStudent = data.student;
                    this.scannedBook = data.book;
                }
                this.manualInput = '';
            }
        },

        async commitCheckout() {
            if (!this.scannedStudent || !this.scannedBook) return;
            const res = await fetch('<?= BASE_URL ?>/api/circulation/process.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'checkout',
                    student_id: this.scannedStudent.id,
                    accession_id: this.scannedBook.id,
                    loan_days: this.loanDays
                })
            });
            const data = await res.json();
            this.showToast(data.message, data.success);
            if (data.success) {
                this.scannedBook = null;
                await this.loadRecentActivity();
            }
        },

        async commitReturn() {
            if (!this.scannedBook) return;
            const res = await fetch('<?= BASE_URL ?>/api/circulation/process.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'return_book',
                    accession_id: this.scannedBook.id,
                    fine_status: this.returnFineStatus
                })
            });
            const data = await res.json();
            this.showToast(data.message, data.success);
            if (data.success) {
                this.scannedBook = null;
                await this.loadRecentActivity();
            }
        }
    };
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>