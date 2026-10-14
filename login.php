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
$dev_mode = is_dev_mode($pdo);
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

    <script>
        (function() {
            const savedTheme = localStorage.getItem('basa_theme') || 'bestlink-dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>

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

        [data-theme="bestlink-light"], [data-theme="light-mode"] {
            --bg-sidebar: #1b1464;
            --bg-main: #f0f7ff;
            --bg-card: #ffffff;
            --bg-hover: #e0f2fe;
            --border-color: #bae6fd;
            --text-normal: #0f172a;
            --text-muted: #475569;
            --accent-primary: #0089d0;
            --accent-hover: #1b1464;
        }

        .bcp-login-bg {
            background-color: #080b24;
            background-image:
                radial-gradient(at 15% 20%, rgba(0, 137, 208, 0.32) 0px, transparent 50%),
                radial-gradient(at 85% 15%, rgba(217, 35, 42, 0.24) 0px, transparent 45%),
                radial-gradient(at 50% 85%, rgba(27, 20, 100, 0.85) 0px, transparent 60%),
                linear-gradient(135deg, #070a20 0%, #130f40 50%, #0c1938 100%);
        }

        [data-theme="bestlink-light"] .bcp-login-bg,
        [data-theme="light-mode"] .bcp-login-bg {
            background-color: #f0f7ff;
            background-image:
                radial-gradient(at 15% 20%, rgba(0, 137, 208, 0.18) 0px, transparent 50%),
                radial-gradient(at 85% 15%, rgba(217, 35, 42, 0.12) 0px, transparent 45%),
                linear-gradient(135deg, #e0f2fe 0%, #f0f7ff 50%, #eef2ff 100%);
        }
    </style>
</head>
<body class="bcp-login-bg text-theme-text font-sans min-h-screen flex flex-col justify-center items-center p-4 relative selection:bg-theme-accent selection:text-white transition-colors duration-200"
      x-data="loginPortalApp('<?= e($timeout_notice) ?>')"
      @keydown.escape.window="if (alertMsg) { closeAlert(); } else { forgotOpen = false; }"
      @keydown.enter.window="if (alertMsg) { $event.preventDefault(); $event.stopPropagation(); closeAlert(); }">

    <?php include __DIR__ . '/includes/firebase_sms.php'; ?>

    <!-- TOP BAR: BACK TO HOME & LIGHT/DARK MODE TOGGLE -->
    <div class="fixed top-4 inset-x-4 max-w-5xl mx-auto flex items-center justify-between z-30 pointer-events-none">
        <a href="<?= BASE_URL ?>/index.php?home=1"
           class="pointer-events-auto px-3.5 py-2 rounded-xl bg-theme-card hover:bg-theme-hover border border-theme-border text-xs font-extrabold text-theme-text shadow-md transition flex items-center gap-1.5">
            <span>←</span>
            <span>Home</span>
        </a>

        <button type="button"
                @click="toggleDarkLight()"
                title="Toggle Light / Dark Mode"
                class="pointer-events-auto px-3.5 py-2 rounded-xl bg-theme-card hover:bg-theme-hover border border-theme-border text-xs font-extrabold text-theme-text shadow-md transition flex items-center gap-2">
            <span x-text="theme.includes('light') ? '🌙' : '☀️'"></span>
            <span x-text="theme.includes('light') ? 'Dark Mode' : 'Light Mode'"></span>
        </button>
    </div>

    <!-- CENTERED SCREEN WARNING / RESPONSE MODAL -->
    <div x-show="alertMsg" x-cloak
         @click.stop="closeAlert()"
         class="fixed inset-0 z-[150] flex items-center justify-center bg-black/75 backdrop-blur-xs p-4"
         x-transition>
        <div @click.stop
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
                    @click.stop="closeAlert()"
                    :class="alertOk ? 'bg-emerald-500 hover:bg-emerald-400 text-slate-950' : 'bg-[#D9232A] hover:bg-red-700 text-white'"
                    class="w-full py-2.5 px-5 rounded-xl font-extrabold text-xs shadow-lg transition focus:ring-4 focus:ring-white/30 outline-none">
                OK, Understood
            </button>
        </div>
    </div>

    <!-- MAIN LOGIN CARD -->
    <div class="w-full max-w-md bg-theme-card/95 backdrop-blur-xl border border-theme-border rounded-3xl shadow-2xl overflow-hidden mt-10 sm:mt-0 relative z-10">
        <div class="p-6 sm:p-8 border-b border-theme-border text-center bg-gradient-to-b from-white/5 to-transparent">
            <a href="<?= BASE_URL ?>/index.php?home=1" class="inline-block bg-white px-5 py-3 rounded-2xl shadow-md border border-slate-200 mb-4">
                <?php render_basa_logo('md'); ?>
            </a>
            <h1 class="text-xl font-extrabold text-theme-text">Bestlink Library &amp; Archives</h1>
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
                                class="py-2 px-2 rounded-xl bg-theme-main hover:bg-theme-hover border border-theme-border text-[11px] font-extrabold text-sky-500 transition">
                            🎓 Student
                        </button>
                        <button type="button" @click="quickFill('LIB-001', 'password')"
                                class="py-2 px-2 rounded-xl bg-theme-main hover:bg-theme-hover border border-theme-border text-[11px] font-extrabold text-emerald-500 transition">
                            📚 Librarian
                        </button>
                        <button type="button" @click="quickFill('ADMIN-001', 'password')"
                                class="py-2 px-2 rounded-xl bg-theme-main hover:bg-theme-hover border border-theme-border text-[11px] font-extrabold text-amber-500 transition">
                            🛡️ Super Admin
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- FORGOT PASSWORD MODAL -->
    <div x-show="forgotOpen" x-cloak
         @click.self="if (!alertMsg) forgotOpen = false"
         class="fixed inset-0 z-[120] flex items-center justify-center bg-black/75 backdrop-blur-xs p-4"
         x-transition>
        <div @click.stop
             class="w-full max-w-lg bg-theme-card border border-theme-border rounded-2xl shadow-2xl overflow-hidden">
            <div class="p-4 sm:p-5 border-b border-theme-border flex items-center justify-between bg-theme-main/50">
                <div>
                    <h3 class="text-base font-extrabold text-theme-text">Account Password Recovery</h3>
                    <p class="text-xs text-theme-muted">Verify via MailerSend Email OTP, Firebase SMS OTP, or Google Authenticator</p>
                </div>
                <button type="button" @click="forgotOpen = false" class="w-8 h-8 rounded-lg bg-theme-hover text-theme-muted font-bold">&times;</button>
            </div>

            <div class="p-5 space-y-4 text-xs">
                <form @submit.prevent="findAccount()" class="flex gap-2">
                    <input type="text" x-model="fp.identifier" required placeholder="Enter your School ID or Email..."
                           class="flex-1 px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border font-mono font-bold text-theme-text outline-none">
                    <button type="submit"
                            class="px-4 py-2.5 rounded-xl bg-theme-accent text-white font-extrabold shrink-0">
                        Find Account
                    </button>
                </form>

                <template x-if="fp.account">
                    <div class="space-y-4 pt-2 border-t border-theme-border">
                        <div class="p-3 rounded-xl bg-theme-main border border-theme-border flex items-center justify-between">
                            <div>
                                <p class="font-extrabold text-theme-text" x-text="fp.account.full_name"></p>
                                <p class="text-[11px] text-theme-muted mt-0.5">Select a verification method below:</p>
                            </div>
                            <button type="button" @click="resetRecoveryState()"
                                    class="text-[11px] font-bold text-theme-muted hover:text-theme-text underline">
                                Change ID
                            </button>
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
                                📱 Firebase SMS
                                <p class="text-[10px] font-normal truncate mt-0.5" x-text="fp.account.phone_masked || 'Enter Mobile No.'"></p>
                            </button>
                            <button type="button" @click="fp.auth_method = 'ga'"
                                    :class="fp.auth_method === 'ga' ? 'border-theme-accent bg-theme-hover text-theme-accent' : 'border-theme-border bg-theme-main text-theme-muted'"
                                    class="p-2.5 rounded-xl border text-left font-extrabold">
                                🔐 2FA App
                                <p class="text-[10px] font-normal mt-0.5" x-text="fp.account.ga_enabled ? 'Authenticator' : 'Not Linked'"></p>
                            </button>
                        </div>

                        <!-- Editable Mobile Number Input when SMS is chosen -->
                        <div x-show="fp.auth_method === 'sms'" class="p-3 rounded-xl bg-theme-main border border-theme-border space-y-1.5">
                            <label class="block text-[10px] font-extrabold uppercase text-theme-muted">
                                Philippine Mobile Number for SMS OTP (09XX... or +639XX...):
                            </label>
                            <input type="text" x-model="fp.phone_override" placeholder="e.g. 09171234567"
                                   class="w-full px-3 py-2 rounded-lg bg-theme-card border border-theme-border font-mono font-bold text-xs text-theme-text outline-none focus:border-theme-accent">
                        </div>

                        <div x-show="fp.auth_method === 'email' || fp.auth_method === 'sms'" class="flex items-center justify-between gap-2 p-3 rounded-xl bg-theme-main border border-theme-border">
                            <span class="text-[11px] text-theme-muted"
                                  x-text="fp.auth_method === 'email' ? 'Send 6-digit code via MailerSend:' : 'Send 6-digit SMS via Firebase:'"></span>
                            <button type="button" @click="sendRecoveryOtp()" :disabled="fp.sendingOtp"
                                    class="px-3.5 py-2 rounded-xl bg-theme-accent disabled:opacity-50 text-white font-extrabold">
                                <span x-text="fp.sendingOtp ? '⏳ Sending...' : '📨 Send Code'"></span>
                            </button>
                        </div>

                        <div x-show="fp.demo_otp" class="p-2.5 rounded-xl bg-amber-500/15 border border-amber-500/30 text-amber-500 font-mono text-[11px]">
                            🧪 <strong>OTP Code:</strong> <span class="font-extrabold underline select-all" x-text="fp.demo_otp"></span>
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
                                ✓ Verify &amp; Reset Password
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
            theme: localStorage.getItem('basa_theme') || 'bestlink-dark',
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
                phone_override: '',
                otp_code: '',
                new_password: '',
                confirm_password: '',
                demo_otp: '',
                sendingOtp: false
            },

            toggleDarkLight() {
                this.theme = this.theme.includes('light') ? 'bestlink-dark' : 'bestlink-light';
                localStorage.setItem('basa_theme', this.theme);
                document.documentElement.setAttribute('data-theme', this.theme);
            },

            showAlert(msg, ok = false) {
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
                if (!this.fp.identifier && this.identifier) {
                    this.fp.identifier = this.identifier;
                }
                this.forgotOpen = true;
            },

            resetRecoveryState() {
                this.fp.account = null;
                this.fp.phone_override = '';
                this.fp.otp_code = '';
                this.fp.new_password = '';
                this.fp.confirm_password = '';
                this.fp.demo_otp = '';
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
                    this.fp.phone_override = data.account.contact_no || '';
                } else {
                    this.showAlert(data.message, false);
                }
            },

            async sendRecoveryOtp() {
                if (this.alertMsg || this.fp.sendingOtp) return;
                this.fp.sendingOtp = true;

                try {
                    const res = await fetch('<?= BASE_URL ?>/api/auth/forgot_password.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'send_otp',
                            identifier: this.fp.identifier,
                            channel: this.fp.auth_method,
                            phone_override: this.fp.phone_override
                        })
                    });
                    const data = await res.json();

                    if (!data.success) {
                        this.showAlert(data.message, false);
                        return;
                    }

                    if (data.demo_otp) {
                        this.fp.demo_otp = data.demo_otp;
                        this.fp.otp_code = data.demo_otp;
                    }

                    if (this.fp.auth_method === 'sms' && data.phone_e164 && typeof window.basaSendFirebaseSms === 'function') {
                        const fbRes = await window.basaSendFirebaseSms(data.phone_e164);
                        if (fbRes.success) {
                            this.fp.otp_code = '';
                            this.showAlert(`Firebase SMS OTP sent to ${data.phone_e164}! Check your phone messages.`, true);
                            return;
                        } else {
                            // Provide fallback OTP if Firebase project is on free Spark tier
                            if (data.fallback_otp) {
                                this.fp.demo_otp = data.fallback_otp;
                                this.fp.otp_code = data.fallback_otp;
                            }
                            this.showAlert(`${fbRes.error} (Fallback OTP activated below so you can continue testing).`, false);
                            return;
                        }
                    }

                    this.showAlert(data.message, true);
                } finally {
                    this.fp.sendingOtp = false;
                }
            },

            async resetForgottenPassword() {
                if (this.alertMsg) return;

                let firebaseIdToken = '';
                if (this.fp.auth_method === 'sms' && window.basaSmsConfirmation && typeof window.basaVerifyFirebaseSms === 'function') {
                    const fbVerify = await window.basaVerifyFirebaseSms(this.fp.otp_code);
                    if (fbVerify.success) {
                        firebaseIdToken = fbVerify.idToken;
                    } else if (!this.fp.demo_otp) {
                        this.showAlert(fbVerify.error || 'Invalid Firebase SMS code.', false);
                        return;
                    }
                }

                const res = await fetch('<?= BASE_URL ?>/api/auth/forgot_password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'reset_password',
                        identifier: this.fp.identifier,
                        auth_method: this.fp.auth_method,
                        phone_override: this.fp.phone_override,
                        otp_code: this.fp.otp_code,
                        firebase_id_token: firebaseIdToken,
                        new_password: this.fp.new_password,
                        confirm_password: this.fp.confirm_password
                    })
                });
                const data = await res.json();
                this.showAlert(data.message, data.success);
                if (data.success) {
                    this.resetRecoveryState();
                    this.forgotOpen = false;
                }
            }
        };
    }
    </script>
</body>
</html>