<?php
// includes/logo.php
function render_basa_logo(string $size = 'md'): void {
    $dimensions = [
        'sm' => 'h-8',
        'md' => 'h-11',
        'lg' => 'h-16'
    ];
    $heightClass = $dimensions[$size] ?? 'h-11';
    ?>
    <div class="inline-flex items-baseline select-none group">
        <svg class="<?= $heightClass ?> w-auto transition-transform duration-300 group-hover:scale-105" viewBox="0 0 350 100" fill="none" xmlns="http://www.w3.org/2000/svg">
            <!-- Vertical Rounded Stem of 'b' (Bestlink Deep Indigo) -->
            <path d="M22 14V82" stroke="#1B1464" stroke-width="20" stroke-linecap="round"/>
            
            <!-- Tri-Color Circular Bowl of 'b' (Bestlink Crest Silver, Sky Blue, Crimson Red) -->
            <path d="M35 39 A 24 24 0 0 0 35 77" stroke="#CBD5E1" stroke-width="18" stroke-linecap="round"/>
            <path d="M35 39 A 24 24 0 0 1 74 58" stroke="#0089D0" stroke-width="18" stroke-linecap="round"/>
            <path d="M74 58 A 24 24 0 0 1 35 77" stroke="#D9232A" stroke-width="18" stroke-linecap="round"/>

            <!-- Bubbly Cartoonish 'ASA' (Bestlink Deep Indigo) -->
            <text x="92" y="83" font-family="'Fredoka', 'Arial Rounded MT Bold', sans-serif" font-weight="700" font-size="82" fill="#1B1464" letter-spacing="-2">
                ASA
            </text>

            <!-- Subscript 'BCP' (Bestlink Sky Blue) -->
            <text x="262" y="83" font-family="'Fredoka', 'Arial Rounded MT Bold', sans-serif" font-weight="700" font-size="38" fill="#0089D0" letter-spacing="0">
                BCP
            </text>
        </svg>
    </div>
    <?php
}