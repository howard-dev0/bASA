<?php
// includes/header.php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/logo.php';
$page_title = $page_title ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="en" data-theme="bestlink-dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title><?= e($page_title) ?> | bASA Bestlink Library</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <script>
        const DEFAULT_CUSTOM_COLORS = {
            sidebar: '#1b1464',
            main: '#0f172a',
            card: '#1e293b',
            hover: '#26354d',
            border: '#334155',
            text: '#f8fafc',
            muted: '#94a3b8',
            accent: '#0089d0'
        };

        function applyCustomCssVariables(colors) {
            const root = document.documentElement;
            root.style.setProperty('--bg-sidebar', colors.sidebar);
            root.style.setProperty('--bg-main', colors.main);
            root.style.setProperty('--bg-card', colors.card);
            root.style.setProperty('--bg-hover', colors.hover);
            root.style.setProperty('--border-color', colors.border);
            root.style.setProperty('--text-normal', colors.text);
            root.style.setProperty('--text-muted', colors.muted);
            root.style.setProperty('--accent-primary', colors.accent);
            root.style.setProperty('--accent-hover', colors.accent);
        }

        function clearCustomCssVariables() {
            const root = document.documentElement;
            ['--bg-sidebar','--bg-main','--bg-card','--bg-hover','--border-color','--text-normal','--text-muted','--accent-primary','--accent-hover']
                .forEach(prop => root.style.removeProperty(prop));
        }

        (function() {
            const savedTheme = localStorage.getItem('basa_theme') || 'bestlink-dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
            if (savedTheme === 'custom') {
                try {
                    const savedCustom = JSON.parse(localStorage.getItem('basa_custom_colors')) || DEFAULT_CUSTOM_COLORS;
                    applyCustomCssVariables(savedCustom);
                } catch (e) {
                    applyCustomCssVariables(DEFAULT_CUSTOM_COLORS);
                }
            }
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

        [data-theme="bestlink-light"] {
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

        [data-theme="discord-dark"] {
            --bg-sidebar: #2b2d31;
            --bg-main: #313338;
            --bg-card: #232428;
            --bg-hover: #35373c;
            --border-color: #3f4147;
            --text-normal: #f2f3f5;
            --text-muted: #949ba4;
            --accent-primary: #5865f2;
            --accent-hover: #4752c4;
        }

        [data-theme="light-mode"] {
            --bg-sidebar: #ffffff;
            --bg-main: #f8fafc;
            --bg-card: #ffffff;
            --bg-hover: #f1f5f9;
            --border-color: #e2e8f0;
            --text-normal: #0f172a;
            --text-muted: #64748b;
            --accent-primary: #2563eb;
            --accent-hover: #1d4ed8;
        }

        [data-theme="emerald-scholar"] {
            --bg-sidebar: #0d1f1a;
            --bg-main: #132a23;
            --bg-card: #19382f;
            --bg-hover: #21473c;
            --border-color: #2b5c4e;
            --text-normal: #ecfdf5;
            --text-muted: #6ee7b7;
            --accent-primary: #10b981;
            --accent-hover: #059669;
        }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: var(--bg-main); }
        ::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 4px; }
    </style>
</head>
<body class="bg-theme-main text-theme-text font-sans antialiased overflow-hidden"
      x-data="themeStudio()">
    <div class="h-[100dvh] w-screen flex overflow-hidden relative">