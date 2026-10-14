<?php
// admin/circulation_desk.php
$page_title = 'Webcam QR Circulation Desk';
require_once __DIR__ . '/../includes/header.php';
require_role(['librarian', 'super_admin']);

$pdo = $GLOBALS['pdo'];
$dev_mode = is_dev_mode($pdo);

$sample_accessions = [];
if ($dev_mode) {
    $sample_accessions = $pdo->query("
        SELECT ia.accession_no, ia.status, ci.title 
        FROM item_accessions ia 
        JOIN catalog_items ci ON ia.catalog_id = ci.id 
        ORDER BY ia.id ASC LIMIT 6
    ")->fetchAll();
}

require_once __DIR__ . '/../includes/sidebar.php';
?>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<style>
    /* CSS Filter to digitally invert the visual camera feed on the screen */
    .qr-invert-active video {
        filter: invert(1) hue-rotate(180deg) contrast(1.5) brightness(1.2);
    }
</style>

<div x-data="circulationDeskApp()" x-init="loadRecentActivity()"
     @keydown.escape.window="alertMsg = ''"
     @keydown.enter.window="if (alertMsg) { $event.preventDefault(); $event.stopPropagation(); alertMsg = ''; }"
     class="space-y-6">

    <!-- CENTERED SCREEN RESPONSE MODAL -->
    <div x-show="alertMsg" x-cloak
         class="fixed inset-0 z-[100] flex items-center justify-center bg-black/70 backdrop-blur-xs p-4"
         x-transition>
        <div @click.away="alertMsg = ''"
             :class="alertSuccess ? 'border-emerald-500' : 'border-[#D9232A]'"
             class="w-full max-w-md bg-theme-card border-2 rounded-2xl shadow-2xl p-6 text-center space-y-4">
            <div :class="alertSuccess ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/40' : 'bg-red-500/20 text-red-400 border-red-500/40'"
                 class="w-14 h-14 rounded-2xl border mx-auto flex items-center justify-center text-2xl font-extrabold">
                <span x-text="alertSuccess ? '✓' : '⚠️'"></span>
            </div>
            <div>
                <h3 class="text-base font-extrabold" :class="alertSuccess ? 'text-emerald-400' : 'text-red-400'"
                    x-text="alertSuccess ? 'Circulation Scan Verified' : 'Scan / Verification Error'"></h3>
                <p class="text-xs sm:text-sm text-theme-text font-semibold mt-1.5 leading-relaxed" x-text="alertMsg"></p>
            </div>
            <button type="button" x-ref="deskAlertOkBtn" @click="alertMsg = ''"
                    :class="alertSuccess ? 'bg-emerald-500 text-slate-950 hover:bg-emerald-400' : 'bg-[#D9232A] hover:bg-red-700 text-white'"
                    class="w-full py-2.5 px-5 rounded-xl font-extrabold text-xs shadow-lg transition outline-none">
                OK
            </button>
        </div>
    </div>

    <!-- Top Desk Status Banner -->
    <div class="bg-theme-card border border-theme-border border-l-4 border-l-theme-accent rounded-2xl p-4 sm:p-6 flex flex-col lg:flex-row lg:items-center justify-between gap-4 shadow-xs">
        <div>
            <span class="px-2.5 py-1 text-[10px] font-extrabold uppercase rounded-full bg-theme-accent text-white">
                4th Floor Help Desk • Optical Verification Terminal
            </span>
            <h1 class="text-xl sm:text-2xl font-extrabold text-theme-text mt-2">
                Webcam QR Scanner & Closed-Loop Circulation
            </h1>
            <p class="text-xs text-theme-muted mt-0.5">
                Scan a Student's <strong>30s Dynamic QR Pass</strong> to verify identity, settle unpaid balances, or pair with a <strong>Book QR Sticker</strong>.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5 shrink-0">
            <button @click="toggleCamera()"
                    :class="cameraActive ? 'bg-[#D9232A] text-white hover:bg-red-700' : 'bg-theme-accent hover:bg-theme-accentHover text-white'"
                    class="px-4 py-2.5 rounded-xl text-xs font-extrabold shadow transition flex items-center gap-2">
                <span x-text="cameraActive ? '⏹ Stop Webcam' : '📷 Start Webcam Scanner'"></span>
            </button>
            <button @click="resetSession()"
                    class="px-3.5 py-2.5 rounded-xl bg-theme-main hover:bg-theme-hover border border-theme-border text-xs font-bold text-theme-muted hover:text-theme-text transition">
                ↺ Clear Desk HUD
            </button>
        </div>
    </div>

    <!-- MAIN 12-COLUMN CIRCULATION WORKSPACE -->
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">

        <!-- LEFT 5 COLUMNS: WEBCAM OPTICAL VIEWPORT & SIMULATOR -->
        <div class="xl:col-span-5 space-y-4">
            <div class="bg-theme-card border border-theme-border rounded-2xl p-4 sm:p-5 shadow-xs">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-sm font-extrabold text-theme-text">Optical Webcam Viewport</h2>
                    <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase"
                          :class="cameraActive ? 'bg-emerald-500/20 text-emerald-400 animate-pulse' : 'bg-theme-main text-theme-muted border border-theme-border'"
                          x-text="cameraActive ? '● CAMERA LIVE' : 'STANDBY'"></span>
                </div>

                <div class="w-full min-h-[260px] bg-slate-950 rounded-xl border-2 border-dashed border-theme-border overflow-hidden flex flex-col items-center justify-center relative">
                    <div id="qr-reader" class="w-full relative z-0" :class="{ 'qr-invert-active': camInvert }"></div>
                    
                    <div x-show="isProcessing" class="absolute inset-0 bg-black/60 z-10 flex flex-col items-center justify-center backdrop-blur-sm">
                        <span class="px-4 py-2 bg-theme-accent text-white font-extrabold text-xs rounded-full shadow-lg animate-pulse">⏳ Processing Scan...</span>
                    </div>

                    <div x-show="!cameraActive" class="absolute inset-0 z-10 flex flex-col items-center justify-center p-6 text-center space-y-2 bg-slate-950">
                        <p class="text-xs font-bold text-slate-300">Webcam Scanner is in Standby</p>
                        <p class="text-[11px] text-slate-400">Click <strong>Start Webcam Scanner</strong> above to scan via camera, or use the manual input below.</p>
                    </div>
                </div>

                <!-- HARDWARE CAMERA CONTROLS -->
                <div x-show="cameraActive" x-transition class="mt-4 p-4 rounded-xl bg-theme-main border border-theme-border space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-theme-border pb-3">
                        <span class="text-[11px] font-extrabold uppercase tracking-wider text-theme-muted">Hardware Lens Controls</span>
                        <div class="flex flex-wrap items-center gap-2">
                            <button @click="toggleInvert()" type="button"
                                    :class="camInvert ? 'bg-indigo-500 text-white shadow-[0_0_10px_rgba(99,102,241,0.4)]' : 'bg-slate-700 text-white'"
                                    class="px-2.5 py-1 rounded-lg text-[10px] font-extrabold uppercase flex items-center gap-1 transition">
                                <span x-text="camInvert ? '◐ Invert: ON' : '◐ Invert: OFF'"></span>
                            </button>

                            <button x-show="hasTorch" @click="toggleTorch()" type="button"
                                    :class="camTorchEnabled ? 'bg-amber-400 text-slate-950 shadow-[0_0_10px_rgba(251,191,36,0.4)]' : 'bg-slate-700 text-white'"
                                    class="px-2.5 py-1 rounded-lg text-[10px] font-extrabold uppercase flex items-center gap-1 transition">
                                <span x-text="camTorchEnabled ? '🔦 Flash On' : '🔦 Flash Off'"></span>
                            </button>
                            
                            <span class="px-2 py-0.5 rounded bg-emerald-500/15 text-emerald-400 text-[9px] font-bold uppercase" x-text="hwFocusMode"></span>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <span class="text-lg" title="Zoom">🔍</span>
                            <div class="flex-1">
                                <div class="flex items-center justify-between mb-1">
                                    <label class="block text-[10px] font-extrabold uppercase text-theme-muted">Optical Zoom</label>
                                    <span class="text-[10px] font-mono text-theme-accent font-bold" x-text="camZoom + 'x'"></span>
                                </div>
                                <input type="range" x-model="camZoom" @input="updateCameraSettings()" :min="minZoom" :max="maxZoom" step="0.1"
                                       class="w-full accent-theme-accent cursor-pointer">
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <span class="text-lg" title="Exposure">☀️</span>
                            <div class="flex-1">
                                <div class="flex items-center justify-between mb-1">
                                    <label class="block text-[10px] font-extrabold uppercase text-theme-muted">Exposure Comp</label>
                                    <span class="text-[10px] font-mono text-amber-400 font-bold" x-text="camExposure"></span>
                                </div>
                                <input type="range" x-model="camExposure" @input="updateCameraSettings()" :min="minExposure" :max="maxExposure" step="0.5"
                                       class="w-full accent-amber-500 cursor-pointer">
                            </div>
                        </div>
                    </div>
                </div>

                <form @submit.prevent="submitScan(manualInput)" class="mt-4 flex gap-2">
                    <input type="text" x-model="manualInput"
                           placeholder="Paste QR payload or Accession (e.g. BCP-BOK-IT-00001)"
                           class="flex-1 px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border text-xs font-mono text-theme-text outline-none focus:border-theme-accent transition">
                    <button type="submit" :disabled="isProcessing"
                            class="px-4 py-2.5 rounded-xl bg-theme-accent hover:bg-theme-accentHover text-white text-xs font-extrabold shrink-0 shadow transition disabled:opacity-50">
                        Decode
                    </button>
                </form>

                <?php if ($dev_mode): ?>
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
                        <?php foreach ($sample_accessions as $sa): ?>
                            <button type="button" @click="submitScan('<?= e($sa['accession_no']) ?>')"
                                    class="px-2.5 py-1.5 rounded-lg bg-theme-main hover:bg-theme-hover border border-theme-border text-[11px] font-mono font-bold text-theme-text transition">
                                📘 <?= e($sa['accession_no']) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- RIGHT 7 COLUMNS: TWO-TIER VISUAL COUNTER-VERIFICATION HUD -->
        <div class="xl:col-span-7 space-y-4">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- TIER 1: BORROWER 2x2 IDENTITY & DEBT VERIFICATION CARD -->
                <div class="bg-theme-card border-2 rounded-2xl p-5 transition-colors duration-300"
                     :class="scannedStudent ? (scannedStudent.unpaid_balance > 0 ? 'border-red-500/70 shadow-[0_0_15px_rgba(239,68,68,0.12)]' : 'border-emerald-500/60 shadow-[0_0_15px_rgba(16,185,129,0.1)]') : 'border-theme-border shadow-xs'">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-theme-muted">Tier 1 • Borrower Verification</span>
                        <span x-show="scannedStudent" class="px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-400 text-[10px] font-extrabold">
                            ✓ 30s TOTP VERIFIED
                        </span>
                    </div>

                    <template x-if="!scannedStudent">
                        <div class="py-8 text-center text-xs text-theme-muted border border-dashed border-theme-border rounded-xl">
                            Scan Student's 30s Dynamic QR Pass (verifies identity, checks unpaid balance/debt, or pairs with a book).
                        </div>
                    </template>

                    <template x-if="scannedStudent">
                        <div class="space-y-3">
                            <div class="flex items-center gap-4">
                                <template x-if="scannedStudent.photo_path">
                                    <img :src="'<?= BASE_URL ?>/' + scannedStudent.photo_path"
                                         class="w-16 h-16 rounded-2xl object-cover shrink-0 shadow-md border-2 border-emerald-500">
                                </template>
                                <template x-if="!scannedStudent.photo_path">
                                    <div class="w-16 h-16 rounded-2xl bg-theme-accent text-white font-cartoon font-bold text-2xl flex items-center justify-center shrink-0 shadow-md border-2 border-theme-border">
                                        <span x-text="scannedStudent.full_name.substring(0,1)"></span>
                                    </div>
                                </template>
                                <div class="min-w-0 flex-1">
                                    <h3 class="text-base font-extrabold text-theme-text truncate" x-text="scannedStudent.full_name"></h3>
                                    <p class="text-xs font-mono font-bold text-theme-accent mt-0.5" x-text="'#' + scannedStudent.school_id"></p>
                                    <p class="text-xs text-theme-muted mt-0.5" x-text="'Program: ' + (scannedStudent.program_code || scannedStudent.role)"></p>
                                </div>
                            </div>

                            <!-- Student Unpaid Balance / Debt Status Pill -->
                            <div class="pt-2.5 border-t border-theme-border flex items-center justify-between text-xs">
                                <span class="font-bold text-theme-muted">Account Balance / Debt:</span>
                                <span class="px-2.5 py-1 rounded-lg font-mono font-extrabold"
                                      :class="Number(scannedStudent.unpaid_balance || 0) > 0 ? 'bg-red-500/20 text-red-400 border border-red-500/40' : 'bg-emerald-500/20 text-emerald-400'"
                                      x-text="Number(scannedStudent.unpaid_balance || 0) > 0 ? '⚠️ ₱' + Number(scannedStudent.unpaid_balance).toFixed(2) + ' UNPAID' : '✓ ₱0.00 (Cleared)'"></span>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- TIER 2: LITERATURE VERIFICATION CARD -->
                <div class="bg-theme-card border-2 rounded-2xl p-5 transition-colors duration-300"
                     :class="scannedBook ? 'border-theme-accent shadow-[0_0_15px_rgba(0,137,208,0.1)]' : 'border-theme-border shadow-xs'">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-theme-muted">Tier 2 • Physical Book Verification</span>
                        <template x-if="scannedBook">
                            <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase"
                                  :class="{
                                      'bg-emerald-500/20 text-emerald-400': scannedBook.status === 'available',
                                      'bg-amber-500/20 text-amber-400': scannedBook.status === 'borrowed',
                                      'bg-red-500/20 text-red-400': scannedBook.status === 'damaged' || scannedBook.status === 'lost',
                                      'bg-indigo-500/20 text-indigo-400': scannedBook.status === 'reserved'
                                  }"
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

            <!-- ACTION PANEL: STUDENT UNPAID BALANCE / DEBT SETTLEMENT (Triggered via Student QR) -->
            <div x-show="scannedStudent && Number(scannedStudent.unpaid_balance || 0) > 0" x-cloak x-transition
                 class="bg-theme-card border-2 border-[#D9232A]/70 rounded-2xl p-5 space-y-4 shadow-lg">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-theme-border">
                    <div>
                        <span class="px-2.5 py-0.5 rounded bg-red-500/20 text-red-400 text-[10px] font-extrabold uppercase">
                            STUDENT QR BALANCE / DEBT LEDGER
                        </span>
                        <h3 class="text-base font-extrabold text-theme-text mt-1">
                            Outstanding Fines for <span class="text-theme-accent" x-text="scannedStudent?.full_name"></span>
                        </h3>
                        <p class="text-xs text-theme-muted">Settle overdue or damaged book balances directly using the student's scanned QR pass.</p>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] font-extrabold uppercase text-theme-muted">Total Unpaid Debt</p>
                        <p class="text-2xl font-mono font-extrabold text-red-400"
                           x-text="'₱' + Number(scannedStudent?.unpaid_balance || 0).toFixed(2)"></p>
                    </div>
                </div>

                <!-- Breakdown of Unpaid Items -->
                <div class="space-y-2 max-h-44 overflow-y-auto pr-1">
                    <template x-for="item in (scannedStudent?.unpaid_items || [])" :key="item.id">
                        <div class="p-3 rounded-xl bg-theme-main border border-theme-border flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                            <div>
                                <div class="flex items-center gap-2">
                                    <p class="font-extrabold text-theme-text" x-text="item.title"></p>
                                    <span x-show="item.status === 'damaged'" class="px-1.5 py-0.2 rounded bg-red-500/20 text-red-400 text-[9px] font-extrabold uppercase">DAMAGED</span>
                                </div>
                                <p class="text-[11px] font-mono text-theme-muted"
                                   x-text="item.accession_no + ' • Due: ' + item.due_date"></p>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="font-mono font-extrabold text-red-400" x-text="'₱' + Number(item.fine_amount).toFixed(2)"></span>
                                <button type="button" @click="settleStudentDebt('paid', item.id)"
                                        class="px-2.5 py-1 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold text-[11px] transition">
                                    Mark Paid
                                </button>
                                <button type="button" @click="settleStudentDebt('waived', item.id)"
                                        class="px-2.5 py-1 rounded-lg bg-theme-card hover:bg-theme-hover border border-theme-border text-theme-muted hover:text-theme-text font-extrabold text-[11px] transition">
                                    Waive
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Settle All Buttons -->
                <div class="flex flex-col sm:flex-row gap-2.5 pt-2 border-t border-theme-border">
                    <button type="button" @click="settleStudentDebt('paid', 0)"
                            class="flex-1 py-3 px-4 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold text-xs shadow-lg transition">
                        ✓ Receive Full Payment at Counter (<span class="font-mono" x-text="'₱' + Number(scannedStudent?.unpaid_balance || 0).toFixed(2)"></span>)
                    </button>
                    <button type="button" @click="settleStudentDebt('waived', 0)"
                            class="py-3 px-4 rounded-xl bg-theme-main hover:bg-theme-hover border border-theme-border text-theme-muted hover:text-theme-text font-extrabold text-xs transition">
                        Waive All Balance (Audit Logged)
                    </button>
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
                        <select x-model="loanDays" class="px-3 py-1.5 rounded-xl bg-theme-main border border-theme-border text-xs font-bold text-theme-text outline-none">
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

            <!-- ACTION PANEL B: CLOSED-LOOP RETURN & CUSTOM DAMAGED PENALTY SETTLEMENT -->
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
                        <p class="text-xs text-theme-muted uppercase font-bold">Total Overdue & Penalty Fine</p>
                        <p class="text-2xl font-extrabold font-mono"
                           :class="computedTotalFine > 0 ? 'text-red-400' : 'text-emerald-400'"
                           x-text="'₱' + computedTotalFine.toFixed(2)"></p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Physical Condition Toggle + Librarian Custom Broken Book Fine Input -->
                    <div class="p-3.5 rounded-xl bg-theme-main border border-theme-border space-y-3">
                        <label class="block text-[10px] font-extrabold uppercase tracking-wider text-theme-muted">Inspect Book Condition</label>
                        <div class="flex flex-col gap-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" x-model="bookCondition" value="good" class="w-4 h-4 accent-emerald-500">
                                <span class="text-xs font-bold text-emerald-400">Good / Normal Return</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="radio" x-model="bookCondition" value="damaged" class="w-4 h-4 accent-[#D9232A]">
                                <span class="text-xs font-bold text-red-400">Damaged / Broken Book (Set Fine)</span>
                            </label>
                        </div>

                        <!-- Librarian Custom Fine Input for Broken/Damaged Books -->
                        <div x-show="bookCondition === 'damaged'" x-transition class="pt-2 border-t border-theme-border">
                            <label class="block text-[10px] font-extrabold uppercase text-red-400 mb-1">
                                Librarian Damaged Book Fine (₱ PHP):
                            </label>
                            <input type="number" step="10" min="0" x-model="damagedFineAmount"
                                   class="w-full px-3 py-2 rounded-xl bg-theme-card border border-red-500/50 font-mono text-xs font-extrabold text-theme-text outline-none focus:border-red-400">
                        </div>
                    </div>

                    <!-- Fine Settlement Status (Only shown if fines exist) -->
                    <div x-show="computedTotalFine > 0" class="p-3.5 rounded-xl bg-theme-main border border-theme-border flex flex-col justify-center">
                        <label class="block text-[10px] font-extrabold uppercase tracking-wider text-theme-muted mb-1.5">Fine Settlement Status</label>
                        <select x-model="returnFineStatus" class="w-full px-3 py-2.5 rounded-xl bg-theme-card border border-theme-border text-xs font-bold text-theme-text outline-none">
                            <option value="paid">Paid at Help Desk Counter</option>
                            <option value="unpaid">Log as Unpaid Debt on Student Account</option>
                            <option value="waived">Waive Fine (Logs to Security Audit Trail)</option>
                        </select>
                        <p class="text-[10px] text-theme-muted mt-2">
                            If logged as <strong>Unpaid Debt</strong>, the student can pay it later by scanning their Student QR Pass.
                        </p>
                    </div>
                </div>

                <button type="button"
                        @click="commitReturn()"
                        :class="bookCondition === 'damaged' ? 'bg-[#D9232A] hover:bg-red-700 text-white' : 'bg-emerald-500 hover:bg-emerald-400 text-slate-950'"
                        class="w-full py-3.5 px-5 rounded-xl font-extrabold text-xs shadow-lg transition">
                    <span x-show="bookCondition === 'good'">✓ Confirm Normal Return & Restore to Shelf <span class="font-mono" x-text="scannedBook?.placement_code"></span></span>
                    <span x-show="bookCondition === 'damaged'" x-text="'⚠️ Mark Book Damaged & Issue ₱' + Number(damagedFineAmount || 0).toFixed(2) + ' Fine'"></span>
                </button>
            </div>

            <!-- RECENT CIRCULATION LOGS TABLE -->
            <div class="bg-theme-card border border-theme-border rounded-2xl overflow-hidden shadow-xs">
                <div class="p-4 border-b border-theme-border flex items-center justify-between">
                    <h3 class="text-sm font-extrabold text-theme-text">Recent Help Desk Transactions</h3>
                    <span class="text-[11px] text-theme-muted">Live Audit Feed</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs min-w-[600px]">
                        <thead>
                            <tr class="bg-theme-main text-theme-muted uppercase text-[10px] border-b border-theme-border">
                                <th class="py-2.5 px-4">Student</th>
                                <th class="py-2.5 px-4">Accession & Title</th>
                                <th class="py-2.5 px-4">Shelf</th>
                                <th class="py-2.5 px-4">Fine</th>
                                <th class="py-2.5 px-4">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-theme-border">
                            <template x-for="lg in recentLogs" :key="lg.id">
                                <tr class="hover:bg-theme-hover/40 transition">
                                    <td class="py-2.5 px-4">
                                        <p class="font-bold text-theme-text" x-text="lg.full_name"></p>
                                        <p class="font-mono text-[10px] text-theme-muted" x-text="'#' + lg.school_id"></p>
                                    </td>
                                    <td class="py-2.5 px-4">
                                        <p class="font-bold text-theme-text truncate max-w-[200px]" x-text="lg.title"></p>
                                        <p class="font-mono text-[10px] text-theme-accent" x-text="lg.accession_no"></p>
                                    </td>
                                    <td class="py-2.5 px-4 font-mono font-bold text-amber-400" x-text="lg.placement_code"></td>
                                    <td class="py-2.5 px-4 font-mono">
                                        <span :class="{
                                                  'text-red-400 font-extrabold': Number(lg.fine_amount) > 0 && lg.fine_status === 'unpaid',
                                                  'text-emerald-400 font-bold': Number(lg.fine_amount) > 0 && lg.fine_status === 'paid',
                                                  'text-amber-400 font-bold': Number(lg.fine_amount) > 0 && lg.fine_status === 'waived',
                                                  'text-theme-muted': Number(lg.fine_amount) === 0
                                              }"
                                              x-text="'₱' + Number(lg.fine_amount || 0).toFixed(2) + (Number(lg.fine_amount) > 0 ? ' (' + lg.fine_status + ')' : '')"></span>
                                    </td>
                                    <td class="py-2.5 px-4">
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase inline-block"
                                              :class="{
                                                  'bg-red-500/20 text-red-400 border border-red-500/30': lg.status === 'damaged',
                                                  'bg-amber-500/20 text-amber-400 border border-amber-500/30': lg.status === 'overdue' || (lg.status === 'returned' && lg.fine_status === 'unpaid'),
                                                  'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30': lg.status === 'active',
                                                  'bg-sky-500/20 text-sky-400 border border-sky-500/30': lg.status === 'returned' && lg.fine_status !== 'unpaid'
                                              }"
                                              x-text="lg.status === 'damaged' ? 'DAMAGED' : (lg.status === 'active' ? 'BORROWED' : (lg.status === 'overdue' ? 'OVERDUE' : (lg.fine_status === 'unpaid' ? 'UNPAID FINE' : 'RETURNED')))"></span>
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
// Canvas Image Data Interceptor for Dark-Mode QR Reading
window.basaCamInvertEnabled = false;
const originalGetImageData = CanvasRenderingContext2D.prototype.getImageData;

CanvasRenderingContext2D.prototype.getImageData = function(x, y, w, h, settings) {
    const imageData = originalGetImageData.call(this, x, y, w, h, settings);
    if (window.basaCamInvertEnabled) {
        const data = imageData.data;
        for (let i = 0; i < data.length; i += 4) {
            data[i]     = 255 - data[i];
            data[i + 1] = 255 - data[i + 1];
            data[i + 2] = 255 - data[i + 2];
        }
    }
    return imageData;
};

function circulationDeskApp() {
    return {
        cameraActive: false,
        html5QrCode: null,
        manualInput: '',
        scannedStudent: null,
        scannedBook: null,
        loanDays: 3,
        bookCondition: 'good',
        damagedFineAmount: 300,
        returnFineStatus: 'paid',
        recentLogs: [],
        
        isProcessing: false,
        lastScannedCode: '',
        
        alertMsg: '',
        alertSuccess: true,

        camTrack: null,
        camZoom: 1,
        minZoom: 1,
        maxZoom: 5,
        camExposure: 0,
        minExposure: -3,
        maxExposure: 3,
        camTorchEnabled: false,
        hasTorch: false,
        camInvert: false, 
        hwFocusMode: 'Auto-Focus Disabled',

        get computedTotalFine() {
            let baseFine = Number(this.scannedBook?.active_loan?.computed_fine || 0);
            if (this.bookCondition === 'damaged') {
                baseFine += Math.max(0, Number(this.damagedFineAmount || 0));
            }
            return baseFine;
        },

        showToast(msg, ok = true) {
            if (document.activeElement) {
                document.activeElement.blur();
            }
            this.alertMsg = msg;
            this.alertSuccess = ok;
            this.$nextTick(() => {
                if (this.$refs.deskAlertOkBtn) {
                    this.$refs.deskAlertOkBtn.focus();
                }
            });
        },

        resetSession() {
            this.scannedStudent = null;
            this.scannedBook = null;
            this.manualInput = '';
            this.alertMsg = '';
            this.lastScannedCode = '';
            this.bookCondition = 'good';
            this.damagedFineAmount = 300;
        },

        async loadRecentActivity() {
            const res = await fetch('<?= BASE_URL ?>/api/circulation/process.php?action=recent_activity');
            const data = await res.json();
            if (data.success) this.recentLogs = data.logs;
        },

        toggleInvert() {
            this.camInvert = !this.camInvert;
            window.basaCamInvertEnabled = this.camInvert;
        },

        async toggleCamera() {
            if (this.cameraActive) {
                if (this.html5QrCode) {
                    if (this.camTrack && this.hasTorch && this.camTorchEnabled) {
                        this.camTrack.applyConstraints({ advanced: [{ torch: false }] }).catch(()=>{});
                    }
                    await this.html5QrCode.stop();
                    this.html5QrCode.clear();
                }
                this.cameraActive = false;
                this.camTrack = null;
                this.hasTorch = false;
                this.camTorchEnabled = false;
                this.camInvert = false;
                window.basaCamInvertEnabled = false;
                return;
            }

            try {
                const formatsToSupport = [ Html5QrcodeSupportedFormats.QR_CODE ];

                this.html5QrCode = new Html5Qrcode("qr-reader", { formatsToSupport: formatsToSupport });
                this.cameraActive = true;
                this.hwFocusMode = 'Calibrating Lens...';
                
                const cameraConfig = { facingMode: "environment" };

                await this.html5QrCode.start(
                    cameraConfig,
                    { 
                        fps: 20,
                        qrbox: (viewfinderWidth, viewfinderHeight) => {
                            let minEdgePercentage = 0.75; 
                            let minEdgeSize = Math.min(viewfinderWidth, viewfinderHeight);
                            let qrboxSize = Math.floor(minEdgeSize * minEdgePercentage);
                            return { width: qrboxSize, height: qrboxSize };
                        },
                        aspectRatio: 1.0,
                        disableFlip: false 
                    },
                    (decodedText) => {
                        this.submitScan(decodedText);
                    },
                    (err) => {}
                );

                setTimeout(() => {
                    const video = document.querySelector('#qr-reader video');
                    if (video && video.srcObject) {
                        this.camTrack = video.srcObject.getVideoTracks()[0];
                        if (this.camTrack && typeof this.camTrack.getCapabilities === 'function') {
                            const caps = this.camTrack.getCapabilities();
                            
                            if (caps.zoom) {
                                this.minZoom = caps.zoom.min || 1;
                                this.maxZoom = caps.zoom.max || 5;
                                this.camZoom = this.minZoom;
                            }
                            if (caps.exposureCompensation) {
                                this.minExposure = caps.exposureCompensation.min || -3;
                                this.maxExposure = caps.exposureCompensation.max || 3;
                                this.camExposure = 0;
                            }
                            if (caps.torch) {
                                this.hasTorch = true;
                            }
                            if (caps.focusMode && caps.focusMode.includes('continuous')) {
                                this.hwFocusMode = 'Continuous Auto-Focus Active';
                            } else {
                                this.hwFocusMode = 'Fixed Focus Mode';
                            }
                            
                            this.updateCameraSettings();
                        } else {
                            this.hwFocusMode = 'Hardware Constraints Blocked by Browser';
                        }
                    }
                }, 1500);

            } catch (err) {
                this.cameraActive = false;
                this.showToast("Camera permission denied, blocked by browser, or no webcam detected.", false);
            }
        },

        toggleTorch() {
            if (!this.hasTorch || !this.camTrack) return;
            this.camTorchEnabled = !this.camTorchEnabled;
            this.updateCameraSettings();
        },

        updateCameraSettings() {
            if (!this.camTrack || typeof this.camTrack.applyConstraints !== 'function') return;
            
            const caps = this.camTrack.getCapabilities();
            let advancedObj = {};
            
            if (caps.zoom && this.camZoom > this.minZoom) {
                advancedObj.zoom = parseFloat(this.camZoom);
            }
            if (caps.exposureCompensation && this.camExposure !== 0) {
                advancedObj.exposureCompensation = parseFloat(this.camExposure);
            }
            if (caps.focusMode && caps.focusMode.includes('continuous')) {
                advancedObj.focusMode = "continuous";
            }
            if (this.hasTorch) {
                advancedObj.torch = this.camTorchEnabled;
            }
            
            if (Object.keys(advancedObj).length > 0) {
                this.camTrack.applyConstraints({ advanced: [advancedObj] }).catch(e => {
                    console.warn("Hardware does not support this exact constraint combination.");
                });
            }
        },

        async submitScan(codeString) {
            if (!codeString || this.isProcessing || this.alertMsg) return;
            if (codeString === this.lastScannedCode) return;

            this.lastScannedCode = codeString;
            this.isProcessing = true;
            this.bookCondition = 'good';
            
            try {
                const res = await fetch('<?= BASE_URL ?>/api/circulation/process.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'scan_decode', qr_code: codeString })
                });
                const data = await res.json();
                
                if (!data.success) {
                    this.showToast(data.message, false);
                } else {
                    this.showToast(data.message, true);
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
            } catch (err) {
                this.showToast("Network error or invalid QR payload format.", false);
            } finally {
                setTimeout(() => { 
                    this.isProcessing = false; 
                    setTimeout(() => { this.lastScannedCode = ''; }, 3000);
                }, 800);
            }
        },

        async settleStudentDebt(settlementType, transactionId = 0) {
            if (!this.scannedStudent) return;
            const res = await fetch('<?= BASE_URL ?>/api/circulation/process.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'settle_student_debt',
                    student_id: this.scannedStudent.id,
                    settlement_type: settlementType,
                    transaction_id: transactionId
                })
            });
            const data = await res.json();
            this.showToast(data.message, data.success);
            if (data.success && data.student) {
                this.scannedStudent = data.student;
                await this.loadRecentActivity();
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
                this.lastScannedCode = ''; 
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
                    fine_status: this.returnFineStatus,
                    book_condition: this.bookCondition,
                    damaged_fee: this.damagedFineAmount
                })
            });
            const data = await res.json();
            this.showToast(data.message, data.success);
            if (data.success) {
                this.scannedBook = null;
                this.bookCondition = 'good';
                this.damagedFineAmount = 300;
                this.lastScannedCode = '';
                if (data.student && this.scannedStudent && this.scannedStudent.id == data.student.id) {
                    this.scannedStudent = data.student;
                }
                await this.loadRecentActivity();
            }
        }
    };
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>