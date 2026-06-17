<script setup>
import { ref, computed, watch } from 'vue';
import { router, useForm }  from '@inertiajs/vue3';
import AppLayout  from '@/Layouts/AppLayout.vue';
import Icd10Search from '@/Components/Icd10Search.vue';
import { useAuth } from '@/composables/useAuth.js';

const { canBuatBookingExternal, canVerifikasiAdmisiExt, canApproveAdmisi, isAdmin } = useAuth();

const props = defineProps({
    antrian:     { type: Array,  default: () => [] },
    summary:     { type: Object, default: () => ({}) },
    filters:     { type: Object, default: () => ({}) },
    caraBayar:   { type: Array,  default: () => [] },
    kamarKosong: { type: Array,  default: () => [] },
    masterKelas: { type: Array,  default: () => [] },
    flash:       { type: Object, default: () => ({}) },
});

// Flash ditangani oleh FlashMessage global di AppLayout — tidak perlu toast lokal

// ── Filters ────────────────────────────────────────────────
const fStatus = ref(props.filters.filterStatus ?? '');
const fJenis  = ref(props.filters.filterJenis  ?? '');
const fNama   = ref(props.filters.filterNama   ?? '');
const fTgl    = ref(props.filters.filterTgl    ?? '');
const sortBy  = ref(props.filters.sortBy       ?? 'created_at');
const sortDir = ref(props.filters.sortDir      ?? 'asc');

let searchTimer = null;
const applyFilters = () => router.get(route('icu.menu_admisi'), {
    status: fStatus.value, jenis: fJenis.value,
    nama: fNama.value, tgl: fTgl.value,
    sort: sortBy.value, dir: sortDir.value,
}, { preserveState: true, replace: true });

const onNamaInput = () => { clearTimeout(searchTimer); searchTimer = setTimeout(applyFilters, 400); };
const toggleSort  = (col) => {
    sortDir.value = sortBy.value === col ? (sortDir.value === 'asc' ? 'desc' : 'asc') : 'asc';
    sortBy.value  = col;
    applyFilters();
};
const resetFilter = () => { fStatus.value=''; fJenis.value=''; fNama.value=''; fTgl.value=''; applyFilters(); };
const sortIcon = (col) => sortBy.value !== col ? '↕' : sortDir.value === 'asc' ? '↑' : '↓';

// ── Style helpers ──────────────────────────────────────────
const SS = {
    pending_icu:     { bg: 'rgba(230,126,34,.15)',  color: '#E67E22', dot: '#E67E22' },
    pending_admisi:  { bg: 'rgba(245,166,35,.15)',  color: '#E67E22', dot: '#E67E22' },
    bed_confirmed:   { bg: 'rgba(52,152,219,.15)',  color: '#3498DB', dot: '#3498DB' },
    bed_verified:    { bg: 'rgba(0,168,132,.15)',  color: '#00A884', dot: '#00A884' },
    admisi_verified: { bg: 'rgba(0,168,132,.15)',  color: '#00A884', dot: '#00A884' },
    ditolak:         { bg: 'rgba(231,76,60,.15)',  color: '#E74C3C', dot: '#E74C3C' },
};
const ss = (s) => SS[s] ?? { bg: 'var(--bg-input)', color: 'var(--text-secondary)', dot: '#888' };

const SRC = {
    external: { bg: 'rgba(52,152,219,.12)', color: '#3498DB' },
    internal: { bg: 'rgba(90,107,124,.12)', color: '#5A6B7C' },
};
const jaminanLabel = (k) => props.caraBayar.find(c => c.kode === k)?.nama ?? k ?? '-';
const gIcon  = (g) => g === 'L' ? '♂' : g === 'P' ? '♀' : '·';
const gColor = (g) => g === 'L' ? '#3498DB' : g === 'P' ? '#8E44AD' : 'var(--text-secondary)';

// ── Summary cards ──────────────────────────────────────────
const CARDS = computed(() => [
    { key:'',               label:'Total',           val: props.summary.total        ?? 0, color:'#5A6B7C' },
    { key:'pending_admisi', label:'Menunggu Admisi',  val: props.antrian.filter(a=>a.status==='pending_admisi').length, color:'#E67E22' },
    { key:'bed_confirmed',  label:'Perlu Verifikasi', val: props.antrian.filter(a=>a.status==='bed_confirmed').length,  color:'#3498DB' },
    { key:'admisi_verified',label:'Selesai',          val: props.summary.verified      ?? 0, color:'#00A884' },
    { key:'ditolak',        label:'Ditolak',          val: props.summary.ditolak       ?? 0, color:'#E74C3C' },
]);

// ── Aksi yang tersedia per item ────────────────────────────
const canAct = computed(() => canVerifikasiAdmisiExt.value || canApproveAdmisi.value || isAdmin.value);

const actionsOf = (item) => {
    if (!canAct.value) return [];
    const acts = [];
    if (item.sumber === 'internal' && item.status === 'pending_admisi') {
        acts.push({ id:'approve', label:'Setujui SPRI', color:'#00A884', bg:'rgba(0,168,132,.12)', border:'rgba(0,168,132,.3)' });
        acts.push({ id:'tolak',   label:'Tolak SPRI',   color:'#E74C3C', bg:'rgba(231,76,60,.08)', border:'rgba(231,76,60,.25)' });
    }
    if (item.sumber === 'external' && item.status === 'bed_confirmed') {
        acts.push({ id:'verifikasi', label:'Verifikasi Pasien', color:'#00A884', bg:'rgba(0,168,132,.12)', border:'rgba(0,168,132,.3)' });
    }
    return acts;
};

// ── Modal state ────────────────────────────────────────────
const modal = ref({ open: false, type: '', item: null });
const openModal = (type, item = null) => { modal.value = { open: true, type, item }; };
const closeModal = () => {
    modal.value.open = false;
    setTimeout(() => {
        modal.value = { open: false, type: '', item: null };
        // Reset semua state lookup saat modal ditutup
        verifLookupResult.value  = null;
        verifLookupError.value   = '';
        verifKunjungans.value    = [];
        fmVerif.reset();
        fmApprove.reset();
        fmTolak.reset();
    }, 200);
};

// ── Form: Booking Baru ─────────────────────────────────────
const fmBooking = useForm({
    nama_pasien: '', jenis_kelamin: '', no_identitas: '',
    asal_rujukan: '', no_telp_keluarga: '',
    diagnosa: '', rencana_tindakan: '',
    jaminan: '', catatan_jaminan: '', keterangan: '',
});
const submitBooking = () => fmBooking.post(route('icu.menu_admisi.booking.store'), {
    onSuccess: () => { closeModal(); fmBooking.reset(); },
});

// ── Form: Approve SPRI ─────────────────────────────────────
const fmApprove = useForm({ catatan_admisi: '' });
const submitApprove = () => {
    if (!modal.value.item) return;
    fmApprove.post(route('icu.menu_admisi.int.approve', modal.value.item.id), {
        onSuccess: closeModal,
    });
};

// ── Form: Tolak SPRI ───────────────────────────────────────
const fmTolak = useForm({ alasan_tolak: '' });
const submitTolak = () => {
    if (!modal.value.item) return;
    fmTolak.post(route('icu.menu_admisi.int.tolak', modal.value.item.id), {
        onSuccess: closeModal,
    });
};

// ── Form: Verifikasi Pasien (Ext bed_confirmed) ────────────
const fmVerif = useForm({ No_MR: '', No_Reg: '' });
const verifLookupLoading = ref(false);
const verifLookupResult  = ref(null);
const verifLookupError   = ref('');
const verifKunjungans    = ref([]);

const openVerifModal = (item) => {
    fmVerif.No_MR  = item.No_MR ?? '';
    fmVerif.No_Reg = item.No_Reg ?? '';
    verifLookupResult.value  = null;
    verifLookupError.value   = '';
    verifKunjungans.value    = [];
    // Auto-lookup jika sudah ada No_MR
    openModal('verifikasi', item);
    if (fmVerif.No_MR) doVerifLookup();
};

const doVerifLookup = async () => {
    const noMr = fmVerif.No_MR.trim();
    if (noMr.length < 3) return;
    verifLookupLoading.value = true;
    verifLookupResult.value  = null;
    verifLookupError.value   = '';
    verifKunjungans.value    = [];

    try {
        const res  = await fetch(
            route('icu.booking_external.lookup_pasien') + '?No_MR=' + encodeURIComponent(noMr),
            { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }
        );
        const data = await res.json();
        if (!data.found) {
            verifLookupError.value = data.message ?? 'Pasien tidak ditemukan.';
        } else {
            verifLookupResult.value = data;
            verifKunjungans.value   = data.kunjungans ?? [];
            if (verifKunjungans.value.length === 1 && !fmVerif.No_Reg) {
                fmVerif.No_Reg = verifKunjungans.value[0].No_Reg;
            }
        }
    } catch {
        verifLookupError.value = 'Gagal menghubungi server.';
    } finally {
        verifLookupLoading.value = false;
    }
};

const submitVerif = () => {
    if (!modal.value.item) return;
    fmVerif.post(route('icu.menu_admisi.ext.verifikasi', modal.value.item.id), {
        onSuccess: closeModal,
    });
};

const statusOptions = [
    { value:'', label:'Semua Status' },
    { value:'pending_admisi',  label:'Menunggu Admisi' },
    { value:'pending_icu',     label:'Menunggu ICU' },
    { value:'bed_confirmed',   label:'Bed Dikonfirmasi' },
    { value:'bed_verified',    label:'Bed Terverifikasi' },
    { value:'admisi_verified', label:'Terverifikasi' },
    { value:'ditolak',         label:'Ditolak' },
];
const jenisOptions = [
    { value:'', label:'Semua Jenis' },
    { value:'external', label:'Booking Eksternal' },
    { value:'internal', label:'SPRI Internal' },
];
</script>

<template>
<AppLayout :flash="flash" page-title="Menu Admisi">

    <div class="p-6 sm:p-8 space-y-6" style="font-family:'Inter','Plus Jakarta Sans',sans-serif">

        <!-- ═══ PAGE HEADER ══════════════════════════════════════════════ -->
        <div class="flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl flex items-center justify-center flex-shrink-0" style="background:rgba(0,168,132,.15)">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="#00A884" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight" style="color:var(--text-primary)">Menu Admisi</h1>
                    <p class="text-sm" style="color:var(--text-secondary)">Booking Eksternal &amp; SPRI Internal</p>
                </div>
            </div>
            <button v-if="canBuatBookingExternal" @click="openModal('booking')"
                class="flex items-center gap-2 font-bold px-5 py-2.5 rounded-xl transition-all duration-150 hover:-translate-y-px"
                style="background:#00A884; color:var(--text-on-accent); font-size:14px; box-shadow:0 4px 14px rgba(0,168,132,0.3)">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                    Booking Baru
            </button>
        </div>

        <!-- ═══ KPI SUMMARY CARDS ═══════════════════════════════════════ -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
            <button v-for="c in CARDS" :key="c.key"
                @click="fStatus=c.key; applyFilters()"
                class="relative flex flex-col gap-2 p-5 rounded-2xl text-left transition-all duration-200 hover:-translate-y-1"
                style="background:var(--bg-card); border:1px solid var(--border-default); box-shadow:var(--shadow-card); min-height:100px"
                :style="fStatus===c.key ? `border:2.5px solid ${c.color}; box-shadow:0 0 0 3px ${c.color}18` : ''">
                <span class="absolute left-0 top-6 bottom-6 w-1 rounded-r-full"
                    :style="`background:${c.color}; opacity:${fStatus===c.key?'1':'0.35'}`"></span>
                <span class="text-3xl font-bold tracking-tight" :style="`color:${c.color}`">{{ c.val }}</span>
                <span class="text-xs font-medium leading-tight" style="color:var(--text-secondary)">{{ c.label }}</span>
            </button>
        </div>

        <!-- ═══ BREAKDOWN SUMBER ════════════════════════════════════════ -->
        <div class="flex items-center gap-3 flex-wrap">
            <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium"
                style="background:rgba(52,152,219,.1); border:1px solid rgba(52,152,219,.2)">
                <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:#3498DB"></span>
                <span style="color:var(--text-secondary)">Booking Eksternal</span>
                <strong class="font-bold" style="color:#3498DB">{{ summary.by_sumber?.external ?? 0 }}</strong>
            </span>
            <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium"
                style="background:rgba(90,107,124,.1); border:1px solid rgba(90,107,124,.2)">
                <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:#5A6B7C"></span>
                <span style="color:var(--text-secondary)">SPRI Internal</span>
                <strong class="font-bold" style="color:#5A6B7C">{{ summary.by_sumber?.internal ?? 0 }}</strong>
            </span>
        </div>

        <!-- ═══ FILTER BAR ══════════════════════════════════════════════ -->
        <div class="rounded-2xl p-5 sm:p-6 space-y-4" style="background:var(--bg-surface); border:1px solid var(--border-default); box-shadow:var(--shadow-card)">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Status</label>
                    <select v-model="fStatus" @change="applyFilters" class="w-full rounded-xl outline-none"
                        style="padding:10px 14px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); font-size:13px">
                        <option v-for="o in statusOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                </div>
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Jenis</label>
                    <select v-model="fJenis" @change="applyFilters" class="w-full rounded-xl outline-none"
                        style="padding:10px 14px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); font-size:13px">
                        <option v-for="o in jenisOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                </div>
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Nama / No. MR</label>
                    <input v-model="fNama" @input="onNamaInput" placeholder="Cari pasien..." class="w-full rounded-xl outline-none"
                        style="padding:10px 14px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); font-size:13px"/>
                </div>
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Tanggal</label>
                    <input v-model="fTgl" @change="applyFilters" type="date" class="w-full rounded-xl outline-none"
                        style="padding:10px 14px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); font-size:13px"/>
                </div>
            </div>
            <div class="flex items-center gap-2.5 flex-wrap">
                <span class="text-xs font-semibold" style="color:var(--text-muted)">Urutkan:</span>
                <button v-for="col in [{key:'created_at',label:'Waktu'},{key:'nama_pasien',label:'Nama'},{key:'status',label:'Status'}]"
                    :key="col.key" @click="toggleSort(col.key)"
                    class="text-xs font-semibold px-3.5 py-2 rounded-xl transition-all duration-150"
                    :style="sortBy===col.key
                        ? 'background:rgba(0,168,132,.15); color:#00A884; border:1.5px solid rgba(0,168,132,.35)'
                        : 'background:var(--bg-input); color:var(--text-secondary); border:1.5px solid var(--border-default)'">
                    {{ col.label }} {{ sortIcon(col.key) }}
                </button>
                <button v-if="fStatus||fJenis||fNama||fTgl" @click="resetFilter"
                    class="ml-auto text-xs font-semibold px-3.5 py-2 rounded-xl flex items-center gap-1.5"
                    style="background:rgba(231,76,60,.1); color:#E74C3C; border:1.5px solid rgba(231,76,60,.25)">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Reset Filter
                </button>
            </div>
        </div>


        <!-- Empty state -->
        <div v-if="!antrian.length" class="card-dark text-center py-16">
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-4" style="background:var(--bg-input)">
                <svg class="w-7 h-7" style="color:var(--text-muted)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
            </div>
            <p class="font-semibold" style="color:var(--text-secondary)">Tidak ada antrian</p>
            <p class="text-sm mt-1" style="color:var(--text-muted)">Coba reset filter atau tambah booking baru</p>
        </div>

        <!-- Content Area -->
        <div v-else class="card-dark overflow-hidden">
            
            <!-- TAMPILAN DESKTOP — Tabel modern spacious -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full" style="border-collapse:collapse; min-width:860px">
                    <thead>
                        <tr style="background:var(--bg-surface-2)">
                            <th class="px-4 py-3.5 text-left w-10" style="color:var(--table-th-color); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; border-bottom:2px solid var(--border-table)">#</th>
                            <th class="px-4 py-3.5 text-left cursor-pointer select-none" style="color:var(--table-th-color); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; border-bottom:2px solid var(--border-table); min-width:180px" @click="toggleSort('nama_pasien')">
                                <span class="flex items-center gap-1">Pasien <span style="opacity:.5">{{ sortIcon('nama_pasien') }}</span></span>
                            </th>
                            <th class="px-4 py-3.5 text-left" style="color:var(--table-th-color); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; border-bottom:2px solid var(--border-table); min-width:120px">Jenis</th>
                            <th class="px-4 py-3.5 text-left" style="color:var(--table-th-color); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; border-bottom:2px solid var(--border-table); min-width:200px">Diagnosa / Indikasi</th>
                            <th class="px-4 py-3.5 text-left" style="color:var(--table-th-color); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; border-bottom:2px solid var(--border-table); min-width:110px">Jaminan</th>
                            <th class="px-4 py-3.5 text-left" style="color:var(--table-th-color); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; border-bottom:2px solid var(--border-table); min-width:150px">Bed</th>
                            <th class="px-4 py-3.5 text-left cursor-pointer select-none" style="color:var(--table-th-color); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; border-bottom:2px solid var(--border-table); min-width:140px" @click="toggleSort('status')">
                                <span class="flex items-center gap-1">Status <span style="opacity:.5">{{ sortIcon('status') }}</span></span>
                            </th>
                            <th class="px-4 py-3.5 text-left cursor-pointer select-none" style="color:var(--table-th-color); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; border-bottom:2px solid var(--border-table); min-width:130px" @click="toggleSort('created_at')">
                                <span class="flex items-center gap-1">Waktu <span style="opacity:.5">{{ sortIcon('created_at') }}</span></span>
                            </th>
                            <th class="px-4 py-3.5 text-center w-12" style="border-bottom:2px solid var(--border-table)"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, idx) in antrian" :key="`${item.sumber}-${item.id}`"
                            @click="openModal('detail', item)"
                            class="cursor-pointer group"
                            style="border-bottom:1px solid var(--border-row); transition:background .15s ease, transform .15s ease, box-shadow .15s ease"
                            :style="`border-left:4px solid ${ss(item.status).dot}`"
                            @mouseenter="e => { e.currentTarget.style.background='var(--bg-row-hover)'; e.currentTarget.style.transform='translateY(-1px)'; e.currentTarget.style.boxShadow='0 3px 12px rgba(0,0,0,0.07)'; e.currentTarget.style.zIndex='1'; e.currentTarget.style.position='relative'; }"
                            @mouseleave="e => { e.currentTarget.style.background=''; e.currentTarget.style.transform=''; e.currentTarget.style.boxShadow=''; }">
                            <!-- # -->
                            <td class="px-4 py-4">
                                <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold"
                                    style="background:var(--bg-input); color:var(--text-muted); font-family:'DM Mono',monospace">
                                    {{ idx+1 }}
                                </span>
                            </td>
                            <!-- Pasien -->
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 text-sm font-bold"
                                        :style="`background:${gColor(item.jenis_kelamin)}18; color:${gColor(item.jenis_kelamin)}`">
                                        {{ gIcon(item.jenis_kelamin) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-semibold truncate" style="color:var(--text-primary); font-size:13.5px">{{ item.nama_pasien }}</p>
                                        <p class="font-mono mt-0.5" style="color:var(--text-muted); font-size:10.5px">{{ item.No_MR ?? 'No MR' }}</p>
                                    </div>
                                </div>
                            </td>
                            <!-- Jenis -->
                            <td class="px-4 py-4">
                                <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-lg whitespace-nowrap"
                                    :style="`background:${SRC[item.sumber]?.bg}; color:${SRC[item.sumber]?.color}`">
                                    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" :style="`background:${SRC[item.sumber]?.color}`"></span>
                                    {{ item.sumber_label }}
                                </span>
                            </td>
                            <!-- Diagnosa -->
                            <td class="px-4 py-4">
                                <p class="font-medium truncate" style="color:var(--text-primary); font-size:13px; max-width:200px" :title="item.diagnosa">{{ item.diagnosa ?? '—' }}</p>
                            </td>
                            <!-- Jaminan -->
                            <td class="px-4 py-4">
                                <span v-if="item.jaminan" class="text-xs font-semibold px-2.5 py-1 rounded-lg"
                                    style="background:#EAF4FB; color:#3498DB">{{ jaminanLabel(item.jaminan) }}</span>
                                <span v-else class="text-xs" style="color:var(--text-muted)">—</span>
                            </td>
                            <!-- Bed -->
                            <td class="px-4 py-4">
                                <div v-if="item.nama_bed" class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0"
                                        style="background:#EBF9F1; color:#00A884; font-size:14px">🏥</div>
                                    <span class="font-semibold text-xs" style="color:#00A884">{{ item.nama_bed }}</span>
                                </div>
                                <span v-else class="text-xs" style="color:var(--text-muted)">Belum dialokasi</span>
                            </td>
                            <!-- Status -->
                            <td class="px-4 py-4">
                                <span class="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-xl whitespace-nowrap"
                                    :style="`background:${ss(item.status).bg}; color:${ss(item.status).color}`">
                                    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" :style="`background:${ss(item.status).color}`"></span>
                                    {{ item.status_label }}
                                </span>
                            </td>
                            <!-- Waktu -->
                            <td class="px-4 py-4">
                                <p class="font-mono text-xs" style="color:var(--text-secondary)">{{ item.created_at_fmt }}</p>
                            </td>
                            <!-- Arrow -->
                            <td class="px-4 py-4 text-center">
                                <div class="w-7 h-7 rounded-lg flex items-center justify-center mx-auto transition-all group-hover:translate-x-0.5"
                                    style="background:var(--bg-input); color:var(--text-muted)">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- TAMPILAN MOBILE — Kartu vertikal -->
            <div class="block md:hidden divide-y" style="border-color:var(--border-default)">
                <div v-for="(item, idx) in antrian" :key="`mob-${item.sumber}-${item.id}`"
                    @click="openModal('detail', item)"
                    class="p-5 cursor-pointer relative"
                    style="border-left:4px solid transparent; transition:background .15s ease"
                    :style="`border-left-color:${ss(item.status).dot}`"
                    @mouseenter="e => e.currentTarget.style.background='var(--bg-row-hover)'"
                    @mouseleave="e => e.currentTarget.style.background=''">
                    
                    <div class="flex justify-between items-start mb-3 gap-2 pr-6">
                        <div class="flex flex-wrap gap-1.5">
                            <span class="inline-flex items-center gap-1 text-xs font-bold px-2.5 py-1 rounded-lg" :style="`background:${ss(item.status).bg}; color:${ss(item.status).color}`">
                                <span class="w-1.5 h-1.5 rounded-full" :style="`background:${ss(item.status).color}`"></span>
                                {{ item.status_label }}
                            </span>
                            <span class="text-xs font-semibold px-2 py-1 rounded-lg" :style="`background:${SRC[item.sumber]?.bg}; color:${SRC[item.sumber]?.color}`">{{ item.sumber_label }}</span>
                        </div>
                        <span class="text-xs font-mono whitespace-nowrap" style="color:var(--text-muted)">{{ item.created_at_fmt?.split(' ')[0] }}</span>
                    </div>

                    <div class="flex items-center gap-3 mb-3 pr-6">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 text-base font-bold"
                            :style="`background:${gColor(item.jenis_kelamin)}18; color:${gColor(item.jenis_kelamin)}`">
                            {{ gIcon(item.jenis_kelamin) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-sm truncate" style="color:var(--text-primary)">{{ item.nama_pasien }}</p>
                            <p class="font-mono text-xs truncate" style="color:var(--text-muted)">{{ item.No_MR ?? '—' }} · {{ jaminanLabel(item.jaminan) }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 text-xs pr-6">
                        <div>
                            <p class="font-medium mb-0.5" style="color:var(--text-muted)">Diagnosa</p>
                            <p class="font-semibold truncate" style="color:var(--text-secondary)">{{ item.diagnosa ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="font-medium mb-0.5" style="color:var(--text-muted)">Bed</p>
                            <p class="font-semibold truncate" :style="item.nama_bed ? 'color:#00A884' : 'color:var(--text-muted)'">{{ item.nama_bed ? '🏥 ' + item.nama_bed : 'Belum dialokasi' }}</p>
                        </div>
                    </div>

                    <div class="absolute right-4 top-1/2 -translate-y-1/2 w-7 h-7 rounded-lg flex items-center justify-center"
                        style="background:var(--bg-input); color:var(--text-muted)">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="px-5 py-3.5 flex items-center justify-between" style="border-top:1px solid var(--border-default); background:var(--bg-surface-2)">
                <p class="text-xs" style="color:var(--text-secondary)">
                    Menampilkan <strong style="color:var(--text-primary)">{{ antrian.length }}</strong> data
                </p>
            </div>
        </div>
    </div>


    <!-- ══════════════════════════════════════════════════════════════════
         MODAL — pola satu container + inner Transition mode="out-in"
         persis Menu ICU → smooth crossfade, tidak ada pop/jump
    ══════════════════════════════════════════════════════════════════════ -->
    <Transition enter-active-class="transition-all duration-300 ease-out" enter-from-class="opacity-0" leave-to-class="opacity-0">
        <div v-if="modal.open"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
            style="background:rgba(0,0,0,0.65); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px)"
            @click.self="closeModal">

            <!-- Satu container tetap — ukuran berubah soft via CSS transition -->
            <div class="w-full flex flex-col relative overflow-hidden"
                :class="modal.type === 'booking' ? 'max-w-2xl' : modal.type === 'verifikasi' ? 'max-w-md' : 'max-w-lg'"
                style="max-height:92vh; background:var(--bg-surface); border:1px solid var(--border-default); border-radius:20px; box-shadow:0 25px 60px rgba(0,0,0,0.25), 0 8px 24px rgba(0,0,0,0.15); transition:max-width .25s ease"
                @click.stop>

                <!-- Inner Transition mode="out-in" → konten lama fade-out dulu, baru fade-in -->
                <Transition
                    enter-active-class="transition-all duration-220 ease-out"
                    enter-from-class="opacity-0 translate-y-2"
                    leave-active-class="transition-all duration-150 ease-in"
                    leave-to-class="opacity-0 -translate-y-1"
                    mode="out-in">

                <!-- ── VIEW: DETAIL ─────────────────────────────────────────── -->
                <div v-if="modal.type==='detail' && modal.item" key="detail" class="flex flex-col w-full" style="max-height:92vh">
                    <div class="flex items-start justify-between px-6 py-5 flex-shrink-0" style="border-bottom:1px solid var(--border-default)">
                        <div class="flex-1 min-w-0 pr-3">
                            <div class="flex items-center gap-2 flex-wrap mb-2">
                                <span class="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-full"
                                    :style="`background:${ss(modal.item.status).bg}; color:${ss(modal.item.status).color}`">
                                    <span class="w-1.5 h-1.5 rounded-full" :style="`background:${ss(modal.item.status).dot}`"></span>
                                    {{ modal.item.status_label }}
                                </span>
                                <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full"
                                    :style="`background:${SRC[modal.item.sumber]?.bg}; color:${SRC[modal.item.sumber]?.color}`">
                                    {{ modal.item.sumber_label }}
                                </span>
                            </div>
                            <h2 class="text-base font-bold truncate" style="color:var(--text-primary)">{{ modal.item.nama_pasien }}</h2>
                            <p class="text-xs mt-0.5" style="color:var(--text-secondary)">Detail Antrian Pasien</p>
                        </div>
                        <button @click="closeModal" class="w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0 transition-all hover:scale-110" style="background:var(--bg-input); color:var(--text-secondary)">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="overflow-y-auto flex-1 px-6 py-5 space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-0.5">
                                <p class="text-xs font-medium" style="color:var(--text-muted)">No. MR / Identitas</p>
                                <p class="text-sm font-bold font-mono" style="color:var(--text-primary)">{{ modal.item.No_MR ?? modal.item.no_identitas ?? '—' }}</p>
                            </div>
                            <div class="space-y-0.5">
                                <p class="text-xs font-medium" style="color:var(--text-muted)">Jenis Kelamin</p>
                                <p class="text-sm font-bold flex items-center gap-1.5" style="color:var(--text-primary)">
                                    <span :style="`color:${gColor(modal.item.jenis_kelamin)}`">{{ gIcon(modal.item.jenis_kelamin) }}</span>
                                    {{ modal.item.jenis_kelamin==='L'?'Pria':modal.item.jenis_kelamin==='P'?'Wanita':'—' }}
                                </p>
                            </div>
                            <div class="sm:col-span-2 space-y-0.5">
                                <p class="text-xs font-medium" style="color:var(--text-muted)">Diagnosa / Indikasi</p>
                                <p class="text-sm font-bold" style="color:var(--text-primary)">{{ modal.item.diagnosa ?? '—' }}</p>
                            </div>
                            <div class="space-y-0.5">
                                <p class="text-xs font-medium" style="color:var(--text-muted)">DPJP</p>
                                <p class="text-sm font-bold" style="color:var(--text-primary)">{{ modal.item.Dokter ?? '—' }}</p>
                            </div>
                            <div class="space-y-0.5">
                                <p class="text-xs font-medium" style="color:var(--text-muted)">Jaminan</p>
                                <p class="text-sm font-bold" style="color:var(--text-primary)">{{ jaminanLabel(modal.item.jaminan) }}</p>
                            </div>
                            <div class="space-y-0.5">
                                <p class="text-xs font-medium" style="color:var(--text-muted)">Asal Rujukan</p>
                                <p class="text-sm font-bold" style="color:var(--text-primary)">{{ modal.item.asal_rujukan ?? '—' }}</p>
                            </div>
                            <div class="space-y-0.5">
                                <p class="text-xs font-medium" style="color:var(--text-muted)">Alokasi Bed</p>
                                <p class="text-sm font-bold flex items-center gap-1.5" style="color:#00A884">
                                    <svg v-if="modal.item.nama_bed" class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                                    </svg>
                                    {{ modal.item.nama_bed ?? '—' }}
                                </p>
                                <p v-if="modal.item.kebutuhan_bed" class="text-xs mt-0.5" style="color:var(--text-muted)">{{ modal.item.kebutuhan_bed }}</p>
                            </div>
                            <div class="space-y-0.5">
                                <p class="text-xs font-medium" style="color:var(--text-muted)">Waktu Booking</p>
                                <p class="text-sm font-bold font-mono" style="color:var(--text-primary)">{{ modal.item.created_at_fmt }}</p>
                            </div>
                        </div>
                        <div v-if="modal.item.alasan_tolak" class="rounded-xl p-4 space-y-1.5" style="background:rgba(231,76,60,.06); border:1.5px solid rgba(231,76,60,.2)">
                            <p class="text-xs font-bold flex items-center gap-1.5" style="color:#E74C3C">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                Alasan Penolakan
                            </p>
                            <p class="text-sm" style="color:var(--text-primary)">{{ modal.item.alasan_tolak }}</p>
                        </div>
                        <div v-if="modal.item.catatan_admisi" class="rounded-xl p-4 space-y-1" style="background:var(--bg-surface-2); border:1px solid var(--border-default)">
                            <p class="text-xs font-medium" style="color:var(--text-muted)">Catatan Admisi</p>
                            <p class="text-sm" style="color:var(--text-primary)">{{ modal.item.catatan_admisi }}</p>
                        </div>
                    </div>
                    <div class="px-6 py-5 flex-shrink-0 space-y-3" style="border-top:1px solid var(--border-default); background:var(--bg-surface-2)">
                        <p class="text-xs font-bold uppercase tracking-widest" style="color:var(--text-muted)">Tindakan Tersedia</p>
                        <div class="flex flex-col gap-2.5">
                            <template v-for="act in actionsOf(modal.item)" :key="act.id">
                                <button @click="act.id==='verifikasi' ? openVerifModal(modal.item) : openModal(act.id, modal.item)"
                                    class="w-full text-sm font-bold py-3 rounded-xl flex items-center justify-center transition-all duration-150 hover:-translate-y-px hover:brightness-105"
                                    :style="`background:${act.bg}; color:${act.color}; border:1.5px solid ${act.border}`">
                                    {{ act.label }}
                                </button>
                            </template>
                            <p v-if="!actionsOf(modal.item).length" class="text-sm py-1" style="color:var(--text-muted)">Tidak ada aksi yang tersedia untuk status ini.</p>
                        </div>
                    </div>
                </div>

                <!-- ── VIEW: BOOKING BARU ───────────────────────────────────── -->
                <div v-else-if="modal.type==='booking'" key="booking" class="flex flex-col w-full" style="max-height:92vh">
                    <div class="flex items-center justify-between px-6 py-5 flex-shrink-0" style="border-bottom:1px solid var(--border-default)">
                        <div>
                            <h2 class="text-base font-bold" style="color:var(--text-primary)">Booking ICU Baru</h2>
                            <p class="text-xs mt-0.5" style="color:var(--text-secondary)">Pasien eksternal — akan dikirim ke ICU</p>
                        </div>
                        <button @click="closeModal" class="w-8 h-8 rounded-xl flex items-center justify-center transition-all hover:scale-110" style="background:var(--bg-input); color:var(--text-secondary)">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="overflow-y-auto flex-1">
                        <form @submit.prevent="submitBooking" class="p-6 space-y-6">
                            <!-- Identitas -->
                            <div class="space-y-3">
                                <p class="text-xs font-bold uppercase tracking-widest" style="color:var(--text-accent)">Identitas Pasien</p>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div class="sm:col-span-2 space-y-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Nama Pasien <span style="color:#E74C3C">*</span></label>
                                        <input v-model="fmBooking.nama_pasien" required placeholder="Nama lengkap" class="w-full rounded-xl outline-none"
                                            style="padding:10px 14px; font-size:13px"
                                            :style="`border:1.5px solid ${fmBooking.errors.nama_pasien?'#E74C3C':'var(--border-default)'}; background:var(--bg-input); color:var(--text-primary)`"/>
                                        <p v-if="fmBooking.errors.nama_pasien" class="text-xs" style="color:#E74C3C">{{ fmBooking.errors.nama_pasien }}</p>
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Jenis Kelamin <span style="color:#E74C3C">*</span></label>
                                        <div class="flex gap-2">
                                            <button type="button" @click="fmBooking.jenis_kelamin='L'" class="flex-1 py-2.5 rounded-xl text-sm font-semibold transition-colors"
                                                :style="fmBooking.jenis_kelamin==='L'?'background:#3498DB;color:#fff;border:2px solid #3498DB':'background:var(--bg-input);color:var(--text-secondary);border:2px solid var(--border-default)'">♂ Pria</button>
                                            <button type="button" @click="fmBooking.jenis_kelamin='P'" class="flex-1 py-2.5 rounded-xl text-sm font-semibold transition-colors"
                                                :style="fmBooking.jenis_kelamin==='P'?'background:#8E44AD;color:#fff;border:2px solid #8E44AD':'background:var(--bg-input);color:var(--text-secondary);border:2px solid var(--border-default)'">♀ Wanita</button>
                                        </div>
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">No. Identitas / NIK</label>
                                        <input v-model="fmBooking.no_identitas" placeholder="NIK / sementara" class="w-full rounded-xl outline-none font-mono"
                                            style="padding:10px 14px; font-size:13px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary)"/>
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Asal Rujukan</label>
                                        <input v-model="fmBooking.asal_rujukan" placeholder="RS / klinik pengirim" class="w-full rounded-xl outline-none"
                                            style="padding:10px 14px; font-size:13px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary)"/>
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">No. Telp Keluarga</label>
                                        <input v-model="fmBooking.no_telp_keluarga" placeholder="08xx-xxxx" class="w-full rounded-xl outline-none font-mono"
                                            style="padding:10px 14px; font-size:13px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary)"/>
                                    </div>
                                </div>
                            </div>
                            <!-- Klinis -->
                            <div class="space-y-3">
                                <p class="text-xs font-bold uppercase tracking-widest" style="color:var(--text-accent)">Data Klinis</p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Diagnosa <span style="color:#E74C3C">*</span></label>
                                        <Icd10Search v-model="fmBooking.diagnosa" placeholder="Cari kode ICD-10..." :required="true" :has-error="!!fmBooking.errors.diagnosa"/>
                                        <p v-if="fmBooking.errors.diagnosa" class="text-xs" style="color:#E74C3C">{{ fmBooking.errors.diagnosa }}</p>
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Rencana Tindakan <span style="color:#E74C3C">*</span></label>
                                        <input v-model="fmBooking.rencana_tindakan" required placeholder="Rencana tindakan ICU" class="w-full rounded-xl outline-none"
                                            style="padding:10px 14px; font-size:13px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary)"/>
                                    </div>
                                    <div class="sm:col-span-2 space-y-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Keterangan Klinis</label>
                                        <textarea v-model="fmBooking.keterangan" rows="2" placeholder="Kondisi, riwayat, catatan dokter pengirim..." class="w-full rounded-xl outline-none resize-none"
                                            style="padding:10px 14px; font-size:13px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); line-height:1.6"/>
                                    </div>
                                </div>
                            </div>
                            <!-- Jaminan -->
                            <div class="space-y-3">
                                <p class="text-xs font-bold uppercase tracking-widest" style="color:var(--text-accent)">Jaminan</p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Jenis Jaminan <span style="color:#E74C3C">*</span></label>
                                        <select v-model="fmBooking.jaminan" required class="w-full rounded-xl outline-none"
                                            style="padding:10px 14px; font-size:13px"
                                            :style="`border:1.5px solid ${fmBooking.errors.jaminan?'#E74C3C':'var(--border-default)'}; background:var(--bg-input); color:${fmBooking.jaminan?'var(--text-primary)':'var(--text-muted)'}`">
                                            <option value="" disabled>— Pilih Jaminan —</option>
                                            <option v-for="cb in caraBayar" :key="cb.kode" :value="cb.kode">{{ cb.nama }}</option>
                                        </select>
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Catatan Jaminan</label>
                                        <input v-model="fmBooking.catatan_jaminan" placeholder="No. BPJS / No. Polis..." class="w-full rounded-xl outline-none"
                                            style="padding:10px 14px; font-size:13px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary)"/>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 pt-2" style="border-top:1px solid var(--border-default)">
                                <button type="submit" :disabled="fmBooking.processing || !fmBooking.jenis_kelamin || !fmBooking.jaminan"
                                    class="flex items-center gap-2 font-bold px-6 py-3 rounded-xl transition-all duration-150 disabled:opacity-50 hover:-translate-y-px"
                                    style="background:#00A884; color:var(--text-on-accent); font-size:14px">
                                    <svg v-if="fmBooking.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    {{ fmBooking.processing ? 'Menyimpan...' : 'Kirim ke ICU' }}
                                </button>
                                <button type="button" @click="closeModal" class="px-6 py-3 rounded-xl font-medium"
                                    style="background:var(--bg-input); color:var(--text-secondary); border:1.5px solid var(--border-default); font-size:14px">Batal</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ── VIEW: APPROVE SPRI ───────────────────────────────────── -->
                <div v-else-if="modal.type==='approve' && modal.item" key="approve" class="flex flex-col w-full" style="max-height:92vh">
                    <div class="flex items-center justify-between px-6 py-5 flex-shrink-0" style="border-bottom:1px solid var(--border-default)">
                        <div class="flex items-center gap-3">
                            <button type="button" @click="openModal('detail', modal.item)" class="w-8 h-8 rounded-xl flex items-center justify-center transition-all hover:scale-110" style="background:var(--bg-input); color:var(--text-secondary)">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <div>
                                <h2 class="text-base font-bold" style="color:var(--text-primary)">Setujui SPRI</h2>
                                <p class="text-xs mt-0.5" style="color:var(--text-secondary)">{{ modal.item.nama_pasien }}</p>
                            </div>
                        </div>
                        <button @click="closeModal" class="w-8 h-8 rounded-xl flex items-center justify-center transition-all hover:scale-110" style="background:var(--bg-input); color:var(--text-secondary)">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="px-6 py-4 grid grid-cols-2 gap-3 flex-shrink-0" style="border-bottom:1px solid var(--border-default); background:var(--bg-surface-2)">
                        <div class="rounded-xl p-3 space-y-0.5" style="background:var(--bg-input)">
                            <p class="text-xs" style="color:var(--text-muted)">No. MR</p>
                            <p class="text-sm font-bold font-mono" style="color:var(--text-primary)">{{ modal.item.No_MR ?? '—' }}</p>
                        </div>
                        <div class="rounded-xl p-3 space-y-0.5" style="background:var(--bg-input)">
                            <p class="text-xs" style="color:var(--text-muted)">Asal Ruang</p>
                            <p class="text-sm font-bold truncate" style="color:var(--text-primary)">{{ modal.item.asal_rujukan ?? '—' }}</p>
                        </div>
                        <div class="col-span-2 rounded-xl p-3 space-y-0.5" style="background:var(--bg-input)">
                            <p class="text-xs" style="color:var(--text-muted)">Diagnosa</p>
                            <p class="text-sm font-bold" style="color:var(--text-primary)">{{ modal.item.diagnosa ?? '—' }}</p>
                        </div>
                    </div>
                    <form @submit.prevent="submitApprove" class="px-6 py-5 space-y-4 overflow-y-auto flex-1">
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">
                                Catatan Admisi <span class="normal-case font-normal" style="color:var(--text-muted)">(opsional)</span>
                            </label>
                            <textarea v-model="fmApprove.catatan_admisi" rows="4" placeholder="Informasi jaminan, kondisi khusus, catatan untuk petugas ICU..." class="w-full rounded-xl outline-none resize-none"
                                style="padding:11px 14px; font-size:13px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); line-height:1.6"/>
                        </div>
                        <div class="flex items-center gap-3 pt-1" style="border-top:1px solid var(--border-default)">
                            <button type="submit" :disabled="fmApprove.processing"
                                class="flex-1 font-bold py-3 rounded-xl transition-all duration-150 disabled:opacity-50 hover:-translate-y-px"
                                style="background:#00A884; color:var(--text-on-accent); font-size:14px">
                                {{ fmApprove.processing ? 'Menyimpan...' : '✓ Setujui SPRI' }}
                            </button>
                            <button type="button" @click="closeModal" class="px-5 py-3 rounded-xl font-medium"
                                style="background:var(--bg-input); color:var(--text-secondary); border:1.5px solid var(--border-default); font-size:14px">Batal</button>
                        </div>
                    </form>
                </div>

                <!-- ── VIEW: TOLAK SPRI ─────────────────────────────────────── -->
                <div v-else-if="modal.type==='tolak' && modal.item" key="tolak" class="flex flex-col w-full" style="max-height:92vh">
                    <div class="flex items-center justify-between px-6 py-5 flex-shrink-0" style="border-bottom:1px solid var(--border-default)">
                        <div class="flex items-center gap-3">
                            <button type="button" @click="openModal('detail', modal.item)" class="w-8 h-8 rounded-xl flex items-center justify-center transition-all hover:scale-110" style="background:var(--bg-input); color:var(--text-secondary)">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <div>
                                <h2 class="text-base font-bold" style="color:#E74C3C">Tolak Permintaan</h2>
                                <p class="text-xs mt-0.5" style="color:var(--text-secondary)">{{ modal.item.nama_pasien }}</p>
                            </div>
                        </div>
                        <button @click="closeModal" class="w-8 h-8 rounded-xl flex items-center justify-center transition-all hover:scale-110" style="background:var(--bg-input); color:var(--text-secondary)">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <form @submit.prevent="submitTolak" class="px-6 py-5 space-y-4 overflow-y-auto flex-1">
                        <div class="rounded-xl p-4 space-y-1" style="background:rgba(231,76,60,.06); border:1.5px solid rgba(231,76,60,.15)">
                            <p class="text-xs font-bold flex items-center gap-1.5" style="color:#E74C3C">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                Perhatian
                            </p>
                            <p class="text-xs" style="color:var(--text-secondary)">Tindakan ini tidak dapat dibatalkan. Pastikan alasan sudah jelas dan lengkap.</p>
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Alasan Penolakan <span style="color:#E74C3C">*</span></label>
                            <textarea v-model="fmTolak.alasan_tolak" required rows="5" placeholder="Tuliskan alasan penolakan secara jelas dan lengkap..." class="w-full rounded-xl outline-none resize-none"
                                style="padding:11px 14px; font-size:13px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); line-height:1.6"/>
                            <p v-if="fmTolak.errors.alasan_tolak" class="text-xs" style="color:#E74C3C">{{ fmTolak.errors.alasan_tolak }}</p>
                        </div>
                        <div class="flex items-center gap-3 pt-1" style="border-top:1px solid var(--border-default)">
                            <button type="submit" :disabled="fmTolak.processing || !fmTolak.alasan_tolak.trim()"
                                class="flex-1 font-bold py-3 rounded-xl transition-all duration-150 disabled:opacity-40 hover:-translate-y-px"
                                style="background:rgba(231,76,60,.12); color:#E74C3C; border:1.5px solid rgba(231,76,60,.3); font-size:14px">
                                {{ fmTolak.processing ? 'Menyimpan...' : '✕ Proses Penolakan' }}
                            </button>
                            <button type="button" @click="closeModal" class="px-5 py-3 rounded-xl font-medium"
                                style="background:var(--bg-input); color:var(--text-secondary); border:1.5px solid var(--border-default); font-size:14px">Batal</button>
                        </div>
                    </form>
                </div>

                <!-- ── VIEW: VERIFIKASI PASIEN TIBA ─────────────────────────── -->
                <div v-else-if="modal.type==='verifikasi' && modal.item" key="verifikasi" class="flex flex-col w-full" style="max-height:92vh">
                    <div class="flex items-center justify-between px-6 py-5 flex-shrink-0" style="border-bottom:1px solid var(--border-default)">
                        <div class="flex items-center gap-3">
                            <button type="button" @click="openModal('detail', modal.item)" class="w-8 h-8 rounded-xl flex items-center justify-center transition-all hover:scale-110" style="background:var(--bg-input); color:var(--text-secondary)">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <div>
                                <h2 class="text-base font-bold" style="color:var(--text-primary)">Verifikasi Pasien Tiba</h2>
                                <p class="text-xs mt-0.5" style="color:var(--text-secondary)">
                                    {{ modal.item.nama_pasien }} · Bed: <span style="color:#00A884">{{ modal.item.nama_bed }}</span>
                                </p>
                            </div>
                        </div>
                        <button @click="closeModal" class="w-8 h-8 rounded-xl flex items-center justify-center transition-all hover:scale-110" style="background:var(--bg-input); color:var(--text-secondary)">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <!-- Snapshot booking -->
                    <div class="px-6 py-4 grid grid-cols-2 gap-3 flex-shrink-0" style="border-bottom:1px solid var(--border-default); background:var(--bg-surface-2)">
                        <div class="rounded-xl p-3 space-y-0.5" style="background:var(--bg-input)">
                            <p class="text-xs" style="color:var(--text-muted)">Nama Booking</p>
                            <p class="text-sm font-bold" style="color:var(--text-primary)">{{ modal.item.nama_pasien }}</p>
                        </div>
                        <div class="rounded-xl p-3 space-y-0.5" style="background:var(--bg-input)">
                            <p class="text-xs" style="color:var(--text-muted)">Diagnosa</p>
                            <p class="text-sm font-bold truncate" :title="modal.item.diagnosa" style="color:var(--text-primary)">{{ modal.item.diagnosa ?? '—' }}</p>
                        </div>
                    </div>
                    <form @submit.prevent="submitVerif" class="px-6 py-5 space-y-4 overflow-y-auto flex-1">
                        <p class="text-sm" style="color:var(--text-secondary)">Cari No. MR pasien untuk memverifikasi kedatangan.</p>
                        <!-- Input No. MR + Cari -->
                        <div class="space-y-1.5">
                            <div class="flex gap-2">
                                <input v-model="fmVerif.No_MR" @keydown.enter.prevent="doVerifLookup" placeholder="Masukkan No. MR..."
                                    class="flex-1 rounded-xl outline-none font-mono"
                                    style="padding:10px 14px; font-size:13px"
                                    :style="`border:1.5px solid ${verifLookupError?'#E74C3C':verifLookupResult?.found?'#00A884':'var(--border-default)'}; background:var(--bg-input); color:var(--text-primary)`"/>
                                <button type="button" @click="doVerifLookup" :disabled="verifLookupLoading || fmVerif.No_MR.length < 3"
                                    class="flex items-center gap-1.5 font-semibold px-4 rounded-xl transition-all duration-150 disabled:opacity-40"
                                    style="background:rgba(0,168,132,.15); color:#00A884; border:1.5px solid rgba(0,168,132,.3); font-size:13px; white-space:nowrap">
                                    <svg v-if="verifLookupLoading" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                    Cari
                                </button>
                            </div>
                            <p v-if="fmVerif.errors.No_MR" class="text-xs" style="color:#E74C3C">{{ fmVerif.errors.No_MR }}</p>
                        </div>
                        <!-- Error lookup -->
                        <div v-if="verifLookupError" class="rounded-xl px-4 py-3 text-sm" style="background:rgba(231,76,60,.08); color:#E74C3C; border:1.5px solid rgba(231,76,60,.2)">
                            ⚠ {{ verifLookupError }}
                        </div>
                        <!-- Hasil ditemukan -->
                        <div v-if="verifLookupResult?.found" class="rounded-xl px-4 py-3" style="background:rgba(0,168,132,.08); border:1.5px solid rgba(0,168,132,.2)">
                            <div class="flex items-center gap-3">
                                <span class="text-lg" style="color:#00A884">✓</span>
                                <div>
                                    <p class="text-sm font-bold" style="color:var(--text-primary)">{{ verifLookupResult.nama_pasien }}</p>
                                    <p class="text-xs font-mono mt-0.5" style="color:var(--text-muted)">{{ verifLookupResult.No_MR }}</p>
                                </div>
                            </div>
                        </div>
                        <!-- Pilih kunjungan (>1) -->
                        <div v-if="verifKunjungans.length > 1" class="space-y-1.5">
                            <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Pilih No. Reg Kunjungan <span style="color:#E74C3C">*</span></label>
                            <div class="space-y-2 max-h-40 overflow-y-auto">
                                <button v-for="k in verifKunjungans" :key="k.No_Reg" type="button" @click="fmVerif.No_Reg = k.No_Reg"
                                    class="w-full text-left px-4 py-2.5 rounded-xl text-sm transition-all"
                                    :style="fmVerif.No_Reg===k.No_Reg?'background:rgba(0,168,132,.12); border:1.5px solid rgba(0,168,132,.4); color:var(--text-primary)':'background:var(--bg-input); border:1px solid var(--border-default); color:var(--text-secondary)'">
                                    <span class="font-mono font-semibold">{{ k.No_Reg }}</span>
                                    <span class="ml-2 text-xs" style="color:var(--text-muted)">· {{ k.nama_ruang || k.Kode_Masuk || '-' }}</span>
                                </button>
                            </div>
                        </div>
                        <!-- Auto-fill 1 kunjungan -->
                        <div v-if="verifKunjungans.length===1 && fmVerif.No_Reg" class="text-sm" style="color:var(--text-secondary)">
                            No. Reg: <span class="font-mono font-semibold" style="color:var(--text-primary)">{{ fmVerif.No_Reg }}</span>
                        </div>
                        <!-- Manual No. Reg (0 kunjungan tapi found) -->
                        <div v-if="verifKunjungans.length===0 && verifLookupResult?.found" class="space-y-1.5">
                            <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">No. Reg <span class="normal-case font-normal">(opsional)</span></label>
                            <input v-model="fmVerif.No_Reg" placeholder="No. Reg kunjungan" class="w-full rounded-xl outline-none font-mono"
                                style="padding:10px 14px; font-size:13px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary)"/>
                        </div>
                        <div class="flex items-center gap-3 pt-1" style="border-top:1px solid var(--border-default)">
                            <button type="submit" :disabled="fmVerif.processing || !fmVerif.No_MR.trim() || !verifLookupResult?.found"
                                class="flex-1 font-bold py-3 rounded-xl transition-all duration-150 disabled:opacity-40 hover:-translate-y-px"
                                style="background:#00A884; color:var(--text-on-accent); font-size:14px">
                                {{ fmVerif.processing ? 'Menyimpan...' : '✓ Verifikasi Kedatangan' }}
                            </button>
                            <button type="button" @click="closeModal" class="px-5 py-3 rounded-xl font-medium"
                                style="background:var(--bg-input); color:var(--text-secondary); border:1.5px solid var(--border-default); font-size:14px">Batal</button>
                        </div>
                    </form>
                </div>

                </Transition>
            </div><!-- end container -->
        </div><!-- end backdrop -->
    </Transition><!-- end outer -->

</AppLayout>
</template>
