<?php // includes/footer.php ?>
    </main>
</div>

<!-- THEME & CUSTOM COLOR BUILDER MODAL -->
<div x-show="themeModal" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-xs p-3 sm:p-4"
     x-transition>
    <div @click.away="themeModal = false" class="w-full max-w-xl bg-theme-card border border-theme-border rounded-2xl shadow-2xl overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-theme-border flex items-center justify-between">
            <div>
                <h3 class="text-sm sm:text-base font-extrabold text-theme-text">Appearance & Theme Customizer</h3>
                <p class="text-xs text-theme-muted mt-0.5">Select an official Bestlink theme, Discord theme, or customize your own</p>
            </div>
            <button @click="themeModal = false" class="w-8 h-8 rounded-lg bg-theme-hover text-theme-muted hover:text-theme-text font-bold">&times;</button>
        </div>

        <div class="px-4 sm:px-5 pt-3 border-b border-theme-border flex gap-6 text-xs font-extrabold uppercase tracking-wider">
            <button @click="studioTab = 'presets'"
                    :class="studioTab === 'presets' ? 'border-b-2 border-theme-accent text-theme-accent pb-2.5' : 'text-theme-muted pb-2.5'">
                Preset Themes (5)
            </button>
            <button @click="studioTab = 'custom'; activateCustomTheme()"
                    :class="studioTab === 'custom' ? 'border-b-2 border-theme-accent text-theme-accent pb-2.5' : 'text-theme-muted pb-2.5'">
                Custom Color Picker
            </button>
        </div>

        <div x-show="studioTab === 'presets'" class="p-4 sm:p-5 grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-[65dvh] overflow-y-auto">
            <button @click="setTheme('bestlink-dark')"
                    :class="theme === 'bestlink-dark' ? 'ring-2 ring-[#0089d0]' : ''"
                    class="p-4 rounded-xl bg-[#0f172a] text-white border border-slate-700 text-left">
                <p class="text-sm font-extrabold">Bestlink Midnight (Dark)</p>
                <p class="text-xs text-slate-400">Deep Indigo, Sky Blue & Crimson</p>
            </button>
            <button @click="setTheme('bestlink-light')"
                    :class="theme === 'bestlink-light' ? 'ring-2 ring-[#0089d0]' : ''"
                    class="p-4 rounded-xl bg-[#f0f7ff] text-slate-900 border border-sky-200 text-left">
                <p class="text-sm font-extrabold">Bestlink Crest (Light)</p>
                <p class="text-xs text-slate-600">Indigo Sidebar & Sky Blue Stage</p>
            </button>
            <button @click="setTheme('discord-dark')"
                    :class="theme === 'discord-dark' ? 'ring-2 ring-[#5865f2]' : ''"
                    class="p-4 rounded-xl bg-[#313338] text-white border border-zinc-700 text-left">
                <p class="text-sm font-extrabold">Discord Dark</p>
                <p class="text-xs text-zinc-400">Classic Ash Grey & Blurple</p>
            </button>
            <button @click="setTheme('light-mode')"
                    :class="theme === 'light-mode' ? 'ring-2 ring-blue-600' : ''"
                    class="p-4 rounded-xl bg-white text-slate-900 border border-slate-200 text-left">
                <p class="text-sm font-extrabold">Clean Daylight</p>
                <p class="text-xs text-slate-500">Crisp White & Royal Blue</p>
            </button>
            <button @click="setTheme('emerald-scholar')"
                    :class="theme === 'emerald-scholar' ? 'ring-2 ring-emerald-400' : ''"
                    class="p-4 rounded-xl bg-[#132a23] text-emerald-50 border border-emerald-800 text-left sm:col-span-2">
                <p class="text-sm font-extrabold">Emerald Scholar</p>
                <p class="text-xs text-emerald-300/80">Eye-friendly forest tones for long thesis reading sessions</p>
            </button>
        </div>

        <div x-show="studioTab === 'custom'" x-cloak class="p-4 sm:p-5 space-y-4 max-h-[65dvh] overflow-y-auto">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <label class="flex items-center justify-between p-3 rounded-xl bg-theme-main border border-theme-border cursor-pointer">
                    <span class="text-xs font-bold text-theme-text">Accent Color</span>
                    <input type="color" x-model="customColors.accent" @input="updateCustomColor()" class="w-9 h-9 rounded cursor-pointer bg-transparent border-0">
                </label>
                <label class="flex items-center justify-between p-3 rounded-xl bg-theme-main border border-theme-border cursor-pointer">
                    <span class="text-xs font-bold text-theme-text">Sidebar Background</span>
                    <input type="color" x-model="customColors.sidebar" @input="updateCustomColor()" class="w-9 h-9 rounded cursor-pointer bg-transparent border-0">
                </label>
                <label class="flex items-center justify-between p-3 rounded-xl bg-theme-main border border-theme-border cursor-pointer">
                    <span class="text-xs font-bold text-theme-text">Main Background</span>
                    <input type="color" x-model="customColors.main" @input="updateCustomColor()" class="w-9 h-9 rounded cursor-pointer bg-transparent border-0">
                </label>
                <label class="flex items-center justify-between p-3 rounded-xl bg-theme-main border border-theme-border cursor-pointer">
                    <span class="text-xs font-bold text-theme-text">Cards & Tables</span>
                    <input type="color" x-model="customColors.card" @input="updateCustomColor()" class="w-9 h-9 rounded cursor-pointer bg-transparent border-0">
                </label>
                <label class="flex items-center justify-between p-3 rounded-xl bg-theme-main border border-theme-border cursor-pointer">
                    <span class="text-xs font-bold text-theme-text">Primary Text</span>
                    <input type="color" x-model="customColors.text" @input="updateCustomColor()" class="w-9 h-9 rounded cursor-pointer bg-transparent border-0">
                </label>
                <label class="flex items-center justify-between p-3 rounded-xl bg-theme-main border border-theme-border cursor-pointer">
                    <span class="text-xs font-bold text-theme-text">Borders</span>
                    <input type="color" x-model="customColors.border" @input="customColors.hover = customColors.border; updateCustomColor()" class="w-9 h-9 rounded cursor-pointer bg-transparent border-0">
                </label>
            </div>
            <button type="button" @click="resetCustomColors()" class="text-xs font-bold text-red-500 hover:underline">
                ↺ Reset to Bestlink Default Colors
            </button>
        </div>

        <div class="px-4 sm:px-5 py-3 bg-theme-main border-t border-theme-border flex justify-end">
            <button @click="themeModal = false" class="px-5 py-2 rounded-xl bg-theme-accent text-white text-xs font-bold shadow">Done</button>
        </div>
    </div>
</div>

<script>
function themeStudio() {
    let savedCustom = DEFAULT_CUSTOM_COLORS;
    try {
        const parsed = JSON.parse(localStorage.getItem('basa_custom_colors'));
        if (parsed) savedCustom = { ...DEFAULT_CUSTOM_COLORS, ...parsed };
    } catch (e) {}

    return {
        sidebarOpen: false,
        theme: localStorage.getItem('basa_theme') || 'bestlink-dark',
        themeModal: false,
        studioTab: (localStorage.getItem('basa_theme') === 'custom') ? 'custom' : 'presets',
        customColors: savedCustom,
        setTheme(newTheme) {
            this.theme = newTheme;
            localStorage.setItem('basa_theme', newTheme);
            clearCustomCssVariables();
            document.documentElement.setAttribute('data-theme', newTheme);
        },
        activateCustomTheme() {
            this.theme = 'custom';
            localStorage.setItem('basa_theme', 'custom');
            document.documentElement.setAttribute('data-theme', 'custom');
            applyCustomCssVariables(this.customColors);
        },
        updateCustomColor() {
            this.theme = 'custom';
            localStorage.setItem('basa_theme', 'custom');
            localStorage.setItem('basa_custom_colors', JSON.stringify(this.customColors));
            document.documentElement.setAttribute('data-theme', 'custom');
            applyCustomCssVariables(this.customColors);
        },
        resetCustomColors() {
            this.customColors = { ...DEFAULT_CUSTOM_COLORS };
            this.updateCustomColor();
        },
        toggleDarkLight() {
            this.setTheme(this.theme.includes('light') ? 'bestlink-dark' : 'bestlink-light');
        }
    };
}
</script>
</div>
</body>
</html>