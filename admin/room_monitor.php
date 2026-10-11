<?php
// admin/room_monitor.php
$page_title = 'Discussion Rooms A–G & Cubicles Monitor';
require_once __DIR__ . '/../includes/header.php';
require_role(['librarian', 'super_admin']);
$auto_verify_id = (int) ($_GET['verify'] ?? 0);
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div x-data="adminRoomMonitor(<?= $auto_verify_id ?>)" x-init="loadMonitor()" class="space-y-6">

    <!-- Top Monitor Header & Date Filter -->
    <div class="bg-theme-card border border-theme-border border-l-4 border-l-emerald-500 rounded-2xl p-4 sm:p-6 flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-xs">
        <div>
            <span class="px-2.5 py-1 text-[10px] font-extrabold uppercase rounded-full bg-emerald-500/15 text-emerald-400">
                4th Floor Space Control • Rooms A–G & Cubicles A–F
            </span>
            <h1 class="text-xl sm:text-2xl font-extrabold text-theme-text mt-2">
                Discussion Room Verification & Cubicle Radar
            </h1>
            <p class="text-xs text-theme-muted mt-0.5">
                Verify at least <strong>6 physically present group members</strong> at the Help Desk, mark sessions as <strong>Completed</strong>, and <strong>Remove</strong> finished records.
            </p>
        </div>

        <div class="flex items-center gap-2.5 shrink-0">
            <input type="date" x-model="selectedDate" @change="loadMonitor()"
                   class="px-3.5 py-2 rounded-xl bg-theme-main border border-theme-border text-xs font-mono font-bold text-theme-text outline-none">
            <button @click="loadMonitor()"
                    class="px-4 py-2 rounded-xl bg-theme-accent text-white text-xs font-extrabold shadow">
                ↻ Refresh
            </button>
        </div>
    </div>

    <!-- Alert Banner -->
    <div x-show="feedbackMsg" x-cloak x-transition
         :class="feedbackSuccess ? 'bg-emerald-500/15 border-emerald-500/40 text-emerald-400' : 'bg-red-500/15 border-red-500/40 text-red-400'"
         class="p-4 rounded-xl border text-xs font-bold flex items-center justify-between">
        <span x-text="feedbackMsg"></span>
        <button @click="feedbackMsg = ''" class="font-extrabold">&times;</button>
    </div>

    <!-- LIVE SPACES RADAR: DISCUSSION ROOMS A–G & CUBICLES A–F -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <div class="lg:col-span-7 bg-theme-card border border-theme-border rounded-2xl p-5 shadow-xs">
            <h2 class="text-sm font-extrabold text-theme-text mb-3">East Wing: Discussion Rooms A–G (6–8 Pax)</h2>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                <template x-for="sp in spaces.filter(s => s.space_type === 'discussion_room')" :key="sp.id">
                    <div class="p-3 rounded-xl bg-theme-main border border-theme-border flex flex-col justify-between">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-extrabold text-theme-text" x-text="sp.space_code"></span>
                            <span class="px-2 py-0.5 rounded text-[9px] font-extrabold uppercase"
                                  :class="{
                                      'bg-emerald-500/20 text-emerald-400': sp.current_status === 'available',
                                      'bg-amber-500/20 text-amber-400': sp.current_status === 'reserved',
                                      'bg-red-500/20 text-red-400': sp.current_status === 'occupied',
                                      'bg-slate-500/20 text-slate-400': sp.current_status === 'maintenance'
                                  }"
                                  x-text="sp.current_status"></span>
                        </div>
                        <select :value="sp.current_status"
                                @change="changeSpaceStatus(sp.id, $event.target.value)"
                                class="mt-3 w-full px-2 py-1 rounded-lg bg-theme-card border border-theme-border text-[10px] font-bold text-theme-text outline-none">
                            <option value="available">Set Available</option>
                            <option value="reserved">Set Reserved</option>
                            <option value="occupied">Set Occupied</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                </template>
            </div>
        </div>

        <div class="lg:col-span-5 bg-theme-card border border-theme-border rounded-2xl p-5 shadow-xs">
            <h2 class="text-sm font-extrabold text-theme-text mb-3">West Reading Hub: Enclosed Cubicles A–F</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                <template x-for="cb in spaces.filter(s => s.space_type === 'reading_cubicle')" :key="cb.id">
                    <div class="p-3 rounded-xl bg-theme-main border border-theme-border flex flex-col justify-between">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-extrabold text-theme-text" x-text="cb.space_code.replace('CUBICLE-', 'Cubicle ')"></span>
                            <span class="w-2.5 h-2.5 rounded-full"
                                  :class="cb.current_status === 'available' ? 'bg-emerald-500' : 'bg-red-500'"></span>
                        </div>
                        <button @click="changeSpaceStatus(cb.id, cb.current_status === 'available' ? 'occupied' : 'available')"
                                :class="cb.current_status === 'available' ? 'bg-emerald-500/15 text-emerald-400' : 'bg-red-500/15 text-red-400'"
                                class="mt-3 w-full py-1.5 rounded-lg text-[10px] font-extrabold uppercase transition"
                                x-text="cb.current_status === 'available' ? 'Available (Click to Occupy)' : 'Occupied (Click to Free)'">
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- RESERVATIONS TABLE & HEADCOUNT VERIFICATION QUEUE -->
    <div class="bg-theme-card border border-theme-border rounded-2xl overflow-hidden shadow-xs">
        <div class="p-5 border-b border-theme-border flex items-center justify-between">
            <div>
                <h2 class="text-base font-extrabold text-theme-text">Discussion Room Reservations Queue (<span x-text="selectedDate"></span>)</h2>
                <p class="text-xs text-theme-muted">Verify group check-ins, mark sessions as completed, or remove completed records</p>
            </div>
        </div>

        <template x-if="reservations.length === 0">
            <div class="p-10 text-center text-xs text-theme-muted">
                No Discussion Room reservations found for <span class="font-mono" x-text="selectedDate"></span>.
            </div>
        </template>

        <div x-show="reservations.length > 0" class="overflow-x-auto">
            <table class="w-full text-left text-xs min-w-[780px]">
                <thead>
                    <tr class="bg-theme-main text-theme-muted uppercase text-[10px] tracking-wider border-b border-theme-border">
                        <th class="py-3.5 px-5">Room & Slot</th>
                        <th class="py-3.5 px-5">Group Representative</th>
                        <th class="py-3.5 px-5">Research Purpose</th>
                        <th class="py-3.5 px-5">Headcount Verified</th>
                        <th class="py-3.5 px-5">Status</th>
                        <th class="py-3.5 px-5 text-right">Desk Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-theme-border">
                    <template x-for="res in reservations" :key="res.id">
                        <tr class="hover:bg-theme-hover/40 transition">
                            <td class="py-3.5 px-5">
                                <span class="px-2.5 py-1 rounded-lg bg-theme-accent/15 text-theme-accent font-extrabold" x-text="res.space_name"></span>
                                <p class="font-mono text-[11px] text-theme-muted mt-1" x-text="res.start_time.substring(0,5) + ' – ' + res.end_time.substring(0,5)"></p>
                            </td>
                            <td class="py-3.5 px-5">
                                <p class="font-bold text-theme-text" x-text="res.rep_name"></p>
                                <p class="font-mono text-[11px] text-theme-accent" x-text="'#' + res.rep_school_id + ' • ' + (res.rep_program || 'STAFF')"></p>
                            </td>
                            <td class="py-3.5 px-5 text-theme-text max-w-[200px] truncate" x-text="res.purpose"></td>
                            <td class="py-3.5 px-5">
                                <span class="px-2.5 py-1 rounded-full font-mono font-extrabold text-[11px]"
                                      :class="res.present_headcount >= 6 ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400'"
                                      x-text="res.present_headcount + ' / ' + res.members.length + ' Present'"></span>
                            </td>
                            <td class="py-3.5 px-5">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase"
                                      :class="{
                                          'bg-amber-500/20 text-amber-400': res.status === 'pending',
                                          'bg-emerald-500/20 text-emerald-400': res.status === 'occupied',
                                          'bg-sky-500/20 text-sky-400': res.status === 'completed',
                                          'bg-red-500/20 text-red-400': res.status === 'violation' || res.status === 'no_show' || res.status === 'cancelled'
                                      }"
                                      x-text="res.status"></span>
                            </td>
                            <td class="py-3.5 px-5 text-right">
                                <div class="inline-flex items-center justify-end gap-2">
                                    <button @click="openVerifyModal(res)"
                                            class="px-3 py-1.5 rounded-xl bg-theme-accent hover:bg-theme-accentHover text-white font-extrabold text-xs shadow">
                                        Verify / Manage
                                    </button>

                                    <!-- Quick Complete Button -->
                                    <button x-show="res.status === 'occupied' || res.status === 'pending'"
                                            @click="quickComplete(res.id)"
                                            class="px-3 py-1.5 rounded-xl bg-emerald-500/20 text-emerald-400 hover:bg-emerald-500 hover:text-slate-950 font-extrabold text-xs transition">
                                        ✓ Complete
                                    </button>

                                    <!-- Remove Button for Completed / No-Show / Cancelled / Violation -->
                                    <button x-show="res.status === 'completed' || res.status === 'no_show' || res.status === 'cancelled' || res.status === 'violation'"
                                            @click="deleteReservationRecord(res.id)"
                                            class="px-3 py-1.5 rounded-xl bg-red-500/20 text-red-400 hover:bg-red-500 hover:text-white font-extrabold text-xs transition">
                                        🗑️ Remove
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- LIBRARIAN 6–8 MEMBER VERIFICATION MODAL -->
    <div x-show="activeRes" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 backdrop-blur-xs p-3 sm:p-5"
         x-transition>
        <div @click.away="activeRes = null"
             class="w-full max-w-3xl max-h-[90dvh] bg-theme-card border border-theme-border rounded-2xl shadow-2xl overflow-hidden flex flex-col">

            <div class="p-4 sm:p-5 border-b border-theme-border flex items-center justify-between bg-theme-main/50">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="px-2.5 py-0.5 rounded bg-theme-accent text-white text-xs font-extrabold" x-text="activeRes?.space_name"></span>
                        <span class="text-xs font-mono text-theme-muted" x-text="activeRes?.start_time?.substring(0,5) + ' – ' + activeRes?.end_time?.substring(0,5)"></span>
                    </div>
                    <h3 class="text-base sm:text-lg font-extrabold text-theme-text mt-1">
                        Physical Presence Headcount Verification (Min. 6 Required)
                    </h3>
                </div>
                <button @click="activeRes = null" class="w-8 h-8 rounded-lg bg-theme-hover text-theme-muted font-bold">&times;</button>
            </div>

            <div class="p-4 sm:p-6 overflow-y-auto space-y-5">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 p-3.5 rounded-xl bg-theme-main border border-theme-border">
                    <div>
                        <p class="text-xs font-extrabold text-theme-text">
                            Verified Present at Help Desk:
                            <span class="text-base font-mono ml-1"
                                  :class="activeRes?.present_headcount >= 6 ? 'text-emerald-400' : 'text-amber-400'"
                                  x-text="activeRes?.present_headcount + ' of ' + activeRes?.members?.length + ' Members'"></span>
                        </p>
                        <p class="text-[11px] text-theme-muted">Click each student card below to confirm they are physically standing at the counter.</p>
                    </div>
                    <button type="button" @click="markAllPresent()"
                            class="px-3 py-2 rounded-xl bg-emerald-500/20 text-emerald-400 hover:bg-emerald-500 hover:text-slate-950 text-xs font-extrabold transition shrink-0">
                        ✓ Mark All Present
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <template x-for="(mem, idx) in activeRes?.members" :key="mem.user_id">
                        <div @click="toggleMemberPresence(mem)"
                             :class="mem.is_present == 1 ? 'border-emerald-500 bg-emerald-500/10' : 'border-theme-border bg-theme-main hover:bg-theme-hover'"
                             class="p-3.5 rounded-xl border-2 cursor-pointer transition flex items-center justify-between gap-3 select-none">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-11 h-11 rounded-xl bg-theme-accent text-white font-cartoon font-bold flex items-center justify-center text-base shrink-0 shadow">
                                    <span x-text="mem.full_name.substring(0,1)"></span>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-xs font-extrabold text-theme-text truncate" x-text="(idx + 1) + '. ' + mem.full_name"></p>
                                    <p class="text-[11px] font-mono text-theme-accent" x-text="'#' + mem.school_id + ' • ' + (mem.program_code || 'BCP')"></p>
                                </div>
                            </div>

                            <span class="px-2.5 py-1 rounded-lg text-[10px] font-extrabold uppercase shrink-0"
                                  :class="mem.is_present == 1 ? 'bg-emerald-500 text-slate-950' : 'bg-theme-card text-theme-muted border border-theme-border'"
                                  x-text="mem.is_present == 1 ? '✓ PRESENT' : 'ABSENT'"></span>
                        </div>
                    </template>
                </div>

                <div class="p-3.5 rounded-xl bg-theme-main border border-theme-border space-y-2">
                    <label class="block text-[11px] font-extrabold uppercase tracking-wider text-red-400">
                        Rule Violation / Incident Report (Optional)
                    </label>
                    <div class="flex flex-col sm:flex-row gap-2">
                        <input type="text" x-model="violationNotes"
                               placeholder="e.g. Less than 6 members arrived / Noise violation in Room A..."
                               class="flex-1 px-3.5 py-2 rounded-xl bg-theme-card border border-theme-border text-xs text-theme-text outline-none">
                        <button type="button" @click="updateBookingStatus('violation')"
                                class="px-4 py-2 rounded-xl bg-[#D9232A] hover:bg-red-700 text-white text-xs font-extrabold shrink-0">
                            ⚠️ Log Violation
                        </button>
                    </div>
                </div>
            </div>

            <!-- Modal Footer Controls -->
            <div class="p-4 bg-theme-main border-t border-theme-border flex flex-wrap items-center justify-between gap-2">
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" @click="updateBookingStatus('no_show')"
                            class="px-3.5 py-2 rounded-xl bg-amber-500/20 text-amber-400 hover:bg-amber-500 hover:text-slate-950 text-xs font-extrabold transition">
                        Mark No-Show
                    </button>
                    <button type="button" @click="updateBookingStatus('completed')"
                            class="px-3.5 py-2 rounded-xl bg-sky-500/20 text-sky-400 hover:bg-sky-500 hover:text-white text-xs font-extrabold transition">
                        ✓ Complete & Free Room
                    </button>
                    <button type="button" @click="deleteReservationRecord(activeRes.id)"
                            class="px-3.5 py-2 rounded-xl bg-red-500/20 text-red-400 hover:bg-red-500 hover:text-white text-xs font-extrabold transition">
                        🗑️ Remove Record
                    </button>
                </div>

                <button type="button"
                        @click="approveCheckIn()"
                        :disabled="activeRes?.present_headcount < 6"
                        class="px-5 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 disabled:opacity-40 text-slate-950 font-extrabold text-xs shadow-lg transition">
                    🔓 Unlock Room (Confirm 6+ Present)
                </button>
            </div>
        </div>
    </div>

</div>

<script>
function adminRoomMonitor(autoVerifyId = 0) {
    return {
        selectedDate: '<?= date('Y-m-d') ?>',
        spaces: [],
        reservations: [],
        activeRes: null,
        violationNotes: '',
        feedbackMsg: '',
        feedbackSuccess: true,

        async loadMonitor() {
            const res = await fetch(`<?= BASE_URL ?>/api/rooms/checkin_group.php?action=list&date=${this.selectedDate}`);
            const data = await res.json();
            if (data.success) {
                this.spaces = data.spaces;
                this.reservations = data.reservations;

                if (this.activeRes) {
                    this.activeRes = this.reservations.find(r => r.id == this.activeRes.id) || null;
                } else if (autoVerifyId > 0) {
                    const found = this.reservations.find(r => r.id == autoVerifyId);
                    if (found) this.openVerifyModal(found);
                    autoVerifyId = 0;
                }
            }
        },

        openVerifyModal(resObj) {
            this.activeRes = resObj;
            this.violationNotes = resObj.violation_notes || '';
        },

        async toggleMemberPresence(mem) {
            const res = await fetch('<?= BASE_URL ?>/api/rooms/checkin_group.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'toggle_member',
                    reservation_id: this.activeRes.id,
                    user_id: mem.user_id
                })
            });
            const data = await res.json();
            if (data.success) {
                mem.is_present = data.is_present ? 1 : 0;
                this.activeRes.present_headcount = data.present_headcount;
            }
        },

        async markAllPresent() {
            const res = await fetch('<?= BASE_URL ?>/api/rooms/checkin_group.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'mark_all_present', reservation_id: this.activeRes.id })
            });
            const data = await res.json();
            if (data.success) {
                await this.loadMonitor();
            }
        },

        async approveCheckIn() {
            const res = await fetch('<?= BASE_URL ?>/api/rooms/checkin_group.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'approve_checkin', reservation_id: this.activeRes.id })
            });
            const data = await res.json();
            this.feedbackMsg = data.message;
            this.feedbackSuccess = data.success;
            if (data.success) {
                this.activeRes = null;
                await this.loadMonitor();
            }
        },

        async quickComplete(reservationId) {
            const res = await fetch('<?= BASE_URL ?>/api/rooms/checkin_group.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update_reservation_status',
                    reservation_id: reservationId,
                    status: 'completed',
                    violation_notes: ''
                })
            });
            const data = await res.json();
            this.feedbackMsg = data.message;
            this.feedbackSuccess = data.success;
            await this.loadMonitor();
        },

        async updateBookingStatus(status) {
            const res = await fetch('<?= BASE_URL ?>/api/rooms/checkin_group.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'update_reservation_status',
                    reservation_id: this.activeRes.id,
                    status: status,
                    violation_notes: this.violationNotes
                })
            });
            const data = await res.json();
            this.feedbackMsg = data.message;
            this.feedbackSuccess = data.success;
            this.activeRes = null;
            await this.loadMonitor();
        },

        async deleteReservationRecord(reservationId) {
            const res = await fetch('<?= BASE_URL ?>/api/rooms/checkin_group.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'delete_reservation',
                    reservation_id: reservationId
                })
            });
            const data = await res.json();
            this.feedbackMsg = data.message;
            this.feedbackSuccess = data.success;
            this.activeRes = null;
            await this.loadMonitor();
        },

        async changeSpaceStatus(spaceId, newStatus) {
            await fetch('<?= BASE_URL ?>/api/rooms/checkin_group.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'toggle_space_status',
                    space_id: spaceId,
                    current_status: newStatus
                })
            });
            await this.loadMonitor();
        }
    };
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>