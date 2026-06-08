<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    flash: { type: Object, default: () => ({}) },
});

const form = useForm({
    email:    '',
    password: '',
    remember: false,
});

const showPass   = ref(false);
const errorMsg   = ref('');

watch(() => props.flash, (f) => {
    if (f?.error) errorMsg.value = f.error;
}, { immediate: true, deep: true });

const submit = () => {
    errorMsg.value = '';
    form.post(route('login'), {
        onError: () => { errorMsg.value = form.errors.email || form.errors.password || 'Login gagal.'; },
    });
};
</script>

<template>
    <div class="min-h-screen flex items-center justify-center p-4"
        style="background:var(--bg-main); font-family:'Plus Jakarta Sans',sans-serif">

        <!-- Card -->
        <div class="w-full max-w-sm">

            <!-- Logo + Title -->
            <div class="text-center mb-8">
                <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4"
                    style="background:linear-gradient(135deg,#2DD9A4,#1A9E8F); box-shadow:0 8px 24px rgba(45,217,164,0.3)">
                    <svg class="w-8 h-8" fill="white" viewBox="0 0 24 24">
                        <path d="M19 8H15V4H9v4H5v6h4v4h6v-4h4V8z"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold" style="color:var(--text-primary); letter-spacing:-0.02em">ICU Monitor</h1>
                <p class="text-sm mt-1" style="color:var(--text-secondary)">Sistem Monitoring Bed ICU</p>
            </div>

            <!-- Form Card -->
            <div class="card-dark p-6 sm:p-8">
                <h2 class="text-base font-bold mb-1" style="color:var(--text-primary)">Masuk ke Sistem</h2>
                <p class="text-xs mb-6" style="color:var(--text-secondary)">Gunakan akun yang diberikan administrator</p>

                <!-- Error banner -->
                <div v-if="errorMsg"
                    class="flex items-center gap-2 px-3 py-2.5 rounded-xl mb-4 text-sm"
                    style="background:rgba(224,112,80,0.1); border:1px solid rgba(224,112,80,0.3); color:#E07050">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    {{ errorMsg }}
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <!-- Email -->
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-primary)">
                            Email
                        </label>
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4" style="color:var(--text-secondary)"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"/>
                            </svg>
                            <input
                                v-model="form.email"
                                type="email"
                                required
                                autocomplete="email"
                                placeholder="nama@icu.rs"
                                class="w-full pl-10 pr-4 py-2.5 text-sm rounded-xl outline-none transition-all"
                                :style="`
                                    border: 1px solid ${form.errors.email ? 'rgba(224,112,80,0.5)' : 'var(--border-default)'};
                                    background: var(--bg-input);
                                    color: var(--text-primary);
                                `"
                                @focus="$el.style.borderColor='var(--border-input-focus)'"
                                @blur="$el.style.borderColor=form.errors.email?'rgba(224,112,80,0.5)':'var(--border-default)'"
                            />
                        </div>
                    </div>

                    <!-- Password -->
                    <div>
                        <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-primary)">
                            Password
                        </label>
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4" style="color:var(--text-secondary)"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            <input
                                v-model="form.password"
                                :type="showPass ? 'text' : 'password'"
                                required
                                autocomplete="current-password"
                                placeholder="••••••••"
                                class="w-full pl-10 pr-10 py-2.5 text-sm rounded-xl outline-none transition-all"
                                :style="`
                                    border: 1px solid ${form.errors.password ? 'rgba(224,112,80,0.5)' : 'var(--border-default)'};
                                    background: var(--bg-input);
                                    color: var(--text-primary);
                                `"
                                @focus="$el.style.borderColor='var(--border-input-focus)'"
                                @blur="$el.style.borderColor=form.errors.password?'rgba(224,112,80,0.5)':'var(--border-default)'"
                            />
                            <!-- Toggle show/hide -->
                            <button type="button" @click="showPass = !showPass"
                                class="absolute right-3 top-1/2 -translate-y-1/2 transition-opacity"
                                style="color:var(--text-secondary); opacity:0.6"
                                @mouseenter="$el.style.opacity='1'" @mouseleave="$el.style.opacity='0.6'">
                                <svg v-if="!showPass" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Remember me -->
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <div class="relative w-4 h-4 flex-shrink-0">
                            <input v-model="form.remember" type="checkbox" class="sr-only"/>
                            <div class="w-4 h-4 rounded border transition-all"
                                :style="form.remember
                                    ? 'background:#2DD9A4; border-color:#2DD9A4'
                                    : 'background:var(--bg-input); border-color:var(--border-default)'">
                                <svg v-if="form.remember" class="w-3 h-3 mx-auto mt-0.5" fill="none" viewBox="0 0 24 24" stroke="#0D1A17" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                        </div>
                        <span class="text-xs" style="color:var(--text-secondary)">Ingat saya</span>
                    </label>

                    <!-- Submit -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full flex items-center justify-center gap-2 py-3 rounded-xl text-sm font-bold transition-all disabled:opacity-60"
                        style="background:#2DD9A4; color:#0D1A17">
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
            </div>

            <!-- Hint akun demo -->
            <!-- <div class="mt-4 p-4 rounded-xl text-xs" style="background:var(--bg-card); border:1px solid var(--border-default)">
                <p class="font-bold mb-2" style="color:var(--text-secondary)">Akun Demo:</p>
                <div class="space-y-1" style="color:var(--text-secondary)">
                    <p><span class="font-mono" style="color:var(--text-accent)">admin@icu.rs</span> / admin123 — Admin</p>
                    <p><span class="font-mono" style="color:#4A90D9">admisi1@icu.rs</span> / admisi123 — Admisi</p>
                    <p><span class="font-mono" style="color:#2DD9A4">icu1@icu.rs</span> / icu123 — Petugas ICU</p>
                    <p><span class="font-mono" style="color:#D9517A">poli.dalam@icu.rs</span> / ruang123 — Petugas Ruang</p>
                </div>
            </div> -->
        </div>
    </div>
</template>
