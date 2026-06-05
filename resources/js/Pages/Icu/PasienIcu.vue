<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout    from '@/Layouts/AppLayout.vue';
import AlertModal   from '@/Components/AlertModal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';

const props = defineProps({
    pasienIcu: { type: Array, default: () => [] },
    riwayat:   { type: Array, default: () => [] },
    flash:     { type: Object, default: () => ({}) },
});

const alert   = ref({ show: false, type: 'success', title: '', message: '' });
const confirm = ref({ show: false, id: null, nama: '' });
const showAlert = (t, h, m) => { alert.value = { show: true, type: t, title: h, message: m }; };
watch(() => props.flash, (f) => {
    if (f?.success) showAlert('success', 'Berhasil!', f.success);
    if (f?.error)   showAlert('error',   'Gagal!',    f.error);
}, { immediate: true, deep: true });

const activeTab  = ref('aktif');
const openConfirm = (p) => { confirm.value = { show: true, id: p.id, nama: p.nama_pasien }; };
const doPulang    = () => { router.post(route('icu.pulangkan', confirm.value.id)); confirm.value.show = false; };

// ── Gender theme helpers ───────────────────────────────────
// Card border accent + top stripe + badge — vivid enough for both light & dark
const gCardBorder = (g) => {
    if (g === 'L') return 'rgba(74,144,217,0.45)';
    if (g === 'P') return 'rgba(217,81,122,0.45)';
    return 'var(--border-default)';
};
const gStripe = (g) => {
    if (g === 'L') return '#4A90D9';
    if (g === 'P') return '#D9517A';
    return '#2DD9A4';
};
const gBadgeBg = (g) => {
    if (g === 'L') return 'rgba(74,144,217,0.15)';
    if (g === 'P') return 'rgba(217,81,122,0.15)';
    return 'rgba(45,217,164,0.12)';
};
const gBadgeColor = (g) => {
    if (g === 'L') return '#4A90D9';
    if (g === 'P') return '#D9517A';
    return '#2DD9A4';
};
const gLabel = (g) => g === 'L' ? '♂ Pria' : g === 'P' ? '♀ Wanita' : '— ?';
const gPulse = (g) => g === 'L' ? '#4A90D9' : g === 'P' ? '#D9517A' : '#2DC5B2';
</script>

<template>
    <AppLayout :flash="flash" page-title="Pasien di ICU">
        <AlertModal v-bind="alert" @close="alert.show = false"/>
        <ConfirmModal :show="confirm.show" title="Pulangkan Pasien?"
            :message="`${confirm.nama} akan dipulangkan dan bed kembali kosong.`"
            confirm-text="Ya, Pulangkan" :danger="true"
            @confirm="doPulang" @cancel="confirm.show = false"/>

        <div class="p-4 sm:p-6 space-y-4" style="font-family:'Plus Jakarta Sans',sans-serif">

            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h1 class="font-bold text-lg" style="color:var(--text-primary); font-family:'Plus Jakarta Sans',sans-serif">Pasien di ICU</h1>
                    <p class="text-sm mt-0.5" style="color:var(--text-secondary)">Monitoring pasien aktif dan riwayat</p>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <!-- Gender summary badges -->
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full"
                        style="background:rgba(74,144,217,0.15); color:#4A90D9; border:1px solid rgba(74,144,217,0.3)">
                        ♂ {{ pasienIcu.filter(p => p.jenis_kelamin === 'L').length }} Pria
                    </span>
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full"
                        style="background:rgba(217,81,122,0.15); color:#D9517A; border:1px solid rgba(217,81,122,0.3)">
                        ♀ {{ pasienIcu.filter(p => p.jenis_kelamin === 'P').length }} Wanita
                    </span>
                    <span class="flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full"
                        style="background:rgba(45,217,164,0.12); color:#2DD9A4; border:1px solid rgba(45,217,164,0.2)">
                        <span class="w-1.5 h-1.5 rounded-full pulse-teal" style="background:#2DC5B2"></span>
                        {{ pasienIcu.length }} Aktif
                    </span>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex gap-1 p-1 rounded-xl w-full sm:w-fit" style="background:var(--bg-main); border:1px solid var(--border-default)">
                <button @click="activeTab='aktif'" class="flex-1 sm:flex-none px-4 py-1.5 rounded-lg text-sm font-semibold transition-all flex items-center gap-1.5"
                    :style="activeTab==='aktif'
                        ? 'background:var(--bg-surface); color:#1A9E8F; box-shadow:0 1px 4px rgba(26,158,143,.12)'
                        : 'color:var(--text-secondary)'">
                    Pasien Aktif
                    <span class="text-xs px-1.5 rounded-full" style="background:rgba(45,217,164,0.15); color:#2DD9A4">{{ pasienIcu.length }}</span>
                </button>
                <button @click="activeTab='riwayat'" class="flex-1 sm:flex-none px-4 py-1.5 rounded-lg text-sm font-semibold transition-all flex items-center gap-1.5"
                    :style="activeTab==='riwayat'
                        ? 'background:var(--bg-surface); color:#1A9E8F; box-shadow:0 1px 4px rgba(26,158,143,.12)'
                        : 'color:var(--text-secondary)'">
                    Riwayat
                    <span class="text-xs px-1.5 rounded-full" style="background:var(--bg-input); color:var(--text-secondary)">{{ riwayat.length }}</span>
                </button>
            </div>

            <!-- ── Pasien Aktif ── -->
            <div v-if="activeTab==='aktif'">
                <div v-if="pasienIcu.length===0"
                    class="card-teal text-center py-16" style="color:var(--text-secondary)">
                    <svg class="w-12 h-12 mx-auto mb-3 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>
                    <p class="text-sm">Belum ada pasien di ICU</p>
                </div>

                <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 sm:gap-4">
                    <div v-for="p in pasienIcu" :key="p.id"
                        class="card-teal-hover flex flex-col overflow-hidden"
                        :style="`
                            background: var(--bg-card);
                            border-radius: 14px;
                            border: 1px solid ${gCardBorder(p.jenis_kelamin)};
                            box-shadow: var(--shadow-card);
                        `">

                        <!-- Gender color stripe (top) -->
                        <div class="h-1.5 w-full flex-shrink-0" :style="`background:${gStripe(p.jenis_kelamin)}`"></div>

                        <div class="p-4 flex flex-col flex-1">
                            <!-- Name row -->
                            <div class="flex items-start justify-between mb-3 gap-2">
                                <div class="flex items-center gap-2 min-w-0">
                                    <!-- Gender icon circle -->
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 text-sm font-bold"
                                        :style="`background:${gBadgeBg(p.jenis_kelamin)}; color:${gBadgeColor(p.jenis_kelamin)}`">
                                        {{ p.jenis_kelamin === 'L' ? '♂' : p.jenis_kelamin === 'P' ? '♀' : '?' }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold truncate leading-tight" style="color:var(--text-primary); font-family:'Plus Jakarta Sans',sans-serif">
                                            {{ p.nama_pasien }}
                                        </p>
                                        <!-- Gender label -->
                                        <span class="text-xs font-semibold px-1.5 py-0.5 rounded-full mt-0.5 inline-block"
                                            :style="`background:${gBadgeBg(p.jenis_kelamin)}; color:${gBadgeColor(p.jenis_kelamin)}`">
                                            {{ gLabel(p.jenis_kelamin) }}
                                        </span>
                                    </div>
                                </div>
                                <!-- Di ICU badge -->
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full flex-shrink-0 mt-0.5"
                                    style="background:rgba(45,217,164,0.15); color:#2DD9A4; border:1px solid rgba(45,217,164,0.2)">
                                    Di ICU
                                </span>
                            </div>

                            <!-- Info rows -->
                            <div class="space-y-1.5 mb-4 flex-1 text-xs" style="color:var(--text-secondary)">
                                <div class="flex items-center gap-2">
                                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                                        :style="`color:${gStripe(p.jenis_kelamin)}`">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>
                                    </svg>
                                    <span>Bed: <strong style="color:var(--text-primary)">{{ p.nama_bed }}</strong></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                                        style="color:var(--text-secondary)">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                    </svg>
                                    <span style="color:var(--text-secondary)">{{ p.nama_kelas ?? p.required_bed_type ?? '-' }}</span>
                                </div>
                                <p class="font-mono" style="font-size:10px; color:var(--text-secondary); opacity:0.7">
                                    {{ p.No_Reg }} · {{ p.created_at }}
                                </p>
                            </div>

                            <!-- Pulangkan button — color matches gender -->
                            <button @click="openConfirm(p)"
                                class="w-full flex items-center justify-center gap-2 text-xs font-semibold px-4 py-2 rounded-xl transition-all"
                                style="border:1px solid rgba(224,112,80,0.35); color:#E07050; background:rgba(224,112,80,0.06)"
                                @mouseenter="$el.style.background='rgba(224,112,80,0.14)'"
                                @mouseleave="$el.style.background='rgba(224,112,80,0.06)'">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                                Pulangkan
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Riwayat ── -->
            <div v-if="activeTab==='riwayat'" class="card-teal overflow-hidden">
                <div class="px-5 py-3.5" style="border-bottom:1px solid var(--border-default)">
                    <p class="text-sm font-semibold" style="color:var(--text-primary)">Riwayat Pasien Pulang (20 terakhir)</p>
                </div>
                <div v-if="riwayat.length===0" class="text-center py-10" style="color:var(--text-secondary)">
                    <p class="text-sm">Belum ada riwayat</p>
                </div>
                <!-- Mobile list -->
                <div class="block sm:hidden" style="border-color:var(--border-default)">
                    <div v-for="p in riwayat" :key="p.id"
                        class="px-4 py-3" style="border-bottom:1px solid var(--border-default)">
                        <p class="text-sm font-semibold" style="color:var(--text-primary)">{{ p.nama_pasien }}</p>
                        <p class="text-xs font-mono mt-0.5" style="color:var(--text-secondary)">MR: {{ p.No_MR }} · Reg: {{ p.No_Reg }}</p>
                        <p class="text-xs mt-0.5" style="color:var(--text-secondary); opacity:0.7">Pulang: {{ p.updated_at }}</p>
                    </div>
                </div>
                <!-- Desktop table -->
                <div class="hidden sm:block overflow-x-auto">
                    <table class="w-full text-sm dark-table" style="min-width:500px">
                        <thead>
                            <tr>
                                <th class="text-left px-5">Nama</th>
                                <th class="text-left px-5">No. MR</th>
                                <th class="text-left px-5">No. Registrasi</th>
                                <th class="text-left px-5">Waktu Pulang</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in riwayat" :key="p.id">
                                <td class="px-5 font-semibold">{{ p.nama_pasien }}</td>
                                <td class="px-5 text-xs font-mono" style="color:var(--text-secondary)">{{ p.No_MR }}</td>
                                <td class="px-5 text-xs font-mono" style="color:var(--text-secondary)">{{ p.No_Reg }}</td>
                                <td class="px-5 text-xs" style="color:var(--text-secondary)">{{ p.updated_at }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
