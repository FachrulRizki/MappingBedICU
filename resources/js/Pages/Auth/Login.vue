<script setup>
import { ref, watch } from 'vue';
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
</script>

<template>
<div class="min-h-screen flex" style="font-family:'Inter','Plus Jakarta Sans',sans-serif">

    <!-- ── Kiri: ilustrasi / branding (hidden mobile) ─── -->
    <div class="hidden lg:flex lg:w-1/2 xl:w-3/5 flex-col items-center justify-center relative overflow-hidden"
        style="background:linear-gradient(145deg,#00A884 0%,#008C6E 50%,#006B55 100%)">

        <!-- Decorative blobs -->
        <div class="absolute -top-32 -left-32 w-80 h-80 rounded-full opacity-20"
            style="background:radial-gradient(circle,#fff,transparent)"></div>
        <div class="absolute -bottom-24 -right-20 w-64 h-64 rounded-full opacity-15"
            style="background:radial-gradient(circle,#fff,transparent)"></div>
        <div class="absolute top-1/3 right-10 w-40 h-40 rounded-full opacity-10"
            style="background:radial-gradient(circle,#fff,transparent)"></div>

        <div class="relative z-10 text-center px-12">
            <!-- Logo -->
            <div class="w-20 h-20 rounded-2xl flex items-center justify-center mx-auto mb-6"
                style="background:rgba(255,255,255,0.2); border:2px solid rgba(255,255,255,0.3);
                       box-shadow:0 8px 32px rgba(0,0,0,0.15)">
                <svg class="w-10 h-10" fill="white" viewBox="0 0 24 24">
                    <path d="M19 8H15V4H9v4H5v6h4v4h6v-4h4V8z"/>
                </svg>
            </div>
            <h1 class="text-4xl font-bold text-white mb-3" style="letter-spacing:-0.03em">ICU Monitor</h1>
            <p class="text-white/75 text-lg mb-8">Sistem Monitoring Bed ICU</p>

            <!-- Feature chips -->
            <div class="flex flex-wrap justify-center gap-3">
                <div v-for="feat in ['Real-time Monitoring','Manajemen Bed','SPRI Digital','Multi-role Access']"
                    :key="feat"
                    class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-medium"
                    style="background:rgba(255,255,255,0.15); color:rgba(255,255,255,0.9);
                           border:1px solid rgba(255,255,255,0.2)">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    {{ feat }}
                </div>
            </div>
        </div>
    </div>

    <!-- ── Kanan: form login ─────────────────────────────── -->
    <div class="flex-1 flex items-center justify-center p-6 lg:p-10"
        style="background:var(--bg-main)">

        <div class="w-full max-w-sm">

            <!-- Logo mobile only -->
            <div class="lg:hidden text-center mb-8">
                <div class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-3"
                    style="background:linear-gradient(135deg,#00A884,#008C6E); box-shadow:0 6px 20px rgba(0,168,132,0.3)">
                    <svg class="w-7 h-7" fill="white" viewBox="0 0 24 24">
                        <path d="M19 8H15V4H9v4H5v6h4v4h6v-4h4V8z"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold" style="color:var(--text-primary)">ICU Monitor</h1>
                <p class="text-sm mt-1" style="color:var(--text-secondary)">Sistem Monitoring Bed ICU</p>
            </div>

            <h2 class="text-xl font-bold mb-1 text-center" style="color:var(--text-primary)">Selamat Datang</h2>
            <p class="text-sm mb-7 text-center" style="color:var(--text-secondary)">Masuk ke akun Anda untuk melanjutkan</p>
            
            <!-- Card -->
            <div class="card-dark p-6 sm:p-7 space-y-5">

                <!-- SSO Button -->
                <div v-if="keycloakAvailable" class="space-y-4">
                    <button @click="loginSSO" :disabled="ssoLoading"
                        class="w-full flex items-center justify-center gap-3 py-3 rounded-xl text-sm font-semibold transition-all disabled:opacity-60"
                        style="background:linear-gradient(135deg,#1a6fde,#0f4f9e); color:white;
                               box-shadow:0 4px 16px rgba(26,111,222,0.25)">
                        <svg v-if="!ssoLoading" class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        <svg v-else class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        {{ ssoLoading ? 'Menghubungkan...' : 'Login dengan SSO Rumah Sakit' }}
                    </button>
                    <div class="flex items-center gap-3">
                        <div class="flex-1 h-px" style="background:var(--border-default)"></div>
                        <span class="text-xs" style="color:var(--text-muted)">atau login lokal</span>
                        <div class="flex-1 h-px" style="background:var(--border-default)"></div>
                    </div>
                </div>

                <!-- Error -->
                <Transition enter-active-class="transition-all duration-200" enter-from-class="opacity-0 -translate-y-1">
                    <div v-if="errorMsg"
                        class="flex items-center gap-2.5 px-3.5 py-2.5 rounded-xl text-sm"
                        style="background:var(--pill-reject-bg); border:1px solid rgba(231,76,60,0.25); color:var(--pill-reject-color)">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        {{ errorMsg }}
                    </div>
                </Transition>

                <!-- Form -->
                <form @submit.prevent="submit" class="space-y-4">
                    <!-- Username -->
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-primary)">Username</label>
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 pointer-events-none"
                                style="color:var(--text-muted)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            <input v-model="form.username" type="text" required autocomplete="username"
                                placeholder="Masukkan username"
                                class="w-full pl-10 pr-4 py-2.5 text-sm rounded-xl outline-none transition-all"
                                :style="`border:1px solid ${form.errors.username ? 'rgba(231,76,60,0.5)' : 'var(--border-input)'};
                                         background:var(--bg-input); color:var(--text-primary);`"
                                @focus="e => e.target.style.borderColor='var(--border-input-focus)'"
                                @blur="e => e.target.style.borderColor=form.errors.username?'rgba(231,76,60,0.5)':'var(--border-input)'"/>
                        </div>
                    </div>

                    <!-- Password -->
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-primary)">Password</label>
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 pointer-events-none"
                                style="color:var(--text-muted)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            <input v-model="form.password" :type="showPass ? 'text' : 'password'"
                                required autocomplete="current-password" placeholder="••••••••"
                                class="w-full pl-10 pr-10 py-2.5 text-sm rounded-xl outline-none transition-all"
                                :style="`border:1px solid ${form.errors.password ? 'rgba(231,76,60,0.5)' : 'var(--border-input)'};
                                         background:var(--bg-input); color:var(--text-primary);`"
                                @focus="e => e.target.style.borderColor='var(--border-input-focus)'"
                                @blur="e => e.target.style.borderColor=form.errors.password?'rgba(231,76,60,0.5)':'var(--border-input)'"/>
                            <button type="button" @click="showPass = !showPass"
                                class="absolute right-3 top-1/2 -translate-y-1/2 transition-opacity"
                                style="color:var(--text-muted); opacity:0.7">
                                <svg v-if="!showPass" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
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
                                :style="form.remember
                                    ? 'background:var(--text-accent); border:1px solid var(--text-accent)'
                                    : 'background:var(--bg-input); border:1px solid var(--border-input)'">
                                <svg v-if="form.remember" class="w-3 h-3 mx-auto mt-0.5" fill="none" viewBox="0 0 24 24"
                                    stroke="white" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                        </div>
                        <span class="text-xs" style="color:var(--text-secondary)">Ingat saya</span>
                    </label>

                    <!-- Submit -->
                    <button type="submit" :disabled="form.processing"
                        class="w-full flex items-center justify-center gap-2 py-3 rounded-xl text-sm font-semibold disabled:opacity-60"
                        style="background:var(--text-accent); color:var(--text-on-accent); transition:all .2s ease"
                        @mouseenter="e => { if(!form.processing) { e.currentTarget.style.filter='brightness(1.06)'; e.currentTarget.style.transform='translateY(-1px)'; e.currentTarget.style.boxShadow='0 6px 20px rgba(0,168,132,0.35)'; }}"
                        @mouseleave="e => { e.currentTarget.style.filter=''; e.currentTarget.style.transform=''; e.currentTarget.style.boxShadow=''; }"
                        @mousedown="e => e.currentTarget.style.transform='scale(0.97)'"
                        @mouseup="e => e.currentTarget.style.transform=''">
                        <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                        </svg>
                        {{ form.processing ? 'Memproses...' : 'Masuk' }}
                    </button>
                </form>

                <p v-if="!keycloakAvailable" class="text-center text-xs" style="color:var(--text-muted)">
                    🔒 Mode offline — SSO tidak tersedia
                </p>
            </div>

            <p class="text-center text-xs mt-6" style="color:var(--text-muted)">
                ICU Monitor v2.0 &nbsp;·&nbsp; Sistem Informasi Manajemen RS
            </p>
        </div>
    </div>
</div>
</template>
