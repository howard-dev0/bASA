<?php
// student/room_reservation.php
$page_title = 'Discussion Rooms A–G Reservation';
require_once __DIR__ . '/../includes/header.php';
require_role(['student', 'faculty']);

$pdo =$GLOBALS['pdo'];
$dev_mode = is_callable('is_dev_mode') ? is_dev_mode($pdo) : true;

// Fetch sample student IDs for the Defense Simulator when Global Dev Mode is ON
$sample_companions = [];
if ($dev_mode) {
    $stmt_comp =$pdo->prepare("
        SELECT school_id, full_name, program_code
        FROM users
        WHERE role IN ('student', 'faculty') AND status = 'active' AND id != ?
        ORDER BY id ASC
        LIMIT 7
    ");
    $stmt_comp->execute([(int)$_SESSION['user_id']]);
    $sample_companions =$stmt_comp->fetchAll();
}

require_once __DIR__ . '/../includes/sidebar.php';
?>

<div x-data="roomReservationApp()" x-init="initPage()"
     @keydown.escape.window="feedbackMsg = ''"
     @keydown.enter.window="if (feedbackMsg) { $event.preventDefault();$event.stopPropagation(); feedbackMsg = ''; }"
     class="space-y-6">

    <!-- CENTERED SCREEN RESPONSE MODAL -->
    <div x-show="feedbackMsg" x-cloak
         class="fixed inset-0 z-[120] flex items-center justify-center bg-black/70 backdrop-blur-xs p-4"
         x-transition>
        <div @click.away="feedbackMsg = ''"
             :class="feedbackSuccess ? 'border-emerald-500' : 'border-[#D9232A]'"
             class="w-full max-w-md bg-theme-card border-2 rounded-2xl shadow-2xl p-6 text-center space-y-4">
            <div :class="feedbackSuccess ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/40' : 'bg-red-500/20 text-red-400 border-red-500/40'"
                 class="w-14 h-14 rounded-2xl border mx-auto flex items-center justify-center text-2xl font-extrabold shadow-inner">
                <span x-text="feedbackSuccess ? '✓' : '⚠️'"></span>
            </div>
            <div>
                <h3 class="text-base font-extrabold"
                    :class="feedbackSuccess ? 'text-emerald-400' : 'text-red-400'"
                    x-text="feedbackSuccess ? 'Reservation Updated' : 'Policy / Booking Notice'"></h3>
                <p class="text-xs sm:text-sm text-theme-text font-semibold mt-1.5 leading-relaxed" x-text="feedbackMsg"></p>
            </div>
            <button type="button" x-ref="feedbackOkBtn" @click="feedbackMsg = ''"
                    :class="feedbackSuccess ? 'bg-emerald-500 hover:bg-emerald-400 text-slate-950' : 'bg-[#D9232A] hover:bg-red-700 text-white'"
                    class="w-full py-2.5 px-5 rounded-xl font-extrabold text-xs shadow-lg transition outline-none">
                OK, Understood
            </button>
        </div>
    </div>

    <!-- Top Header & Interactive Map Toggle -->
    <div class="bg-theme-card border border-theme-border border-l-4 border-l-emerald-500 rounded-2xl p-4 sm:p-6 flex flex-col lg:flex-row lg:items-center justify-between gap-4 shadow-xs">
        <div>
            <span class="px-2.5 py-1 text-[10px] font-extrabold uppercase rounded-full bg-emerald-500/15 text-emerald-400">
                4th Floor East Wing • Discussion Rooms A to G
            </span>
            <h1 class="text-xl sm:text-2xl font-extrabold text-theme-text mt-2">
                Discussion Room Group Reservation
            </h1>
            <p class="text-xs text-theme-muted mt-0.5">
                Reserve a collaborative study room for <strong>6 to 8 persons</strong> and tag your groupmates using their 9-digit BCP Student IDs.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5 shrink-0">
            <button type="button" @click="showFloorMap = !showFloorMap"
                    :class="showFloorMap ? 'bg-amber-500 text-slate-950' : 'bg-theme-main text-theme-text border border-theme-border'"
                    class="px-4 py-2.5 rounded-xl text-xs font-extrabold transition flex items-center gap-2 shadow-xs">
                <span>🗺️</span>
                <span x-text="showFloorMap ? 'Hide 4F Floor Map' : 'View Rooms A–G on 4F Map'"></span>
            </button>
        </div>
    </div>

    <!-- 4-Step Institutional Policy Strip -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
        <div class="bg-theme-card border border-theme-border rounded-2xl p-4 shadow-xs">
            <span class="px-2 py-0.5 rounded bg-theme-accent/15 text-theme-accent font-mono text-[10px] font-extrabold uppercase">Rule 01 • Capacity</span>
            <p class="text-sm font-extrabold text-theme-text mt-1.5">6 to 8 Persons Required</p>
            <p class="text-[11px] text-theme-muted mt-0.5">1 Representative (You) + 5 to 7 tagged 9-digit Student IDs.</p>
        </div>
        <div class="bg-theme-card border border-theme-border rounded-2xl p-4 shadow-xs">
            <span class="px-2 py-0.5 rounded bg-emerald-500/15 text-emerald-400 font-mono text-[10px] font-extrabold uppercase">Rule 02 • Window</span>
            <p class="text-sm font-extrabold text-theme-text mt-1.5">Today or 1 Day Ahead</p>
            <p class="text-[11px] text-theme-muted mt-0.5">Reservations can only be scheduled for Today or Tomorrow.</p>
        </div>
        <div class="bg-theme-card border border-theme-border rounded-2xl p-4 shadow-xs">
            <span class="px-2 py-0.5 rounded bg-amber-500/15 text-amber-400 font-mono text-[10px] font-extrabold uppercase">Rule 03 • Daily Quota</span>
            <p class="text-sm font-extrabold text-theme-text mt-1.5">1 Booking / Rep / Day</p>
            <p class="text-[11px] text-theme-muted mt-0.5">A representative can book 1 two-hour slot per day.</p>
        </div>
        <div class="bg-theme-card border border-theme-border rounded-2xl p-4 shadow-xs">
            <span class="px-2 py-0.5 rounded bg-purple-500/15 text-purple-400 font-mono text-[10px] font-extrabold uppercase">Rule 04 • Unlocking</span>
            <p class="text-sm font-extrabold text-theme-text mt-1.5">Min. 6 Present at Desk</p>
            <p class="text-[11px] text-theme-muted mt-0.5">At least 6 tagged members must check in physically at the Help Desk.</p>
        </div>
    </div>

    <!-- Collapsible 4th Floor Interactive Map -->
    <div x-show="showFloorMap" x-cloak x-transition class="bg-theme-card border border-theme-border rounded-2xl p-4 sm:p-5 shadow-xs">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3">
            <div>
                <h2 class="text-sm sm:text-base font-extrabold text-theme-text">4th Floor East Wing — Discussion Rooms A to G</h2>
                <p class="text-xs text-theme-muted">Click any Discussion Room (<span class="font-mono text-emerald-400 font-bold">Room A – Room G</span>) on the right wall of the map to select it.</p>
            </div>
        </div>
        <?php include __DIR__ . '/../includes/map_svg.php'; ?>
    </div>

    <!-- Main 12-Column Reservation Workspace -->
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">

        <!-- LEFT 7 COLUMNS: INTERACTIVE ROOM & GROUP ROSTER BUILDER -->
        <div class="xl:col-span-7 bg-theme-card border border-theme-border rounded-2xl p-5 sm:p-6 shadow-xs space-y-6">

            <!-- Step 1: Date Selection Cards -->
            <div>
                <label class="block text-xs font-extrabold uppercase tracking-wider text-theme-muted mb-2">
                    1. Select Reservation Date (Today or 1 Day in Advance)
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <button type="button"
                            @click="selectedDate = today; fetchInit()"
                            :class="selectedDate === today ? 'border-theme-accent bg-theme-accent/15 ring-2 ring-theme-accent' : 'border-theme-border bg-theme-main hover:bg-theme-hover'"
                            class="p-3.5 rounded-xl border text-left transition flex items-center justify-between">
                        <div>
                            <p class="text-xs font-extrabold text-theme-text">Today's Schedule</p>
                            <p class="text-[11px] font-mono text-theme-muted mt-0.5" x-text="today"></p>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase bg-emerald-500/20 text-emerald-400"> Same-Day </span>
                    </button>

                    <button type="button"
                            @click="selectedDate = tomorrow; fetchInit()"
                            :class="selectedDate === tomorrow ? 'border-theme-accent bg-theme-accent/15 ring-2 ring-theme-accent' : 'border-theme-border bg-theme-main hover:bg-theme-hover'"
                            class="p-3.5 rounded-xl border text-left transition flex items-center justify-between">
                        <div>
                            <p class="text-xs font-extrabold text-theme-text">Tomorrow (1 Day Advance)</p>
                            <p class="text-[11px] font-mono text-theme-muted mt-0.5" x-text="tomorrow"></p>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase bg-sky-500/20 text-sky-400"> +1 Day </span>
                    </button>
                </div>
            </div>

            <!-- Step 2: Interactive Discussion Rooms A–G Grid -->
            <div>
                <label class="block text-xs font-extrabold uppercase tracking-wider text-theme-muted mb-2">
                    2. Choose Discussion Room (Rooms A–G • 6 to 8 Pax)
                </label>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                    <template x-for="room in rooms" :key="room.id">
                        <button type="button"
                                :disabled="room.current_status === 'maintenance'"
                                @click="selectedSpaceId = room.id"
                                :class="selectedSpaceId == room.id
                                    ? 'border-theme-accent bg-theme-accent/15 ring-2 ring-theme-accent'
                                    : 'border-theme-border bg-theme-main hover:bg-theme-hover disabled:opacity-40'"
                                class="p-3 rounded-xl border text-left transition flex flex-col justify-between">
                            <div class="flex items-center justify-between w-full">
                                <span class="text-xs font-extrabold text-theme-text" x-text="room.space_name"></span>
                                <span class="w-2.5 h-2.5 rounded-full shrink-0"
                                      :class="{
                                          'bg-emerald-500': room.current_status === 'available',
                                          'bg-amber-500': room.current_status === 'reserved',
                                          'bg-red-500': room.current_status === 'occupied',
                                          'bg-slate-500': room.current_status === 'maintenance'
                                      }"></span>
                            </div>
                            <div class="mt-2 flex items-center justify-between text-[10px]">
                                <span class="font-mono text-theme-muted" x-text="room.min_capacity + '–' + room.max_capacity + ' Pax'"></span>
                                <span class="uppercase font-extrabold"
                                      :class="{
                                          'text-emerald-400': room.current_status === 'available',
                                          'text-amber-400': room.current_status === 'reserved',
                                          'text-red-400': room.current_status === 'occupied',
                                          'text-slate-400': room.current_status === 'maintenance'
                                      }"
                                      x-text="room.current_status"></span>
                            </div>
                        </button>
                    </template>
                </div>
            </div>

            <!-- Step 3: Interactive 2-Hour Time Slot Buttons -->
            <div>
                <label class="block text-xs font-extrabold uppercase tracking-wider text-theme-muted mb-2">
                    3. Select 2-Hour Time Slot
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2.5">
                    <template x-for="slot in timeSlots" :key="slot.value">
                        <button type="button"
                                :disabled="isSlotTaken(slot.start)"
                                @click="selectedTimeSlot = slot.value"
                                :class="isSlotTaken(slot.start)
                                    ? 'border-red-500/30 bg-red-500/10 text-red-400 opacity-60 cursor-not-allowed'
                                    : (selectedTimeSlot === slot.value
                                        ? 'border-theme-accent bg-theme-accent text-white shadow-md'
                                        : 'border-theme-border bg-theme-main text-theme-text hover:bg-theme-hover')"
                                class="p-3 rounded-xl border text-xs font-mono font-extrabold transition flex items-center justify-between">
                            <span x-text="slot.label"></span>
                            <span class="text-[10px] uppercase px-2 py-0.5 rounded"
                                  :class="isSlotTaken(slot.start) ? 'bg-red-500/20 text-red-300' : (selectedTimeSlot === slot.value ? 'bg-white/20 text-white' : 'bg-emerald-500/15 text-emerald-400')"
                                  x-text="isSlotTaken(slot.start) ? 'Booked' : 'Open'"></span>
                        </button>
                    </template>
                </div>
            </div>

            <!-- Step 4: Research / Study Purpose -->
            <div>
                <label class="block text-xs font-extrabold uppercase tracking-wider text-theme-muted mb-1.5">
                    4. Group Research / Discussion Purpose
                </label>
                <input type="text" x-model="purpose"
                       placeholder="e.g. BSIT Capstone Manuscript Review & System Architecture Defense Prep"
                       class="w-full px-4 py-2.5 rounded-xl bg-theme-main border border-theme-border text-xs text-theme-text focus:border-theme-accent outline-none">
            </div>

            <!-- Step 5: Tag 5 to 7 Group Companions (9-Digit Student IDs) -->
            <div class="pt-4 border-t border-theme-border space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                        <label class="block text-xs font-extrabold uppercase tracking-wider text-theme-text">
                            5. Tag Group Companions (5 to 7 BCP Student IDs)
                        </label>
                        <p class="text-[11px] text-theme-muted">Total group size must be 6 to 8 persons (including yourself as Representative).</p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-xs font-mono font-extrabold shrink-0"
                          :class="totalGroupSize >= 6 && totalGroupSize <= 8 ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/40' : 'bg-amber-500/20 text-amber-400 border border-amber-500/40'"
                          x-text="totalGroupSize + ' / 8 Pax (' + (totalGroupSize >= 6 ? 'Ready' : 'Need ' + (6 - totalGroupSize) + ' more') + ')'"></span>
                </div>

                <form @submit.prevent="lookupAndAddMember()" class="flex gap-2">
                    <input type="text" x-model="memberIdInput" maxlength="9"
                           placeholder="Enter 9-digit Student ID (e.g. 240103538)"
                           class="flex-1 px-4 py-2.5 rounded-xl bg-theme-main border border-theme-border text-xs font-mono text-theme-text focus:border-theme-accent outline-none">
                    <button type="submit"
                            :disabled="members.length >= 7"
                            class="px-4 py-2.5 rounded-xl bg-theme-accent hover:bg-theme-accentHover disabled:opacity-40 text-white text-xs font-extrabold shadow transition shrink-0">
                        + Verify & Tag ID
                    </button>
                </form>

                <?php if ($dev_mode): ?>
                <!-- Dev Mode Only: Capstone Defense Single-PC Simulator -->
                <div class="p-3.5 rounded-xl bg-theme-main border border-theme-border space-y-2">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-[10px] font-extrabold uppercase tracking-wider text-theme-muted">
                            ⚡ Capstone Defense Single-PC Scan Simulator
                        </p>
                        <button type="button" @click="quickFillCompanions()"
                                class="px-2.5 py-1 rounded-lg bg-emerald-500/20 text-emerald-400 hover:bg-emerald-500 hover:text-slate-950 text-[10px] font-extrabold transition">
                            ⚡ Auto-Fill 5 Groupmates
                        </button>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <?php foreach ($sample_companions as$sc): ?>
                            <button type="button" @click="addSampleId('<?= e($sc['school_id']) ?>')"
                                    class="px-2.5 py-1 rounded-lg bg-theme-card hover:bg-theme-hover border border-theme-border text-[11px] font-mono font-bold text-sky-400 transition">
                                + #<?= e($sc['school_id']) ?> (<?= e($sc['full_name']) ?>)
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Tagged Group Roster List -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 pt-1">
                    <!-- Representative Card (#1) -->
                    <div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/40 flex items-center justify-between text-xs">
                        <div class="min-w-0">
                            <p class="text-[10px] font-extrabold uppercase text-emerald-400">1. Representative (You)</p>
                            <p class="font-extrabold text-theme-text truncate mt-0.5"><?= e($_SESSION['full_name']) ?></p>
                            <p class="font-mono text-[11px] text-theme-muted">#<?= e($_SESSION['school_id']) ?> • <?= e($_SESSION['program_code'] ?? 'BCP') ?></p>
                        </div>
                        <span class="px-2 py-0.5 rounded bg-emerald-500 text-slate-950 text-[10px] font-extrabold shrink-0">REP</span>
                    </div>

                    <!-- Tagged Companions (#2 to #8) -->
                    <template x-for="(mem, idx) in members" :key="mem.school_id">
                        <div class="p-3 rounded-xl bg-theme-main border border-theme-border flex items-center justify-between gap-2 text-xs">
                            <div class="min-w-0">
                                <p class="text-[10px] font-extrabold uppercase text-theme-accent" x-text="(idx + 2) + '. Tagged Companion'"></p>
                                <p class="font-extrabold text-theme-text truncate mt-0.5" x-text="mem.full_name"></p>
                                <p class="font-mono text-[11px] text-theme-muted" x-text="'#' + mem.school_id + ' • ' + (mem.program_code || 'BCP')"></p>
                            </div>
                            <button type="button" @click="removeMember(mem.school_id)"
                                    class="px-2.5 py-1 rounded-lg bg-red-500/15 text-red-400 hover:bg-red-500 hover:text-white text-[10px] font-extrabold shrink-0 transition">
                                Remove
                            </button>
                        </div>
                    </template>
                </div>
            </div>

            <button type="button"
                    @click="submitReservation()"
                    :disabled="totalGroupSize < 6 || totalGroupSize > 8 || isSlotTaken(selectedTimeSlot.split('|')[0])"
                    class="w-full py-3.5 px-5 rounded-xl bg-emerald-500 hover:bg-emerald-400 disabled:opacity-40 text-slate-950 font-extrabold text-xs shadow-lg transition">
                ✓ Submit Discussion Room Reservation (<span x-text="totalGroupSize"></span> Members)
            </button>
        </div>

        <!-- RIGHT 5 COLUMNS: MY GROUP RESERVATIONS & CHECK-IN TRACKER -->
        <div class="xl:col-span-5 space-y-6">
            <div class="bg-theme-card border border-theme-border rounded-2xl p-5 sm:p-6 shadow-xs space-y-4">
                <div class="pb-3 border-b border-theme-border flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-extrabold text-theme-text">My Group Reservations</h2>
                        <p class="text-xs text-theme-muted">Bookings where you are the Representative or a Tagged Member</p>
                    </div>
                </div>

                <template x-if="myBookings.length === 0">
                    <div class="p-8 text-center text-xs text-theme-muted border border-dashed border-theme-border rounded-xl">
                        You have no active or past Discussion Room reservations yet.
                    </div>
                </template>

                <div class="space-y-4">
                    <template x-for="b in myBookings" :key="b.id">
                        <div class="p-4 rounded-2xl bg-theme-main border border-theme-border space-y-3 text-xs">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <span class="px-2.5 py-1 rounded-lg bg-theme-accent/15 text-theme-accent font-extrabold" x-text="b.space_name"></span>
                                    <p class="font-mono text-[11px] text-theme-muted mt-1.5"
                                       x-text="b.reservation_date + ' • ' + b.start_time.substring(0,5) + ' – ' + b.end_time.substring(0,5)"></p>
                                </div>
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase shrink-0"
                                      :class="{
                                          'bg-amber-500/20 text-amber-400': b.status === 'pending',
                                          'bg-emerald-500/20 text-emerald-400': b.status === 'occupied',
                                          'bg-sky-500/20 text-sky-400': b.status === 'completed',
                                          'bg-red-500/20 text-red-400': ['cancelled','no_show','violation'].includes(b.status)
                                      }"
                                      x-text="b.status"></span>
                            </div>

                            <div>
                                <p class="font-extrabold text-theme-text" x-text="b.purpose"></p>
                                <p class="text-[11px] text-theme-muted mt-0.5">
                                    Rep: <strong class="text-theme-text" x-text="b.rep_name"></strong> (#<span x-text="b.rep_school_id"></span>)
                                    • Desk Headcount: <strong class="font-mono text-emerald-400" x-text="b.present_headcount + ' / ' + b.members.length + ' Present'"></strong>
                                </p>
                            </div>

                            <!-- Tagged Group Roster Pills -->
                            <div class="flex flex-wrap gap-1.5 pt-1">
                                <template x-for="m in b.members" :key="m.school_id">
                                    <span class="px-2 py-0.5 rounded-lg border text-[10px] font-mono"
                                          :class="m.is_present == 1 ? 'bg-emerald-500/15 border-emerald-500/40 text-emerald-400 font-bold' : 'bg-theme-card border-theme-border text-theme-muted'">
                                        <span x-text="m.is_present == 1 ? '✓ ' : ''"></span>
                                        <span x-text="m.full_name + ' (#' + m.school_id + ')'"></span>
                                    </span>
                                </template>
                            </div>

                            <!-- Action Buttons -->
                            <div class="pt-3 border-t border-theme-border flex flex-wrap items-center justify-end gap-2">
                                <button x-show="b.status === 'pending'"
                                        type="button"
                                        @click="cancelBooking(b.id)"
                                        class="px-3 py-1.5 rounded-xl bg-red-500/15 text-red-400 hover:bg-red-500 hover:text-white font-extrabold text-[11px] transition">
                                    ✕ Cancel Pending
                                </button>

                                <button x-show="b.status === 'pending' || b.status === 'occupied'"
                                        type="button"
                                        @click="completeBooking(b.id)"
                                        class="px-3 py-1.5 rounded-xl bg-emerald-500/20 text-emerald-400 hover:bg-emerald-500 hover:text-slate-950 font-extrabold text-[11px] transition">
                                    ✓ Mark Completed
                                </button>

                                <button x-show="['completed', 'cancelled', 'no_show', 'violation'].includes(b.status)"
                                        type="button"
                                        @click="removeBooking(b.id)"
                                        class="px-3 py-1.5 rounded-xl bg-red-500/20 text-red-400 hover:bg-red-500 hover:text-white font-extrabold text-[11px] transition">
                                    🗑️ Remove Completed
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
function roomReservationApp() {
    const SAMPLE_IDS = <?= json_encode(array_column($sample_companions, 'school_id')) ?>;

    return {
        showFloorMap: false,
        today: '<?= date('Y-m-d') ?>',
        tomorrow: '<?= date('Y-m-d', strtotime('+1 day')) ?>',
        selectedDate: '<?= date('Y-m-d') ?>',
        selectedSpaceId: 1,
        selectedTimeSlot: '08:00:00|10:00:00',
        purpose: '',
        memberIdInput: '',
        members: [],
        rooms: [],
        spaces: [],
        takenSlots: [],
        myBookings: [],
        feedbackMsg: '',
        feedbackSuccess: true,

        timeSlots: [
            { value: '08:00:00|10:00:00', start: '08:00:00', label: '08:00 AM – 10:00 AM' },
            { value: '10:00:00|12:00:00', start: '10:00:00', label: '10:00 AM – 12:00 PM' },
            { value: '12:00:00|14:00:00', start: '12:00:00', label: '12:00 PM – 02:00 PM' },
            { value: '14:00:00|16:00:00', start: '14:00:00', label: '02:00 PM – 04:00 PM' },
            { value: '16:00:00|18:00:00', start: '16:00:00', label: '04:00 PM – 06:00 PM' }
        ],

        get totalGroupSize() {
            return this.members.length + 1;
        },

        showToast(msg, ok = true) {
            if (document.activeElement) document.activeElement.blur();
            this.feedbackMsg = msg;
            this.feedbackSuccess = ok;
            this.$nextTick(() => {
                if (this.$refs.feedbackOkBtn) this.$refs.feedbackOkBtn.focus();
            });
        },

        async initPage() {
            await this.fetchInit();
            await this.fetchMapSpaces();
        },

        async fetchInit() {
            const res = await fetch(`<?= BASE_URL ?>/api/rooms/book_room.php?action=init&date=${this.selectedDate}`);
            const data = await res.json();
            if (data.success) {
                this.today = data.today;
                this.tomorrow = data.tomorrow;
                this.rooms = data.rooms;
                this.takenSlots = data.taken_slots;
                this.myBookings = data.my_bookings;
                if (this.rooms.length > 0 && !this.rooms.some(r => r.id == this.selectedSpaceId)) {
                    this.selectedSpaceId = this.rooms[0].id;
                }
            }
        },

        async fetchMapSpaces() {
            const res = await fetch('<?= BASE_URL ?>/api/rooms/book_room.php?action=map_status');
            const data = await res.json();
            if (data.success) {
                this.spaces = data.spaces;
            }
        },

        getSpaceColor(code, type, defaultColor) {
            const sp = this.spaces.find(s => s.space_code === code);
            if (!sp) return defaultColor;
            if (sp.current_status === 'available') return type === 'bg' ? '#064e3b' : '#10b981';
            if (sp.current_status === 'occupied') return type === 'bg' ? '#450a0a' : '#ef4444';
            if (sp.current_status === 'reserved') return type === 'bg' ? '#422006' : '#f59e0b';
            if (sp.current_status === 'maintenance') return type === 'bg' ? '#1e293b' : '#64748b';
            return defaultColor;
        },

        showSpaceDetails(code) {
            const rm = this.rooms.find(r => r.space_code === code);
            if (rm) {
                this.selectedSpaceId = rm.id;
                this.showToast(`Selected ${rm.space_name} (${rm.min_capacity}–${rm.max_capacity} Pax).`, true);
            }
        },

        isShelfHighlighted() { return false; },
        selectShelfFromMap() {},

        isSlotTaken(startTime) {
            return this.takenSlots.some(
                t => t.space_id == this.selectedSpaceId && t.start_time === startTime
            );
        },

        async addSampleId(schoolId) {
            this.memberIdInput = schoolId;
            await this.lookupAndAddMember();
        },

        async quickFillCompanions() {
            for (const sid of SAMPLE_IDS) {
                if (this.members.length >= 5) break;
                if (!this.members.some(m => m.school_id === sid)) {
                    this.memberIdInput = sid;
                    await this.lookupAndAddMember();
                }
            }
            if (!this.purpose) {
                this.purpose = 'BSIT Capstone Manuscript & System Architecture Review';
            }
        },

        async lookupAndAddMember() {
            const id = String(this.memberIdInput || '').trim();
            if (!id) return;

            if (this.members.length >= 7) {
                this.showToast('Maximum capacity reached (8 persons total including Representative).', false);
                return;
            }

            if (this.members.some(m => m.school_id === id)) {
                this.showToast(`Student ID #${id} is already tagged in your group.`, false);
                return;
            }

            const res = await fetch('<?= BASE_URL ?>/api/rooms/book_room.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'lookup_member', school_id: id })
            });
            const data = await res.json();
            if (data.success) {
                this.members.push(data.member);
                this.memberIdInput = '';
            } else {
                this.showToast(data.message, false);
            }
        },

        removeMember(schoolId) {
            this.members = this.members.filter(m => m.school_id !== schoolId);
        },

        async submitReservation() {
            if (this.feedbackMsg) return;
            const res = await fetch('<?= BASE_URL ?>/api/rooms/book_room.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'create_booking',
                    space_id: this.selectedSpaceId,
                    reservation_date: this.selectedDate,
                    time_slot: this.selectedTimeSlot,
                    purpose: this.purpose,
                    member_school_ids: this.members.map(m => m.school_id)
                })
            });
            const data = await res.json();
            this.showToast(data.message, data.success);
            if (data.success) {
                this.purpose = '';
                this.members = [];
                await this.fetchInit();
                await this.fetchMapSpaces();
            }
        },

        async cancelBooking(id) {
            const res = await fetch('<?= BASE_URL ?>/api/rooms/book_room.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'cancel_booking', reservation_id: id })
            });
            const data = await res.json();
            this.showToast(data.message, data.success);
            if (data.success) {
                await this.fetchInit();
                await this.fetchMapSpaces();
            }
        },

        async completeBooking(id) {
            const res = await fetch('<?= BASE_URL ?>/api/rooms/book_room.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'complete_booking', reservation_id: id })
            });
            const data = await res.json();
            this.showToast(data.message, data.success);
            if (data.success) {
                await this.fetchInit();
                await this.fetchMapSpaces();
            }
        },

        async removeBooking(id) {
            const res = await fetch('<?= BASE_URL ?>/api/rooms/book_room.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'remove_booking', reservation_id: id })
            });
            const data = await res.json();
            this.showToast(data.message, data.success);
            if (data.success) {
                await this.fetchInit();
                await this.fetchMapSpaces();
            }
        }
    };
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>