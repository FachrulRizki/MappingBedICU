<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    flash:               { type: Object,  default: () => ({}) },
    keycloakAvailable:   { type: Boolean, default: false },
    keycloakRedirectUrl: { type: String,  default: '' },
});

const ssoLoading  = ref(false);
const showModal   = ref(false);
const errorMsg    = ref('');
const isRoleError = ref(false);

watch(() => props.flash, (f) => {
    if (f?.error) {
        errorMsg.value  = f.error;
        isRoleError.value = f.error.toLowerCase().includes('role') ||
                            f.error.toLowerCase().includes('sesuai') ||
                            f.error.toLowerCase().includes('akses');
        showModal.value = true;
    }
}, { immediate: true, deep: true });

const closeModal = () => { showModal.value = false; };

const loginSSO = () => {
    if (!props.keycloakRedirectUrl || !props.keycloakAvailable) return;
    ssoLoading.value = true;
    window.location.href = props.keycloakRedirectUrl;
};

const year = new Date().getFullYear();
</script>

<template>
<div class="page">

    <!-- Background -->
    <div class="bg" aria-hidden="true">
        <div class="bg-grad"></div>
        <div class="bg-grid"></div>
    </div>

    <!-- Center column -->
    <div class="center">

        <!-- Brand -->
        <div class="brand">
            <div class="brand-icon">
                <svg viewBox="0 0 24 24" fill="white" class="w-6 h-6">
                    <path d="M19 8H15V4H9v4H5v6h4v4h6v-4h4V8z"/>
                </svg>
            </div>
            <div>
                <p class="brand-name">Booking ICU &amp; HCU</p>
                <p class="brand-sub">RS Urip Sumoharjo</p>
            </div>
        </div>

        <!-- Card -->
        <div class="card">
            <h2 class="card-title">Masuk ke Akun Anda</h2>
            <p class="card-sub">Masuk menggunakan login SSO RS Urip Sumoharjo</p>

            <!-- SSO Button -->
            <button @click="loginSSO" :disabled="ssoLoading || !keycloakAvailable" class="btn-sso">
                <span v-if="ssoLoading" class="btn-inner">
                    <svg viewBox="0 0 24 24" fill="none" class="animate-spin w-5 h-5">
                        <circle class="opacity-20" cx="12" cy="12" r="10" stroke="white" stroke-width="4"/>
                        <path class="opacity-80" fill="white" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <span>Menghubungkan...</span>
                </span>
                <span v-else class="btn-inner">
                    <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                    </svg>
                    <span>Login dengan SSO</span>
                </span>
            </button>

            <!-- SSO tidak tersedia -->
            <div v-if="!keycloakAvailable" class="sso-down">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4 flex-shrink-0">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636a9 9 0 010 12.728M5.636 5.636a9 9 0 000 12.728M12 8v4m0 4h.01"/>
                </svg>
                <span>Server SSO tidak dapat dijangkau saat ini</span>
            </div>

            <!-- Secure badge -->
            <div v-else class="secure-badge">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                <span>Koneksi aman &amp; terenkripsi</span>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>Belum punya akun? <strong>Hubungi Administrator</strong></p>
            <p>© {{ year }} RS Urip Sumoharjo. All rights reserved.</p>
        </div>

    </div>

    <!-- ══ ERROR MODAL ══ -->
    <Transition name="modal">
        <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
            <div class="modal-box" :class="isRoleError ? 'modal-role' : 'modal-err'">

                <!-- Close button -->
                <button class="modal-close" @click="closeModal" aria-label="Tutup">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>

                <!-- ── Role error ── -->
                <template v-if="isRoleError">
                    <div class="modal-icon modal-icon-role">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-7 h-7">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                        </svg>
                    </div>
                    <h3 class="modal-title">Akun Belum Memiliki Akses</h3>
                    <p class="modal-body">
                        Akun SSO Anda belum terdaftar atau belum memiliki role yang sesuai untuk mengakses aplikasi ini.
                    </p>
                    <button @click="closeModal" class="modal-btn modal-btn-role">Mengerti</button>
                </template>

                <!-- ── General error ── -->
                <template v-else>
                    <div class="modal-icon modal-icon-err">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-7 h-7">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <h3 class="modal-title">Login Gagal</h3>
                    <p class="modal-body">{{ errorMsg }}</p>
                    <button @click="closeModal" class="modal-btn modal-btn-err">Coba Lagi</button>
                </template>

            </div>
        </div>
    </Transition>

</div>
</template>

<style scoped>
/* ── Page ── */
.page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    font-family: 'Inter', 'Plus Jakarta Sans', system-ui, sans-serif;
    padding: 24px 16px;
}

/* ── Background ── */
.bg { position: fixed; inset: 0; z-index: 0; pointer-events: none; }
.bg-grad {
    position: absolute; inset: 0;
    background: radial-gradient(ellipse 80% 60% at 50% 0%, #e8f0fe 0%, #f0f4ff 40%, #f8fafc 100%);
}
.bg-grid {
    position: absolute; inset: 0;
    background-image:
        linear-gradient(rgba(99,102,241,0.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(99,102,241,0.04) 1px, transparent 1px);
    background-size: 32px 32px;
}

/* ── Center ── */
.center {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 420px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 24px;
}

/* ── Brand ── */
.brand { display: flex; align-items: center; gap: 12px; }
.brand-icon {
    width: 48px; height: 48px;
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 8px 20px rgba(79,70,229,0.3);
    flex-shrink: 0;
}
.brand-name { font-size: 18px; font-weight: 800; color: #111827; letter-spacing: -0.02em; line-height: 1.2; }
.brand-sub  { font-size: 12px; color: #6b7280; font-weight: 500; margin-top: 2px; }

/* ── Card ── */
.card {
    width: 100%;
    background: #fff;
    border-radius: 20px;
    padding: 36px 32px;
    box-shadow: 0 0 0 1px rgba(0,0,0,0.06), 0 4px 6px rgba(0,0,0,0.04), 0 20px 40px rgba(0,0,0,0.08);
}
@media (max-width: 480px) { .card { padding: 28px 24px; } }

.card-title { font-size: 22px; font-weight: 800; color: #111827; letter-spacing: -0.025em; margin-bottom: 6px; text-align: center; }
.card-sub   { font-size: 13px; color: #6b7280; text-align: center; margin-bottom: 28px; line-height: 1.5; }

/* ── SSO Button ── */
.btn-sso {
    width: 100%;
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    border: none; border-radius: 12px;
    padding: 14px; cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 4px 14px rgba(79,70,229,0.35);
    margin-bottom: 14px;
}
.btn-sso:hover:not(:disabled) { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(79,70,229,0.45); filter: brightness(1.06); }
.btn-sso:active:not(:disabled) { transform: translateY(0); }
.btn-sso:disabled { opacity: 0.5; cursor: not-allowed; }
.btn-inner { display: flex; align-items: center; justify-content: center; gap: 10px; font-size: 14px; font-weight: 700; color: #fff; letter-spacing: -0.01em; }

/* SSO down / secure */
.sso-down    { display: flex; align-items: center; justify-content: center; gap: 6px; font-size: 12px; color: #ef4444; font-weight: 500; }
.secure-badge{ display: flex; align-items: center; justify-content: center; gap: 6px; font-size: 12px; color: #059669; font-weight: 500; }

/* ── Footer ── */
.footer { text-align: center; font-size: 12px; color: #9ca3af; line-height: 1.8; }
.footer strong { color: #374151; font-weight: 600; }

/* ══ MODAL ══ */
.modal-overlay {
    position: fixed; inset: 0; z-index: 50;
    background: rgba(0,0,0,0.45);
    backdrop-filter: blur(4px);
    display: flex; align-items: center; justify-content: center;
    padding: 16px;
}
.modal-box {
    width: 100%; max-width: 360px;
    background: #fff;
    border-radius: 20px;
    padding: 32px 28px 28px;
    box-shadow: 0 24px 64px rgba(0,0,0,0.2);
    text-align: center;
    position: relative;
}

/* Close */
.modal-close {
    position: absolute; top: 14px; right: 14px;
    width: 28px; height: 28px;
    background: #f3f4f6; border: none; border-radius: 8px;
    cursor: pointer; color: #6b7280;
    display: flex; align-items: center; justify-content: center;
    transition: background 0.15s;
}
.modal-close:hover { background: #e5e7eb; color: #111827; }

/* Icon */
.modal-icon {
    width: 56px; height: 56px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 16px;
}
.modal-icon-role { background: #fef3c7; color: #d97706; }
.modal-icon-err  { background: #fee2e2; color: #dc2626; }

/* Text */
.modal-title { font-size: 18px; font-weight: 800; color: #111827; letter-spacing: -0.02em; margin-bottom: 10px; }
.modal-body  { font-size: 13px; color: #6b7280; line-height: 1.6; margin-bottom: 16px; }

/* Hint (role only) */
.modal-hint {
    display: flex; align-items: flex-start; gap: 8px;
    background: #fffbeb; border: 1px solid #fde68a;
    border-radius: 10px; padding: 10px 12px;
    font-size: 12px; color: #92400e;
    text-align: left; margin-bottom: 20px; line-height: 1.5;
}
.modal-hint strong { font-weight: 700; }

/* Buttons */
.modal-btn {
    width: 100%; padding: 12px;
    border: none; border-radius: 10px;
    font-size: 14px; font-weight: 700;
    cursor: pointer; transition: all 0.2s;
}
.modal-btn-role { background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; box-shadow: 0 4px 12px rgba(245,158,11,0.35); }
.modal-btn-role:hover { filter: brightness(1.06); transform: translateY(-1px); }
.modal-btn-err  { background: linear-gradient(135deg, #ef4444, #dc2626); color: #fff; box-shadow: 0 4px 12px rgba(239,68,68,0.35); }
.modal-btn-err:hover  { filter: brightness(1.06); transform: translateY(-1px); }

/* ── Modal transition ── */
.modal-enter-active { transition: all 0.25s cubic-bezier(0.34,1.56,0.64,1); }
.modal-leave-active { transition: all 0.18s ease; }
.modal-enter-from .modal-box { opacity: 0; transform: scale(0.92) translateY(12px); }
.modal-leave-to   .modal-box { opacity: 0; transform: scale(0.96) translateY(6px); }
.modal-enter-from { background: transparent; }
.modal-leave-to   { background: transparent; }
</style>
