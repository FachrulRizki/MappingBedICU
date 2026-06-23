<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Doughnut, Bar } from 'vue-chartjs';
import { Chart as ChartJS, ArcElement, Tooltip, CategoryScale, LinearScale, BarElement } from 'chart.js';
ChartJS.register(ArcElement, Tooltip, CategoryScale, LinearScale, BarElement);
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTheme } from '@/composables/useTheme.js';

const { theme } = useTheme();
const page     = usePage();
const authUser = computed(() => page.props.auth?.user ?? null);
const logoUrl     = `${import.meta.env.BASE_URL}images/logo-urip.png`;
const doctorImgUrl = `${import.meta.env.BASE_URL}images/welcome-doctors.svg`;

const props = defineProps({
    semuaKamar:    { type: Array,  default: () => [] },
    statsExternal: { type: Object, default: () => ({ pending: 0, bed_confirmed: 0, terverifikasi: 0 }) },
    statsInternal: { type: Object, default: () => ({ pending_admisi: 0, pending_icu: 0, bed_verified: 0 }) },
    listAktif:     { type: Array,  default: () => [] },
    flash:         { type: Object, default: () => ({}) },
    userRole:      { type: String, default: 'guest' },
    filters:       { type: Object, default: () => ({}) },
});

// ── Bed stats ─────────────────────────────────────────────────────────────────
const bedKosong  = computed(() => props.semuaKamar.filter(k => k.Status === 'KOSONG').length);
const bedBooking = computed(() => props.semuaKamar.filter(k => k.Status === 'BOOKING').length);
const bedTerisi  = computed(() => props.semuaKamar.filter(k => k.Status === 'ISI').length);
const totalBed   = computed(() => props.semuaKamar.length);
const occupancy  = computed(() => totalBed.value > 0 ? Math.round((bedTerisi.value / totalBed.value) * 100) : 0);

const bedPerKelas = computed(() => {
    const map = {};
    props.semuaKamar.forEach(k => {
        const key = k.nama_kelas ?? 'Lainnya';
        if (!map[key]) map[key] = { nama: key, total: 0, kosong: 0, terisi: 0, booking: 0 };
        map[key].total++;
        if (k.Status === 'KOSONG') map[key].kosong++;
        else if (k.Status === 'ISI') map[key].terisi++;
        else map[key].booking++;
    });
    return Object.values(map);
});

// ── Donut chart ───────────────────────────────────────────────────────────────
const donutData = computed(() => ({
    datasets: [{
        data: [bedTerisi.value || 0, bedBooking.value || 0, bedKosong.value || 1],
        backgroundColor: ['#00A884', '#F59E0B', '#10B981'],
        borderWidth: 3, borderColor: 'transparent', hoverOffset: 6,
    }],
}));
const donutOptions = {
    cutout: '74%', responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false }, tooltip: { enabled: false } },
};

// ── Bar chart helpers ─────────────────────────────────────────────────────────
const topN = (arr, key, n = 8) => {
    const map = {};
    arr.forEach(p => {
        const v = (p[key] ?? '').trim();
        if (v && v !== '-' && v !== '—') map[v] = (map[v] ?? 0) + 1;
    });
    return Object.entries(map).sort((a, b) => b[1] - a[1]).slice(0, n);
};
const makeBarData = (entries, color) => ({
    labels: entries.map(([k]) => k.length > 24 ? k.slice(0, 24) + '…' : k),
    datasets: [{ data: entries.map(([, v]) => v), backgroundColor: color, borderRadius: 6, barThickness: 20 }],
});
const barOpt = {
    indexAxis: 'y', responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => ` ${c.raw} kasus` } } },
    scales: {
        x: { grid: { color: 'rgba(0,0,0,.04)' }, ticks: { font: { size: 11 }, color: '#64748B' } },
        y: { grid: { display: false }, ticks: { font: { size: 11 }, color: '#374151' } },
    },
};

// ── Date helpers ──────────────────────────────────────────────────────────────
const ld = (n = 0) => {
    const d = new Date(); d.setDate(d.getDate() + n);
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
};
const today = ld(0), yesterday = ld(-1), week7 = ld(-6), month30 = ld(-29);

// ── Filters ───────────────────────────────────────────────────────────────────
const filterJenis  = ref('semua');
const filterTglMul = ref(props.filters?.tgl_dari   ?? today);
const filterTglAkh = ref(props.filters?.tgl_sampai ?? today);
const searchQuery  = ref('');

const applyDate = () => router.get(window.location.pathname,
    { tgl_dari: filterTglMul.value, tgl_sampai: filterTglAkh.value },
    { preserveState: true, replace: true, preserveScroll: true });
const setPreset = (a, b) => { filterTglMul.value = a; filterTglAkh.value = b; applyDate(); };

const listFiltered = computed(() => props.listAktif.filter(p => {
    if (filterJenis.value !== 'semua' && p.jalur !== filterJenis.value) return false;
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return true;
    return ['nama_pasien','No_MR','diagnosa','Diagnosis','nama_bed','asal_ruang','Dokter']
        .some(k => (p[k] ?? '').toLowerCase().includes(q));
}));

const diagData     = computed(() => makeBarData(topN(listFiltered.value, 'diagnosa'), '#00A884'));
const ruangData    = computed(() => makeBarData(topN(listFiltered.value, 'asal_ruang'), '#7C3AED'));
const bedData      = computed(() => makeBarData(topN(listFiltered.value, 'nama_bed'), '#0EA5E9'));
const statusDist   = computed(() => [
    { key:'pending_admisi',  label:'Tunggu Admisi', color:'#D97706' },
    { key:'pending_icu',     label:'Menunggu ICU',  color:'#3B82F6' },
    { key:'pending_icu_int', label:'Antrian ICU',   color:'#6366F1' },
    { key:'bed_confirmed',   label:'Bed Confirmed', color:'#00A884' },
    { key:'bed_verified',    label:'Bed Verified',  color:'#059669' },
    { key:'admisi_verified', label:'Terverifikasi', color:'#10B981' },
    { key:'ditolak',         label:'Ditolak',       color:'#DC2626' },
].map(d => ({ ...d, count: listFiltered.value.filter(p => p.status === d.key).length }))
 .filter(d => d.count > 0));

// ── Ticker & refresh ──────────────────────────────────────────────────────────
const countdown   = ref(30);
const tickerIdx   = ref(0);
const tickerPause = ref(false);
const tickerItem  = computed(() =>
    listFiltered.value.length > 0 ? listFiltered.value[tickerIdx.value % listFiltered.value.length] : null);
let tickerTimer = null, refreshTimer = null;
const manualRefresh = () => { router.reload({ only: ['semuaKamar','statsExternal','statsInternal','listAktif','userRole'] }); countdown.value = 30; };

onMounted(() => {
    tickerTimer  = setInterval(() => {
        if (!tickerPause.value && listFiltered.value.length > 1)
            tickerIdx.value = (tickerIdx.value + 1) % listFiltered.value.length;
    }, 3500);
    refreshTimer = setInterval(() => { if (--countdown.value <= 0) { manualRefresh(); countdown.value = 30; } }, 1000);
});
onUnmounted(() => { clearInterval(tickerTimer); clearInterval(refreshTimer); });

// ── Style helpers ─────────────────────────────────────────────────────────────
const sI = s => ({ pending_icu:{color:'#D97706',label:'Menunggu ICU',bg:'#FEF3C7'}, bed_confirmed:{color:'#00A884',label:'Perlu Verif',bg:'#ECFDF5'}, admisi_verified:{color:'#059669',label:'Terverifikasi',bg:'#ECFDF5'}, pending_admisi:{color:'#D97706',label:'Tunggu Admisi',bg:'#FEF3C7'}, pending_icu_int:{color:'#3B82F6',label:'Antrian ICU',bg:'#EFF6FF'}, bed_verified:{color:'#059669',label:'Bed Terverif',bg:'#ECFDF5'}, ditolak:{color:'#DC2626',label:'Ditolak',bg:'#FEF2F2'} }[s] ?? {color:'#6B7280',label:s,bg:'#F9FAFB'});
const jI = j => j === 'external' ? {color:'#7C3AED',label:'External',bg:'#F5F3FF'} : {color:'#00A884',label:'Internal',bg:'#ECFDF5'};
const ini = n => { if (!n || n==='-') return '?'; const p=n.trim().split(' '); return p.length>=2?(p[0][0]+p[1][0]).toUpperCase():n.slice(0,2).toUpperCase(); };
const avC = [['#ECFDF5','#00A884'],['#EFF6FF','#3B82F6'],['#F5F3FF','#7C3AED'],['#FFF7ED','#EA580C'],['#F0FDFA','#0D9488']];
const av  = i => avC[i % avC.length];

const kpiCards = computed(() => [
    { label:'Total Bed', val:totalBed.value,   sub:'Seluruh ICU/HCU', pct:100, bg:'rgba(0,168,132,.12)', color:'#00A884', icon:'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5' },
    { label:'Tersedia',  val:bedKosong.value,  sub:`${totalBed.value>0?Math.round(bedKosong.value/totalBed.value*100):0}%`, pct:totalBed.value>0?Math.round(bedKosong.value/totalBed.value*100):0, bg:'rgba(16,185,129,.12)', color:'#059669', icon:'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
    { label:'Terisi',    val:bedTerisi.value,  sub:'Sedang digunakan', pct:totalBed.value>0?Math.round(bedTerisi.value/totalBed.value*100):0, bg:'rgba(13,148,136,.12)', color:'#0D9488', icon:'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z' },
    { label:'Booking',   val:bedBooking.value, sub:'Menunggu proses',  pct:totalBed.value>0?Math.round(bedBooking.value/totalBed.value*100):0, bg:'rgba(245,158,11,.14)', color:'#D97706', icon:'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
    { label:'Hunian',    val:occupancy.value+'%', sub:occupancy.value>=85?'Padat':occupancy.value>=55?'Stabil':'Aman', pct:occupancy.value, bg:'rgba(124,58,237,.12)', color:'#7C3AED', icon:'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
]);
</script>

<template>
<AppLayout :flash="flash" page-title="Dashboard ICU">
<div class="db-wrap">

<!-- HERO -->
<div class="db-sec">
  <div class="db-hero">
    <div class="db-hero-copy">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;flex-wrap:wrap">
        <div class="db-hero-logo"><img :src="logoUrl" alt="Logo" style="width:36px;height:36px;object-fit:contain" @error="$event.target.style.display='none'"/></div>
        <div style="min-width:0">
          <p style="color:rgba(255,255,255,.6);font-size:11px;font-weight:500">ICU Command Center</p>
          <h1 style="color:#fff;font-size:clamp(18px,4vw,30px);font-weight:900;letter-spacing:-.02em;line-height:1.1">Monitoring Bed ICU</h1>
          <p style="color:rgba(255,255,255,.45);font-size:11px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:280px">{{ authUser?.unit_kerja ?? 'Intensive Care Unit' }}</p>
        </div>
      </div>
      <div v-if="tickerItem" class="db-ticker" @mouseenter="tickerPause=true" @mouseleave="tickerPause=false">
        <span class="db-ticker-live">● LIVE</span>
        <span class="db-ticker-name">{{ tickerItem.nama_pasien }}</span>
        <span class="db-tbadge" :style="`background:${jI(tickerItem.jalur).bg};color:${jI(tickerItem.jalur).color}`">{{ jI(tickerItem.jalur).label }}</span>
        <span class="db-tbadge" :style="`background:${sI(tickerItem.status).bg};color:${sI(tickerItem.status).color}`">{{ sI(tickerItem.status).label }}</span>
        <span v-if="tickerItem.nama_bed" style="color:rgba(255,255,255,.7);font-size:11px;flex-shrink:0">🏥 {{ tickerItem.nama_bed }}</span>
      </div>
    </div>

    <!-- Doctor illustration -->
    <div class="db-hero-vis" aria-hidden="true">
      <div class="db-char">
        <img :src="doctorImgUrl" alt="Dokter ICU" style="width:100%;height:100%;object-fit:contain"/>
      </div>
    </div>
  </div>
</div>

<!-- KPI -->
<div class="db-sec">
  <div class="db-kgrid">
    <div v-for="c in kpiCards" :key="c.label" class="db-kcard">
      <div class="db-kicon" :style="`background:${c.bg}`">
        <svg style="width:18px;height:18px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" :style="`color:${c.color};width:18px;height:18px`">
          <path stroke-linecap="round" stroke-linejoin="round" :d="c.icon"/>
        </svg>
      </div>
      <p class="db-kval" :style="`color:${c.color}`">{{ c.val }}</p>
      <p class="db-klabel">{{ c.label }}</p>
      <p class="db-ksub">{{ c.sub }}</p>
      <div class="db-kbar" :style="`background:${c.bg}`"><div :style="`width:${Math.min(100,Math.max(0,c.pct))}%;background:${c.color}`"></div></div>
    </div>
  </div>
</div>

<!-- Filter bar -->
<div class="db-sec">
  <div class="db-fbar">
    <div class="db-presets">
      <button @click="setPreset(today,today)"       class="db-pbtn" :class="{active:filterTglMul===today&&filterTglAkh===today}">Hari ini</button>
      <button @click="setPreset(yesterday,yesterday)" class="db-pbtn" :class="{active:filterTglMul===yesterday&&filterTglAkh===yesterday}">Kemarin</button>
      <button @click="setPreset(week7,today)"       class="db-pbtn" :class="{active:filterTglMul===week7&&filterTglAkh===today}">7 Hari</button>
      <button @click="setPreset(month30,today)"     class="db-pbtn" :class="{active:filterTglMul===month30&&filterTglAkh===today}">30 Hari</button>
    </div>
    <div class="db-drange">
      <input type="date" v-model="filterTglMul" @change="applyDate" class="db-dinput"/>
      <span style="color:var(--text-muted);font-size:11px">s/d</span>
      <input type="date" v-model="filterTglAkh" :min="filterTglMul" @change="applyDate" class="db-dinput"/>
      <button @click="applyDate" class="db-abtn">Terapkan</button>
    </div>
    <div class="db-swrap">
      <svg style="position:absolute;left:9px;top:50%;transform:translateY(-50%);width:14px;height:14px;color:#9CA3AF" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
      <input v-model="searchQuery" placeholder="Cari nama, MR, diagnosa, ruang..." class="db-sinput"/>
    </div>
    <div class="db-jtabs">
      <button v-for="o in [{v:'semua',l:'Semua'},{v:'external',l:'Ext'},{v:'internal',l:'Int'}]" :key="o.v"
        @click="filterJenis=o.v" class="db-jtab" :class="{active:filterJenis===o.v}">{{ o.l }}</button>
    </div>
  </div>
</div>

<!-- Charts row -->
<div class="db-sec db-charts" style="padding-bottom:28px">

  <!-- Top Diagnosa -->
  <div class="db-card" style="padding:18px">
    <p class="db-card-t" style="margin-bottom:14px">
      <span style="display:inline-flex;align-items:center;gap:6px">
        <span style="width:10px;height:10px;border-radius:3px;background:#00A884;display:inline-block"></span>
        Top Diagnosa
      </span>
    </p>
    <div v-if="diagData.labels.length" :style="`height:${Math.max(150, diagData.labels.length*34+20)}px`">
      <Bar :data="diagData" :options="barOpt"/>
    </div>
    <div v-else class="db-cempty"><svg style="width:32px;height:32px;opacity:.25" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10"/></svg><p>Belum ada data</p></div>
  </div>

  <!-- Top Asal Ruang -->
    <div class="db-card" style="padding:18px">
        <p class="db-card-t" style="margin-bottom:14px">
        <span style="display:inline-flex;align-items:center;gap:6px">
            <span style="width:10px;height:10px;border-radius:3px;background:#7C3AED;display:inline-block"></span>
            Top Asal Ruang
        </span>
        </p>
        <div v-if="ruangData.labels.length" :style="`height:${Math.max(150, ruangData.labels.length*34+20)}px`">
        <Bar :data="ruangData" :options="barOpt"/>
        </div>
        <div v-else class="db-cempty"><svg style="width:32px;height:32px;opacity:.25" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857"/></svg><p>Belum ada data</p></div>
    </div>

     <!-- Top Bed -->
    <div class="db-card" style="padding:18px">
        <p class="db-card-t" style="margin-bottom:14px">
        <span style="display:inline-flex;align-items:center;gap:6px">
            <span style="width:10px;height:10px;border-radius:3px;background:#0EA5E9;display:inline-block"></span>
            Top Bed
        </span>
        </p>
        <div v-if="bedData.labels.length" :style="`height:${Math.max(150, bedData.labels.length*34+20)}px`">
        <Bar :data="bedData" :options="barOpt"/>
        </div>
        <div v-else class="db-cempty"><svg style="width:32px;height:32px;opacity:.25" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg><p>Belum ada data</p></div>
    </div>
</div>

<!-- Main: table + donut side -->
<div class="db-sec db-main">
  <!-- Tabel -->
  <div class="db-card">
    <div class="db-card-hdr">
      <p class="db-card-t">Daftar Pasien Aktif</p>
      <span class="db-cpill">{{ listFiltered.length }}</span>
    </div>
    <div style="overflow-x:auto">
      <table class="db-tbl">
        <thead><tr>
          <th>Pasien</th><th>Jalur</th>
          <th class="hidden md:table-cell">Diagnosa</th>
          <th class="hidden lg:table-cell">Asal Ruang</th>
          <th class="hidden lg:table-cell">DPJP</th>
          <th>Bed</th><th>Status</th>
          <th class="hidden xl:table-cell">Tanggal</th>
        </tr></thead>
        <tbody>
          <tr v-if="!listFiltered.length"><td colspan="8">
            <div class="db-empty">
              <svg style="width:40px;height:40px;opacity:.25" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
              <p style="font-size:13px;font-weight:600;color:var(--text-secondary)">{{ userRole==='petugas_ruang'?'Belum ada BU':'Tidak ada pasien aktif' }}</p>
            </div>
          </td></tr>
          <tr v-for="(p,i) in listFiltered" :key="p.id">
            <td>
              <div style="display:flex;align-items:center;gap:10px">
                <div class="db-av" :style="`background:${av(i)[0]};color:${av(i)[1]}`">{{ ini(p.nama_pasien) }}</div>
                <div style="min-width:0">
                  <p style="font-size:13px;font-weight:600;color:var(--text-primary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:140px">{{ p.nama_pasien }}</p>
                  <p style="font-size:10px;font-family:'DM Mono',monospace;color:var(--text-muted)">{{ p.No_MR ?? '—' }}</p>
                </div>
              </div>
            </td>
            <td><span class="db-badge" :style="`background:${jI(p.jalur).bg};color:${jI(p.jalur).color}`">{{ jI(p.jalur).label }}</span></td>
            <td class="hidden md:table-cell"><p class="db-trunc">{{ p.diagnosa ?? p.Diagnosis ?? '—' }}</p></td>
            <td class="hidden lg:table-cell"><p class="db-trunc sm">{{ p.asal_ruang ?? '—' }}</p></td>
            <td class="hidden lg:table-cell"><p class="db-trunc sm">{{ p.Dokter ?? '—' }}</p></td>
            <td>
              <p v-if="p.nama_bed" class="db-bed">{{ p.nama_bed }}</p>
              <p v-else class="db-bed-e">{{ p.kebutuhan_bed ?? '—' }}</p>
            </td>
            <td>
              <span class="db-sbadge" :style="`background:${sI(p.status).bg};color:${sI(p.status).color}`">
                <span style="width:5px;height:5px;border-radius:50%;flex-shrink:0" :style="`background:${sI(p.status).color}`"></span>
                {{ sI(p.status).label }}
              </span>
            </td>
            <td class="hidden xl:table-cell" style="font-size:11px;font-family:'DM Mono',monospace;color:var(--text-muted)">{{ p.created_at }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="db-tfooter">
      <p style="font-size:12px;color:var(--text-secondary)">Tampil <strong style="color:var(--text-primary)">{{ listFiltered.length }}</strong> / <strong style="color:var(--text-primary)">{{ listAktif.length }}</strong></p>
      <button @click="manualRefresh" class="db-rbtn">
        <span style="width:6px;height:6px;border-radius:50%;background:#10B981;flex-shrink:0;display:inline-block"></span>
        Refresh {{ countdown }}s
      </button>
    </div>
  </div>
</div>

</div>
</AppLayout>
</template>

<style scoped>
.db-wrap { min-height:100%; font-family:'Inter','Plus Jakarta Sans',sans-serif; background:var(--bg-main); padding-bottom:4px; }
.db-sec  { padding:16px 20px 0; }
@media(min-width:640px){ .db-sec { padding:20px 24px 0; } }

/* ── Hero ────────────────────────────────────────────────────────────────── */
.db-hero {
  background:linear-gradient(135deg,#00A884 0%,#007a61 55%,#005a48 100%);
  border-radius:16px; padding:22px 28px 18px; position:relative; overflow:hidden;
  border:1px solid rgba(255,255,255,.1); box-shadow:0 12px 32px rgba(0,90,68,.25);
  display:grid; grid-template-columns:1fr; gap:18px; align-items:center;
}
@media(min-width:860px){ .db-hero { grid-template-columns:1fr auto; } }
.db-hero::before { content:''; position:absolute; width:260px; height:260px; border-radius:50%; right:-80px; top:-100px; background:radial-gradient(circle,rgba(255,255,255,.1),transparent); pointer-events:none; }
.db-hero-copy { position:relative; z-index:2; }
.db-hero-logo { width:44px; height:44px; border-radius:13px; background:rgba(255,255,255,.18); border:1px solid rgba(255,255,255,.22); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.db-ticker { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-top:10px; }
.db-ticker-live { background:#fff; color:#00A884; font-size:10px; font-weight:900; padding:3px 7px; border-radius:6px; letter-spacing:.07em; flex-shrink:0; }
.db-ticker-name { color:#fff; font-weight:700; font-size:13px; max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.db-tbadge { font-size:11px; font-weight:600; padding:2px 8px; border-radius:20px; flex-shrink:0; }
.db-hbtn { display:inline-flex; align-items:center; gap:7px; padding:8px 14px; border-radius:10px; font-size:12px; font-weight:800; text-decoration:none; transition:transform .15s; white-space:nowrap; }
.db-hbtn:hover { transform:translateY(-1px); }
.db-hbtn.solid { background:#fff; color:#007a61; box-shadow:0 6px 18px rgba(0,0,0,.12); }
.db-hbtn.outline { background:rgba(255,255,255,.14); color:#fff; border:1px solid rgba(255,255,255,.2); }

/* Animated character */
.db-hero-vis { position:relative; min-height:190px; min-width:280px; align-self:center; display:none; }
@media(min-width:860px){ .db-hero-vis { display:block; } }
.db-char {
  position:absolute; right:0; bottom:-16px; width:min(260px,100%); aspect-ratio:1;
}

/* ── KPI ───────────────────────────────────────────────────────────────────── */
.db-kgrid { display:grid; grid-template-columns:repeat(2,1fr); gap:12px; }
@media(min-width:640px)  { .db-kgrid { grid-template-columns:repeat(3,1fr); } }
@media(min-width:1024px) { .db-kgrid { grid-template-columns:repeat(5,1fr); } }
.db-kcard { background:var(--bg-card); border:1px solid var(--border-default); border-radius:14px; padding:16px; box-shadow:var(--shadow-card); transition:transform .2s; }
.db-kcard:hover { transform:translateY(-3px); }
.db-kicon { width:40px; height:40px; border-radius:11px; display:flex; align-items:center; justify-content:center; margin-bottom:10px; }
.db-kval  { font-size:26px; font-weight:800; font-family:'DM Mono',monospace; line-height:1; margin-bottom:3px; }
.db-klabel{ font-size:11px; color:var(--text-muted); font-weight:500; margin-bottom:6px; }
.db-ksub  { font-size:10px; color:var(--text-muted); font-weight:700; margin-bottom:10px; min-height:13px; }
.db-kbar  { height:4px; border-radius:99px; overflow:hidden; }
.db-kbar > div { height:100%; border-radius:99px; transition:width .6s; }

/* Petugas */
.db-pgrid { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; }
@media(max-width:480px){ .db-pgrid { grid-template-columns:1fr; } }
.db-pcard { background:var(--bg-card); border:1px solid var(--border-default); border-radius:12px; padding:12px 14px; display:flex; align-items:center; gap:10px; }
.db-picon { width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:15px; font-weight:800; font-family:'DM Mono',monospace; flex-shrink:0; }

/* Filter bar */
.db-fbar    { background:var(--bg-surface); border:1px solid var(--border-default); border-radius:12px; padding:12px 16px; display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.db-presets { display:flex; gap:2px; background:var(--bg-input); border:1px solid var(--border-default); border-radius:10px; padding:3px; flex-shrink:0; flex-wrap:wrap; }
.db-pbtn    { padding:4px 10px; border-radius:7px; font-size:11px; font-weight:600; border:none; cursor:pointer; background:transparent; color:var(--text-secondary); transition:all .14s; white-space:nowrap; }
.db-pbtn.active { background:var(--bg-card); color:#00A884; box-shadow:0 1px 4px rgba(0,0,0,.08); }
.db-drange  { display:flex; align-items:center; gap:5px; flex-wrap:wrap; }
.db-dinput  { padding:5px 8px; border:1.5px solid var(--border-default); border-radius:8px; font-size:11px; color:var(--text-primary); background:var(--bg-input); outline:none; min-width:0; }
.db-abtn    { padding:5px 12px; border-radius:8px; font-size:11px; font-weight:700; background:#00A884; color:#fff; border:none; cursor:pointer; }
.db-swrap   { position:relative; flex:1; min-width:140px; }
.db-sinput  { width:100%; padding:7px 12px 7px 30px; border:1.5px solid var(--border-default); border-radius:10px; font-size:12px; color:var(--text-primary); background:var(--bg-input); outline:none; box-sizing:border-box; }
.db-sinput:focus { border-color:#00A884; box-shadow:0 0 0 3px rgba(0,168,132,.1); }
.db-jtabs   { display:flex; gap:2px; background:var(--bg-input); border:1px solid var(--border-default); border-radius:10px; padding:3px; flex-shrink:0; }
.db-jtab    { padding:4px 10px; border-radius:7px; font-size:11px; font-weight:600; border:none; cursor:pointer; background:transparent; color:var(--text-secondary); transition:all .14s; }
.db-jtab.active { background:var(--bg-card); color:#00A884; box-shadow:0 1px 4px rgba(0,0,0,.08); }

/* Main 2-col */
.db-main { display:grid; grid-template-columns:1fr; gap:14px; }
@media(min-width:1024px){ .db-main { grid-template-columns:1fr; } }
.db-sidebar { display:flex; flex-direction:column; gap:14px; }

/* Card */
.db-card      { background:var(--bg-card); border:1px solid var(--border-default); border-radius:14px; box-shadow:var(--shadow-card); overflow:hidden; }
.db-card-hdr  { padding:14px 18px 10px; border-bottom:1px solid var(--border-default); display:flex; align-items:center; gap:8px; }
.db-card-t    { font-size:13px; font-weight:700; color:var(--text-primary); }
.db-cpill     { background:#ECFDF5; color:#00A884; font-size:11px; font-weight:700; padding:2px 8px; border-radius:20px; }

/* Table */
.db-tbl { width:100%; border-collapse:collapse; font-size:13px; min-width:540px; }
.db-tbl thead tr { background:var(--bg-surface,#F8FAFC); border-bottom:2px solid var(--border-default); }
.db-tbl th  { padding:9px 14px; text-align:left; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--text-muted); }
.db-tbl tbody tr { border-bottom:1px solid var(--border-row,#F1F5F9); transition:background .12s; }
.db-tbl tbody tr:hover { background:var(--bg-row-hover,#F8FAFC) !important; }
.db-tbl td  { padding:9px 14px; vertical-align:middle; }
.db-empty   { display:flex; flex-direction:column; align-items:center; gap:8px; padding:40px 16px; }
.db-av      { width:30px; height:30px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:10px; font-weight:700; flex-shrink:0; }
.db-badge   { font-size:11px; font-weight:600; padding:2px 8px; border-radius:20px; }
.db-sbadge  { font-size:11px; font-weight:600; padding:2px 8px; border-radius:20px; display:inline-flex; align-items:center; gap:4px; }
.db-trunc   { max-width:150px; overflow:hidden; font-size:12px; text-overflow:ellipsis; white-space:nowrap; color:var(--text-secondary); }
.db-trunc.sm{ font-size:11px; }
.db-bed     { font-size:12px; font-weight:700; color:var(--text-accent); }
.db-bed-e   { font-size:12px; font-weight:600; color:var(--text-muted); }
.db-tfooter { padding:10px 18px; border-top:1px solid var(--border-default); background:var(--bg-surface,#FAFAFA); display:flex; align-items:center; justify-content:space-between; gap:8px; flex-wrap:wrap; }
.db-rbtn    { display:flex; align-items:center; gap:5px; font-size:11px; color:var(--text-secondary); background:none; border:none; cursor:pointer; padding:4px 8px; border-radius:8px; transition:background .14s; }
.db-rbtn:hover { background:var(--bg-input); }

/* Charts row */
.db-charts { display:grid; grid-template-columns:1fr; gap:14px; padding-bottom:28px !important; }
@media(min-width:768px)  { .db-charts { grid-template-columns:repeat(2,1fr); } }
@media(min-width:1200px) { .db-charts { grid-template-columns:repeat(3,1fr); } }
.db-cempty  { display:flex; flex-direction:column; align-items:center; gap:8px; padding:24px; color:var(--text-muted); font-size:12px; }

@media(max-width:640px){
  .db-kgrid  { grid-template-columns:repeat(2,1fr); }
  .db-fbar   { flex-direction:column; align-items:stretch; }
  .db-swrap  { min-width:0; }
  .db-drange { width:100%; }
  .db-dinput { flex:1; min-width:0; }
  .db-presets{ width:100%; justify-content:space-between; }
  .db-jtabs  { width:100%; justify-content:space-between; }
  .db-pgrid  { grid-template-columns:1fr; }
}
</style>