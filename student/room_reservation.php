<?php
// student/room_reservation.php
$page_title = 'Discussion Rooms A–G Reservation';
require_once __DIR__ . '/../includes/header.php';
require_role(['student', 'faculty']);
require_once __DIR__ . '/../includes/sidebar.php';
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<div x-data="roomBookingApp()" x-init="initData()" class="space-y-6">

    <!-- Top Policy & Guidelines Banner -->
    <div class="bg-theme-card border border-theme-border border-l-4 border-l-theme-accent rounded-2xl p-4 sm:p-6 shadow-xs">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <span class="px-2.5 py-1 text-[10px] font-extrabold uppercase rounded-full bg-theme-accent/15 text-theme-accent">
                    4th Floor East Wing • 7 Discussion Rooms (Rooms A–G)
                </span>
                <h1 class="text-xl sm:text-2xl font-extrabold text-theme-text mt-2">
                    Group Research & Discussion Room Reservation
                </h1>
                <p class="text-xs text-theme-muted mt-1">
                    Automated self-service scheduling replacing manual phone calls and Google Sheets.
                </p>
            </div>

            <button @click="showRulesModal = true"
                class="px-4 py-2.5 rounded-xl bg-amber-500/15 text-amber-400 border border-amber-500/30 text-xs font-extrabold hover:bg-amber-500 hover:text-slate-950 transition shrink-0">
                📜 Read Official 4-Step Room Rules
            </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mt-5 pt-4 border-t border-theme-border text-xs">
            <div class="p-3 rounded-xl bg-theme-main border border-theme-border">
                <p class="font-extrabold text-theme-accent">Step 1 • Booking Window</p>
                <p class="text-theme-muted mt-0.5">Book for Today or up to <strong>1 day before</strong> intended use.</p>
            </div>
            <div class="p-3 rounded-xl bg-theme-main border border-theme-border">
                <p class="font-extrabold text-emerald-400">Step 2 • 6 to 8 Persons</p>
                <p class="text-theme-muted mt-0.5">Group Rep + <strong>5 to 7 members</strong> (Min 6, Max 8 capacity).</p>
            </div>
            <div class="p-3 rounded-xl bg-theme-main border border-theme-border">
                <p class="font-extrabold text-amber-400">Step 3 • 1 Room / Day</p>
                <p class="text-theme-muted mt-0.5">The group representative may book <strong>1 room per day</strong>.</p>
            </div>
            <div class="p-3 rounded-xl bg-theme-main border border-theme-border">
                <p class="font-extrabold text-rose-400">Step 4 • Physical Presence</p>
                <p class="text-theme-muted mt-0.5">Min. <strong>6 members physically present</strong> at Help Desk to enter.</p>
            </div>
        </div>
    </div>

    <!-- Live Status Strip of Discussion Rooms A to G -->
    <div class="bg-theme-card border border-theme-border rounded-2xl p-4 sm:p-5 shadow-xs">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-extrabold text-theme-text">Click a Discussion Room to Select (Rooms A–G)</h2>
            <span class="text-xs text-theme-muted font-mono">Capacity: 6–8 Researchers</span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2.5">
            <template x-for="rm in rooms" :key="rm.id">
                <button type="button"
                    @click="form.space_id = rm.id"
                    :disabled="rm.current_status === 'maintenance'"
                    :class="[
                            form.space_id == rm.id ? 'ring-2 ring-theme-accent bg-theme-accent/15 border-theme-accent' : 'bg-theme-main border-theme-border hover:bg-theme-hover',
                            rm.current_status === 'maintenance' ? 'opacity-40 cursor-not-allowed' : ''
                        ]"
                    class="p-3 rounded-xl border text-left transition flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-extrabold text-theme-text" x-text="rm.space_code"></span>
                        <span class="w-2.5 h-2.5 rounded-full"
                            :class="{
                                  'bg-emerald-500': rm.current_status === 'available',
                                  'bg-amber-400': rm.current_status === 'reserved',
                                  'bg-red-500': rm.current_status === 'occupied',
                                  'bg-slate-500': rm.current_status === 'maintenance'
                              }"></span>
                    </div>
                    <p class="text-[11px] font-bold text-theme-muted mt-1" x-text="rm.space_name"></p>
                    <span class="mt-2 inline-block text-[10px] font-extrabold uppercase"
                        :class="{
                              'text-emerald-400': rm.current_status === 'available',
                              'text-amber-400': rm.current_status === 'reserved',
                              'text-red-400': rm.current_status === 'occupied'
                          }"
                        x-text="rm.current_status"></span>
                </button>
            </template>
        </div>
    </div>

    <!-- Main 2-Column Layout -->
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">

        <!-- LEFT 7 COLUMNS: RESERVATION BUILDER FORM -->
        <div class="xl:col-span-7 bg-theme-card border border-theme-border rounded-2xl p-5 sm:p-6 shadow-xs">
            <div class="flex items-center justify-between pb-4 mb-5 border-b border-theme-border">
                <div>
                    <h2 class="text-base font-extrabold text-theme-text">Create Discussion Room Reservation</h2>
                    <p class="text-xs text-theme-muted">Representative: <strong class="text-theme-text"><?= e($_SESSION['full_name']) ?></strong> (<span class="font-mono text-theme-accent">#<?= e($_SESSION['school_id']) ?></span>)</p>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-extrabold"
                    :class="totalGroupSize >= 6 && totalGroupSize <= 8 ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400'"
                    x-text="totalGroupSize + ' / 8 Pax Selected'"></span>
            </div>

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
                            x-text="alertSuccess ? 'Reservation Updated' : 'Policy / Booking Notice'"></h3>
                        <p class="text-xs sm:text-sm text-theme-text font-semibold mt-1.5 leading-relaxed" x-text="alertMsg"></p>
                    </div>
                    <button type="button" @click="alertMsg = ''"
                        :class="alertSuccess ? 'bg-emerald-500 text-slate-950' : 'bg-[#D9232A] text-white'"
                        class="w-full py-2.5 px-5 rounded-xl font-extrabold text-xs shadow-lg transition">
                        OK
                    </button>
                </div>
            </div>

            <form @submit.prevent="submitBooking()" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-extrabold uppercase tracking-wider text-theme-muted mb-1">
                            Step 1: Intended Date (Max 1 Day Advance)
                        </label>
                        <select x-model="form.reservation_date" @change="initData()"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border text-xs font-bold text-theme-text outline-none">
                            <option :value="today" x-text="'Today (' + today + ')'"></option>
                            <option :value="tomorrow" x-text="'Tomorrow (' + tomorrow + ' — 1 Day Advance)'"></option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-extrabold uppercase tracking-wider text-theme-muted mb-1">
                            Selected Discussion Room
                        </label>
                        <select x-model="form.space_id" required
                            class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border text-xs font-bold text-theme-text outline-none">
                            <template x-for="rm in rooms" :key="rm.id">
                                <option :value="rm.id" x-text="rm.space_name + ' (' + rm.space_code + ' — 6 to 8 Pax)'"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[11px] font-extrabold uppercase tracking-wider text-theme-muted mb-1">
                            2-Hour Time Slot
                        </label>
                        <select x-model="form.time_slot" required
                            class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border text-xs font-bold text-theme-text font-mono outline-none">
                            <option value="08:00:00|10:00:00">08:00 AM – 10:00 AM</option>
                            <option value="10:00:00|12:00:00">10:00 AM – 12:00 PM</option>
                            <option value="13:00:00|15:00:00">01:00 PM – 03:00 PM</option>
                            <option value="15:00:00|17:00:00">03:00 PM – 05:00 PM</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-extrabold uppercase tracking-wider text-theme-muted mb-1">
                            Research / Discussion Topic
                        </label>
                        <input type="text" x-model="form.purpose" required
                            placeholder="e.g. BSIT Capstone Chapter 4 System Testing"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border text-xs text-theme-text outline-none">
                    </div>
                </div>

                <!-- Step 2: Tag 5 to 7 Group Members -->
                <div class="p-4 rounded-xl bg-theme-main border border-theme-border space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div>
                            <label class="block text-xs font-extrabold text-theme-text">
                                Step 2: Tag 5 to 7 Group Members (9-Digit BCP Student ID)
                            </label>
                            <p class="text-[11px] text-theme-muted">
                                Total group must be <strong>6 to 8 members</strong> including you as the Representative.
                            </p>
                        </div>

                        <button type="button" @click="quickFillClassmates()"
                            class="px-3 py-1.5 rounded-lg bg-theme-accent/15 text-theme-accent hover:bg-theme-accent hover:text-white text-[11px] font-extrabold transition shrink-0">
                            ⚡ Demo Quick-Fill 5 Classmates
                        </button>
                    </div>

                    <div class="flex gap-2">
                        <input type="text" x-model="memberInput"
                            @keydown.enter.prevent="addMemberById()"
                            maxlength="9"
                            placeholder="Enter 9-digit Student ID (e.g. 240103538)"
                            class="flex-1 px-3.5 py-2.5 rounded-xl bg-theme-card border border-theme-border text-xs font-mono text-theme-text outline-none">
                        <button type="button" @click="addMemberById()"
                            :disabled="taggedMembers.length >= 7"
                            class="px-4 py-2.5 rounded-xl bg-theme-accent hover:bg-theme-accentHover disabled:opacity-40 text-white text-xs font-extrabold shrink-0">
                            + Add Member
                        </button>
                    </div>

                    <div class="space-y-1.5 pt-1">
                        <div class="p-2.5 rounded-lg bg-theme-card border border-theme-accent/40 flex items-center justify-between text-xs">
                            <div class="flex items-center gap-2">
                                <span class="w-5 h-5 rounded-full bg-theme-accent text-white font-bold text-[10px] flex items-center justify-center">1</span>
                                <span class="font-bold text-theme-text"><?= e($_SESSION['full_name']) ?></span>
                                <span class="font-mono text-[11px] text-theme-accent">#<?= e($_SESSION['school_id']) ?></span>
                            </div>
                            <span class="px-2 py-0.5 rounded bg-theme-accent/20 text-theme-accent text-[10px] font-extrabold uppercase">Representative</span>
                        </div>

                        <template x-for="(m, idx) in taggedMembers" :key="m.school_id">
                            <div class="p-2.5 rounded-lg bg-theme-card border border-theme-border flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="w-5 h-5 rounded-full bg-emerald-500/20 text-emerald-400 font-bold text-[10px] flex items-center justify-center" x-text="idx + 2"></span>
                                    <span class="font-bold text-theme-text" x-text="m.full_name"></span>
                                    <span class="font-mono text-[11px] text-theme-muted" x-text="'#' + m.school_id + ' (' + (m.program_code || 'BCP') + ')'"></span>
                                </div>
                                <button type="button" @click="removeMember(m.school_id)"
                                    class="text-red-400 hover:text-red-300 text-xs font-extrabold px-2">
                                    Remove
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

                <button type="submit"
                    :disabled="submitting || totalGroupSize < 6 || totalGroupSize > 8"
                    class="w-full py-3 px-5 rounded-xl bg-theme-accent hover:bg-theme-accentHover disabled:opacity-40 text-white font-extrabold text-xs shadow-lg transition">
                    <span x-show="!submitting">Confirm Group Reservation (6–8 Members Policy)</span>
                    <span x-show="submitting" x-cloak>Validating Policy & Saving...</span>
                </button>
            </form>
        </div>

        <!-- RIGHT 5 COLUMNS: MY GROUP RESERVATIONS (WITH COMPLETE & REMOVE BUTTONS) -->
        <div class="xl:col-span-5 space-y-4">
            <div class="bg-theme-card border border-theme-border rounded-2xl p-5 shadow-xs">
                <h2 class="text-base font-extrabold text-theme-text mb-1">My Discussion Room Bookings</h2>
                <p class="text-xs text-theme-muted mb-4">
                    You can mark an active session as <strong>Completed</strong> and <strong>Remove</strong> completed records below.
                </p>

                <template x-if="myBookings.length === 0">
                    <div class="p-8 text-center text-xs text-theme-muted border border-dashed border-theme-border rounded-xl">
                        You have no active or past Discussion Room reservations yet.
                    </div>
                </template>

                <div class="space-y-3">
                    <template x-for="bk in myBookings" :key="bk.id">
                        <div class="p-4 rounded-xl bg-theme-main border border-theme-border space-y-3">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="px-2.5 py-0.5 rounded bg-theme-accent/20 text-theme-accent text-xs font-extrabold" x-text="bk.space_name"></span>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase"
                                            :class="{
                                                  'bg-amber-500/20 text-amber-400': bk.status === 'pending',
                                                  'bg-emerald-500/20 text-emerald-400': bk.status === 'occupied',
                                                  'bg-sky-500/20 text-sky-400': bk.status === 'completed',
                                                  'bg-red-500/20 text-red-400': bk.status === 'violation' || bk.status === 'no_show' || bk.status === 'cancelled'
                                              }"
                                            x-text="bk.status"></span>
                                    </div>
                                    <p class="text-xs font-mono font-bold text-theme-text mt-1.5"
                                        x-text="bk.reservation_date + ' • ' + bk.start_time.substring(0,5) + ' – ' + bk.end_time.substring(0,5)"></p>
                                    <p class="text-xs text-theme-muted mt-0.5" x-text="bk.purpose"></p>
                                </div>

                                <div class="text-right shrink-0">
                                    <span class="text-xs font-extrabold text-theme-text" x-text="bk.present_headcount + '/' + bk.members.length + ' Verified'"></span>
                                    <p class="text-[10px] text-theme-muted">Min 6 required</p>
                                </div>
                            </div>

                            <!-- Member Badges -->
                            <div class="flex flex-wrap gap-1.5 pt-2 border-t border-theme-border">
                                <template x-for="mem in bk.members" :key="mem.school_id">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-mono border"
                                        :class="mem.is_present == 1 ? 'bg-emerald-500/15 border-emerald-500/40 text-emerald-400' : 'bg-theme-card border-theme-border text-theme-muted'"
                                        x-text="mem.full_name + ' (#' + mem.school_id + ')'"></span>
                                </template>
                            </div>

                            <!-- Action Buttons: Cancel, Complete Session, OR Remove Completed -->
                            <div class="flex flex-wrap items-center justify-end gap-2 pt-2 border-t border-theme-border">
                                <!-- Cancel Pending -->
                                <button x-show="bk.status === 'pending'"
                                    type="button" @click="cancelBooking(bk.id)"
                                    class="px-3 py-1.5 rounded-lg bg-amber-500/15 text-amber-400 hover:bg-amber-500 hover:text-slate-950 text-xs font-bold transition">
                                    Cancel Slot
                                </button>

                                <!-- Mark as Completed -->
                                <button x-show="bk.status === 'occupied' || bk.status === 'pending'"
                                    type="button" @click="completeBooking(bk.id)"
                                    class="px-3 py-1.5 rounded-lg bg-emerald-500/20 text-emerald-400 hover:bg-emerald-500 hover:text-slate-950 text-xs font-extrabold transition">
                                    ✓ Mark Completed
                                </button>

                                <!-- Remove Completed Discussion Room Record -->
                                <button x-show="bk.status === 'completed' || bk.status === 'cancelled' || bk.status === 'no_show' || bk.status === 'violation'"
                                    type="button" @click="removeBooking(bk.id)"
                                    class="px-3.5 py-1.5 rounded-lg bg-red-500/20 text-red-400 hover:bg-red-500 hover:text-white text-xs font-extrabold transition flex items-center gap-1">
                                    <span>🗑️ Remove Completed</span>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

    </div>

    <!-- OFFICIAL DISCUSSION ROOM POLICY MODAL -->
    <div x-show="showRulesModal" x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 backdrop-blur-xs p-4"
        x-transition>
        <div @click.away="showRulesModal = false"
            class="w-full max-w-lg bg-theme-card border border-theme-border rounded-2xl shadow-2xl overflow-hidden">
            <div class="p-5 border-b border-theme-border flex items-center justify-between">
                <h3 class="text-base font-extrabold text-theme-text">Bestlink Discussion Rooms A–G Policy</h3>
                <button @click="showRulesModal = false" class="w-8 h-8 rounded-lg bg-theme-hover text-theme-muted font-bold">&times;</button>
            </div>
            <div class="p-5 space-y-3 text-xs text-theme-text leading-relaxed">
                <p>• The library has <strong>7 discussion rooms (Rooms A–G)</strong> serving as a venue for researchers to discuss and work together.</p>
                <p>• Use of the room is strictly limited to a <strong>minimum of 6 persons</strong> and a <strong>maximum capacity of 8 people</strong>.</p>
                <div class="p-3.5 rounded-xl bg-theme-main border border-theme-border space-y-1.5">
                    <p class="font-extrabold text-theme-accent">How to Reserve Discussion Rooms:</p>
                    <p><strong>Step 1.</strong> Reservation can be made up to one day before the intended date of use.</p>
                    <p><strong>Step 2.</strong> The group should choose among themselves a representative who will make the reservation.</p>
                    <p><strong>Step 3.</strong> The group's representative is allowed to book one discussion room per day.</p>
                    <p><strong>Step 4.</strong> Only a group with a <strong>minimum of 6 members physically present</strong> at the specified reservation time shall be allowed to enter the room.</p>
                </div>
            </div>
            <div class="px-5 py-3 bg-theme-main border-t border-theme-border flex justify-end">
                <button @click="showRulesModal = false" class="px-5 py-2 rounded-xl bg-theme-accent text-white text-xs font-bold">Understood</button>
            </div>
        </div>
    </div>

</div>

<script>
    function roomBookingApp() {
        return {
            today: '<?= date('Y-m-d') ?>',
            tomorrow: '<?= date('Y-m-d', strtotime('+1 day')) ?>',
            rooms: [],
            takenSlots: [],
            myBookings: [],
            taggedMembers: [],
            memberInput: '',
            submitting: false,
            alertMsg: '',
            alertSuccess: false,
            showRulesModal: false,

            form: {
                space_id: 1,
                reservation_date: '<?= date('Y-m-d') ?>',
                time_slot: '08:00:00|10:00:00',
                purpose: ''
            },

            get totalGroupSize() {
                return 1 + this.taggedMembers.length;
            },

            async initData() {
                const res = await fetch(`<?= BASE_URL ?>/api/rooms/book_room.php?action=init&date=${this.form.reservation_date}`);
                const data = await res.json();
                if (data.success) {
                    this.rooms = data.rooms;
                    this.takenSlots = data.taken_slots;
                    this.myBookings = data.my_bookings;
                    if (this.rooms.length > 0 && !this.form.space_id) {
                        this.form.space_id = this.rooms[0].id;
                    }
                }
            },

            async addMemberById(customId = null) {
                const idToLookup = (customId || this.memberInput).trim();
                if (!idToLookup) return;

                if (this.taggedMembers.length >= 7) {
                    this.showAlert('Maximum capacity reached (8 persons total including Representative).', false);
                    return;
                }

                if (this.taggedMembers.some(m => m.school_id === idToLookup)) {
                    this.showAlert(`Student #${idToLookup} is already in your group list.`, false);
                    return;
                }

                const res = await fetch('<?= BASE_URL ?>/api/rooms/book_room.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'lookup_member',
                        school_id: idToLookup
                    })
                });
                const data = await res.json();
                if (data.success) {
                    this.taggedMembers.push(data.member);
                    if (!customId) this.memberInput = '';
                    this.alertMsg = '';
                } else {
                    this.showAlert(data.message, false);
                }
            },

            removeMember(schoolId) {
                this.taggedMembers = this.taggedMembers.filter(m => m.school_id !== schoolId);
            },

            async quickFillClassmates() {
                this.taggedMembers = [];
                const demoIds = ['240103538', '240103539', '240103540', '240103541', '240103542'];
                for (const id of demoIds) {
                    await this.addMemberById(id);
                }
                if (!this.form.purpose) {
                    this.form.purpose = 'BSIT Capstone Group Research & System Documentation';
                }
            },

            showAlert(msg, success = false) {
                this.alertMsg = msg;
                this.alertSuccess = success;
            },

            async submitBooking() {
                this.submitting = true;
                this.alertMsg = '';
                try {
                    const res = await fetch('<?= BASE_URL ?>/api/rooms/book_room.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'create_booking',
                            space_id: this.form.space_id,
                            reservation_date: this.form.reservation_date,
                            time_slot: this.form.time_slot,
                            purpose: this.form.purpose,
                            member_school_ids: this.taggedMembers.map(m => m.school_id)
                        })
                    });
                    const data = await res.json();
                    this.showAlert(data.message, data.success);
                    if (data.success) {
                        this.taggedMembers = [];
                        this.form.purpose = '';
                        await this.initData();
                    }
                } finally {
                    this.submitting = false;
                }
            },

            async cancelBooking(reservationId) {
                const res = await fetch('<?= BASE_URL ?>/api/rooms/book_room.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'cancel_booking',
                        reservation_id: reservationId
                    })
                });
                const data = await res.json();
                this.showAlert(data.message, data.success);
                await this.initData();
            },

            async completeBooking(reservationId) {
                const res = await fetch('<?= BASE_URL ?>/api/rooms/book_room.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'complete_booking',
                        reservation_id: reservationId
                    })
                });
                const data = await res.json();
                this.showAlert(data.message, data.success);
                await this.initData();
            },

            async removeBooking(reservationId) {
                const res = await fetch('<?= BASE_URL ?>/api/rooms/book_room.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'remove_booking',
                        reservation_id: reservationId
                    })
                });
                const data = await res.json();
                this.showAlert(data.message, data.success);
                await this.initData();
            }
        };
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>