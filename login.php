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
<body x-data="loginHandler()" :class="lightMode ? 'bg-[#f0f7ff] text-slate-900' : 'bg-[#0f172a] text-slate-100'" class="min-h-screen flex items-center justify-center p-4 font-['Plus_Jakarta_Sans'] transition-colors duration-300">

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
            <div class="mb-4 p-3 bg-amber-500/15 border border-amber-500/40 text-amber-400 rounded-xl text-xs font-semibold">
                Session timed out after 15 minutes of inactivity.
            </div>
        <?php endif; ?>

        <div x-show="errorMsg" x-cloak class="mb-4 p-3 bg-red-500/15 border border-red-500/40 text-red-400 rounded-xl text-xs font-semibold" x-text="errorMsg"></div>

        <form @submit.prevent="submitLogin" class="space-y-4">
            <div>
                <label class="block text-[11px] font-extrabold uppercase tracking-wider mb-1.5 opacity-80">BCP School ID or Email</label>
                <input type="text" x-model="identifier" required placeholder="e.g. 240103537 or LIB-001"
                       :class="lightMode ? 'bg-slate-50 border-slate-300 text-slate-900' : 'bg-[#0f172a] border-slate-700 text-white'"
                       class="w-full px-4 py-3 rounded-xl border focus:border-[#0089D0] outline-none text-sm font-mono">
            </div>

            <div>
                <label class="block text-[11px] font-extrabold uppercase tracking-wider mb-1.5 opacity-80">Password</label>
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
                errorMsg: '',
                lightMode: (localStorage.getItem('basa_theme') || '').includes('light'),
                toggleMode() {
                    this.lightMode = !this.lightMode;
                    localStorage.setItem('basa_theme', this.lightMode ? 'bestlink-light' : 'bestlink-dark');
                },
                fillDemo(id) {
                    this.identifier = id;
                    this.password = 'password';
                    this.errorMsg = '';
                },
                async submitLogin() {
                    this.loading = true;
                    this.errorMsg = '';
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
                            this.errorMsg = data.message;
                        }
                    } catch (err) {
                        this.errorMsg = 'Server connection error. Check Apache & MySQL.';
                    } finally {
                        this.loading = false;
                    }
                }
            }
        }
    </script>
</body>
</html>