<script setup>
import { ref, watch, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    flash:               { type: Object,  default: () => ({}) },
    keycloakAvailable:   { type: Boolean, default: false },
    keycloakRedirectUrl: { type: String,  default: '' },
});

const form = useForm({ username: '', password: '', remember: false });
const showPass   = ref(false);
const errorMsg   = ref('');
const ssoLoading = ref(false);

// Mode login: 'local' | 'sso' | null (null = tampilkan pilihan metode)
const mode = ref(props.keycloakAvailable ? null : 'local');

const headerSubtitle = computed(() => {
    if (mode.value === 'sso')   return 'Masuk menggunakan akun SSO rumah sakit';
    if (mode.value === 'local') return 'Masuk ke sistem monitoring ICU Anda';
    return 'Pilih metode login Anda';
});

watch(() => props.flash, (f) => {
    if (f?.error) errorMsg.value = f.error;
}, { immediate: true, deep: true });

const submit = () => {
    errorMsg.value = '';
    form.post(route('login'), {
        onError: () => {
            errorMsg.value = form.errors.username || form.errors.password || 'Login gagal. Periksa kembali.';
        },
    });
};

const loginSSO = () => {
    if (!props.keycloakRedirectUrl) return;
    ssoLoading.value = true;
    window.location.href = props.keycloakRedirectUrl;
};

const features = [
    { icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', label: 'Real-time Monitoring', desc: 'Status bed ICU langsung dari sistem' },
    { icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4', label: 'Manajemen Bed ICU', desc: 'Alokasi dan booking bed ICU/HCU' },
    { icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', label: 'Booking Digital', desc: 'Permintaan ICU tanpa kertas' },
    { icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z', label: 'Multi-role Access', desc: 'Admisi, ICU, Rawat Inap, Admin' },
];
</script>

<template>
<div class="min-h-screen flex items-center justify-center relative overflow-hidden login-bg"
    style="font-family:'Inter','Plus Jakarta Sans',sans-serif">

    <!-- Animated background blobs -->
    <div class="login-blob login-blob-1"></div>
    <div class="login-blob login-blob-2"></div>
    <div class="login-blob login-blob-3"></div>

    <!-- Grid pattern overlay -->
    <div class="login-grid-overlay"></div>

    <!-- ── Main container ── -->
    <div class="relative z-10 w-full max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex flex-col lg:flex-row items-center gap-10 lg:gap-16">

        <!-- ── LEFT: Branding ── -->
        <div class="hidden lg:flex flex-col flex-1 text-white">
            <!-- Logo -->
            <div class="flex items-center gap-3 mb-10">
                <div class="w-12 h-12 rounded-2xl flex items-center justify-center login-logo-box">
                    <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M19 8H15V4H9v4H5v6h4v4h6v-4h4V8z"/>
                    </svg>
                </div>
                <div>
                    <p class="font-extrabold text-lg leading-tight tracking-tight">ICU Monitor</p>
                    <p class="text-white/50 text-xs font-medium">Sistem Manajemen Bed ICU</p>
                </div>
            </div>

            <!-- Live badge -->
            <div class="inline-flex items-center gap-2 self-start px-3 py-1.5 rounded-full mb-6 login-live-badge">
                <span class="w-2 h-2 rounded-full bg-emerald-300 ping-dot"></span>
                <span class="text-white/80 text-xs font-semibold">Live System — Aktif 24/7</span>
            </div>

            <!-- Headline -->
            <h1 class="font-extrabold leading-[1.15] mb-4" style="font-size:clamp(30px,3.5vw,44px); letter-spacing:-0.03em">
                Monitoring ICU
            </h1>
            <p class="text-white/60 leading-relaxed mb-10 max-w-sm" style="font-size:15px">
                Pantau status bed, kelola antrian pasien, dan koordinasi tim medis dalam satu platform terintegrasi.
            </p>

            <!-- Feature grid -->
            <div class="grid grid-cols-2 gap-3">
                <div v-for="feat in features" :key="feat.label" class="login-feat-card">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0 login-feat-icon">
                        <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="feat.icon"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-white font-semibold text-xs">{{ feat.label }}</p>
                        <p class="text-white/50 text-xs mt-0.5 leading-snug">{{ feat.desc }}</p>
                    </div>
                </div>
            </div>

            <p class="text-white/30 text-xs mt-10">© 2026 ICU Monitor · Sistem Informasi Manajemen RS</p>
        </div>

        <!-- ── RIGHT: Login Form ── -->
        <div class="w-full max-w-[400px] lg:max-w-[380px]">

            <!-- Mobile logo -->
            <div class="lg:hidden text-center mb-8">
                <div class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-3 login-logo-box">
                    <svg class="w-7 h-7 text-white" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M19 8H15V4H9v4H5v6h4v4h6v-4h4V8z"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-extrabold text-white">ICU Monitor</h1>
                <p class="text-white/50 text-sm mt-1">Sistem Manajemen Bed ICU</p>
            </div>

            <!-- Card -->
            <div class="login-form-card">
                <!-- Card header -->
                <div class="mb-6">
                    <h2 class="text-2xl font-extrabold" style="color:#0F1D2E; letter-spacing:-0.02em">Selamat Datang 👋</h2>
                    <p class="text-sm mt-1" style="color:#64748B">{{ headerSubtitle }}</p>
                </div>

                <!-- ── Pilihan metode login (tampil jika belum memilih) ── -->
                <div v-if="mode === null" class="space-y-3">
                    <button @click="mode = 'sso'"
                        class="w-full flex items-center justify-center gap-2.5 py-3 rounded-xl text-sm font-bold transition-all login-sso-btn">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        Login dengan SSO Rumah Sakit
                    </button>

                    <button @click="mode = 'local'"
                        class="w-full flex items-center justify-center gap-2.5 py-3 rounded-xl text-sm font-bold transition-all login-local-btn">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        Login Lokal (Username & Password)
                    </button>
                </div>

                <!-- ── Mode: SSO ── -->
                <div v-else-if="mode === 'sso'" class="space-y-4">
                    <button @click="loginSSO" :disabled="ssoLoading"
                        class="w-full flex items-center justify-center gap-2.5 py-3 rounded-xl text-sm font-bold transition-all disabled:opacity-60 login-sso-btn">
                        <svg v-if="!ssoLoading" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        {{ ssoLoading ? 'Menghubungkan...' : 'Login dengan SSO Rumah Sakit' }}
                    </button>

                    <button type="button" @click="mode = null" :disabled="ssoLoading"
                        class="w-full flex items-center justify-center gap-1.5 text-xs font-semibold transition-opacity disabled:opacity-50"
                        style="color:#64748B">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Pilih metode lain
                    </button>
                </div>

                <!-- ── Mode: Login Lokal ── -->
                <template v-else-if="mode === 'local'">

                <!-- Error -->
                <Transition enter-active-class="transition-all duration-200" enter-from-class="opacity-0 -translate-y-1">
                    <div v-if="errorMsg" class="flex items-center gap-2.5 px-3.5 py-3 rounded-xl text-sm mb-4"
                        style="background:#FEE9E8; border:1.5px solid rgba(185,28,28,0.2); color:#B91C1C">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <span class="font-medium">{{ errorMsg }}</span>
                    </div>
                </Transition>

                <!-- Form -->
                <form @submit.prevent="submit" class="space-y-4">
                    <!-- Username -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold" style="color:#0F1D2E">Username</label>
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 pointer-events-none" style="color:#9AA5B1"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            <input v-model="form.username" type="text" required autocomplete="username"
                                placeholder="Masukkan username"
                                class="w-full pl-10 pr-4 py-3 text-sm rounded-xl outline-none transition-all login-input"
                                :class="{ 'login-input-error': form.errors.username }"/>
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold" style="color:#0F1D2E">Password</label>
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 pointer-events-none" style="color:#9AA5B1"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            <input v-model="form.password" :type="showPass ? 'text' : 'password'"
                                required autocomplete="current-password" placeholder="••••••••"
                                class="w-full pl-10 pr-10 py-3 text-sm rounded-xl outline-none transition-all login-input"
                                :class="{ 'login-input-error': form.errors.password }"/>
                            <button type="button" @click="showPass=!showPass"
                                class="absolute right-3 top-1/2 -translate-y-1/2 opacity-50 hover:opacity-80 transition-opacity">
                                <svg v-if="!showPass" class="w-4 h-4" style="color:#64748B" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <svg v-else class="w-4 h-4" style="color:#64748B" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Remember me -->
                    <label class="flex items-center gap-2.5 cursor-pointer select-none">
                        <div class="relative w-4 h-4 flex-shrink-0">
                            <input v-model="form.remember" type="checkbox" class="sr-only"/>
                            <div class="w-4 h-4 rounded transition-all"
                                :style="form.remember ? 'background:#00A884; border:1.5px solid #00A884' : 'background:#F7F9FC; border:1.5px solid rgba(15,29,46,0.15)'">
                                <svg v-if="form.remember" class="w-3 h-3 mx-auto mt-0.5" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                        </div>
                        <span class="text-xs" style="color:#64748B">Ingat saya</span>
                    </label>

                    <!-- Submit -->
                    <button type="submit" :disabled="form.processing"
                        class="w-full flex items-center justify-center gap-2 py-3 rounded-xl text-sm font-bold disabled:opacity-60 transition-all login-submit-btn">
                        <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                        </svg>
                        {{ form.processing ? 'Memproses...' : 'Masuk ke Sistem' }}
                    </button>
                </form>

                <!-- Tombol kembali ke pilihan metode (hanya jika SSO tersedia) -->
                <button v-if="keycloakAvailable" type="button" @click="mode = null"
                    class="w-full flex items-center justify-center gap-1.5 text-xs font-semibold mt-4 transition-opacity"
                    style="color:#64748B">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Pilih metode lain
                </button>

                <!-- Offline note -->
                <p v-if="!keycloakAvailable" class="text-center text-xs mt-4" style="color:#9AA5B1">
                    🔒 Mode offline — SSO tidak tersedia
                </p>

                </template>
                <!-- ── /Mode: Login Lokal ── -->
            </div>

            <p class="text-center text-xs mt-5 text-white/40">ICU Monitor v3.0 · Sistem Informasi Manajemen RS</p>
        </div>
    </div>
</div>
</template>

<style scoped>
/* ── Background ── */
.login-bg {
    background: linear-gradient(145deg, #0a2018 0%, #0d3028 40%, #0f4a30 70%, #1a6b4a 100%);
}

/* Animated blobs */
.login-blob {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    opacity: 0.25;
    animation: blobFloat 12s ease-in-out infinite;
    pointer-events: none;
}
.login-blob-1 {
    width: 500px; height: 500px;
    background: radial-gradient(circle, #00A884, #007a61);
    top: -150px; left: -100px;
    animation-delay: 0s;
}
.login-blob-2 {
    width: 400px; height: 400px;
    background: radial-gradient(circle, #00C58F, #007a61);
    bottom: -100px; right: -80px;
    animation-delay: -4s;
}
.login-blob-3 {
    width: 300px; height: 300px;
    background: radial-gradient(circle, #00A884, #007a61);
    top: 50%; right: 20%;
    animation-delay: -8s;
    opacity: 0.15;
}
@keyframes blobFloat {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33%       { transform: translate(30px, -30px) scale(1.05); }
    66%       { transform: translate(-20px, 20px) scale(0.95); }
}

/* Grid overlay */
.login-grid-overlay {
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
    background-size: 40px 40px;
    pointer-events: none;
}

/* Logo box */
.login-logo-box {
    background: linear-gradient(135deg, rgba(255,255,255,0.2), rgba(255,255,255,0.08));
    border: 1.5px solid rgba(255,255,255,0.25);
    backdrop-filter: blur(10px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
}

/* Live badge */
.login-live-badge {
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.15);
    backdrop-filter: blur(8px);
}

/* Headline accent */
.login-headline-accent {
    background: linear-gradient(135deg, #34d399, #34d399);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* Feature card */
.login-feat-card {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px;
    border-radius: 12px;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.10);
    backdrop-filter: blur(8px);
    transition: background 0.2s, transform 0.2s;
}
.login-feat-card:hover {
    background: rgba(255,255,255,0.10);
    transform: translateY(-2px);
}
.login-feat-icon {
    background: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.15);
}

/* Form card */
.login-form-card {
    background: rgba(255,255,255,0.97);
    border-radius: 24px;
    padding: 36px 32px;
    box-shadow: 0 24px 64px rgba(0,0,0,0.35), 0 8px 24px rgba(0,0,0,0.2);
    border: 1px solid rgba(255,255,255,0.9);
}
@media (max-width: 480px) {
    .login-form-card {
        padding: 28px 24px;
        border-radius: 20px;
    }
}

/* Input */
.login-input {
    border: 1.5px solid rgba(15,29,46,0.12);
    background: #F7F9FC;
    color: #0F1D2E;
}
.login-input:focus {
    border-color: #00A884 !important;
    background: #ECFDF5 !important;
    box-shadow: 0 0 0 3px rgba(0,168,132,0.12) !important;
}
.login-input-error {
    border-color: rgba(185,28,28,0.5) !important;
}
.login-input::placeholder { color: #9AA5B1; }

/* SSO button */
.login-sso-btn {
    background: linear-gradient(135deg, #00A884, #007a61);
    color: white;
    box-shadow: 0 4px 14px rgba(0,168,132,0.3);
}
.login-sso-btn:hover:not(:disabled) {
    filter: brightness(1.08);
    box-shadow: 0 6px 20px rgba(0,168,132,0.4);
    transform: translateY(-1px);
}

/* Login lokal button (di layar pilihan metode) */
.login-local-btn {
    background: #F7F9FC;
    color: #0F1D2E;
    border: 1.5px solid rgba(15,29,46,0.12);
}
.login-local-btn:hover {
    background: #ECFDF5;
    border-color: #00A884;
    transform: translateY(-1px);
}

/* Submit button */
.login-submit-btn {
    background: linear-gradient(135deg, #00A884, #007a61);
    color: white;
    box-shadow: 0 4px 14px rgba(0,168,132,0.3);
    position: relative;
    overflow: hidden;
}
.login-submit-btn::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, transparent 0%, rgba(255,255,255,0.1) 50%, transparent 100%);
    transform: translateX(-100%);
    transition: transform 0.4s ease;
}
.login-submit-btn:hover:not(:disabled)::before {
    transform: translateX(100%);
}
.login-submit-btn:hover:not(:disabled) {
    filter: brightness(1.08);
    box-shadow: 0 6px 20px rgba(0,168,132,0.4);
    transform: translateY(-1px);
}
</style>