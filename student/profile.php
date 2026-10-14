<?php
// student/profile.php
$page_title = 'My Profile & Security';
require_once __DIR__ . '/../includes/header.php';
require_role(['student', 'faculty', 'librarian', 'super_admin']);
require_once __DIR__ . '/../includes/sidebar.php';
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<div x-data="profileSecurityApp()" x-init="loadProfile()" @keydown.escape.window="toastMsg = ''; closePinModal();" class="max-w-5xl mx-auto space-y-6">

    <!-- CENTERED SCREEN RESPONSE MODAL (Elevated to z-[120] to stay above the PIN lock) -->
    <div x-show="toastMsg" x-cloak
         class="fixed inset-0 z-[120] flex items-center justify-center bg-black/70 backdrop-blur-xs p-4"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95">
        <div @click.away="toastMsg = ''"
             :class="toastOk ? 'border-emerald-500' : 'border-[#D9232A]'"
             class="w-full max-w-md bg-theme-card border-2 rounded-2xl shadow-2xl p-6 text-center space-y-4">
            
            <div :class="toastOk ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/40' : 'bg-red-500/20 text-red-400 border-red-500/40'"
                 class="w-14 h-14 rounded-2xl border mx-auto flex items-center justify-center text-2xl font-extrabold shadow-inner">
                <span x-text="toastOk ? '✓' : '⚠️'"></span>
            </div>

            <div>
                <h3 class="text-base font-extrabold"
                    :class="toastOk ? 'text-emerald-400' : 'text-red-400'"
                    x-text="toastOk ? 'Action Successful' : 'Action / Verification Failed'"></h3>
                <p class="text-xs sm:text-sm text-theme-text font-semibold mt-1.5 leading-relaxed" x-text="toastMsg"></p>
            </div>

            <!-- x-ref allows us to force focus here so Spacebar presses this button instead of re-submitting forms -->
            <button type="button" @click="toastMsg = ''" x-ref="toastOkBtn"
                    :class="toastOk ? 'bg-emerald-500 hover:bg-emerald-400 text-slate-950' : 'bg-[#D9232A] hover:bg-red-700 text-white'"
                    class="w-full py-2.5 px-5 rounded-xl font-extrabold text-xs shadow-lg transition focus:ring-4 focus:ring-opacity-50"
                    :class="toastOk ? 'focus:ring-emerald-500' : 'focus:ring-[#D9232A]'">
                OK, Understood
            </button>
        </div>
    </div>

    <!-- PROFILE LOCK PIN REQUIRED MODAL -->
    <div x-show="pinModalOpen" x-cloak
         class="fixed inset-0 z-[110] flex items-center justify-center bg-black/75 backdrop-blur-sm p-4"
         x-transition>
        <div @click.away="closePinModal()"
             class="w-full max-w-sm bg-theme-card border-2 border-amber-500 rounded-2xl shadow-2xl p-6 text-center space-y-4">
            <div class="w-14 h-14 rounded-2xl bg-amber-500/20 text-amber-500 border border-amber-500/40 mx-auto flex items-center justify-center text-2xl font-extrabold shadow-inner">
                🔒
            </div>
            <div>
                <h3 class="text-base font-extrabold text-amber-400">Profile Lock PIN Required</h3>
                <p class="text-xs text-theme-muted mt-1">Enter your 4 to 6-digit PIN to authorize this change.</p>
            </div>
            <form @submit.prevent="confirmPinSubmit()" class="space-y-4">
                <input type="password" x-model="pinInput" required minlength="4" maxlength="6" pattern="\d+" placeholder="••••" x-ref="pinModalField"
                       class="w-full px-4 py-3 rounded-xl bg-theme-main border-2 border-amber-500/50 font-mono text-center tracking-[0.5em] text-lg font-extrabold text-theme-text outline-none focus:border-amber-400 transition">
                <div class="flex gap-2">
                    <button type="button" @click="closePinModal()" class="flex-1 py-2.5 rounded-xl bg-theme-main border border-theme-border text-theme-muted font-bold text-xs">Cancel</button>
                    <button type="submit" class="flex-1 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold text-xs shadow-lg transition">Verify & Unlock</button>
                </div>
                
                <p class="text-[10px] text-theme-muted pt-3 border-t border-theme-border/50">
                    Forgot your PIN? Please contact the Administration or Library Help Desk to reset your security lock.
                </p>
            </form>
        </div>
    </div>

    <!-- TOP PROFILE CARD: AVATAR UPLOAD + EDITABLE DISPLAY NAME & CONTACT NO. -->
    <div class="bg-theme-card border border-theme-border border-l-4 border-l-theme-accent rounded-2xl p-5 sm:p-6 shadow-xs">
        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
            
            <!-- Left: Avatar with Edit Icon -->
            <div class="flex items-center gap-5">
                <div class="relative group shrink-0">
                    <template x-if="profile.photo_path">
                        <img :src="'<?= BASE_URL ?>/' + profile.photo_path"
                             class="w-24 h-24 rounded-2xl object-cover border-4 border-theme-accent shadow-lg">
                    </template>
                    <template x-if="!profile.photo_path">
                        <div class="w-24 h-24 rounded-2xl bg-theme-accent text-white font-cartoon font-bold text-4xl flex items-center justify-center border-4 border-theme-border shadow-lg"
                             x-text="(profile.display_name || profile.full_name || 'U').substring(0,1).toUpperCase()"></div>
                    </template>

                    <label class="absolute -bottom-2 -right-2 w-9 h-9 rounded-xl bg-theme-accent hover:bg-theme-accentHover text-white flex items-center justify-center shadow-lg cursor-pointer border-2 border-theme-card transition"
                           title="Change Profile Picture">
                        <input type="file" class="hidden" accept="image/jpeg,image/png,image/webp" @change="uploadAvatar($event)">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                        </svg>
                    </label>
                </div>

                <div>
                    <div class="flex items-center gap-2">
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-theme-accent text-white"
                              x-text="profile.role"></span>
                        <span class="font-mono text-xs font-bold text-theme-muted" x-text="'#' + profile.school_id"></span>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-extrabold text-theme-text mt-1" x-text="profile.display_name || profile.full_name"></h1>
                    <p class="text-xs text-theme-muted" x-text="profile.full_name + (profile.program_code ? ' • ' + profile.program_code : '')"></p>
                </div>
            </div>

            <button type="button"
                    @click="toggleEditPersonal()"
                    class="px-4 py-2.5 rounded-xl bg-theme-main hover:bg-theme-hover border border-theme-border text-xs font-extrabold text-theme-text flex items-center gap-2 transition shrink-0">
                <span x-text="editingPersonal ? 'Cancel Editing' : (profile.has_pin && !isUnlocked ? '🔒 Unlock to Edit Info' : '✏️ Edit Display Name & Contact')"></span>
            </button>
        </div>

        <!-- Theme-Adaptive Editable Personal Info Bar -->
        <div class="mt-6 pt-5 border-t border-theme-border">
            <form @submit.prevent="savePersonal()" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                <div>
                    <label class="flex items-center justify-between text-[11px] font-extrabold uppercase tracking-wider text-theme-muted mb-1">
                        <span>Display Name / Nickname</span>
                        <button type="button" @click="profile.has_pin && !isUnlocked ? requirePin(()=>{}) : toggleEditPersonal()" class="text-theme-accent hover:underline">
                            <span x-text="profile.has_pin && !isUnlocked ? '🔒 Unlock' : '✏️ Edit'"></span>
                        </button>
                    </label>
                    <input type="text" x-ref="dispInput" x-model="profile.display_name" :readonly="!editingPersonal"
                           :class="editingPersonal ? 'border-theme-accent ring-2 ring-theme-accent bg-theme-main' : 'border-theme-border opacity-85 cursor-default bg-theme-card'"
                           class="w-full px-3.5 py-2.5 rounded-xl text-theme-text border text-xs font-bold outline-none transition">
                </div>

                <div>
                    <label class="flex items-center justify-between text-[11px] font-extrabold uppercase tracking-wider text-theme-muted mb-1">
                        <span>Contact Number</span>
                        <button type="button" @click="profile.has_pin && !isUnlocked ? requirePin(()=>{}) : toggleEditPersonal()" class="text-theme-accent hover:underline">
                            <span x-text="profile.has_pin && !isUnlocked ? '🔒 Unlock' : '✏️ Edit'"></span>
                        </button>
                    </label>
                    <input type="text" x-ref="contactInput" x-model="profile.contact_no" :readonly="!editingPersonal"
                           placeholder="09171234567"
                           :class="editingPersonal ? 'border-theme-accent ring-2 ring-theme-accent bg-theme-main' : 'border-theme-border opacity-85 cursor-default bg-theme-card'"
                           class="w-full px-3.5 py-2.5 rounded-xl text-theme-text border text-xs font-mono font-bold outline-none transition">
                </div>

                <div>
                    <button type="submit" x-show="editingPersonal" x-transition
                            class="w-full py-2.5 px-4 rounded-xl bg-theme-accent hover:bg-theme-accentHover text-white font-extrabold text-xs shadow transition">
                        ✓ Save Personal Info
                    </button>
                    <div x-show="!editingPersonal" class="p-2.5 rounded-xl bg-theme-main border border-theme-border text-[11px] text-theme-muted text-center">
                        Click <strong>Edit</strong> to update Display Name or Contact No.
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- MAIN 2-COLUMN GRID -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- LEFT 5 COLUMNS: LOCKED LEGAL REGISTRAR IDENTITY (SUPER ADMIN ONLY) -->
        <div class="lg:col-span-5 bg-theme-card border border-theme-border rounded-2xl p-5 sm:p-6 shadow-xs h-fit space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-theme-border">
                <div>
                    <h2 class="text-base font-extrabold text-theme-text">Official Registrar Name</h2>
                    <p class="text-xs text-theme-muted">Locked institutional record</p>
                </div>
                <span class="px-2.5 py-1 rounded-lg bg-amber-500/15 text-amber-400 border border-amber-500/30 text-[10px] font-extrabold uppercase flex items-center gap-1">
                    🔒 Super Admin Only
                </span>
            </div>

            <div class="space-y-3 text-xs">
                <div>
                    <label class="block text-[10px] font-extrabold uppercase tracking-wider text-theme-muted mb-1">First Name</label>
                    <input type="text" :value="profile.first_name" readonly disabled
                           class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border text-theme-muted font-bold opacity-80 cursor-not-allowed">
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-2">
                        <label class="block text-[10px] font-extrabold uppercase tracking-wider text-theme-muted mb-1">Middle Name</label>
                        <input type="text" :value="profile.middle_name || '—'" readonly disabled
                               class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border text-theme-muted font-bold opacity-80 cursor-not-allowed">
                    </div>
                    <div>
                        <label class="block text-[10px] font-extrabold uppercase tracking-wider text-theme-muted mb-1">M.I.</label>
                        <input type="text" :value="profile.middle_initial || '—'" readonly disabled
                               class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border text-theme-muted font-mono font-bold text-center opacity-80 cursor-not-allowed">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-2">
                        <label class="block text-[10px] font-extrabold uppercase tracking-wider text-theme-muted mb-1">Last Name</label>
                        <input type="text" :value="profile.last_name" readonly disabled
                               class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border text-theme-muted font-bold opacity-80 cursor-not-allowed">
                    </div>
                    <div>
                        <label class="block text-[10px] font-extrabold uppercase tracking-wider text-theme-muted mb-1">Extension</label>
                        <input type="text" :value="profile.suffix || 'None'" readonly disabled
                               class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border text-theme-muted font-bold text-center opacity-80 cursor-not-allowed">
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-extrabold uppercase tracking-wider text-theme-muted mb-1">Official BCP Email</label>
                    <input type="text" :value="profile.email" readonly disabled
                           class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border text-theme-muted font-mono opacity-80 cursor-not-allowed">
                </div>
            </div>

            <div class="p-3.5 rounded-xl bg-theme-main border border-theme-border text-[11px] text-theme-muted leading-relaxed">
                ℹ️ Your <strong>First Name, Middle Name, Middle Initial, Last Name, and Extension</strong> can only be edited by the <strong>Super Admin</strong>.
            </div>
        </div>

        <!-- RIGHT 7 COLUMNS: SECURITY, PASSWORDS & 2FA -->
        <div class="lg:col-span-7 space-y-6">

            <!-- CARD 1: CHANGE PASSWORD -->
            <div class="bg-theme-card border border-theme-border rounded-2xl p-5 sm:p-6 shadow-xs space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-theme-border">
                    <div class="flex items-center gap-2">
                        <h2 class="text-base font-extrabold text-theme-text"
                            x-text="forgotMode ? 'Reset Forgotten Password (OTP Verification)' : 'Change Account Password'"></h2>
                        <svg class="w-4 h-4 text-theme-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                        </svg>
                    </div>

                    <button type="button" x-show="!profile.has_pin || isUnlocked || forgotMode" x-transition
                            @click="forgotMode = !forgotMode; demoOtpCode = ''"
                            class="text-xs font-extrabold text-theme-accent hover:underline shrink-0">
                        <span x-text="forgotMode ? '← I Remember My Current Password' : 'Forgot Current Password?'"></span>
                    </button>
                </div>

                <!-- MODE A: STANDARD PASSWORD CHANGE -->
                <form x-show="!forgotMode" @submit.prevent="submitRegularPasswordChange()" class="space-y-4 text-xs">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-start">
                        <div class="w-full">
                            <label class="block font-extrabold uppercase text-theme-muted mb-1 whitespace-nowrap">Current Password</label>
                            <input type="password" x-model="pwForm.current_password" required placeholder="••••••••"
                                   :readonly="profile.has_pin && !isUnlocked"
                                   :class="(profile.has_pin && !isUnlocked) ? 'opacity-50 cursor-not-allowed bg-theme-card border-theme-border' : 'bg-theme-main focus:border-theme-accent border-theme-border'"
                                   class="w-full px-3.5 py-2.5 rounded-xl border text-theme-text outline-none transition">
                        </div>
                        <div class="w-full">
                            <label class="block font-extrabold uppercase text-theme-muted mb-1 whitespace-nowrap">New Password</label>
                            <input type="password" x-model="pwForm.new_password" required minlength="6" placeholder="Min. 6 chars"
                                   :readonly="profile.has_pin && !isUnlocked"
                                   :class="(profile.has_pin && !isUnlocked) ? 'opacity-50 cursor-not-allowed bg-theme-card border-theme-border' : 'bg-theme-main focus:border-theme-accent border-theme-border'"
                                   class="w-full px-3.5 py-2.5 rounded-xl border text-theme-text outline-none transition">
                        </div>
                        <div class="w-full">
                            <label class="block font-extrabold uppercase text-theme-muted mb-1 whitespace-nowrap">Confirm New Password</label>
                            <input type="password" x-model="pwForm.confirm_password" required minlength="6" placeholder="Repeat password"
                                   :readonly="profile.has_pin && !isUnlocked"
                                   :class="(profile.has_pin && !isUnlocked) ? 'opacity-50 cursor-not-allowed bg-theme-card border-theme-border' : 'bg-theme-main focus:border-theme-accent border-theme-border'"
                                   class="w-full px-3.5 py-2.5 rounded-xl border text-theme-text outline-none transition">
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row items-center sm:justify-between gap-3 pt-3">
                        <!-- Left Aligned Link -->
                        <button type="button" @click="toggleForgotMode()"
                                class="text-xs font-bold text-amber-400 hover:underline text-center sm:text-left">
                            <span x-text="(profile.has_pin && !isUnlocked) ? '🔒 Forgot password? (Unlock Required)' : 'Forgot password? Send verification code instead →'"></span>
                        </button>
                        
                        <!-- Right Aligned Actions -->
                        <div class="flex gap-2 w-full sm:w-auto">
                            <button type="button" x-show="profile.has_pin && !isUnlocked" @click="requirePin(()=>{})"
                                    class="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold text-xs shadow-lg transition shrink-0">
                                🔒 Unlock to Change Password
                            </button>
                            <button type="submit" x-show="!profile.has_pin || isUnlocked"
                                    class="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-theme-accent hover:bg-theme-accentHover text-white font-extrabold text-xs shadow-lg transition shrink-0">
                                ✓ Update Password
                            </button>
                        </div>
                    </div>
                </form>

                <!-- MODE B: FORGOT PASSWORD FLOW -->
                <div x-show="forgotMode" x-cloak x-transition class="space-y-4 text-xs">
                    <div class="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-400 text-[11px]">
                        🔐 <strong>Forgot Password Recovery:</strong> Choose a verification channel below to reset your password without needing your current password.
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                        <button type="button" @click="forgotForm.auth_method = 'email'"
                                :class="forgotForm.auth_method === 'email' ? 'border-theme-accent bg-theme-hover text-theme-accent ring-1 ring-theme-accent' : 'border-theme-border bg-theme-main text-theme-muted'"
                                class="p-3 rounded-xl border font-extrabold text-left transition">
                            <p>📧 Email OTP API</p>
                            <p class="text-[10px] opacity-75 font-normal mt-0.5 truncate" x-text="profile.email"></p>
                        </button>

                        <button type="button" @click="forgotForm.auth_method = 'sms'"
                                :class="forgotForm.auth_method === 'sms' ? 'border-theme-accent bg-theme-hover text-theme-accent ring-1 ring-theme-accent' : 'border-theme-border bg-theme-main text-theme-muted'"
                                class="p-3 rounded-xl border font-extrabold text-left transition">
                            <p>📱 SMS OTP API</p>
                            <p class="text-[10px] opacity-75 font-normal mt-0.5 font-mono" x-text="profile.contact_no || 'No Contact No.'"></p>
                        </button>

                        <button type="button" @click="forgotForm.auth_method = 'ga'"
                                :class="forgotForm.auth_method === 'ga' ? 'border-theme-accent bg-theme-hover text-theme-accent ring-1 ring-theme-accent' : 'border-theme-border bg-theme-main text-theme-muted'"
                                class="p-3 rounded-xl border font-extrabold text-left transition">
                            <p>🔐 Authenticator 2FA</p>
                            <p class="text-[10px] opacity-75 font-normal mt-0.5" x-text="profile.ga_enabled == 1 ? '6-Digit App Code' : 'Setup in 2FA card below'"></p>
                        </button>
                    </div>

                    <div x-show="forgotForm.auth_method === 'email' || forgotForm.auth_method === 'sms'"
                         class="p-3.5 rounded-xl bg-theme-main border border-theme-border space-y-2">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div>
                                <p class="font-extrabold text-theme-text"
                                   x-text="forgotForm.auth_method === 'email' ? 'Send Code to ' + profile.email : 'Send SMS to ' + (profile.contact_no || 'No number set')"></p>
                                <p class="text-[11px] text-theme-muted">Click to receive a 6-digit password reset code.</p>
                            </div>
                            <button type="button" @click="sendOtpCode()"
                                    class="px-4 py-2 rounded-xl bg-theme-accent text-white font-extrabold shrink-0 shadow">
                                📨 Send Verification Code
                            </button>
                        </div>

                        <div x-show="demoOtpCode" x-cloak class="p-2.5 rounded-lg bg-amber-500/15 border border-amber-500/30 text-[11px] text-amber-400 font-mono">
                            🧪 <strong>Local XAMPP Sandbox OTP:</strong> <span class="text-sm font-extrabold underline select-all" x-text="demoOtpCode"></span>
                        </div>
                    </div>

                    <form @submit.prevent="submitForgotPasswordReset()" class="space-y-4 pt-1">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-start">
                            <div class="w-full">
                                <label class="block font-extrabold uppercase text-theme-accent mb-1 whitespace-nowrap">6-Digit Verification Code</label>
                                <input type="text" x-model="forgotForm.otp_code" required maxlength="6" placeholder="000000"
                                       class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border-2 border-theme-accent font-mono font-extrabold text-center tracking-widest text-theme-text outline-none transition focus:border-emerald-500">
                            </div>
                            <div class="w-full">
                                <label class="block font-extrabold uppercase text-theme-muted mb-1 whitespace-nowrap">New Password</label>
                                <input type="password" x-model="forgotForm.new_password" required minlength="6" placeholder="Min. 6 chars"
                                       class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border text-theme-text outline-none transition focus:border-emerald-500">
                            </div>
                            <div class="w-full">
                                <label class="block font-extrabold uppercase text-theme-muted mb-1 whitespace-nowrap">Confirm New Password</label>
                                <input type="password" x-model="forgotForm.confirm_password" required minlength="6" placeholder="Repeat password"
                                       class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border text-theme-text outline-none transition focus:border-emerald-500">
                            </div>
                        </div>

                        <div class="flex justify-end pt-2">
                            <button type="submit"
                                    class="w-full sm:w-auto px-6 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold text-xs shadow-lg transition shrink-0">
                                🔓 Verify Code & Reset Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- CARD 2: PROFILE LOCK PIN SETUP -->
            <div class="bg-theme-card border border-theme-border rounded-2xl p-5 sm:p-6 shadow-xs space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-theme-border">
                    <div>
                        <h2 class="text-base font-extrabold text-theme-text">Profile Lock PIN (Extra Security)</h2>
                        <p class="text-xs text-theme-muted">Require a 4 to 6-digit PIN before personal info or passwords can be changed.</p>
                    </div>
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase shrink-0"
                          :class="profile.has_pin ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/40' : 'bg-amber-500/20 text-amber-400'"
                          x-text="profile.has_pin ? '✓ PIN Active' : 'Not Set'"></span>
                </div>

                <!-- Setup PIN Form -->
                <form x-show="!profile.has_pin" @submit.prevent="enablePin()" class="space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[11px] font-extrabold uppercase text-theme-muted mb-1">Create 4-6 Digit PIN</label>
                            <input type="password" x-model="pinForm.new_pin" required minlength="4" maxlength="6" pattern="\d+" placeholder="••••"
                                   class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border font-mono text-center tracking-[0.5em] text-theme-text outline-none focus:border-theme-accent transition">
                        </div>
                        <div>
                            <label class="block text-[11px] font-extrabold uppercase text-theme-muted mb-1">Current Password</label>
                            <input type="password" x-model="pinForm.password" required placeholder="Verify it's you"
                                   class="w-full px-3.5 py-2.5 rounded-xl bg-theme-main border border-theme-border text-theme-text outline-none focus:border-theme-accent transition">
                        </div>
                    </div>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-theme-accent text-white font-extrabold text-xs shadow transition">
                        🔒 Enable Lock PIN
                    </button>
                </form>

                <!-- Remove PIN Form -->
                <form x-show="profile.has_pin" @submit.prevent="disablePin()" class="space-y-3">
                    <p class="text-xs text-theme-muted mb-3">To disable the PIN requirement, please verify your PIN to confirm.</p>
                    <button type="submit" 
                            class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-[#D9232A] hover:bg-red-700 text-white font-extrabold text-xs shadow transition shrink-0">
                        <span x-text="isUnlocked ? 'Remove PIN' : '🔒 Unlock & Remove PIN'"></span>
                    </button>
                    <p class="text-[10px] text-theme-muted mt-2 pt-2 border-t border-theme-border/50 inline-block">
                        Forgot your PIN? Please contact the Administration or Library Help Desk to reset your security lock.
                    </p>
                </form>
            </div>

            <!-- CARD 3: TWO-FACTOR AUTHENTICATION (2FA) APP SETUP -->
            <div class="bg-theme-card border border-theme-border rounded-2xl p-5 sm:p-6 shadow-xs space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-theme-border">
                    <div>
                        <h2 class="text-base font-extrabold text-theme-text">Two-Factor Authentication (2FA) App Setup</h2>
                        <p class="text-xs text-theme-muted">Link a 3rd-party OTP app (Google Authenticator) for offline password recovery</p>
                    </div>
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase shrink-0"
                          :class="profile.ga_enabled == 1 ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400'"
                          x-text="profile.ga_enabled == 1 ? '✓ Google Authenticator Active' : 'Not Linked Yet'"></span>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                    <p class="text-theme-muted leading-relaxed">
                        Set up <strong>Google Authenticator</strong> once so you can always recover a forgotten password offline using a 6-digit time-based code from your phone.
                    </p>
                    <button type="button" @click="startGaSetup()"
                            class="px-4 py-2.5 rounded-xl bg-theme-main hover:bg-theme-hover border border-theme-border font-extrabold text-theme-text shrink-0 transition">
                        <span x-text="profile.ga_enabled == 1 ? '↻ Re-Configure 2FA QR' : '⚡ Setup First-Time 2FA'"></span>
                    </button>
                </div>

                <div x-show="gaSetupOpen" x-cloak x-transition class="p-5 rounded-xl bg-theme-main border border-theme-border flex flex-col sm:flex-row items-start gap-6">
                    <div class="space-y-2 shrink-0 w-full sm:w-auto">
                        <div class="flex items-center justify-between mb-1">
                            <p class="font-bold text-theme-text text-xs">1. Scan this QR code</p>
                            <button type="button" @click="toggleGaQrInvert()"
                                    :class="gaQrInverted ? 'bg-indigo-500 text-white' : 'bg-theme-card text-theme-muted border border-theme-border'"
                                    class="px-2 py-1 rounded-lg text-[9px] font-extrabold uppercase transition shadow-sm">
                                <span x-text="gaQrInverted ? '◐ Inverted' : '◑ Standard'"></span>
                            </button>
                        </div>
                        <div class="p-3 rounded-xl border-2 transition-colors duration-300 flex justify-center"
                             :class="gaQrInverted ? 'bg-[#0f172a] border-indigo-500' : 'bg-white border-theme-accent'">
                            <div id="gaQrCanvas" class="w-36 h-36 flex items-center justify-center"></div>
                        </div>
                    </div>
                    <div class="space-y-3 text-xs flex-1 w-full pt-1">
                        <div>
                            <p class="text-[11px] text-theme-muted mb-1">Manual Base32 Setup Key:</p>
                            <span class="px-2.5 py-1 rounded-lg bg-theme-card border border-theme-border font-mono font-extrabold text-amber-400 select-all tracking-widest inline-block" x-text="gaSecret"></span>
                        </div>
                        <div class="pt-2 border-t border-theme-border/50">
                            <p class="font-bold text-theme-text mb-2">2. Enter the 6-digit code from your phone to confirm:</p>
                            <div class="flex flex-col sm:flex-row gap-2">
                                <input type="text" x-model="gaVerifyCode" maxlength="6" placeholder="123456"
                                       class="w-full sm:w-32 px-3 py-2.5 rounded-xl bg-theme-card border border-theme-border font-mono text-center font-extrabold text-sm text-theme-text outline-none focus:border-emerald-500 transition">
                                <button type="button" @click="confirmGaSetup()"
                                        class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold text-xs shadow transition">
                                    Activate 2FA
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

<script>
function profileSecurityApp() {
    return {
        editingPersonal: false,
        forgotMode: false,
        toastMsg: '',
        toastOk: true,
        
        isUnlocked: false,
        sessionPin: '',
        
        pinModalOpen: false,
        pinInput: '',
        pinCallback: null,
        pinForm: { new_pin: '', password: '' },

        gaSetupOpen: false,
        gaSecret: '',
        gaVerifyCode: '',
        gaOtpauthUri: '',
        gaQrInverted: false,
        demoOtpCode: '',

        profile: {
            school_id: '',
            first_name: '',
            middle_name: '',
            middle_initial: '',
            last_name: '',
            suffix: '',
            full_name: '',
            display_name: '',
            email: '',
            contact_no: '',
            role: '',
            program_code: '',
            photo_path: '',
            ga_enabled: 0,
            has_pin: false
        },

        pwForm: {
            current_password: '',
            new_password: '',
            confirm_password: ''
        },

        forgotForm: {
            auth_method: 'email',
            otp_code: '',
            new_password: '',
            confirm_password: ''
        },

        showToast(msg, ok = true) {
            this.toastMsg = msg;
            this.toastOk = ok;
            // Unfocus active element to stop Spacebar re-triggers
            if (document.activeElement) document.activeElement.blur();
            this.$nextTick(() => {
                if (this.$refs.toastOkBtn) this.$refs.toastOkBtn.focus();
            });
        },

        async loadProfile() {
            try {
                const res = await fetch('<?= BASE_URL ?>/api/profile/update.php?action=get_profile');
                const data = await res.json();
                if (data.success && data.profile) {
                    this.profile = data.profile;
                    this.isUnlocked = !this.profile.has_pin;
                }
            } catch (e) {
                this.showToast('Could not load profile data.', false);
            }
        },

        async verifyPinAndUnlock(pin) {
            try {
                const res = await fetch('<?= BASE_URL ?>/api/profile/update.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'verify_pin', pin: pin })
                });
                const data = await res.json();
                if (data.success) {
                    this.sessionPin = pin;
                    this.isUnlocked = true;
                    this.pinModalOpen = false;
                    this.showToast('Security Unlocked!', true);
                    return true;
                } else {
                    this.showToast(data.message, false);
                    this.pinInput = ''; // Auto clear incorrect PIN
                    return false;
                }
            } catch (e) {
                this.showToast('Error verifying PIN', false);
                return false;
            }
        },

        requirePin(callback) {
            if (this.profile.has_pin && !this.isUnlocked) {
                this.pinCallback = async (enteredPin) => {
                    const isValid = await this.verifyPinAndUnlock(enteredPin);
                    if (isValid) {
                        callback(enteredPin);
                    }
                };
                this.pinInput = '';
                this.pinModalOpen = true;
                setTimeout(() => { if(this.$refs.pinModalField) this.$refs.pinModalField.focus(); }, 150);
            } else {
                callback(this.sessionPin);
            }
        },

        closePinModal() {
            this.pinModalOpen = false;
            this.pinInput = '';
            this.pinCallback = null;
        },

        confirmPinSubmit() {
            if (this.pinCallback) {
                this.pinCallback(this.pinInput);
            }
            if (document.activeElement) document.activeElement.blur();
        },

        toggleEditPersonal() {
            if (this.editingPersonal) {
                this.editingPersonal = false;
                return;
            }
            if (this.profile.has_pin && !this.isUnlocked) {
                this.requirePin(() => {}); // Only unlock, do not enter edit mode immediately
                return;
            }
            this.editingPersonal = true;
            this.$nextTick(() => { if (this.$refs.dispInput) this.$refs.dispInput.focus(); });
        },

        toggleForgotMode() {
            if (this.forgotMode) {
                this.forgotMode = false;
                this.demoOtpCode = '';
                return;
            }
            this.requirePin(() => {
                this.forgotMode = true;
            });
        },

        uploadAvatar(event) {
            const file = event.target.files[0];
            if (!file) return;

            this.requirePin(async (pinCode) => {
                const formData = new FormData();
                formData.append('action', 'upload_photo');
                formData.append('photo', file);
                formData.append('profile_pin', pinCode);

                try {
                    const res = await fetch('<?= BASE_URL ?>/api/profile/update.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await res.json();
                    this.showToast(data.message, data.success);
                    if (data.success) {
                        this.profile.photo_path = data.photo_path;
                        setTimeout(() => window.location.reload(), 900);
                    }
                } catch (e) {
                    this.showToast('Failed to upload image.', false);
                }
            });
        },

        savePersonal() {
            this.requirePin(async (pinCode) => {
                try {
                    const res = await fetch('<?= BASE_URL ?>/api/profile/update.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'update_personal',
                            display_name: this.profile.display_name,
                            contact_no: this.profile.contact_no,
                            profile_pin: pinCode
                        })
                    });
                    const data = await res.json();
                    this.showToast(data.message, data.success);
                    if (data.success) {
                        this.editingPersonal = false;
                    }
                } catch (e) {
                    this.showToast('Server error while saving personal info.', false);
                }
            });
        },

        submitRegularPasswordChange() {
            this.requirePin(async (pinCode) => {
                try {
                    const res = await fetch('<?= BASE_URL ?>/api/profile/update.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'change_password',
                            ...this.pwForm,
                            profile_pin: pinCode
                        })
                    });
                    const data = await res.json();
                    this.showToast(data.message, data.success);
                    if (data.success) {
                        this.pwForm.current_password = '';
                        this.pwForm.new_password = '';
                        this.pwForm.confirm_password = '';
                    }
                } catch (e) {
                    this.showToast('Error updating password.', false);
                }
            });
        },

        async enablePin() {
            try {
                const res = await fetch('<?= BASE_URL ?>/api/profile/update.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'setup_pin',
                        ...this.pinForm
                    })
                });
                const data = await res.json();
                this.showToast(data.message, data.success);
                if (data.success) {
                    this.profile.has_pin = true;
                    this.pinForm.new_pin = '';
                    this.pinForm.password = '';
                }
            } catch (e) {
                this.showToast('Error enabling PIN.', false);
            }
        },

        disablePin() {
            this.requirePin(async (pinCode) => {
                try {
                    const res = await fetch('<?= BASE_URL ?>/api/profile/update.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'remove_pin',
                            profile_pin: pinCode
                        })
                    });
                    const data = await res.json();
                    this.showToast(data.message, data.success);
                    if (data.success) {
                        this.profile.has_pin = false;
                        this.isUnlocked = true;
                        this.sessionPin = '';
                        this.pinForm.password = '';
                    }
                } catch (e) {
                    this.showToast('Error removing PIN.', false);
                }
            });
        },

        async sendOtpCode() {
            this.requirePin(async (pinCode) => {
                try {
                    const res = await fetch('<?= BASE_URL ?>/api/profile/update.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ 
                            action: 'send_otp', 
                            channel: this.forgotForm.auth_method,
                            profile_pin: pinCode
                        })
                    });
                    const data = await res.json();
                    this.showToast(data.message, data.success);
                    if (data.success && data.demo_otp) {
                        this.demoOtpCode = data.demo_otp;
                        this.forgotForm.otp_code = data.demo_otp;
                    }
                } catch (e) {
                    this.showToast('Error sending verification code.', false);
                }
            });
        },

        async submitForgotPasswordReset() {
            this.requirePin(async (pinCode) => {
                try {
                    const res = await fetch('<?= BASE_URL ?>/api/profile/update.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'reset_forgot_password',
                            ...this.forgotForm,
                            profile_pin: pinCode
                        })
                    });
                    const data = await res.json();
                    this.showToast(data.message, data.success);
                    if (data.success) {
                        this.forgotForm.otp_code = '';
                        this.forgotForm.new_password = '';
                        this.forgotForm.confirm_password = '';
                        this.demoOtpCode = '';
                        this.forgotMode = false;
                    }
                } catch (e) {
                    this.showToast('Error resetting password.', false);
                }
            });
        },

        toggleGaQrInvert() {
            this.gaQrInverted = !this.gaQrInverted;
            if (this.gaOtpauthUri) {
                this.renderGaQr(this.gaOtpauthUri);
            }
        },

        renderGaQr(uri) {
            this.gaOtpauthUri = uri;
            const el = document.getElementById('gaQrCanvas');
            if (el) {
                el.innerHTML = '';
                new QRCode(el, {
                    text: uri,
                    width: 144,
                    height: 144,
                    colorDark: this.gaQrInverted ? "#ffffff" : "#0f172a",
                    colorLight: this.gaQrInverted ? "#0f172a" : "#ffffff",
                    correctLevel: QRCode.CorrectLevel.M
                });
            }
        },

        async startGaSetup() {
            try {
                const res = await fetch('<?= BASE_URL ?>/api/profile/update.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'init_ga_setup', regenerate: true })
                });
                const data = await res.json();
                if (data.success) {
                    this.gaSecret = data.secret;
                    this.gaSetupOpen = true;
                    this.$nextTick(() => {
                        this.renderGaQr(data.otpauth_uri);
                    });
                }
            } catch (e) {
                this.showToast('Could not initialize 2FA setup.', false);
            }
        },

        async confirmGaSetup() {
            try {
                const res = await fetch('<?= BASE_URL ?>/api/profile/update.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'verify_ga_setup', code: this.gaVerifyCode })
                });
                const data = await res.json();
                this.showToast(data.message, data.success);
                if (data.success) {
                    this.profile.ga_enabled = 1;
                    this.gaSetupOpen = false;
                    this.gaVerifyCode = '';
                }
            } catch (e) {
                this.showToast('Error verifying Authenticator code.', false);
            }
        }
    };
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>