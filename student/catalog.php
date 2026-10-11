<?php
// student/catalog.php
$page_title = 'OPAC & 4th Floor Library Map';
require_once __DIR__ . '/../includes/header.php';
require_role(['student', 'faculty', 'librarian', 'super_admin']);
require_once __DIR__ . '/../includes/sidebar.php';
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<div x-data="opacApp()" x-init="fetchCatalog()" class="space-y-6">

    <!-- Top OPAC Banner & Navigation Tabs -->
    <div class="bg-theme-card border border-theme-border rounded-2xl p-4 sm:p-6 shadow-xs">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-5 border-b border-theme-border">
            <div>
                <span class="px-2.5 py-1 text-[10px] font-extrabold uppercase rounded-full bg-theme-accent/15 text-theme-accent">
                    4th Floor Library Catalogue • 15 Shelves (SH00–SH14)
                </span>
                <h1 class="text-xl sm:text-2xl font-extrabold text-theme-text mt-2">
                    Academic Literature & Capstone Archives
                </h1>
                <p class="text-xs text-theme-muted mt-0.5">
                    Available literature is listed first; unavailable items are placed at the end of the catalogue.
                </p>
            </div>

            <div class="flex items-center gap-2.5 shrink-0">
                <button @click="showFloorMap = !showFloorMap"
                        :class="showFloorMap ? 'bg-amber-500 text-slate-950' : 'bg-theme-main text-theme-text border border-theme-border'"
                        class="px-4 py-2.5 rounded-xl text-xs font-extrabold transition flex items-center gap-2 shadow-xs">
                    <span>🗺️</span>
                    <span x-text="showFloorMap ? 'Hide 4F Floor Map' : 'Show 4F Floor Map'"></span>
                </button>
                <button @click="resetFilters()"
                        class="px-3.5 py-2.5 rounded-xl bg-theme-main hover:bg-theme-hover border border-theme-border text-xs font-bold text-theme-muted hover:text-theme-text transition">
                    ↺ Reset
                </button>
            </div>
        </div>

        <!-- Mode Tabs -->
        <div class="flex flex-wrap items-center gap-2 pt-4">
            <button @click="setTab('all')"
                    :class="activeTab === 'all' ? 'bg-theme-accent text-white shadow' : 'bg-theme-main text-theme-muted hover:text-theme-text'"
                    class="px-3.5 py-2 rounded-xl text-xs font-extrabold transition">
                All Literature
            </button>
            <button @click="setTab('learning_book')"
                    :class="activeTab === 'learning_book' ? 'bg-theme-accent text-white shadow' : 'bg-theme-main text-theme-muted hover:text-theme-text'"
                    class="px-3.5 py-2 rounded-xl text-xs font-extrabold transition">
                📚 Learning Books (13 Subjects)
            </button>
            <button @click="setTab('capstone_research')"
                    :class="activeTab === 'capstone_research' ? 'bg-theme-accent text-white shadow' : 'bg-theme-main text-theme-muted hover:text-theme-text'"
                    class="px-3.5 py-2 rounded-xl text-xs font-extrabold transition">
                🎓 Capstones & Researches (23 Programs)
            </button>
            <button @click="setTab('favorites')"
                    :class="activeTab === 'favorites' ? 'bg-amber-500 text-slate-950 shadow' : 'bg-theme-main text-theme-muted hover:text-theme-text'"
                    class="px-3.5 py-2 rounded-xl text-xs font-extrabold transition flex items-center gap-1">
                <span>★</span>
                <span>My Starred Favorites</span>
            </button>
            <button @click="advancedOpen = !advancedOpen"
                    :class="advancedOpen ? 'ring-2 ring-theme-accent bg-theme-accent/15 text-theme-accent' : 'bg-theme-main text-theme-muted hover:text-theme-text'"
                    class="ml-auto px-3.5 py-2 rounded-xl text-xs font-extrabold border border-theme-border transition flex items-center gap-1.5">
                <span>⚙️ Advanced Search & Exclude</span>
                <span x-text="advancedOpen ? '▴' : '▾'"></span>
            </button>
        </div>

        <!-- BASIC FILTER BAR -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 mt-4 pt-4 border-t border-theme-border">
            <div class="lg:col-span-2">
                <label class="block text-[10px] font-extrabold uppercase tracking-wider text-theme-muted mb-1">Search Title, Author, or ISBN/Code</label>
                <input type="text" x-model="filters.include_kw" @input.debounce.300ms="fetchCatalog()"
                       placeholder="e.g. Programming, Criminal Law, bASA..."
                       class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border text-xs text-theme-text focus:border-theme-accent outline-none">
            </div>

            <div>
                <label class="block text-[10px] font-extrabold uppercase tracking-wider text-theme-muted mb-1">Subject / BCP Program</label>
                <select x-model="filters.subject_or_program" @change="fetchCatalog()"
                        class="w-full px-3 py-2.5 rounded-xl bg-theme-main border border-theme-border text-xs text-theme-text outline-none">
                    <option value="all">All Subjects & Programs</option>
                    <optgroup label="📚 13 Learning Book Subjects (SH01–SH09)">
                        <?php foreach (BOOK_SUBJECTS as $code => $label): ?>
                            <option value="<?= e($code) ?>"><?= e($code) ?> — <?= e($label) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php foreach (BCP_PROGRAMS as $clusterCode =>$cluster): ?>
                        <optgroup label="🎓 <?= e($cluster['label']) ?>">
                            <?php foreach ($cluster['programs'] as $progCode =>$progName): ?>
                                <option value="<?= e($progCode) ?>"><?= e($progCode) ?> — <?= e($progName) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-[10px] font-extrabold uppercase tracking-wider text-theme-muted mb-1">Literature Age (Published)</label>
                <select x-model="filters.book_age" @change="fetchCatalog()"
                        class="w-full px-3 py-2.5 rounded-xl bg-theme-main border border-theme-border text-xs text-theme-text outline-none">
                    <option value="all">Any Publication Age</option>
                    <option value="new">Recent (0–2 Years Old)</option>
                    <option value="standard">Standard (3–5 Years Old)</option>
                    <option value="archival">Archival (Over 5 Years Old)</option>
                </select>
            </div>

            <div>
                <label class="block text-[10px] font-extrabold uppercase tracking-wider text-theme-muted mb-1">Website Upload & Date Sort</label>
                <select x-model="filters.sort_upload" @change="fetchCatalog()"
                        class="w-full px-3 py-2.5 rounded-xl bg-theme-main border border-theme-border text-xs text-theme-text outline-none">
                    <option value="newest">Newest Uploaded to Website</option>
                    <option value="oldest">Oldest Uploaded to Website</option>
                    <option value="pub_desc">Publication Year (Newest First)</option>
                    <option value="pub_asc">Publication Year (Oldest First)</option>
                </select>
            </div>
        </div>

        <!-- ADVANCED BOOLEAN SEARCH DRAWER -->
        <div x-show="advancedOpen" x-cloak x-transition
             class="mt-4 pt-4 border-t border-theme-border bg-theme-main/60 p-4 rounded-xl grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label class="block text-[10px] font-extrabold uppercase tracking-wider text-red-400 mb-1">Exclude Words (Boolean NOT)</label>
                <input type="text" x-model="filters.exclude_kw" @input.debounce.300ms="fetchCatalog()"
                       placeholder="Words to exclude..."
                       class="w-full px-3 py-2 rounded-xl bg-theme-card border border-red-500/40 text-xs text-theme-text outline-none">
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[10px] font-extrabold uppercase tracking-wider text-theme-muted mb-1">From Year</label>
                    <input type="number" x-model="filters.year_from" @input.debounce.300ms="fetchCatalog()" placeholder="2018"
                           class="w-full px-3 py-2 rounded-xl bg-theme-card border border-theme-border text-xs text-theme-text font-mono outline-none">
                </div>
                <div>
                    <label class="block text-[10px] font-extrabold uppercase tracking-wider text-theme-muted mb-1">To Year</label>
                    <input type="number" x-model="filters.year_to" @input.debounce.300ms="fetchCatalog()" placeholder="<?= date('Y') ?>"
                           class="w-full px-3 py-2 rounded-xl bg-theme-card border border-theme-border text-xs text-theme-text font-mono outline-none">
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-extrabold uppercase tracking-wider text-theme-muted mb-1">4th Floor Shelf Filter</label>
                <select x-model="filters.shelf_id" @change="fetchCatalog()"
                        class="w-full px-3 py-2 rounded-xl bg-theme-card border border-theme-border text-xs text-theme-text font-mono outline-none">
                    <option value="all">All Shelves (SH00–SH14)</option>
                    <?php for ($i = 0; $i <= 14; $i++): $sc = sprintf('SH%02d',$i); ?>
                        <option value="<?= $sc ?>"><?= $sc ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="flex items-end">
                <label class="flex items-center gap-2.5 p-2.5 rounded-xl bg-theme-card border border-theme-border w-full cursor-pointer">
                    <input type="checkbox" x-model="filters.available_only" @change="fetchCatalog()" class="w-4 h-4 accent-sky-500 rounded">
                    <span class="text-xs font-bold text-theme-text">Hide Unavailable Literature</span>
                </label>
            </div>
        </div>
    </div>

    <!-- COLLAPSIBLE INTERACTIVE 4TH FLOOR LIBRARY MAP -->
    <div x-show="showFloorMap" x-cloak x-transition class="bg-theme-card border border-theme-border rounded-2xl p-4 sm:p-5 shadow-xs">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3">
            <div>
                <h2 class="text-sm sm:text-base font-extrabold text-theme-text">Interactive 4th Floor Bestlink Library Map</h2>
                <p class="text-xs text-theme-muted">Click any shelf (<span class="font-mono font-bold text-theme-accent">SH00–SH14</span>) to filter books stored on that unit.</p>
            </div>
            <template x-if="filters.shelf_id !== 'all'">
                <button @click="filters.shelf_id = 'all'; fetchCatalog()"
                        class="px-3 py-1.5 rounded-lg bg-amber-500/20 text-amber-400 border border-amber-500/40 text-xs font-extrabold">
                    Showing Shelf: <span x-text="filters.shelf_id"></span> ✕
                </button>
            </template>
        </div>
        <?php include __DIR__ . '/../includes/map_svg.php'; ?>
    </div>

    <!-- CATALOGUE GRID (Available Items First, Unavailable Items at the End) -->
    <div x-show="loading" class="p-12 text-center text-theme-muted text-sm font-bold">
        Loading 4th Floor Library Catalogue...
    </div>

    <div x-show="!loading && items.length === 0" x-cloak
         class="bg-theme-card border border-theme-border rounded-2xl p-12 text-center">
        <p class="text-base font-extrabold text-theme-text">No books or capstones matched your filters.</p>
        <p class="text-xs text-theme-muted mt-1">Try clearing your search terms or selecting "All Shelves".</p>
    </div>

    <div x-show="!loading && items.length > 0" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <template x-for="item in items" :key="item.id">
            <div @click="openItemModal(item)"
                 :class="item.available_copies == 0 ? 'opacity-75 border-red-500/40 bg-theme-card/70' : 'border-theme-border hover:border-theme-accent bg-theme-card'"
                 class="border rounded-2xl p-5 flex flex-col justify-between cursor-pointer transition-all duration-150 hover:-translate-y-0.5 shadow-xs group">
                
                <div>
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase"
                                  :class="item.material_type === 'capstone_research' ? 'bg-indigo-500/20 text-indigo-400 border border-indigo-500/30' : 'bg-sky-500/20 text-sky-400 border border-sky-500/30'"
                                  x-text="item.material_type === 'capstone_research' ? '🎓 Capstone' : '📚 Book'"></span>
                            <span class="px-2 py-0.5 rounded bg-theme-main border border-theme-border text-[10px] font-extrabold font-mono text-theme-text"
                                  x-text="item.subject_or_program"></span>
                            <!-- Unavailable Tag -->
                            <span x-show="item.available_copies == 0"
                                  class="px-2 py-0.5 rounded bg-red-500 text-white text-[10px] font-extrabold uppercase">
                                UNAVAILABLE
                            </span>
                        </div>

                        <button @click.stop="toggleFavorite(item)"
                                class="p-1.5 rounded-lg bg-theme-main hover:bg-theme-hover border border-theme-border transition"
                                :class="item.is_favorited ? 'text-amber-400' : 'text-theme-muted'">
                            <span x-text="item.is_favorited ? '★' : '☆'"></span>
                            <span class="text-[11px] font-bold ml-0.5" x-text="item.total_stars"></span>
                        </button>
                    </div>

                    <h3 class="text-base font-extrabold text-theme-text group-hover:text-theme-accent transition line-clamp-2" x-text="item.title"></h3>
                    <p class="text-xs text-theme-muted mt-1 font-medium" x-text="item.authors"></p>
                    <p class="text-xs text-theme-muted/80 mt-2.5 line-clamp-2" x-text="item.abstract_or_summary || 'No summary provided.'"></p>
                </div>

                <div class="mt-4 pt-3.5 border-t border-theme-border flex items-center justify-between gap-2 text-xs">
                    <div class="flex items-center gap-2">
                        <span class="px-2.5 py-1 rounded-lg bg-theme-accent/15 text-theme-accent font-mono font-extrabold text-[11px]"
                              x-text="item.primary_code"></span>
                        <span class="text-[11px] text-theme-muted font-mono" x-text="item.publish_year"></span>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="text-[11px] text-amber-400 font-bold" x-show="item.review_count > 0">
                            ★ <span x-text="item.avg_rating"></span>
                        </span>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase"
                              :class="item.available_copies > 0 ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400 border border-red-500/30'"
                              x-text="item.available_copies > 0 ? (item.available_copies + '/' + item.total_copies + ' Available') : ('UNAVAILABLE (0/' + item.total_copies + ')')"></span>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- BOOK DETAIL, MAP LOCATOR, REVIEWS (WITH DELETE) & EXPRESS BORROW MODAL -->
    <div x-show="selectedItem" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 backdrop-blur-xs p-3 sm:p-5"
         x-transition>
        <div @click.away="closeModal()"
             class="w-full max-w-4xl max-h-[92dvh] bg-theme-card border border-theme-border rounded-2xl shadow-2xl overflow-hidden flex flex-col">

            <div class="p-4 sm:p-5 border-b border-theme-border flex items-start justify-between gap-4 bg-theme-main/50 shrink-0">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-theme-accent text-white"
                              x-text="selectedItem?.material_type === 'capstone_research' ? 'Capstone Research' : 'Academic Learning Book'"></span>
                        <span class="font-mono text-xs font-bold text-theme-accent" x-text="selectedItem?.subject_or_program"></span>
                        <span class="font-mono text-xs text-theme-muted" x-text="'• Published ' + selectedItem?.publish_year"></span>
                        <span x-show="selectedItem?.available_copies == 0"
                              class="px-2 py-0.5 rounded bg-red-500 text-white text-[10px] font-extrabold uppercase">
                            Currently Unavailable
                        </span>
                    </div>
                    <h2 class="text-lg sm:text-xl font-extrabold text-theme-text mt-1.5" x-text="selectedItem?.title"></h2>
                    <p class="text-xs text-theme-muted" x-text="'By ' + selectedItem?.authors"></p>
                </div>

                <button @click="closeModal()" class="w-8 h-8 rounded-lg bg-theme-hover text-theme-muted hover:text-theme-text font-bold shrink-0">&times;</button>
            </div>

            <div class="p-4 sm:p-6 overflow-y-auto space-y-6">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    <div class="lg:col-span-2 bg-theme-main p-4 rounded-xl border border-theme-border">
                        <h4 class="text-xs font-extrabold uppercase tracking-wider text-theme-muted mb-1.5">Abstract / Literature Overview</h4>
                        <p class="text-xs sm:text-sm text-theme-text leading-relaxed" x-text="selectedItem?.abstract_or_summary"></p>
                        <p class="text-[11px] font-mono text-theme-muted mt-3">
                            Catalog ID / ISBN: <strong class="text-theme-text" x-text="selectedItem?.isbn_or_doc_id"></strong>
                        </p>
                    </div>

                    <div class="bg-theme-main p-4 rounded-xl border border-theme-border flex flex-col justify-between">
                        <div>
                            <h4 class="text-xs font-extrabold uppercase tracking-wider text-theme-muted">4th Floor Shelf Location</h4>
                            <p class="text-2xl font-mono font-extrabold text-amber-400 mt-1" x-text="selectedItem?.primary_code"></p>
                            <p class="text-[11px] text-theme-muted mt-1">Format: <span class="font-mono">[Shelf]-[Side]-[Section]-[Level]</span></p>
                        </div>

                        <div class="mt-4 pt-3 border-t border-theme-border flex items-center justify-between">
                            <button @click="toggleFavorite(selectedItem)"
                                    class="px-3 py-2 rounded-xl border border-theme-border text-xs font-extrabold flex items-center gap-1.5 transition"
                                    :class="selectedItem?.is_favorited ? 'bg-amber-500/20 text-amber-400 border-amber-500/40' : 'bg-theme-card text-theme-text'">
                                <span x-text="selectedItem?.is_favorited ? '★ Starred' : '☆ Star'"></span>
                                (<span x-text="selectedItem?.total_stars"></span>)
                            </button>

                            <button @click="showModalMap = !showModalMap"
                                    class="px-3 py-2 rounded-xl bg-theme-accent/15 text-theme-accent text-xs font-extrabold">
                                <span x-text="showModalMap ? 'Hide Map' : '🗺️ Pin on Map'"></span>
                            </button>
                        </div>
                    </div>
                </div>

                <div x-show="showModalMap" x-transition>
                    <p class="text-xs font-bold text-amber-400 mb-2">
                        📍 Highlighted in Gold below: Shelf <span class="font-mono" x-text="selectedItem?.primary_shelf"></span>
                    </p>
                    <?php include __DIR__ . '/../includes/map_svg.php'; ?>
                </div>

                <!-- PHYSICAL COPIES & EXPRESS BORROW QR -->
                <div class="bg-theme-main border border-theme-border rounded-xl p-4">
                    <h4 class="text-sm font-extrabold text-theme-text mb-1">Physical Copies & Express Library Checkout</h4>
                    <p class="text-xs text-theme-muted mb-3">Click <strong>⚡ Borrow QR</strong> on an available copy to generate a 30-second checkout QR for the Help Desk.</p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <template x-for="acc in selectedItem?.accessions" :key="acc.id">
                            <div class="p-3.5 rounded-xl bg-theme-card border border-theme-border flex items-center justify-between gap-3">
                                <div>
                                    <p class="font-mono text-xs font-extrabold text-theme-text" x-text="acc.accession_no"></p>
                                    <p class="text-[11px] font-mono text-theme-accent mt-0.5" x-text="'Shelf: ' + acc.placement_code"></p>
                                </div>

                                <div class="flex items-center gap-2">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase"
                                          :class="acc.status === 'available' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400'"
                                          x-text="acc.status === 'available' ? 'AVAILABLE' : 'UNAVAILABLE (' + acc.status + ')'"></span>

                                    <button x-show="acc.status === 'available'"
                                            @click="generateExpressQr(acc.accession_no)"
                                            class="px-3 py-1.5 rounded-lg bg-theme-accent hover:bg-theme-accentHover text-white text-xs font-extrabold shadow transition">
                                        ⚡ Borrow QR
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div x-show="expressQrActive" x-cloak x-transition
                         class="mt-4 p-5 rounded-2xl bg-theme-card border-2 border-theme-accent text-center">
                        <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 text-[10px] font-extrabold uppercase">
                            IN-LIBRARY EXPRESS CHECKOUT TOKEN
                        </span>
                        <h5 class="text-base font-extrabold text-theme-text mt-1">
                            Present Phone + Book (<span class="font-mono text-theme-accent" x-text="expressAccession"></span>) at Help Desk
                        </h5>
                        <div class="inline-block p-4 bg-white rounded-2xl border-4 border-theme-accent shadow-lg my-3">
                            <div id="expressQrCanvas" class="w-44 h-44 flex items-center justify-center"></div>
                        </div>
                        <p class="text-xs font-mono font-bold text-theme-accent">Refreshes in <span x-text="expressSecondsLeft"></span>s</p>
                    </div>
                </div>

                <!-- STUDENT COMMENTS & STAR RATINGS (WITH REMOVE COMMENT BUTTON) -->
                <div class="bg-theme-main border border-theme-border rounded-xl p-4">
                    <h4 class="text-sm font-extrabold text-theme-text mb-3">Student & Faculty Reviews (<span x-text="reviews.length"></span>)</h4>

                    <form @submit.prevent="submitReview()" class="mb-5 space-y-3 bg-theme-card p-3.5 rounded-xl border border-theme-border">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-theme-muted">Rate this literature:</span>
                            <div class="flex items-center gap-1">
                                <template x-for="star in [1,2,3,4,5]" :key="star">
                                    <button type="button" @click="newReview.rating = star"
                                            class="text-lg transition"
                                            :class="star <= newReview.rating ? 'text-amber-400' : 'text-slate-600'">★</button>
                                </template>
                            </div>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-2">
                            <input type="text" x-model="newReview.comment" required
                                   placeholder="Share how this book or capstone helped your research..."
                                   class="flex-1 px-3.5 py-2 rounded-xl bg-theme-main border border-theme-border text-xs text-theme-text outline-none">
                            <button type="submit"
                                    class="px-4 py-2 rounded-xl bg-theme-accent text-white text-xs font-extrabold shrink-0">
                                Post Comment
                            </button>
                        </div>
                    </form>

                    <div class="space-y-2.5 max-h-60 overflow-y-auto">
                        <template x-if="reviews.length === 0">
                            <p class="text-xs text-theme-muted text-center py-4">No reviews yet. Be the first to review this literature!</p>
                        </template>
                        <template x-for="rev in reviews" :key="rev.id">
                            <div class="p-3 rounded-xl bg-theme-card border border-theme-border text-xs flex items-start justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2 mb-1">
                                        <strong class="text-theme-text" x-text="rev.full_name"></strong>
                                        <span class="font-mono text-[10px] text-theme-muted" x-text="'(' + (rev.program_code || rev.role) + ')'"></span>
                                        <span class="text-amber-400 font-bold" x-text="'★'.repeat(rev.rating)"></span>
                                    </div>
                                    <p class="text-theme-text" x-text="rev.comment"></p>
                                    <p class="text-[10px] text-theme-muted mt-1 font-mono" x-text="rev.created_at"></p>
                                </div>

                                <!-- Remove Comment Button (Visible to comment author or Librarian/Admin) -->
                                <button x-show="rev.can_delete"
                                        type="button"
                                        @click="deleteReview(rev.id)"
                                        class="px-2.5 py-1 rounded-lg bg-red-500/15 text-red-400 hover:bg-red-500 hover:text-white text-[10px] font-extrabold shrink-0 transition">
                                    Remove
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

            </div>
        </div>
    </div>

</div>

<script>
function opacApp() {
    return {
        loading: true,
        activeTab: 'all',
        advancedOpen: false,
        showFloorMap: false,
        showModalMap: true,
        items: [],
        selectedItem: null,
        reviews: [],
        newReview: { rating: 5, comment: '' },
        expressQrActive: false,
        expressAccession: '',
        expressSecondsLeft: 30,
        expressTimer: null,

        filters: {
            material_type: 'all',
            subject_or_program: 'all',
            book_age: 'all',
            sort_upload: 'newest',
            include_kw: '',
            exclude_kw: '',
            year_from: '',
            year_to: '',
            shelf_id: 'all',
            available_only: false,
            favorites_only: false
        },

        setTab(tab) {
            this.activeTab = tab;
            if (tab === 'favorites') {
                this.filters.favorites_only = true;
                this.filters.material_type = 'all';
            } else {
                this.filters.favorites_only = false;
                this.filters.material_type = tab;
            }
            this.fetchCatalog();
        },

        resetFilters() {
            this.activeTab = 'all';
            this.filters = {
                material_type: 'all',
                subject_or_program: 'all',
                book_age: 'all',
                sort_upload: 'newest',
                include_kw: '',
                exclude_kw: '',
                year_from: '',
                year_to: '',
                shelf_id: 'all',
                available_only: false,
                favorites_only: false
            };
            this.fetchCatalog();
        },

        isShelfHighlighted(shelfCode) {
            if (this.selectedItem && this.selectedItem.primary_shelf === shelfCode) return true;
            if (!this.selectedItem && this.filters.shelf_id === shelfCode) return true;
            return false;
        },

        selectShelfFromMap(shelfCode) {
            if (this.selectedItem) return;
            this.filters.shelf_id = (this.filters.shelf_id === shelfCode) ? 'all' : shelfCode;
            this.fetchCatalog();
        },

        async fetchCatalog() {
            this.loading = true;
            try {
                const res = await fetch('<?= BASE_URL ?>/api/catalog/search.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.filters)
                });
                const data = await res.json();
                if (data.success) this.items = data.items;
            } finally {
                this.loading = false;
            }
        },

        async toggleFavorite(item) {
            if (!item) return;
            const res = await fetch('<?= BASE_URL ?>/api/catalog/interact.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'toggle_favorite', catalog_id: item.id })
            });
            const data = await res.json();
            if (data.success) {
                item.is_favorited = data.is_favorited;
                item.total_stars  = data.total_stars;
            }
        },

        async openItemModal(item) {
            this.selectedItem = item;
            this.expressQrActive = false;
            clearInterval(this.expressTimer);
            await this.loadReviews(item.id);
        },

        closeModal() {
            this.selectedItem = null;
            this.expressQrActive = false;
            clearInterval(this.expressTimer);
        },

        async loadReviews(catalogId) {
            const res = await fetch(`<?= BASE_URL ?>/api/catalog/interact.php?action=get_reviews&catalog_id=${catalogId}`);
            const data = await res.json();
            if (data.success) this.reviews = data.reviews;
        },

        async submitReview() {
            if (!this.selectedItem || !this.newReview.comment.trim()) return;
            const res = await fetch('<?= BASE_URL ?>/api/catalog/interact.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'post_review',
                    catalog_id: this.selectedItem.id,
                    rating: this.newReview.rating,
                    comment: this.newReview.comment
                })
            });
            const data = await res.json();
            if (data.success) {
                this.newReview.comment = '';
                await this.loadReviews(this.selectedItem.id);
                this.fetchCatalog();
            }
        },

        async deleteReview(reviewId) {
            const res = await fetch('<?= BASE_URL ?>/api/catalog/interact.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete_review', review_id: reviewId })
            });
            const data = await res.json();
            if (data.success && this.selectedItem) {
                await this.loadReviews(this.selectedItem.id);
                this.fetchCatalog();
            }
        },

        async generateExpressQr(accessionNo) {
            this.expressAccession = accessionNo;
            this.expressQrActive = true;
            clearInterval(this.expressTimer);

            const fetchToken = async () => {
                const res = await fetch('<?= BASE_URL ?>/api/catalog/interact.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'generate_express_qr', accession_no: accessionNo })
                });
                const data = await res.json();
                if (data.success) {
                    this.expressSecondsLeft = data.seconds_left;
                    const container = document.getElementById('expressQrCanvas');
                    if (container) {
                        container.innerHTML = '';
                        new QRCode(container, {
                            text: data.qr_payload,
                            width: 176,
                            height: 176,
                            colorDark: "#0f172a",
                            colorLight: "#ffffff",
                            correctLevel: QRCode.CorrectLevel.M
                        });
                    }
                }
            };

            await fetchToken();
            this.expressTimer = setInterval(() => {
                this.expressSecondsLeft--;
                if (this.expressSecondsLeft <= 0) fetchToken();
            }, 1000);
        }
    };
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>