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
// Flash ditangani oleh FlashMessage global di AppLayout


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
    pending_admisi: { bg: 'rgba(245,166,35,.15)', color: '#E67E22', dot: '#E67E22' },
    pending_icu:    { bg: 'rgba(230,126,34,.15)', color: '#E67E22', dot: '#E67E22' },
    bed_verified:   { bg: 'rgba(0,168,132,.15)', color: '#00A884', dot: '#00A884' },
    ditolak:        { bg: 'rgba(231,76,60,.15)', color: '#E74C3C', dot: '#E74C3C' },
};
const ss = (s) => SS[s] ?? { bg: 'var(--bg-input)', color: 'var(--text-secondary)', dot: '#888' };

// ── Summary cards ──────────────────────────────────────────
const CARDS = computed(() => [
    { key: '',              label: 'Total',         val: props.summary.total        ?? 0, color: '#5A6B7C' },
    { key: 'pending_admisi',label: 'Menunggu Admisi',val: props.spriList.filter(s=>s.status==='pending_admisi').length, color: '#E67E22' },
    { key: 'pending_icu',   label: 'Menunggu ICU',  val: props.spriList.filter(s=>s.status==='pending_icu').length,    color: '#E67E22' },
    { key: 'bed_verified',  label: 'Bed Verified',  val: props.summary.bed_verified ?? 0, color: '#00A884' },
    { key: 'ditolak',       label: 'Ditolak',       val: props.summary.ditolak      ?? 0, color: '#E74C3C' },
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


    <div class="p-4 sm:p-6 space-y-4" style="font-family:'Inter','Plus Jakarta Sans',sans-serif">

        <!-- ═══ PAGE HEADER ══════════════════════════════════════════════ -->
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl flex items-center justify-center flex-shrink-0" style="background:rgba(52,152,219,.15)">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="#3498DB" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight" style="color:var(--text-primary)">Permintaan Pindah ICU</h1>
                    <p class="text-sm" style="color:var(--text-secondary)">Daftar SPRI yang saya buat</p>
                </div>
            </div>
            <button v-if="canBuatSpriInternal || isAdmin" @click="openModal('spri')"
                class="flex items-center gap-2 font-bold px-5 py-2.5 rounded-xl transition-all duration-150 hover:-translate-y-px"
                style="background:#00A884; color:var(--text-on-accent); font-size:14px; box-shadow:0 4px 14px rgba(0,168,132,0.3)">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                Buat Surat Permintaan
            </button>
        </div>

        <!-- ═══ KPI SUMMARY CARDS ═══════════════════════════════════════ -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
            <button v-for="c in CARDS" :key="c.key" @click="fStatus=c.key; applyFilters()"
                class="relative flex flex-col gap-2 p-5 rounded-2xl text-left transition-all duration-200 hover:-translate-y-1"
                style="background:var(--bg-card); border:1px solid var(--border-default); box-shadow:var(--shadow-card); min-height:100px"
                :style="fStatus===c.key ? `border:2.5px solid ${c.color}; box-shadow:0 0 0 3px ${c.color}18` : ''">
                <span class="absolute left-0 top-6 bottom-6 w-1 rounded-r-full" :style="`background:${c.color}; opacity:${fStatus===c.key?'1':'0.35'}`"></span>
                <span class="text-3xl font-bold tracking-tight" :style="`color:${c.color}`">{{ c.val }}</span>
                <span class="text-xs font-medium leading-tight" style="color:var(--text-secondary)">{{ c.label }}</span>
            </button>
        </div>

        <!-- ═══ FILTER BAR ══════════════════════════════════════════════ -->
        <div class="rounded-2xl p-5 sm:p-6 space-y-4" style="background:var(--bg-surface); border:1px solid var(--border-default); box-shadow:var(--shadow-card)">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Status</label>
                    <select v-model="fStatus" @change="applyFilters" class="w-full rounded-xl outline-none"
                        style="padding:10px 14px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); font-size:13px">
                        <option v-for="o in statusOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                </div>
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Nama / No. MR</label>
                    <input v-model="fNama" @input="onNamaInput" placeholder="Cari pasien..." class="w-full rounded-xl outline-none"
                        style="padding:10px 14px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); font-size:13px"/>
                </div>
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Tanggal</label>
                    <div class="flex gap-2">
                        <input v-model="fTgl" @change="applyFilters" type="date" class="flex-1 rounded-xl outline-none"
                            style="padding:10px 14px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); font-size:13px"/>
                        <button v-if="fStatus||fNama||fTgl" @click="resetFilter"
                            class="px-3.5 rounded-xl font-semibold text-xs flex items-center"
                            style="background:rgba(231,76,60,.1); color:#E74C3C; border:1.5px solid rgba(231,76,60,.25)">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

        <!-- ═══ EMPTY STATE ═════════════════════════════════════════════ -->
        <div v-if="!spriList.length" class="rounded-2xl flex flex-col items-center justify-center py-20 px-8 text-center"
            style="background:var(--bg-surface); border:1px solid var(--border-default); box-shadow:var(--shadow-card)">
            <div class="w-20 h-20 rounded-3xl flex items-center justify-center mb-5"
                style="background:rgba(52,152,219,.1); border:1.5px dashed rgba(52,152,219,.3)">
                <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="#3498DB" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <p class="text-base font-bold mb-1.5" style="color:var(--text-primary)">Belum Ada SPRI</p>
            <p class="text-sm max-w-xs" style="color:var(--text-muted)">Buat Surat Permintaan Rawat ICU baru dengan klik tombol di atas.</p>
        </div>

        <!-- ═══ LIST SPRI ════════════════════════════════════════════════ -->
        <div v-else class="space-y-4">
            <div v-for="item in spriList" :key="item.id"
                class="rounded-2xl overflow-hidden cursor-pointer transition-all duration-200"
                style="background:var(--bg-surface); border:1px solid var(--border-default); box-shadow:var(--shadow-card)"
                :style="`border-left:4px solid ${ss(item.status).dot}`"
                @click="openModal('detail', item)"
                onmouseenter="this.style.boxShadow='var(--shadow-card-hover)'; this.style.transform='translateY(-1px)'"
                onmouseleave="this.style.boxShadow='var(--shadow-card)'; this.style.transform=''">
                <!-- Top: pasien + status -->
                <div class="px-5 py-4 flex items-center justify-between gap-4 flex-wrap">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 font-bold text-sm"
                            style="background:rgba(0,168,132,.12); color:#00A884">
                            {{ item.nama_pasien?.charAt(0)?.toUpperCase() ?? '?' }}
                        </div>
                        <div class="min-w-0">
                            <p class="font-bold truncate" style="color:var(--text-primary); font-size:14px">{{ item.nama_pasien }}</p>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span class="font-mono text-xs" style="color:var(--text-muted)">{{ item.No_MR }}</span>
                                <span class="text-xs" style="color:var(--text-muted)">· {{ item.No_Reg }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 flex-shrink-0">
                        <span class="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-full whitespace-nowrap"
                            :style="`background:${ss(item.status).bg}; color:${ss(item.status).color}`">
                            <span class="w-1.5 h-1.5 rounded-full" :style="`background:${ss(item.status).dot}`"></span>
                            {{ item.status_label }}
                        </span>
                        <span class="font-mono text-xs" style="color:var(--text-muted)">{{ item.created_at_fmt }}</span>
                    </div>
                </div>
                <!-- Detail -->
                <div class="px-5 pb-4 grid grid-cols-2 sm:grid-cols-4 gap-4" style="border-top:1px solid var(--border-default); padding-top:14px">
                    <div><p class="text-xs mb-0.5" style="color:var(--text-muted)">Diagnosa</p><p class="text-sm font-semibold truncate" style="color:var(--text-primary)">{{ item.Diagnosis }}</p></div>
                    <div><p class="text-xs mb-0.5" style="color:var(--text-muted)">Indikasi RI</p><p class="text-sm font-semibold truncate" style="color:var(--text-primary)">{{ item.IndikasiRI }}</p></div>
                    <div><p class="text-xs mb-0.5" style="color:var(--text-muted)">Asal Ruang</p><p class="text-sm truncate" style="color:var(--text-secondary)">{{ item.asal_ruang ?? '—' }}</p></div>
                    <div><p class="text-xs mb-0.5" style="color:var(--text-muted)">Dokter</p><p class="text-sm truncate" style="color:var(--text-secondary)">{{ item.Dokter ?? '—' }}</p></div>
                    <div v-if="item.nama_bed" class="col-span-2">
                        <p class="text-xs mb-0.5" style="color:var(--text-muted)">Bed Dialokasikan</p>
                        <p class="text-sm font-bold" style="color:#00A884">🏥 {{ item.nama_bed }}<span v-if="item.kebutuhan_bed" class="font-normal text-xs ml-1" style="color:var(--text-muted)">({{ item.kebutuhan_bed }})</span></p>
                    </div>
                    <div v-if="item.alasan_tolak" class="col-span-4 rounded-xl p-3" style="background:rgba(231,76,60,.06); border:1px solid rgba(231,76,60,.2)">
                        <p class="text-xs font-bold mb-1" style="color:#E74C3C">Alasan Penolakan</p>
                        <p class="text-sm" style="color:var(--text-primary)">{{ item.alasan_tolak }}</p>
                    </div>
                    <!-- Progress tracker -->
                    <div class="col-span-2 sm:col-span-4 flex items-center gap-3 flex-wrap pt-1">
                        <template v-for="(step, i) in [{key:'pending_admisi',label:'Menunggu Admisi'},{key:'pending_icu',label:'Menunggu ICU'},{key:'bed_verified',label:'Bed Verified'}]" :key="step.key">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0"
                                    :style="item.status==='ditolak'&&i>0 ? 'background:rgba(231,76,60,.15);color:#E74C3C'
                                        : item.status==='bed_verified'||(item.status==='pending_icu'&&i===0) ? 'background:rgba(0,168,132,.2);color:#00A884'
                                        : item.status===step.key ? 'background:rgba(230,126,34,.2);color:#E67E22'
                                        : 'background:var(--bg-input);color:var(--text-muted)'">
                                    {{ item.status==='bed_verified'||(item.status==='pending_icu'&&i===0) ? '✓' : (i+1) }}
                                </div>
                                <span class="text-xs" style="color:var(--text-muted)">{{ step.label }}</span>
                            </div>
                            <div v-if="i<2" class="w-8 h-px" style="background:var(--border-default)"></div>
                        </template>
                    </div>
                </div>
            </div>

    </div>

    <!-- ═══════════════ MODALS ════════════════════════════════════════════ -->
    <Transition enter-active-class="transition-all duration-300 ease-out" enter-from-class="opacity-0" leave-to-class="opacity-0">
        <div v-if="modal.open" class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
            style="background:rgba(0,0,0,0.65); backdrop-filter:blur(8px)" @click.self="closeModal">

            <!-- ── Modal: Buat SPRI ──────────────────────────────────── -->
            <Transition enter-active-class="transition-all duration-200" enter-from-class="opacity-0 scale-95" leave-to-class="opacity-0 scale-95">
                <div v-if="modal.type==='spri'" class="w-full max-w-2xl max-h-[92vh] overflow-y-auto"
                    style="background:var(--bg-surface); border:1px solid var(--border-default); border-radius:20px; box-shadow:0 25px 60px rgba(0,0,0,0.2)">
                    <!-- Header sticky -->
                    <div class="flex items-center justify-between px-6 py-5 sticky top-0 z-10"
                        style="background:var(--bg-surface); border-bottom:1px solid var(--border-default)">
                        <div>
                            <h2 class="text-base font-bold" style="color:var(--text-primary)">Surat Permintaan Rawat ICU</h2>
                            <p class="text-xs mt-0.5" style="color:var(--text-secondary)">Pasien Internal — akan diteruskan ke Admisi</p>
                        </div>
                        <button @click="closeModal" class="w-8 h-8 rounded-xl flex items-center justify-center"
                            style="background:var(--bg-input); color:var(--text-secondary)">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <!-- Gradient strip -->
                    <div class="px-6 py-3" style="background:linear-gradient(90deg,#3498DB,#00A884)">
                        <p class="text-sm font-bold text-white">SPRI — Pasien Internal</p>
                    </div>
                    <form @submit.prevent="submitSpri" class="p-6 space-y-6">
                        <!-- 1. Verifikasi Pasien -->
                        <div class="space-y-3">
                            <p class="text-xs font-bold uppercase tracking-widest" style="color:var(--text-accent)">1. Verifikasi Pasien</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="space-y-1.5">
                                    <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">No. Medical Record <span style="color:#E74C3C">*</span></label>
                                    <div class="relative">
                                        <input v-model="fmSpri.No_MR" required placeholder="Ketik No. MR..."
                                            class="w-full rounded-xl outline-none font-mono pr-10"
                                            style="padding:10px 14px; font-size:13px"
                                            :style="`border:1.5px solid ${fmSpri.errors.No_MR||lookupError?'#E74C3C':lookupResult?.found?'#00A884':'var(--border-default)'}; background:var(--bg-input); color:var(--text-primary)`"/>
                                        <div class="absolute right-3 top-1/2 -translate-y-1/2">
                                            <svg v-if="lookupLoading" class="w-4 h-4 animate-spin" style="color:var(--text-muted)" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                            <span v-else-if="lookupResult?.found" class="text-sm font-bold" style="color:#00A884">✓</span>
                                            <span v-else-if="lookupError" class="text-sm font-bold" style="color:#E74C3C">✕</span>
                                        </div>
                                    </div>
                                    <p v-if="lookupError" class="text-xs" style="color:#E74C3C">{{ lookupError }}</p>
                                    <p v-else-if="lookupResult?.found" class="text-xs font-semibold" style="color:#00A884">✓ {{ lookupResult.nama_pasien }}</p>
                                </div>
                                <div class="space-y-1.5">
                                    <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">No. Registrasi Kunjungan <span style="color:#E74C3C">*</span></label>
                                    <select v-if="kunjungans.length > 1" v-model="fmSpri.No_Reg" @change="onKunjunganChange(fmSpri.No_Reg)"
                                        class="w-full rounded-xl outline-none" style="padding:10px 14px; font-size:13px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary)">
                                        <option value="" disabled>— Pilih Kunjungan —</option>
                                        <option v-for="k in kunjungans" :key="k.No_Reg" :value="k.No_Reg">{{ k.No_Reg }}{{ k.asal_ruang ? ' — '+k.asal_ruang : '' }}</option>
                                    </select>
                                    <input v-else readonly :value="fmSpri.No_Reg" :placeholder="!lookupResult?.found?'Isi No. MR dulu':'Tidak ada kunjungan aktif'"
                                        class="w-full rounded-xl outline-none cursor-not-allowed opacity-60"
                                        style="padding:10px 14px; font-size:13px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary)"/>
                                </div>
                            </div>
                        </div>
                        <!-- 2. Data dari Rekam Medis -->
                        <div class="space-y-3">
                            <p class="text-xs font-bold uppercase tracking-widest" style="color:var(--text-accent)">2. Data dari Rekam Medis</p>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                <div v-for="(lbl,field) in {'diagnosisExisting':'Diagnosa (RM)','asal_ruang':'Asal Ruang','Dokter':'Dokter DPJP'}" :key="field" class="space-y-1.5">
                                    <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">{{ lbl }}</label>
                                    <input readonly :value="field==='diagnosisExisting'?diagnosisExisting:fmSpri[field]" placeholder="Terisi otomatis"
                                        class="w-full rounded-xl outline-none cursor-not-allowed opacity-60"
                                        style="padding:10px 14px; font-size:13px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary)"/>
                                </div>
                            </div>
                        </div>
                        <!-- 3. Data Klinis ICU -->
                        <div class="space-y-3">
                            <p class="text-xs font-bold uppercase tracking-widest" style="color:var(--text-accent)">3. Data Klinis untuk ICU</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="space-y-1.5">
                                    <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Diagnosis ICU <span style="color:#E74C3C">*</span></label>
                                    <Icd10Search v-model="fmSpri.Diagnosis" placeholder="Cari kode / keterangan ICD10" :required="true" :has-error="!!fmSpri.errors.Diagnosis"/>
                                    <p v-if="fmSpri.errors.Diagnosis" class="text-xs" style="color:#E74C3C">{{ fmSpri.errors.Diagnosis }}</p>
                                </div>
                                <div class="space-y-1.5">
                                    <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Indikasi Rawat ICU <span style="color:#E74C3C">*</span></label>
                                    <input v-model="fmSpri.IndikasiRI" required placeholder="Alasan klinis butuh ICU"
                                        class="w-full rounded-xl outline-none"
                                        style="padding:10px 14px; font-size:13px"
                                        :style="`border:1.5px solid ${fmSpri.errors.IndikasiRI?'#E74C3C':'var(--border-default)'}; background:var(--bg-input); color:var(--text-primary)`"/>
                                </div>
                                <div class="sm:col-span-2 space-y-1.5">
                                    <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Keterangan Klinis</label>
                                    <textarea v-model="fmSpri.Keterangan" rows="3" placeholder="Kondisi terkini, riwayat, catatan penting untuk ICU..."
                                        class="w-full rounded-xl outline-none resize-none"
                                        style="padding:10px 14px; font-size:13px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); line-height:1.6"/>
                                </div>
                            </div>
                        </div>
                        <!-- Actions -->
                        <div class="flex items-center gap-3 pt-2" style="border-top:1px solid var(--border-default)">
                            <button type="submit" :disabled="fmSpri.processing || !canSubmitSpri"
                                class="flex items-center gap-2 font-bold px-6 py-3 rounded-xl transition-all duration-150 disabled:opacity-40 hover:-translate-y-px"
                                style="background:#00A884; color:var(--text-on-accent); font-size:14px">
                                <svg v-if="fmSpri.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                {{ fmSpri.processing ? 'Menyimpan...' : 'Kirim ke Admisi' }}
                            </button>
                            <button type="button" @click="closeModal" class="px-6 py-3 rounded-xl font-medium"
                                style="background:var(--bg-input); color:var(--text-secondary); border:1.5px solid var(--border-default); font-size:14px">Batal</button>
                            <p v-if="!lookupResult?.found && fmSpri.No_MR.trim()" class="text-xs flex items-center gap-1.5" style="color:#E67E22">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                Menunggu verifikasi No. MR
                            </p>
                        </div>
                    </form>
                </div>
            </Transition>

            <!-- ── Modal: Detail SPRI ─────────────────────────────────── -->
            <Transition enter-active-class="transition-all duration-200" enter-from-class="opacity-0 scale-95" leave-to-class="opacity-0 scale-95">
                <div v-if="modal.type==='detail' && modal.item" class="w-full max-w-lg"
                    style="background:var(--bg-surface); border:1px solid var(--border-default); border-radius:20px; box-shadow:0 25px 60px rgba(0,0,0,0.2); overflow:hidden">
                    <!-- Header -->
                    <div class="flex items-start justify-between px-6 py-5"
                        style="border-bottom:1px solid var(--border-default)"
                        :style="`border-left:4px solid ${ss(modal.item.status).dot}`">
                        <div class="flex-1 min-w-0 pr-3">
                            <div class="flex items-center gap-2 flex-wrap mb-2">
                                <span class="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-full"
                                    :style="`background:${ss(modal.item.status).bg}; color:${ss(modal.item.status).color}`">
                                    <span class="w-1.5 h-1.5 rounded-full" :style="`background:${ss(modal.item.status).dot}`"></span>
                                    {{ modal.item.status_label }}
                                </span>
                            </div>
                            <h2 class="text-base font-bold truncate" style="color:var(--text-primary)">{{ modal.item.nama_pasien }}</h2>
                            <p class="text-xs font-mono mt-0.5" style="color:var(--text-muted)">{{ modal.item.No_MR }} · {{ modal.item.No_Reg }}</p>
                        </div>
                        <button @click="closeModal" class="w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0"
                            style="background:var(--bg-input); color:var(--text-secondary)">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <!-- Body -->
                    <div class="p-6 space-y-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-0.5">
                                <p class="text-xs" style="color:var(--text-muted)">Diagnosa</p>
                                <p class="text-sm font-semibold" style="color:var(--text-primary)">{{ modal.item.Diagnosis }}</p>
                            </div>
                            <div class="space-y-0.5">
                                <p class="text-xs" style="color:var(--text-muted)">Indikasi RI</p>
                                <p class="text-sm font-semibold" style="color:var(--text-primary)">{{ modal.item.IndikasiRI }}</p>
                            </div>
                            <div class="space-y-0.5">
                                <p class="text-xs" style="color:var(--text-muted)">Asal Ruang</p>
                                <p class="text-sm" style="color:var(--text-secondary)">{{ modal.item.asal_ruang ?? '—' }}</p>
                            </div>
                            <div class="space-y-0.5">
                                <p class="text-xs" style="color:var(--text-muted)">Dokter DPJP</p>
                                <p class="text-sm" style="color:var(--text-secondary)">{{ modal.item.Dokter ?? '—' }}</p>
                            </div>
                            <div v-if="modal.item.nama_bed" class="col-span-2 rounded-xl p-3.5"
                                style="background:rgba(0,168,132,.08); border:1.5px solid rgba(0,168,132,.2)">
                                <p class="text-xs mb-1" style="color:#00A884">Bed Dialokasikan</p>
                                <p class="text-sm font-bold" style="color:#00A884">🏥 {{ modal.item.nama_bed }}<span v-if="modal.item.kebutuhan_bed" class="font-normal text-xs ml-1" style="color:var(--text-muted)">({{ modal.item.kebutuhan_bed }})</span></p>
                            </div>
                            <div v-if="modal.item.catatan_admisi" class="col-span-2 rounded-xl p-3.5" style="background:var(--bg-input)">
                                <p class="text-xs mb-1" style="color:var(--text-muted)">Catatan Admisi</p>
                                <p class="text-sm" style="color:var(--text-primary)">{{ modal.item.catatan_admisi }}</p>
                            </div>
                            <div v-if="modal.item.alasan_tolak" class="col-span-2 rounded-xl p-3.5"
                                style="background:rgba(231,76,60,.06); border:1.5px solid rgba(231,76,60,.2)">
                                <p class="text-xs font-bold mb-1" style="color:#E74C3C">Alasan Penolakan</p>
                                <p class="text-sm" style="color:var(--text-primary)">{{ modal.item.alasan_tolak }}</p>
                            </div>
                        </div>
                        <div class="flex items-center justify-between pt-1" style="color:var(--text-muted); font-size:11px">
                            <span>Dibuat: {{ modal.item.created_at_fmt }}</span>
                            <span v-if="modal.item.approved_by">Disetujui: {{ modal.item.approved_by }}</span>
                        </div>
                    </div>
                </div>
            </Transition>

        </div>
    </Transition>

</AppLayout>
</template>
