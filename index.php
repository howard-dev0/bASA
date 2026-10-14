<?php
// index.php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/logo.php';

$is_logged_in = isset($_SESSION['user_id']) && isset($_SESSION['role']);
$dashboard_url = BASE_URL . '/login.php';

if ($is_logged_in) {
    $dashboard_url = match ($_SESSION['role']) {
        'super_admin' => BASE_URL . '/super_admin/index.php',
        'librarian'   => BASE_URL . '/admin/index.php',
        default       => BASE_URL . '/student/index.php',
    };

    // Automatically redirect after login unless user explicitly clicked 'Home' (?home=1)
    if (!isset($_GET['home'])) {
        header('Location: ' . $dashboard_url);
        exit();
    }
}

$pdo = $GLOBALS['pdo'] ?? null;
$stats = [
    'titles' => 65,
    'copies' => 70,
    'shelves' => 15,
    'rooms' => 7
];

if ($pdo) {
    try {
        $stats['titles'] = (int) $pdo->query("SELECT COUNT(*) FROM catalog_items")->fetchColumn();
        $stats['copies'] = (int) $pdo->query("SELECT COUNT(*) FROM item_accessions")->fetchColumn();
    } catch (\Throwable $e) {}
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="bestlink-dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>bASA | Bestlink Automated System for Archives</title>

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
                    }
                }
            }
        }
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }

        /* Authentic Bestlink College Royal Indigo, Sky Blue & Crimson Architectural Backdrop */
        .bcp-hero-bg {
            background-color: #080b24;
            background-image:
                radial-gradient(at 12% 18%, rgba(0, 137, 208, 0.34) 0px, transparent 50%),
                radial-gradient(at 88% 15%, rgba(217, 35, 42, 0.25) 0px, transparent 45%),
                radial-gradient(at 50% 85%, rgba(27, 20, 100, 0.85) 0px, transparent 60%),
                linear-gradient(135deg, #070a20 0%, #130f40 48%, #0c1938 100%);
        }

        [data-theme="bestlink-light"] .bcp-hero-bg,
        [data-theme="light-mode"] .bcp-hero-bg {
            background-color: #f0f7ff;
            background-image:
                radial-gradient(at 10% 15%, rgba(0, 137, 208, 0.18) 0px, transparent 50%),
                radial-gradient(at 90% 15%, rgba(217, 35, 42, 0.12) 0px, transparent 45%),
                radial-gradient(at 50% 90%, rgba(27, 20, 100, 0.14) 0px, transparent 60%),
                linear-gradient(135deg, #e0f2fe 0%, #f0f7ff 50%, #eef2ff 100%);
        }
    </style>
</head>
<body x-data="{
          theme: localStorage.getItem('basa_theme') || 'bestlink-dark',
          toggleTheme() {
              this.theme = this.theme.includes('light') ? 'bestlink-dark' : 'bestlink-light';
              localStorage.setItem('basa_theme', this.theme);
              document.documentElement.setAttribute('data-theme', this.theme);
          }
      }"
      :class="theme.includes('light') ? 'text-slate-900' : 'text-slate-100'"
      class="bcp-hero-bg font-sans min-h-screen flex flex-col justify-between relative overflow-x-hidden selection:bg-[#0089d0] selection:text-white">

    <!-- ARCHITECTURAL BESTLINK CAMPUS & LIBRARY WATERMARK SVG BACKDROP -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden z-0">
        <!-- Diagonal Bestlink Crest Ribbon Accents -->
        <div class="absolute -top-32 -left-32 w-96 h-96 rounded-full bg-[#0089d0]/15 blur-3xl"></div>
        <div class="absolute top-1/3 -right-32 w-96 h-96 rounded-full bg-[#D9232A]/15 blur-3xl"></div>

        <!-- Architectural Campus Building & Library Arches Silhouette -->
        <svg class="absolute bottom-0 inset-x-0 w-full h-auto max-h-[55vh] opacity-15" viewBox="0 0 1440 420" fill="none" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
            <defs>
                <linearGradient id="bcpArchGrad" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#0089d0" stop-opacity="0.8"/>
                    <stop offset="100%" stop-color="#1b1464" stop-opacity="0.1"/>
                </linearGradient>
            </defs>
            <!-- Background Campus Skyline -->
            <path d="M0 420V210L120 210V150L280 150V210L420 210V95L580 50L720 20L860 50L1020 95V210L1160 210V150L1320 150V210L1440 210V420H0Z" fill="url(#bcpArchGrad)"/>
            <!-- Central Bestlink Academic Building Columns & 4th Floor Library Windows -->
            <?php for ($col = 450; $col <= 970; $col += 40): ?>
                <rect x="<?= $col ?>" y="120" width="16" height="280" fill="#38bdf8" fill-opacity="0.25"/>
            <?php endfor; ?>
            <!-- 3rd & 4th Floor Horizontal Architectural Ledges -->
            <rect x="420" y="110" width="600" height="10" fill="#f59e0b" fill-opacity="0.5"/>
            <rect x="420" y="210" width="600" height="8" fill="#0089d0" fill-opacity="0.5"/>
            <rect x="420" y="300" width="600" height="8" fill="#D9232A" fill-opacity="0.5"/>
        </svg>
    </div>

    <!-- TOP NAVIGATION HEADER -->
    <header class="relative z-20 w-full max-w-7xl mx-auto px-4 sm:px-8 pt-5 pb-3 flex items-center justify-between gap-4">
        <a href="<?= BASE_URL ?>/index.php?home=1" class="bg-white/95 backdrop-blur-md px-4 py-2 rounded-2xl shadow-lg border border-sky-200/60 transition hover:scale-[1.02]">
            <?php render_basa_logo('sm'); ?>
        </a>

        <div class="flex items-center gap-2.5">
            <button type="button" @click="toggleTheme()"
                    :class="theme.includes('light') ? 'bg-white/90 text-slate-900 border-sky-200' : 'bg-slate-900/80 text-white border-slate-700'"
                    class="px-3.5 py-2.5 rounded-xl border backdrop-blur-md text-xs font-extrabold shadow-md transition flex items-center gap-2">
                <span x-text="theme.includes('light') ? '🌙 Dark' : '☀️ Light'"></span>
            </button>

            <?php if ($is_logged_in): ?>
                <a href="<?= e($dashboard_url) ?>"
                   class="px-5 py-2.5 rounded-xl bg-[#0089d0] hover:bg-[#0271ab] text-white text-xs font-extrabold shadow-lg transition">
                    Open My Dashboard →
                </a>
            <?php else: ?>
                <a href="<?= BASE_URL ?>/login.php"
                   class="px-5 py-2.5 rounded-xl bg-[#0089d0] hover:bg-[#0271ab] text-white text-xs font-extrabold shadow-lg transition">
                    Sign In to Portal →
                </a>
            <?php endif; ?>
        </div>
    </header>

    <!-- MAIN HERO SECTION -->
    <main class="relative z-10 flex-1 flex items-center justify-center px-4 sm:px-8 py-10">
        <div class="w-full max-w-6xl grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">

            <!-- Left 7 Cols: Bestlink Library Branding & CTA -->
            <div class="lg:col-span-7 space-y-6 text-center lg:text-left">
                <div class="inline-flex flex-wrap items-center justify-center lg:justify-start gap-2 px-3.5 py-1.5 rounded-full bg-[#0089d0]/20 border border-[#0089d0]/40 text-xs font-extrabold">
                    <span class="px-2 py-0.5 rounded-full bg-[#D9232A] text-white text-[10px] uppercase">BCP Official</span>
                    <span :class="theme.includes('light') ? 'text-[#1b1464]' : 'text-sky-300'">
                        3rd &amp; 4th Floor Academic Library &amp; Archives
                    </span>
                </div>

                <h1 class="text-3xl sm:text-5xl font-extrabold tracking-tight leading-tight">
                    Bestlink Automated System for
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#38bdf8] via-[#0089d0] to-[#f43f5e]">
                        Archives &amp; Literature
                    </span>
                </h1>

                <p :class="theme.includes('light') ? 'text-slate-700' : 'text-slate-300'"
                   class="text-sm sm:text-base leading-relaxed max-w-2xl mx-auto lg:mx-0">
                    Explore the interactive <strong>4th Floor Digital Twin Map</strong> (<span class="font-mono font-bold text-[#0089d0]">SH00–SH14</span>), borrow books and undergraduate theses with your <strong>30-second Dynamic TOTP QR Pass</strong>, and reserve <strong>Discussion Rooms A–G</strong>.
                </p>

                <div class="flex flex-wrap items-center justify-center lg:justify-start gap-3 pt-2">
                    <?php if ($is_logged_in): ?>
                        <a href="<?= e($dashboard_url) ?>"
                           class="px-6 py-3.5 rounded-2xl bg-[#0089d0] hover:bg-[#0271ab] text-white font-extrabold text-xs sm:text-sm shadow-xl transition flex items-center gap-2">
                            <span>🚀 Return to <?= e(strtoupper(str_replace('_', ' ', $_SESSION['role']))) ?> Dashboard</span>
                        </a>
                        <a href="<?= BASE_URL ?>/student/catalog.php"
                           :class="theme.includes('light') ? 'bg-white text-slate-900 border-sky-200 hover:bg-sky-50' : 'bg-slate-900/80 text-white border-slate-700 hover:bg-slate-800'"
                           class="px-5 py-3.5 rounded-2xl border font-extrabold text-xs sm:text-sm shadow-md transition">
                            🗺️ Browse 4F OPAC &amp; Map
                        </a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/login.php"
                           class="px-6 py-3.5 rounded-2xl bg-[#0089d0] hover:bg-[#0271ab] text-white font-extrabold text-xs sm:text-sm shadow-xl transition flex items-center gap-2">
                            <span>🔐 Sign In with BCP ID</span>
                            <span>→</span>
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Live Institutional Metrics Strip -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-4">
                    <div :class="theme.includes('light') ? 'bg-white/90 border-sky-200' : 'bg-slate-900/75 border-slate-800'"
                         class="p-4 rounded-2xl border backdrop-blur-md shadow-md">
                        <p class="text-2xl font-extrabold font-mono text-[#0089d0]"><?= (int)$stats['titles'] ?>+</p>
                        <p class="text-[11px] font-bold opacity-75 mt-0.5">Books, Theses &amp; Capstones</p>
                    </div>
                    <div :class="theme.includes('light') ? 'bg-white/90 border-sky-200' : 'bg-slate-900/75 border-slate-800'"
                         class="p-4 rounded-2xl border backdrop-blur-md shadow-md">
                        <p class="text-2xl font-extrabold font-mono text-emerald-400">SH00–SH14</p>
                        <p class="text-[11px] font-bold opacity-75 mt-0.5">15 Mapped 4F Shelves</p>
                    </div>
                    <div :class="theme.includes('light') ? 'bg-white/90 border-sky-200' : 'bg-slate-900/75 border-slate-800'"
                         class="p-4 rounded-2xl border backdrop-blur-md shadow-md">
                        <p class="text-2xl font-extrabold font-mono text-amber-400">Rooms A–G</p>
                        <p class="text-[11px] font-bold opacity-75 mt-0.5">6–8 Pax Group Study</p>
                    </div>
                    <div :class="theme.includes('light') ? 'bg-white/90 border-sky-200' : 'bg-slate-900/75 border-slate-800'"
                         class="p-4 rounded-2xl border backdrop-blur-md shadow-md">
                        <p class="text-2xl font-extrabold font-mono text-[#f43f5e]">30s TOTP</p>
                        <p class="text-[11px] font-bold opacity-75 mt-0.5">Anti-Clone QR Pass</p>
                    </div>
                </div>
            </div>

            <!-- Right 5 Cols: Bestlink Crest Showcase Card -->
            <div class="lg:col-span-5">
                <div :class="theme.includes('light') ? 'bg-white/95 border-sky-200 text-slate-900' : 'bg-slate-900/85 border-slate-700/80 text-white'"
                     class="rounded-3xl border-2 p-6 sm:p-7 shadow-2xl backdrop-blur-xl space-y-5">
                    <div class="bg-white p-4 rounded-2xl shadow-inner border border-slate-200 flex justify-center">
                        <?php render_basa_logo('md'); ?>
                    </div>

                    <div class="space-y-3 text-xs">
                        <div class="p-3.5 rounded-xl bg-[#0089d0]/10 border border-[#0089d0]/30 flex items-start gap-3">
                            <span class="text-lg">🗺️</span>
                            <div>
                                <p class="font-extrabold">4th Floor Shelf Coordinate Pinning</p>
                                <p class="opacity-75 mt-0.5">Instant `[Shelf]-[Side]-[Section]-[Level]` locator across all 13 Book Domains and 23 BCP Degree Programs.</p>
                            </div>
                        </div>

                        <div class="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-start gap-3">
                            <span class="text-lg">📷</span>
                            <div>
                                <p class="font-extrabold">Closed-Loop Webcam QR Circulation</p>
                                <p class="opacity-75 mt-0.5">Optical Help Desk verification with automatic overdue calculation and damaged book inspection.</p>
                            </div>
                        </div>

                        <div class="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/30 flex items-start gap-3">
                            <span class="text-lg">🔐</span>
                            <div>
                                <p class="font-extrabold">Multi-Channel Account Security</p>
                                <p class="opacity-75 mt-0.5">Protected by Profile Lock PINs, MailerSend Email OTP, Firebase SMS OTP, and Google Authenticator 2FA.</p>
                            </div>
                        </div>
                    </div>

                    <a href="<?= $is_logged_in ? e($dashboard_url) : BASE_URL . '/login.php' ?>"
                       class="block w-full py-3.5 px-5 rounded-2xl bg-gradient-to-r from-[#1b1464] via-[#0089d0] to-[#0271ab] hover:brightness-110 text-white text-center font-extrabold text-xs shadow-lg transition">
                        <?= $is_logged_in ? 'Continue to bASA Portal →' : 'Access bASA Library Portal →' ?>
                    </a>
                </div>
            </div>

        </div>
    </main>

    <!-- FOOTER -->
    <footer class="relative z-10 w-full max-w-7xl mx-auto px-4 sm:px-8 py-4 text-center sm:flex sm:items-center sm:justify-between text-[11px] opacity-75">
        <p>© <?= date('Y') ?> Bestlink College of the Philippines • bASA Library &amp; Archives</p>
        <p class="font-mono mt-1 sm:mt-0">3rd &amp; 4th Floor Academic Building • Quirino Highway, Novaliches, QC</p>
    </footer>

</body>
</html>