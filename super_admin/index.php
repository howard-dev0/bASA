<?php
// super_admin/index.php
$page_title = 'Super Admin Accounts & Security Console';
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
                Registrar Account Management, Audit Trail & SQL Backup
            </h1>
            <p class="text-xs text-theme-muted mt-0.5">
                Only Super Admin can edit official legal names (First, Middle, M.I., Last, Extension) and delete user accounts.
            </p>
        </div>

        <div class="flex flex-wrap gap-2.5 shrink-0">
            <button @click="openCreateModal()"
                    class="px-4 py-2.5 rounded-xl bg-theme-accent hover:bg-theme-accentHover text-white font-extrabold text-xs shadow transition">
                + Provision New Account
            </button>
            <a href="<?= BASE_URL ?>/api/system/backup_db.php"
               class="bg-[#D9232A] hover:bg-red-700 text-white font-extrabold text-xs px-4 py-2.5 rounded-xl shadow transition text-center">
                ⬇ .SQL Backup
            </a>
        </div>
    </div>

    <!-- Alert Toast -->
    <div x-show="alertMsg" x-cloak
         class="fixed inset-0 z-[100] flex items-center justify-center bg-black/70 backdrop-blur-xs p-4"
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
            <button type="button" @click="alertMsg = ''"
                    :class="alertOk ? 'bg-emerald-500 text-slate-950' : 'bg-[#D9232A] text-white'"
                    class="w-full py-2.5 px-5 rounded-xl font-extrabold text-xs shadow-lg transition">
                OK
            </button>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex flex-wrap gap-2">
        <button @click="tab = 'accounts'"
                :class="tab === 'accounts' ? 'bg-theme-accent text-white shadow' : 'bg-theme-card text-theme-muted border border-theme-border'"
                class="px-4 py-2.5 rounded-xl text-xs font-extrabold transition">
            👥 Manage Accounts (<span x-text="users.length"></span>)
        </button>
        <button @click="tab = 'logs'"
                :class="tab === 'logs' ? 'bg-theme-accent text-white shadow' : 'bg-theme-card text-theme-muted border border-theme-border'"
                class="px-4 py-2.5 rounded-xl text-xs font-extrabold transition">
            🛡️ Security Audit Trail
        </button>
        <button @click="tab = 'settings'"
                :class="tab === 'settings' ? 'bg-theme-accent text-white shadow' : 'bg-theme-card text-theme-muted border border-theme-border'"
                class="px-4 py-2.5 rounded-xl text-xs font-extrabold transition">
            ⚙️ Global System Settings
        </button>
    </div>

    <!-- TAB 1: ACCOUNTS TABLE -->
    <div x-show="tab === 'accounts'" class="bg-theme-card border border-theme-border rounded-2xl overflow-hidden shadow-xs">
        <div class="p-4 sm:p-5 border-b border-theme-border flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-extrabold text-theme-text">Registered Student, Faculty & Staff Accounts</h2>
                <p class="text-xs text-theme-muted">Click <strong>✏️ Edit Info</strong> to modify First/Middle/Last/Extension names or <strong>🗑️ Delete</strong> to remove an account.</p>
            </div>
            <input type="text" x-model="userFilter" placeholder="Search ID, name, or program..."
                   class="px-3.5 py-2 rounded-xl bg-theme-main border border-theme-border text-xs text-theme-text outline-none w-full sm:w-64">
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs min-w-[780px]">
                <thead>
                    <tr class="bg-theme-main text-theme-muted uppercase text-[10px] border-b border-theme-border">
                        <th class="py-3.5 px-5">School ID & Role</th>
                        <th class="py-3.5 px-5">Legal Name (First / Middle / Last / Ext)</th>
                        <th class="py-3.5 px-5">Display Name & Contact</th>
                        <th class="py-3.5 px-5">Status</th>
                        <th class="py-3.5 px-5 text-right">Super Admin Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-theme-border">
                    <template x-for="u in filteredUsers" :key="u.id">
                        <tr class="hover:bg-theme-hover/40 transition">
                            <td class="py-3.5 px-5">
                                <p class="font-mono font-extrabold text-theme-accent" x-text="'#' + u.school_id"></p>
                                <span class="text-[10px] uppercase font-bold text-theme-muted" x-text="u.role + (u.program_code ? ' • ' + u.program_code : '')"></span>
                            </td>
                            <td class="py-3.5 px-5">
                                <p class="font-extrabold text-theme-text" x-text="u.full_name"></p>
                                <p class="text-[11px] text-theme-muted font-mono"
                                   x-text="'F: ' + (u.first_name || '-') + ' | M: ' + (u.middle_name || '-') + ' (' + (u.middle_initial || '-') + ') | L: ' + (u.last_name || '-') + (u.suffix ? ' | Ext: ' + u.suffix : '')"></p>
                            </td>
                            <td class="py-3.5 px-5">
                                <p class="font-bold text-theme-text" x-text="u.display_name || '—'"></p>
                                <p class="font-mono text-[11px] text-theme-muted" x-text="(u.contact_no || 'No phone') + ' • ' + u.email"></p>
                            </td>
                            <td class="py-3.5 px-5">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase"
                                      :class="u.status === 'active' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400'"
                                      x-text="u.status"></span>
                            </td>
                            <td class="py-3.5 px-5 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <button type="button" @click="openEditModal(u)"
                                            class="px-3 py-1.5 rounded-xl bg-theme-accent/15 hover:bg-theme-accent text-theme-accent hover:text-white font-extrabold text-xs transition">
                                        ✏️ Edit Info
                                    </button>
                                    <button x-show="u.id != <?= (int)$_SESSION['user_id'] ?>"
                                            type="button" @click="deleteUser(u)"
                                            class="px-3 py-1.5 rounded-xl bg-red-500/15 hover:bg-red-500 text-red-400 hover:text-white font-extrabold text-xs transition">
                                        🗑️ Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB 2: SECURITY AUDIT LOGS -->
    <div x-show="tab === 'logs'" x-cloak class="bg-theme-card border border-theme-border rounded-2xl overflow-hidden shadow-xs">
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

    <!-- TAB 3: GLOBAL SYSTEM SETTINGS -->
    <div x-show="tab === 'settings'" x-cloak class="max-w-lg bg-theme-card border border-theme-border rounded-2xl p-6 space-y-4">
        <h3 class="text-base font-extrabold text-theme-text">System Policies & Dev Mode</h3>
        <form @submit.prevent="saveSettings()" class="space-y-4 text-xs">
            <div>
                <label class="block font-bold text-theme-muted uppercase mb-1">Daily Overdue Fine Rate (₱ PHP / Day)</label>
                <input type="number" step="0.50" x-model="settings.daily_fine_rate" required
                       class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border font-mono text-sm text-theme-text outline-none focus:border-theme-accent transition">
            </div>
            <div>
                <label class="block font-bold text-theme-muted uppercase mb-1">Default Book Loan Duration (Days)</label>
                <input type="number" x-model="settings.loan_duration_days" required min="1" max="30"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border font-mono text-sm text-theme-text outline-none focus:border-theme-accent transition">
            </div>
            <div>
                <label class="block font-bold text-theme-muted uppercase mb-1">Session Idle Timeout (Seconds)</label>
                <!-- Changed min value from 300 to 60, step to 10 -->
                <input type="number" x-model="settings.session_timeout_seconds" required min="60" max="7200" step="10"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border font-mono text-sm text-theme-text outline-none focus:border-theme-accent transition">
                <p class="text-[9px] text-theme-muted mt-1 ml-1">Default 900s (15 mins). Prompts "Are you there?" 60s before log out.</p>
            </div>
            <div class="pt-3 border-t border-theme-border">
                <label class="flex items-center gap-2.5 cursor-pointer select-none">
                    <input type="checkbox" x-model="settings.dev_mode_quick_fills" class="w-4 h-4 accent-sky-500 rounded">
                    <span class="font-extrabold uppercase text-theme-muted tracking-wider">Enable Website Dev Mode</span>
                </label>
                <p class="text-[10px] text-theme-muted mt-1 ml-6.5">Displays the 'Defense Quick-Fill' auto-login buttons on the login page.</p>
            </div>
            <button type="submit" class="px-5 py-2.5 rounded-xl bg-theme-accent text-white font-extrabold shadow">
                Save System Settings
            </button>
        </form>
    </div>

    <!-- MODAL: CREATE OR EDIT USER ACCOUNT -->
    <div x-show="userModalOpen" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 backdrop-blur-xs p-3 sm:p-5"
         x-transition>
        <div @click.away="userModalOpen = false"
             class="w-full max-w-2xl max-h-[92dvh] bg-theme-card border border-theme-border rounded-2xl shadow-2xl overflow-hidden flex flex-col">

            <div class="p-4 sm:p-5 border-b border-theme-border flex items-center justify-between bg-theme-main/50">
                <div>
                    <h3 class="text-base font-extrabold text-theme-text"
                        x-text="isEditing ? 'Edit Registrar Account & Legal Name' : 'Provision New Account'"></h3>
                    <p class="text-xs text-theme-muted">Super Admin Exclusive: Edit First Name, Middle Name, M.I., Last Name & Extension</p>
                </div>
                <button @click="userModalOpen = false" class="w-8 h-8 rounded-lg bg-theme-hover text-theme-muted font-bold">&times;</button>
            </div>

            <form @submit.prevent="submitUserForm()" class="p-5 overflow-y-auto space-y-4 text-xs">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block font-extrabold uppercase text-theme-muted mb-1">Role</label>
                        <select x-model="userForm.role" class="w-full px-3 py-2.5 rounded-xl bg-theme-main border border-theme-border font-bold text-theme-text">
                            <option value="student">Student</option>
                            <option value="faculty">Faculty</option>
                            <option value="librarian">Librarian</option>
                            <option value="super_admin">Super Admin</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-extrabold uppercase text-theme-muted mb-1">School / Staff ID</label>
                        <input type="text" x-model="userForm.school_id" required placeholder="240103543"
                               class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border font-mono font-bold text-theme-text">
                    </div>
                    <div>
                        <label class="block font-extrabold uppercase text-theme-muted mb-1">Program Code</label>
                        <input type="text" x-model="userForm.program_code" placeholder="BSIT (Optional)"
                               class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border font-mono text-theme-text">
                    </div>
                </div>

                <div class="p-4 rounded-xl bg-theme-main border border-theme-border space-y-3">
                    <p class="text-[11px] font-extrabold uppercase tracking-wider text-amber-400">
                        🔒 Registrar Legal Name Fields (Super Admin Editable)
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-theme-muted mb-1">First Name *</label>
                            <input type="text" x-model="userForm.first_name" required placeholder="Juan"
                                   class="w-full px-3.5 py-2 rounded-xl bg-theme-card border border-theme-border text-theme-text font-bold">
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="col-span-2">
                                <label class="block font-bold text-theme-muted mb-1">Middle Name</label>
                                <input type="text" x-model="userForm.middle_name" placeholder="Santos"
                                       class="w-full px-3.5 py-2 rounded-xl bg-theme-card border border-theme-border text-theme-text">
                            </div>
                            <div>
                                <label class="block font-bold text-theme-muted mb-1">M.I.</label>
                                <input type="text" x-model="userForm.middle_initial" placeholder="S." maxlength="5"
                                       class="w-full px-2.5 py-2 rounded-xl bg-theme-card border border-theme-border font-mono text-center text-theme-text">
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="sm:col-span-2">
                            <label class="block font-bold text-theme-muted mb-1">Last Name *</label>
                            <input type="text" x-model="userForm.last_name" required placeholder="Dela Cruz"
                                   class="w-full px-3.5 py-2 rounded-xl bg-theme-card border border-theme-border text-theme-text font-bold">
                        </div>
                        <div>
                            <label class="block font-bold text-theme-muted mb-1">Extension (Suffix)</label>
                            <input type="text" x-model="userForm.suffix" placeholder="Jr., III (Optional)"
                                   class="w-full px-3.5 py-2 rounded-xl bg-theme-card border border-theme-border text-theme-text">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block font-extrabold uppercase text-theme-muted mb-1">Display Name</label>
                        <input type="text" x-model="userForm.display_name" placeholder="JuanDC"
                               class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border text-theme-text">
                    </div>
                    <div>
                        <label class="block font-extrabold uppercase text-theme-muted mb-1">Contact No.</label>
                        <input type="text" x-model="userForm.contact_no" placeholder="09171234567"
                               class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border font-mono text-theme-text">
                    </div>
                    <div>
                        <label class="block font-extrabold uppercase text-theme-muted mb-1">Email Address *</label>
                        <input type="email" x-model="userForm.email" required placeholder="user@bcp.edu.ph"
                               class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border text-theme-text">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-extrabold uppercase text-theme-muted mb-1">Account Status</label>
                        <select x-model="userForm.status" class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border font-bold text-theme-text">
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                            <option value="graduated">Graduated</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-extrabold uppercase text-theme-muted mb-1"
                               x-text="isEditing ? 'Reset Password (Leave blank to keep)' : 'Initial Password *'"></label>
                        <input type="text" x-model="userForm.password" :required="!isEditing" placeholder="password"
                               class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border font-mono text-theme-text">
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="userModalOpen = false" class="px-4 py-2.5 rounded-xl bg-theme-main text-theme-muted font-bold">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-theme-accent text-white font-extrabold shadow"
                            x-text="isEditing ? '✓ Save Account Changes' : '+ Create Account'"></button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
function superAdminApp() {
    return {
        tab: 'accounts',
        users: [],
        logs: [],
        userFilter: '',
        logFilter: '',
        settings: { daily_fine_rate: '10.00', loan_duration_days: '3', dev_mode_quick_fills: true, session_timeout_seconds: '900' },
        userModalOpen: false,
        isEditing: false,
        alertMsg: '',
        alertOk: true,

        userForm: {
            id: 0,
            role: 'student',
            school_id: '',
            program_code: 'BSIT',
            first_name: '',
            middle_name: '',
            middle_initial: '',
            last_name: '',
            suffix: '',
            display_name: '',
            contact_no: '',
            email: '',
            status: 'active',
            password: ''
        },

        get filteredUsers() {
            const q = this.userFilter.toLowerCase().trim();
            if (!q) return this.users;
            return this.users.filter(u =>
                (u.school_id || '').toLowerCase().includes(q) ||
                (u.full_name || '').toLowerCase().includes(q) ||
                (u.display_name || '').toLowerCase().includes(q) ||
                (u.email || '').toLowerCase().includes(q) ||
                (u.program_code || '').toLowerCase().includes(q)
            );
        },

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

        openCreateModal() {
            this.isEditing = false;
            this.userForm = {
                id: 0,
                role: 'student',
                school_id: '',
                program_code: 'BSIT',
                first_name: '',
                middle_name: '',
                middle_initial: '',
                last_name: '',
                suffix: '',
                display_name: '',
                contact_no: '',
                email: '',
                status: 'active',
                password: 'password'
            };
            this.userModalOpen = true;
        },

        openEditModal(u) {
            this.isEditing = true;
            this.userForm = {
                id: u.id,
                role: u.role,
                school_id: u.school_id,
                program_code: u.program_code || '',
                first_name: u.first_name || '',
                middle_name: u.middle_name || '',
                middle_initial: u.middle_initial || '',
                last_name: u.last_name || '',
                suffix: u.suffix || '',
                display_name: u.display_name || '',
                contact_no: u.contact_no || '',
                email: u.email || '',
                status: u.status || 'active',
                password: ''
            };
            this.userModalOpen = true;
        },

        async submitUserForm() {
            const payload = {
                action: this.isEditing ? 'update_user' : 'create_user',
                ...this.userForm,
                new_password: this.userForm.password
            };
            const res = await fetch('<?= BASE_URL ?>/api/system/manage.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            this.alertMsg = data.message;
            this.alertOk = data.success;
            if (data.success) {
                this.userModalOpen = false;
                await this.loadConsole();
            }
        },

        async deleteUser(u) {
            if (!confirm(`Are you sure you want to permanently delete account #${u.school_id} (${u.full_name})?`)) return;
            const res = await fetch('<?= BASE_URL ?>/api/system/manage.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete_user', user_id: u.id })
            });
            const data = await res.json();
            this.alertMsg = data.message;
            this.alertOk = data.success;
            if (data.success) await this.loadConsole();
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