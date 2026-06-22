<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Doughnut } from 'vue-chartjs';
import { Chart as ChartJS, ArcElement, Tooltip } from 'chart.js';
ChartJS.register(ArcElement, Tooltip);
import AppLayout from '@/Layouts/AppLayout.vue';
import { useTheme } from '@/composables/useTheme.js';

const { theme } = useTheme();
const isDark   = computed(() => theme.value === 'dark');
const page     = usePage();
const authUser = computed(() => page.props.auth?.user ?? null);
const logoUrl  = `${import.meta.env.BASE_URL}images/logo-urip.png`;

const props = defineProps({
    semuaKamar:    { type: Array,  default: () => [] },
    statsExternal: { type: Object, default: () => ({ pending: 0, bed_confirmed: 0, terverifikasi: 0 }) },
    statsInternal: { type: Object, default: () => ({ pending_admisi: 0, pending_icu: 0, bed_verified: 0 }) },
    listAktif:     { type: Array,  default: () => [] },
    flash:         { type: Object, default: () => ({}) },
    userRole:      { type: String, default: 'guest' },
    filters:       { type: Object, default: () => ({}) },
});

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

const donutData = computed(() => ({
    datasets: [{
        data: [bedTerisi.value || 0, bedBooking.value || 0, bedKosong.value || 1],
        backgroundColor: ['#00A884', '#F59E0B', '#10B981'],
        borderWidth: 4,
        borderColor: 'transparent',
        hoverOffset: 6,
    }],
}));
const donutOptions = {
    cutout: '72%',
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false }, tooltip: { enabled: false } },
};

const now = ref(new Date());
let clockTimer = null;
const countdown = ref(30);
let refreshTimer = null;
const manualRefresh = () => {
    router.reload({ only: ['semuaKamar','statsExternal','statsInternal','listAktif','userRole'] });
    countdown.value = 30;
};

const tickerIdx   = ref(0);
const tickerPause = ref(false);
let tickerTimer   = null;
const tickerItem  = computed(() =>
    props.listAktif.length > 0 ? props.listAktif[tickerIdx.value % props.listAktif.length] : null
);

// Helper: tanggal lokal (timezone browser, bukan UTC)
const localDate = (offsetDays = 0) => {
    const d = new Date();
    d.setDate(d.getDate() + offsetDays);
    const y  = d.getFullYear();
    const m  = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${dd}`;
};

const filterJenis  = ref('semua');
const filterTglMul = ref(props.filters?.tgl_dari   ?? localDate(0));
const filterTglAkh = ref(props.filters?.tgl_sampai ?? localDate(0));
const searchQuery  = ref(props.filters?.search ?? '');

// Terapkan filter ke server (Inertia reload)
const applyDateFilter = () => {
    router.get(route('dashboard'), {
        tgl_dari:   filterTglMul.value,
        tgl_sampai: filterTglAkh.value,
        search:     searchQuery.value,
    }, { preserveState: true, replace: true, preserveScroll: true });
};

// Date presets (lokal)
const today     = localDate(0);
const yesterday = localDate(-1);
const week7     = localDate(-6);
const month30   = localDate(-29);

const setPreset = (dari, sampai) => {
    filterTglMul.value = dari;
    filterTglAkh.value = sampai;
    applyDateFilter();
};

const listFiltered = computed(() => props.listAktif.filter(p => {
    if (filterJenis.value !== 'semua' && p.jalur !== filterJenis.value) return false;
    if (searchQuery.value.trim()) {
        const q = searchQuery.value.trim().toLowerCase();
        return (p.nama_pasien ?? '').toLowerCase().includes(q)
            || (p.No_MR ?? '').toLowerCase().includes(q)
            || (p.diagnosa ?? p.Diagnosis ?? '').toLowerCase().includes(q)
            || (p.nama_bed ?? '').toLowerCase().includes(q)
            || (p.asal_ruang ?? '').toLowerCase().includes(q)
            || (p.Dokter ?? '').toLowerCase().includes(q);
    }
    return true;
}));

onMounted(() => {
    clockTimer   = setInterval(() => { now.value = new Date(); }, 1000);
    tickerTimer  = setInterval(() => {
        if (!tickerPause.value && props.listAktif.length > 1)
            tickerIdx.value = (tickerIdx.value + 1) % props.listAktif.length;
    }, 3500);
    refreshTimer = setInterval(() => {
        countdown.value--;
        if (countdown.value <= 0) {
            router.reload({ only: ['semuaKamar','statsExternal','statsInternal','listAktif','userRole'] });
            countdown.value = 30;
        }
    }, 1000);
});
onUnmounted(() => { clearInterval(clockTimer); clearInterval(tickerTimer); clearInterval(refreshTimer); });

const gIcon = (g) => g === 'L' ? '♂' : g === 'P' ? '♀' : '·';
const gTxt  = (g) => g === 'L' ? '#00A884' : g === 'P' ? '#8B5CF6' : '#6B7280';

const statusInfo = (status) => ({
    pending_icu:     { color: '#D97706', label: 'Menunggu ICU',     bg: '#FEF3C7' },
    bed_confirmed:   { color: '#00A884', label: 'Perlu Verifikasi', bg: '#ECFDF5' },
    admisi_verified: { color: '#059669', label: 'Terverifikasi',    bg: '#ECFDF5' },
    pending_admisi:  { color: '#D97706', label: 'Tunggu Admisi',    bg: '#FEF3C7' },
    bed_verified:    { color: '#059669', label: 'Bed Terverif',     bg: '#ECFDF5' },
    ditolak:         { color: '#DC2626', label: 'Ditolak',          bg: '#FEF2F2' },
}[status] ?? { color: '#6B7280', label: status, bg: '#F9FAFB' });

const jalurInfo = (jalur) => jalur === 'external'
    ? { color: '#7C3AED', label: 'External', bg: '#F5F3FF' }
    : { color: '#00A884', label: 'Internal', bg: '#ECFDF5' };

const getInitials = (name) => {
    if (!name || name === '-') return '?';
    const p = name.trim().split(' ');
    return p.length >= 2 ? (p[0][0] + p[1][0]).toUpperCase() : name.slice(0, 2).toUpperCase();
};

const avColors = [
    ['#ECFDF5','#00A884'], ['#F0FDF4','#059669'], ['#FDF4FF','#9333EA'],
    ['#FFF7ED','#EA580C'], ['#F0FDFA','#0D9488'],
];
const av = (i) => avColors[i % avColors.length];

const kpiCards = computed(() => [
    { label:'Total Bed',    val: totalBed.value,   icon:'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5',          iconBg:'#ECFDF5', iconColor:'#00A884' },
    { label:'Tersedia',     val: bedKosong.value,  icon:'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',                                 iconBg:'#ECFDF5', iconColor:'#059669' },
    { label:'Terisi',       val: bedTerisi.value,  icon:'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z', iconBg:'#ECFDF5', iconColor:'#00A884' },
    { label:'Booking',      val: bedBooking.value, icon:'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', iconBg:'#FFF7ED', iconColor:'#EA580C' },
    { label:'Hunian',       val: occupancy.value + '%', icon:'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', iconBg:'#FDF4FF', iconColor:'#9333EA' },
]);
</script>

<template>
<AppLayout :flash="flash" page-title="Dashboard ICU">
<div class="dash-wrap">

  <!-- ══ HERO BANNER ══════════════════════════════ -->
  <div class="dash-section">
    <div class="dash-hero">
      <div class="dash-hero-bg-circle dash-hero-circle-1"></div>
      <div class="dash-hero-bg-circle dash-hero-circle-2"></div>

      <!-- Hero top row -->
      <div class="dash-hero-top">
        <div class="dash-hero-brand">
          <div class="dash-hero-logo">
            <img :src="logoUrl" alt="Logo" class="w-9 h-9 object-contain" @error="$event.target.style.display='none'"/>
          </div>
          <div>
            <p class="dash-hero-subtitle">Monitoring Real-time</p>
            <h1 class="dash-hero-title">Ruang ICU &amp; HCU</h1>
            <p class="dash-hero-unit">{{ authUser?.unit_kerja ?? 'Intensive Care Unit' }}</p>
          </div>
        </div>
        <!-- Quick stat pills -->
        <div class="dash-hero-stats">
          <div v-for="s in [{l:'Total',v:totalBed,c:'rgba(255,255,255,0.95)'},{l:'Tersedia',v:bedKosong,c:'#86EFAC'},{l:'Terisi',v:bedTerisi,c:'#FCA5A5'},{l:'Booking',v:bedBooking,c:'#FDE68A'}]"
            :key="s.l" class="dash-hero-stat-pill">
            <p class="dash-hero-stat-num" :style="`color:${s.c}`">{{ s.v }}</p>
            <p class="dash-hero-stat-label">{{ s.l }}</p>
          </div>
        </div>
      </div>

      <!-- Ticker bar -->
      <div v-if="tickerItem" @mouseenter="tickerPause=true" @mouseleave="tickerPause=false" class="dash-ticker">
        <span class="dash-ticker-live">
          <span class="ping-dot" style="width:6px;height:6px;border-radius:50%;background:#00A884;display:inline-block"></span>
          LIVE
        </span>
        <span class="dash-ticker-name">{{ tickerItem.nama_pasien }}</span>
        <span class="dash-ticker-badge" :style="`background:${jalurInfo(tickerItem.jalur).bg};color:${jalurInfo(tickerItem.jalur).color}`">{{ jalurInfo(tickerItem.jalur).label }}</span>
        <span class="dash-ticker-badge" :style="`background:${statusInfo(tickerItem.status).bg};color:${statusInfo(tickerItem.status).color}`">{{ statusInfo(tickerItem.status).label }}</span>
        <span v-if="tickerItem.nama_bed" class="dash-ticker-bed">🏥 {{ tickerItem.nama_bed }}</span>
        <div class="dash-ticker-dots ml-auto">
          <span v-for="(_,i) in Array(Math.min(5,props.listAktif.length))" :key="i"
            :style="i===(tickerIdx%Math.min(5,props.listAktif.length))?'width:12px;height:4px;background:#fff;border-radius:4px':'width:4px;height:4px;background:rgba(255,255,255,0.3);border-radius:50%'"
            style="display:inline-block;transition:all .3s"></span>
        </div>
      </div>
    </div>
  </div>

  <!-- ══ KPI CARDS ═══════════════ -->
  <div class="dash-section">
    <div class="dash-kpi-grid">
      <div v-for="(c,i) in kpiCards" :key="c.label" class="dash-kpi-card kpi-card"
        @mouseenter="$event.currentTarget.style.transform='translateY(-4px)';$event.currentTarget.style.boxShadow='0 10px 28px rgba(0,0,0,0.1)'"
        @mouseleave="$event.currentTarget.style.transform='';$event.currentTarget.style.boxShadow=''">
        <div class="dash-kpi-icon" :style="`background:${c.iconBg}`">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" :style="`color:${c.iconColor}`">
            <path stroke-linecap="round" stroke-linejoin="round" :d="c.icon"/>
          </svg>
        </div>
        <p class="dash-kpi-val" :style="`color:${c.iconColor}`">{{ c.val }}</p>
        <p class="dash-kpi-label">{{ c.label }}</p>
        <div class="dash-kpi-bar" :style="`background:${c.iconBg}`">
          <div :style="`width:${i===4?occupancy+'%':totalBed>0?(c.val/totalBed*100).toFixed(0)+'%':'30%'};background:${c.iconColor}`"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Petugas Ruang mini stats -->
  <div v-if="userRole === 'petugas_ruang'" class="dash-section dash-petugas-stats">
    <div v-for="c in [{label:'Total BU Saya',val:statsInternal.pending_icu+statsInternal.bed_verified,color:'#00A884',bg:'#ECFDF5'},{label:'Menunggu ICU',val:statsInternal.pending_icu,color:'#D97706',bg:'#FEF3C7'},{label:'Bed Verified',val:statsInternal.bed_verified,color:'#059669',bg:'#ECFDF5'}]" :key="c.label"
      class="dash-petugas-card">
      <div class="dash-petugas-icon" :style="`background:${c.bg};color:${c.color}`">{{ c.val }}</div>
      <p class="text-xs font-semibold" style="color:var(--text-secondary)">{{ c.label }}</p>
    </div>
  </div>

  <!-- ══ MAIN 2-COL ═══════════════════════════════════════════ -->
  <div class="dash-main-grid">

    <!-- LEFT: Pasien table -->
    <div class="dash-table-card">
      <!-- Table header -->
      <div class="dash-table-header">
        <div class="dash-table-title-row">
          <div class="flex items-center gap-2">
            <p class="dash-table-title">Daftar Pasien Aktif</p>
            <span class="dash-count-badge">{{ listFiltered.length }}</span>
          </div>
          <div class="dash-search-wrap">
            <svg class="dash-search-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input v-model="searchQuery" placeholder="Cari nama, MR, ruang, DPJP..." class="dash-search-input"
              @keyup.enter="applyDateFilter"
              @focus="$event.target.style.borderColor='#00A884'" @blur="$event.target.style.borderColor='#E2E8F0'"/>
          </div>
        </div>

        <!-- Date range filter -->
        <div class="dash-date-filter-row">
          <!-- Preset buttons -->
          <div class="dash-preset-group">
            <button @click="setPreset(today, today)" class="dash-preset-btn" :class="filterTglMul===today&&filterTglAkh===today?'active':''">Hari ini</button>
            <button @click="setPreset(yesterday, yesterday)" class="dash-preset-btn" :class="filterTglMul===yesterday&&filterTglAkh===yesterday?'active':''">Kemarin</button>
            <button @click="setPreset(week7, today)" class="dash-preset-btn" :class="filterTglMul===week7&&filterTglAkh===today?'active':''">7 Hari</button>
            <button @click="setPreset(month30, today)" class="dash-preset-btn" :class="filterTglMul===month30&&filterTglAkh===today?'active':''">30 Hari</button>
          </div>
          <!-- Custom range -->
          <div class="dash-date-range">
            <svg class="w-3.5 h-3.5 flex-shrink-0" style="color:var(--text-muted)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <input type="date" v-model="filterTglMul" @change="applyDateFilter" class="dash-date-input"/>
            <span style="color:var(--text-muted);font-size:11px">s/d</span>
            <input type="date" v-model="filterTglAkh" :min="filterTglMul" @change="applyDateFilter" class="dash-date-input"/>
            <button @click="applyDateFilter" class="dash-apply-btn">Terapkan</button>
          </div>
          <!-- Jalur filter -->
          <div class="dash-filter-tabs">
            <button v-for="opt in [{v:'semua',l:'Semua'},{v:'external',l:'External'},{v:'internal',l:'Internal'}]" :key="opt.v"
              @click="filterJenis=opt.v" class="dash-filter-tab" :class="filterJenis===opt.v?'active':''">
              {{ opt.l }}
            </button>
          </div>
        </div>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto">
        <table class="dash-table">
          <thead>
            <tr>
              <th>Pasien</th>
              <th>Jalur</th>
              <th class="hidden sm:table-cell">Diagnosa</th>
              <th class="hidden lg:table-cell">Asal Ruang</th>
              <th class="hidden lg:table-cell">DPJP</th>
              <th>Bed / ICU</th>
              <th>Status</th>
              <th class="hidden lg:table-cell">Tgl</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="listFiltered.length===0">
              <td colspan="6">
                <div class="dash-empty">
                  <div class="dash-empty-icon">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                  </div>
                  <p class="font-semibold text-sm" style="color:var(--text-secondary)">
                    {{ userRole==='petugas_ruang' ? 'Belum ada BU yang Anda buat' : 'Tidak ada pasien aktif' }}
                  </p>
                  <p v-if="userRole==='petugas_ruang'" class="text-xs mt-1" style="color:var(--text-muted)">Buka Menu Rawat Inap untuk membuat BU</p>
                </div>
              </td>
            </tr>
            <tr v-for="(p,i) in listFiltered" :key="p.id"
              @mouseenter="$event.currentTarget.style.background='#F8FAFC'"
              @mouseleave="$event.currentTarget.style.background='transparent'">
              <td>
                <div class="flex items-center gap-3">
                  <div class="dash-patient-av" :style="`background:${av(i)[0]};color:${av(i)[1]}`">{{ getInitials(p.nama_pasien) }}</div>
                  <div>
                    <p class="font-semibold text-sm" style="color:#0F172A;max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ p.nama_pasien }}</p>
                    <p style="font-size:10px;color:#94A3B8;font-family:'DM Mono',monospace;margin-top:1px">{{ p.No_MR ? 'MR: '+p.No_MR : 'NIK: '+(p.no_identitas??'-') }}</p>
                  </div>
                </div>
              </td>
              <td><span class="dash-badge" :style="`background:${jalurInfo(p.jalur).bg};color:${jalurInfo(p.jalur).color}`">{{ jalurInfo(p.jalur).label }}</span></td>
              <td class="hidden sm:table-cell" style="max-width:150px">
                <p style="font-size:12px;color:#334155;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ p.diagnosa ?? p.Diagnosis ?? '—' }}</p>
              </td>
              <td class="hidden lg:table-cell" style="max-width:120px">
                <p style="font-size:11px;color:var(--text-secondary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ p.asal_ruang ?? '—' }}</p>
              </td>
              <td class="hidden lg:table-cell" style="max-width:120px">
                <p style="font-size:11px;color:var(--text-secondary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ p.Dokter ?? '—' }}</p>
              </td>
              <td>
                <p v-if="p.nama_bed" style="font-size:12px;font-weight:600;color:#059669">🏥 {{ p.nama_bed }}</p>
                <p v-else style="font-size:11px;color:#94A3B8">{{ p.kebutuhan_bed ?? '—' }}</p>
              </td>
              <td>
                <span class="dash-status-badge" :style="`background:${statusInfo(p.status).bg};color:${statusInfo(p.status).color}`">
                  <span style="width:5px;height:5px;border-radius:50%;flex-shrink:0" :style="`background:${statusInfo(p.status).color}`"></span>
                  {{ statusInfo(p.status).label }}
                </span>
              </td>
              <td class="hidden lg:table-cell" style="font-size:11px;color:#94A3B8;font-family:'DM Mono',monospace">{{ p.created_at }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Footer -->
      <div class="dash-table-footer">
        <p style="font-size:12px;color:#64748B">Menampilkan <strong style="color:#0F172A">{{ listFiltered.length }}</strong> dari <strong style="color:#0F172A">{{ listAktif.length }}</strong> pasien</p>
        <button @click="manualRefresh" class="dash-refresh-btn">
          <span class="ping-dot" style="width:7px;height:7px;border-radius:50%;background:#10B981;display:inline-block"></span>
          Auto-refresh {{ countdown }}s
        </button>
      </div>
    </div>

    <!-- RIGHT: Donut + Per kelas -->
    <div class="dash-right-col">

      <!-- Donut card -->
      <div class="dash-side-card">
        <div class="flex items-center justify-between mb-4">
          <p style="font-size:14px;font-weight:700;color:var(--text-primary)">Status Bed ICU</p>
          <a href="/icu/denah-bed" style="font-size:12px;font-weight:600;color:#00A884;text-decoration:none">Lihat semua →</a>
        </div>
        <div style="position:relative;height:160px;display:flex;align-items:center;justify-content:center">
          <Doughnut :data="donutData" :options="donutOptions" style="position:relative;z-index:1"/>
          <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;pointer-events:none">
            <p style="font-size:28px;font-weight:800;color:var(--text-primary);font-family:'DM Mono',monospace;line-height:1">{{ totalBed }}</p>
            <p style="font-size:10px;color:#94A3B8;font-weight:600;margin-top:2px">Total Bed</p>
          </div>
        </div>
        <div style="margin-top:16px;display:flex;flex-direction:column;gap:8px">
          <div v-for="item in [{label:'Terisi',val:bedTerisi,color:'#00A884',bg:'#ECFDF5'},{label:'Booking',val:bedBooking,color:'#F59E0B',bg:'#FEF3C7'},{label:'Tersedia',val:bedKosong,color:'#10B981',bg:'#ECFDF5'}]" :key="item.label"
            style="display:flex;align-items:center;justify-content:space-between">
            <div style="display:flex;align-items:center;gap:8px">
              <span style="width:10px;height:10px;border-radius:50%;flex-shrink:0" :style="`background:${item.color}`"></span>
              <span style="font-size:12px;color:var(--text-secondary);font-weight:500">{{ item.label }}</span>
            </div>
            <div style="display:flex;align-items:center;gap:8px">
              <div style="width:70px;height:5px;border-radius:99px;background:#F1F5F9;overflow:hidden">
                <div style="height:100%;border-radius:99px;transition:width .5s" :style="`width:${totalBed>0?(item.val/totalBed*100).toFixed(0)+'%':'0%'};background:${item.color}`"></div>
              </div>
              <span style="font-size:12px;font-weight:700;color:var(--text-primary);font-family:'DM Mono',monospace;min-width:20px;text-align:right">{{ item.val }}</span>
            </div>
          </div>
        </div>
        <!-- Occupancy bar -->
        <div style="margin-top:14px;padding-top:14px;border-top:1px solid var(--border-default)">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
            <span style="font-size:11px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.05em">Tingkat Hunian</span>
            <span style="font-size:13px;font-weight:700;font-family:'DM Mono',monospace" :style="`color:${occupancy>80?'#DC2626':occupancy>50?'#D97706':'#059669'}`">{{ occupancy }}%</span>
          </div>
          <div style="height:8px;border-radius:99px;background:#F1F5F9;overflow:hidden">
            <div style="height:100%;border-radius:99px;transition:width .5s" :style="`width:${occupancy}%;background:${occupancy>80?'#DC2626':occupancy>50?'#F59E0B':'#10B981'}`"></div>
          </div>
        </div>
      </div>

      <!-- Per kelas card -->
      <div class="dash-side-card">
        <p style="font-size:14px;font-weight:700;color:var(--text-primary);margin-bottom:14px">Per Jenis ICU</p>
        <div style="display:flex;flex-direction:column;gap:12px">
          <div v-if="bedPerKelas.length===0" style="text-align:center;padding:20px 0;color:#94A3B8;font-size:12px">Tidak ada data</div>
          <div v-for="k in bedPerKelas" :key="k.nama">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px">
              <span style="font-size:12px;font-weight:600;color:var(--text-primary)">{{ k.nama }}</span>
              <div style="display:flex;gap:6px;align-items:center">
                <span style="font-size:10px;font-weight:600;padding:2px 6px;border-radius:6px;background:#ECFDF5;color:#059669">{{ k.kosong }} kosong</span>
                <span style="font-size:11px;font-weight:700;color:var(--text-primary);font-family:'DM Mono',monospace">{{ k.total }}</span>
              </div>
            </div>
            <div style="height:6px;border-radius:99px;background:#F1F5F9;overflow:hidden;display:flex;gap:1px">
              <div style="border-radius:99px;background:#10B981;transition:width .5s" :style="`width:${k.total>0?(k.kosong/k.total*100).toFixed(0)+'%':'0%'}`"></div>
              <div style="background:#F59E0B;transition:width .5s" :style="`width:${k.total>0?(k.booking/k.total*100).toFixed(0)+'%':'0%'}`"></div>
              <div style="border-radius:99px;background:#00A884;transition:width .5s" :style="`width:${k.total>0?(k.terisi/k.total*100).toFixed(0)+'%':'0%'}`"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</AppLayout>
</template>

<style scoped>
.dash-wrap { min-height: 100%; background: var(--bg-main); font-family:'Inter','Plus Jakarta Sans',sans-serif; }
.dash-section { padding: 16px 20px 0; }
@media (min-width: 640px) { .dash-section { padding: 20px 24px 0; } }

/* Hero */
.dash-hero {
    background: linear-gradient(135deg, #00A884 0%, #007a61 50%, #005a48 100%);
    border-radius: 16px; overflow: hidden; position: relative; min-height: 130px;
}
.dash-hero-bg-circle {
    position: absolute; border-radius: 50%; pointer-events: none;
    background: radial-gradient(circle, rgba(255,255,255,0.08), transparent);
}
.dash-hero-circle-1 { width: 280px; height: 280px; top: -80px; right: -60px; }
.dash-hero-circle-2 { width: 200px; height: 200px; bottom: -60px; left: 180px; opacity: 0.5; }
.dash-hero-top {
    padding: 18px 24px;
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 14px;
}
.dash-hero-brand { display: flex; align-items: center; gap: 14px; }
.dash-hero-logo {
    width: 48px; height: 48px; border-radius: 14px; flex-shrink: 0;
    background: rgba(255,255,255,0.15); border: 1px solid rgba(255,255,255,0.2);
    display: flex; align-items: center; justify-content: center;
}
.dash-hero-subtitle { color: rgba(255,255,255,0.6); font-size: 11px; font-weight: 500; }
.dash-hero-title { color: #fff; font-size: clamp(16px,2.5vw,22px); font-weight: 800; letter-spacing: -0.02em; line-height: 1.2; }
.dash-hero-unit { color: rgba(255,255,255,0.45); font-size: 11px; margin-top: 2px; }
.dash-hero-stats { display: flex; gap: 8px; flex-wrap: wrap; }
.dash-hero-stat-pill {
    text-align: center; padding: 10px 14px; border-radius: 12px; min-width: 66px;
    background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.15);
}
.dash-hero-stat-num { font-size: 22px; font-weight: 800; font-family:'DM Mono',monospace; line-height: 1; }
.dash-hero-stat-label { color: rgba(255,255,255,0.55); font-size: 11px; margin-top: 4px; }
.dash-ticker {
    background: rgba(0,0,0,0.18); border-top: 1px solid rgba(255,255,255,0.08);
    padding: 8px 24px; display: flex; align-items: center; gap: 10px; overflow: hidden; flex-wrap: nowrap;
}
.dash-ticker-live {
    background: #fff; color: #00A884; font-size: 10px; font-weight: 800;
    padding: 3px 8px; border-radius: 6px; letter-spacing: 0.07em;
    display: flex; align-items: center; gap: 5px; flex-shrink: 0;
}
.dash-ticker-name { color: #fff; font-weight: 700; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 200px; }
.dash-ticker-badge { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 20px; flex-shrink: 0; }
.dash-ticker-bed { color: rgba(255,255,255,0.7); font-size: 11px; flex-shrink: 0; }
.dash-ticker-dots { display: flex; gap: 4px; align-items: center; }

/* KPI grid */
.dash-kpi-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}
@media (min-width: 640px) { .dash-kpi-grid { grid-template-columns: repeat(3, 1fr); } }
@media (min-width: 1024px) { .dash-kpi-grid { grid-template-columns: repeat(5, 1fr); } }
.dash-kpi-card {
    background: var(--bg-card); border-radius: 14px;
    border: 1px solid var(--border-default); box-shadow: var(--shadow-card);
    padding: 16px; transition: transform .2s, box-shadow .2s; cursor: default;
}
.dash-kpi-icon {
    width: 42px; height: 42px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center; margin-bottom: 12px;
}
.dash-kpi-val { font-size: 28px; font-weight: 800; font-family:'DM Mono',monospace; line-height: 1; margin-bottom: 4px; }
.dash-kpi-label { font-size: 11px; color: var(--text-muted); font-weight: 500; margin-bottom: 10px; }
.dash-kpi-bar { height: 4px; border-radius: 99px; overflow: hidden; }
.dash-kpi-bar > div { height: 100%; border-radius: 99px; transition: width .6s; }

/* Petugas stats */
.dash-petugas-stats {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;
}
.dash-petugas-card {
    background: var(--bg-card); border-radius: 12px; border: 1px solid var(--border-default);
    padding: 12px 14px; display: flex; align-items: center; gap: 10px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.04);
}
.dash-petugas-icon {
    width: 36px; height: 36px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 800; font-family:'DM Mono',monospace; flex-shrink: 0;
}

/* Main grid */
.dash-main-grid {
    padding: 16px 20px 24px;
    display: grid; grid-template-columns: 1fr; gap: 16px;
}
@media (min-width: 640px) { .dash-main-grid { padding: 20px 24px 24px; } }
@media (min-width: 1024px) { .dash-main-grid { grid-template-columns: 1fr 300px; } }
@media (min-width: 1280px) { .dash-main-grid { grid-template-columns: 1fr 320px; } }

/* Table card */
.dash-table-card {
    background: var(--bg-card); border-radius: 14px; border: 1px solid var(--border-default);
    box-shadow: var(--shadow-card); overflow: hidden;
}
.dash-table-header { padding: 14px 18px; border-bottom: 1px solid var(--border-default); }
.dash-table-title-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; flex-wrap: wrap; gap: 8px; }
.dash-table-title { font-size: 14px; font-weight: 700; color: var(--text-primary); }
.dash-count-badge { background: #ECFDF5; color: #00A884; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 20px; }
.dash-search-wrap { position: relative; }
.dash-search-icon { position: absolute; left: 9px; top: 50%; transform: translateY(-50%); width: 14px; height: 14px; color: #9CA3AF; }
.dash-search-input {
    padding: 6px 12px 6px 30px; border: 1.5px solid #E2E8F0; border-radius: 10px;
    font-size: 12px; color: #0F172A; background: #F8FAFC; outline: none; width: 200px;
    transition: border-color .2s;
}
@media (max-width: 640px) { .dash-search-input { width: 150px; } }
.dash-filter-row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.dash-filter-tabs { display: flex; gap: 2px; background: #F1F5F9; border-radius: 10px; padding: 3px; }
.dash-filter-tab { padding: 4px 10px; border-radius: 7px; font-size: 11px; font-weight: 600; border: none; cursor: pointer; transition: all .15s; background: transparent; color: #64748B; }
.dash-filter-tab.active { background: #fff; color: #00A884; box-shadow: 0 1px 4px rgba(0,0,0,0.08); }
.dash-date-filter { display: flex; align-items: center; gap: 4px; font-size: 11px; color: #64748B; }
.dash-date-input { padding: 4px 6px; border: 1.5px solid #E2E8F0; border-radius: 7px; font-size: 10px; color: #0F172A; background: #F8FAFC; outline: none; }
.dash-reset-btn { padding: 4px 10px; border-radius: 8px; font-size: 11px; font-weight: 600; background: #FEF2F2; color: #DC2626; border: 1.5px solid rgba(220,38,38,0.15); cursor: pointer; }
.dash-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.dash-table thead tr { background: #F8FAFC; border-bottom: 2px solid #E2E8F0; }
.dash-table th { padding: 10px 14px; text-align: left; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #64748B; }
.dash-table tbody tr { border-bottom: 1px solid #F1F5F9; transition: background .12s; }
.dash-table td { padding: 10px 14px; vertical-align: middle; }
.dash-empty { display: flex; flex-direction: column; align-items: center; gap: 10px; padding: 40px 16px; }
.dash-empty-icon { width: 44px; height: 44px; border-radius: 12px; background: #F1F5F9; display: flex; align-items: center; justify-content: center; color: #CBD5E1; }
.dash-empty-icon svg { width: 22px; height: 22px; }
.dash-patient-av { width: 32px; height: 32px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; flex-shrink: 0; }
.dash-badge { font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 20px; }
.dash-status-badge { font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 20px; display: inline-flex; align-items: center; gap: 4px; }
.dash-table-footer { padding: 10px 18px; border-top: 1px solid #F1F5F9; display: flex; align-items: center; justify-content: space-between; background: #FAFAFA; flex-wrap: wrap; gap: 6px; }
.dash-refresh-btn { display: flex; align-items: center; gap: 5px; font-size: 11px; color: #64748B; background: none; border: none; cursor: pointer; padding: 4px 8px; border-radius: 8px; transition: background .15s; }
.dash-refresh-btn:hover { background: #F1F5F9; }

/* Date filter row */
.dash-date-filter-row { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-top:8px; }
.dash-preset-group { display:flex; gap:3px; background:#F1F5F9; border-radius:10px; padding:3px; }
.dash-preset-btn { padding:4px 10px; border-radius:7px; font-size:11px; font-weight:600; border:none; cursor:pointer; background:transparent; color:#64748B; transition:all .15s; white-space:nowrap; }
.dash-preset-btn.active { background:#fff; color:#00A884; box-shadow:0 1px 4px rgba(0,0,0,0.08); }
.dash-date-range { display:flex; align-items:center; gap:5px; flex-wrap:wrap; }
.dash-apply-btn { padding:5px 12px; border-radius:8px; font-size:11px; font-weight:700; background:#00A884; color:#fff; border:none; cursor:pointer; }
.dash-apply-btn:hover { filter:brightness(1.08); }

/* Right column */
.dash-right-col { display: flex; flex-direction: column; gap: 14px; }
.dash-side-card { background: var(--bg-card); border-radius: 14px; border: 1px solid var(--border-default); box-shadow: var(--shadow-card); padding: 18px; }
</style>
