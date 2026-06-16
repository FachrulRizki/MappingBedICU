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

// Flash ditangani oleh FlashMessage global di AppLayout


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
    pending_icu:     { bg:'#FDF3E9', color:'#E67E22', dot:'#E67E22' },
    pending_admisi:  { bg:'rgba(245,166,35,.15)', color:'#E67E22', dot:'#E67E22' },
    bed_confirmed:   { bg:'#EAF4FB', color:'#3498DB', dot:'#3498DB' },
    bed_verified:    { bg:'#EBF9F1', color:'#27AE60', dot:'#27AE60' },
    admisi_verified: { bg:'#EBF9F1', color:'#27AE60', dot:'#27AE60' },
    ditolak:         { bg:'#FDEDEC', color:'#E74C3C', dot:'#E74C3C' },
};
const ss = (s) => SS[s] ?? { bg:'var(--bg-input)', color:'var(--text-secondary)', dot:'#888' };
const SRC = {
    external: { bg:'rgba(52,152,219,.12)', color:'#3498DB' },
    internal: { bg:'rgba(90,107,124,.12)', color:'#5A6B7C' },
};
const gIcon  = (g) => g==='L'?'♂':g==='P'?'♀':'·';
const gColor = (g) => g==='L'?'#3498DB':g==='P'?'#8E44AD':'var(--text-secondary)';

// ── Aksi yang tersedia per item ────────────────────────────
const actionsOf = (item) => {
    if (!canKonfirmasiIcu.value && !isAdmin.value) return [];
    const acts = [];
    if (item.status === 'pending_icu') {
        const labelKonfirm = item.sumber === 'external' ? 'Konfirmasi Bed' : 'Verifikasi Bed';
        acts.push({ id:'konfirmasi', label: labelKonfirm, bg:'rgba(0,168,132,.12)', color:'#00A884', border:'rgba(0,168,132,.3)' });
        acts.push({ id:'tolak', label:'Tolak Permintaan', bg:'rgba(231,76,60,.08)', color:'#E74C3C', border:'rgba(231,76,60,.25)' });
    }
    return acts;
};

// ── Summary ────────────────────────────────────────────────
const CARDS = computed(() => [
    { key:'',           label:'Total',        val: props.summary.total         ?? 0, color:'#5A6B7C' },
    { key:'pending_icu',label:'Menunggu ICU', val: props.antrian.filter(a=>a.status==='pending_icu').length, color:'#E67E22' },
    { key:'bed_confirmed',label:'Dikonfirmasi',val: props.summary.bed_confirmed ?? 0, color:'#3498DB' },
    { key:'bed_verified,admisi_verified',label:'Terverifikasi',val: props.summary.verified ?? 0, color:'#00A884' },
    { key:'ditolak',    label:'Ditolak',      val: props.summary.ditolak       ?? 0, color:'#E74C3C' },
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
const closeModal = () => { modal.value.open = false; setTimeout(() => modal.value = {open:false,type:'',item:null}, 300); };

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

  <div class="p-6 sm:p-8 space-y-6" style="font-family:'Inter','Plus Jakarta Sans',sans-serif; background:var(--bg-main); min-height:100%">

    <!-- ═══ 1. PAGE HEADER ═══════════════════════════════════════════════ -->
    <div class="space-y-1">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl flex items-center justify-center flex-shrink-0" style="background:rgba(0,168,132,.15)">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="#00A884" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
          </svg>
        </div>
        <div>
          <h1 class="text-2xl font-bold tracking-tight" style="color:var(--text-primary)">Menu ICU</h1>
          <p class="text-sm" style="color:var(--text-secondary)">Kelola semua antrian — Booking Eksternal &amp; SPRI Internal</p>
        </div>
      </div>
    </div>

    <!-- ═══ 2. KPI SUMMARY CARDS ════════════════════════════════════════ -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
      <button
        v-for="c in CARDS" :key="c.key"
        @click="clickCard(c.key)"
        class="group relative flex flex-col gap-2 p-5 rounded-2xl text-left transition-all duration-200 hover:-translate-y-1"
        style="background:var(--bg-card); border:1px solid var(--border-default); box-shadow:var(--shadow-card); min-height:100px"
        :style="fStatus===c.key ? `border:2.5px solid ${c.color}; box-shadow:0 0 0 3px ${c.color}18` : ''"
      >
        <!-- Dot indicator kiri -->
        <span class="absolute left-0 top-6 bottom-6 w-1 rounded-r-full transition-all"
          :style="`background:${c.color}; opacity:${fStatus===c.key?'1':'0.35'}`"></span>
        <span class="text-3xl font-bold tracking-tight" :style="`color:${c.color}`">{{ c.val }}</span>
        <span class="text-xs font-medium leading-tight" style="color:var(--text-secondary)">{{ c.label }}</span>
      </button>
    </div>

    <!-- ═══ 3. BREAKDOWN SUMBER ══════════════════════════════════════════ -->
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

    <!-- ═══ 4. FILTER BAR ════════════════════════════════════════════════ -->
    <div class="rounded-2xl p-5 sm:p-6 space-y-4" style="background:var(--bg-surface); border:1px solid var(--border-default); box-shadow:var(--shadow-card)">
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
        <div class="space-y-1.5">
          <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Status</label>
          <select v-model="fStatus" @change="applyFilters"
            class="w-full rounded-xl outline-none transition-all"
            style="padding:10px 14px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); font-size:13px">
            <option v-for="o in statusOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
        <div class="space-y-1.5">
          <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Jenis</label>
          <select v-model="fJenis" @change="applyFilters"
            class="w-full rounded-xl outline-none transition-all"
            style="padding:10px 14px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); font-size:13px">
            <option v-for="o in jenisOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
        </div>
        <div class="space-y-1.5">
          <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Nama / No. MR</label>
          <input v-model="fNama" @input="onNamaInput" placeholder="Cari pasien..."
            class="w-full rounded-xl outline-none transition-all"
            style="padding:10px 14px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); font-size:13px"/>
        </div>
        <div class="space-y-1.5">
          <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Tanggal</label>
          <input v-model="fTgl" @change="applyFilters" type="date"
            class="w-full rounded-xl outline-none transition-all"
            style="padding:10px 14px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); font-size:13px"/>
        </div>
      </div>
      <div class="flex items-center gap-2.5 flex-wrap">
        <span class="text-xs font-semibold" style="color:var(--text-muted)">Urutkan:</span>
        <button v-for="col in [{key:'created_at',label:'Waktu'},{key:'nama_pasien',label:'Nama'},{key:'status',label:'Status'}]"
          :key="col.key" @click="toggleSort(col.key)"
          class="text-xs font-semibold px-3.5 py-2 rounded-xl transition-all duration-150 hover:-translate-y-px"
          :style="sortBy===col.key
            ? 'background:rgba(0,168,132,.15); color:#00A884; border:1.5px solid rgba(0,168,132,.35)'
            : 'background:var(--bg-input); color:var(--text-secondary); border:1.5px solid var(--border-default)'">
          {{ col.label }} {{ sortIcon(col.key) }}
        </button>
        <button v-if="fStatus||fJenis||fNama||fTgl" @click="resetFilter"
          class="ml-auto text-xs font-semibold px-3.5 py-2 rounded-xl transition-all duration-150 hover:-translate-y-px flex items-center gap-1.5"
          style="background:rgba(231,76,60,.1); color:#E74C3C; border:1.5px solid rgba(231,76,60,.25)">
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
          </svg>
          Reset Filter
        </button>
      </div>
    </div>

    <!-- ═══ 5. EMPTY STATE ═══════════════════════════════════════════════ -->
    <div v-if="!antrian.length" class="rounded-2xl flex flex-col items-center justify-center py-20 px-8 text-center"
      style="background:var(--bg-surface); border:1px solid var(--border-default); box-shadow:var(--shadow-card)">
      <div class="w-20 h-20 rounded-3xl flex items-center justify-center mb-5"
        style="background:rgba(0,168,132,.1); border:1.5px dashed rgba(0,168,132,.3)">
        <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="#00A884" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
        </svg>
      </div>
      <p class="text-base font-bold mb-1.5" style="color:var(--text-primary)">Tidak Ada Antrian Ditemukan</p>
      <p class="text-sm max-w-xs" style="color:var(--text-muted)">Belum ada data antrian yang sesuai dengan filter yang dipilih. Coba ubah kriteria pencarian.</p>
    </div>

    <!-- ═══ 6 & 7. CONTENT AREA ══════════════════════════════════════════ -->
    <div v-else class="rounded-2xl overflow-hidden"
      style="background:var(--bg-surface); border:1px solid var(--border-default); box-shadow:var(--shadow-card)">

      <!-- ── 6. TABEL DESKTOP ──────────────────────────────────────────── -->
      <div class="hidden overflow-x-auto" style="display:none" :class="''" v-show="true">
        <!-- wrapper untuk media query via inline, gunakan class hidden/block -->
      </div>
      <div class="hidden md:block overflow-x-auto">
        <table class="w-full" style="border-collapse:collapse; min-width:900px; font-size:13px; font-family:'Inter','Plus Jakarta Sans',sans-serif">
          <thead>
            <tr style="background:var(--table-th-bg); border-bottom:2px solid var(--border-default)">
              <th class="py-3.5 px-5 text-left font-semibold text-xs uppercase tracking-wider w-10" style="color:var(--table-th-color)">#</th>
              <th class="py-3.5 px-5 text-left font-semibold text-xs uppercase tracking-wider cursor-pointer select-none" style="color:var(--table-th-color)" @click="toggleSort('nama_pasien')">
                <span class="flex items-center gap-1.5">Pasien <span class="opacity-60">{{ sortIcon('nama_pasien') }}</span></span>
              </th>
              <th class="py-3.5 px-5 text-left font-semibold text-xs uppercase tracking-wider" style="color:var(--table-th-color)">Jenis</th>
              <th class="py-3.5 px-5 text-left font-semibold text-xs uppercase tracking-wider" style="color:var(--table-th-color)">Diagnosa</th>
              <th class="py-3.5 px-5 text-left font-semibold text-xs uppercase tracking-wider" style="color:var(--table-th-color)">Asal</th>
              <th class="py-3.5 px-5 text-left font-semibold text-xs uppercase tracking-wider" style="color:var(--table-th-color)">Bed</th>
              <th class="py-3.5 px-5 text-left font-semibold text-xs uppercase tracking-wider cursor-pointer select-none" style="color:var(--table-th-color)" @click="toggleSort('status')">
                <span class="flex items-center gap-1.5">Status <span class="opacity-60">{{ sortIcon('status') }}</span></span>
              </th>
              <th class="py-3.5 px-5 text-left font-semibold text-xs uppercase tracking-wider cursor-pointer select-none" style="color:var(--table-th-color)" @click="toggleSort('created_at')">
                <span class="flex items-center gap-1.5">Waktu <span class="opacity-60">{{ sortIcon('created_at') }}</span></span>
              </th>
              <th class="py-3.5 px-5 text-center w-10"></th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(item, idx) in antrian" :key="`${item.sumber}-${item.id}`"
              @click="openModal('detail', item)"
              class="group cursor-pointer transition-all duration-150"
              style="border-bottom:1px solid var(--border-row)"
              :style="`border-left:4px solid ${ss(item.status).dot}`"
              onmouseenter="this.style.transform='translateY(-1px)'; this.style.background='var(--bg-row-hover)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.06)'"
              onmouseleave="this.style.transform=''; this.style.background=''; this.style.boxShadow=''"
            >
              <td class="px-5 py-4 font-mono text-xs" style="color:var(--text-muted)">{{ idx+1 }}</td>
              <td class="px-5 py-4">
                <div class="flex items-center gap-3">
                  <div class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 text-sm font-bold"
                    :style="`background:${gColor(item.jenis_kelamin)}18; color:${gColor(item.jenis_kelamin)}`">
                    {{ gIcon(item.jenis_kelamin) }}
                  </div>
                  <div class="min-w-0">
                    <p class="font-semibold truncate" style="color:var(--text-primary); max-width:140px">{{ item.nama_pasien }}</p>
                    <p class="font-mono text-xs mt-0.5" style="color:var(--text-muted)">{{ item.No_MR ?? '—' }}</p>
                  </div>
                </div>
              </td>
              <td class="px-5 py-4">
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full"
                  :style="`background:${SRC[item.sumber]?.bg}; color:${SRC[item.sumber]?.color}`">
                  <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" :style="`background:${SRC[item.sumber]?.color}`"></span>
                  {{ item.sumber_label }}
                </span>
              </td>
              <td class="px-5 py-4" style="max-width:160px">
                <p class="truncate text-sm" :title="item.diagnosa" style="color:var(--text-primary)">{{ item.diagnosa ?? '—' }}</p>
                <p v-if="item.kebutuhan_bed" class="text-xs mt-0.5 font-medium" style="color:#00A884">{{ item.kebutuhan_bed }}</p>
              </td>
              <td class="px-5 py-4" style="max-width:110px">
                <p class="truncate text-sm" style="color:var(--text-secondary)">{{ item.asal_rujukan ?? '—' }}</p>
              </td>
              <td class="px-5 py-4">
                <span v-if="item.nama_bed" class="inline-flex items-center gap-1.5 text-sm font-semibold" style="color:#00A884">
                  <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                  </svg>
                  {{ item.nama_bed }}
                </span>
                <span v-else class="text-sm" style="color:var(--text-muted)">—</span>
              </td>
              <td class="px-5 py-4">
                <span class="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-full whitespace-nowrap"
                  :style="`background:${ss(item.status).bg}; color:${ss(item.status).color}`">
                  <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" :style="`background:${ss(item.status).dot}`"></span>
                  {{ item.status_label }}
                </span>
              </td>
              <td class="px-5 py-4 font-mono text-xs whitespace-nowrap" style="color:var(--text-secondary)">{{ item.created_at_fmt }}</td>
              <td class="px-5 py-4 text-center">
                <svg class="w-4 h-4 mx-auto transition-transform duration-150 group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="color:var(--text-muted)">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- ── 7. MOBILE CARDS ───────────────────────────────────────────── -->
      <div class="block md:hidden divide-y" style="border-color:var(--border-row)">
        <div
          v-for="(item, idx) in antrian" :key="`mob-${item.sumber}-${item.id}`"
          @click="openModal('detail', item)"
          class="p-5 cursor-pointer group relative transition-all duration-150 hover:bg-[var(--bg-row-hover)]"
          :style="`border-left:4px solid ${ss(item.status).dot}`">

          <!-- Top row: badges + time -->
          <div class="flex items-start justify-between gap-2 mb-3 pr-6">
            <div class="flex flex-wrap gap-1.5">
              <span class="inline-flex items-center gap-1.5 text-xs font-bold px-2.5 py-1 rounded-full"
                :style="`background:${ss(item.status).bg}; color:${ss(item.status).color}`">
                <span class="w-1.5 h-1.5 rounded-full" :style="`background:${ss(item.status).dot}`"></span>
                {{ item.status_label }}
              </span>
              <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full"
                :style="`background:${SRC[item.sumber]?.bg}; color:${SRC[item.sumber]?.color}`">
                {{ item.sumber_label }}
              </span>
            </div>
            <span class="font-mono text-xs whitespace-nowrap flex-shrink-0" style="color:var(--text-muted)">{{ item.created_at_fmt?.split(' ')[0] }}</span>
          </div>

          <!-- Patient info -->
          <div class="flex items-center gap-3 mb-3 pr-6">
            <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 text-base font-bold"
              :style="`background:${gColor(item.jenis_kelamin)}18; color:${gColor(item.jenis_kelamin)}`">
              {{ gIcon(item.jenis_kelamin) }}
            </div>
            <div class="min-w-0 flex-1">
              <p class="font-semibold text-sm truncate" style="color:var(--text-primary)">{{ item.nama_pasien }}</p>
              <p class="font-mono text-xs mt-0.5" style="color:var(--text-muted)">{{ item.No_MR ?? 'Belum ada MR' }}</p>
            </div>
          </div>

          <!-- Info grid 2 cols -->
          <div class="grid grid-cols-2 gap-3 text-xs pr-6">
            <div class="min-w-0">
              <p class="mb-0.5" style="color:var(--text-muted)">Diagnosa</p>
              <p class="font-semibold truncate" style="color:var(--text-secondary)">{{ item.diagnosa ?? '—' }}</p>
            </div>
            <div class="min-w-0">
              <p class="mb-0.5" style="color:var(--text-muted)">Bed</p>
              <p class="font-semibold truncate flex items-center gap-1" :style="item.nama_bed ? 'color:#00A884' : 'color:var(--text-secondary)'">
                <svg v-if="item.nama_bed" class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                {{ item.nama_bed ?? '—' }}
              </p>
            </div>
          </div>

          <!-- Chevron -->
          <div class="absolute right-4 top-1/2 -translate-y-1/2">
            <svg class="w-4 h-4 transition-transform duration-150 group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="color:var(--text-muted)">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
            </svg>
          </div>
        </div>
      </div>

      <!-- ── 8. TABLE FOOTER ──────────────────────────────────────────── -->
      <div class="flex items-center justify-between px-5 py-3.5" style="border-top:1px solid var(--border-default); background:var(--bg-surface-2)">
        <p class="text-xs" style="color:var(--text-secondary)">
          Menampilkan <strong style="color:var(--text-primary)">{{ antrian.length }}</strong> data antrian
        </p>
        <div class="flex items-center gap-1.5 text-xs" style="color:var(--text-muted)">
          <span class="w-2 h-2 rounded-full animate-pulse inline-block" style="background:#00A884"></span>
          Auto-refresh aktif
        </div>
      </div>
    </div>

  </div>

  <!-- ═══ 9. MODAL OVERLAY ═══════════════════════════════════════════════ -->
  <Transition
    enter-active-class="transition-all duration-300 ease-out"
    enter-from-class="opacity-0"
    leave-active-class="transition-all duration-200 ease-in"
    leave-to-class="opacity-0"
  >
    <div v-if="modal.open"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
      style="background:rgba(0,0,0,0.65); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px)"
      @click.self="closeModal">

      <!-- Modal Card -->
      <div
        class="w-full flex flex-col relative overflow-hidden transition-all duration-300"
        style="max-width:32rem; max-height:92vh; background:var(--bg-surface); border:1px solid var(--border-default); border-radius:20px; box-shadow:0 25px 60px rgba(0,0,0,0.25), 0 8px 24px rgba(0,0,0,0.15)"
        @click.stop>

        <Transition
          enter-active-class="transition-all duration-250 ease-out"
          enter-from-class="opacity-0 scale-95"
          leave-active-class="transition-all duration-150 ease-in"
          leave-to-class="opacity-0 scale-105"
          mode="out-in"
        >
          <!-- ── VIEW 1: DETAIL ─────────────────────────────────────────── -->
          <div v-if="modal.type==='detail' && modal.item" key="detail" class="flex flex-col w-full" style="max-height:92vh">
            <!-- Header -->
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
                <p class="text-xs mt-0.5" style="color:var(--text-secondary)">Detail Pasien ICU</p>
              </div>
              <button @click="closeModal"
                class="flex-shrink-0 w-8 h-8 rounded-xl flex items-center justify-center transition-all hover:scale-110"
                style="background:var(--bg-input); color:var(--text-secondary)">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>

            <!-- Body -->
            <div class="overflow-y-auto px-6 py-5 space-y-4 flex-1">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-0.5">
                  <p class="text-xs font-medium" style="color:var(--text-muted)">No. MR</p>
                  <p class="text-sm font-bold font-mono" style="color:var(--text-primary)">{{ modal.item.No_MR ?? '—' }}</p>
                </div>
                <div class="space-y-0.5">
                  <p class="text-xs font-medium" style="color:var(--text-muted)">Jenis Kelamin</p>
                  <p class="text-sm font-bold flex items-center gap-1.5" style="color:var(--text-primary)">
                    <span class="text-base" :style="`color:${gColor(modal.item.jenis_kelamin)}`">{{ gIcon(modal.item.jenis_kelamin) }}</span>
                    {{ modal.item.jenis_kelamin === 'L' ? 'Pria' : modal.item.jenis_kelamin === 'P' ? 'Wanita' : '—' }}
                  </p>
                </div>
                <div class="sm:col-span-2 space-y-0.5">
                  <p class="text-xs font-medium" style="color:var(--text-muted)">Diagnosa / Indikasi</p>
                  <p class="text-sm font-bold" style="color:var(--text-primary)">{{ modal.item.diagnosa ?? '—' }}</p>
                </div>
                <div class="sm:col-span-2 space-y-0.5">
                  <p class="text-xs font-medium" style="color:var(--text-muted)">Catatan Admisi</p>
                  <p class="text-sm font-bold" style="color:var(--text-primary)">{{ modal.item.catatan_admisi ?? '—' }}</p>
                </div>
                <div class="space-y-0.5">
                  <p class="text-xs font-medium" style="color:var(--text-muted)">Asal Rujukan</p>
                  <p class="text-sm font-bold" style="color:var(--text-primary)">{{ modal.item.asal_rujukan ?? '—' }}</p>
                </div>
                <div class="space-y-0.5">
                  <p class="text-xs font-medium" style="color:var(--text-muted)">Alokasi Bed</p>
                  <p class="text-sm font-bold flex items-center gap-1.5" style="color:#00A884">
                    <svg v-if="modal.item.nama_bed" class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    {{ modal.item.nama_bed ?? '—' }}
                  </p>
                  <p v-if="modal.item.kebutuhan_bed" class="text-xs mt-0.5" style="color:var(--text-muted)">{{ modal.item.kebutuhan_bed }}</p>
                </div>
                <div class="sm:col-span-2 space-y-0.5">
                  <p class="text-xs font-medium" style="color:var(--text-muted)">Waktu Booking</p>
                  <p class="text-sm font-bold font-mono" style="color:var(--text-primary)">{{ modal.item.created_at_fmt }}</p>
                </div>
              </div>

              <!-- Alasan Tolak section -->
              <div v-if="modal.item.alasan_tolak" class="rounded-xl p-4 space-y-1.5"
                style="background:rgba(231,76,60,.06); border:1.5px solid rgba(231,76,60,.2)">
                <p class="text-xs font-bold flex items-center gap-1.5" style="color:#E74C3C">
                  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                  </svg>
                  Alasan Penolakan
                </p>
                <p class="text-sm" style="color:var(--text-primary)">{{ modal.item.alasan_tolak }}</p>
              </div>
            </div>

            <!-- Footer Actions -->
            <div class="px-6 py-5 flex-shrink-0 space-y-3" style="border-top:1px solid var(--border-default); background:var(--bg-surface-2)">
              <p class="text-xs font-bold uppercase tracking-widest" style="color:var(--text-muted)">Tindakan Tersedia</p>
              <div class="flex flex-col gap-2.5">
                <template v-for="act in actionsOf(modal.item)" :key="act.id">
                  <button @click="openModal(act.id, modal.item)"
                    class="w-full text-sm font-bold py-3 rounded-xl flex items-center justify-center transition-all duration-150 hover:-translate-y-px hover:brightness-105"
                    :style="`background:${act.bg}; color:${act.color}; border:1.5px solid ${act.border}`">
                    {{ act.label }}
                  </button>
                </template>
                <p v-if="!actionsOf(modal.item).length" class="text-sm py-1" style="color:var(--text-muted)">
                  Tidak ada aksi yang tersedia untuk status ini.
                </p>
              </div>
            </div>
          </div>

          <!-- ── VIEW 2: KONFIRMASI BED ─────────────────────────────────── -->
          <div v-else-if="modal.type==='konfirmasi' && modal.item" key="konfirmasi" class="flex flex-col w-full" style="max-height:92vh">
            <!-- Header with Back -->
            <div class="flex items-center justify-between px-6 py-5 flex-shrink-0" style="border-bottom:1px solid var(--border-default)">
              <div class="flex items-center gap-3">
                <button type="button" @click="openModal('detail', modal.item)"
                  class="w-8 h-8 rounded-xl flex items-center justify-center transition-all hover:scale-110"
                  style="background:var(--bg-input); color:var(--text-secondary)">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                  </svg>
                </button>
                <div>
                  <h2 class="text-base font-bold" style="color:var(--text-primary)">
                    {{ modal.item?.sumber==='external' ? 'Konfirmasi Bed' : 'Verifikasi Bed' }}
                  </h2>
                  <p class="text-xs mt-0.5" style="color:var(--text-secondary)">{{ modal.item?.nama_pasien }}</p>
                </div>
              </div>
              <button @click="closeModal"
                class="w-8 h-8 rounded-xl flex items-center justify-center transition-all hover:scale-110"
                style="background:var(--bg-input); color:var(--text-secondary)">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>

            <!-- Info snapshot -->
            <div class="px-6 py-4 grid grid-cols-2 gap-3 flex-shrink-0" style="border-bottom:1px solid var(--border-default); background:var(--bg-surface-2)">
              <div class="rounded-xl p-3 space-y-0.5" style="background:var(--bg-input)">
                <p class="text-xs" style="color:var(--text-muted)">No. MR</p>
                <p class="text-sm font-bold font-mono" style="color:var(--text-primary)">{{ modal.item?.No_MR ?? '—' }}</p>
              </div>
              <div class="rounded-xl p-3 space-y-0.5" style="background:var(--bg-input)">
                <p class="text-xs" style="color:var(--text-muted)">Asal Rujukan</p>
                <p class="text-sm font-bold truncate" style="color:var(--text-primary)">{{ modal.item?.asal_rujukan ?? '—' }}</p>
              </div>
            </div>

            <!-- Form Body -->
            <div class="overflow-y-auto flex-1">
              <form @submit.prevent="submitKonfirmasi" class="px-6 py-5 space-y-5">
                <div class="space-y-2">
                  <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">
                    Jenis ICU <span style="color:#E74C3C">*</span>
                  </label>
                  <select v-model="fmKonfirmasi.kebutuhan_bed" required
                    class="w-full rounded-xl outline-none transition-all"
                    style="padding:11px 14px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); font-size:13px">
                    <option value="" disabled>— Pilih Jenis ICU —</option>
                    <option v-for="k in masterKelas" :key="k.kode" :value="k.nama">{{ k.nama }}</option>
                  </select>
                </div>
                <div class="space-y-2">
                  <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">
                    Pilih Bed <span style="color:#E74C3C">*</span>
                    <span class="normal-case font-normal ml-1.5 px-2 py-0.5 rounded-full text-xs" style="background:rgba(0,168,132,.12); color:#00A884">
                      {{ bedCocok.length }} tersedia
                    </span>
                  </label>
                  <select v-model="fmKonfirmasi.Kode_Ruang" required :disabled="!bedCocok.length"
                    class="w-full rounded-xl outline-none transition-all disabled:opacity-40"
                    style="padding:11px 14px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); font-size:13px">
                    <option value="" disabled>— Pilih Bed —</option>
                    <option v-for="bed in bedCocok" :key="bed.Kode_Ruang" :value="bed.Kode_Ruang">{{ bed.nama_ruang }}</option>
                  </select>
                  <p v-if="!kamarKosong.length" class="text-xs flex items-center gap-1.5" style="color:#E67E22">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                    Tidak ada bed kosong saat ini
                  </p>
                </div>
                <button type="submit"
                  :disabled="fmKonfirmasi.processing || !fmKonfirmasi.Kode_Ruang || !fmKonfirmasi.kebutuhan_bed"
                  class="w-full text-sm font-bold py-3.5 rounded-xl transition-all duration-150 hover:-translate-y-px disabled:opacity-40 disabled:hover:translate-y-0 flex items-center justify-center gap-2"
                  style="background:#00A884; color:var(--text-on-accent)">
                  <svg v-if="!fmKonfirmasi.processing" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                  </svg>
                  {{ fmKonfirmasi.processing ? 'Menyimpan...' : 'Simpan Alokasi Bed' }}
                </button>
              </form>
            </div>
          </div>

          <!-- ── VIEW 3: TOLAK ──────────────────────────────────────────── -->
          <div v-else-if="modal.type==='tolak' && modal.item" key="tolak" class="flex flex-col w-full" style="max-height:92vh">
            <!-- Header with Back -->
            <div class="flex items-center justify-between px-6 py-5 flex-shrink-0" style="border-bottom:1px solid var(--border-default)">
              <div class="flex items-center gap-3">
                <button type="button" @click="openModal('detail', modal.item)"
                  class="w-8 h-8 rounded-xl flex items-center justify-center transition-all hover:scale-110"
                  style="background:var(--bg-input); color:var(--text-secondary)">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                  </svg>
                </button>
                <div>
                  <h2 class="text-base font-bold" style="color:#E74C3C">Tolak Permintaan</h2>
                  <p class="text-xs mt-0.5" style="color:var(--text-secondary)">{{ modal.item?.nama_pasien }}</p>
                </div>
              </div>
              <button @click="closeModal"
                class="w-8 h-8 rounded-xl flex items-center justify-center transition-all hover:scale-110"
                style="background:var(--bg-input); color:var(--text-secondary)">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>

            <!-- Form Body -->
            <div class="overflow-y-auto flex-1">
              <form @submit.prevent="submitTolak" class="px-6 py-5 space-y-5">
                <div class="rounded-xl p-4 space-y-1" style="background:rgba(231,76,60,.06); border:1.5px solid rgba(231,76,60,.15)">
                  <p class="text-xs font-bold flex items-center gap-1.5" style="color:#E74C3C">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    Perhatian
                  </p>
                  <p class="text-xs" style="color:var(--text-secondary)">Tindakan ini tidak dapat dibatalkan. Pastikan alasan penolakan sudah jelas dan lengkap.</p>
                </div>
                <div class="space-y-2">
                  <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">
                    Alasan Penolakan <span style="color:#E74C3C">*</span>
                  </label>
                  <textarea v-model="fmTolak.alasan_tolak" required rows="5"
                    placeholder="Tuliskan alasan penolakan secara jelas dan lengkap..."
                    class="w-full rounded-xl outline-none resize-none transition-all"
                    style="padding:11px 14px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); font-size:13px; line-height:1.6"/>
                </div>
                <button type="submit"
                  :disabled="fmTolak.processing || !fmTolak.alasan_tolak.trim()"
                  class="w-full text-sm font-bold py-3.5 rounded-xl transition-all duration-150 hover:-translate-y-px disabled:opacity-40 disabled:hover:translate-y-0 flex items-center justify-center gap-2"
                  style="background:rgba(231,76,60,.12); color:#E74C3C; border:1.5px solid rgba(231,76,60,.3)">
                  <svg v-if="!fmTolak.processing" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                  </svg>
                  {{ fmTolak.processing ? 'Menyimpan...' : 'Proses Penolakan' }}
                </button>
              </form>
            </div>
          </div>

        </Transition>
      </div>
    </div>
  </Transition>

</AppLayout>
</template>
