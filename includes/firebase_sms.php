<?php
// includes/firebase_sms.php
require_once __DIR__ . '/../config/constants.php';
?>
<!-- Visible high-z-index mount wrapper so Google reCAPTCHA challenges never get blocked by modals -->
<div id="firebase-recaptcha-wrapper" class="fixed bottom-3 right-3 z-[200] pointer-events-auto"></div>

<script type="module">
    import { initializeApp } from "https://www.gstatic.com/firebasejs/13.0.0/firebase-app.js";
    import { getAuth, RecaptchaVerifier, signInWithPhoneNumber } from "https://www.gstatic.com/firebasejs/13.0.0/firebase-auth.js";

    const firebaseConfig = {
        apiKey: "<?= e(FIREBASE_API_KEY) ?>",
        authDomain: "<?= e(FIREBASE_AUTH_DOMAIN) ?>",
        projectId: "<?= e(FIREBASE_PROJECT_ID) ?>",
        storageBucket: "<?= e(FIREBASE_STORAGE_BUCKET) ?>",
        messagingSenderId: "<?= e(FIREBASE_MESSAGING_SENDER_ID) ?>",
        appId: "<?= e(FIREBASE_APP_ID) ?>"
    };

    const app = initializeApp(firebaseConfig);
    const auth = getAuth(app);
    auth.useDeviceLanguage();

    window.basaSmsConfirmation = null;
    window.basaRecaptchaVerifier = null;

    function buildFreshRecaptchaVerifier() {
        const wrapper = document.getElementById('firebase-recaptcha-wrapper');
        if (!wrapper) return null;

        if (window.basaRecaptchaVerifier) {
            try { window.basaRecaptchaVerifier.clear(); } catch (e) {}
            window.basaRecaptchaVerifier = null;
        }

        // Always create a fresh child div because verifier.clear() destroys the old element
        wrapper.innerHTML = '';
        const slot = document.createElement('div');
        slot.id = 'firebase-recaptcha-slot-' + Date.now();
        wrapper.appendChild(slot);

        window.basaRecaptchaVerifier = new RecaptchaVerifier(auth, slot, {
            size: 'invisible',
            callback: () => {},
            'expired-callback': () => {}
        });

        return window.basaRecaptchaVerifier;
    }

    // Dispatches a 6-digit SMS OTP via Firebase Phone Auth
    window.basaSendFirebaseSms = async function(phoneE164) {
        try {
            window.basaSmsConfirmation = null;
            const verifier = buildFreshRecaptchaVerifier();
            if (!verifier) {
                return { success: false, code: 'no-container', error: 'reCAPTCHA mount container not found.' };
            }

            await verifier.render();
            const confirmationResult = await signInWithPhoneNumber(auth, phoneE164, verifier);
            window.basaSmsConfirmation = confirmationResult;
            return { success: true };
        } catch (err) {
            console.warn("Firebase SMS Dispatch Error:", err);
            const code = err?.code || 'unknown';
            let friendly = err?.message || 'Firebase SMS could not be dispatched.';

            if (code === 'auth/billing-not-enabled') {
                friendly = 'Firebase Project (hellop2) requires either Blaze plan for carrier SMS or adding ' + phoneE164 + ' under Firebase Console → Authentication → Sign-in method → Phone → Phone numbers for testing.';
            } else if (code === 'auth/operation-not-allowed') {
                friendly = 'Phone Authentication is not enabled yet in Firebase Console (Authentication → Sign-in method → Phone).';
            } else if (code === 'auth/invalid-phone-number') {
                friendly = 'Invalid phone format (' + phoneE164 + '). Use a valid 11-digit PH mobile number (e.g. 09171234567).';
            } else if (code === 'auth/too-many-requests') {
                friendly = 'Firebase SMS quota exceeded for this number. Try again later or use a Firebase Test Phone Number.';
            }

            return {
                success: false,
                code: code,
                error: friendly
            };
        }
    };

    // Confirms the 6-digit SMS code with Firebase and returns a signed ID Token
    window.basaVerifyFirebaseSms = async function(otpCode) {
        if (!window.basaSmsConfirmation) {
            return { success: false, noSession: true };
        }
        try {
            const credential = await window.basaSmsConfirmation.confirm(String(otpCode).trim());
            const idToken = await credential.user.getIdToken();
            return { success: true, idToken: idToken };
        } catch (err) {
            return {
                success: false,
                noSession: false,
                error: 'Invalid 6-digit Firebase SMS verification code.'
            };
        }
    };
</script>