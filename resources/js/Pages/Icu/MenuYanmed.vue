<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { usePagination } from '@/composables/usePagination.js';
import { Pie } from 'vue-chartjs';
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js';
ChartJS.register(ArcElement, Tooltip, Legend);

const props = defineProps({
  pasien:   { type: Array,  default: () => [] },
  summary:  { type: Object, default: () => ({}) },
  filters:  { type: Object, default: () => ({}) },
  caraBayar:{ type: Array,  default: () => [] },
  flash:    { type: Object, default: () => ({}) },
});

const jaminanLabel = (k) => {
  if (!k) return '—';
  const found = props.caraBayar.find(c => c.kode === k);
  return found ? found.nama : k;
};

const fJenis   = ref(props.filters.jenis    ?? '');
const fStatus  = ref(props.filters.status   ?? '');
const fAsal    = ref(props.filters.asal     ?? '');
const fNama    = ref(props.filters.nama     ?? '');
const localDate = (n=0) => { const d=new Date(); d.setDate(d.getDate()+n); return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`; };
const fTglDari = ref(props.filters.tgl_dari    || localDate(0));
const fTglAkh  = ref(props.filters.tgl_sampai  || localDate(0));
const today=localDate(0), yesterday=localDate(-1), week7=localDate(-6);

let st=null;
const applyFilters = () => router.get(route('icu.menu_yanmed'), {
  jenis:fJenis.value, status:fStatus.value, asal:fAsal.value,
  nama:fNama.value, tgl_dari:fTglDari.value, tgl_sampai:fTglAkh.value,
}, { preserveState:true, replace:true, preserveScroll:true });
const onNamaInput = () => { clearTimeout(st); st=setTimeout(applyFilters,400); };
const resetFilter = () => { fJenis.value=''; fStatus.value=''; fAsal.value=''; fNama.value=''; fTglDari.value=localDate(0); fTglAkh.value=localDate(0); applyFilters(); };
const setPreset = (d,s) => { fTglDari.value=d; fTglAkh.value=s; applyFilters(); };

// ── Pie chart helpers ─────────────────────────────────────────────────────
const PALETTE = [
  '#00A884','#7C3AED','#0EA5E9','#F59E0B','#EF4444',
  '#10B981','#8B5CF6','#F97316','#06B6D4','#84CC16',
];
const pieOpt = {
  responsive: true, maintainAspectRatio: false,
  plugins: {
    legend: { position: 'right', labels: { font:{size:11}, padding:12, boxWidth:12, boxHeight:12 } },
    tooltip: { callbacks: { label: c => ` ${c.label}: ${c.raw} pasien` } },
  },
};
const topN = (key, n=8) => {
  const map={};
  props.pasien.forEach(p => { const v=(p[key]??'').trim(); if(v&&v!=='-'&&v!=='—') map[v]=(map[v]??0)+1; });
  return Object.entries(map).sort((a,b)=>b[1]-a[1]).slice(0,n);
};
const makePie = (entries) => ({
  labels: entries.map(([k]) => k.length>20 ? k.slice(0,20)+'…' : k),
  datasets: [{ data: entries.map(([,v])=>v), backgroundColor: PALETTE.slice(0,entries.length), borderWidth:2, borderColor:'transparent', hoverOffset:6 }],
});

const asalEntries   = computed(() => topN('asal', 8));
const diagEntries   = computed(() => topN('diagnosa', 8));
const asalPieData   = computed(() => makePie(asalEntries.value));
const diagPieData   = computed(() => makePie(diagEntries.value));

// Distribusi status
const statusEntries = computed(() => {
  const map={};
  props.pasien.forEach(p => {
    const lbl = (SS[p.status]?.label ?? p.status ?? 'Lainnya');
    map[lbl] = (map[lbl]??0)+1;
  });
  return Object.entries(map).sort((a,b)=>b[1]-a[1]);
});
const statusColors  = ['#E67E22','#D97706','#00A884','#27AE60','#27AE60','#E74C3C'];
const statusPieData = computed(() => ({
  labels: statusEntries.value.map(([k])=>k),
  datasets:[{ data: statusEntries.value.map(([,v])=>v), backgroundColor: statusColors.slice(0, statusEntries.value.length), borderWidth:2, borderColor:'transparent', hoverOffset:6 }],
}));

// ── Styles ────────────────────────────────────────────────────────────────
const SS = {
  pending_icu:     { bg:'#FDF3E9', color:'#E67E22', dot:'#E67E22', label:'Menunggu ICU' },
  pending_admisi:  { bg:'#FDF3E9', color:'#E67E22', dot:'#E67E22', label:'Menunggu Admisi' },
  waiting_list:    { bg:'#FEF3C7', color:'#D97706', dot:'#D97706', label:'Waiting List' },
  bed_confirmed:   { bg:'#D1FAF0', color:'#00A884', dot:'#00A884', label:'Bed Dikonfirmasi' },
  bed_verified:    { bg:'#EBF9F1', color:'#27AE60', dot:'#27AE60', label:'Bed Terverifikasi' },
  admisi_verified: { bg:'#EBF9F1', color:'#27AE60', dot:'#27AE60', label:'Terverifikasi' },
};
const ss = (s) => SS[s]??{bg:'var(--bg-input)',color:'var(--text-secondary)',dot:'#888',label:s};
const SRC = { external:{bg:'rgba(0,168,132,.12)',color:'#00A884'}, internal:{bg:'rgba(90,107,124,.12)',color:'#5A6B7C'} };
const gIcon  = (g) => g==='L'?'♂':g==='P'?'♀':'·';
const gColor = (g) => g==='L'?'#00A884':g==='P'?'#8E44AD':'var(--text-secondary)';

const detail = ref(null);
const openDetail  = (i) => { detail.value=i; };
const closeDetail = () => { detail.value=null; };

const statusOptions = [
  {value:'',label:'Semua Status'},{value:'pending_icu',label:'Menunggu ICU'},
  {value:'pending_admisi',label:'Menunggu Admisi'},{value:'waiting_list',label:'Waiting List'},
  {value:'bed_confirmed',label:'Bed Dikonfirmasi'},{value:'bed_verified',label:'Bed Terverifikasi'},
  {value:'admisi_verified',label:'Terverifikasi'},
];

// ── Pagination ─────────────────────────────────────────────────────────────
// MenuYanmed tidak punya tab, langsung paginate props.pasien
const pasienList = computed(() => props.pasien);
const { page, perPage, totalPages, paginated: paginatedPasien, pageRange, goTo, next, prev, reset: resetPage } = usePagination(pasienList, 10);

// Reset ke hal. 1 saat filter berubah
watch([fJenis, fStatus, fAsal, fNama, fTglDari, fTglAkh], resetPage);
</script>

<template>
  <AppLayout :flash="flash" page-title="Menu Yanmed">
    <div class="p-6 sm:p-8 space-y-6" style="font-family:'Inter','Plus Jakarta Sans',sans-serif;background:var(--bg-main);min-height:100%">

      <!-- HERO -->
      <div style="background:#00A884;border-radius:16px;padding:22px 28px;position:relative;overflow:hidden;border:1px solid rgba(255,255,255,.1);box-shadow:0 12px 32px rgba(0,168,132,.2)">
        <div style="position:absolute;width:260px;height:260px;border-radius:50%;right:-80px;top:-100px;background:radial-gradient(circle,rgba(255,255,255,.08),transparent);pointer-events:none"></div>
        <p style="color:rgba(255,255,255,.65);font-size:11px;font-weight:600;letter-spacing:.04em">YANKESMAS &amp; MEDIS</p>
        <h1 style="color:#fff;font-size:clamp(18px,4vw,28px);font-weight:900;letter-spacing:-.02em;line-height:1.1;margin:4px 0 6px">Laporan Antrian ICU</h1>
        <p style="color:rgba(255,255,255,.5);font-size:12px">Ringkasan data pasien booking ICU — eksternal &amp; internal</p>
      </div>

      <!-- KPI CARDS -->
      <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3">
        <div v-for="card in [
          {label:'Total Pasien',  val:summary.total,        color:'#5A6B7C', icon:'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'},
          {label:'Booking Ext.', val:summary.total_ext,    color:'#00A884', icon:'M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z'},
          {label:'Booking Int.', val:summary.total_int,    color:'#5A6B7C', icon:'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'},
          {label:'Menunggu',     val:summary.menunggu,     color:'#D97706', icon:'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'},
          {label:'Terkonfirmasi',val:summary.terkonfirmasi,color:'#00A884', icon:'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'},
          {label:'Bed Terisi',   val:summary.bed_terisi,   color:'#E74C3C', icon:'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z'},
          {label:'Bed Kosong',   val:summary.bed_kosong,   color:'#27AE60', icon:'M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z'},
        ]" :key="card.label"
          class="rounded-2xl p-4 flex items-center gap-3"
          style="background:var(--bg-card);border:1px solid var(--border-default);box-shadow:var(--shadow-card)">
          <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0" :style="`background:${card.color}18`">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" :style="`color:${card.color}`">
              <path stroke-linecap="round" stroke-linejoin="round" :d="card.icon"/>
            </svg>
          </div>
          <div class="min-w-0">
            <p class="text-xl font-black leading-none" :style="`color:${card.color};font-family:'DM Mono',monospace`">{{ card.val??0 }}</p>
            <p class="text-xs font-semibold mt-1 truncate" style="color:var(--text-secondary)">{{ card.label }}</p>
          </div>
        </div>
      </div>

      <!-- CHARTS ROW — 3 pie dalam satu row -->
      <div class="ym-charts">

        <!-- Pie: Asal Rujukan/Ruang -->
        <div class="rounded-2xl p-5" style="background:var(--bg-surface);border:1px solid var(--border-default);box-shadow:var(--shadow-card)">
          <div class="flex items-center justify-between mb-4">
            <div>
              <p class="text-sm font-bold" style="color:var(--text-primary)">Asal Rujukan / Ruang</p>
              <p class="text-xs mt-0.5" style="color:var(--text-muted)">Top {{ asalEntries.length }} sumber rujukan</p>
            </div>
            <span class="text-xs font-semibold px-2 py-1 rounded-full" style="background:rgba(0,168,132,.1);color:#00A884">
              {{ asalEntries.length }} ruang
            </span>
          </div>
          <div v-if="asalEntries.length" style="height:220px">
            <Pie :data="asalPieData" :options="pieOpt"/>
          </div>
          <div v-else class="flex flex-col items-center gap-2 py-10" style="color:var(--text-muted)">
            <svg style="width:32px;height:32px;opacity:.25" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/>
              <path stroke-linecap="round" stroke-linejoin="round" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/>
            </svg>
            <p style="font-size:12px">Belum ada data</p>
          </div>
        </div>

        <!-- Pie: Top Diagnosa -->
        <div class="rounded-2xl p-5" style="background:var(--bg-surface);border:1px solid var(--border-default);box-shadow:var(--shadow-card)">
          <div class="flex items-center justify-between mb-4">
            <div>
              <p class="text-sm font-bold" style="color:var(--text-primary)">Top Diagnosa</p>
              <p class="text-xs mt-0.5" style="color:var(--text-muted)">Top {{ diagEntries.length }} diagnosa terbanyak</p>
            </div>
            <span class="text-xs font-semibold px-2 py-1 rounded-full" style="background:rgba(124,58,237,.1);color:#7C3AED">
              {{ diagEntries.length }} diagnosa
            </span>
          </div>
          <div v-if="diagEntries.length" style="height:220px">
            <Pie :data="diagPieData" :options="pieOpt"/>
          </div>
          <div v-else class="flex flex-col items-center gap-2 py-10" style="color:var(--text-muted)">
            <svg style="width:32px;height:32px;opacity:.25" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p style="font-size:12px">Belum ada data</p>
          </div>
        </div>

        <!-- Pie: Distribusi Status -->
        <div class="rounded-2xl p-5" style="background:var(--bg-surface);border:1px solid var(--border-default);box-shadow:var(--shadow-card)">
          <div class="flex items-center justify-between mb-4">
            <div>
              <p class="text-sm font-bold" style="color:var(--text-primary)">Distribusi Status</p>
              <p class="text-xs mt-0.5" style="color:var(--text-muted)">Sebaran status antrian saat ini</p>
            </div>
            <span class="text-xs font-semibold px-2 py-1 rounded-full" style="background:rgba(90,107,124,.1);color:#5A6B7C">
              {{ pasien.length }} pasien
            </span>
          </div>
          <div v-if="statusEntries.length" style="height:220px">
            <Pie :data="statusPieData" :options="pieOpt"/>
          </div>
          <div v-else class="flex flex-col items-center gap-2 py-10" style="color:var(--text-muted)">
            <svg style="width:32px;height:32px;opacity:.25" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p style="font-size:12px">Belum ada data</p>
          </div>
        </div>

      </div>

      <!-- FILTER BAR -->
      <div class="rounded-2xl p-5 space-y-4" style="background:var(--bg-surface);border:1px solid var(--border-default);box-shadow:var(--shadow-card)">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4">
          <div class="space-y-1.5">
            <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Jenis</label>
            <select v-model="fJenis" @change="applyFilters" class="w-full rounded-xl outline-none"
              style="padding:10px 14px;border:1.5px solid var(--border-default);background:var(--bg-input);color:var(--text-primary);font-size:13px">
              <option value="">Semua Jenis</option>
              <option value="external">Booking Eksternal</option>
              <option value="internal">Booking Internal</option>
            </select>
          </div>
          <div class="space-y-1.5">
            <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Status</label>
            <select v-model="fStatus" @change="applyFilters" class="w-full rounded-xl outline-none"
              style="padding:10px 14px;border:1.5px solid var(--border-default);background:var(--bg-input);color:var(--text-primary);font-size:13px">
              <option v-for="o in statusOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
          </div>
          <div class="space-y-1.5">
            <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Nama / No. MR</label>
            <input v-model="fNama" @input="onNamaInput" placeholder="Cari pasien..."
              class="w-full rounded-xl outline-none"
              style="padding:10px 14px;border:1.5px solid var(--border-default);background:var(--bg-input);color:var(--text-primary);font-size:13px"/>
          </div>
          <div class="space-y-1.5">
            <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Tgl Mulai</label>
            <input v-model="fTglDari" @change="applyFilters" type="date" class="w-full rounded-xl outline-none"
              style="padding:10px 14px;border:1.5px solid var(--border-default);background:var(--bg-input);color:var(--text-primary);font-size:13px"/>
          </div>
          <div class="space-y-1.5">
            <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Tgl Selesai</label>
            <input v-model="fTglAkh" @change="applyFilters" type="date" :min="fTglDari" class="w-full rounded-xl outline-none"
              style="padding:10px 14px;border:1.5px solid var(--border-default);background:var(--bg-input);color:var(--text-primary);font-size:13px"/>
          </div>
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <div class="flex gap-1 p-1 rounded-xl" style="background:var(--bg-input)">
            <button v-for="p in [{l:'Hari ini',d:today,s:today},{l:'Kemarin',d:yesterday,s:yesterday},{l:'7 Hari',d:week7,s:today}]"
              :key="p.l" @click="setPreset(p.d,p.s)"
              class="px-3 py-1 rounded-lg text-xs font-semibold transition-all"
              :style="fTglDari===p.d&&fTglAkh===p.s?'background:#fff;color:#00A884;box-shadow:0 1px 4px rgba(0,0,0,.08)':'color:var(--text-muted)'">
              {{ p.l }}
            </button>
          </div>
          <button v-if="fJenis||fStatus||fNama||fAsal" @click="resetFilter"
            class="ml-auto text-xs font-semibold px-3 py-1.5 rounded-xl flex items-center gap-1.5"
            style="background:rgba(231,76,60,.1);color:#E74C3C;border:1.5px solid rgba(231,76,60,.25)">
            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            Reset Filter
          </button>
        </div>
      </div>

      <!-- EMPTY -->
      <div v-if="!pasien.length" class="rounded-2xl flex flex-col items-center justify-center py-20 text-center"
        style="background:var(--bg-surface);border:1px solid var(--border-default)">
        <div class="w-16 h-16 rounded-2xl flex items-center justify-center mb-4"
          style="background:rgba(0,168,132,.08);border:1.5px dashed rgba(0,168,132,.3)">
          <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="#00A884" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
        </div>
        <p class="font-bold text-sm mb-1" style="color:var(--text-primary)">Tidak ada data ditemukan</p>
        <p class="text-xs max-w-xs" style="color:var(--text-muted)">Coba ubah filter atau rentang tanggal.</p>
      </div>

      <!-- TABEL -->
      <div v-else class="rounded-2xl overflow-hidden"
        style="background:var(--bg-surface);border:1px solid var(--border-default);box-shadow:var(--shadow-card)">
        <div class="hidden md:block overflow-x-auto">
          <table class="w-full" style="border-collapse:collapse;min-width:960px;font-size:13px">
            <thead>
              <tr style="background:var(--table-th-bg);border-bottom:2px solid var(--border-default)">
                <th class="py-3.5 px-4 text-left text-xs font-semibold uppercase tracking-wider w-8" style="color:var(--table-th-color)">#</th>
                <th class="py-3.5 px-4 text-left text-xs font-semibold uppercase tracking-wider" style="color:var(--table-th-color)">Pasien</th>
                <th class="py-3.5 px-4 text-left text-xs font-semibold uppercase tracking-wider" style="color:var(--table-th-color)">Jenis</th>
                <th class="py-3.5 px-4 text-left text-xs font-semibold uppercase tracking-wider" style="color:var(--table-th-color)">Asal Ruang / Rujukan</th>
                <th class="py-3.5 px-4 text-left text-xs font-semibold uppercase tracking-wider" style="color:var(--table-th-color)">Diagnosa / Indikasi</th>
                <th class="py-3.5 px-4 text-left text-xs font-semibold uppercase tracking-wider" style="color:var(--table-th-color)">Bed</th>
                <th class="py-3.5 px-4 text-left text-xs font-semibold uppercase tracking-wider" style="color:var(--table-th-color)">Status</th>
                <th class="py-3.5 px-4 text-left text-xs font-semibold uppercase tracking-wider" style="color:var(--table-th-color)">Waktu</th>
                <th class="py-3.5 px-4 w-8"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(item,idx) in paginatedPasien" :key="`${item.sumber}-${item.id}`"
                @click="openDetail(item)" class="group cursor-pointer transition-all"
                style="border-bottom:1px solid var(--border-row)"
                :style="`border-left:4px solid ${ss(item.status).dot}`"
                onmouseenter="this.style.background='var(--bg-row-hover)'"
                onmouseleave="this.style.background=''">
                <td class="px-4 py-3.5 font-mono text-xs" style="color:var(--text-muted)">{{ (page - 1) * perPage + idx + 1 }}</td>
                <td class="px-4 py-3.5">
                  <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 font-bold text-sm"
                      :style="`background:${gColor(item.jenis_kelamin)}18;color:${gColor(item.jenis_kelamin)}`">
                      {{ gIcon(item.jenis_kelamin) }}
                    </div>
                    <div class="min-w-0">
                      <p class="font-semibold truncate" style="color:var(--text-primary);max-width:130px">{{ item.nama_pasien }}</p>
                      <p class="font-mono text-xs mt-0.5" style="color:var(--text-muted)">{{ item.No_MR??'—' }}</p>
                    </div>
                  </div>
                </td>
                <td class="px-4 py-3.5">
                  <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full"
                    :style="`background:${SRC[item.sumber]?.bg};color:${SRC[item.sumber]?.color}`">
                    <span class="w-1.5 h-1.5 rounded-full" :style="`background:${SRC[item.sumber]?.color}`"></span>
                    {{ item.sumber==='external'?'Eksternal':'Internal' }}
                  </span>
                </td>
                <td class="px-4 py-3.5 text-sm" style="color:var(--text-secondary);max-width:140px">
                  <p class="break-words whitespace-normal">{{ item.asal??'—' }}</p>
                </td>
                <td class="px-4 py-3.5" style="max-width:200px">
                  <p class="text-sm font-semibold break-words whitespace-normal" style="color:var(--text-primary)">{{ item.diagnosa??'—' }}</p>
                  <p v-if="item.indikasi" class="text-xs mt-0.5 break-words whitespace-normal" style="color:var(--text-muted)">{{ item.indikasi }}</p>
                  <p v-if="item.diagnosa_icd" class="text-xs mt-0.5 font-mono" style="color:#00A884">ICD: {{ item.diagnosa_icd }}</p>
                </td>
                <td class="px-4 py-3.5">
                  <span v-if="item.nama_bed" class="inline-flex items-center gap-1 text-sm font-semibold" style="color:#00A884">🛏 {{ item.nama_bed }}</span>
                  <span v-else-if="item.kebutuhan_bed" class="text-xs px-2 py-0.5 rounded-lg font-semibold"
                    style="background:rgba(0,168,132,.1);color:#00A884">{{ item.kebutuhan_bed }}</span>
                  <span v-else class="text-sm" style="color:var(--text-muted)">—</span>
                </td>
                <td class="px-4 py-3.5">
                  <span class="inline-flex items-center gap-1.5 text-xs font-bold px-2.5 py-1.5 rounded-full whitespace-nowrap"
                    :style="`background:${ss(item.status).bg};color:${ss(item.status).color}`">
                    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" :style="`background:${ss(item.status).dot}`"></span>
                    {{ ss(item.status).label }}
                  </span>
                </td>
                <td class="px-4 py-3.5 font-mono text-xs whitespace-nowrap" style="color:var(--text-secondary)">{{ item.created_at_fmt }}</td>
                <td class="px-4 py-3.5 text-center">
                  <svg class="w-4 h-4 mx-auto transition-transform group-hover:translate-x-1" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="color:var(--text-muted)">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                  </svg>
                </td>
              </tr>
            </tbody>
          </table>
          <!-- Pagination desktop -->
          <div class="px-4 py-3">
            <Pagination :page="page" :total-pages="totalPages" :page-range="pageRange"
              :total="pasien.length" :per-page="perPage" label="pasien"
              @go="goTo" @prev="prev" @next="next" />
          </div>
        </div>
        <!-- Mobile -->
        <div class="block md:hidden divide-y" style="border-color:var(--border-row)">
          <div v-for="item in paginatedPasien" :key="`mob-${item.sumber}-${item.id}`"
            @click="openDetail(item)"
            class="p-4 cursor-pointer relative transition-all hover:bg-[var(--bg-row-hover)]"
            :style="`border-left:4px solid ${ss(item.status).dot}`">
            <div class="flex items-start justify-between gap-2 mb-2 pr-6">
              <div class="flex flex-wrap gap-1.5">
                <span class="inline-flex items-center gap-1 text-xs font-bold px-2.5 py-1 rounded-full"
                  :style="`background:${ss(item.status).bg};color:${ss(item.status).color}`">
                  <span class="w-1.5 h-1.5 rounded-full" :style="`background:${ss(item.status).dot}`"></span>
                  {{ ss(item.status).label }}
                </span>
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full"
                  :style="`background:${SRC[item.sumber]?.bg};color:${SRC[item.sumber]?.color}`">
                  {{ item.sumber==='external'?'Ext':'Int' }}
                </span>
              </div>
              <span class="font-mono text-xs" style="color:var(--text-muted)">{{ item.created_at_fmt?.split(' ')[0] }}</span>
            </div>
            <div class="flex items-center gap-2.5 mb-2 pr-6">
              <div class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 font-bold"
                :style="`background:${gColor(item.jenis_kelamin)}18;color:${gColor(item.jenis_kelamin)}`">
                {{ gIcon(item.jenis_kelamin) }}
              </div>
              <div class="min-w-0">
                <p class="font-semibold text-sm truncate" style="color:var(--text-primary)">{{ item.nama_pasien }}</p>
                <p class="font-mono text-xs" style="color:var(--text-muted)">{{ item.No_MR??'—' }}</p>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-2 text-xs pr-6">
              <div><p style="color:var(--text-muted)" class="mb-0.5">Asal</p><p class="font-semibold truncate" style="color:var(--text-secondary)">{{ item.asal??'—' }}</p></div>
              <div><p style="color:var(--text-muted)" class="mb-0.5">Bed</p><p class="font-semibold truncate" :style="item.nama_bed?'color:#00A884':'color:var(--text-secondary)'">{{ item.nama_bed??item.kebutuhan_bed??'—' }}</p></div>
              <div class="col-span-2"><p style="color:var(--text-muted)" class="mb-0.5">Diagnosa</p><p class="font-semibold" style="color:var(--text-primary)">{{ item.diagnosa??'—' }}</p></div>
            </div>
            <div class="absolute right-4 top-1/2 -translate-y-1/2">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="color:var(--text-muted)"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </div>
          </div>
        </div>
        <div class="flex items-center justify-between px-5 py-3"
          style="border-top:1px solid var(--border-default);background:var(--bg-surface-2)">
          <p class="text-xs" style="color:var(--text-secondary)">
            Menampilkan <strong style="color:var(--text-primary)">{{ pasien.length }}</strong> pasien
            <span style="color:var(--text-muted)">({{ summary.total_ext??0 }} Ext · {{ summary.total_int??0 }} Int)</span>
          </p>
        </div>
        <!-- Pagination mobile -->
        <div class="block md:hidden px-4 py-3" style="border-top:1px solid var(--border-default)">
          <Pagination :page="page" :total-pages="totalPages" :page-range="pageRange"
            :total="pasien.length" :per-page="perPage" label="pasien"
            @go="goTo" @prev="prev" @next="next" />
        </div>
      </div>
    </div>

    <!-- MODAL DETAIL -->
    <Transition enter-active-class="transition-all duration-300 ease-out" enter-from-class="opacity-0"
      leave-active-class="transition-all duration-200 ease-in" leave-to-class="opacity-0">
      <div v-if="detail" class="fixed inset-0 z-50 flex items-center justify-center p-4"
        style="background:rgba(0,0,0,0.65);backdrop-filter:blur(8px)" @click.self="closeDetail">
        <div class="w-full flex flex-col relative overflow-hidden"
          style="max-width:30rem;max-height:90vh;background:var(--bg-surface);border:1px solid var(--border-default);border-radius:20px;box-shadow:0 25px 60px rgba(0,0,0,0.25)"
          @click.stop>
          <div class="flex items-start justify-between px-6 py-5 flex-shrink-0"
            style="border-bottom:1px solid var(--border-default)">
            <div class="flex-1 min-w-0 pr-3">
              <div class="flex flex-wrap gap-1.5 mb-2">
                <span class="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-full"
                  :style="`background:${ss(detail.status).bg};color:${ss(detail.status).color}`">
                  <span class="w-1.5 h-1.5 rounded-full" :style="`background:${ss(detail.status).dot}`"></span>
                  {{ ss(detail.status).label }}
                </span>
                <span class="text-xs font-semibold px-3 py-1.5 rounded-full"
                  :style="`background:${SRC[detail.sumber]?.bg};color:${SRC[detail.sumber]?.color}`">
                  {{ detail.sumber_label }}
                </span>
              </div>
              <h2 class="text-base font-bold truncate" style="color:var(--text-primary)">{{ detail.nama_pasien }}</h2>
              <p class="text-xs mt-0.5" style="color:var(--text-secondary)">Detail Pasien ICU</p>
            </div>
            <button @click="closeDetail" class="flex-shrink-0 w-8 h-8 rounded-xl flex items-center justify-center hover:scale-110 transition-transform"
              style="background:var(--bg-input);color:var(--text-secondary)">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>
          <div class="overflow-y-auto px-6 py-5 space-y-4 flex-1">
            <div class="grid grid-cols-2 gap-4">
              <div class="space-y-0.5"><p class="text-xs font-medium" style="color:var(--text-muted)">No. MR</p><p class="text-sm font-bold font-mono" style="color:var(--text-primary)">{{ detail.No_MR??'—' }}</p></div>
              <div class="space-y-0.5"><p class="text-xs font-medium" style="color:var(--text-muted)">Jenis Kelamin</p>
                <p class="text-sm font-bold" :style="`color:${gColor(detail.jenis_kelamin)}`">{{ gIcon(detail.jenis_kelamin) }} {{ detail.jenis_kelamin==='L'?'Laki-laki':detail.jenis_kelamin==='P'?'Perempuan':'—' }}</p></div>
              <div class="col-span-2 space-y-0.5"><p class="text-xs font-medium" style="color:var(--text-muted)">Asal Ruang / Rujukan</p><p class="text-sm font-bold" style="color:var(--text-primary)">{{ detail.asal??'—' }}</p></div>
              <div class="col-span-2 space-y-0.5"><p class="text-xs font-medium" style="color:var(--text-muted)">Diagnosa</p>
                <p class="text-sm font-bold" style="color:var(--text-primary)">{{ detail.diagnosa??'—' }}</p>
                <p v-if="detail.diagnosa_icd" class="text-xs font-mono mt-0.5" style="color:#00A884">ICD: {{ detail.diagnosa_icd }}</p></div>
              <div class="col-span-2 space-y-0.5"><p class="text-xs font-medium" style="color:var(--text-muted)">Indikasi / Rencana Tindakan</p><p class="text-sm font-bold" style="color:var(--text-primary)">{{ detail.indikasi??'—' }}</p></div>
              <div v-if="detail.dokter" class="space-y-0.5"><p class="text-xs font-medium" style="color:var(--text-muted)">DPJP</p><p class="text-sm font-bold" style="color:var(--text-primary)">{{ detail.dokter }}</p></div>
              <div class="space-y-0.5"><p class="text-xs font-medium" style="color:var(--text-muted)">Alokasi Bed</p>
                <p class="text-sm font-bold flex items-center gap-1" style="color:#00A884">
                  <span v-if="detail.nama_bed">🛏 {{ detail.nama_bed }}</span>
                  <span v-else-if="detail.kebutuhan_bed" class="text-xs px-2 py-0.5 rounded-lg" style="background:rgba(0,168,132,.1);color:#00A884">{{ detail.kebutuhan_bed }}</span>
                  <span v-else style="color:var(--text-muted)">—</span>
                </p></div>
              <div v-if="detail.jaminan" class="space-y-0.5"><p class="text-xs font-medium" style="color:var(--text-muted)">Jaminan</p>
                <span class="inline-block text-xs font-semibold px-2.5 py-1 rounded-lg" style="background:#D1FAF0;color:#00A884">{{ jaminanLabel(detail.jaminan) }}</span></div>
              <div class="col-span-2 space-y-0.5"><p class="text-xs font-medium" style="color:var(--text-muted)">Waktu Booking</p><p class="text-sm font-bold font-mono" style="color:var(--text-primary)">{{ detail.created_at_fmt }}</p></div>
            </div>
            <div v-if="detail.dokter_kolab?.length" class="rounded-xl p-4" style="background:var(--bg-surface-2);border:1px solid var(--border-default)">
              <p class="text-xs font-bold mb-2 uppercase tracking-wider" style="color:var(--text-muted)">Dokter Kolaborasi</p>
              <div class="space-y-1.5">
                <div v-for="d in detail.dokter_kolab" :key="d.nama" class="flex items-center gap-2 text-xs">
                  <div class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background:#00A884"></div>
                  <span class="font-semibold" style="color:var(--text-primary)">{{ d.nama }}</span>
                  <span style="color:var(--text-muted)">({{ d.ket }})</span>
                </div>
              </div>
            </div>
            <div class="rounded-xl overflow-hidden" style="border:1px solid var(--border-default)">
              <div class="px-4 py-2.5" style="background:var(--bg-surface-2);border-bottom:1px solid var(--border-default)">
                <p class="text-xs font-bold uppercase tracking-wider" style="color:var(--text-muted)">Timeline Proses</p>
              </div>
              <div class="px-4 py-3 space-y-2.5">
                <div class="flex items-start gap-3">
                  <div class="w-2 h-2 rounded-full mt-1.5 flex-shrink-0" style="background:#00A884"></div>
                  <div><p class="text-xs font-semibold" style="color:var(--text-primary)">Booking dibuat</p><p class="text-xs font-mono" style="color:var(--text-muted)">{{ detail.created_at_fmt??'—' }}</p></div>
                </div>
                <div v-if="detail.approved_at_fmt||detail.confirmed_at_fmt" class="flex items-start gap-3">
                  <div class="w-2 h-2 rounded-full mt-1.5 flex-shrink-0" style="background:#0EA5E9"></div>
                  <div>
                    <p class="text-xs font-semibold" style="color:var(--text-primary)">{{ detail.sumber==='internal'?'Disetujui Admisi':'Bed Dikonfirmasi ICU' }}</p>
                    <p class="text-xs font-mono" style="color:var(--text-muted)">{{ detail.approved_at_fmt??detail.confirmed_at_fmt }}</p>
                  </div>
                </div>
                <div v-if="detail.verified_at_fmt" class="flex items-start gap-3">
                  <div class="w-2 h-2 rounded-full mt-1.5 flex-shrink-0" style="background:#059669"></div>
                  <div>
                    <p class="text-xs font-semibold" style="color:var(--text-primary)">{{ detail.sumber==='internal'?'Bed Terverifikasi ICU':'Terverifikasi Admisi' }}</p>
                    <p class="text-xs font-mono" style="color:var(--text-muted)">{{ detail.verified_at_fmt }}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>

  </AppLayout>
</template>

<style scoped>
.ym-charts {
  display: grid;
  grid-template-columns: 1fr;
  gap: 14px;
}
@media (min-width: 768px) {
  .ym-charts { grid-template-columns: repeat(2, 1fr); }
}
@media (min-width: 1200px) {
  .ym-charts { grid-template-columns: repeat(3, 1fr); }
}
</style>
