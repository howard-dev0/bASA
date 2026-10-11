<?php
// login.php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/logo.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit();
}
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
                    x-text="modalOk ? 'Success' : 'Verification / Login Failed'"></h3>
                <p class="text-xs sm:text-sm font-semibold mt-1.5 opacity-90" x-text="modalMsg"></p>
            </div>
            <button type="button" @click="modalMsg = ''"
                    :class="modalOk ? 'bg-emerald-500 text-slate-950' : 'bg-[#D9232A] hover:bg-red-700 text-white'"
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

        <!-- STANDARD SIGN IN FORM -->
        <form x-show="!forgotOpen" @submit.prevent="submitLogin" class="space-y-4">
            <div>
                <label class="block text-[11px] font-extrabold uppercase tracking-wider mb-1.5 opacity-80">BCP School ID or Email</label>
                <input type="text" x-model="identifier" required placeholder="e.g. 240103537 or LIB-001"
                       :class="lightMode ? 'bg-slate-50 border-slate-300 text-slate-900' : 'bg-[#0f172a] border-slate-700 text-white'"
                       class="w-full px-4 py-3 rounded-xl border focus:border-[#0089D0] outline-none text-sm font-mono">
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
                       class="w-full px-4 py-3 rounded-xl border focus:border-[#0089D0] outline-none text-sm">
            </div>

            <button type="submit" :disabled="loading"
                    class="w-full bg-[#0089D0] hover:bg-[#1B1464] text-white font-bold py-3 px-4 rounded-xl shadow-lg transition duration-200 text-sm">
                <span x-show="!loading">Sign In to Portal</span>
                <span x-show="loading" x-cloak>Authenticating...</span>
            </button>
        </form>

        <!-- FORGOT PASSWORD RECOVERY VIEW (Only shown when "Forgot Password?" is clicked) -->
        <div x-show="forgotOpen" x-cloak x-transition class="space-y-4 text-xs">
            <div class="flex items-center justify-between pb-2 border-b border-slate-500/20">
                <span class="font-extrabold text-sm text-[#0089D0]">Forgot Password Recovery</span>
                <button type="button" @click="forgotOpen = false" class="font-bold opacity-75 hover:opacity-100">← Back to Login</button>
            </div>

            <div>
                <label class="block text-[11px] font-extrabold uppercase mb-1 opacity-80">Your BCP School ID or Email</label>
                <input type="text" x-model="identifier" required placeholder="e.g. 240103537"
                       :class="lightMode ? 'bg-slate-50 border-slate-300 text-slate-900' : 'bg-[#0f172a] border-slate-700 text-white'"
                       class="w-full px-3.5 py-2.5 rounded-xl border font-mono outline-none">
            </div>

            <div>
                <label class="block text-[11px] font-extrabold uppercase mb-1 opacity-80">Verification Method</label>
                <div class="grid grid-cols-3 gap-2">
                    <button type="button" @click="forgotChannel = 'email'"
                            :class="forgotChannel === 'email' ? 'bg-[#0089D0] text-white' : 'bg-slate-500/15'"
                            class="py-2 rounded-lg font-extrabold transition">📧 Email</button>
                    <button type="button" @click="forgotChannel = 'sms'"
                            :class="forgotChannel === 'sms' ? 'bg-[#0089D0] text-white' : 'bg-slate-500/15'"
                            class="py-2 rounded-lg font-extrabold transition">📱 SMS</button>
                    <button type="button" @click="forgotChannel = 'ga'"
                            :class="forgotChannel === 'ga' ? 'bg-[#0089D0] text-white' : 'bg-slate-500/15'"
                            class="py-2 rounded-lg font-extrabold transition">🔐 2FA App</button>
                </div>
            </div>

            <div x-show="forgotChannel !== 'ga'" class="flex items-center justify-between gap-2">
                <button type="button" @click="sendForgotOtp()"
                        class="w-full py-2.5 rounded-xl bg-amber-500/20 text-amber-400 border border-amber-500/40 font-extrabold hover:bg-amber-500 hover:text-slate-950 transition">
                    📨 Send 6-Digit Verification Code
                </button>
            </div>

            <div x-show="demoOtp" x-cloak class="p-2.5 rounded-lg bg-amber-500/15 border border-amber-500/30 text-amber-400 font-mono text-center">
                Sandbox OTP: <strong class="underline select-all" x-text="demoOtp"></strong>
            </div>

            <form @submit.prevent="submitForgotReset()" class="space-y-3">
                <div>
                    <label class="block text-[11px] font-extrabold uppercase mb-1 opacity-80">6-Digit Verification Code</label>
                    <input type="text" x-model="forgotOtp" required maxlength="6" placeholder="000000"
                           :class="lightMode ? 'bg-slate-50 border-slate-300 text-slate-900' : 'bg-[#0f172a] border-slate-700 text-white'"
                           class="w-full px-3.5 py-2.5 rounded-xl border font-mono text-center font-extrabold tracking-widest outline-none">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <input type="password" x-model="newPassword" required minlength="6" placeholder="New Password"
                           :class="lightMode ? 'bg-slate-50 border-slate-300 text-slate-900' : 'bg-[#0f172a] border-slate-700 text-white'"
                           class="w-full px-3.5 py-2.5 rounded-xl border outline-none">
                    <input type="password" x-model="confirmPassword" required minlength="6" placeholder="Confirm Password"
                           :class="lightMode ? 'bg-slate-50 border-slate-300 text-slate-900' : 'bg-[#0f172a] border-slate-700 text-white'"
                           class="w-full px-3.5 py-2.5 rounded-xl border outline-none">
                </div>
                <button type="submit"
                        class="w-full bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold py-3 rounded-xl shadow-lg transition">
                    🔓 Verify & Reset Password
                </button>
            </form>
        </div>

        <div class="mt-6 pt-5 border-t border-slate-500/20">
            <p class="text-[10px] font-extrabold uppercase tracking-wider text-center mb-2.5 opacity-60">
                Defense Quick-Fill (Password: password)
            </p>
            <div class="grid grid-cols-3 gap-2 text-xs">
                <button @click="fillDemo('240103537')" type="button" class="py-2 px-2 rounded-lg font-bold bg-[#0089D0]/15 text-[#0089D0] hover:bg-[#0089D0] hover:text-white transition">240103537</button>
                <button @click="fillDemo('LIB-001')" type="button" class="py-2 px-2 rounded-lg font-bold bg-emerald-500/15 text-emerald-500 hover:bg-emerald-500 hover:text-white transition">Librarian</button>
                <button @click="fillDemo('ADMIN-001')" type="button" class="py-2 px-2 rounded-lg font-bold bg-amber-500/15 text-amber-500 hover:bg-amber-500 hover:text-slate-950 transition">Super Admin</button>
            </div>
        </div>
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
                },
                showModal(msg, ok = false) {
                    this.modalMsg = msg;
                    this.modalOk = ok;
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
                async sendForgotOtp() {
                    const res = await fetch('<?= BASE_URL ?>/api/auth/forgot_password.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'send_otp', identifier: this.identifier, channel: this.forgotChannel })
                    });
                    const data = await res.json();
                    this.showModal(data.message, data.success);
                    if (data.success && data.demo_otp) {
                        this.demoOtp = data.demo_otp;
                        this.forgotOtp = data.demo_otp;
                    }
                },
                async submitForgotReset() {
                    const res = await fetch('<?= BASE_URL ?>/api/auth/forgot_password.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'reset_password',
                            identifier: this.identifier,
                            auth_method: this.forgotChannel,
                            otp_code: this.forgotOtp,
                            new_password: this.newPassword,
                            confirm_password: this.confirmPassword
                        })
                    });
                    const data = await res.json();
                    this.showModal(data.message, data.success);
                    if (data.success) {
                        this.forgotOpen = false;
                        this.password = this.newPassword;
                        this.newPassword = '';
                        this.confirmPassword = '';
                        this.forgotOtp = '';
                        this.demoOtp = '';
                    }
                }
            }
        }
    </script>
</body>
</html>