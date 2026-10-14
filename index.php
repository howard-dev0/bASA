<?php
// index.php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/includes/logo.php';

// If already logged in, redirect to respective dashboards
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if (in_array($_SESSION['role'], ['super_admin', 'librarian'])) {
        header('Location: ' . BASE_URL . '/admin/index.php');
        exit();
    } else {
        header('Location: ' . BASE_URL . '/student/index.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>bASA | Bestlink College of the Philippines Library</title>
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
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-[#0f172a] text-slate-100 font-sans antialiased selection:bg-[#0089d0] selection:text-white flex flex-col min-h-screen">

    <!-- Navigation Bar -->
    <nav class="border-b border-slate-800 bg-[#0b1120]/80 backdrop-blur-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
            <div class="bg-white px-4 py-2 rounded-xl shadow-lg border border-slate-200">
                <?php render_basa_logo('sm'); ?>
            </div>
            <div class="flex items-center gap-4">
                <a href="https://bcp.edu.ph" target="_blank" class="hidden sm:block text-sm font-bold text-slate-400 hover:text-white transition">About BCP</a>
                <a href="<?= BASE_URL ?>/login.php" class="px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-white text-sm font-extrabold shadow transition">
                    Sign In to Portal
                </a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <main class="flex-1">
        <section class="relative px-6 py-20 sm:py-32 flex flex-col items-center text-center overflow-hidden">
            <!-- Background Glow -->
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-[#0089d0] rounded-full blur-[120px] opacity-20 pointer-events-none"></div>

            <span class="px-3 py-1 rounded-full bg-[#0089d0]/20 text-[#38bdf8] border border-[#0089d0]/40 text-xs font-extrabold uppercase tracking-wider mb-6 relative z-10">
                3rd & 4th Floor Academic Building
            </span>
            <h1 class="text-4xl sm:text-6xl md:text-7xl font-extrabold tracking-tight text-white mb-6 relative z-10 max-w-4xl leading-tight">
                The Digital Gateway to the <br/> <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#38bdf8] to-[#0089d0]">Bestlink Library & Archives</span>
            </h1>
            <p class="text-base sm:text-lg text-slate-400 max-w-2xl mb-10 relative z-10 leading-relaxed">
                Experience seamless learning with bASA's dynamic 30-second QR passes, real-time OPAC, and automated Discussion Room reservations tailored for BCP students and faculty.
            </p>

            <div class="flex flex-col sm:flex-row items-center gap-4 relative z-10">
                <a href="<?= BASE_URL ?>/login.php" class="w-full sm:w-auto px-8 py-3.5 rounded-xl bg-[#0089d0] hover:bg-[#0271ab] text-white text-sm font-extrabold shadow-[0_0_20px_rgba(0,137,208,0.4)] transition">
                    Access Library Portal →
                </a>
                <a href="https://bcp.edu.ph/enrollment" target="_blank" class="w-full sm:w-auto px-8 py-3.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 text-sm font-extrabold shadow-[0_0_20px_rgba(16,185,129,0.2)] transition flex items-center justify-center gap-2">
                    🎓 Enroll to Bestlink
                </a>
            </div>
        </section>

        <!-- Library Showcase Grid -->
        <section class="max-w-7xl mx-auto px-6 py-16">
            <div class="text-center mb-12">
                <h2 class="text-2xl sm:text-3xl font-extrabold text-white">Modern Facilities for Modern Scholars</h2>
                <p class="text-sm text-slate-400 mt-2">Explore the resources available on our campus</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Card 1 -->
                <div class="bg-[#1e293b] border border-slate-700 rounded-3xl p-8 hover:-translate-y-1 transition duration-300 shadow-xl">
                    <div class="w-12 h-12 rounded-2xl bg-sky-500/20 text-sky-400 flex items-center justify-center text-2xl mb-6 border border-sky-500/30">📚</div>
                    <h3 class="text-xl font-extrabold text-white mb-2">15 Dedicated Shelves</h3>
                    <p class="text-sm text-slate-400 leading-relaxed">
                        Access thousands of academic learning books across 13 domains, meticulously mapped from SH01 to SH09 on the 4th floor.
                    </p>
                </div>
                <!-- Card 2 -->
                <div class="bg-[#1e293b] border border-slate-700 rounded-3xl p-8 hover:-translate-y-1 transition duration-300 shadow-xl">
                    <div class="w-12 h-12 rounded-2xl bg-indigo-500/20 text-indigo-400 flex items-center justify-center text-2xl mb-6 border border-indigo-500/30">🎓</div>
                    <h3 class="text-xl font-extrabold text-white mb-2">Capstone Archives</h3>
                    <p class="text-sm text-slate-400 leading-relaxed">
                        Browse top-tier research, theses, and capstone projects from 23 BCP degree programs organized securely in SH00 and SH10–SH14.
                    </p>
                </div>
                <!-- Card 3 -->
                <div class="bg-[#1e293b] border border-slate-700 rounded-3xl p-8 hover:-translate-y-1 transition duration-300 shadow-xl">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-2xl mb-6 border border-emerald-500/30">👥</div>
                    <h3 class="text-xl font-extrabold text-white mb-2">Discussion Rooms A–G</h3>
                    <p class="text-sm text-slate-400 leading-relaxed">
                        Collaborate in 7 fully-equipped, sound-isolated discussion rooms. Check-in seamlessly with your group of 6–8 members using the bASA portal.
                    </p>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-800 bg-[#0b1120] py-8 text-center mt-12">
        <p class="text-xs font-bold text-slate-500 uppercase tracking-widest">Bestlink College of the Philippines</p>
        <p class="text-[10px] text-slate-600 mt-2">© <?= date('Y') ?> bASA: Bestlink Automated System for Archives. All rights reserved.</p>
    </footer>

</body>
</html>