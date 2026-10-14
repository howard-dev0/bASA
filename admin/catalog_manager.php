<?php
// admin/catalog_manager.php
$page_title = 'Book & Capstone QR Cataloging';
require_once __DIR__ . '/../includes/header.php';
require_role(['librarian', 'super_admin']);
require_once __DIR__ . '/../includes/sidebar.php';
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<style>
    @media print {
        body * { visibility: hidden; }
        #printableStickerArea, #printableStickerArea * { visibility: visible; }
        #printableStickerArea { position: fixed; inset: 0; background: #ffffff !important; color: #000000 !important; padding: 16px; z-index: 9999; }
    }
</style>

<div x-data="catalogManagerApp()" x-init="loadItems()"
     @keydown.escape.window="alertMsg = ''; manageItem = null; showAddModal = false; showPrintModal = false"
     @keydown.enter.window="if (alertMsg) { $event.preventDefault(); $event.stopPropagation(); alertMsg = ''; }"
     class="space-y-6">

    <!-- Top Header Banner -->
    <div class="bg-theme-card border border-theme-border border-l-4 border-l-theme-accent rounded-2xl p-4 sm:p-6 flex flex-col lg:flex-row lg:items-center justify-between gap-4 shadow-xs">
        <div>
            <span class="px-2.5 py-1 text-[10px] font-extrabold uppercase rounded-full bg-theme-accent/15 text-theme-accent">
                4th Floor Shelf Taxonomy • SH00 to SH14
            </span>
            <h1 class="text-xl sm:text-2xl font-extrabold text-theme-text mt-2">
                Literature Cataloging, Availability & QR Stickers
            </h1>
            <p class="text-xs text-theme-muted mt-0.5">
                Register Books & Theses, print QR stickers, and manage unavailable, damaged, lost, or reserved copies.
            </p>
        </div>

        <div class="flex flex-wrap gap-2.5 shrink-0">
            <button @click="showAddModal = true"
                class="px-4 py-2.5 rounded-xl bg-theme-accent hover:bg-theme-accentHover text-white text-xs font-extrabold shadow transition">
                + Add New Book / Thesis
            </button>
            <button @click="openPrintAllStickers()"
                class="px-4 py-2.5 rounded-xl bg-theme-main hover:bg-theme-hover border border-theme-border text-xs font-extrabold text-theme-text transition">
                🖨️ Print QR Stickers (<span x-text="selectedStickers.length"></span>)
            </button>
        </div>
    </div>

    <!-- CENTERED SCREEN RESPONSE MODAL -->
    <div x-show="alertMsg" x-cloak
        class="fixed inset-0 z-[120] flex items-center justify-center bg-black/70 backdrop-blur-xs p-4"
        x-transition>
        <div @click.away="alertMsg = ''"
            :class="alertOk ? 'border-emerald-500' : 'border-[#D9232A]'"
            class="w-full max-w-md bg-theme-card border-2 rounded-2xl shadow-2xl p-6 text-center space-y-4">
            <div :class="alertOk ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/40' : 'bg-red-500/20 text-red-400 border-red-500/40'"
                class="w-14 h-14 rounded-2xl border mx-auto flex items-center justify-center text-2xl font-extrabold">
                <span x-text="alertOk ? '✓' : '⚠️'"></span>
            </div>
            <div>
                <h3 class="text-base font-extrabold" :class="alertOk ? 'text-emerald-400' : 'text-red-400'"
                    x-text="alertOk ? 'Action Successful' : 'Action Failed'"></h3>
                <p class="text-xs sm:text-sm text-theme-text font-semibold mt-1.5 leading-relaxed" x-text="alertMsg"></p>
            </div>
            <button type="button" x-ref="catAlertOkBtn" @click="alertMsg = ''"
                :class="alertOk ? 'bg-emerald-500 text-slate-950' : 'bg-[#D9232A] text-white'"
                class="w-full py-2.5 px-5 rounded-xl font-extrabold text-xs shadow-lg transition outline-none">
                OK
            </button>
        </div>
    </div>

    <!-- CATALOGUE INVENTORY & UNAVAILABLE BOOKS FILTER TABLE -->
    <div class="bg-theme-card border border-theme-border rounded-2xl overflow-hidden shadow-xs">
        <div class="p-4 sm:p-5 border-b border-theme-border space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-extrabold text-theme-text">Registered Literature & Physical Copy Availability</h2>
                    <p class="text-xs text-theme-muted">Click any accession badge to queue its QR sticker, or click <strong>⚙️ Manage Copies</strong> to repair/restore unavailable books.</p>
                </div>
                <input type="text" x-model="searchFilter" placeholder="Filter by title, code, status, or shelf..."
                    class="px-3.5 py-2 rounded-xl bg-theme-main border border-theme-border text-xs text-theme-text outline-none w-full sm:w-64">
            </div>

            <!-- Availability Filter Tabs -->
            <div class="flex flex-wrap items-center gap-2 pt-1">
                <button type="button" @click="availabilityFilter = 'all'"
                        :class="availabilityFilter === 'all' ? 'bg-theme-accent text-white shadow' : 'bg-theme-main text-theme-muted hover:text-theme-text border border-theme-border'"
                        class="px-3.5 py-2 rounded-xl text-xs font-extrabold transition">
                    All Literature (<span x-text="items.length"></span>)
                </button>
                <button type="button" @click="availabilityFilter = 'unavailable'"
                        :class="availabilityFilter === 'unavailable' ? 'bg-[#D9232A] text-white shadow' : 'bg-theme-main text-red-400 hover:bg-theme-hover border border-red-500/30'"
                        class="px-3.5 py-2 rounded-xl text-xs font-extrabold transition">
                    🚫 Unavailable / 0 Avail (<span x-text="items.filter(i => i.available_copies === 0).length"></span>)
                </button>
                <button type="button" @click="availabilityFilter = 'damaged_lost'"
                        :class="availabilityFilter === 'damaged_lost' ? 'bg-amber-500 text-slate-950 shadow' : 'bg-theme-main text-amber-400 hover:bg-theme-hover border border-amber-500/30'"
                        class="px-3.5 py-2 rounded-xl text-xs font-extrabold transition">
                    🛠️ Damaged / Lost Copies (<span x-text="items.filter(i => i.damaged_or_lost > 0).length"></span>)
                </button>
                <button type="button" @click="availabilityFilter = 'borrowed'"
                        :class="availabilityFilter === 'borrowed' ? 'bg-sky-500 text-white shadow' : 'bg-theme-main text-sky-400 hover:bg-theme-hover border border-sky-500/30'"
                        class="px-3.5 py-2 rounded-xl text-xs font-extrabold transition">
                    📖 Currently Borrowed (<span x-text="items.filter(i => i.borrowed_copies > 0).length"></span>)
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs min-w-[820px]">
                <thead>
                    <tr class="bg-theme-main text-theme-muted uppercase text-[10px] tracking-wider border-b border-theme-border">
                        <th class="py-3.5 px-5">Literature Details</th>
                        <th class="py-3.5 px-5">Type & Domain</th>
                        <th class="py-3.5 px-5">4F Shelf Code</th>
                        <th class="py-3.5 px-5">Physical QR Accessions (Click to Print)</th>
                        <th class="py-3.5 px-5 text-right">Manage & Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-theme-border">
                    <template x-if="filteredItems.length === 0">
                        <tr>
                            <td colspan="5" class="py-10 text-center text-theme-muted text-xs">
                                No literature items match the current filter.
                            </td>
                        </tr>
                    </template>
                    <template x-for="item in filteredItems" :key="item.id">
                        <tr class="hover:bg-theme-hover/40 transition"
                            :class="item.available_copies === 0 ? 'bg-red-500/5' : ''">
                            <td class="py-4 px-5">
                                <div class="flex items-center gap-2">
                                    <p class="font-extrabold text-theme-text text-sm" x-text="item.title"></p>
                                    <span x-show="item.available_copies === 0"
                                          class="px-2 py-0.5 rounded bg-red-500 text-white text-[9px] font-extrabold uppercase shrink-0">
                                        UNAVAILABLE
                                    </span>
                                </div>
                                <p class="text-theme-muted mt-0.5" x-text="item.authors + ' • ' + item.publish_year"></p>
                                <p class="font-mono text-[10px] text-theme-muted mt-0.5"
                                   x-text="'ID/ISBN: ' + item.isbn_or_doc_id + ' • ' + item.available_copies + '/' + item.total_copies + ' Available'"></p>
                            </td>
                            <td class="py-4 px-5">
                                <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase"
                                      :class="item.material_type === 'capstone_research' ? (['BSIT', 'BSCPE', 'BLIS'].includes(item.subject_or_program) ? 'bg-indigo-500/20 text-indigo-400' : 'bg-purple-500/20 text-purple-400') : 'bg-sky-500/20 text-sky-400'"
                                      x-text="item.material_type === 'capstone_research' ? (['BSIT', 'BSCPE', 'BLIS'].includes(item.subject_or_program) ? 'Capstone' : 'Thesis') : 'Book'"></span>
                                <p class="font-mono font-bold text-theme-text mt-1" x-text="item.subject_or_program"></p>
                            </td>
                            <td class="py-4 px-5">
                                <span class="inline-block whitespace-nowrap px-2.5 py-1 rounded-lg bg-amber-500/15 text-amber-400 border border-amber-500/30 font-mono font-extrabold"
                                    x-text="(item.accessions && item.accessions.length > 0) ? item.accessions[0].placement_code : 'UNASSIGNED'"></span>
                            </td>
                            <td class="py-4 px-5">
                                <div class="flex flex-wrap gap-1.5">
                                    <template x-for="acc in item.accessions" :key="acc.id">
                                        <button type="button"
                                            @click="toggleStickerQueue(item, acc)"
                                            :class="isQueued(acc.accession_no) ? 'ring-2 ring-theme-accent bg-theme-accent text-white' : 'bg-theme-main text-theme-text border border-theme-border'"
                                            class="px-2.5 py-1 rounded-lg font-mono text-[11px] font-bold transition flex items-center gap-1.5"
                                            :title="'Status: ' + acc.status.toUpperCase() + ' (Click to queue QR sticker)'">
                                            <span>🖨️</span>
                                            <span x-text="acc.accession_no"></span>
                                            <span class="px-1.5 py-0.2 rounded text-[9px] font-extrabold uppercase"
                                                  :class="{
                                                      'bg-emerald-500/20 text-emerald-400': acc.status === 'available',
                                                      'bg-amber-500/20 text-amber-400': acc.status === 'borrowed',
                                                      'bg-red-500/20 text-red-400': acc.status === 'damaged',
                                                      'bg-rose-500/20 text-rose-400': acc.status === 'lost',
                                                      'bg-indigo-500/20 text-indigo-400': acc.status === 'reserved'
                                                  }"
                                                  x-text="acc.status"></span>
                                        </button>
                                    </template>
                                </div>
                            </td>
                            <td class="py-4 px-5 text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    <button type="button" @click="openManageCopies(item)"
                                        class="px-3 py-1.5 rounded-xl bg-amber-500/15 hover:bg-amber-500 text-amber-400 hover:text-slate-950 border border-amber-500/30 text-xs font-extrabold transition">
                                        ⚙️ Manage Copies
                                    </button>
                                    <button type="button" @click="addExtraCopy(item.id)"
                                        class="px-3 py-1.5 rounded-xl bg-theme-main hover:bg-theme-hover border border-theme-border text-xs font-extrabold text-theme-accent">
                                        +1 Copy
                                    </button>
                                    <button type="button" @click="deleteItem(item.id)"
                                        class="px-2.5 py-1.5 rounded-xl bg-red-500/15 hover:bg-red-500 text-red-400 hover:text-white text-xs font-extrabold transition">
                                        🗑️
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- MODAL: MANAGE UNAVAILABLE / DAMAGED / LOST PHYSICAL COPIES -->
    <div x-show="manageItem" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 backdrop-blur-xs p-3 sm:p-5"
         x-transition>
        <div @click.away="manageItem = null"
             class="w-full max-w-3xl max-h-[90dvh] bg-theme-card border border-theme-border rounded-2xl shadow-2xl overflow-hidden flex flex-col">

            <div class="p-4 sm:p-5 border-b border-theme-border flex items-start justify-between gap-4 bg-theme-main/50">
                <div>
                    <span class="px-2.5 py-0.5 rounded-full bg-amber-500/20 text-amber-400 text-[10px] font-extrabold uppercase">
                        Physical Copy & Availability Manager
                    </span>
                    <h3 class="text-base sm:text-lg font-extrabold text-theme-text mt-1" x-text="manageItem?.title"></h3>
                    <p class="text-xs text-theme-muted" x-text="'By ' + manageItem?.authors + ' • ' + manageItem?.isbn_or_doc_id"></p>
                </div>
                <button type="button" @click="manageItem = null" class="w-8 h-8 rounded-lg bg-theme-hover text-theme-muted font-bold shrink-0">&times;</button>
            </div>

            <div class="p-4 sm:p-6 overflow-y-auto space-y-4 text-xs">
                <div class="p-3.5 rounded-xl bg-theme-main border border-theme-border flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <p class="font-extrabold text-theme-text">
                            Available Copies: <span class="text-emerald-400 font-mono" x-text="manageItem?.available_copies + ' / ' + manageItem?.total_copies"></span>
                        </p>
                        <p class="text-[11px] text-theme-muted">Repair damaged books, mark missing copies as lost, reserve copies at the desk, or relocate shelf coordinates.</p>
                    </div>
                    <div class="flex flex-wrap gap-2 shrink-0">
                        <button type="button"
                                x-show="manageItem?.accessions?.some(a => ['damaged', 'lost', 'reserved'].includes(a.status))"
                                @click="restoreAllUnavailable(manageItem.id)"
                                class="px-3.5 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold text-xs shadow transition">
                            ✓ Restore All Damaged/Lost to Available
                        </button>
                        <button type="button"
                                @click="addExtraCopy(manageItem.id)"
                                class="px-3.5 py-2 rounded-xl bg-theme-accent hover:bg-theme-accentHover text-white font-extrabold text-xs shadow transition">
                            + Add New Copy
                        </button>
                    </div>
                </div>

                <!-- Individual Physical Copies List -->
                <div class="space-y-3">
                    <template x-for="acc in manageItem?.accessions" :key="acc.id">
                        <div class="p-4 rounded-xl bg-theme-main border border-theme-border space-y-3">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-2.5 border-b border-theme-border">
                                <div class="flex items-center gap-2.5">
                                    <span class="font-mono text-sm font-extrabold text-theme-accent" x-text="acc.accession_no"></span>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase"
                                          :class="{
                                              'bg-emerald-500/20 text-emerald-400': acc.status === 'available',
                                              'bg-amber-500/20 text-amber-400': acc.status === 'borrowed',
                                              'bg-red-500/20 text-red-400': acc.status === 'damaged',
                                              'bg-rose-500/20 text-rose-400': acc.status === 'lost',
                                              'bg-indigo-500/20 text-indigo-400': acc.status === 'reserved'
                                          }"
                                          x-text="acc.status"></span>
                                </div>
                                <span class="font-mono text-xs font-bold text-amber-400" x-text="'Current Shelf: ' + acc.placement_code"></span>
                            </div>

                            <template x-if="acc.status === 'borrowed'">
                                <div class="p-3 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-400 text-[11px] flex items-center justify-between">
                                    <span>⚠️ This copy is currently checked out to a borrower. Process its return at the Webcam Circulation Desk.</span>
                                    <a href="<?= BASE_URL ?>/admin/circulation_desk.php" class="font-extrabold underline shrink-0 ml-2">Go to Desk →</a>
                                </div>
                            </template>

                            <template x-if="acc.status !== 'borrowed'">
                                <div class="grid grid-cols-2 sm:grid-cols-6 gap-2.5 items-end">
                                    <div class="col-span-2">
                                        <label class="block text-[10px] font-extrabold uppercase text-theme-muted mb-1">Availability Status</label>
                                        <select x-model="acc.status"
                                                class="w-full px-3 py-2 rounded-lg bg-theme-card border border-theme-border font-bold text-theme-text outline-none">
                                            <option value="available">✓ Available (On Shelf)</option>
                                            <option value="damaged">🛠️ Damaged (Under Repair)</option>
                                            <option value="lost">🚫 Lost (Missing Copy)</option>
                                            <option value="reserved">📌 Reserved (Held at Desk)</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-[10px] font-bold text-theme-muted mb-1">Shelf</label>
                                        <select x-model="acc.shelf_id" class="w-full px-2 py-2 rounded-lg bg-theme-card border border-theme-border font-mono text-theme-text">
                                            <?php for ($i = 0; $i <= 14; $i++): $sc = sprintf('SH%02d', $i); ?>
                                                <option value="<?= $sc ?>"><?= $sc ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-[10px] font-bold text-theme-muted mb-1">Side / Sec</label>
                                        <div class="grid grid-cols-2 gap-1">
                                            <select x-model="acc.shelf_side" class="px-1.5 py-2 rounded-lg bg-theme-card border border-theme-border font-mono text-theme-text">
                                                <option value="F">F</option>
                                                <option value="B">B</option>
                                            </select>
                                            <select x-model="acc.shelf_section" class="px-1.5 py-2 rounded-lg bg-theme-card border border-theme-border font-mono text-theme-text">
                                                <option value="S1">S1</option>
                                                <option value="S2">S2</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-[10px] font-bold text-theme-muted mb-1">Level</label>
                                        <select x-model="acc.shelf_level" class="w-full px-2 py-2 rounded-lg bg-theme-card border border-theme-border font-mono text-theme-text">
                                            <option value="L1">L1</option>
                                            <option value="L2">L2</option>
                                            <option value="L3">L3</option>
                                            <option value="L4">L4</option>
                                            <option value="L5">L5</option>
                                        </select>
                                    </div>

                                    <div class="flex items-center gap-1.5">
                                        <button type="button" @click="saveCopyStatus(acc)"
                                                class="flex-1 py-2 px-3 rounded-lg bg-theme-accent hover:bg-theme-accentHover text-white font-extrabold text-xs shadow transition">
                                            Save
                                        </button>
                                        <button type="button" @click="deleteSingleCopy(acc)"
                                                title="Delete this physical copy"
                                                class="py-2 px-2.5 rounded-lg bg-red-500/15 hover:bg-red-500 text-red-400 hover:text-white font-extrabold text-xs transition">
                                            🗑️
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL 1: ADD NEW BOOK OR CAPSTONE WITH SMART SHELF AUTO-ASSIGNER -->
    <div x-show="showAddModal" x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 backdrop-blur-xs p-3 sm:p-5"
        x-transition>
        <div @click.away="showAddModal = false"
            class="w-full max-w-2xl max-h-[92dvh] bg-theme-card border border-theme-border rounded-2xl shadow-2xl overflow-hidden flex flex-col">

            <div class="p-5 border-b border-theme-border flex items-center justify-between bg-theme-main/50">
                <div>
                    <h3 class="text-base font-extrabold text-theme-text">Catalog New Book, Thesis or Capstone</h3>
                    <p class="text-xs text-theme-muted">Automatically maps to the correct 4th Floor Shelf (`SH00`–`SH14`)</p>
                </div>
                <button @click="showAddModal = false" class="w-8 h-8 rounded-lg bg-theme-hover text-theme-muted font-bold">&times;</button>
            </div>

            <form @submit.prevent="submitNewItem()" class="p-5 overflow-y-auto space-y-4 text-xs">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-extrabold uppercase text-theme-muted mb-1">Material Type</label>
                        <select x-model="form.material_type" @change="onTypeChange()"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border font-bold text-theme-text">
                            <option value="learning_book">📚 Academic Learning Book (SH01–SH09)</option>
                            <option value="capstone_research">🎓 Thesis / Capstone Research (SH00, SH10–SH14)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-extrabold uppercase text-theme-muted mb-1">Subject Domain / BCP Program</label>

                        <select x-show="form.material_type === 'learning_book'"
                            x-model="form.subject_or_program" @change="autoAssignShelf()"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border font-bold text-theme-text">
                            <optgroup label="📚 13 Learning Book Domains (SH01–SH09)">
                                <?php foreach (BOOK_SUBJECTS as $code =>$label): ?>
                                    <option value="<?= e($code) ?>"><?= e($code) ?> — <?= e($label) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>

                        <select x-show="form.material_type === 'capstone_research'" x-cloak
                            x-model="form.subject_or_program" @change="autoAssignShelf()"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border font-bold text-theme-text">
                            <?php foreach (BCP_PROGRAMS as $cCode =>$cluster): ?>
                                <optgroup label="🎓 <?= e($cluster['label']) ?>">
                                    <?php foreach ($cluster['programs'] as $pCode =>$pName): ?>
                                        <option value="<?= e($pCode) ?>"><?= e($pCode) ?> — <?= e($pName) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block font-extrabold uppercase text-theme-muted mb-1">Title of Literature</label>
                        <input type="text" x-model="form.title" required placeholder="Full book, thesis or capstone title"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border text-theme-text">
                    </div>
                    <div>
                        <label class="block font-extrabold uppercase text-theme-muted mb-1">ISBN / Document Code</label>
                        <input type="text" x-model="form.isbn_or_doc_id" required placeholder="e.g. BCP-RES-2025"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border font-mono text-theme-text">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block font-extrabold uppercase text-theme-muted mb-1">Authors / Researchers</label>
                        <input type="text" x-model="form.authors" required placeholder="e.g. Robert C. Martin"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border text-theme-text">
                    </div>
                    <div>
                        <label class="block font-extrabold uppercase text-theme-muted mb-1">Publication Year</label>
                        <input type="number" x-model="form.publish_year" required min="1980" max="2030"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border font-mono text-theme-text">
                    </div>
                </div>

                <div class="p-4 rounded-xl bg-theme-main border border-theme-border space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="font-extrabold text-theme-text">4th Floor Shelf Placement Coordinates</span>
                        <span class="px-2.5 py-1 rounded bg-amber-500/20 text-amber-400 font-mono font-extrabold"
                            x-text="form.shelf_id + '-' + form.shelf_side + '-' + form.shelf_section + '-' + form.shelf_level"></span>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5">
                        <div>
                            <label class="block text-[10px] font-bold text-theme-muted mb-1">Shelf Unit</label>
                            <select x-model="form.shelf_id" class="w-full px-2.5 py-2 rounded-lg bg-theme-card border border-theme-border font-mono text-theme-text">
                                <?php for ($i = 0; $i <= 14; $i++): $sc = sprintf('SH\%02d',$i); ?>
                                    <option value="<?= $sc ?>"><?= $sc ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-theme-muted mb-1">Shelf Side</label>
                            <select x-model="form.shelf_side" class="w-full px-2.5 py-2 rounded-lg bg-theme-card border border-theme-border font-mono text-theme-text">
                                <option value="F">F (Front)</option>
                                <option value="B">B (Back)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-theme-muted mb-1">Section</label>
                            <select x-model="form.shelf_section" class="w-full px-2.5 py-2 rounded-lg bg-theme-card border border-theme-border font-mono text-theme-text">
                                <option value="S1">S1 (Left)</option>
                                <option value="S2">S2 (Right)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-theme-muted mb-1">Level (1–5)</label>
                            <select x-model="form.shelf_level" class="w-full px-2.5 py-2 rounded-lg bg-theme-card border border-theme-border font-mono text-theme-text">
                                <option value="L1">L1 (Top)</option>
                                <option value="L2">L2</option>
                                <option value="L3">L3 (Mid)</option>
                                <option value="L4">L4</option>
                                <option value="L5">L5 (Bottom)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-theme-muted mb-1">Copies</label>
                            <input type="number" x-model="form.copies_count" min="1" max="10"
                                class="w-full px-2.5 py-2 rounded-lg bg-theme-card border border-theme-border font-mono text-theme-text">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block font-extrabold uppercase text-theme-muted mb-1">Abstract / Summary</label>
                    <textarea x-model="form.abstract_or_summary" rows="3" placeholder="Brief summary or research abstract..."
                        class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border text-theme-text"></textarea>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showAddModal = false" class="px-4 py-2.5 rounded-xl bg-theme-main text-theme-muted font-bold">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-theme-accent text-white font-extrabold shadow">
                        Save & Generate QR Accession(s)
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2: PRINTABLE BOOK QR STICKER SHEET (With Invert Option) -->
    <div x-show="showPrintModal" x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 backdrop-blur-xs p-4"
        x-transition>
        <div @click.away="showPrintModal = false"
            class="w-full max-w-4xl max-h-[90dvh] bg-theme-card border border-theme-border rounded-2xl shadow-2xl overflow-hidden flex flex-col">
            <div class="p-4 sm:p-5 border-b border-theme-border flex items-center justify-between">
                <div>
                    <h3 class="text-base font-extrabold text-theme-text">Printable Physical Book QR Stickers</h3>
                    <p class="text-xs text-theme-muted">Contains compact Version-2 QR matrix + Accession ID + 4th Floor Shelf Placement Code</p>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="toggleInvert()" type="button"
                            :class="qrInverted ? 'bg-indigo-500 text-white' : 'bg-theme-main text-theme-muted border border-theme-border'"
                            class="px-3 py-2 rounded-xl text-xs font-extrabold shadow transition">
                        <span x-text="qrInverted ? '◐ Inverted Stickers' : '◑ Standard Stickers'"></span>
                    </button>

                    <button @click="window.print()" class="px-4 py-2 rounded-xl bg-theme-accent text-white text-xs font-extrabold shadow">
                        🖨️ Print Sticker Sheet
                    </button>
                    <button @click="showPrintModal = false" class="w-8 h-8 rounded-lg bg-theme-hover text-theme-muted font-bold">&times;</button>
                </div>
            </div>

            <div id="printableStickerArea" class="p-6 overflow-y-auto grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4"
                 :style="qrInverted ? 'background-color: #0f172a !important; color: #ffffff !important; color-adjust: exact; -webkit-print-color-adjust: exact;' : 'background-color: #ffffff !important; color: #000000 !important; color-adjust: exact; -webkit-print-color-adjust: exact;'">
                
                <template x-for="st in selectedStickers" :key="st.accession_no">
                    <div class="p-4 border-2 rounded-xl flex flex-col items-center text-center transition-colors duration-300"
                         :class="qrInverted ? 'border-slate-500 bg-[#0f172a]' : 'border-slate-900 bg-white'">
                        <p class="text-[10px] font-extrabold uppercase tracking-wider"
                           :class="qrInverted ? 'text-slate-400' : 'text-slate-600'">BCP 4TH FLOOR LIBRARY • bASA</p>
                        
                        <div :id="'sticker-qr-' + st.accession_no" class="my-2.5 p-2 rounded" :class="qrInverted ? 'bg-[#0f172a]' : 'bg-white'"></div>
                        
                        <p class="font-mono text-xs font-extrabold"
                           :class="qrInverted ? 'text-white' : 'text-slate-950'" x-text="st.accession_no"></p>
                        <p class="mt-1 px-2.5 py-0.5 rounded font-mono text-xs font-extrabold"
                           :class="qrInverted ? 'bg-slate-700 text-white' : 'bg-slate-900 text-white'"
                           x-text="'SHELF: ' + st.placement_code"></p>
                        <p class="text-[10px] font-bold mt-1 line-clamp-1"
                           :class="qrInverted ? 'text-slate-400' : 'text-slate-600'" x-text="st.title"></p>
                    </div>
                </template>
            </div>
        </div>
    </div>

</div>

<script>
    function catalogManagerApp() {
        const SHELF_MAP = {
            'GENED': { shelf: 'SH01', side: 'F', sec: 'S1' },
            'IT': { shelf: 'SH02', side: 'F', sec: 'S1' },
            'CPE': { shelf: 'SH02', side: 'B', sec: 'S1' },
            'BLIS': { shelf: 'SH03', side: 'F', sec: 'S1' },
            'PSYCH': { shelf: 'SH03', side: 'B', sec: 'S1' },
            'EDUC': { shelf: 'SH04', side: 'F', sec: 'S1' },
            'BA': { shelf: 'SH05', side: 'F', sec: 'S1' },
            'ACC': { shelf: 'SH06', side: 'F', sec: 'S1' },
            'ENTREP': { shelf: 'SH06', side: 'B', sec: 'S1' },
            'OA': { shelf: 'SH07', side: 'F', sec: 'S1' },
            'TM': { shelf: 'SH07', side: 'B', sec: 'S1' },
            'HM': { shelf: 'SH08', side: 'F', sec: 'S1' },
            'CRIM': { shelf: 'SH09', side: 'F', sec: 'S1' },
            'BSIT': { shelf: 'SH00', side: 'F', sec: 'S1' },
            'BSCPE': { shelf: 'SH00', side: 'F', sec: 'S2' },
            'BEED': { shelf: 'SH10', side: 'F', sec: 'S1' },
            'BTLED': { shelf: 'SH10', side: 'F', sec: 'S2' },
            'BPED': { shelf: 'SH10', side: 'F', sec: 'S2' },
            'BSED-ENG': { shelf: 'SH11', side: 'F', sec: 'S1' },
            'BSED-FIL': { shelf: 'SH11', side: 'F', sec: 'S1' },
            'BSED-VAL': { shelf: 'SH11', side: 'F', sec: 'S1' },
            'BSED-MATH': { shelf: 'SH11', side: 'F', sec: 'S2' },
            'BSED-SCI': { shelf: 'SH11', side: 'F', sec: 'S2' },
            'BSED-SOC': { shelf: 'SH11', side: 'F', sec: 'S2' },
            'BSAIS': { shelf: 'SH12', side: 'F', sec: 'S1' },
            'BSENTREP': { shelf: 'SH12', side: 'F', sec: 'S1' },
            'BSOA': { shelf: 'SH12', side: 'F', sec: 'S1' },
            'BSBA-FM': { shelf: 'SH12', side: 'F', sec: 'S2' },
            'BSBA-HRM': { shelf: 'SH12', side: 'F', sec: 'S2' },
            'BSBA-MM': { shelf: 'SH12', side: 'F', sec: 'S2' },
            'BSHM': { shelf: 'SH13', side: 'F', sec: 'S1' },
            'BSTM': { shelf: 'SH13', side: 'F', sec: 'S2' },
            'BSCRIM': { shelf: 'SH14', side: 'F', sec: 'S1' },
            'BSP': { shelf: 'SH14', side: 'F', sec: 'S2' }
        };

        return {
            items: [],
            searchFilter: '',
            availabilityFilter: 'all',
            showAddModal: false,
            showPrintModal: false,
            manageItem: null,
            selectedStickers: [],
            alertMsg: '',
            alertOk: true,
            qrInverted: false,

            form: {
                material_type: 'learning_book',
                subject_or_program: 'IT',
                isbn_or_doc_id: '',
                title: '',
                authors: '',
                publish_year: <?= date('Y') ?>,
                shelf_id: 'SH02',
                shelf_side: 'F',
                shelf_section: 'S1',
                shelf_level: 'L1',
                copies_count: 1,
                abstract_or_summary: ''
            },

            showAlert(msg, ok = true) {
                if (document.activeElement) document.activeElement.blur();
                this.alertMsg = msg;
                this.alertOk = ok;
                this.$nextTick(() => {
                    if (this.$refs.catAlertOkBtn) this.$refs.catAlertOkBtn.focus();
                });
            },

            get filteredItems() {
                const q = this.searchFilter.toLowerCase().trim();
                return this.items.filter(i => {
                    if (this.availabilityFilter === 'unavailable' && i.available_copies > 0) return false;
                    if (this.availabilityFilter === 'damaged_lost' && i.damaged_or_lost === 0) return false;
                    if (this.availabilityFilter === 'borrowed' && i.borrowed_copies === 0) return false;

                    if (!q) return true;
                    return (
                        i.title.toLowerCase().includes(q) ||
                        i.authors.toLowerCase().includes(q) ||
                        i.subject_or_program.toLowerCase().includes(q) ||
                        i.isbn_or_doc_id.toLowerCase().includes(q) ||
                        i.accessions.some(a =>
                            a.accession_no.toLowerCase().includes(q) ||
                            a.placement_code.toLowerCase().includes(q) ||
                            a.status.toLowerCase().includes(q)
                        )
                    );
                });
            },

            onTypeChange() {
                this.form.subject_or_program = (this.form.material_type === 'capstone_research') ? 'BSIT' : 'IT';
                this.autoAssignShelf();
            },

            autoAssignShelf() {
                if (this.form.material_type === 'capstone_research' && this.form.subject_or_program === 'BLIS') {
                    this.form.shelf_id = 'SH00';
                    this.form.shelf_side = 'F';
                    this.form.shelf_section = 'S2';
                    return;
                }
                if (this.form.material_type === 'capstone_research' && this.form.subject_or_program === 'CPE') {
                    this.form.shelf_id = 'SH10';
                    this.form.shelf_side = 'F';
                    this.form.shelf_section = 'S1';
                    return;
                }
                const mapped = SHELF_MAP[this.form.subject_or_program];
                if (mapped) {
                    this.form.shelf_id = mapped.shelf;
                    this.form.shelf_side = mapped.side;
                    this.form.shelf_section = mapped.sec;
                }
            },

            async loadItems() {
                const res = await fetch('<?= BASE_URL ?>/api/catalog/manage.php?action=list');
                const data = await res.json();
                if (data.success) {
                    this.items = data.items;
                    if (this.manageItem) {
                        this.manageItem = this.items.find(i => i.id == this.manageItem.id) || null;
                    }
                }
            },

            openManageCopies(item) {
                this.manageItem = JSON.parse(JSON.stringify(item));
            },

            async saveCopyStatus(acc) {
                const res = await fetch('<?= BASE_URL ?>/api/catalog/manage.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'update_copy_status',
                        accession_id: acc.id,
                        status: acc.status,
                        shelf_id: acc.shelf_id,
                        shelf_side: acc.shelf_side,
                        shelf_section: acc.shelf_section,
                        shelf_level: acc.shelf_level
                    })
                });
                const data = await res.json();
                this.showAlert(data.message, data.success);
                if (data.success) {
                    await this.loadItems();
                }
            },

            async restoreAllUnavailable(catalogId) {
                const res = await fetch('<?= BASE_URL ?>/api/catalog/manage.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'restore_unavailable_copies', catalog_id: catalogId })
                });
                const data = await res.json();
                this.showAlert(data.message, data.success);
                if (data.success) {
                    await this.loadItems();
                }
            },

            async deleteSingleCopy(acc) {
                if (!confirm(`Are you sure you want to permanently remove physical copy ${acc.accession_no}?`)) return;
                const res = await fetch('<?= BASE_URL ?>/api/catalog/manage.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete_copy', accession_id: acc.id })
                });
                const data = await res.json();
                this.showAlert(data.message, data.success);
                if (data.success) {
                    await this.loadItems();
                }
            },

            async submitNewItem() {
                const res = await fetch('<?= BASE_URL ?>/api/catalog/manage.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'create_item', ...this.form })
                });
                const data = await res.json();
                this.showAlert(data.message, data.success);
                if (data.success) {
                    this.showAddModal = false;
                    this.form.title = '';
                    this.form.authors = '';
                    this.form.isbn_or_doc_id = '';
                    this.form.abstract_or_summary = '';
                    await this.loadItems();
                }
            },

            async addExtraCopy(catalogId) {
                const res = await fetch('<?= BASE_URL ?>/api/catalog/manage.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'add_copy', catalog_id: catalogId })
                });
                const data = await res.json();
                this.showAlert(data.message, data.success);
                await this.loadItems();
            },

            async deleteItem(catalogId) {
                if (!confirm('Are you sure you want to delete this literature record and all of its copies?')) return;
                const res = await fetch('<?= BASE_URL ?>/api/catalog/manage.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete_item', catalog_id: catalogId })
                });
                const data = await res.json();
                this.showAlert(data.message, data.success);
                if (data.success) await this.loadItems();
            },

            isQueued(accNo) {
                return this.selectedStickers.some(s => s.accession_no === accNo);
            },

            toggleStickerQueue(item, acc) {
                if (this.isQueued(acc.accession_no)) {
                    this.selectedStickers = this.selectedStickers.filter(s => s.accession_no !== acc.accession_no);
                } else {
                    this.selectedStickers.push({
                        accession_no: acc.accession_no,
                        placement_code: acc.placement_code,
                        title: item.title
                    });
                }
            },

            toggleInvert() {
                this.qrInverted = !this.qrInverted;
                this.renderPrintStickers();
            },

            renderPrintStickers() {
                setTimeout(() => {
                    this.selectedStickers.forEach(st => {
                        const el = document.getElementById('sticker-qr-' + st.accession_no);
                        if (el) {
                            el.innerHTML = '';
                            new QRCode(el, {
                                text: st.accession_no,
                                width: 112,
                                height: 112,
                                colorDark: this.qrInverted ? "#ffffff" : "#000000",
                                colorLight: this.qrInverted ? "#0f172a" : "#ffffff",
                                correctLevel: QRCode.CorrectLevel.M
                            });
                        }
                    });
                }, 100);
            },

            openPrintAllStickers() {
                if (this.selectedStickers.length === 0) {
                    this.items.forEach(item => {
                        item.accessions.forEach(acc => {
                            this.selectedStickers.push({
                                accession_no: acc.accession_no,
                                placement_code: acc.placement_code,
                                title: item.title
                            });
                        });
                    });
                }
                this.showPrintModal = true;
                this.renderPrintStickers();
            }
        };
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>