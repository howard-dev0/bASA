<?php
// includes/map_svg.php
// Interactive 4th Floor Library SVG Map (Supports shelf highlighting & click-to-filter)
?>
<div class="w-full overflow-x-auto bg-slate-950 rounded-2xl border border-theme-border p-3 sm:p-4 shadow-inner select-none">
    <div class="min-w-[780px]">
        <!-- Map Legend Bar -->
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3 px-2 text-[11px] text-slate-300">
            <div class="flex items-center gap-4">
                <span class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded-xs bg-amber-400 animate-pulse inline-block"></span>
                    <strong class="text-amber-300">Active / Selected Shelf</strong>
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded-xs bg-sky-600/80 border border-sky-400 inline-block"></span>
                    <span>Learning Books (SH01–SH09)</span>
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded-xs bg-indigo-600/80 border border-indigo-400 inline-block"></span>
                    <span>Capstones & Theses (SH00, SH10–SH14)</span>
                </span>
            </div>
            <span class="text-slate-400 font-mono">Click any shelf (SH00–SH14) to inspect books</span>
        </div>

        <!-- 4th Floor Architectural SVG Map -->
        <svg viewBox="0 0 1000 560" class="w-full h-auto rounded-xl bg-[#0b1120] border border-slate-800">
            <!-- Grid Pattern -->
            <defs>
                <pattern id="floorGrid" width="25" height="25" patternUnits="userSpaceOnUse">
                    <path d="M 25 0 L 0 0 0 25" fill="none" stroke="#1e293b" stroke-width="0.7"/>
                </pattern>
            </defs>
            <rect width="1000" height="560" fill="url(#floorGrid)"/>

            <!-- 1. TOP-LEFT EXIT -->
            <rect x="12" y="10" width="110" height="44" rx="6" fill="#1e293b" stroke="#475569" stroke-width="2"/>
            <text x="67" y="37" text-anchor="middle" fill="#f8fafc" font-weight="800" font-size="16" letter-spacing="2">EXIT</text>

            <!-- 2. NORTH OPEN READING HUB (Tables & Chairs) -->
            <text x="455" y="82" text-anchor="middle" fill="#94a3b8" font-weight="700" font-size="11" letter-spacing="1.5">NORTH READING HUB</text>
            <?php foreach ([195, 335, 475, 615] as $tx): ?>
                <rect x="<?= $tx ?>" y="12" width="38" height="52" rx="4" fill="#1e293b" stroke="#64748b" stroke-width="1.5"/>
                <circle cx="<?= $tx - 12 ?>" cy="24" r="7" fill="#0f172a" stroke="#64748b"/>
                <circle cx="<?= $tx - 12 ?>" cy="50" r="7" fill="#0f172a" stroke="#64748b"/>
                <circle cx="<?= $tx + 50 ?>" cy="24" r="7" fill="#0f172a" stroke="#64748b"/>
                <circle cx="<?= $tx + 50 ?>" cy="50" r="7" fill="#0f172a" stroke="#64748b"/>
            <?php endforeach; ?>

            <!-- Lobby Circular Reading Tables -->
            <?php foreach ([180, 275, 365, 455, 545] as $cx): ?>
                <circle cx="<?= $cx ?>" cy="112" r="20" fill="#1e293b" stroke="#64748b" stroke-width="1.5"/>
            <?php endforeach; ?>
            <?php foreach ([190, 265] as $cy): ?>
                <circle cx="180" cy="<?= $cy ?>" r="20" fill="#1e293b" stroke="#64748b" stroke-width="1.5"/>
            <?php endforeach; ?>
            <?php foreach ([180, 265, 350, 435, 520, 605, 690, 775] as $cx): ?>
                <circle cx="<?= $cx ?>" cy="365" r="20" fill="#1e293b" stroke="#64748b" stroke-width="1.5"/>
            <?php endforeach; ?>

            <text x="260" y="245" text-anchor="middle" fill="#e2e8f0" font-weight="800" font-size="22" letter-spacing="1">LOBBY</text>

            <!-- 3. WEST READING HUB (ENCLOSED CUBICLES A–F) -->
            <g>
                <rect x="10" y="75" width="76" height="315" rx="6" fill="#0f172a" stroke="#334155" stroke-width="2"/>
                <?php 
                $cubicles = ['A', 'B', 'C', 'D', 'E', 'F'];
                foreach ($cubicles as $idx => $letter): 
                    $cy = 82 + ($idx * 50);
                ?>
                    <rect x="15" y="<?= $cy ?>" width="66" height="44" rx="4" fill="#1e293b" stroke="#0089d0" stroke-width="1.2"/>
                    <text x="48" y="<?= $cy + 26 ?>" text-anchor="middle" fill="#bae6fd" font-weight="700" font-size="10">Cubicle <?= $letter ?></text>
                <?php endforeach; ?>
                <text x="102" y="235" text-anchor="middle" fill="#94a3b8" font-weight="700" font-size="10" transform="rotate(-90 102 235)" letter-spacing="1">READING HUB (CUBICLES A–F)</text>
            </g>

            <!-- 4. CENTRAL STAIRWELL & HELP DESK / CIRCULATION COUNTER -->
            <text x="460" y="152" text-anchor="middle" fill="#f8fafc" font-weight="800" font-size="13" letter-spacing="1">4TH FLOOR LIBRARY</text>
            <rect x="330" y="162" width="260" height="168" rx="8" fill="#1e293b" stroke="#64748b" stroke-width="2"/>
            <!-- Inner Stairwell Void -->
            <rect x="355" y="180" width="210" height="108" rx="4" fill="#0f172a" stroke="#475569" stroke-width="1.5" stroke-dasharray="4 2"/>
            <text x="460" y="228" text-anchor="middle" fill="#94a3b8" font-weight="700" font-size="11">CENTRAL STAIRWELL & VOID</text>
            <text x="460" y="245" text-anchor="middle" fill="#64748b" font-weight="600" font-size="10">(Access to 3rd Floor)</text>
            <!-- Help Desk on the Bottom Edge of the Central Block -->
            <rect x="338" y="294" width="244" height="30" rx="5" fill="#0089d0" stroke="#38bdf8" stroke-width="1.5"/>
            <text x="460" y="313" text-anchor="middle" fill="#ffffff" font-weight="800" font-size="10" letter-spacing="0.8">HELP DESK / WEBCAM CIRCULATION</text>

            <!-- 5. EAST STUDY TABLES & DISCUSSION ROOMS A–G -->
            <?php foreach ([120, 195, 270] as $sy): ?>
                <rect x="695" y="<?= $sy ?>" width="44" height="44" rx="4" fill="#1e293b" stroke="#64748b" stroke-width="1.5"/>
                <rect x="785" y="<?= $sy ?>" width="44" height="44" rx="4" fill="#1e293b" stroke="#64748b" stroke-width="1.5"/>
            <?php endforeach; ?>

            <!-- Discussion Rooms A–G (Right Wall) -->
            <g>
                <rect x="875" y="45" width="112" height="345" rx="6" fill="#0f172a" stroke="#334155" stroke-width="2"/>
                <?php 
                $rooms = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];
                foreach ($rooms as $idx => $rLetter): 
                    $ry = 51 + ($idx * 48);
                ?>
                    <rect x="881" y="<?= $ry ?>" width="100" height="42" rx="4" fill="#064e3b" stroke="#10b981" stroke-width="1.2"/>
                    <text x="931" y="<?= $ry + 25 ?>" text-anchor="middle" fill="#ecfdf5" font-weight="800" font-size="11">Room <?= $rLetter ?></text>
                <?php endforeach; ?>
                <text x="862" y="220" text-anchor="middle" fill="#34d399" font-weight="700" font-size="10" transform="rotate(-90 862 220)" letter-spacing="1">DISCUSSION ROOMS A–G (6–8 PAX)</text>
            </g>

            <!-- 6. BOTTOM-LEFT: STAIR TO 3RD FLOOR & TEACHERS' SECTION ROOM -->
            <rect x="10" y="415" width="82" height="132" rx="6" fill="#0f172a" stroke="#475569" stroke-width="2"/>
            <text x="51" y="472" text-anchor="middle" fill="#94a3b8" font-weight="700" font-size="9">STAIR TO 3RD</text>
            <text x="51" y="486" text-anchor="middle" fill="#94a3b8" font-weight="700" font-size="9">FLOOR LIBRARY</text>

            <rect x="98" y="415" width="90" height="132" rx="6" fill="#1e1b4b" stroke="#6366f1" stroke-width="2"/>
            <text x="143" y="475" text-anchor="middle" fill="#e0e7ff" font-weight="800" font-size="10">TEACHERS'</text>
            <text x="143" y="490" text-anchor="middle" fill="#e0e7ff" font-weight="800" font-size="10">SECTION ROOM</text>

            <!-- 7. SOUTH WING: SEPARATED SHELVING UNITS (1 + 9 + 5 = SH00 to SH14) -->
            <text x="545" y="405" text-anchor="middle" fill="#e2e8f0" font-weight="800" font-size="11" letter-spacing="1.5">SEPARATED SHELVING UNITS (CLICK SHELF TO FILTER)</text>

            <!-- SH00: Single-Sided Capstone Shelf next to Teachers' Room -->
            <g @click="selectShelfFromMap('SH00')" class="cursor-pointer group">
                <rect x="198" y="415" width="32" height="82" rx="4"
                      :fill="isShelfHighlighted('SH00') ? '#f59e0b' : '#312e81'"
                      :stroke="isShelfHighlighted('SH00') ? '#fef08a' : '#818cf8'"
                      stroke-width="2" class="transition-all duration-200 group-hover:brightness-125"/>
                <text x="214" y="456" text-anchor="middle" fill="#ffffff" font-weight="800" font-size="10" transform="rotate(-90 214 456)">SH00</text>
                <text x="214" y="508" text-anchor="middle" fill="#a5b4fc" font-weight="700" font-size="8">CIS CAP</text>
            </g>

            <!-- SH01 to SH09: 9 Double-Sided Learning Book Shelves -->
            <?php
            $mainShelves = [
                'SH01' => ['x' => 265, 'sub' => 'GENED'],
                'SH02' => ['x' => 340, 'sub' => 'IT/CPE'],
                'SH03' => ['x' => 415, 'sub' => 'BLIS/PSY'],
                'SH04' => ['x' => 490, 'sub' => 'EDUC'],
                'SH05' => ['x' => 565, 'sub' => 'BA'],
                'SH06' => ['x' => 640, 'sub' => 'ACC/ENT'],
                'SH07' => ['x' => 715, 'sub' => 'OA/TM'],
                'SH08' => ['x' => 790, 'sub' => 'HM'],
                'SH09' => ['x' => 865, 'sub' => 'CRIM'],
            ];
            foreach ($mainShelves as $shCode => $meta):
                $sx = $meta['x'];
            ?>
                <g @click="selectShelfFromMap('<?= $shCode ?>')" class="cursor-pointer group">
                    <rect x="<?= $sx ?>" y="415" width="38" height="82" rx="4"
                          :fill="isShelfHighlighted('<?= $shCode ?>') ? '#f59e0b' : '#0369a1'"
                          :stroke="isShelfHighlighted('<?= $shCode ?>') ? '#fef08a' : '#38bdf8'"
                          stroke-width="2" class="transition-all duration-200 group-hover:brightness-125"/>
                    <!-- Center divider line showing Front (F) & Back (B) faces -->
                    <line x1="<?= $sx + 19 ?>" y1="417" x2="<?= $sx + 19 ?>" y2="495" stroke="#bae6fd" stroke-width="1" stroke-dasharray="2 2"/>
                    <text x="<?= $sx + 19 ?>" y="456" text-anchor="middle" fill="#ffffff" font-weight="800" font-size="10" transform="rotate(-90 <?= $sx + 19 ?> 456)"><?= $shCode ?></text>
                    <text x="<?= $sx + 19 ?>" y="508" text-anchor="middle" fill="#7dd3fc" font-weight="700" font-size="8"><?= $meta['sub'] ?></text>
                </g>
            <?php endforeach; ?>

            <!-- SH10 to SH14: 5 Single-Sided Horizontal Capstone Shelves along Bottom Wall -->
            <?php
            $bottomShelves = [
                'SH10' => ['x' => 215, 'sub' => 'EDUC-A CAP'],
                'SH11' => ['x' => 360, 'sub' => 'BSED CAP'],
                'SH12' => ['x' => 505, 'sub' => 'CBA CAP'],
                'SH13' => ['x' => 650, 'sub' => 'CHTM CAP'],
                'SH14' => ['x' => 795, 'sub' => 'CRIM/PSY CAP'],
            ];
            foreach ($bottomShelves as $bCode => $bMeta):
                $bx = $bMeta['x'];
            ?>
                <g @click="selectShelfFromMap('<?= $bCode ?>')" class="cursor-pointer group">
                    <rect x="<?= $bx ?>" y="520" width="115" height="28" rx="4"
                          :fill="isShelfHighlighted('<?= $bCode ?>') ? '#f59e0b' : '#312e81'"
                          :stroke="isShelfHighlighted('<?= $bCode ?>') ? '#fef08a' : '#818cf8'"
                          stroke-width="2" class="transition-all duration-200 group-hover:brightness-125"/>
                    <text x="<?= $bx + 57 ?>" y="538" text-anchor="middle" fill="#ffffff" font-weight="800" font-size="10">
                        <?= $bCode ?> (<?= $bMeta['sub'] ?>)
                    </text>
                </g>
            <?php endforeach; ?>
        </svg>
    </div>
</div>