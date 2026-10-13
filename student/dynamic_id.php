<?php
// student/dynamic_id.php
$page_title = 'Dynamic 30s QR ID';
require_once __DIR__ . '/../includes/header.php';
require_role(['student', 'faculty']);
require_once __DIR__ . '/../includes/sidebar.php';
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<div class="max-w-lg mx-auto" x-data="dynamicQrApp()" x-init="initQr()">
    <div class="bg-theme-card rounded-2xl shadow-xl border border-theme-border border-l-4 border-l-theme-accent overflow-hidden">
        <div class="p-5 border-b border-theme-border flex items-center justify-between">
            <div>
                <h1 class="text-base font-extrabold text-theme-text">Dynamic 30s Library Pass</h1>
                <p class="text-xs text-theme-muted mt-0.5">HMAC-SHA256 Anti-Cloning Student Token</p>
            </div>
            <span class="px-2.5 py-1 bg-emerald-500/20 border border-emerald-400/40 text-emerald-500 text-[10px] font-extrabold rounded-full animate-pulse">
                LIVE TOTP
            </span>
        </div>

        <div class="p-7 text-center">
            <div class="flex items-center justify-between mb-4">
                <div class="text-left">
                    <p class="text-lg font-extrabold text-theme-text"><?= e($_SESSION['full_name']) ?></p>
                    <p class="text-xs font-mono text-theme-muted">
                        ID: <span class="text-theme-accent font-bold"><?= e($_SESSION['school_id']) ?></span> • <?= e($_SESSION['program_code'] ?? 'Faculty') ?>
                    </p>
                </div>
                <!-- Invert QR Toggle Button -->
                <button type="button" @click="toggleInvert()"
                        :class="qrInverted ? 'bg-indigo-500 text-white' : 'bg-theme-main text-theme-muted border border-theme-border'"
                        class="px-3 py-1.5 rounded-lg text-[10px] font-extrabold uppercase transition shadow-sm">
                    <span x-text="qrInverted ? '◐ Inverted QR' : '◑ Standard QR'"></span>
                </button>
            </div>

            <!-- The QR Canvas Wrapper automatically adapts its background based on inversion state -->
            <div :class="qrInverted ? 'bg-[#0f172a] border-indigo-500' : 'bg-white border-theme-accent'"
                 class="inline-block p-5 border-4 rounded-2xl shadow-lg mb-6 transition-colors duration-300">
                <div id="qrcode" class="w-52 h-52 flex items-center justify-center"></div>
            </div>

            <div class="max-w-xs mx-auto">
                <div class="flex justify-between text-xs font-bold text-theme-muted mb-1.5">
                    <span>Auto-Refreshing Token</span>
                    <span class="font-mono text-theme-accent font-extrabold" x-text="secondsLeft + 's'"></span>
                </div>
                <div class="w-full bg-theme-main h-2.5 rounded-full overflow-hidden border border-theme-border">
                    <div class="bg-theme-accent h-full transition-all duration-1000 ease-linear"
                         :style="'width: ' + ((secondsLeft / 30) * 100) + '%'"></div>
                </div>
            </div>

            <div class="mt-6 p-4 bg-theme-main rounded-xl border border-theme-border text-left text-xs text-theme-muted space-y-1">
                <p class="font-extrabold text-theme-text">Security Verification Protocol:</p>
                <p>• Screenshots expire after 30 seconds and are rejected by the 4th Floor Help Desk webcam.</p>
                <p>• Ready for Phase 2 BCP Turnstile RFID UID linking.</p>
            </div>
        </div>
    </div>
</div>

<script>
function dynamicQrApp() {
    return {
        secondsLeft: 30,
        timer: null,
        qrInverted: false,
        lastPayload: '',

        toggleInvert() {
            this.qrInverted = !this.qrInverted;
            this.renderQr(this.lastPayload);
        },

        renderQr(payload) {
            this.lastPayload = payload;
            const container = document.getElementById('qrcode');
            container.innerHTML = '';
            new QRCode(container, {
                text: payload,
                width: 208,
                height: 208,
                // Swap colors if inverted is true
                colorDark: this.qrInverted ? "#ffffff" : "#0f172a",
                colorLight: this.qrInverted ? "#0f172a" : "#ffffff",
                correctLevel: QRCode.CorrectLevel.M
            });
        },

        async fetchToken() {
            const res = await fetch('<?= BASE_URL ?>/api/auth/generate_totp.php');
            const data = await res.json();
            if (data.success) {
                this.secondsLeft = data.seconds_left;
                this.renderQr(data.qr_payload);
            }
        },

        initQr() {
            this.fetchToken();
            this.timer = setInterval(() => {
                this.secondsLeft--;
                if (this.secondsLeft <= 0) this.fetchToken();
            }, 1000);
        }
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>