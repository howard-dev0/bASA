<?php
// student/map.php
$page_title = 'Live Interactive Library Map';
require_once __DIR__ . '/../includes/header.php';
require_role(['student', 'faculty', 'librarian', 'super_admin']);
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div x-data="liveMapApp()" x-init="initMap()" class="space-y-6">

    <!-- Top Banner -->
    <div class="bg-theme-card border border-theme-border border-l-4 border-l-theme-accent rounded-2xl p-4 sm:p-6 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <span class="px-2.5 py-1 text-[10px] font-extrabold uppercase rounded-full bg-theme-accent/15 text-theme-accent">
                Bestlink 4th Floor Digital Twin
            </span>
            <h1 class="text-xl sm:text-2xl font-extrabold text-theme-text mt-2">
                Live Spatial Availability Map
            </h1>
            <p class="text-xs text-theme-muted mt-0.5">
                The map automatically updates room availability (Green, Amber, Red). Click any room or cubicle to view live occupancy.
            </p>
        </div>
        <button @click="fetchSpaces()" class="px-4 py-2.5 rounded-xl bg-theme-main border border-theme-border text-xs font-extrabold hover:text-theme-accent transition shrink-0">
            ↻ Sync Live Status
        </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- Map Container (Left 8 Cols) -->
        <div class="lg:col-span-8 bg-theme-card border border-theme-border rounded-2xl p-4 shadow-xs h-fit">
            <?php include __DIR__ . '/../includes/map_svg.php'; ?>
        </div>

        <!-- Space Details Side Panel (Right 4 Cols) -->
        <div class="lg:col-span-4 space-y-4">
            <div class="bg-theme-card border border-theme-border rounded-2xl p-5 sm:p-6 shadow-xs h-full min-h-[300px]">
                <div class="border-b border-theme-border pb-3 mb-4 flex items-center justify-between">
                    <h3 class="text-base font-extrabold text-theme-text">Space Occupancy Details</h3>
                </div>

                <template x-if="!selectedSpace">
                    <div class="p-8 text-center text-xs text-theme-muted border border-dashed border-theme-border rounded-xl">
                        Select a Discussion Room or Reading Cubicle on the map to inspect its real-time status.
                    </div>
                </template>

                <template x-if="selectedSpace">
                    <div class="space-y-4 animate-pulse-once">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl font-bold shadow-inner border-2"
                                 :class="selectedSpace.current_status === 'available' ? 'bg-[#064e3b] border-[#10b981] text-emerald-400' : (selectedSpace.current_status === 'occupied' ? 'bg-[#450a0a] border-[#ef4444] text-red-400' : 'bg-[#422006] border-[#f59e0b] text-amber-400')">
                                <span x-text="selectedSpace.space_type === 'reading_cubicle' ? '👤' : '👥'"></span>
                            </div>
                            <div>
                                <h4 class="text-lg font-extrabold text-theme-text" x-text="selectedSpace.space_name"></h4>
                                <p class="text-xs font-mono text-theme-muted mt-0.5" x-text="'Identifier: ' + selectedSpace.space_code"></p>
                            </div>
                        </div>

                        <div class="p-4 rounded-xl border"
                             :class="selectedSpace.current_status === 'available' ? 'bg-emerald-500/10 border-emerald-500/40 text-emerald-500' : (selectedSpace.current_status === 'occupied' ? 'bg-red-500/10 border-red-500/40 text-red-500' : 'bg-amber-500/10 border-amber-500/40 text-amber-500')">
                            <p class="text-[10px] font-extrabold uppercase tracking-wider opacity-80 mb-1">Live State</p>
                            <p class="text-xl font-extrabold uppercase tracking-widest" x-text="selectedSpace.current_status"></p>
                        </div>

                        <div class="grid grid-cols-2 gap-3 text-xs">
                            <div class="p-3 rounded-xl bg-theme-main border border-theme-border">
                                <p class="text-[10px] font-extrabold uppercase tracking-wider text-theme-muted mb-1">Space Type</p>
                                <p class="font-bold text-theme-text capitalize" x-text="selectedSpace.space_type.replace('_', ' ')"></p>
                            </div>
                            <div class="p-3 rounded-xl bg-theme-main border border-theme-border">
                                <p class="text-[10px] font-extrabold uppercase tracking-wider text-theme-muted mb-1">Capacity</p>
                                <p class="font-bold text-theme-text" x-text="selectedSpace.min_capacity + ' to ' + selectedSpace.max_capacity + ' Pax'"></p>
                            </div>
                        </div>

                        <template x-if="selectedSpace.current_status === 'available' && selectedSpace.space_type === 'discussion_room'">
                            <a href="<?= BASE_URL ?>/student/room_reservation.php"
                               class="block w-full text-center px-4 py-3 rounded-xl bg-theme-accent hover:bg-theme-accentHover text-white font-extrabold text-xs shadow-lg transition mt-4">
                                Book This Discussion Room →
                            </a>
                        </template>
                    </div>
                </template>
            </div>
        </div>

    </div>
</div>

<style>
.animate-pulse-once { animation: subtlePulse 0.3s ease-out 1; }
@keyframes subtlePulse {
    0% { transform: scale(0.98); opacity: 0.8; }
    100% { transform: scale(1); opacity: 1; }
}
</style>

<script>
function liveMapApp() {
    return {
        spaces: [],
        selectedSpace: null,

        async initMap() {
            await this.fetchSpaces();
            setInterval(() => this.fetchSpaces(), 20000); // 20s live sync
        },

        async fetchSpaces() {
            const res = await fetch('<?= BASE_URL ?>/api/rooms/book_room.php?action=map_status');
            const data = await res.json();
            if (data.success) {
                this.spaces = data.spaces;
                if (this.selectedSpace) {
                    this.selectedSpace = this.spaces.find(s => s.space_code === this.selectedSpace.space_code) || null;
                }
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
            this.selectedSpace = this.spaces.find(s => s.space_code === code) || null;
        },

        isShelfHighlighted(shelf) { return false; },
        selectShelfFromMap(shelf) {}
    };
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>