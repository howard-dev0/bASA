<?php
// login.php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/logo.php';

if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    header('Location: ' . BASE_URL . '/index.php');
    exit();
}

$pdo = $GLOBALS['pdo'];
$dev_mode = true;
try {
    $stmt_dev = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'dev_mode_quick_fills' LIMIT 1");
    if ($stmt_dev) {
        $val = $stmt_dev->fetchColumn();
        if ($val !== false) {
            $dev_mode = ($val === '1');
        }
    }
} catch (\Throwable $e) {}

$timeout_notice = isset($_GET['timeout']) ? 'Your session expired due to inactivity. Please sign in again.' : '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="bestlink-dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Sign In | bASA Bestlink Library</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        cartoon: ['"Fredoka"', 'sans-serif'],
                    },
                    colors: {
                        theme: {
                            sidebar: 'var(--bg-sidebar)',
                            main: 'var(--bg-main)',
                            card: 'var(--bg-card)',
                            hover: 'var(--bg-hover)',
                            border: 'var(--border-color)',
                            text: 'var(--text-normal)',
                            muted: 'var(--text-muted)',
                            accent: 'var(--accent-primary)',
                            accentHover: 'var(--accent-hover)',
                        }
                    }
                }
            }
        }
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }
        :root, [data-theme="bestlink-dark"] {
            --bg-sidebar: #130f40;
            --bg-main: #0f172a;
            --bg-card: #1e293b;
            --bg-hover: #27354d;
            --border-color: #334155;
            --text-normal: #f8fafc;
            --text-muted: #94a3b8;
            --accent-primary: #0089d0;
            --accent-hover: #0271ab;
        }
    </style>
</head>
<body class="bg-theme-main text-theme-text font-sans min-h-screen flex flex-col justify-center items-center p-4 selection:bg-theme-accent selection:text-white"
      x-data="loginPortalApp('<?= e($timeout_notice) ?>')"
      @keydown.escape.window="closeAlert(); forgotOpen = false"
      @keydown.enter.window="if (alertMsg) { $event.preventDefault(); $event.stopPropagation(); closeAlert(); }">

    <!-- CENTERED SCREEN WARNING / RESPONSE MODAL -->
    <div x-show="alertMsg" x-cloak
         class="fixed inset-0 z-[150] flex items-center justify-center bg-black/75 backdrop-blur-xs p-4"
         x-transition>
        <div @click.away="closeAlert()"
             :class="alertOk ? 'border-emerald-500' : 'border-[#D9232A]'"
             class="w-full max-w-md bg-theme-card border-2 rounded-2xl shadow-2xl p-6 text-center space-y-4">
            <div :class="alertOk ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/40' : 'bg-red-500/20 text-red-400 border-red-500/40'"
                 class="w-14 h-14 rounded-2xl border mx-auto flex items-center justify-center text-2xl font-extrabold shadow-inner">
                <span x-text="alertOk ? '✓' : '⚠️'"></span>
            </div>
            <div>
                <h3 class="text-base font-extrabold"
                    :class="alertOk ? 'text-emerald-400' : 'text-red-400'"
                    x-text="alertOk ? 'Success' : 'Authentication Notice'"></h3>
                <p class="text-xs sm:text-sm text-theme-text font-semibold mt-1.5 leading-relaxed" x-text="alertMsg"></p>
            </div>
            <button type="button"
                    x-ref="alertOkBtn"
                    @click="closeAlert()"
                    :class="alertOk ? 'bg-emerald-500 hover:bg-emerald-400 text-slate-950' : 'bg-[#D9232A] hover:bg-red-700 text-white'"
                    class="w-full py-2.5 px-5 rounded-xl font-extrabold text-xs shadow-lg transition focus:ring-4 focus:ring-white/30 outline-none">
                OK, Understood
            </button>
        </div>
    </div>

    <!-- MAIN LOGIN CARD -->
    <div class="w-full max-w-md bg-theme-card border border-theme-border rounded-3xl shadow-2xl overflow-hidden">
        <div class="p-6 sm:p-8 border-b border-theme-border text-center bg-gradient-to-b from-white/5 to-transparent">
            <a href="<?= BASE_URL ?>/index.php" class="inline-block bg-white px-5 py-2.5 rounded-2xl shadow-md mb-4">
                <?php render_basa_logo('md'); ?>
            </a>
            <h1 class="text-xl font-extrabold text-theme-text">Bestlink Library & Archives</h1>
            <p class="text-xs text-theme-muted mt-1">Sign in with your 9-digit Student ID, Staff ID, or BCP Email</p>
        </div>

        <form @submit.prevent="submitLogin()" class="p-6 sm:p-8 space-y-4">
            <div>
                <label class="block text-[11px] font-extrabold uppercase tracking-wider text-theme-muted mb-1.5">
                    School ID or Official Email
                </label>
                <input type="text" x-model="identifier" x-ref="idInput" required
                       placeholder="e.g. 240103537 or LIB-001"
                       class="w-full px-4 py-3 rounded-xl bg-theme-main border border-theme-border text-xs sm:text-sm font-mono font-bold text-theme-text focus:border-theme-accent outline-none transition">
            </div>

            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-[11px] font-extrabold uppercase tracking-wider text-theme-muted">
                        Password
                    </label>
                    <button type="button" @click="openForgotModal()"
                            class="text-xs font-extrabold text-theme-accent hover:underline">
                        Forgot Password?
                    </button>
                </div>
                <input type="password" x-model="password" x-ref="pwInput" required
                       placeholder="••••••••"
                       class="w-full px-4 py-3 rounded-xl bg-theme-main border border-theme-border text-xs sm:text-sm text-theme-text focus:border-theme-accent outline-none transition">
            </div>

            <button type="submit" :disabled="loading"
                    class="w-full py-3.5 px-5 rounded-xl bg-theme-accent hover:bg-theme-accentHover disabled:opacity-50 text-white font-extrabold text-xs sm:text-sm shadow-lg transition">
                <span x-text="loading ? 'Signing In...' : 'Sign In to bASA Portal →'"></span>
            </button>

            <?php if ($dev_mode): ?>
                <div class="pt-4 mt-4 border-t border-theme-border space-y-2">
                    <p class="text-[10px] font-extrabold uppercase tracking-wider text-theme-muted text-center">
                        ⚡ Capstone Defense Quick-Fill Accounts
                    </p>
                    <div class="grid grid-cols-3 gap-2">
                        <button type="button" @click="quickFill('240103537', 'password')"
                                class="py-2 px-2 rounded-xl bg-theme-main hover:bg-theme-hover border border-theme-border text-[11px] font-extrabold text-sky-400 transition">
                            🎓 Student
                        </button>
                        <button type="button" @click="quickFill('LIB-001', 'password')"
                                class="py-2 px-2 rounded-xl bg-theme-main hover:bg-theme-hover border border-theme-border text-[11px] font-extrabold text-emerald-400 transition">
                            📚 Librarian
                        </button>
                        <button type="button" @click="quickFill('ADMIN-001', 'password')"
                                class="py-2 px-2 rounded-xl bg-theme-main hover:bg-theme-hover border border-theme-border text-[11px] font-extrabold text-amber-400 transition">
                            🛡️ Super Admin
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- FORGOT PASSWORD MODAL -->
    <div x-show="forgotOpen" x-cloak
         class="fixed inset-0 z-[120] flex items-center justify-center bg-black/75 backdrop-blur-xs p-4"
         x-transition>
        <div @click.away="forgotOpen = false"
             class="w-full max-w-lg bg-theme-card border border-theme-border rounded-2xl shadow-2xl overflow-hidden">
            <div class="p-4 sm:p-5 border-b border-theme-border flex items-center justify-between bg-theme-main/50">
                <div>
                    <h3 class="text-base font-extrabold text-theme-text">Account Password Recovery</h3>
                    <p class="text-xs text-theme-muted">Verify via Email OTP, SMS OTP, or Google Authenticator</p>
                </div>
                <button type="button" @click="forgotOpen = false" class="w-8 h-8 rounded-lg bg-theme-hover text-theme-muted font-bold">&times;</button>
            </div>

            <div class="p-5 space-y-4 text-xs">
                <div class="flex gap-2">
                    <input type="text" x-model="fp.identifier" placeholder="Enter your School ID or Email..."
                           class="flex-1 px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border font-mono font-bold text-theme-text outline-none">
                    <button type="button" @click="findAccount()"
                            class="px-4 py-2.5 rounded-xl bg-theme-accent text-white font-extrabold shrink-0">
                        Find Account
                    </button>
                </div>

                <template x-if="fp.account">
                    <div class="space-y-4 pt-2 border-t border-theme-border">
                        <div class="p-3 rounded-xl bg-theme-main border border-theme-border">
                            <p class="font-extrabold text-theme-text" x-text="fp.account.full_name"></p>
                            <p class="text-[11px] text-theme-muted mt-0.5">Select a verification method below:</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                            <button type="button" @click="fp.auth_method = 'email'"
                                    :class="fp.auth_method === 'email' ? 'border-theme-accent bg-theme-hover text-theme-accent' : 'border-theme-border bg-theme-main text-theme-muted'"
                                    class="p-2.5 rounded-xl border text-left font-extrabold">
                                📧 Email OTP
                                <p class="text-[10px] font-normal truncate mt-0.5" x-text="fp.account.email_masked"></p>
                            </button>
                            <button type="button" @click="fp.auth_method = 'sms'"
                                    :class="fp.auth_method === 'sms' ? 'border-theme-accent bg-theme-hover text-theme-accent' : 'border-theme-border bg-theme-main text-theme-muted'"
                                    class="p-2.5 rounded-xl border text-left font-extrabold">
                                📱 SMS OTP
                                <p class="text-[10px] font-normal truncate mt-0.5" x-text="fp.account.phone_masked || 'Not Set'"></p>
                            </button>
                            <button type="button" @click="fp.auth_method = 'ga'"
                                    :class="fp.auth_method === 'ga' ? 'border-theme-accent bg-theme-hover text-theme-accent' : 'border-theme-border bg-theme-main text-theme-muted'"
                                    class="p-2.5 rounded-xl border text-left font-extrabold">
                                🔐 2FA App
                                <p class="text-[10px] font-normal mt-0.5" x-text="fp.account.ga_enabled ? 'Authenticator' : 'Not Linked'"></p>
                            </button>
                        </div>

                        <div x-show="fp.auth_method === 'email' || fp.auth_method === 'sms'" class="flex items-center justify-between gap-2 p-3 rounded-xl bg-theme-main border border-theme-border">
                            <span class="text-[11px] text-theme-muted">Request a 6-digit reset code:</span>
                            <button type="button" @click="sendRecoveryOtp()"
                                    class="px-3.5 py-2 rounded-xl bg-theme-accent text-white font-extrabold">
                                📨 Send Code
                            </button>
                        </div>

                        <div x-show="fp.demo_otp" class="p-2.5 rounded-xl bg-amber-500/15 border border-amber-500/30 text-amber-400 font-mono text-[11px]">
                            🧪 <strong>Sandbox OTP:</strong> <span class="font-extrabold underline" x-text="fp.demo_otp"></span>
                        </div>

                        <form @submit.prevent="resetForgottenPassword()" class="space-y-3">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                                <input type="text" x-model="fp.otp_code" required maxlength="6" placeholder="6-Digit Code"
                                       class="px-3 py-2.5 rounded-xl bg-theme-main border border-theme-accent font-mono font-extrabold text-center text-theme-text outline-none">
                                <input type="password" x-model="fp.new_password" required minlength="6" placeholder="New Password"
                                       class="px-3 py-2.5 rounded-xl bg-theme-main border border-theme-border text-theme-text outline-none">
                                <input type="password" x-model="fp.confirm_password" required minlength="6" placeholder="Confirm Password"
                                       class="px-3 py-2.5 rounded-xl bg-theme-main border border-theme-border text-theme-text outline-none">
                            </div>
                            <button type="submit"
                                    class="w-full py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold shadow">
                                ✓ Verify & Reset Password
                            </button>
                        </form>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <script>
    function loginPortalApp(initialNotice = '') {
        return {
            identifier: '',
            password: '',
            loading: false,
            alertMsg: initialNotice,
            alertOk: false,
            forgotOpen: false,

            fp: {
                identifier: '',
                account: null,
                auth_method: 'email',
                otp_code: '',
                new_password: '',
                confirm_password: '',
                demo_otp: ''
            },

            showAlert(msg, ok = false) {
                // Immediately blur any focused input so pressing Enter won't re-submit the form
                if (document.activeElement) {
                    document.activeElement.blur();
                }
                this.alertMsg = msg;
                this.alertOk = ok;
                this.$nextTick(() => {
                    if (this.$refs.alertOkBtn) {
                        this.$refs.alertOkBtn.focus();
                    }
                });
            },

            closeAlert() {
                this.alertMsg = '';
            },

            quickFill(id, pw) {
                this.identifier = id;
                this.password = pw;
                this.submitLogin();
            },

            async submitLogin() {
                // Prevent duplicate submission if modal is already open or request is in flight
                if (this.loading || this.alertMsg) return;
                this.loading = true;

                try {
                    const res = await fetch('<?= BASE_URL ?>/api/auth/login.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            identifier: this.identifier,
                            password: this.password
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        window.location.href = data.redirect;
                    } else {
                        this.showAlert(data.message || 'Invalid credentials.', false);
                    }
                } catch (e) {
                    this.showAlert('Network error while connecting to authentication server.', false);
                } finally {
                    this.loading = false;
                }
            },

            openForgotModal() {
                this.fp.identifier = this.identifier;
                this.fp.account = null;
                this.fp.demo_otp = '';
                this.forgotOpen = true;
            },

            async findAccount() {
                if (this.alertMsg) return;
                const res = await fetch('<?= BASE_URL ?>/api/auth/forgot_password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'find_account', identifier: this.fp.identifier })
                });
                const data = await res.json();
                if (data.success) {
                    this.fp.account = data.account;
                } else {
                    this.showAlert(data.message, false);
                }
            },

            async sendRecoveryOtp() {
                if (this.alertMsg) return;
                const res = await fetch('<?= BASE_URL ?>/api/auth/forgot_password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'send_otp',
                        identifier: this.fp.identifier,
                        channel: this.fp.auth_method
                    })
                });
                const data = await res.json();
                this.showAlert(data.message, data.success);
                if (data.success && data.demo_otp) {
                    this.fp.demo_otp = data.demo_otp;
                    this.fp.otp_code = data.demo_otp;
                }
            },

            async resetForgottenPassword() {
                if (this.alertMsg) return;
                const res = await fetch('<?= BASE_URL ?>/api/auth/forgot_password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'reset_password',
                        identifier: this.fp.identifier,
                        auth_method: this.fp.auth_method,
                        otp_code: this.fp.otp_code,
                        new_password: this.fp.new_password,
                        confirm_password: this.fp.confirm_password
                    })
                });
                const data = await res.json();
                this.showAlert(data.message, data.success);
                if (data.success) {
                    this.forgotOpen = false;
                }
            }
        };
    }
    </script>
</body>
</html>