<?php
// super_admin/index.php
$page_title = 'Super Admin Security & System Console';
require_once __DIR__ . '/../includes/header.php';
require_role(['super_admin']);
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div x-data="superAdminApp()" x-init="loadConsole()" class="space-y-6">

    <!-- Top Banner -->
    <div class="bg-theme-card border border-theme-border border-l-4 border-l-[#D9232A] rounded-2xl p-4 sm:p-6 flex flex-col lg:flex-row lg:items-center justify-between gap-4 shadow-xs">
        <div>
            <span class="px-2.5 py-1 text-[10px] font-extrabold uppercase rounded-full bg-red-500/15 text-red-400">
                SUPER ADMIN SECURITY CONSOLE
            </span>
            <h1 class="text-xl sm:text-2xl font-extrabold text-theme-text mt-2">
                System Telemetry, Accounts & Disaster Recovery
            </h1>
            <p class="text-xs text-theme-muted mt-0.5">
                Manage Librarian & Student accounts, configure overdue fine policies, inspect audit logs, and export `.sql` backups.
            </p>
        </div>

        <a href="<?= BASE_URL ?>/api/system/backup_db.php"
           class="bg-[#D9232A] hover:bg-red-700 text-white font-extrabold text-xs px-5 py-3 rounded-xl shadow transition shrink-0 text-center">
            ⬇ Download Full .SQL Database Backup
        </a>
    </div>

    <!-- Alert Toast -->
    <div x-show="alertMsg" x-cloak x-transition
         :class="alertOk ? 'bg-emerald-500/15 border-emerald-500/40 text-emerald-400' : 'bg-red-500/15 border-red-500/40 text-red-400'"
         class="p-4 rounded-xl border text-xs font-bold flex items-center justify-between">
        <span x-text="alertMsg"></span>
        <button @click="alertMsg = ''">&times;</button>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex flex-wrap gap-2">
        <button @click="tab = 'logs'"
                :class="tab === 'logs' ? 'bg-theme-accent text-white shadow' : 'bg-theme-card text-theme-muted border border-theme-border'"
                class="px-4 py-2.5 rounded-xl text-xs font-extrabold transition">
            🛡️ Security Audit Trail
        </button>
        <button @click="tab = 'accounts'"
                :class="tab === 'accounts' ? 'bg-theme-accent text-white shadow' : 'bg-theme-card text-theme-muted border border-theme-border'"
                class="px-4 py-2.5 rounded-xl text-xs font-extrabold transition">
            👥 Staff & Student Accounts (<span x-text="users.length"></span>)
        </button>
        <button @click="tab = 'settings'"
                :class="tab === 'settings' ? 'bg-theme-accent text-white shadow' : 'bg-theme-card text-theme-muted border border-theme-border'"
                class="px-4 py-2.5 rounded-xl text-xs font-extrabold transition">
            ⚙️ Fine & Circulation Policy
        </button>
    </div>

    <!-- TAB 1: IMMUTABLE SECURITY AUDIT LOGS -->
    <div x-show="tab === 'logs'" class="bg-theme-card border border-theme-border rounded-2xl overflow-hidden shadow-xs">
        <div class="p-4 sm:p-5 border-b border-theme-border flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <h2 class="text-base font-extrabold text-theme-text">Immutable Security Audit Trail (`audit_logs`)</h2>
            <input type="text" x-model="logFilter" placeholder="Filter by event, actor, or description..."
                   class="px-3.5 py-2 rounded-xl bg-theme-main border border-theme-border text-xs text-theme-text outline-none w-full sm:w-64">
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs min-w-[640px]">
                <thead>
                    <tr class="bg-theme-main text-theme-muted uppercase border-b border-theme-border text-[10px]">
                        <th class="py-3 px-5">Timestamp</th>
                        <th class="py-3 px-5">Actor</th>
                        <th class="py-3 px-5">Event Type</th>
                        <th class="py-3 px-5">Description</th>
                        <th class="py-3 px-5">IP Address</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-theme-border">
                    <template x-for="log in filteredLogs" :key="log.id">
                        <tr class="hover:bg-theme-hover/40 transition">
                            <td class="py-3 px-5 font-mono text-theme-muted" x-text="log.created_at"></td>
                            <td class="py-3 px-5 font-bold text-theme-text" x-text="log.school_id || 'SYSTEM'"></td>
                            <td class="py-3 px-5">
                                <span class="px-2 py-0.5 rounded bg-theme-accent/15 text-theme-accent font-mono font-bold" x-text="log.event_type"></span>
                            </td>
                            <td class="py-3 px-5 text-theme-text" x-text="log.event_description"></td>
                            <td class="py-3 px-5 font-mono text-theme-muted" x-text="log.ip_address"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB 2: STAFF & STUDENT ACCOUNTS MANAGEMENT -->
    <div x-show="tab === 'accounts'" x-cloak class="grid grid-cols-1 xl:grid-cols-12 gap-6">
        <!-- Create Account Form -->
        <div class="xl:col-span-4 bg-theme-card border border-theme-border rounded-2xl p-5 h-fit space-y-4">
            <h3 class="text-base font-extrabold text-theme-text">Create Staff / Student Account</h3>
            <form @submit.prevent="createUser()" class="space-y-3 text-xs">
                <div>
                    <label class="block font-bold text-theme-muted uppercase mb-1">Role</label>
                    <select x-model="newUser.role" class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border font-bold text-theme-text">
                        <option value="librarian">Librarian Staff (e.g. LIB-002)</option>
                        <option value="student">BCP Student (9-Digit ID)</option>
                        <option value="faculty">Faculty Member</option>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-theme-muted uppercase mb-1">School / Staff ID</label>
                    <input type="text" x-model="newUser.school_id" required
                           :placeholder="newUser.role === 'student' ? '9-digit ID (e.g. 240103543)' : 'e.g. LIB-002'"
                           class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border font-mono text-theme-text">
                </div>
                <div>
                    <label class="block font-bold text-theme-muted uppercase mb-1">Full Name</label>
                    <input type="text" x-model="newUser.full_name" required placeholder="Full Name"
                           class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border text-theme-text">
                </div>
                <div>
                    <label class="block font-bold text-theme-muted uppercase mb-1">Email</label>
                    <input type="email" x-model="newUser.email" required placeholder="user@bcp.edu.ph"
                           class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border text-theme-text">
                </div>
                <div x-show="newUser.role === 'student'">
                    <label class="block font-bold text-theme-muted uppercase mb-1">Program Code</label>
                    <input type="text" x-model="newUser.program_code" placeholder="BSIT"
                           class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border font-mono text-theme-text">
                </div>
                <div>
                    <label class="block font-bold text-theme-muted uppercase mb-1">Initial Password</label>
                    <input type="text" x-model="newUser.password" required
                           class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border font-mono text-theme-text">
                </div>
                <button type="submit" class="w-full py-3 rounded-xl bg-theme-accent text-white font-extrabold shadow">
                    + Provision Account
                </button>
            </form>
        </div>

        <!-- Accounts List -->
        <div class="xl:col-span-8 bg-theme-card border border-theme-border rounded-2xl overflow-hidden">
            <div class="p-4 border-b border-theme-border">
                <h3 class="text-base font-extrabold text-theme-text">Registered Accounts</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs min-w-[540px]">
                    <thead>
                        <tr class="bg-theme-main text-theme-muted uppercase text-[10px] border-b border-theme-border">
                            <th class="py-3 px-4">ID & Name</th>
                            <th class="py-3 px-4">Role</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-theme-border">
                        <template x-for="u in users" :key="u.id">
                            <tr>
                                <td class="py-3 px-4">
                                    <p class="font-bold text-theme-text" x-text="u.full_name"></p>
                                    <p class="font-mono text-[11px] text-theme-accent" x-text="'#' + u.school_id + ' • ' + u.email"></p>
                                </td>
                                <td class="py-3 px-4 uppercase font-mono font-bold text-theme-muted" x-text="u.role"></td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase"
                                          :class="u.status === 'active' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400'"
                                          x-text="u.status"></span>
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <button x-show="u.role !== 'super_admin'"
                                            @click="toggleUserStatus(u.id)"
                                            class="px-3 py-1.5 rounded-lg bg-theme-main hover:bg-theme-hover border border-theme-border text-[11px] font-bold text-theme-text"
                                            x-text="u.status === 'active' ? 'Suspend' : 'Activate'"></button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 3: GLOBAL FINE & CIRCULATION POLICY -->
    <div x-show="tab === 'settings'" x-cloak class="max-w-lg bg-theme-card border border-theme-border rounded-2xl p-6 space-y-4">
        <h3 class="text-base font-extrabold text-theme-text">Circulation & Overdue Fine Policy</h3>
        <form @submit.prevent="saveSettings()" class="space-y-4 text-xs">
            <div>
                <label class="block font-bold text-theme-muted uppercase mb-1">Daily Overdue Fine Rate (₱ PHP / Day)</label>
                <input type="number" step="0.50" x-model="settings.daily_fine_rate" required
                       class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border font-mono text-sm text-theme-text">
            </div>
            <div>
                <label class="block font-bold text-theme-muted uppercase mb-1">Default Book Loan Duration (Days)</label>
                <input type="number" x-model="settings.loan_duration_days" required min="1" max="30"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border font-mono text-sm text-theme-text">
            </div>
            <button type="submit" class="px-5 py-2.5 rounded-xl bg-theme-accent text-white font-extrabold shadow">
                Save Policy Settings
            </button>
        </form>
    </div>

</div>

<script>
function superAdminApp() {
    return {
        tab: 'logs',
        users: [],
        logs: [],
        logFilter: '',
        settings: { daily_fine_rate: '10.00', loan_duration_days: '3' },
        newUser: { role: 'librarian', school_id: '', full_name: '', email: '', program_code: 'BSIT', password: 'password' },
        alertMsg: '',
        alertOk: true,

        get filteredLogs() {
            const q = this.logFilter.toLowerCase().trim();
            if (!q) return this.logs;
            return this.logs.filter(l =>
                (l.event_type || '').toLowerCase().includes(q) ||
                (l.school_id || '').toLowerCase().includes(q) ||
                (l.event_description || '').toLowerCase().includes(q)
            );
        },

        async loadConsole() {
            const res = await fetch('<?= BASE_URL ?>/api/system/manage.php?action=init');
            const data = await res.json();
            if (data.success) {
                this.users = data.users;
                this.settings = data.settings;
                this.logs = data.logs;
            }
        },

        async createUser() {
            const res = await fetch('<?= BASE_URL ?>/api/system/manage.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'create_user', ...this.newUser })
            });
            const data = await res.json();
            this.alertMsg = data.message;
            this.alertOk = data.success;
            if (data.success) {
                this.newUser.school_id = '';
                this.newUser.full_name = '';
                this.newUser.email = '';
                await this.loadConsole();
            }
        },

        async toggleUserStatus(userId) {
            const res = await fetch('<?= BASE_URL ?>/api/system/manage.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'toggle_user_status', user_id: userId })
            });
            const data = await res.json();
            this.alertMsg = data.message;
            this.alertOk = data.success;
            await this.loadConsole();
        },

        async saveSettings() {
            const res = await fetch('<?= BASE_URL ?>/api/system/manage.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'save_settings', ...this.settings })
            });
            const data = await res.json();
            this.alertMsg = data.message;
            this.alertOk = data.success;
            await this.loadConsole();
        }
    };
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>