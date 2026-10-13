<?php
// includes/sidebar.php
$pdo = $GLOBALS['pdo'];
$current_role = $_SESSION['role'] ?? 'student';
$current_uri  = $_SERVER['REQUEST_URI'];

$sidebar_user = [
    'full_name'    => $_SESSION['full_name'] ?? 'User',
    'display_name' => $_SESSION['display_name'] ?? '',
    'school_id'    => $_SESSION['school_id'] ?? '',
    'photo_path'   => $_SESSION['photo_path'] ?? null
];

if (isset($_SESSION['user_id'])) {
    try {
        $stmt_sb = $pdo->prepare("SELECT full_name, display_name, school_id, photo_path FROM users WHERE id = ? LIMIT 1");
        $stmt_sb->execute([$_SESSION['user_id']]);
        if ($row_sb = $stmt_sb->fetch()) {
            $sidebar_user = $row_sb;
            $_SESSION['full_name']    = $row_sb['full_name'];
            $_SESSION['display_name'] = $row_sb['display_name'];
            $_SESSION['photo_path']   = $row_sb['photo_path'];
        }
    } catch (\Throwable $e) {}
}

$shown_name = !empty($sidebar_user['display_name']) ? $sidebar_user['display_name'] : $sidebar_user['full_name'];

$nav_links = [];

if (in_array($current_role, ['student', 'faculty'])) {
    $nav_links = [
        ['url' => BASE_URL . '/student/index.php',            'label' => 'My Dashboard',             'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
        ['url' => BASE_URL . '/student/profile.php',          'label' => 'My Profile & Security',    'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
        ['url' => BASE_URL . '/student/my_books.php',         'label' => 'My Borrowed Books',        'icon' => 'M16 4v12l-4-2-4 2V4M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z'],
        ['url' => BASE_URL . '/student/catalog.php',          'label' => 'OPAC (Online Catalog)',    'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'],
        ['url' => BASE_URL . '/student/map.php',              'label' => 'Live Interactive Map',     'icon' => 'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7l6-3 5.447 2.724A1 1 0 0121 7.618v10.764a1 1 0 01-1.447.894L15 17l-6 3z'],
        ['url' => BASE_URL . '/student/dynamic_id.php',       'label' => 'Dynamic 30s QR ID',        'icon' => 'M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z'],
        ['url' => BASE_URL . '/student/room_reservation.php', 'label' => 'Discussion Rooms A–G',     'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
        ['url' => BASE_URL . '/student/gallery_contact.php',  'label' => '3F/4F Gallery & Reach Us', 'icon' => 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z'],
    ];
} elseif ($current_role === 'librarian') {
    $nav_links = [
        ['url' => BASE_URL . '/admin/index.php',              'label' => 'Operations Overview',      'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
        ['url' => BASE_URL . '/admin/circulation_desk.php',   'label' => 'Webcam QR Scanner',        'icon' => 'M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z'],
        ['url' => BASE_URL . '/admin/catalog_manager.php',    'label' => 'Book & QR Cataloging',     'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'],
        ['url' => BASE_URL . '/student/map.php',              'label' => 'Live Interactive Map',     'icon' => 'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7l6-3 5.447 2.724A1 1 0 0121 7.618v10.764a1 1 0 01-1.447.894L15 17l-6 3z'],
        ['url' => BASE_URL . '/admin/room_monitor.php',       'label' => 'Rooms A–G Monitor',        'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
        ['url' => BASE_URL . '/student/gallery_contact.php',  'label' => '3F/4F Gallery & Box (Edit)','icon' => 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z'],
        ['url' => BASE_URL . '/student/profile.php',          'label' => 'My Profile & Security',    'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
    ];
} elseif ($current_role === 'super_admin') {
    $nav_links = [
        ['url' => BASE_URL . '/super_admin/index.php',        'label' => 'Accounts & System',        'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
        ['url' => BASE_URL . '/admin/index.php',              'label' => 'Librarian Desk View',      'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2z'],
        ['url' => BASE_URL . '/admin/circulation_desk.php',   'label' => 'Webcam QR Scanner',        'icon' => 'M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z'],
        ['url' => BASE_URL . '/admin/catalog_manager.php',    'label' => 'Catalog & Shelf Map',      'icon' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253'],
        ['url' => BASE_URL . '/student/map.php',              'label' => 'Live Interactive Map',     'icon' => 'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7l6-3 5.447 2.724A1 1 0 0121 7.618v10.764a1 1 0 01-1.447.894L15 17l-6 3z'],
        ['url' => BASE_URL . '/student/gallery_contact.php',  'label' => '3F/4F Gallery & Box (Edit)','icon' => 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z'],
        ['url' => BASE_URL . '/student/profile.php',          'label' => 'My Profile & Security',    'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
    ];
}
?>

<!-- MOBILE & TABLET BACKDROP OVERLAY -->
<div x-show="sidebarOpen" x-cloak
     @click="sidebarOpen = false"
     x-transition:enter="transition-opacity ease-linear duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition-opacity ease-linear duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 bg-black/60 backdrop-blur-xs z-30 lg:hidden"></div>

<!-- RESPONSIVE SIDEBAR -->
<aside :class="[
           sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
           theme === 'bestlink-light' ? 'text-white' : 'text-theme-text'
       ]"
       class="fixed inset-y-0 left-0 z-40 w-64 bg-theme-sidebar border-r border-theme-border flex flex-col justify-between shrink-0 select-none shadow-2xl lg:shadow-lg lg:static transition-transform duration-300 ease-in-out">
    
    <div class="overflow-y-auto">
        <div class="h-16 sm:h-20 px-5 border-b border-theme-border flex items-center justify-between bg-white">
            <a href="<?= BASE_URL ?>/index.php" class="flex items-center">
                <?php render_basa_logo('sm'); ?>
            </a>
            <button @click="sidebarOpen = false"
                    class="lg:hidden p-2 rounded-lg text-slate-600 hover:bg-slate-100">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- User Profile Card with Avatar, Display Name & Quick Edit Icon -->
        <div :class="theme === 'light-mode' ? 'bg-slate-100 border-slate-200 text-slate-900' : 'bg-black/20 border-white/10'"
             class="p-3.5 mx-3 mt-4 rounded-xl border flex items-center justify-between gap-2 group">
            <div class="flex items-center gap-3 min-w-0">
                <?php if (!empty($sidebar_user['photo_path'])): ?>
                    <img src="<?= e(BASE_URL . '/' . ltrim($sidebar_user['photo_path'], '/')) ?>"
                         alt="Avatar"
                         class="w-10 h-10 rounded-full object-cover border-2 border-theme-accent shrink-0 shadow">
                <?php else: ?>
                    <div class="w-10 h-10 rounded-full bg-theme-accent text-white font-cartoon font-bold flex items-center justify-center text-base shrink-0 shadow">
                        <?= e(strtoupper(substr($shown_name, 0, 1))) ?>
                    </div>
                <?php endif; ?>
                <div class="min-w-0">
                    <p class="text-sm font-bold truncate" title="<?= e($sidebar_user['full_name']) ?>"><?= e($shown_name) ?></p>
                    <p class="text-xs opacity-75 font-mono truncate"><?= e($sidebar_user['school_id']) ?></p>
                </div>
            </div>

            <a href="<?= BASE_URL ?>/student/profile.php"
               title="Edit Profile & Security"
               class="p-2 rounded-lg hover:bg-theme-accent hover:text-white opacity-75 group-hover:opacity-100 transition shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                </svg>
            </a>
        </div>

        <nav class="p-3 mt-1 space-y-1">
            <p class="px-3 py-1 text-[10px] font-extrabold uppercase tracking-wider opacity-60">
                Main Navigation
            </p>
            <?php foreach ($nav_links as $link): 
                $is_active = (strpos($current_uri, $link['url']) !== false);
            ?>
                <a href="<?= e($link['url']) ?>"
                   :class="theme === 'light-mode' && !<?= $is_active ? 'true' : 'false' ?> ? 'hover:bg-slate-100 text-slate-700' : ''"
                   class="flex items-center gap-3 px-3.5 py-3 lg:py-2.5 rounded-xl text-sm font-semibold transition-all duration-150 <?= $is_active ? 'bg-theme-accent text-white shadow-md' : 'opacity-85 hover:opacity-100 hover:bg-white/10' ?>">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $link['icon'] ?>"></path>
                    </svg>
                    <span class="truncate"><?= e($link['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>

    <div class="p-4 border-t" :class="theme === 'light-mode' ? 'border-slate-200' : 'border-white/10'">
        <a href="<?= BASE_URL ?>/api/auth/logout.php"
           :class="theme === 'light-mode' 
               ? 'bg-red-50 text-[#D9232A] border-red-200 hover:bg-[#D9232A] hover:text-white' 
               : 'bg-[#D9232A]/20 text-red-100 border-[#D9232A]/40 hover:bg-[#D9232A] hover:text-white'"
           class="flex items-center justify-center gap-2 w-full py-2.5 px-4 rounded-xl text-xs font-extrabold border transition duration-150 shadow-xs">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
            <span>Sign Out</span>
        </a>
    </div>
</aside>

<!-- MAIN CONTENT AREA -->
<div class="flex-1 flex flex-col h-[100dvh] w-full overflow-hidden bg-theme-main">
    <header class="h-16 px-4 sm:px-6 bg-theme-card border-b border-theme-border flex items-center justify-between gap-3 shrink-0 shadow-xs">
        <div class="flex items-center gap-3 min-w-0">
            <button @click="sidebarOpen = true"
                    aria-label="Open Menu"
                    class="lg:hidden p-2 rounded-xl bg-theme-main hover:bg-theme-hover border border-theme-border text-theme-text transition shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            <div class="min-w-0">
                <h2 class="font-extrabold text-theme-text text-sm sm:text-base truncate"><?= e($page_title ?? 'Portal') ?></h2>
                <p class="text-[11px] text-theme-muted hidden md:block truncate">Bestlink College of the Philippines • Library & Archives</p>
            </div>
        </div>

        <div class="flex items-center gap-2 shrink-0">
            <button @click="toggleDarkLight()"
                    title="Toggle Light / Dark Mode"
                    class="p-2 rounded-xl bg-theme-main hover:bg-theme-hover border border-theme-border text-theme-text transition">
                <span x-text="theme.includes('light') ? '🌙' : '☀️'"></span>
            </button>

            <button @click="themeModal = true"
                    class="px-3 py-2 rounded-xl bg-theme-main hover:bg-theme-hover border border-theme-border text-xs font-bold text-theme-text flex items-center gap-2 transition">
                <span class="w-2.5 h-2.5 rounded-full bg-theme-accent shrink-0"></span>
                <span class="hidden sm:inline">Themes & Colors</span>
                <span class="sm:hidden">Theme</span>
            </button>

            <span class="hidden xs:inline-block px-2.5 py-1.5 rounded-xl text-[10px] sm:text-[11px] font-extrabold uppercase bg-theme-accent/15 text-theme-accent border border-theme-accent/30">
                <?= e(str_replace('_', ' ', $current_role)) ?>
            </span>
        </div>
    </header>

    <main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto overflow-x-hidden">