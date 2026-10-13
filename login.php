<?php
// login.php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/logo.php';

$pdo = $GLOBALS['pdo'] ?? null;
if (isset($_SESSION['user_id']) && $pdo) {
    header('Location: ' . BASE_URL . '/index.php');
    exit();
}

$dev_mode = '1';
if ($pdo) {
    try {
        $stmt_dev = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'dev_mode_quick_fills' LIMIT 1");
        if ($stmt_dev) {
            $val = $stmt_dev->fetchColumn();
            if ($val !== false) $dev_mode = $val;
        }
    } catch (\Throwable $e) {}
}
$show_quick_fills = ($dev_mode === '1');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | bASA BCP Library</title>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body x-data="loginHandler()" @keydown.escape.window="modalMsg = ''" :class="lightMode ? 'bg-[#f0f7ff] text-slate-900' : 'bg-[#0f172a] text-slate-100'" class="min-h-screen flex items-center justify-center p-4 font-['Plus_Jakarta_Sans'] transition-colors duration-300">

    <!-- CENTERED SCREEN RESPONSE MODAL -->
    <div x-show="modalMsg" x-cloak
         class="fixed inset-0 z-[100] flex items-center justify-center bg-black/70 backdrop-blur-xs p-4"
         x-transition>
        <div @click.away="modalMsg = ''"
             :class="[
                 lightMode ? 'bg-white text-slate-900' : 'bg-[#1e293b] text-white',
                 modalOk ? 'border-emerald-500' : 'border-[#D9232A]'
             ]"
             class="w-full max-w-sm border-2 rounded-2xl shadow-2xl p-6 text-center space-y-4">
            <div :class="modalOk ? 'bg-emerald-500/20 text-emerald-500 border-emerald-500/40' : 'bg-red-500/20 text-red-500 border-red-500/40'"
                 class="w-14 h-14 rounded-2xl border mx-auto flex items-center justify-center text-2xl font-extrabold">
                <span x-text="modalOk ? '✓' : '⚠️'"></span>
            </div>
            <div>
                <h3 class="text-base font-extrabold" :class="modalOk ? 'text-emerald-500' : 'text-red-500'"
                    x-text="modalOk ? 'Success' : 'Authentication Notice'"></h3>
                <p class="text-xs sm:text-sm font-semibold mt-1.5 opacity-90 leading-relaxed" x-text="modalMsg"></p>
            </div>
            <button type="button" @click="modalMsg = ''"
                    :class="modalOk ? 'bg-emerald-500 text-slate-950 hover:bg-emerald-400' : 'bg-[#D9232A] hover:bg-red-700 text-white'"
                    class="w-full py-2.5 px-5 rounded-xl font-extrabold text-xs shadow-lg transition">
                OK
            </button>
        </div>
    </div>

    <button @click="toggleMode()" class="fixed top-5 right-5 px-3.5 py-2 rounded-xl font-bold text-xs shadow-md transition"
            :class="lightMode ? 'bg-white text-slate-800 border border-sky-200' : 'bg-[#1e293b] text-sky-400 border border-slate-700'">
        <span x-text="lightMode ? '🌙 Dark Mode' : '☀️ Light Mode'"></span>
    </button>

    <div :class="lightMode ? 'bg-white border-sky-200' : 'bg-[#1e293b] border-slate-700'"
         class="w-full max-w-md rounded-2xl shadow-2xl border p-8 transition-colors duration-300">
        
        <div class="flex flex-col items-center text-center mb-6">
            <div class="bg-white px-5 py-2.5 rounded-2xl shadow-sm border border-slate-200 mb-4">
                <?php render_basa_logo('md'); ?>
            </div>
            <h1 class="text-xl font-extrabold">Bestlink Automated System for Archives</h1>
            <p class="text-xs opacity-70 mt-1">4th Floor Library & Research Portal</p>
        </div>

        <?php if (isset($_GET['timeout'])): ?>
            <div class="mb-4 p-3 bg-amber-500/15 border border-amber-500/40 text-amber-400 rounded-xl text-xs font-semibold text-center">
                Session timed out after 15 minutes of inactivity.
            </div>
        <?php endif; ?>

        <!-- STANDARD SIGN IN FORM -->
        <form x-show="!forgotOpen" @submit.prevent="submitLogin" class="space-y-4">
            <div>
                <label class="block text-[11px] font-extrabold uppercase tracking-wider mb-1.5 opacity-80">BCP School ID or Email</label>
                <input type="text" x-model="identifier" required placeholder="e.g. 240103537 or LIB-001"
                       :class="lightMode ? 'bg-slate-50 border-slate-300 text-slate-900' : 'bg-[#0f172a] border-slate-700 text-white'"
                       class="w-full px-4 py-3 rounded-xl border focus:border-[#0089D0] outline-none text-sm font-mono transition">
            </div>

            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-[11px] font-extrabold uppercase tracking-wider opacity-80">Password</label>
                    <button type="button" @click="forgotOpen = true" class="text-xs font-extrabold text-[#0089D0] hover:underline">
                        Forgot Password?
                    </button>
                </div>
                <input type="password" x-model="password" required placeholder="••••••••"
                       :class="lightMode ? 'bg-slate-50 border-slate-300 text-slate-900' : 'bg-[#0f172a] border-slate-700 text-white'"
                       class="w-full px-4 py-3 rounded-xl border focus:border-[#0089D0] outline-none text-sm transition">
            </div>

            <button type="submit" :disabled="loading"
                    class="w-full bg-[#0089D0] hover:bg-[#1B1464] text-white font-bold py-3 px-4 rounded-xl shadow-lg transition duration-200 text-sm">
                <span x-show="!loading">Sign In to Portal</span>
                <span x-show="loading" x-cloak>Authenticating...</span>
            </button>
        </form>

        <!-- 3-STEP FORGOT PASSWORD RECOVERY WIZARD -->
        <div x-show="forgotOpen" x-cloak x-transition class="space-y-4 text-xs">
            <div class="flex items-center justify-between pb-3 border-b border-slate-500/20">
                <span class="font-extrabold text-sm text-[#0089D0]">Account Recovery</span>
                <button type="button" @click="resetForgot()" class="font-bold opacity-75 hover:opacity-100 transition">← Cancel</button>
            </div>

            <!-- STEP 1: Find Account -->
            <div x-show="forgotStep === 1" x-transition class="space-y-4">
                <div>
                    <label class="block text-[11px] font-extrabold uppercase mb-1.5 opacity-80">Step 1: Find Your Account</label>
                    <input type="text" x-model="forgotIdentifier" placeholder="Enter your BCP School ID or Email"
                           @keydown.enter.prevent="findAccount()"
                           :class="lightMode ? 'bg-slate-50 border-slate-300 text-slate-900' : 'bg-[#0f172a] border-slate-700 text-white'"
                           class="w-full px-3.5 py-3 rounded-xl border font-mono outline-none focus:border-[#0089D0] transition">
                </div>
                <button type="button" @click="findAccount()" :disabled="loading"
                        class="w-full bg-[#0089D0] hover:bg-[#1B1464] text-white font-extrabold py-3 px-4 rounded-xl shadow-lg transition">
                    Continue →
                </button>
            </div>

            <!-- STEP 2: Choose Verification Method -->
            <div x-show="forgotStep === 2" x-transition class="space-y-4">
                <div class="p-3 bg-slate-500/10 rounded-xl border border-slate-500/20 text-center">
                    <div class="w-10 h-10 mx-auto rounded-full bg-[#0089D0] text-white font-bold flex items-center justify-center text-lg mb-2 shadow"
                         x-text="forgotAccount.full_name ? forgotAccount.full_name.substring(0,1) : '?'"></div>
                    <p class="font-extrabold text-sm" x-text="forgotAccount.full_name"></p>
                    <p class="text-[10px] uppercase tracking-wider opacity-75 mt-1">Step 2: Choose a way to recover</p>
                </div>

                <div class="space-y-2">
                    <button type="button" @click="forgotChannel = 'email'"
                            :class="forgotChannel === 'email' ? 'border-[#0089D0] bg-[#0089D0]/10 text-[#0089D0]' : 'border-slate-500/30 opacity-80 hover:opacity-100'"
                            class="w-full p-3 rounded-xl border-2 font-extrabold text-left transition flex justify-between items-center">
                        <span>📧 Send Email Code</span>
                        <span class="font-mono text-[10px] opacity-75" x-text="forgotAccount.email_masked"></span>
                    </button>

                    <template x-if="forgotAccount.phone_masked">
                        <button type="button" @click="forgotChannel = 'sms'"
                                :class="forgotChannel === 'sms' ? 'border-[#0089D0] bg-[#0089D0]/10 text-[#0089D0]' : 'border-slate-500/30 opacity-80 hover:opacity-100'"
                                class="w-full p-3 rounded-xl border-2 font-extrabold text-left transition flex justify-between items-center">
                            <span>📱 Send SMS Code</span>
                            <span class="font-mono text-[10px] opacity-75" x-text="forgotAccount.phone_masked"></span>
                        </button>
                    </template>

                    <template x-if="forgotAccount.ga_enabled">
                        <button type="button" @click="forgotChannel = 'ga'"
                                :class="forgotChannel === 'ga' ? 'border-[#0089D0] bg-[#0089D0]/10 text-[#0089D0]' : 'border-slate-500/30 opacity-80 hover:opacity-100'"
                                class="w-full p-3 rounded-xl border-2 font-extrabold text-left transition flex justify-between items-center">
                            <span>🔐 Authenticator App</span>
                            <span class="font-mono text-[10px] opacity-75">6-Digit App Code</span>
                        </button>
                    </template>
                </div>

                <button type="button" @click="sendForgotOtp()" :disabled="loading"
                        class="w-full bg-[#0089D0] hover:bg-[#1B1464] text-white font-extrabold py-3 px-4 rounded-xl shadow-lg transition">
                    Continue →
                </button>
            </div>

            <!-- STEP 3: Verify OTP & Reset Password -->
            <div x-show="forgotStep === 3" x-transition class="space-y-4">
                <div class="text-center pb-2">
                    <p class="font-extrabold text-sm text-emerald-500" x-text="forgotChannel === 'ga' ? 'Step 3: Enter Authenticator Code' : 'Step 3: Enter Verification Code'"></p>
                    <p class="text-[11px] opacity-75 mt-0.5" x-text="forgotChannel === 'email' ? ('Sent to ' + forgotAccount.email_masked) : (forgotChannel === 'sms' ? ('Sent to ' + forgotAccount.phone_masked) : 'Open your Google Authenticator app.')"></p>
                </div>

                <div x-show="demoOtp" x-cloak class="p-2.5 rounded-xl bg-amber-500/15 border border-amber-500/30 text-amber-500 font-mono text-center mb-2">
                    Sandbox OTP: <strong class="underline select-all" x-text="demoOtp"></strong>
                </div>

                <form @submit.prevent="submitForgotReset()" class="space-y-4">
                    <div>
                        <label class="block text-[11px] font-extrabold uppercase mb-1.5 opacity-80 text-emerald-500">6-Digit Verification Code</label>
                        <input type="text" x-model="forgotOtp" required maxlength="6" placeholder="000000"
                               :class="lightMode ? 'bg-slate-50 border-slate-300 text-slate-900' : 'bg-[#0f172a] border-slate-700 text-white'"
                               class="w-full px-3.5 py-2.5 rounded-xl border font-mono text-center text-lg font-extrabold tracking-[0.5em] outline-none focus:border-emerald-500 transition">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4">
                        <div class="w-full">
                            <label class="block text-[11px] font-extrabold uppercase mb-1.5 opacity-80 whitespace-nowrap">New Password</label>
                            <input type="password" x-model="newPassword" required minlength="6" placeholder="Min. 6 chars"
                                   :class="lightMode ? 'bg-slate-50 border-slate-300 text-slate-900' : 'bg-[#0f172a] border-slate-700 text-white'"
                                   class="w-full px-3.5 py-2.5 rounded-xl border outline-none focus:border-emerald-500 transition">
                        </div>
                        <div class="w-full">
                            <label class="block text-[11px] font-extrabold uppercase mb-1.5 opacity-80 whitespace-nowrap">Confirm Password</label>
                            <input type="password" x-model="confirmPassword" required minlength="6" placeholder="Repeat password"
                                   :class="lightMode ? 'bg-slate-50 border-slate-300 text-slate-900' : 'bg-[#0f172a] border-slate-700 text-white'"
                                   class="w-full px-3.5 py-2.5 rounded-xl border outline-none focus:border-emerald-500 transition">
                        </div>
                    </div>
                    
                    <button type="submit" :disabled="loading"
                            class="w-full mt-2 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold py-3 rounded-xl shadow-lg transition">
                        🔓 Verify & Reset Password
                    </button>
                </form>
            </div>
        </div>

        <?php if ($show_quick_fills): ?>
            <div class="mt-6 pt-5 border-t border-slate-500/20">
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-center mb-2.5 opacity-60 text-amber-500">
                    Website Dev Mode (Quick Defense Login)
                </p>
                <div class="grid grid-cols-3 gap-2 text-xs">
                    <button @click="fillDemo('240103537')" type="button" class="py-2 px-2 rounded-lg font-bold bg-[#0089D0]/15 text-[#0089D0] hover:bg-[#0089D0] hover:text-white transition">240103537</button>
                    <button @click="fillDemo('LIB-001')" type="button" class="py-2 px-2 rounded-lg font-bold bg-emerald-500/15 text-emerald-500 hover:bg-emerald-500 hover:text-white transition">Librarian</button>
                    <button @click="fillDemo('ADMIN-001')" type="button" class="py-2 px-2 rounded-lg font-bold bg-amber-500/15 text-amber-500 hover:bg-amber-500 hover:text-slate-950 transition">Super Admin</button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function loginHandler() {
            return {
                identifier: '',
                password: '',
                loading: false,
                modalMsg: '',
                modalOk: false,
                
                forgotOpen: false,
                forgotStep: 1,
                forgotIdentifier: '',
                forgotAccount: {},
                forgotChannel: 'email',
                forgotOtp: '',
                newPassword: '',
                confirmPassword: '',
                demoOtp: '',

                lightMode: (localStorage.getItem('basa_theme') || '').includes('light'),

                toggleMode() {
                    this.lightMode = !this.lightMode;
                    localStorage.setItem('basa_theme', this.lightMode ? 'bestlink-light' : 'bestlink-dark');
                },

                fillDemo(id) {
                    this.forgotOpen = false;
                    this.identifier = id;
                    this.password = 'password';
                    this.modalMsg = '';
                    this.submitLogin(); // Instant auto-login
                },

                showModal(msg, ok = false) {
                    this.modalMsg = msg;
                    this.modalOk = ok;
                },

                resetForgot() {
                    this.forgotOpen = false;
                    this.forgotStep = 1;
                    this.forgotIdentifier = '';
                    this.forgotOtp = '';
                    this.newPassword = '';
                    this.confirmPassword = '';
                    this.demoOtp = '';
                },

                async submitLogin() {
                    this.loading = true;
                    this.modalMsg = '';
                    try {
                        const res = await fetch('<?= BASE_URL ?>/api/auth/login.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ identifier: this.identifier, password: this.password })
                        });
                        const data = await res.json();
                        if (data.success) {
                            window.location.href = data.redirect;
                        } else {
                            this.showModal(data.message, false);
                        }
                    } catch (err) {
                        this.showModal('Server connection error. Check Apache & MySQL.', false);
                    } finally {
                        this.loading = false;
                    }
                },

                async findAccount() {
                    if (!this.forgotIdentifier.trim()) {
                        return this.showModal('Please enter a School ID or Email first.', false);
                    }
                    this.loading = true;
                    try {
                        const res = await fetch('<?= BASE_URL ?>/api/auth/forgot_password.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'find_account', identifier: this.forgotIdentifier })
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.forgotAccount = data.account;
                            this.forgotChannel = 'email';
                            this.forgotStep = 2;
                        } else {
                            this.showModal(data.message, false);
                        }
                    } catch(e) { 
                        this.showModal('Server connection error.', false); 
                    } finally { 
                        this.loading = false; 
                    }
                },

                async sendForgotOtp() {
                    if (this.forgotChannel === 'ga') {
                        this.forgotStep = 3;
                        return;
                    }
                    this.loading = true;
                    try {
                        const res = await fetch('<?= BASE_URL ?>/api/auth/forgot_password.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'send_otp', identifier: this.forgotIdentifier, channel: this.forgotChannel })
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.forgotStep = 3;
                            if (data.demo_otp) {
                                this.demoOtp = data.demo_otp;
                                this.forgotOtp = data.demo_otp;
                            }
                        } else {
                            this.showModal(data.message, false);
                        }
                    } catch(e) { 
                        this.showModal('Server connection error.', false); 
                    } finally { 
                        this.loading = false; 
                    }
                },

                async submitForgotReset() {
                    this.loading = true;
                    try {
                        const res = await fetch('<?= BASE_URL ?>/api/auth/forgot_password.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                action: 'reset_password',
                                identifier: this.forgotIdentifier,
                                auth_method: this.forgotChannel,
                                otp_code: this.forgotOtp,
                                new_password: this.newPassword,
                                confirm_password: this.confirmPassword
                            })
                        });
                        const data = await res.json();
                        if (data.success) {
                            this.showModal(data.message, true);
                            this.password = this.newPassword;
                            this.identifier = this.forgotIdentifier;
                            this.resetForgot();
                        } else {
                            this.showModal(data.message, false);
                        }
                    } catch (e) {
                        this.showModal('Server connection error.', false);
                    } finally {
                        this.loading = false;
                    }
                }
            }
        }
    </script>
</body>
</html>