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

// ── Flash ──────────────────────────────────────────────────
const toast = ref(null);
watch(() => props.flash, (f) => {
    if (f?.success) toast.value = { type: 'success', msg: f.success };
    if (f?.error)   toast.value = { type: 'error',   msg: f.error   };
    if (toast.value) setTimeout(() => toast.value = null, 4000);
}, { immediate: true, deep: true });

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
    pending_icu:     { bg: 'rgba(224,146,58,.15)',  color: '#E0923A', dot: '#E0923A' },
    pending_admisi:  { bg: 'rgba(245,166,35,.15)',  color: '#F5A623', dot: '#F5A623' },
    bed_confirmed:   { bg: 'rgba(74,144,217,.15)',  color: '#4A90D9', dot: '#4A90D9' },
    bed_verified:    { bg: 'rgba(45,217,164,.15)',  color: '#2DD9A4', dot: '#2DD9A4' },
    admisi_verified: { bg: 'rgba(45,217,164,.15)',  color: '#2DD9A4', dot: '#2DD9A4' },
    ditolak:         { bg: 'rgba(224,112,80,.15)',  color: '#E07050', dot: '#E07050' },
};
const ss = (s) => SS[s] ?? { bg: 'var(--bg-input)', color: 'var(--text-secondary)', dot: '#888' };

const SRC = {
    external: { bg: 'rgba(74,144,217,.12)', color: '#4A90D9' },
    internal: { bg: 'rgba(142,168,158,.12)', color: '#8EA89E' },
};
const jaminanLabel = (k) => props.caraBayar.find(c => c.kode === k)?.nama ?? k ?? '-';
const gIcon  = (g) => g === 'L' ? '♂' : g === 'P' ? '♀' : '·';
const gColor = (g) => g === 'L' ? '#4A90D9' : g === 'P' ? '#D9517A' : 'var(--text-secondary)';

// ── Summary cards ──────────────────────────────────────────
const CARDS = computed(() => [
    { key:'',               label:'Total',           val: props.summary.total        ?? 0, color:'#8EA89E' },
    { key:'pending_admisi', label:'Menunggu Admisi',  val: props.antrian.filter(a=>a.status==='pending_admisi').length, color:'#F5A623' },
    { key:'bed_confirmed',  label:'Perlu Verifikasi', val: props.antrian.filter(a=>a.status==='bed_confirmed').length,  color:'#4A90D9' },
    { key:'admisi_verified',label:'Selesai',          val: props.summary.verified      ?? 0, color:'#2DD9A4' },
    { key:'ditolak',        label:'Ditolak',          val: props.summary.ditolak       ?? 0, color:'#E07050' },
]);

// ── Aksi yang tersedia per item ────────────────────────────
const canAct = computed(() => canVerifikasiAdmisiExt.value || canApproveAdmisi.value || isAdmin.value);

const actionsOf = (item) => {
    if (!canAct.value) return [];
    const acts = [];
    if (item.sumber === 'internal' && item.status === 'pending_admisi') {
        acts.push({ id:'approve', label:'Setujui SPRI', color:'#2DD9A4', bg:'rgba(45,217,164,.12)', border:'rgba(45,217,164,.3)' });
        acts.push({ id:'tolak',   label:'Tolak SPRI',   color:'#E07050', bg:'rgba(224,112,80,.08)', border:'rgba(224,112,80,.25)' });
    }
    if (item.sumber === 'external' && item.status === 'bed_confirmed') {
        acts.push({ id:'verifikasi', label:'Verifikasi Pasien', color:'#2DD9A4', bg:'rgba(45,217,164,.12)', border:'rgba(45,217,164,.3)' });
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

    <!-- Toast -->
    <Transition enter-active-class="transition-all duration-300" enter-from-class="opacity-0 -translate-y-2"
        leave-active-class="transition-all duration-200" leave-to-class="opacity-0 -translate-y-2">
        <div v-if="toast" class="fixed top-4 right-4 z-[100] flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold shadow-xl"
            :style="toast.type==='success'
                ? 'background:#2DD9A4; color:#0D1A17'
                : 'background:#E07050; color:#fff'">
            {{ toast.type==='success' ? '✓' : '✕' }} {{ toast.msg }}
        </div>
    </Transition>

    <div class="p-4 sm:p-6 space-y-4" style="font-family:'Plus Jakarta Sans',sans-serif">

        <!-- Header -->
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <h1 class="font-bold text-xl" style="color:var(--text-primary)">Menu Admisi</h1>
                <p class="text-xs mt-0.5" style="color:var(--text-secondary)">Booking Eksternal & SPRI Internal</p>
            </div>
            <button v-if="canBuatBookingExternal" @click="openModal('booking')"
                class="flex items-center gap-2 text-sm font-bold px-4 py-2.5 rounded-xl hover:brightness-110 transition-all"
                style="background:#2DD9A4; color:#0D1A17">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                Booking Baru
            </button>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <button v-for="c in CARDS" :key="c.key"
                @click="fStatus=c.key; applyFilters()"
                class="card-dark p-3 sm:p-3.5 flex items-center gap-2.5 sm:gap-3 text-left transition-all hover:scale-[1.02]"
                :style="fStatus===c.key ? `border:1.5px solid ${c.color}50` : ''">
                <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl flex items-center justify-center flex-shrink-0"
                    :style="`background:${c.color}1A`">
                    <span class="text-base sm:text-lg font-bold" :style="`color:${c.color}`">{{ c.val }}</span>
                </div>
                <p class="text-[10px] sm:text-[11px] leading-tight" style="color:var(--text-secondary)">{{ c.label }}</p>
            </button>
        </div>

        <!-- Breakdown sumber -->
        <div class="flex items-center gap-3 sm:gap-4 text-[11px] sm:text-xs flex-wrap" style="color:var(--text-secondary)">
            <span class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full inline-block" style="background:#4A90D9"></span>
                Booking Eksternal: <strong style="color:var(--text-primary)">{{ summary.by_sumber?.external ?? 0 }}</strong>
            </span>
            <span class="flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 rounded-full inline-block" style="background:#8EA89E"></span>
                SPRI Internal: <strong style="color:var(--text-primary)">{{ summary.by_sumber?.internal ?? 0 }}</strong>
            </span>
        </div>

        <!-- Filter bar -->
        <div class="card-dark p-3.5 space-y-3">
            <!-- Menyesuaikan agar HP jadi 1 kolom, tablet 2 kolom, desktop 4 kolom -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-2.5">
                <div>
                    <label class="block text-[10px] font-semibold mb-1" style="color:var(--text-secondary)">Status</label>
                    <select v-model="fStatus" @change="applyFilters" class="w-full text-xs px-2.5 py-2 rounded-lg outline-none"
                        style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)">
                        <option v-for="o in statusOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-semibold mb-1" style="color:var(--text-secondary)">Jenis</label>
                    <select v-model="fJenis" @change="applyFilters" class="w-full text-xs px-2.5 py-2 rounded-lg outline-none"
                        style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)">
                        <option v-for="o in jenisOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-semibold mb-1" style="color:var(--text-secondary)">Nama / No. MR</label>
                    <input v-model="fNama" @input="onNamaInput" placeholder="Cari..."
                        class="w-full text-xs px-2.5 py-2 rounded-lg outline-none"
                        style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                </div>
                <div>
                    <label class="block text-[10px] font-semibold mb-1" style="color:var(--text-secondary)">Tanggal</label>
                    <input v-model="fTgl" @change="applyFilters" type="date"
                        class="w-full text-xs px-2.5 py-2 rounded-lg outline-none"
                        style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-[10px]" style="color:var(--text-muted)">Urutkan:</span>
                <button v-for="col in [{key:'created_at',label:'Waktu'},{key:'nama_pasien',label:'Nama'},{key:'status',label:'Status'}]"
                    :key="col.key" @click="toggleSort(col.key)"
                    class="text-[10px] px-2 py-1 rounded-lg font-semibold transition-colors"
                    :style="sortBy===col.key
                        ? 'background:rgba(45,217,164,.15); color:#2DD9A4; border:1px solid rgba(45,217,164,.3)'
                        : 'background:var(--bg-input); color:var(--text-secondary); border:1px solid var(--border-default)'">
                    {{ col.label }} {{ sortIcon(col.key) }}
                </button>
                <button v-if="fStatus||fJenis||fNama||fTgl" @click="resetFilter"
                    class="text-[10px] px-2 py-1 rounded-lg ml-auto hover:brightness-110 transition-colors"
                    style="background:rgba(224,112,80,.1); color:#E07050; border:1px solid rgba(224,112,80,.2)">
                    ✕ Reset
                </button>
            </div>
        </div>

        <!-- Empty -->
        <div v-if="!antrian.length" class="card-dark text-center py-14">
            <p class="text-sm font-semibold" style="color:var(--text-secondary)">Tidak ada antrian</p>
            <p class="text-xs mt-1" style="color:var(--text-muted)">Coba reset filter atau tambah booking baru</p>
        </div>

        <!-- Content Area -->
        <div v-else class="card-dark overflow-hidden">
            
            <!-- 💻 TAMPILAN DESKTOP (Tabel) -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-xs" style="border-collapse:collapse; min-width:700px">
                    <thead>
                        <tr style="background:var(--bg-main); border-bottom:2px solid var(--border-default)">
                            <th class="px-3 py-2.5 text-left font-semibold w-7" style="color:var(--text-secondary)">#</th>
                            <th class="px-3 py-2.5 text-left font-semibold cursor-pointer hover:opacity-80" style="color:var(--text-secondary)" @click="toggleSort('nama_pasien')">Pasien {{ sortIcon('nama_pasien') }}</th>
                            <th class="px-3 py-2.5 text-left font-semibold" style="color:var(--text-secondary)">Jenis</th>
                            <th class="px-3 py-2.5 text-left font-semibold" style="color:var(--text-secondary)">Diagnosa / Indikasi</th>
                            <th class="px-3 py-2.5 text-left font-semibold" style="color:var(--text-secondary)">Jaminan</th>
                            <th class="px-3 py-2.5 text-left font-semibold" style="color:var(--text-secondary)">Bed</th>
                            <th class="px-3 py-2.5 text-left font-semibold cursor-pointer hover:opacity-80" style="color:var(--text-secondary)" @click="toggleSort('status')">Status {{ sortIcon('status') }}</th>
                            <th class="px-3 py-2.5 text-left font-semibold cursor-pointer hover:opacity-80" style="color:var(--text-secondary)" @click="toggleSort('created_at')">Waktu {{ sortIcon('created_at') }}</th>
                            <th class="px-3 py-2.5 text-center font-semibold w-10"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, idx) in antrian" :key="`${item.sumber}-${item.id}`"
                            @click="openModal('detail', item)"
                            :style="`border-bottom:1px solid var(--border-default); border-left:3px solid ${ss(item.status).dot}`"
                            class="transition-colors hover:bg-[var(--bg-input)] cursor-pointer group">
                            <td class="px-3 py-2.5 font-mono" style="color:var(--text-muted)">{{ idx+1 }}</td>
                            <td class="px-3 py-2.5">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-bold flex-shrink-0" :style="`color:${gColor(item.jenis_kelamin)}`">{{ gIcon(item.jenis_kelamin) }}</span>
                                    <div class="min-w-0">
                                        <p class="font-semibold truncate" style="color:var(--text-primary); max-width:130px">{{ item.nama_pasien }}</p>
                                        <p class="font-mono text-[10px]" style="color:var(--text-secondary)">{{ item.No_MR ?? '—' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-2.5">
                                <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full whitespace-nowrap"
                                    :style="`background:${SRC[item.sumber]?.bg}; color:${SRC[item.sumber]?.color}`">
                                    {{ item.sumber_label }}
                                </span>
                            </td>
                            <td class="px-3 py-2.5" style="max-width:160px">
                                <p class="truncate" :title="item.diagnosa" style="color:var(--text-primary)">{{ item.diagnosa ?? '-' }}</p>
                            </td>
                            <td class="px-3 py-2.5">
                                <span v-if="item.jaminan" class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full"
                                    style="background:rgba(74,144,217,.12); color:#4A90D9">{{ jaminanLabel(item.jaminan) }}</span>
                                <span v-else class="text-[10px]" style="color:var(--text-muted)">—</span>
                            </td>
                            <td class="px-3 py-2.5">
                                <span v-if="item.nama_bed" class="font-semibold text-[11px]" style="color:#2DD9A4">🏥 {{ item.nama_bed }}</span>
                                <span v-else class="text-[10px]" style="color:var(--text-muted)">—</span>
                            </td>
                            <td class="px-3 py-2.5">
                                <span class="text-[10px] font-bold px-2 py-1 rounded-full whitespace-nowrap"
                                    :style="`background:${ss(item.status).bg}; color:${ss(item.status).color}`">
                                    {{ item.status_label }}
                                </span>
                            </td>
                            <td class="px-3 py-2.5 font-mono whitespace-nowrap" style="color:var(--text-secondary)">
                                {{ item.created_at_fmt }}
                            </td>
                            <td class="px-3 py-2.5 text-center transition-transform group-hover:translate-x-1" style="color:var(--text-muted)">
                                ❯
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- 📱 TAMPILAN MOBILE (Daftar Kartu) -->
            <div class="block md:hidden divide-y" style="border-color:var(--border-default)">
                <div v-for="(item, idx) in antrian" :key="`mob-${item.sumber}-${item.id}`"
                    @click="openModal('detail', item)"
                    class="p-4 transition-colors hover:bg-[var(--bg-input)] cursor-pointer relative group"
                    :style="`border-left:4px solid ${ss(item.status).dot}`">
                    
                    <div class="flex justify-between items-start mb-2 gap-2 pr-4">
                        <div class="flex flex-wrap gap-1.5">
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full" :style="`background:${ss(item.status).bg}; color:${ss(item.status).color}`">{{ item.status_label }}</span>
                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full" :style="`background:${SRC[item.sumber]?.bg}; color:${SRC[item.sumber]?.color}`">{{ item.sumber_label }}</span>
                        </div>
                        <span class="text-[10px] font-mono whitespace-nowrap" style="color:var(--text-muted)">{{ item.created_at_fmt.split(' ')[0] }}</span>
                    </div>

                    <div class="flex items-center gap-2.5 mb-2.5 pr-4">
                        <span class="font-bold text-lg flex-shrink-0" :style="`color:${gColor(item.jenis_kelamin)}`">{{ gIcon(item.jenis_kelamin) }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-sm truncate" style="color:var(--text-primary)">{{ item.nama_pasien }}</p>
                            <p class="font-mono text-[10px] truncate" style="color:var(--text-secondary)">{{ item.No_MR ?? 'Belum ada MR' }} • {{ jaminanLabel(item.jaminan) }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 text-[11px] pr-4">
                        <div class="min-w-0">
                            <p style="color:var(--text-muted)">Diagnosa</p>
                            <p class="font-semibold truncate" style="color:var(--text-secondary)">{{ item.diagnosa ?? '-' }}</p>
                        </div>
                        <div class="min-w-0">
                            <p style="color:var(--text-muted)">Bed</p>
                            <p class="font-semibold truncate" :style="item.nama_bed ? 'color:#2DD9A4' : 'color:var(--text-secondary)'">{{ item.nama_bed ? '🏥 ' + item.nama_bed : '-' }}</p>
                        </div>
                    </div>

                    <div class="absolute right-4 top-1/2 -translate-y-1/2 text-lg transition-transform group-hover:translate-x-1" style="color:var(--text-muted)">
                        ❯
                    </div>
                </div>
            </div>

            <!-- Footer Pagination / Total -->
            <div class="px-4 py-3" style="border-top:1px solid var(--border-default); background:var(--bg-main)">
                <p class="text-[11px]" style="color:var(--text-secondary)">
                    Menampilkan <strong style="color:var(--text-primary)">{{ antrian.length }}</strong> data
                </p>
            </div>
        </div>
    </div>

    <!-- ═══════════════ MODAL WRAPPER ═══════════════════════════════════ -->
    <Transition enter-active-class="transition-all duration-200" enter-from-class="opacity-0" leave-to-class="opacity-0">
        <div v-if="modal.open" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4" style="background:rgba(0,0,0,0.6); backdrop-filter:blur(3px)" @click.self="closeModal">

            <!-- ── Modal: Detail & Aksi Pasien ──────────────────────────────── -->
            <Transition enter-active-class="transition-all duration-200" enter-from-class="opacity-0 scale-95" leave-to-class="opacity-0 scale-95">
                <div v-if="modal.type==='detail' && modal.item" class="w-full max-w-lg rounded-2xl flex flex-col" style="background:var(--bg-sidebar); border:1px solid var(--border-default); max-height: 90vh;">
                    
                    <!-- Header Detail -->
                    <div class="flex items-center justify-between px-4 sm:px-5 py-4 flex-shrink-0" style="border-bottom:1px solid var(--border-default)">
                        <div>
                            <p class="font-bold text-sm" style="color:var(--text-primary)">Detail Antrian Pasien</p>
                            <p class="text-xs mt-0.5" style="color:var(--text-secondary)">{{ modal.item.nama_pasien }}</p>
                        </div>
                        <button @click="closeModal" class="p-1.5 rounded-lg hover:brightness-110 transition-colors" style="background:var(--bg-input); color:var(--text-secondary)">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    
                    <!-- Body Detail -->
                    <div class="p-4 sm:p-5 space-y-4 overflow-y-auto">
                        <!-- Status Badge -->
                        <div class="flex gap-2 mb-2 flex-wrap">
                            <span class="text-[10px] font-bold px-2.5 py-1 rounded-full" :style="`background:${ss(modal.item.status).bg}; color:${ss(modal.item.status).color}`">
                                {{ modal.item.status_label }}
                            </span>
                            <span class="text-[10px] font-semibold px-2.5 py-1 rounded-full" :style="`background:${SRC[modal.item.sumber]?.bg}; color:${SRC[modal.item.sumber]?.color}`">
                                {{ modal.item.sumber_label }}
                            </span>
                        </div>

                        <!-- Data Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-3 gap-x-4 text-xs">
                            <div>
                                <p style="color:var(--text-secondary)">No. MR / Identitas</p>
                                <p class="font-semibold font-mono mt-0.5" style="color:var(--text-primary)">{{ modal.item.No_MR ?? modal.item.no_identitas ?? '—' }}</p>
                            </div>
                            <div>
                                <p style="color:var(--text-secondary)">Jenis Kelamin</p>
                                <p class="font-semibold mt-0.5 flex items-center gap-1" style="color:var(--text-primary)">
                                    <span :style="`color:${gColor(modal.item.jenis_kelamin)}`">{{ gIcon(modal.item.jenis_kelamin) }}</span>
                                    {{ modal.item.jenis_kelamin === 'L' ? 'Pria' : modal.item.jenis_kelamin === 'P' ? 'Wanita' : '—' }}
                                </p>
                            </div>
                            <div class="sm:col-span-2">
                                <p style="color:var(--text-secondary)">Diagnosa / Indikasi</p>
                                <p class="font-semibold mt-0.5" style="color:var(--text-primary)">{{ modal.item.diagnosa ?? '—' }}</p>
                                <p v-if="modal.item.IndikasiRI" class="mt-0.5 text-[10px]" style="color:var(--text-secondary)">{{ modal.item.IndikasiRI }}</p>
                            </div>

                            <div>
                                <p style="color:var(--text-secondary)">Indikasi RI</p>
                                <p class="font-semibold mt-0.5" style="color:var(--text-primary)">{{ modal.item.IndikasiRI }}</p>
                            </div>

                            <div>
                                <p style="color:var(--text-secondary)">DPJP</p>
                                <p class="font-semibold mt-0.5" style="color:var(--text-primary)">{{ modal.item.Dokter ?? '—' }}</p>
                            </div>
                            
                            <div>
                                <p style="color:var(--text-secondary)">Jaminan</p>
                                <p class="font-semibold mt-0.5" style="color:var(--text-primary)">{{ jaminanLabel(modal.item.jaminan) }}</p>
                            </div>
                            <div>
                                <p style="color:var(--text-secondary)">Asal Rujukan</p>
                                <p class="font-semibold mt-0.5" style="color:var(--text-primary)">{{ modal.item.asal_rujukan ?? '—' }}</p>
                            </div>
                            <div>
                                <p style="color:var(--text-secondary)">Alokasi Bed</p>
                                <p class="font-semibold mt-0.5" style="color:#2DD9A4">{{ modal.item.nama_bed ? '🏥 ' + modal.item.nama_bed : '—' }}</p>
                                <p v-if="modal.item.kebutuhan_bed" class="text-[10px] mt-0.5" style="color:var(--text-muted)">{{ modal.item.kebutuhan_bed }}</p>
                            </div>
                            <div>
                                <p style="color:var(--text-secondary)">Waktu Booking</p>
                                <p class="font-semibold mt-0.5 font-mono" style="color:var(--text-primary)">{{ modal.item.created_at_fmt }}</p>
                            </div>
                            
                            <!-- Box Catatan Khusus (Ditolak / Catatan Admisi) -->
                            <div v-if="modal.item.alasan_tolak" class="sm:col-span-2 p-3 rounded-xl mt-1" style="background:rgba(224,112,80,.08); border:1px solid rgba(224,112,80,.2)">
                                <p style="color:#E07050" class="font-semibold">Alasan Ditolak:</p>
                                <p class="mt-1" style="color:var(--text-primary)">{{ modal.item.alasan_tolak }}</p>
                            </div>
                            <div v-if="modal.item.catatan_admisi" class="sm:col-span-2 p-3 rounded-xl mt-1" style="background:var(--bg-input)">
                                <p style="color:var(--text-secondary)" class="font-semibold">Catatan Admisi:</p>
                                <p class="mt-1" style="color:var(--text-primary)">{{ modal.item.catatan_admisi }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Aksi -->
                    <div class="px-4 sm:px-5 py-4 flex-shrink-0" style="border-top:1px solid var(--border-default); background:var(--bg-main); border-bottom-left-radius:1rem; border-bottom-right-radius:1rem;">
                        <p class="text-[10px] font-bold uppercase tracking-widest mb-2.5" style="color:var(--text-secondary)">Tindakan Tersedia</p>
                        <div class="flex gap-2 flex-wrap">
                            <template v-for="act in actionsOf(modal.item)" :key="act.id">
                                <!-- Klik tombol aksi di sini akan otomatis "menimpa" modal.type ke form aksinya -->
                                <button @click="act.id==='verifikasi' ? openVerifModal(modal.item) : openModal(act.id, modal.item)"
                                    class="text-xs font-bold px-4 py-2.5 rounded-xl flex-1 flex items-center justify-center transition-transform hover:scale-[1.02]"
                                    :style="`background:${act.bg}; color:${act.color}; border:1px solid ${act.border}`">
                                    {{ act.label }}
                                </button>
                            </template>
                            <p v-if="!actionsOf(modal.item).length" class="text-xs w-full py-2" style="color:var(--text-muted)">
                                Tidak ada aksi yang dapat Anda lakukan untuk status ini.
                            </p>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- ── Modal: Booking Baru ──────────────────────────────── -->
            <Transition enter-active-class="transition-all duration-200" enter-from-class="opacity-0 scale-95" leave-to-class="opacity-0 scale-95">
                <div v-if="modal.type==='booking'" class="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl" style="background:var(--bg-sidebar); border:1px solid var(--border-default)">
                    <div class="flex items-center justify-between px-4 sm:px-5 py-4" style="border-bottom:1px solid var(--border-default)">
                        <div>
                            <p class="font-bold text-sm" style="color:var(--text-primary)">Booking ICU Baru</p>
                            <p class="text-xs mt-0.5" style="color:var(--text-secondary)">Pasien eksternal — akan dikirim ke ICU</p>
                        </div>
                        <button @click="closeModal" class="p-1.5 rounded-lg" style="background:var(--bg-input); color:var(--text-secondary)">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <form @submit.prevent="submitBooking" class="p-4 sm:p-5 space-y-4">
                        <!-- Identitas -->
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest mb-2.5" style="color:var(--text-accent)">Identitas Pasien</p>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">Nama Pasien <span style="color:#E07050">*</span></label>
                                    <input v-model="fmBooking.nama_pasien" required placeholder="Nama lengkap" class="w-full text-xs px-3 py-2 rounded-xl outline-none"
                                        :style="`border:1px solid ${fmBooking.errors.nama_pasien?'#E07050':'var(--border-default)'}; background:var(--bg-surface); color:var(--text-primary)`"/>
                                    <p v-if="fmBooking.errors.nama_pasien" class="text-[10px] mt-1" style="color:#E07050">{{ fmBooking.errors.nama_pasien }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">Jenis Kelamin <span style="color:#E07050">*</span></label>
                                    <div class="flex gap-2">
                                        <button type="button" @click="fmBooking.jenis_kelamin='L'"
                                            class="flex-1 py-2 rounded-xl text-xs font-semibold transition-colors"
                                            :style="fmBooking.jenis_kelamin==='L' ? 'background:#4A90D9;color:#fff;border:2px solid #4A90D9' : 'background:var(--bg-surface);color:var(--text-secondary);border:2px solid var(--border-default)'">♂ Pria</button>
                                        <button type="button" @click="fmBooking.jenis_kelamin='P'"
                                            class="flex-1 py-2 rounded-xl text-xs font-semibold transition-colors"
                                            :style="fmBooking.jenis_kelamin==='P' ? 'background:#D9517A;color:#fff;border:2px solid #D9517A' : 'background:var(--bg-surface);color:var(--text-secondary);border:2px solid var(--border-default)'">♀ Wanita</button>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">No. Identitas / NIK</label>
                                    <input v-model="fmBooking.no_identitas" placeholder="NIK / sementara" class="w-full text-xs px-3 py-2 rounded-xl outline-none font-mono"
                                        style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">Asal Rujukan</label>
                                    <input v-model="fmBooking.asal_rujukan" placeholder="RS / klinik pengirim" class="w-full text-xs px-3 py-2 rounded-xl outline-none"
                                        style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">No. Telp Keluarga</label>
                                    <input v-model="fmBooking.no_telp_keluarga" placeholder="08xx-xxxx" class="w-full text-xs px-3 py-2 rounded-xl outline-none font-mono"
                                        style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                                </div>
                            </div>
                        </div>
                        <!-- Klinis -->
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest mb-2.5" style="color:var(--text-accent)">Data Klinis</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">Diagnosa <span style="color:#E07050">*</span></label>
                                    <Icd10Search v-model="fmBooking.diagnosa" placeholder="Cari kode ICD-10..."
                                        :required="true" :has-error="!!fmBooking.errors.diagnosa"/>
                                    <p v-if="fmBooking.errors.diagnosa" class="text-[10px] mt-1" style="color:#E07050">{{ fmBooking.errors.diagnosa }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">Rencana Tindakan <span style="color:#E07050">*</span></label>
                                    <input v-model="fmBooking.rencana_tindakan" required placeholder="Rencana tindakan ICU" class="w-full text-xs px-3 py-2 rounded-xl outline-none"
                                        style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">Keterangan Klinis</label>
                                    <textarea v-model="fmBooking.keterangan" rows="2" placeholder="Kondisi, riwayat, catatan dokter pengirim..."
                                        class="w-full text-xs px-3 py-2 rounded-xl outline-none resize-none"
                                        style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                                </div>
                            </div>
                        </div>
                        <!-- Jaminan -->
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest mb-2.5" style="color:var(--text-accent)">Jaminan</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">Jenis Jaminan <span style="color:#E07050">*</span></label>
                                    <select v-model="fmBooking.jaminan" required class="w-full text-xs px-3 py-2 rounded-xl outline-none"
                                        :style="`border:1px solid ${fmBooking.errors.jaminan?'#E07050':'var(--border-default)'}; background:var(--bg-surface); color:${fmBooking.jaminan?'var(--text-primary)':'var(--text-secondary)'}`">
                                        <option value="" disabled>-- Pilih Jaminan --</option>
                                        <option v-for="cb in caraBayar" :key="cb.kode" :value="cb.kode">{{ cb.nama }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">Catatan Jaminan</label>
                                    <input v-model="fmBooking.catatan_jaminan" placeholder="No. BPJS / No. Polis..." class="w-full text-xs px-3 py-2 rounded-xl outline-none"
                                        style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                                </div>
                            </div>
                        </div>
                        <!-- Actions -->
                        <div class="flex items-center gap-2 pt-2" style="border-top:1px solid var(--border-default)">
                            <button type="submit" :disabled="fmBooking.processing || !fmBooking.jenis_kelamin || !fmBooking.jaminan"
                                class="flex items-center justify-center flex-1 sm:flex-none gap-2 text-xs font-bold px-5 py-2.5 rounded-xl disabled:opacity-50"
                                style="background:#2DD9A4; color:#0D1A17">
                                <svg v-if="fmBooking.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                {{ fmBooking.processing ? 'Menyimpan...' : 'Kirim ke ICU' }}
                            </button>
                            <button type="button" @click="closeModal" class="px-5 py-2.5 text-xs rounded-xl"
                                style="background:var(--bg-main); color:var(--text-secondary); border:1px solid var(--border-default)">Batal</button>
                        </div>
                    </form>
                </div>
            </Transition>

            <!-- ── Modal: Approve SPRI ──────────────────────────────── -->
            <Transition enter-active-class="transition-all duration-200" enter-from-class="opacity-0 scale-95" leave-to-class="opacity-0 scale-95">
                <div v-if="modal.type==='approve'" class="w-full max-w-md rounded-2xl" style="background:var(--bg-sidebar); border:1px solid var(--border-default)">
                    <div class="flex items-center justify-between px-4 sm:px-5 py-4" style="border-bottom:1px solid var(--border-default)">
                        <div>
                            <p class="font-bold text-sm" style="color:var(--text-primary)">Setujui SPRI</p>
                            <p class="text-xs mt-0.5" style="color:var(--text-secondary)">{{ modal.item?.nama_pasien }}</p>
                        </div>
                        <button @click="closeModal" class="p-1.5 rounded-lg hover:brightness-110" style="background:var(--bg-input); color:var(--text-secondary)">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <!-- Info ringkas -->
                    <div class="px-4 sm:px-5 py-3 grid grid-cols-2 gap-2 text-xs" style="border-bottom:1px solid var(--border-default)">
                        <div class="rounded-lg p-2.5" style="background:var(--bg-input)">
                            <p style="color:var(--text-secondary)">No. MR</p>
                            <p class="font-mono font-semibold mt-0.5" style="color:var(--text-primary)">{{ modal.item?.No_MR }}</p>
                        </div>
                        <div class="rounded-lg p-2.5" style="background:var(--bg-input)">
                            <p style="color:var(--text-secondary)">Asal Ruang</p>
                            <p class="font-semibold mt-0.5" style="color:var(--text-primary)">{{ modal.item?.asal_rujukan ?? '-' }}</p>
                        </div>
                        <div class="rounded-lg p-2.5 col-span-2" style="background:var(--bg-input)">
                            <p style="color:var(--text-secondary)">Diagnosa</p>
                            <p class="font-semibold mt-0.5" style="color:var(--text-primary)">{{ modal.item?.diagnosa ?? '-' }}</p>
                        </div>
                    </div>
                    <form @submit.prevent="submitApprove" class="p-4 sm:p-5 space-y-4">
                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-primary)">Catatan Admisi <span class="font-normal" style="color:var(--text-secondary)">(opsional)</span></label>
                            <textarea v-model="fmApprove.catatan_admisi" rows="3" placeholder="Informasi jaminan, kondisi khusus, dll..."
                                class="w-full text-xs px-3 py-2.5 rounded-xl outline-none resize-none"
                                style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" :disabled="fmApprove.processing" class="flex-1 text-xs font-bold py-2.5 rounded-xl disabled:opacity-50"
                                style="background:#2DD9A4; color:#0D1A17">
                                {{ fmApprove.processing ? 'Menyimpan...' : '✓ Setujui' }}
                            </button>
                            <button type="button" @click="closeModal" class="px-4 text-xs rounded-xl"
                                style="background:var(--bg-main); color:var(--text-secondary); border:1px solid var(--border-default)">Batal</button>
                        </div>
                    </form>
                </div>
            </Transition>

            <!-- ── Modal: Tolak SPRI ────────────────────────────────── -->
            <Transition enter-active-class="transition-all duration-200" enter-from-class="opacity-0 scale-95" leave-to-class="opacity-0 scale-95">
                <div v-if="modal.type==='tolak'" class="w-full max-w-sm rounded-2xl" style="background:var(--bg-sidebar); border:1px solid var(--border-default)">
                    <div class="flex items-center justify-between px-4 sm:px-5 py-4" style="border-bottom:1px solid var(--border-default)">
                        <div>
                            <p class="font-bold text-sm" style="color:#E07050">Tolak Permintaan</p>
                            <p class="text-xs mt-0.5" style="color:var(--text-secondary)">{{ modal.item?.nama_pasien }}</p>
                        </div>
                        <button @click="closeModal" class="p-1.5 rounded-lg hover:brightness-110" style="background:var(--bg-input); color:var(--text-secondary)">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <form @submit.prevent="submitTolak" class="p-4 sm:p-5 space-y-4">
                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-primary)">Alasan Penolakan <span style="color:#E07050">*</span></label>
                            <textarea v-model="fmTolak.alasan_tolak" required rows="3" placeholder="Alasan penolakan SPRI..."
                                class="w-full text-xs px-3 py-2.5 rounded-xl outline-none resize-none"
                                style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                            <p v-if="fmTolak.errors.alasan_tolak" class="text-[10px] mt-1" style="color:#E07050">{{ fmTolak.errors.alasan_tolak }}</p>
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" :disabled="fmTolak.processing || !fmTolak.alasan_tolak.trim()"
                                class="flex-1 text-xs font-bold py-2.5 rounded-xl disabled:opacity-40"
                                style="background:rgba(224,112,80,.12); color:#E07050; border:1px solid rgba(224,112,80,.3)">
                                {{ fmTolak.processing ? 'Menyimpan...' : '✕ Tolak' }}
                            </button>
                            <button type="button" @click="closeModal" class="px-4 text-xs rounded-xl"
                                style="background:var(--bg-main); color:var(--text-secondary); border:1px solid var(--border-default)">Batal</button>
                        </div>
                    </form>
                </div>
            </Transition>

            <!-- ── Modal: Verifikasi Pasien ──────────────────────────── -->
            <Transition enter-active-class="transition-all duration-200" enter-from-class="opacity-0 scale-95" leave-to-class="opacity-0 scale-95">
                <div v-if="modal.type==='verifikasi'" class="w-full max-w-md rounded-2xl" style="background:var(--bg-sidebar); border:1px solid var(--border-default)">
                    <div class="flex items-center justify-between px-4 sm:px-5 py-4" style="border-bottom:1px solid var(--border-default)">
                        <div>
                            <p class="font-bold text-sm" style="color:var(--text-primary)">Verifikasi Pasien Tiba</p>
                            <p class="text-[11px] sm:text-xs mt-0.5" style="color:var(--text-secondary)">
                                {{ modal.item?.nama_pasien }} · Bed: <span style="color:#2DD9A4">{{ modal.item?.nama_bed }}</span>
                            </p>
                        </div>
                        <button @click="closeModal" class="p-1.5 rounded-lg hover:brightness-110" style="background:var(--bg-input); color:var(--text-secondary)">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <!-- Info booking ringkas -->
                    <div class="px-4 sm:px-5 py-3 grid grid-cols-2 gap-2 text-xs" style="border-bottom:1px solid var(--border-default)">
                        <div class="rounded-lg p-2.5" style="background:var(--bg-input)">
                            <p style="color:var(--text-secondary)">Nama Booking</p>
                            <p class="font-semibold mt-0.5" style="color:var(--text-primary)">{{ modal.item?.nama_pasien }}</p>
                        </div>
                        <div class="rounded-lg p-2.5" style="background:var(--bg-input)">
                            <p style="color:var(--text-secondary)">Diagnosa</p>
                            <p class="font-semibold mt-0.5 truncate" :title="modal.item?.diagnosa" style="color:var(--text-primary)">{{ modal.item?.diagnosa ?? '-' }}</p>
                        </div>
                    </div>

                    <form @submit.prevent="submitVerif" class="p-4 sm:p-5 space-y-3">
                        <p class="text-xs" style="color:var(--text-secondary)">
                            Cari No. MR untuk memverifikasi kedatangan.
                        </p>

                        <!-- Input No. MR + Tombol Cari -->
                        <div>
                            <div class="flex gap-2">
                                <input v-model="fmVerif.No_MR" @keydown.enter.prevent="doVerifLookup"
                                    placeholder="Masukkan No. MR..."
                                    class="flex-1 text-xs px-3 py-2.5 rounded-xl outline-none font-mono"
                                    :style="`border:1px solid ${verifLookupError ? '#E07050' : verifLookupResult?.found ? '#2DD9A4' : 'var(--border-default)'}; background:var(--bg-surface); color:var(--text-primary)`"/>
                                <button type="button" @click="doVerifLookup"
                                    :disabled="verifLookupLoading || fmVerif.No_MR.length < 3"
                                    class="flex items-center gap-1.5 text-xs font-semibold px-3 py-2.5 rounded-xl disabled:opacity-40"
                                    style="background:rgba(45,217,164,.15); color:#2DD9A4; border:1px solid rgba(45,217,164,.3); white-space:nowrap">
                                    <svg v-if="verifLookupLoading" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                    <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                    Cari
                                </button>
                            </div>
                            <p v-if="fmVerif.errors.No_MR" class="text-[10px] mt-1" style="color:#E07050">{{ fmVerif.errors.No_MR }}</p>
                        </div>

                        <!-- Error lookup -->
                        <div v-if="verifLookupError" class="text-xs px-3 py-2 rounded-xl"
                            style="background:rgba(224,112,80,.08); color:#E07050; border:1px solid rgba(224,112,80,.2)">
                            ⚠ {{ verifLookupError }}
                        </div>

                        <!-- Hasil lookup ditemukan -->
                        <div v-if="verifLookupResult?.found" class="px-3 py-2.5 rounded-xl text-xs"
                            style="background:rgba(45,217,164,.08); border:1px solid rgba(45,217,164,.2)">
                            <div class="flex items-center gap-2">
                                <span style="color:#2DD9A4; font-size:14px">✓</span>
                                <div>
                                    <p class="font-bold" style="color:var(--text-primary)">{{ verifLookupResult.nama_pasien }}</p>
                                    <p class="font-mono text-[10px] mt-0.5" style="color:var(--text-secondary)">{{ verifLookupResult.No_MR }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Pilih kunjungan jika ada lebih dari 1 -->
                        <div v-if="verifKunjungans.length > 1">
                            <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-primary)">
                                Pilih No. Reg Kunjungan <span style="color:#E07050">*</span>
                            </label>
                            <div class="space-y-1.5 max-h-36 overflow-y-auto pr-1">
                                <button v-for="k in verifKunjungans" :key="k.No_Reg" type="button"
                                    @click="fmVerif.No_Reg = k.No_Reg"
                                    class="w-full text-left px-3 py-2 rounded-lg text-xs transition-all"
                                    :style="fmVerif.No_Reg === k.No_Reg
                                        ? 'background:rgba(45,217,164,.12); border:1.5px solid rgba(45,217,164,.4); color:var(--text-primary)'
                                        : 'background:var(--bg-input); border:1px solid var(--border-default); color:var(--text-secondary)'">
                                    <span class="font-mono font-semibold">{{ k.No_Reg }}</span>
                                    <span class="ml-2 text-[10px]">· {{ k.nama_ruang || k.Kode_Masuk || '-' }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- No. Reg (auto-fill jika 1 kunjungan) -->
                        <div v-if="verifKunjungans.length === 1 && fmVerif.No_Reg" class="text-xs" style="color:var(--text-secondary)">
                            No. Reg: <span class="font-mono font-semibold" style="color:var(--text-primary)">{{ fmVerif.No_Reg }}</span>
                        </div>

                        <div v-if="verifKunjungans.length === 0 && verifLookupResult?.found">
                            <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-primary)">
                                No. Reg <span class="font-normal" style="color:var(--text-secondary)">(opsional)</span>
                            </label>
                            <input v-model="fmVerif.No_Reg" placeholder="No. Reg kunjungan"
                                class="w-full text-xs px-3 py-2.5 rounded-xl outline-none font-mono"
                                style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                        </div>

                        <!-- Tombol aksi -->
                        <div class="flex gap-2 pt-1">
                            <button type="submit"
                                :disabled="fmVerif.processing || !fmVerif.No_MR.trim() || !verifLookupResult?.found"
                                class="flex-1 text-xs font-bold py-2.5 rounded-xl disabled:opacity-40"
                                style="background:#2DD9A4; color:#0D1A17">
                                {{ fmVerif.processing ? 'Menyimpan...' : '✓ Verifikasi' }}
                            </button>
                            <button type="button" @click="closeModal" class="px-4 text-xs rounded-xl"
                                style="background:var(--bg-main); color:var(--text-secondary); border:1px solid var(--border-default)">Batal</button>
                        </div>
                    </form>
                </div>
            </Transition>

        </div>
    </Transition>

</AppLayout>
</template>