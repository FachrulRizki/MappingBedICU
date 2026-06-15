<script setup>
import { ref, computed, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useAuth } from '@/composables/useAuth.js';

const { canKonfirmasiIcu, isAdmin } = useAuth();

const props = defineProps({
    antrian:     { type: Array,  default: () => [] },
    summary:     { type: Object, default: () => ({}) },
    filters:     { type: Object, default: () => ({}) },
    kamarKosong: { type: Array,  default: () => [] },
    masterKelas: { type: Array,  default: () => [] },
    flash:       { type: Object, default: () => ({}) },
});

// ── Toast ──────────────────────────────────────────────────
const toast = ref(null);
watch(() => props.flash, (f) => {
    if (f?.success) toast.value = { type:'success', msg: f.success };
    if (f?.error)   toast.value = { type:'error',   msg: f.error   };
    if (toast.value) setTimeout(() => toast.value = null, 4000);
}, { immediate: true, deep: true });

// ── Filters ────────────────────────────────────────────────
const fStatus = ref(props.filters.filterStatus ?? '');
const fJenis  = ref(props.filters.filterJenis  ?? '');
const fNama   = ref(props.filters.filterNama   ?? '');
const fTgl    = ref(props.filters.filterTgl    ?? '');
const sortBy  = ref(props.filters.sortBy       ?? 'created_at');
const sortDir = ref(props.filters.sortDir      ?? 'asc');

let st = null;
const applyFilters = () => router.get(route('icu.menu_icu'), {
    status: fStatus.value, jenis: fJenis.value,
    nama: fNama.value, tgl: fTgl.value,
    sort: sortBy.value, dir: sortDir.value,
}, { preserveState: true, replace: true });
const onNamaInput = () => { clearTimeout(st); st = setTimeout(applyFilters, 400); };
const toggleSort  = (col) => {
    sortDir.value = sortBy.value === col ? (sortDir.value==='asc'?'desc':'asc') : 'asc';
    sortBy.value = col; applyFilters();
};
const resetFilter = () => { fStatus.value=''; fJenis.value=''; fNama.value=''; fTgl.value=''; applyFilters(); };
const sortIcon = (col) => sortBy.value!==col?'↕':sortDir.value==='asc'?'↑':'↓';

// ── Styles ─────────────────────────────────────────────────
const SS = {
    pending_icu:     { bg:'rgba(224,146,58,.15)', color:'#E0923A', dot:'#E0923A' },
    pending_admisi:  { bg:'rgba(245,166,35,.15)', color:'#F5A623', dot:'#F5A623' },
    bed_confirmed:   { bg:'rgba(74,144,217,.15)', color:'#4A90D9', dot:'#4A90D9' },
    bed_verified:    { bg:'rgba(45,217,164,.15)', color:'#2DD9A4', dot:'#2DD9A4' },
    admisi_verified: { bg:'rgba(45,217,164,.15)', color:'#2DD9A4', dot:'#2DD9A4' },
    ditolak:         { bg:'rgba(224,112,80,.15)', color:'#E07050', dot:'#E07050' },
};
const ss = (s) => SS[s] ?? { bg:'var(--bg-input)', color:'var(--text-secondary)', dot:'#888' };
const SRC = {
    external: { bg:'rgba(74,144,217,.12)', color:'#4A90D9' },
    internal: { bg:'rgba(142,168,158,.12)', color:'#8EA89E' },
};
const gIcon  = (g) => g==='L'?'♂':g==='P'?'♀':'·';
const gColor = (g) => g==='L'?'#4A90D9':g==='P'?'#D9517A':'var(--text-secondary)';

const canAct = (item) => {
    if (!canKonfirmasiIcu.value && !isAdmin.value) return false;
    return item.status === 'pending_icu';
};

// ── Summary ────────────────────────────────────────────────
const CARDS = computed(() => [
    { key:'',           label:'Total',        val: props.summary.total         ?? 0, color:'#8EA89E' },
    { key:'pending_icu',label:'Menunggu ICU', val: props.antrian.filter(a=>a.status==='pending_icu').length, color:'#E0923A' },
    { key:'bed_confirmed',label:'Dikonfirmasi',val: props.summary.bed_confirmed ?? 0, color:'#4A90D9' },
    { key:'bed_verified,admisi_verified',label:'Terverifikasi',val: props.summary.verified ?? 0, color:'#2DD9A4' },
    { key:'ditolak',    label:'Ditolak',      val: props.summary.ditolak       ?? 0, color:'#E07050' },
]);

const clickCard = (key) => {
    fStatus.value = key.includes(',') ? '' : key;
    applyFilters();
};

// ── Modal ──────────────────────────────────────────────────
const modal = ref({ open: false, type:'', item: null });
const openModal = (type, item) => {
    fmKonfirmasi.reset(); fmTolak.reset();
    modal.value = { open:true, type, item };
};
const closeModal = () => { modal.value.open = false; setTimeout(() => modal.value = {open:false,type:'',item:null}, 200); };

// ── Form: Konfirmasi Bed ───────────────────────────────────
const fmKonfirmasi = useForm({ Kode_Ruang:'', kebutuhan_bed:'' });
const bedCocok = computed(() =>
    fmKonfirmasi.kebutuhan_bed
        ? props.kamarKosong.filter(b => b.nama_kelas === fmKonfirmasi.kebutuhan_bed)
        : props.kamarKosong
);
const submitKonfirmasi = () => {
    const route_name = modal.value.item?.sumber === 'external'
        ? 'icu.menu_icu.ext.konfirmasi'
        : 'icu.menu_icu.int.verifikasi';
    fmKonfirmasi.post(route(route_name, modal.value.item.id), {
        onSuccess: closeModal,
    });
};

// ── Form: Tolak ────────────────────────────────────────────
const fmTolak = useForm({ alasan_tolak:'' });
const submitTolak = () => {
    const route_name = modal.value.item?.sumber === 'external'
        ? 'icu.menu_icu.ext.tolak'
        : 'icu.menu_icu.int.tolak';
    fmTolak.post(route(route_name, modal.value.item.id), {
        onSuccess: closeModal,
    });
};

const statusOptions = [
    { value:'', label:'Semua Status' },
    { value:'pending_icu',     label:'Menunggu ICU' },
    { value:'pending_admisi',  label:'Menunggu Admisi' },
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
<AppLayout :flash="flash" page-title="Menu ICU">

    <!-- Toast -->
    <Transition enter-active-class="transition-all duration-300" enter-from-class="opacity-0 -translate-y-2" leave-to-class="opacity-0 -translate-y-2">
        <div v-if="toast" class="fixed top-4 right-4 z-[100] flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold shadow-xl"
            :style="toast.type==='success' ? 'background:#2DD9A4; color:#0D1A17' : 'background:#E07050; color:#fff'">
            {{ toast.type==='success' ? '✓' : '✕' }} {{ toast.msg }}
        </div>
    </Transition>

    <div class="p-4 sm:p-6 space-y-4" style="font-family:'Plus Jakarta Sans',sans-serif">

        <!-- Header -->
        <div>
            <h1 class="font-bold text-xl" style="color:var(--text-primary)">Menu ICU</h1>
            <p class="text-xs mt-0.5" style="color:var(--text-secondary)">Semua antrian — Booking Eksternal & SPRI Internal</p>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <button v-for="c in CARDS" :key="c.key" @click="clickCard(c.key)"
                class="card-dark p-3.5 flex items-center gap-3 text-left transition-all hover:scale-[1.02]"
                :style="fStatus===c.key ? `border:1.5px solid ${c.color}50` : ''">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0" :style="`background:${c.color}1A`">
                    <span class="text-lg font-bold" :style="`color:${c.color}`">{{ c.val }}</span>
                </div>
                <p class="text-[11px] leading-tight" style="color:var(--text-secondary)">{{ c.label }}</p>
            </button>
        </div>

        <div class="flex items-center gap-4 text-xs" style="color:var(--text-secondary)">
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full inline-block" style="background:#4A90D9"></span>Booking Eksternal: <strong style="color:var(--text-primary)">{{ summary.by_sumber?.external ?? 0 }}</strong></span>
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full inline-block" style="background:#8EA89E"></span>SPRI Internal: <strong style="color:var(--text-primary)">{{ summary.by_sumber?.internal ?? 0 }}</strong></span>
        </div>

        <!-- Filter -->
        <div class="card-dark p-3.5 space-y-3">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
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
                    <input v-model="fNama" @input="onNamaInput" placeholder="Cari..." class="w-full text-xs px-2.5 py-2 rounded-lg outline-none"
                        style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                </div>
                <div>
                    <label class="block text-[10px] font-semibold mb-1" style="color:var(--text-secondary)">Tanggal</label>
                    <input v-model="fTgl" @change="applyFilters" type="date" class="w-full text-xs px-2.5 py-2 rounded-lg outline-none"
                        style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-[10px]" style="color:var(--text-muted)">Urutkan:</span>
                <button v-for="col in [{key:'created_at',label:'Waktu'},{key:'nama_pasien',label:'Nama'},{key:'status',label:'Status'}]"
                    :key="col.key" @click="toggleSort(col.key)"
                    class="text-[10px] px-2 py-1 rounded-lg font-semibold"
                    :style="sortBy===col.key ? 'background:rgba(45,217,164,.15); color:#2DD9A4; border:1px solid rgba(45,217,164,.3)' : 'background:var(--bg-input); color:var(--text-secondary); border:1px solid var(--border-default)'">
                    {{ col.label }} {{ sortIcon(col.key) }}
                </button>
                <button v-if="fStatus||fJenis||fNama||fTgl" @click="resetFilter"
                    class="text-[10px] px-2 py-1 rounded-lg ml-auto"
                    style="background:rgba(224,112,80,.1); color:#E07050; border:1px solid rgba(224,112,80,.2)">✕ Reset</button>
            </div>
        </div>

        <!-- Empty -->
        <div v-if="!antrian.length" class="card-dark text-center py-14">
            <p class="text-sm font-semibold" style="color:var(--text-secondary)">Tidak ada antrian</p>
        </div>

        <!-- Tabel -->
        <div v-else class="card-dark overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-xs" style="border-collapse:collapse; min-width:780px">
                    <thead>
                        <tr style="background:var(--bg-main); border-bottom:2px solid var(--border-default)">
                            <th class="px-3 py-2.5 text-left font-semibold w-7" style="color:var(--text-secondary)">#</th>
                            <th class="px-3 py-2.5 text-left font-semibold cursor-pointer" style="color:var(--text-secondary)" @click="toggleSort('nama_pasien')">Pasien {{ sortIcon('nama_pasien') }}</th>
                            <th class="px-3 py-2.5 text-left font-semibold" style="color:var(--text-secondary)">Jenis</th>
                            <th class="px-3 py-2.5 text-left font-semibold" style="color:var(--text-secondary)">Diagnosa</th>
                            <th class="px-3 py-2.5 text-left font-semibold" style="color:var(--text-secondary)">Asal</th>
                            <th class="px-3 py-2.5 text-left font-semibold" style="color:var(--text-secondary)">Bed</th>
                            <th class="px-3 py-2.5 text-left font-semibold cursor-pointer" style="color:var(--text-secondary)" @click="toggleSort('status')">Status {{ sortIcon('status') }}</th>
                            <th class="px-3 py-2.5 text-left font-semibold cursor-pointer" style="color:var(--text-secondary)" @click="toggleSort('created_at')">Waktu {{ sortIcon('created_at') }}</th>
                            <th class="px-3 py-2.5 text-left font-semibold" style="color:var(--text-secondary)">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, idx) in antrian" :key="`${item.sumber}-${item.id}`"
                            :style="`border-bottom:1px solid var(--border-default); border-left:3px solid ${ss(item.status).dot}`"
                            class="transition-colors hover:bg-[var(--bg-surface)]">
                            <td class="px-3 py-2.5 font-mono" style="color:var(--text-muted)">{{ idx+1 }}</td>
                            <td class="px-3 py-2.5">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-bold" :style="`color:${gColor(item.jenis_kelamin)}`">{{ gIcon(item.jenis_kelamin) }}</span>
                                    <div>
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
                            <td class="px-3 py-2.5" style="max-width:150px">
                                <p class="truncate" :title="item.diagnosa" style="color:var(--text-primary)">{{ item.diagnosa ?? '-' }}</p>
                                <p v-if="item.kebutuhan_bed" class="text-[10px]" style="color:#2DD9A4">{{ item.kebutuhan_bed }}</p>
                            </td>
                            <td class="px-3 py-2.5" style="max-width:90px">
                                <p class="truncate text-[11px]" style="color:var(--text-secondary)">{{ item.asal_rujukan ?? '-' }}</p>
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
                                <p v-if="item.alasan_tolak" class="text-[10px] mt-1 max-w-[100px] truncate" style="color:#E07050">{{ item.alasan_tolak }}</p>
                            </td>
                            <td class="px-3 py-2.5 font-mono whitespace-nowrap" style="color:var(--text-secondary)">
                                {{ item.created_at_fmt }}
                            </td>
                            <td class="px-3 py-2.5">
                                <div class="flex gap-1.5" v-if="canAct(item)">
                                    <button @click="openModal('konfirmasi', item)"
                                        class="text-[10px] font-bold px-2.5 py-1.5 rounded-lg whitespace-nowrap"
                                        style="background:rgba(45,217,164,.12); color:#2DD9A4; border:1px solid rgba(45,217,164,.3)">
                                        ✓ Konfirmasi Bed
                                    </button>
                                    <button @click="openModal('tolak', item)"
                                        class="text-[10px] font-bold px-2.5 py-1.5 rounded-lg whitespace-nowrap"
                                        style="background:rgba(224,112,80,.08); color:#E07050; border:1px solid rgba(224,112,80,.25)">
                                        ✕ Tolak
                                    </button>
                                </div>
                                <span v-else class="text-[10px]" style="color:var(--text-muted)">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-2" style="border-top:1px solid var(--border-default)">
                <p class="text-[11px]" style="color:var(--text-secondary)">
                    Menampilkan <strong style="color:var(--text-primary)">{{ antrian.length }}</strong> data
                </p>
            </div>
        </div>
    </div>

    <!-- ═══════════════ MODALS ════════════════════════════════════════════ -->
    <Transition enter-active-class="transition-all duration-200" enter-from-class="opacity-0" leave-to-class="opacity-0">
        <div v-if="modal.open" class="fixed inset-0 z-50 flex items-center justify-center p-4"
            style="background:rgba(0,0,0,0.6); backdrop-filter:blur(3px)" @click.self="closeModal">

            <!-- Modal Konfirmasi Bed -->
            <Transition enter-active-class="transition-all duration-200" enter-from-class="opacity-0 scale-95" leave-to-class="opacity-0 scale-95">
                <div v-if="modal.type==='konfirmasi'" class="w-full max-w-md rounded-2xl" style="background:var(--bg-sidebar); border:1px solid var(--border-default)">
                    <div class="flex items-center justify-between px-5 py-4" style="border-bottom:1px solid var(--border-default)">
                        <div>
                            <p class="font-bold text-sm" style="color:var(--text-primary)">
                                {{ modal.item?.sumber==='external' ? 'Konfirmasi Bed' : 'Verifikasi Bed' }}
                            </p>
                            <p class="text-xs mt-0.5" style="color:var(--text-secondary)">{{ modal.item?.nama_pasien }}</p>
                        </div>
                        <button @click="closeModal" class="p-1.5 rounded-lg" style="background:var(--bg-input); color:var(--text-secondary)">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <!-- Info pasien -->
                    <div class="px-5 py-3 grid grid-cols-2 gap-2 text-xs" style="border-bottom:1px solid var(--border-default)">
                        <div class="rounded-lg p-2.5" style="background:var(--bg-input)">
                            <p style="color:var(--text-secondary)">No. MR</p>
                            <p class="font-mono font-semibold mt-0.5" style="color:var(--text-primary)">{{ modal.item?.No_MR ?? '—' }}</p>
                        </div>
                        <div class="rounded-lg p-2.5" style="background:var(--bg-input)">
                            <p style="color:var(--text-secondary)">Asal</p>
                            <p class="font-semibold mt-0.5" style="color:var(--text-primary)">{{ modal.item?.asal_rujukan ?? '-' }}</p>
                        </div>
                        <div class="rounded-lg p-2.5 col-span-2" style="background:var(--bg-input)">
                            <p style="color:var(--text-secondary)">Diagnosa</p>
                            <p class="font-semibold mt-0.5" style="color:var(--text-primary)">{{ modal.item?.diagnosa ?? '-' }}</p>
                        </div>
                    </div>
                    <form @submit.prevent="submitKonfirmasi" class="p-5 space-y-3">
                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-primary)">Jenis ICU <span style="color:#E07050">*</span></label>
                            <select v-model="fmKonfirmasi.kebutuhan_bed" required class="w-full text-xs px-3 py-2.5 rounded-xl outline-none"
                                style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)">
                                <option value="" disabled>-- Pilih Jenis ICU --</option>
                                <option v-for="k in masterKelas" :key="k.kode" :value="k.nama">{{ k.nama }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-primary)">
                                Pilih Bed <span style="color:#E07050">*</span>
                                <span class="font-normal ml-1" style="color:var(--text-muted)">({{ bedCocok.length }} tersedia)</span>
                            </label>
                            <select v-model="fmKonfirmasi.Kode_Ruang" required :disabled="!bedCocok.length"
                                class="w-full text-xs px-3 py-2.5 rounded-xl outline-none disabled:opacity-40"
                                style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)">
                                <option value="" disabled>-- Pilih Bed --</option>
                                <option v-for="bed in bedCocok" :key="bed.Kode_Ruang" :value="bed.Kode_Ruang">{{ bed.nama_ruang }}</option>
                            </select>
                            <p v-if="!kamarKosong.length" class="text-[10px] mt-1" style="color:#E0923A">Tidak ada bed kosong saat ini</p>
                        </div>
                        <div class="flex gap-2 pt-1">
                            <button type="submit" :disabled="fmKonfirmasi.processing || !fmKonfirmasi.Kode_Ruang || !fmKonfirmasi.kebutuhan_bed"
                                class="flex-1 text-xs font-bold py-2.5 rounded-xl disabled:opacity-40"
                                style="background:#2DD9A4; color:#0D1A17">
                                {{ fmKonfirmasi.processing ? 'Menyimpan...' : '✓ Konfirmasi Bed' }}
                            </button>
                            <button type="button" @click="closeModal" class="px-4 text-xs rounded-xl"
                                style="background:var(--bg-main); color:var(--text-secondary); border:1px solid var(--border-default)">Batal</button>
                        </div>
                    </form>
                </div>
            </Transition>

            <!-- Modal Tolak -->
            <Transition enter-active-class="transition-all duration-200" enter-from-class="opacity-0 scale-95" leave-to-class="opacity-0 scale-95">
                <div v-if="modal.type==='tolak'" class="w-full max-w-sm rounded-2xl" style="background:var(--bg-sidebar); border:1px solid var(--border-default)">
                    <div class="flex items-center justify-between px-5 py-4" style="border-bottom:1px solid var(--border-default)">
                        <div>
                            <p class="font-bold text-sm" style="color:#E07050">Tolak Permintaan</p>
                            <p class="text-xs mt-0.5" style="color:var(--text-secondary)">{{ modal.item?.nama_pasien }}</p>
                        </div>
                        <button @click="closeModal" class="p-1.5 rounded-lg" style="background:var(--bg-input); color:var(--text-secondary)">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <form @submit.prevent="submitTolak" class="p-5 space-y-4">
                        <div>
                            <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-primary)">Alasan Penolakan <span style="color:#E07050">*</span></label>
                            <textarea v-model="fmTolak.alasan_tolak" required rows="3" placeholder="Alasan penolakan..."
                                class="w-full text-xs px-3 py-2.5 rounded-xl outline-none resize-none"
                                style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
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

        </div>
    </Transition>

</AppLayout>
</template>
