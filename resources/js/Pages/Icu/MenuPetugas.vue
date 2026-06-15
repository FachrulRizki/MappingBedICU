<script setup>
import { ref, computed, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout    from '@/Layouts/AppLayout.vue';
import Icd10Search  from '@/Components/Icd10Search.vue';
import { useAuth }  from '@/composables/useAuth.js';

const { canBuatSpriInternal, isAdmin } = useAuth();

const props = defineProps({
    spriList:    { type: Array,  default: () => [] },
    summary:     { type: Object, default: () => ({}) },
    filters:     { type: Object, default: () => ({}) },
    kamarKosong: { type: Array,  default: () => [] },
    masterKelas: { type: Array,  default: () => [] },
    flash:       { type: Object, default: () => ({}) },
});

// ── Toast ──────────────────────────────────────────────────
const toast = ref(null);
watch(() => props.flash, (f) => {
    if (f?.success) toast.value = { type: 'success', msg: f.success };
    if (f?.error)   toast.value = { type: 'error',   msg: f.error   };
    if (toast.value) setTimeout(() => toast.value = null, 5000);
}, { immediate: true, deep: true });

// ── Filter lokal ───────────────────────────────────────────
const fStatus = ref(props.filters.fStatus ?? '');
const fNama   = ref(props.filters.fNama   ?? '');
const fTgl    = ref(props.filters.fTgl    ?? '');

let st = null;
const applyFilters = () => router.get(route('icu.menu_petugas'), {
    status: fStatus.value, nama: fNama.value, tgl: fTgl.value,
}, { preserveState: true, replace: true });
const onNamaInput = () => { clearTimeout(st); st = setTimeout(applyFilters, 400); };
const resetFilter = () => { fStatus.value=''; fNama.value=''; fTgl.value=''; applyFilters(); };

// ── Styles ─────────────────────────────────────────────────
const SS = {
    pending_admisi: { bg: 'rgba(245,166,35,.15)', color: '#F5A623', dot: '#F5A623' },
    pending_icu:    { bg: 'rgba(224,146,58,.15)', color: '#E0923A', dot: '#E0923A' },
    bed_verified:   { bg: 'rgba(45,217,164,.15)', color: '#2DD9A4', dot: '#2DD9A4' },
    ditolak:        { bg: 'rgba(224,112,80,.15)', color: '#E07050', dot: '#E07050' },
};
const ss = (s) => SS[s] ?? { bg: 'var(--bg-input)', color: 'var(--text-secondary)', dot: '#888' };

// ── Summary cards ──────────────────────────────────────────
const CARDS = computed(() => [
    { key: '',              label: 'Total',         val: props.summary.total        ?? 0, color: '#8EA89E' },
    { key: 'pending_admisi',label: 'Menunggu Admisi',val: props.spriList.filter(s=>s.status==='pending_admisi').length, color: '#F5A623' },
    { key: 'pending_icu',   label: 'Menunggu ICU',  val: props.spriList.filter(s=>s.status==='pending_icu').length,    color: '#E0923A' },
    { key: 'bed_verified',  label: 'Bed Verified',  val: props.summary.bed_verified ?? 0, color: '#2DD9A4' },
    { key: 'ditolak',       label: 'Ditolak',       val: props.summary.ditolak      ?? 0, color: '#E07050' },
]);

// ── Modal ──────────────────────────────────────────────────
const modal = ref({ open: false, type: '', item: null });
const openModal = (type, item = null) => {
    if (type === 'spri') { resetSpriForm(); }
    modal.value = { open: true, type, item };
};
const closeModal = () => { modal.value.open = false; setTimeout(() => modal.value = { open: false, type: '', item: null }, 200); };

// ── Modal: Detail SPRI ────────────────────────────────────
// tidak ada aksi, hanya lihat detail

// ── Form SPRI Baru ─────────────────────────────────────────
const lookupLoading = ref(false);
const lookupResult  = ref(null);
const lookupError   = ref('');
const kunjungans    = ref([]);
const diagnosisExisting = ref('');

const fmSpri = useForm({
    No_MR:      '',
    No_Reg:     '',
    Diagnosis:  '',
    IndikasiRI: '',
    asal_ruang: '',
    Dokter:     '',
    spesialis:  '',
    Keterangan: '',
});

const resetSpriForm = () => {
    fmSpri.reset();
    lookupResult.value      = null;
    lookupError.value       = '';
    kunjungans.value        = [];
    diagnosisExisting.value = '';
};

// Auto-lookup saat No_MR berubah (identik dengan SpriInternal.vue)
watch(() => fmSpri.No_MR, (val) => {
    if (val && val.trim().length >= 3) doLookup(val.trim());
    else {
        lookupResult.value      = null;
        lookupError.value       = '';
        kunjungans.value        = [];
        diagnosisExisting.value = '';
    }
});

const doLookup = async (noMr) => {
    lookupResult.value      = null;
    lookupError.value       = '';
    kunjungans.value        = [];
    fmSpri.No_Reg           = '';
    fmSpri.Dokter           = '';
    fmSpri.asal_ruang       = '';
    diagnosisExisting.value = '';
    if (!noMr || noMr.length < 3) return;

    lookupLoading.value = true;
    try {
        const res  = await fetch(
            route('icu.spri_internal.lookup_pasien') + '?No_MR=' + encodeURIComponent(noMr),
            { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }
        );
        const data = await res.json();
        lookupResult.value = data;
        if (data.found) {
            kunjungans.value = data.kunjungans ?? [];
            if (kunjungans.value.length === 1) {
                const k = kunjungans.value[0];
                fmSpri.No_Reg           = k.No_Reg;
                fmSpri.Dokter           = k.Dokter     ?? '';
                fmSpri.asal_ruang       = k.asal_ruang ?? '';
                diagnosisExisting.value = k.Diagnosis  ?? '';
            }
            if (data.prefill) {
                if (!fmSpri.IndikasiRI) fmSpri.IndikasiRI = data.prefill.IndikasiRI ?? '';
                if (!fmSpri.asal_ruang) fmSpri.asal_ruang = data.prefill.asal_ruang ?? '';
                if (!fmSpri.Dokter)     fmSpri.Dokter     = data.prefill.Dokter     ?? '';
            }
        } else {
            lookupError.value = data.message ?? 'Pasien tidak ditemukan.';
        }
    } catch {
        lookupError.value = 'Gagal menghubungi server.';
    } finally {
        lookupLoading.value = false;
    }
};

const onKunjunganChange = (noReg) => {
    const k = kunjungans.value.find(x => x.No_Reg === noReg);
    if (k) {
        fmSpri.Dokter           = k.Dokter     ?? '';
        fmSpri.asal_ruang       = k.asal_ruang ?? '';
        diagnosisExisting.value = k.Diagnosis  ?? '';
    }
};

const submitSpri = () => {
    fmSpri.post(route('icu.menu_petugas.spri.store'), {
        onSuccess: () => { closeModal(); },
    });
};

const canSubmitSpri = computed(() =>
    fmSpri.No_MR.trim() &&
    fmSpri.No_Reg.trim() &&
    fmSpri.Diagnosis.trim() &&
    fmSpri.IndikasiRI.trim() &&
    lookupResult.value?.found
);

const statusOptions = [
    { value: '',               label: 'Semua Status' },
    { value: 'pending_admisi', label: 'Menunggu Admisi' },
    { value: 'pending_icu',    label: 'Menunggu ICU' },
    { value: 'bed_verified',   label: 'Bed Verified' },
    { value: 'ditolak',        label: 'Ditolak' },
];
</script>

<template>
<AppLayout :flash="flash" page-title="Menu Petugas Internal">

    <!-- Toast -->
    <Transition enter-active-class="transition-all duration-300" enter-from-class="opacity-0 -translate-y-2" leave-to-class="opacity-0 -translate-y-2">
        <div v-if="toast" class="fixed top-4 right-4 z-[100] flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold shadow-xl"
            :style="toast.type==='success' ? 'background:#2DD9A4; color:#0D1A17' : 'background:#E07050; color:#fff'">
            {{ toast.type==='success' ? '✓' : '✕' }} {{ toast.msg }}
        </div>
    </Transition>

    <div class="p-4 sm:p-6 space-y-4" style="font-family:'Plus Jakarta Sans',sans-serif">

        <!-- Header -->
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h1 class="font-bold text-xl" style="color:var(--text-primary)">Permintaan Pindah ICU</h1>
                <p class="text-xs mt-0.5" style="color:var(--text-secondary)">Riwayat SPRI yang saya buat</p>
            </div>
            <button v-if="canBuatSpriInternal || isAdmin" @click="openModal('spri')"
                class="flex items-center gap-2 text-sm font-bold px-4 py-2.5 rounded-xl"
                style="background:#2DD9A4; color:#0D1A17">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                Buat Surat Permintaan Rawat ICU
            </button>
        </div>

        <!-- Summary -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <button v-for="c in CARDS" :key="c.key" @click="fStatus=c.key; applyFilters()"
                class="card-dark p-3.5 flex items-center gap-3 text-left transition-all hover:scale-[1.02]"
                :style="fStatus===c.key ? `border:1.5px solid ${c.color}50` : ''">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0" :style="`background:${c.color}1A`">
                    <span class="text-lg font-bold" :style="`color:${c.color}`">{{ c.val }}</span>
                </div>
                <p class="text-[11px] leading-tight" style="color:var(--text-secondary)">{{ c.label }}</p>
            </button>
        </div>

        <!-- Filter -->
        <div class="card-dark p-3.5">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                <div>
                    <label class="block text-[10px] font-semibold mb-1" style="color:var(--text-secondary)">Status</label>
                    <select v-model="fStatus" @change="applyFilters" class="w-full text-xs px-2.5 py-2 rounded-lg outline-none"
                        style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)">
                        <option v-for="o in statusOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-semibold mb-1" style="color:var(--text-secondary)">Nama / No. MR</label>
                    <input v-model="fNama" @input="onNamaInput" placeholder="Cari..." class="w-full text-xs px-2.5 py-2 rounded-lg outline-none"
                        style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                </div>
                <div>
                    <label class="block text-[10px] font-semibold mb-1" style="color:var(--text-secondary)">Tanggal</label>
                    <div class="flex gap-2">
                        <input v-model="fTgl" @change="applyFilters" type="date" class="flex-1 text-xs px-2.5 py-2 rounded-lg outline-none"
                            style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                        <button v-if="fStatus||fNama||fTgl" @click="resetFilter" class="text-[10px] px-2.5 py-2 rounded-lg"
                            style="background:rgba(224,112,80,.1); color:#E07050; border:1px solid rgba(224,112,80,.2)">✕</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty -->
        <div v-if="!spriList.length" class="card-dark text-center py-14">
            <svg class="w-12 h-12 mx-auto mb-3 opacity-20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2"
                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p class="text-sm font-semibold" style="color:var(--text-secondary)">Belum ada SPRI</p>
            <p class="text-xs mt-1" style="color:var(--text-muted)">Klik "Buat SPRI Baru" untuk memulai</p>
        </div>

        <!-- List SPRI — 1 layer, semua info tampil -->
        <div v-else class="space-y-3">
            <div v-for="item in spriList" :key="item.id"
                class="card-dark overflow-hidden cursor-pointer transition-all hover:shadow-md"
                :style="`border-left:3px solid ${ss(item.status).dot}`"
                @click="openModal('detail', item)">

                <!-- Row utama -->
                <div class="px-4 py-3.5 flex items-center justify-between gap-3 flex-wrap">
                    <!-- Pasien + identitas -->
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 font-bold text-sm"
                            style="background:rgba(45,217,164,.12); color:#2DD9A4">
                            {{ item.nama_pasien?.charAt(0)?.toUpperCase() ?? '?' }}
                        </div>
                        <div class="min-w-0">
                            <p class="font-bold text-sm truncate" style="color:var(--text-primary)">{{ item.nama_pasien }}</p>
                            <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                                <span class="font-mono text-[10px]" style="color:var(--text-secondary)">{{ item.No_MR }}</span>
                                <span class="text-[10px]" style="color:var(--text-muted)">No. Reg: {{ item.No_Reg }}</span>
                            </div>
                        </div>
                    </div>
                    <!-- Status + Waktu -->
                    <div class="flex flex-col items-end gap-1 flex-shrink-0">
                        <span class="text-[10px] font-bold px-2.5 py-1 rounded-full whitespace-nowrap"
                            :style="`background:${ss(item.status).bg}; color:${ss(item.status).color}`">
                            {{ item.status_label }}
                        </span>
                        <span class="text-[10px] font-mono" style="color:var(--text-muted)">{{ item.created_at_fmt }}</span>
                    </div>
                </div>

                <!-- Info detail dalam 1 layer -->
                <div class="px-4 pb-3.5 grid grid-cols-2 sm:grid-cols-4 gap-x-4 gap-y-2" style="border-top:1px solid var(--border-default)">
                    <div class="pt-2.5">
                        <p class="text-[10px]" style="color:var(--text-secondary)">Diagnosa</p>
                        <p class="text-xs font-semibold mt-0.5 truncate" :title="item.Diagnosis" style="color:var(--text-primary)">{{ item.Diagnosis }}</p>
                    </div>
                    <div class="pt-2.5">
                        <p class="text-[10px]" style="color:var(--text-secondary)">Indikasi RI</p>
                        <p class="text-xs font-semibold mt-0.5 truncate" :title="item.IndikasiRI" style="color:var(--text-primary)">{{ item.IndikasiRI }}</p>
                    </div>
                    <div class="pt-2.5">
                        <p class="text-[10px]" style="color:var(--text-secondary)">Asal Ruang</p>
                        <p class="text-xs mt-0.5 truncate" style="color:var(--text-primary)">{{ item.asal_ruang ?? '-' }}</p>
                    </div>
                    <div class="pt-2.5">
                        <p class="text-[10px]" style="color:var(--text-secondary)">Dokter</p>
                        <p class="text-xs mt-0.5 truncate" style="color:var(--text-primary)">{{ item.Dokter ?? '-' }}</p>
                    </div>
                    <div v-if="item.nama_bed" class="pt-1.5 col-span-2">
                        <p class="text-[10px]" style="color:var(--text-secondary)">Bed Dialokasikan</p>
                        <p class="text-xs font-semibold mt-0.5" style="color:#2DD9A4">🏥 {{ item.nama_bed }} <span v-if="item.kebutuhan_bed">({{ item.kebutuhan_bed }})</span></p>
                    </div>
                    <div v-if="item.catatan_admisi" class="pt-1.5 col-span-2">
                        <p class="text-[10px]" style="color:var(--text-secondary)">Catatan Admisi</p>
                        <p class="text-xs mt-0.5" style="color:var(--text-primary)">{{ item.catatan_admisi }}</p>
                    </div>
                    <div v-if="item.alasan_tolak" class="pt-1.5 col-span-4">
                        <p class="text-[10px]" style="color:var(--text-secondary)">Alasan Penolakan</p>
                        <p class="text-xs mt-0.5" style="color:#E07050">{{ item.alasan_tolak }}</p>
                    </div>
                    <!-- Progress tracker -->
                    <div class="pt-2 col-span-2 sm:col-span-4 flex items-center gap-1.5 flex-wrap">
                        <template v-for="(step, i) in [
                            { key:'pending_admisi', label:'Menunggu Admisi' },
                            { key:'pending_icu',    label:'Menunggu ICU' },
                            { key:'bed_verified',   label:'Bed Verified' },
                        ]" :key="step.key">
                            <div class="flex items-center gap-1.5">
                                <div class="w-4 h-4 rounded-full flex items-center justify-center text-[8px] font-bold flex-shrink-0"
                                    :style="item.status === 'ditolak' && i > 0
                                        ? 'background:rgba(224,112,80,.15); color:#E07050'
                                        : ['bed_verified'].includes(item.status) || (item.status === 'pending_icu' && i === 0) || (item.status === 'bed_verified')
                                            ? 'background:rgba(45,217,164,.2); color:#2DD9A4'
                                            : item.status === step.key
                                                ? 'background:rgba(224,146,58,.2); color:#E0923A'
                                                : 'background:var(--bg-input); color:var(--text-muted)'">
                                    {{ i + 1 }}
                                </div>
                                <span class="text-[9px]" style="color:var(--text-muted)">{{ step.label }}</span>
                            </div>
                            <div v-if="i < 2" class="w-6 h-px flex-shrink-0" style="background:var(--border-default)"></div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════ MODALS ════════════════════════════════════════════ -->
    <Transition enter-active-class="transition-all duration-200" enter-from-class="opacity-0" leave-to-class="opacity-0">
        <div v-if="modal.open" class="fixed inset-0 z-50 flex items-center justify-center p-4"
            style="background:rgba(0,0,0,0.6); backdrop-filter:blur(3px)" @click.self="closeModal">

            <!-- Modal: Buat SPRI Baru -->
            <Transition enter-active-class="transition-all duration-200" enter-from-class="opacity-0 scale-95" leave-to-class="opacity-0 scale-95">
                <div v-if="modal.type==='spri'" class="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl" style="background:var(--bg-sidebar); border:1px solid var(--border-default)">
                    <div class="flex items-center justify-between px-5 py-4 sticky top-0 z-10" style="background:var(--bg-sidebar); border-bottom:1px solid var(--border-default)">
                        <div>
                            <p class="font-bold text-sm" style="color:var(--text-primary)">Surat Permintaan Rawat ICU</p>
                            <p class="text-xs mt-0.5" style="color:var(--text-secondary)">Pasien Internal — akan diteruskan ke Admisi</p>
                        </div>
                        <button @click="closeModal" class="p-1.5 rounded-lg" style="background:var(--bg-input); color:var(--text-secondary)">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <!-- Gradient header strip seperti SpriInternal.vue -->
                    <div class="px-5 py-3" style="background:linear-gradient(90deg,#4A90D9,#2DD9A4)">
                        <p class="text-sm font-bold text-white">Surat Permintaan Rawat ICU — Pasien Internal</p>
                    </div>

                    <form @submit.prevent="submitSpri" class="p-5 space-y-5">

                        <!-- 1. Verifikasi Pasien -->
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide mb-3" style="color:var(--text-accent)">1. Verifikasi Pasien</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <!-- No. MR -->
                                <div>
                                    <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">
                                        No. Medical Record <span style="color:#E07050">*</span>
                                    </label>
                                    <div class="relative">
                                        <input v-model="fmSpri.No_MR" required placeholder="Ketik No. MR..."
                                            class="w-full px-3 py-2.5 text-sm rounded-xl outline-none font-mono pr-8"
                                            :style="`border:1px solid ${fmSpri.errors.No_MR || lookupError ? '#E07050' : lookupResult?.found ? '#2DD9A4' : 'var(--border-default)'}; background:var(--bg-surface); color:var(--text-primary)`"/>
                                        <div class="absolute right-2.5 top-1/2 -translate-y-1/2">
                                            <svg v-if="lookupLoading" class="w-4 h-4 animate-spin" style="color:var(--text-secondary)" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                            </svg>
                                            <span v-else-if="lookupResult?.found" style="color:#2DD9A4" class="text-sm">✓</span>
                                            <span v-else-if="lookupError" style="color:#E07050" class="text-sm">✕</span>
                                        </div>
                                    </div>
                                    <p v-if="fmSpri.errors.No_MR" class="text-xs mt-1" style="color:#E07050">{{ fmSpri.errors.No_MR }}</p>
                                    <p v-else-if="lookupError" class="text-xs mt-1" style="color:#E07050">{{ lookupError }}</p>
                                    <p v-else-if="lookupResult?.found" class="text-xs mt-1 font-semibold" style="color:#2DD9A4">✓ {{ lookupResult.nama_pasien }}</p>
                                </div>
                                <!-- No. Reg -->
                                <div>
                                    <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">
                                        No. Registrasi Kunjungan <span style="color:#E07050">*</span>
                                    </label>
                                    <select v-if="kunjungans.length > 1" v-model="fmSpri.No_Reg"
                                        @change="onKunjunganChange(fmSpri.No_Reg)"
                                        class="w-full px-3 py-2.5 text-sm rounded-xl outline-none"
                                        style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)">
                                        <option value="" disabled>-- Pilih Kunjungan --</option>
                                        <option v-for="k in kunjungans" :key="k.No_Reg" :value="k.No_Reg">
                                            {{ k.No_Reg }}{{ k.asal_ruang ? ' — ' + k.asal_ruang : '' }}
                                        </option>
                                    </select>
                                    <input v-else type="text" :value="fmSpri.No_Reg" readonly tabindex="-1"
                                        class="w-full px-3 py-2.5 text-sm rounded-xl outline-none opacity-70 cursor-not-allowed"
                                        :style="`border:1px solid ${fmSpri.errors.No_Reg ? '#E07050' : 'var(--border-default)'}; background:var(--bg-surface); color:var(--text-primary)`"
                                        :placeholder="!lookupResult?.found ? 'Isi No. MR dulu' : (kunjungans.length === 0 ? 'Tidak ada kunjungan aktif' : '')"/>
                                    <p v-if="fmSpri.errors.No_Reg" class="text-xs mt-1" style="color:#E07050">{{ fmSpri.errors.No_Reg }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Data dari Rekam Medis (read-only, auto-fill) -->
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide mb-3" style="color:var(--text-accent)">2. Data dari Rekam Medis</p>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary)">Diagnosis (Rekam Medis)</label>
                                    <input type="text" :value="diagnosisExisting" readonly
                                        class="w-full px-3 py-2.5 text-sm rounded-xl outline-none opacity-70 cursor-not-allowed"
                                        style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"
                                        placeholder="Terisi otomatis"/>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary)">Asal Ruang</label>
                                    <input type="text" :value="fmSpri.asal_ruang" readonly
                                        class="w-full px-3 py-2.5 text-sm rounded-xl outline-none opacity-70 cursor-not-allowed"
                                        style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"
                                        placeholder="Terisi otomatis"/>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary)">Dokter DPJP</label>
                                    <input type="text" :value="fmSpri.Dokter" readonly
                                        class="w-full px-3 py-2.5 text-sm rounded-xl outline-none opacity-70 cursor-not-allowed"
                                        style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"
                                        placeholder="Terisi otomatis"/>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Data Klinis untuk ICU -->
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide mb-3" style="color:var(--text-accent)">3. Data Klinis untuk ICU</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">
                                        Diagnosis ICU <span style="color:#E07050">*</span>
                                    </label>
                                    <Icd10Search v-model="fmSpri.Diagnosis" placeholder="Cari kode / keterangan ICD10"
                                        :required="true" :has-error="!!fmSpri.errors.Diagnosis"/>
                                    <p v-if="fmSpri.errors.Diagnosis" class="text-xs mt-1" style="color:#E07050">{{ fmSpri.errors.Diagnosis }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">
                                        Indikasi Rawat ICU <span style="color:#E07050">*</span>
                                    </label>
                                    <input v-model="fmSpri.IndikasiRI" required placeholder="Alasan klinis butuh ICU"
                                        class="w-full px-3 py-2.5 text-sm rounded-xl outline-none"
                                        :style="`border:1px solid ${fmSpri.errors.IndikasiRI ? '#E07050' : 'var(--border-default)'}; background:var(--bg-surface); color:var(--text-primary)`"/>
                                    <p v-if="fmSpri.errors.IndikasiRI" class="text-xs mt-1" style="color:#E07050">{{ fmSpri.errors.IndikasiRI }}</p>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary)">Keterangan Klinis</label>
                                    <textarea v-model="fmSpri.Keterangan" rows="2"
                                        placeholder="Kondisi terkini, riwayat penyakit, catatan penting untuk ICU"
                                        class="w-full px-3 py-2.5 text-sm rounded-xl outline-none resize-none"
                                        style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-2 pt-1" style="border-top:1px solid var(--border-default)">
                            <button type="submit" :disabled="fmSpri.processing || !canSubmitSpri"
                                class="flex items-center gap-2 text-sm font-bold px-5 py-2.5 rounded-xl disabled:opacity-40"
                                style="background:#2DD9A4; color:#0D1A17">
                                <svg v-if="fmSpri.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                {{ fmSpri.processing ? 'Menyimpan...' : 'Kirim ke Admisi' }}
                            </button>
                            <button type="button" @click="closeModal"
                                class="px-5 py-2.5 text-sm rounded-xl"
                                style="background:var(--bg-main); color:var(--text-secondary); border:1px solid var(--border-default)">Batal</button>
                            <p v-if="!lookupResult?.found && fmSpri.No_MR.trim()" class="text-xs" style="color:#E0923A">
                                ⚠ Menunggu verifikasi No. MR...
                            </p>
                        </div>
                    </form>
                </div>
            </Transition>

            <!-- Modal: Detail SPRI -->
            <Transition enter-active-class="transition-all duration-200" enter-from-class="opacity-0 scale-95" leave-to-class="opacity-0 scale-95">
                <div v-if="modal.type==='detail' && modal.item" class="w-full max-w-md rounded-2xl overflow-hidden" style="background:var(--bg-sidebar); border:1px solid var(--border-default)">
                    <div class="flex items-center justify-between px-5 py-4"
                        :style="`border-bottom:1px solid var(--border-default); border-left:4px solid ${ss(modal.item.status).dot}`">
                        <div>
                            <p class="font-bold text-sm" style="color:var(--text-primary)">{{ modal.item.nama_pasien }}</p>
                            <p class="text-xs mt-0.5 font-mono" style="color:var(--text-secondary)">{{ modal.item.No_MR }} · {{ modal.item.No_Reg }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-bold px-2.5 py-1 rounded-full"
                                :style="`background:${ss(modal.item.status).bg}; color:${ss(modal.item.status).color}`">
                                {{ modal.item.status_label }}
                            </span>
                            <button @click="closeModal" class="p-1.5 rounded-lg" style="background:var(--bg-input); color:var(--text-secondary)">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="p-5 space-y-3 text-xs">
                        <div class="grid grid-cols-2 gap-2.5">
                            <div class="rounded-lg p-2.5" style="background:var(--bg-input)">
                                <p style="color:var(--text-secondary)">Diagnosa</p>
                                <p class="font-semibold mt-0.5" style="color:var(--text-primary)">{{ modal.item.Diagnosis }}</p>
                            </div>
                            <div class="rounded-lg p-2.5" style="background:var(--bg-input)">
                                <p style="color:var(--text-secondary)">Indikasi RI</p>
                                <p class="font-semibold mt-0.5" style="color:var(--text-primary)">{{ modal.item.IndikasiRI }}</p>
                            </div>
                            <div class="rounded-lg p-2.5" style="background:var(--bg-input)">
                                <p style="color:var(--text-secondary)">Asal Ruang</p>
                                <p class="font-semibold mt-0.5" style="color:var(--text-primary)">{{ modal.item.asal_ruang ?? '-' }}</p>
                            </div>
                            <div class="rounded-lg p-2.5" style="background:var(--bg-input)">
                                <p style="color:var(--text-secondary)">Dokter</p>
                                <p class="font-semibold mt-0.5" style="color:var(--text-primary)">{{ modal.item.Dokter ?? '-' }}</p>
                            </div>
                            <div v-if="modal.item.spesialis" class="rounded-lg p-2.5" style="background:var(--bg-input)">
                                <p style="color:var(--text-secondary)">Spesialis</p>
                                <p class="font-semibold mt-0.5" style="color:var(--text-primary)">{{ modal.item.spesialis }}</p>
                            </div>
                            <div v-if="modal.item.nama_bed" class="rounded-lg p-2.5 col-span-2" style="background:rgba(45,217,164,.08); border:1px solid rgba(45,217,164,.2)">
                                <p style="color:#2DD9A4">Bed Dialokasikan</p>
                                <p class="font-bold mt-0.5" style="color:#2DD9A4">🏥 {{ modal.item.nama_bed }} ({{ modal.item.kebutuhan_bed }})</p>
                            </div>
                            <div v-if="modal.item.catatan_admisi" class="rounded-lg p-2.5 col-span-2" style="background:var(--bg-input)">
                                <p style="color:var(--text-secondary)">Catatan Admisi</p>
                                <p class="mt-0.5" style="color:var(--text-primary)">{{ modal.item.catatan_admisi }}</p>
                            </div>
                            <div v-if="modal.item.alasan_tolak" class="rounded-lg p-2.5 col-span-2" style="background:rgba(224,112,80,.08); border:1px solid rgba(224,112,80,.2)">
                                <p style="color:#E07050">Alasan Penolakan</p>
                                <p class="mt-0.5" style="color:#E07050">{{ modal.item.alasan_tolak }}</p>
                            </div>
                        </div>
                        <div class="pt-1 flex items-center justify-between text-[10px]" style="color:var(--text-muted)">
                            <span>Dibuat: {{ modal.item.created_at_fmt }}</span>
                            <span v-if="modal.item.approved_by">Disetujui: {{ modal.item.approved_by }}</span>
                            <span v-if="modal.item.verified_by">Diverifikasi: {{ modal.item.verified_by }}</span>
                        </div>
                    </div>
                </div>
            </Transition>

        </div>
    </Transition>

</AppLayout>
</template>
