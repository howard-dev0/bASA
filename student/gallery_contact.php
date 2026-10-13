<?php
// student/gallery_contact.php
$page_title = '3F & 4F Library Gallery, Reach Us & Suggestion Box';
require_once __DIR__ . '/../includes/header.php';
require_role(['student', 'faculty', 'librarian', 'super_admin']);
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div x-data="communityHubApp()" x-init="loadHub()" @keydown.escape.window="toastMsg = ''; previewSlot = null; editingSlot = null" class="space-y-6">

    <!-- CENTERED SCREEN RESPONSE MODAL -->
    <div x-show="toastMsg" x-cloak
         class="fixed inset-0 z-[100] flex items-center justify-center bg-black/70 backdrop-blur-xs p-4"
         x-transition>
        <div @click.away="toastMsg = ''"
             :class="toastOk ? 'border-emerald-500' : 'border-[#D9232A]'"
             class="w-full max-w-md bg-theme-card border-2 rounded-2xl shadow-2xl p-6 text-center space-y-4">
            <div :class="toastOk ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/40' : 'bg-red-500/20 text-red-400 border-red-500/40'"
                 class="w-14 h-14 rounded-2xl border mx-auto flex items-center justify-center text-2xl font-extrabold">
                <span x-text="toastOk ? '✓' : '⚠️'"></span>
            </div>
            <div>
                <h3 class="text-base font-extrabold" :class="toastOk ? 'text-emerald-400' : 'text-red-400'"
                    x-text="toastOk ? 'Success' : 'Notice'"></h3>
                <p class="text-xs sm:text-sm text-theme-text font-semibold mt-1.5 leading-relaxed" x-text="toastMsg"></p>
            </div>
            <button type="button" @click="toastMsg = ''"
                    :class="toastOk ? 'bg-emerald-500 text-slate-950' : 'bg-[#D9232A] text-white'"
                    class="w-full py-2.5 px-5 rounded-xl font-extrabold text-xs shadow-lg transition">
                OK
            </button>
        </div>
    </div>

    <!-- TOP BANNER & FLOOR / SECTION TABS -->
    <div class="bg-theme-card border border-theme-border border-l-4 border-l-theme-accent rounded-2xl p-5 sm:p-6 shadow-xs">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-5 border-b border-theme-border">
            <div>
                <span class="px-2.5 py-1 text-[10px] font-extrabold uppercase rounded-full bg-theme-accent text-white">
                    BCP Library Showcase & Community Hub
                </span>
                <h1 class="text-xl sm:text-2xl font-extrabold text-theme-text mt-2">
                    4th & 3rd Floor Library Gallery, Reach Us & Suggestion Box
                </h1>
                <p class="text-xs text-theme-muted mt-0.5">
                    Explore <strong>16 pictures of the 4th Floor Library</strong> and <strong>6 pictures of the 3rd Floor Library</strong>, reach out to our staff, or drop an idea in the Suggestion Box.
                </p>
            </div>

            <template x-if="isStaff">
                <span class="px-3.5 py-2 rounded-xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/40 text-xs font-extrabold shrink-0">
                    ✏️ Librarian Edit Mode Active
                </span>
            </template>
        </div>

        <!-- Navigation Pills -->
        <div class="flex flex-wrap items-center gap-2 pt-4">
            <button type="button" @click="activeFloor = '4F'"
                    :class="activeFloor === '4F' ? 'bg-theme-accent text-white shadow' : 'bg-theme-main text-theme-muted hover:text-theme-text border border-theme-border'"
                    class="px-4 py-2.5 rounded-xl text-xs font-extrabold transition flex items-center gap-2">
                <span>🏛️ 4th Floor Library Gallery (16 Pictures)</span>
            </button>

            <button type="button" @click="activeFloor = '3F'"
                    :class="activeFloor === '3F' ? 'bg-theme-accent text-white shadow' : 'bg-theme-main text-theme-muted hover:text-theme-text border border-theme-border'"
                    class="px-4 py-2.5 rounded-xl text-xs font-extrabold transition flex items-center gap-2">
                <span>📖 3rd Floor Library Gallery (6 Pictures)</span>
            </button>

            <a href="#keepInTouchSection"
               class="ml-auto px-4 py-2.5 rounded-xl bg-theme-main hover:bg-theme-hover border border-theme-border text-xs font-extrabold text-theme-text transition">
                📞 Keep in Touch & Suggestion Box ↓
            </a>
        </div>
    </div>

    <!-- FLOOR GALLERY GRID (16 PICTURES FOR 4F / 6 PICTURES FOR 3F) -->
    <div class="space-y-4">
        <div class="flex items-center justify-between px-1">
            <div>
                <h2 class="text-base sm:text-lg font-extrabold text-theme-text"
                    x-text="activeFloor === '4F' ? '4th Floor Library & Archives — 16 Facility & Shelf Zones' : '3rd Floor Library — 6 Facility & Reading Zones'"></h2>
                <p class="text-xs text-theme-muted"
                   x-text="isStaff ? 'Click ✏️ Edit on any card to upload a new photo or update its title and caption.' : 'Click any picture card to view full-screen details.'"></p>
            </div>
            <span class="px-3 py-1 rounded-full bg-theme-card border border-theme-border text-xs font-mono font-extrabold text-theme-accent"
                  x-text="activeFloor === '4F' ? '16 / 16 Slots' : '6 / 6 Slots'"></span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <template x-for="slot in (activeFloor === '4F' ? gallery4F : gallery3F)" :key="slot.id">
                <div class="bg-theme-card border border-theme-border hover:border-theme-accent rounded-2xl overflow-hidden flex flex-col justify-between shadow-xs transition group">
                    
                    <!-- Picture / Architectural Visual Preview -->
                    <div @click="previewSlot = slot" class="relative h-44 bg-slate-900 overflow-hidden cursor-pointer">
                        <template x-if="slot.image_path">
                            <img :src="'<?= BASE_URL ?>/' + slot.image_path"
                                 :alt="slot.title"
                                 class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                        </template>

                        <!-- Crisp Architectural Vector Placeholder when no custom photo is uploaded yet -->
                        <template x-if="!slot.image_path">
                            <div class="w-full h-full flex flex-col items-center justify-center p-4 text-center relative"
                                 :class="slot.floor_level === '4F' ? 'bg-gradient-to-br from-[#130f40] via-[#1e293b] to-[#0089d0]' : 'bg-gradient-to-br from-[#0f172a] via-[#1e1b4b] to-[#059669]'">
                                <span class="px-2.5 py-0.5 rounded-full bg-black/40 text-white font-mono text-[10px] font-extrabold uppercase mb-2"
                                      x-text="slot.floor_level + ' • PICTURE #' + slot.slot_number"></span>
                                <svg class="w-10 h-10 text-white/80 mb-1.5 group-hover:scale-110 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <p class="text-xs font-extrabold text-white line-clamp-1" x-text="slot.zone_tag"></p>
                            </div>
                        </template>

                        <!-- Top-Left Slot Badge -->
                        <span class="absolute top-2.5 left-2.5 px-2.5 py-1 rounded-lg bg-black/70 text-white font-mono text-[10px] font-extrabold"
                              x-text="slot.floor_level + '-' + String(slot.slot_number).padStart(2, '0')"></span>

                        <!-- Librarian Edit Icon Button -->
                        <template x-if="isStaff">
                            <button type="button"
                                    @click.stop="openEditSlot(slot)"
                                    class="absolute top-2.5 right-2.5 px-2.5 py-1 rounded-lg bg-theme-accent hover:bg-theme-accentHover text-white text-[11px] font-extrabold shadow-lg flex items-center gap-1 transition">
                                <span>✏️ Edit</span>
                            </button>
                        </template>
                    </div>

                    <!-- Card Caption Body -->
                    <div class="p-4 flex-1 flex flex-col justify-between">
                        <div>
                            <span class="text-[10px] font-mono font-extrabold uppercase text-theme-accent" x-text="slot.zone_tag"></span>
                            <h3 class="text-sm font-extrabold text-theme-text mt-0.5 line-clamp-1" x-text="slot.title"></h3>
                            <p class="text-xs text-theme-muted mt-1 line-clamp-2 leading-relaxed" x-text="slot.caption"></p>
                        </div>

                        <div class="mt-3 pt-2.5 border-t border-theme-border flex items-center justify-between text-[11px]">
                            <button type="button" @click="previewSlot = slot" class="font-bold text-theme-accent hover:underline">
                                🔍 View Picture
                            </button>
                            <template x-if="isStaff">
                                <button type="button" @click="openEditSlot(slot)" class="font-bold text-emerald-400 hover:underline">
                                    📷 Upload Photo
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- KEEP IN TOUCH (REACH US) + SUGGESTION BOX SECTION -->
    <div id="keepInTouchSection" class="grid grid-cols-1 lg:grid-cols-12 gap-6 pt-4">

        <!-- LEFT 5 COLUMNS: KEEP IN TOUCH / REACH US CARD -->
        <div class="lg:col-span-5 bg-theme-card border border-theme-border rounded-2xl p-5 sm:p-6 shadow-xs h-fit space-y-5">
            <div class="flex items-center justify-between pb-3 border-b border-theme-border">
                <div>
                    <span class="px-2.5 py-0.5 rounded-full bg-theme-accent text-white text-[10px] font-extrabold uppercase">
                        Keep in Touch
                    </span>
                    <h2 class="text-lg font-extrabold text-theme-text mt-1">Reach Us at BCP Library</h2>
                    <p class="text-xs text-theme-muted">We're here to help with your research & archive needs</p>
                </div>

                <!-- Librarian Edit Button for Keep in Touch Info -->
                <template x-if="isStaff">
                    <button type="button" @click="editingContact = !editingContact"
                            class="px-3 py-1.5 rounded-xl bg-theme-main hover:bg-theme-hover border border-theme-border text-xs font-extrabold text-theme-accent transition">
                        <span x-text="editingContact ? 'Cancel' : '✏️ Edit Info'"></span>
                    </button>
                </template>
            </div>

            <!-- View Mode for Keep in Touch -->
            <div x-show="!editingContact" class="space-y-3.5 text-xs">
                <div class="p-3.5 rounded-xl bg-theme-main border border-theme-border flex items-start gap-3.5">
                    <span class="text-lg">📍</span>
                    <div>
                        <p class="text-[10px] font-extrabold uppercase tracking-wider text-theme-muted">Library Location</p>
                        <p class="font-bold text-theme-text mt-0.5" x-text="contact.contact_location"></p>
                    </div>
                </div>

                <div class="p-3.5 rounded-xl bg-theme-main border border-theme-border flex items-start gap-3.5">
                    <span class="text-lg">🕒</span>
                    <div>
                        <p class="text-[10px] font-extrabold uppercase tracking-wider text-theme-muted">Operating Hours</p>
                        <p class="font-bold text-theme-text mt-0.5" x-text="contact.contact_hours"></p>
                    </div>
                </div>

                <div class="p-3.5 rounded-xl bg-theme-main border border-theme-border flex items-start gap-3.5">
                    <span class="text-lg">📧</span>
                    <div>
                        <p class="text-[10px] font-extrabold uppercase tracking-wider text-theme-muted">Official Email</p>
                        <a :href="'mailto:' + contact.contact_email" class="font-mono font-bold text-theme-accent mt-0.5 inline-block hover:underline" x-text="contact.contact_email"></a>
                    </div>
                </div>

                <div class="p-3.5 rounded-xl bg-theme-main border border-theme-border flex items-start gap-3.5">
                    <span class="text-lg">📞</span>
                    <div>
                        <p class="text-[10px] font-extrabold uppercase tracking-wider text-theme-muted">Help Desk Hotline</p>
                        <a :href="'tel:' + contact.contact_phone" class="font-mono font-bold text-theme-text mt-0.5 inline-block hover:underline" x-text="contact.contact_phone"></a>
                    </div>
                </div>

                <div class="p-3.5 rounded-xl bg-theme-main border border-theme-border flex items-start gap-3.5">
                    <span class="text-lg">🌐</span>
                    <div>
                        <p class="text-[10px] font-extrabold uppercase tracking-wider text-theme-muted">Social / Facebook Page</p>
                        <a :href="(contact.contact_facebook || '').startsWith('http') ? contact.contact_facebook : 'https://' + contact.contact_facebook" target="_blank" class="font-bold text-theme-accent mt-0.5 inline-block hover:underline" x-text="contact.contact_facebook"></a>
                    </div>
                </div>
            </div>

            <!-- Librarian Edit Mode for Keep in Touch -->
            <form x-show="editingContact" x-cloak @submit.prevent="saveContactInfo()" class="space-y-3 text-xs">
                <div>
                    <label class="block font-extrabold uppercase text-theme-muted mb-1">Library Location</label>
                    <input type="text" x-model="contact.contact_location" required
                           class="w-full px-3.5 py-2 rounded-xl bg-theme-main border border-theme-border text-theme-text">
                </div>
                <div>
                    <label class="block font-extrabold uppercase text-theme-muted mb-1">Operating Hours</label>
                    <input type="text" x-model="contact.contact_hours" required
                           class="w-full px-3.5 py-2 rounded-xl bg-theme-main border border-theme-border text-theme-text">
                </div>
                <div>
                    <label class="block font-extrabold uppercase text-theme-muted mb-1">Official Email</label>
                    <input type="text" x-model="contact.contact_email" required
                           class="w-full px-3.5 py-2 rounded-xl bg-theme-main border border-theme-border font-mono text-theme-text">
                </div>
                <div>
                    <label class="block font-extrabold uppercase text-theme-muted mb-1">Phone / Hotline</label>
                    <input type="text" x-model="contact.contact_phone" required
                           class="w-full px-3.5 py-2 rounded-xl bg-theme-main border border-theme-border font-mono text-theme-text">
                </div>
                <div>
                    <label class="block font-extrabold uppercase text-theme-muted mb-1">Facebook / Social Link</label>
                    <input type="text" x-model="contact.contact_facebook" required
                           class="w-full px-3.5 py-2 rounded-xl bg-theme-main border border-theme-border text-theme-text">
                </div>
                <button type="submit" class="w-full py-2.5 rounded-xl bg-theme-accent text-white font-extrabold shadow">
                    ✓ Save Reach Us Details
                </button>
            </form>
        </div>

        <!-- RIGHT 7 COLUMNS: INTERACTIVE DIGITAL SUGGESTION BOX -->
        <div class="lg:col-span-7 bg-theme-card border border-theme-border rounded-2xl p-5 sm:p-6 shadow-xs space-y-5">
            <div class="pb-3 border-b border-theme-border flex items-center justify-between">
                <div>
                    <span class="px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 text-[10px] font-extrabold uppercase">
                        Digital Suggestion Box
                    </span>
                    <h2 class="text-lg font-extrabold text-theme-text mt-1">Share Your Ideas & Book Requests</h2>
                    <p class="text-xs text-theme-muted">Suggest new books, discussion room improvements, or archive features</p>
                </div>
            </div>

            <!-- Submit Suggestion Form -->
            <form @submit.prevent="submitSuggestion()" class="p-4 rounded-xl bg-theme-main border border-theme-border space-y-3 text-xs">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block font-extrabold uppercase text-theme-muted mb-1">Category</label>
                        <select x-model="suggestionForm.category"
                                class="w-full px-3 py-2.5 rounded-xl bg-theme-card border border-theme-border font-bold text-theme-text outline-none">
                            <option value="Book / Capstone Request">📚 Book / Capstone Request</option>
                            <option value="4F / 3F Facility & Rooms">🏛️ 4F / 3F Facility & Rooms</option>
                            <option value="bASA System Feedback">💻 bASA System Feedback</option>
                            <option value="General Suggestion">💡 General Suggestion</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block font-extrabold uppercase text-theme-muted mb-1">Subject / Title</label>
                        <input type="text" x-model="suggestionForm.subject" required
                               placeholder="e.g. Requesting additional Python & AI books on Shelf SH02"
                               class="w-full px-3.5 py-2.5 rounded-xl bg-theme-card border border-theme-border text-theme-text outline-none focus:border-theme-accent">
                    </div>
                </div>

                <div>
                    <label class="block font-extrabold uppercase text-theme-muted mb-1">Your Suggestion / Feedback</label>
                    <textarea x-model="suggestionForm.message" rows="3" required
                              placeholder="Write your suggestion for the Bestlink Library team..."
                              class="w-full px-3.5 py-2.5 rounded-xl bg-theme-card border border-theme-border text-theme-text outline-none focus:border-theme-accent"></textarea>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-1">
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" x-model="suggestionForm.is_anonymous" class="w-4 h-4 accent-sky-500 rounded">
                        <span class="text-xs font-bold text-theme-muted">Hide my name from other students (Post Anonymously)</span>
                    </label>

                    <button type="submit"
                            class="px-5 py-2.5 rounded-xl bg-theme-accent hover:bg-theme-accentHover text-white font-extrabold text-xs shadow-lg transition shrink-0">
                        📥 Drop in Suggestion Box
                    </button>
                </div>
            </form>

            <!-- Suggestion Feed & Librarian Responses -->
            <div class="space-y-3">
                <h3 class="text-xs font-extrabold uppercase tracking-wider text-theme-muted">
                    Recent Suggestions & Librarian Updates (<span x-text="suggestions.length"></span>)
                </h3>

                <template x-if="suggestions.length === 0">
                    <div class="p-8 text-center text-xs text-theme-muted border border-dashed border-theme-border rounded-xl">
                        No suggestions submitted yet. Be the first to share an idea!
                    </div>
                </template>

                <div class="space-y-3 max-h-96 overflow-y-auto pr-1">
                    <template x-for="sg in suggestions" :key="sg.id">
                        <div class="p-4 rounded-xl bg-theme-main border border-theme-border space-y-2 text-xs">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span class="px-2.5 py-0.5 rounded bg-theme-card border border-theme-border font-extrabold text-theme-accent"
                                          x-text="sg.category"></span>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase"
                                          :class="{
                                              'bg-amber-500/20 text-amber-400': sg.status === 'pending',
                                              'bg-sky-500/20 text-sky-400': sg.status === 'reviewed',
                                              'bg-emerald-500/20 text-emerald-400': sg.status === 'implemented'
                                          }"
                                          x-text="sg.status"></span>
                                </div>

                                <div class="flex items-center gap-2">
                                    <span class="text-[11px] font-mono text-theme-muted" x-text="sg.created_at"></span>
                                    <button x-show="sg.is_mine || isStaff"
                                            type="button" @click="deleteSuggestion(sg.id)"
                                            class="px-2 py-0.5 rounded bg-red-500/15 text-red-400 hover:bg-red-500 hover:text-white text-[10px] font-extrabold transition">
                                        Remove
                                    </button>
                                </div>
                            </div>

                            <h4 class="font-extrabold text-theme-text text-sm" x-text="sg.subject"></h4>
                            <p class="text-theme-text leading-relaxed" x-text="sg.message"></p>
                            <p class="text-[11px] text-theme-muted">Submitted by: <strong class="text-theme-text" x-text="sg.author_label"></strong></p>

                            <!-- Official Librarian Reply Display -->
                            <template x-if="sg.librarian_reply">
                                <div class="mt-2 p-3 rounded-lg bg-theme-card border-l-4 border-l-emerald-500 text-xs">
                                    <p class="text-[10px] font-extrabold uppercase text-emerald-400">Librarian Response:</p>
                                    <p class="text-theme-text mt-0.5" x-text="sg.librarian_reply"></p>
                                </div>
                            </template>

                            <!-- Librarian Moderation Controls -->
                            <template x-if="isStaff">
                                <div class="pt-2 mt-2 border-t border-theme-border flex flex-col sm:flex-row gap-2">
                                    <select x-model="sg.status"
                                            class="px-2.5 py-1.5 rounded-lg bg-theme-card border border-theme-border text-[11px] font-bold text-theme-text">
                                        <option value="pending">Pending</option>
                                        <option value="reviewed">Reviewed</option>
                                        <option value="implemented">Implemented</option>
                                    </select>
                                    <input type="text" x-model="sg.librarian_reply"
                                           placeholder="Write official librarian reply..."
                                           class="flex-1 px-3 py-1.5 rounded-lg bg-theme-card border border-theme-border text-xs text-theme-text outline-none">
                                    <button type="button" @click="saveSuggestionReply(sg)"
                                            class="px-3.5 py-1.5 rounded-lg bg-emerald-500 text-slate-950 font-extrabold text-xs shrink-0">
                                        Save Reply
                                    </button>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>

    </div>

    <!-- MODAL 1: FULL-SCREEN PICTURE LIGHTBOX PREVIEW -->
    <div x-show="previewSlot" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-xs p-4"
         x-transition>
        <div @click.away="previewSlot = null"
             class="w-full max-w-2xl bg-theme-card border border-theme-border rounded-2xl shadow-2xl overflow-hidden">
            <div class="p-4 border-b border-theme-border flex items-center justify-between">
                <div>
                    <span class="px-2 py-0.5 rounded bg-theme-accent text-white font-mono text-[10px] font-extrabold"
                          x-text="previewSlot?.floor_level + ' PICTURE #' + previewSlot?.slot_number"></span>
                    <span class="text-xs font-mono text-theme-accent ml-2" x-text="previewSlot?.zone_tag"></span>
                </div>
                <button @click="previewSlot = null" class="w-8 h-8 rounded-lg bg-theme-hover text-theme-muted font-bold">&times;</button>
            </div>

            <div class="bg-slate-950 flex items-center justify-center min-h-[280px] max-h-[60dvh] overflow-hidden">
                <template x-if="previewSlot?.image_path">
                    <img :src="'<?= BASE_URL ?>/' + previewSlot.image_path" class="max-h-[60dvh] w-auto object-contain">
                </template>
                <template x-if="!previewSlot?.image_path">
                    <div class="p-12 text-center text-white space-y-2">
                        <p class="text-lg font-extrabold" x-text="previewSlot?.title"></p>
                        <p class="text-xs text-slate-400" x-text="previewSlot?.caption"></p>
                    </div>
                </template>
            </div>

            <div class="p-5 flex items-center justify-between gap-4">
                <div>
                    <h3 class="text-base font-extrabold text-theme-text" x-text="previewSlot?.title"></h3>
                    <p class="text-xs text-theme-muted mt-1" x-text="previewSlot?.caption"></p>
                </div>
                <template x-if="isStaff">
                    <button type="button" @click="openEditSlot(previewSlot); previewSlot = null"
                            class="px-4 py-2 rounded-xl bg-theme-accent text-white text-xs font-extrabold shrink-0">
                        ✏️ Edit This Slot
                    </button>
                </template>
            </div>
        </div>
    </div>

    <!-- MODAL 2: LIBRARIAN EDIT GALLERY PICTURE SLOT (UPLOAD PHOTO / EDIT CAPTION) -->
    <div x-show="editingSlot" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 backdrop-blur-xs p-4"
         x-transition>
        <div @click.away="editingSlot = null"
             class="w-full max-w-lg bg-theme-card border border-theme-border rounded-2xl shadow-2xl overflow-hidden">
            <div class="p-4 sm:p-5 border-b border-theme-border flex items-center justify-between">
                <div>
                    <h3 class="text-base font-extrabold text-theme-text"
                        x-text="'Edit ' + editingSlot?.floor_level + ' Picture Slot #' + editingSlot?.slot_number"></h3>
                    <p class="text-xs text-theme-muted">Upload a facility photo (JPG/PNG/WEBP) or update the zone description</p>
                </div>
                <button @click="editingSlot = null" class="w-8 h-8 rounded-lg bg-theme-hover text-theme-muted font-bold">&times;</button>
            </div>

            <form @submit.prevent="saveSlotChanges()" class="p-5 space-y-4 text-xs">
                <div>
                    <label class="block font-extrabold uppercase text-theme-muted mb-1">Zone / Section Tag</label>
                    <input type="text" x-model="editingSlot.zone_tag" required
                           class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border text-theme-text font-bold">
                </div>

                <div>
                    <label class="block font-extrabold uppercase text-theme-muted mb-1">Picture Title</label>
                    <input type="text" x-model="editingSlot.title" required
                           class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border text-theme-text font-bold">
                </div>

                <div>
                    <label class="block font-extrabold uppercase text-theme-muted mb-1">Caption / Description</label>
                    <textarea x-model="editingSlot.caption" rows="3" required
                              class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border text-theme-text"></textarea>
                </div>

                <div>
                    <label class="block font-extrabold uppercase text-theme-muted mb-1">Upload Facility Picture (Optional)</label>
                    <input type="file" x-ref="slotFileInput" accept="image/jpeg,image/png,image/webp"
                           class="w-full px-3.5 py-2 rounded-xl bg-theme-main border border-theme-border text-theme-text">
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="editingSlot = null"
                            class="px-4 py-2.5 rounded-xl bg-theme-main text-theme-muted font-bold">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-5 py-2.5 rounded-xl bg-theme-accent text-white font-extrabold shadow">
                        ✓ Save Picture Slot
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
function communityHubApp() {
    return {
        activeFloor: '4F',
        isStaff: false,
        gallery4F: [],
        gallery3F: [],
        suggestions: [],
        previewSlot: null,
        editingSlot: null,
        editingContact: false,
        toastMsg: '',
        toastOk: true,

        contact: {
            contact_email: '',
            contact_phone: '',
            contact_location: '',
            contact_hours: '',
            contact_facebook: ''
        },

        suggestionForm: {
            category: 'Book / Capstone Request',
            subject: '',
            message: '',
            is_anonymous: false
        },

        showToast(msg, ok = true) {
            this.toastMsg = msg;
            this.toastOk = ok;
        },

        async loadHub() {
            const res = await fetch('<?= BASE_URL ?>/api/community/hub.php?action=init');
            const data = await res.json();
            if (data.success) {
                this.isStaff     = data.is_staff;
                this.gallery4F   = data.gallery_4f;
                this.gallery3F   = data.gallery_3f;
                this.contact     = data.contact;
                this.suggestions = data.suggestions;
            }
        },

        openEditSlot(slot) {
            this.editingSlot = { ...slot };
            if (this.$refs.slotFileInput) {
                this.$refs.slotFileInput.value = '';
            }
        },

        async saveSlotChanges() {
            if (!this.editingSlot) return;
            const fd = new FormData();
            fd.append('action', 'save_gallery_slot');
            fd.append('id', this.editingSlot.id);
            fd.append('title', this.editingSlot.title);
            fd.append('zone_tag', this.editingSlot.zone_tag);
            fd.append('caption', this.editingSlot.caption);

            if (this.$refs.slotFileInput && this.$refs.slotFileInput.files[0]) {
                fd.append('image', this.$refs.slotFileInput.files[0]);
            }

            const res = await fetch('<?= BASE_URL ?>/api/community/hub.php', {
                method: 'POST',
                body: fd
            });
            const data = await res.json();
            this.showToast(data.message, data.success);
            if (data.success) {
                this.editingSlot = null;
                await this.loadHub();
            }
        },

        async saveContactInfo() {
            const res = await fetch('<?= BASE_URL ?>/api/community/hub.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'save_contact',
                    ...this.contact
                })
            });
            const data = await res.json();
            this.showToast(data.message, data.success);
            if (data.success) {
                this.editingContact = false;
            }
        },

        async submitSuggestion() {
            const res = await fetch('<?= BASE_URL ?>/api/community/hub.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'submit_suggestion',
                    ...this.suggestionForm
                })
            });
            const data = await res.json();
            this.showToast(data.message, data.success);
            if (data.success) {
                this.suggestionForm.subject = '';
                this.suggestionForm.message = '';
                this.suggestionForm.is_anonymous = false;
                await this.loadHub();
            }
        },

        async saveSuggestionReply(sg) {
            const res = await fetch('<?= BASE_URL ?>/api/community/hub.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'reply_suggestion',
                    id: sg.id,
                    status: sg.status,
                    librarian_reply: sg.librarian_reply
                })
            });
            const data = await res.json();
            this.showToast(data.message, data.success);
            if (data.success) await this.loadHub();
        },

        async deleteSuggestion(id) {
            const res = await fetch('<?= BASE_URL ?>/api/community/hub.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete_suggestion', id: id })
            });
            const data = await res.json();
            this.showToast(data.message, data.success);
            if (data.success) await this.loadHub();
        }
    };
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>