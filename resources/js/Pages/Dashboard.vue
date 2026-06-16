<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Doughnut } from 'vue-chartjs';
import { Chart as ChartJS, ArcElement, Tooltip } from 'chart.js';
ChartJS.register(ArcElement, Tooltip);

import AppLayout from '@/Layouts/AppLayout.vue';
import { useTheme } from '@/composables/useTheme.js';

const { theme } = useTheme();
const isDark = computed(() => theme.value === 'dark');
const page   = usePage();
const authUser = computed(() => page.props.auth?.user ?? null);
const logoUrl  = `${import.meta.env.BASE_URL}images/logo-urip.png`;

// ── Props ──────────────────────────────────────────────────
const props = defineProps({
    semuaKamar:    { type: Array,  default: () => [] },
    statsExternal: { type: Object, default: () => ({ pending: 0, bed_confirmed: 0, terverifikasi: 0 }) },
    statsInternal: { type: Object, default: () => ({ pending_admisi: 0, pending_icu: 0, bed_verified: 0 }) },
    listAktif:     { type: Array,  default: () => [] },
    flash:         { type: Object, default: () => ({}) },
});

// ── Bed stats ──────────────────────────────────────────────
const bedKosong  = computed(() => props.semuaKamar.filter(k => k.Status === 'KOSONG').length);
const bedBooking = computed(() => props.semuaKamar.filter(k => k.Status === 'BOOKING').length);
const bedTerisi  = computed(() => props.semuaKamar.filter(k => k.Status === 'ISI').length);
const totalBed   = computed(() => props.semuaKamar.length);
const occupancy  = computed(() =>
    totalBed.value > 0 ? Math.round((bedTerisi.value / totalBed.value) * 100) : 0
);

// Bed per jenis ICU
const bedPerKelas = computed(() => {
    const map = {};
    props.semuaKamar.forEach(k => {
        const key = k.nama_kelas ?? 'Lainnya';
        if (!map[key]) map[key] = { nama: key, total: 0, kosong: 0, terisi: 0, booking: 0 };
        map[key].total++;
        if (k.Status === 'KOSONG')  map[key].kosong++;
        else if (k.Status === 'ISI') map[key].terisi++;
        else                         map[key].booking++;
    });
    return Object.values(map);
});

// ── Donut ──────────────────────────────────────────────────
const donutData = computed(() => ({
    datasets: [{
        data: [
            bedTerisi.value  || 0,
            bedBooking.value || 0,
            bedKosong.value  || 1,
        ],
        backgroundColor: ['#E74C3C', '#E67E22', '#00A884'],
        borderWidth: 0,
        hoverOffset: 6,
    }],
}));
const donutOptions = {
    cutout: '72%',
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false }, tooltip: { enabled: false } },
};

// ── Clock ──────────────────────────────────────────────────
const now = ref(new Date());
let clockTimer = null;
const formattedTime = computed(() =>
    now.value.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
);
const formattedDate = computed(() =>
    now.value.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
);

// ── Auto-refresh ───────────────────────────────────────────
const countdown = ref(30);
let refreshTimer = null;
const manualRefresh = () => {
    router.reload({ only: ['semuaKamar', 'statsExternal', 'statsInternal', 'listAktif'] });
    countdown.value = 30;
};

// ── Ticker pasien berjalan ─────────────────────────────────
const tickerIdx   = ref(0);
const tickerPause = ref(false);
let tickerTimer   = null;

const tickerItem = computed(() =>
    props.listAktif.length > 0
        ? props.listAktif[tickerIdx.value % props.listAktif.length]
        : null
);

// ── Filter list ────────────────────────────────────────────
const filterJenis  = ref('semua');
const filterTglMul = ref('');
const filterTglAkh = ref('');
const searchQuery  = ref('');

const listFiltered = computed(() => props.listAktif.filter(p => {
    if (filterJenis.value !== 'semua' && p.jalur !== filterJenis.value) return false;
    if (filterTglMul.value && p.created_at_raw < filterTglMul.value) return false;
    if (filterTglAkh.value && p.created_at_raw > filterTglAkh.value) return false;
    if (searchQuery.value.trim()) {
        const q = searchQuery.value.trim().toLowerCase();
        return (p.nama_pasien ?? '').toLowerCase().includes(q)
            || (p.No_MR ?? '').toLowerCase().includes(q)
            || (p.diagnosa ?? p.Diagnosis ?? '').toLowerCase().includes(q)
            || (p.nama_bed ?? '').toLowerCase().includes(q);
    }
    return true;
}));

onMounted(() => {
    clockTimer = setInterval(() => { now.value = new Date(); }, 1000);
    tickerTimer = setInterval(() => {
        if (!tickerPause.value && props.listAktif.length > 1) {
            tickerIdx.value = (tickerIdx.value + 1) % props.listAktif.length;
        }
    }, 3500);
    refreshTimer = setInterval(() => {
        countdown.value--;
        if (countdown.value <= 0) {
            router.reload({ only: ['semuaKamar', 'statsExternal', 'statsInternal', 'listAktif'] });
            countdown.value = 30;
        }
    }, 1000);
});
onUnmounted(() => {
    clearInterval(clockTimer);
    clearInterval(tickerTimer);
    clearInterval(refreshTimer);
});

// ── Helpers (palet design.md v3.1) ────────────────────────
const gBg   = (g) => g === 'L' ? 'rgba(52,152,219,0.15)' : g === 'P' ? 'rgba(142,68,173,0.15)' : 'rgba(90,107,124,0.12)';
const gTxt  = (g) => g === 'L' ? '#3498DB' : g === 'P' ? '#8E44AD' : '#5A6B7C';
const gIcon = (g) => g === 'L' ? '♂' : g === 'P' ? '♀' : '?';

const statusInfo = (status) => ({
    pending_icu:     { color: '#E67E22', label: 'Menunggu ICU',       bg: '#FDF3E9' },
    bed_confirmed:   { color: '#3498DB', label: 'Perlu Verifikasi',   bg: '#EAF4FB' },
    admisi_verified: { color: '#27AE60', label: 'Terverifikasi',       bg: '#EBF9F1' },
    pending_admisi:  { color: '#E67E22', label: 'Tunggu Admisi',      bg: '#FDF3E9' },
    pending_icu_int: { color: '#3498DB', label: 'Antrian ICU',        bg: '#EAF4FB' },
    bed_verified:    { color: '#27AE60', label: 'Bed Terverif',       bg: '#EBF9F1' },
    ditolak:         { color: '#E74C3C', label: 'Ditolak',            bg: '#FDEDEC' },
}[status] ?? { color: '#5A6B7C', label: status, bg: 'var(--bg-input)' });

const jalurInfo = (jalur) => jalur === 'external'
    ? { color: '#8E44AD', label: 'External', bg: '#F4ECF7' }
    : { color: '#3498DB', label: 'Internal', bg: '#EAF4FB' };

const bedStatusColor = (s) => ({
    KOSONG:  '#00A884',
    BOOKING: '#E67E22',
    ISI:     '#E74C3C',
}[s?.toUpperCase()] ?? '#5A6B7C');

const bedStatusLabel = (s) => ({
    KOSONG:  'Tersedia',
    BOOKING: 'Booking',
    ISI:     'Terisi',
}[s?.toUpperCase()] ?? s);

const getInitials = (name) => {
    if (!name || name === '-') return '?';
    const p = name.trim().split(' ');
    return p.length >= 2 ? (p[0][0] + p[1][0]).toUpperCase() : name.slice(0, 2).toUpperCase();
};

// Soft avatar palette — light & dark compatible
const avatarPalette = [
    ['rgba(0,168,132,0.15)',  '#00A884'],
    ['rgba(52,152,219,0.15)', '#3498DB'],
    ['rgba(142,68,173,0.15)', '#8E44AD'],
    ['rgba(230,126,34,0.15)', '#E67E22'],
    ['rgba(39,174,96,0.15)',  '#27AE60'],
];
const av = (i) => avatarPalette[i % avatarPalette.length];
</script>

<template>
    <AppLayout :flash="flash" page-title="Dashboard ICU">
        <div class="min-h-screen" style="background:var(--bg-main); font-family:'Inter','Plus Jakarta Sans',sans-serif">

            <!-- ══════════════ HERO BANNER ══════════════════════════════ -->
            <div class="p-4 sm:p-6 pb-0">
                <div class="hero-banner relative overflow-hidden" style="min-height:180px; border-radius:20px">

                    <!-- Decorative blobs -->
                    <div class="absolute -top-16 -right-16 w-64 h-64 rounded-full pointer-events-none"
                        style="background:radial-gradient(circle,rgba(255,255,255,0.12),transparent)"></div>
                    <div class="absolute -bottom-8 left-1/4 w-40 h-40 rounded-full pointer-events-none"
                        style="background:radial-gradient(circle,rgba(255,255,255,0.07),transparent)"></div>

                    <div class="relative px-6 sm:px-10 py-7 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                        <!-- Kiri -->
                        <div class="flex items-center gap-4">
                            <div class="w-14 h-14 rounded-2xl overflow-hidden flex-shrink-0 flex items-center justify-center"
                                style="background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.25)">
                                <img :src="logoUrl" alt="Logo RS" class="w-12 h-12 object-contain"
                                    @error="$event.target.style.display='none'"/>
                            </div>
                            <div>
                                <p class="font-medium text-sm" style="color:rgba(255,255,255,0.7)">Monitoring Real-time</p>
                                <h1 class="font-bold text-white leading-tight"
                                    style="font-size:clamp(20px,3vw,32px); letter-spacing:-0.02em">
                                    Ruang ICU &amp; HCU
                                </h1>
                                <p class="text-sm mt-0.5" style="color:rgba(255,255,255,0.6)">
                                    {{ authUser?.unit_kerja ?? 'Intensive Care Unit' }}
                                </p>
                            </div>
                        </div>

                        <!-- Kanan: quick stats -->
                        <div class="flex gap-3 flex-wrap">
                            <div v-for="stat in [
                                { label:'Total Bed', val:totalBed, color:'rgba(255,255,255,0.9)' },
                                { label:'Tersedia',  val:bedKosong,  color:'#6BFFD9' },
                                { label:'Terisi',    val:bedTerisi,  color:'#FF9090' },
                            ]" :key="stat.label"
                                class="text-center px-4 py-2 rounded-xl"
                                style="background:rgba(255,255,255,0.12); border:1px solid rgba(255,255,255,0.18); min-width:80px">
                                <p class="font-extrabold text-2xl leading-none" :style="`color:${stat.color}; font-family:'DM Mono',monospace`">
                                    {{ stat.val }}
                                </p>
                                <p class="text-xs mt-0.5" style="color:rgba(255,255,255,0.65)">{{ stat.label }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Ticker pasien berjalan -->
                    <div v-if="tickerItem"
                        class="flex items-center gap-3 px-6 sm:px-10 py-2.5 overflow-hidden"
                        style="background:rgba(0,0,0,0.2); backdrop-filter:blur(8px)"
                        @mouseenter="tickerPause=true" @mouseleave="tickerPause=false">
                        <!-- LIVE badge -->
                        <span class="flex items-center gap-1.5 font-bold px-2.5 py-1 rounded-lg flex-shrink-0"
                            style="background:rgba(255,255,255,0.9); color:#00A884; font-size:11px; letter-spacing:.08em">
                            <span class="w-1.5 h-1.5 rounded-full pulse-emerald" style="background:#00A884"></span>
                            LIVE
                        </span>

                        <!-- Content -->
                        <div class="flex items-center gap-3 overflow-hidden flex-1" style="font-size:clamp(11px,1.1vw,15px)">
                            <span class="font-bold text-white truncate">{{ tickerItem.nama_pasien }}</span>
                            <span style="color:rgba(255,255,255,0.4)">·</span>
                            <span class="px-2 py-0.5 rounded-full flex-shrink-0 text-xs font-semibold"
                                :style="`background:${jalurInfo(tickerItem.jalur).bg}; color:${jalurInfo(tickerItem.jalur).color}`">
                                {{ jalurInfo(tickerItem.jalur).label }}
                            </span>
                            <span class="px-2 py-0.5 rounded-full flex-shrink-0 text-xs font-semibold"
                                :style="`background:${statusInfo(tickerItem.status).bg}; color:${statusInfo(tickerItem.status).color}`">
                                {{ statusInfo(tickerItem.status).label }}
                            </span>
                            <span v-if="tickerItem.nama_bed" class="text-xs flex-shrink-0" style="color:rgba(255,255,255,0.75)">
                                🏥 {{ tickerItem.nama_bed }}
                            </span>
                            <span class="text-xs truncate" style="color:rgba(255,255,255,0.5)">
                                {{ tickerItem.diagnosa ?? tickerItem.Diagnosis ?? '' }}
                            </span>
                        </div>

                        <!-- Progress dots -->
                        <div class="flex gap-1 flex-shrink-0">
                            <span v-for="(_, i) in Array(Math.min(8, props.listAktif.length))" :key="i"
                                class="rounded-full transition-all duration-300"
                                :style="i === (tickerIdx % Math.min(8, props.listAktif.length))
                                    ? 'width:16px; height:5px; background:#fff'
                                    : 'width:5px; height:5px; background:rgba(255,255,255,0.3)'">
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══════════════ BODY ══════════════════════════════════════ -->
            <div class="p-4 sm:p-6 space-y-5">
                <div class="card-dark overflow-hidden">
                    <!-- Header + filter -->
                    <div class="px-5 pt-4 pb-3 space-y-3" style="border-bottom:1px solid var(--border-default)">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <p class="font-semibold" style="color:var(--text-primary); font-size:14px">
                                    Daftar Pasien Aktif
                                </p>
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                    style="background:rgba(0,168,132,0.12); color:#00A884">
                                    {{ listFiltered.length }}
                                </span>
                            </div>
                            <!-- Search -->
                            <div class="relative">
                                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 pointer-events-none"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                                    style="color:var(--text-muted)">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                <input v-model="searchQuery" placeholder="Cari nama, No MR, diagnosa, bed..."
                                    class="text-xs pl-9 pr-3 py-2.5 rounded-xl outline-none w-full sm:w-72"
                                    style="border:1px solid var(--border-input); background:var(--bg-input);
                                           color:var(--text-primary); transition:border-color .2s ease"
                                    @focus="e => e.target.style.borderColor='var(--border-input-focus)'"
                                    @blur="e => e.target.style.borderColor='var(--border-input)'"/>
                            </div>
                        </div>

                        <!-- Filter row -->
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="flex gap-0.5 p-0.5 rounded-xl" style="background:var(--bg-input); border:1px solid var(--border-default)">
                                <button v-for="opt in [{v:'semua',l:'Semua'},{v:'external',l:'External'},{v:'internal',l:'Internal'}]"
                                    :key="opt.v" @click="filterJenis=opt.v"
                                    class="text-xs font-semibold px-3 py-1.5 rounded-lg transition-all"
                                    :style="filterJenis===opt.v
                                        ? 'background:var(--bg-surface); color:var(--text-accent); box-shadow:0 1px 4px rgba(0,168,132,.12)'
                                        : 'color:var(--text-secondary)'">
                                    {{ opt.l }}
                                </button>
                            </div>
                            <div class="flex items-center gap-1.5 text-xs" style="color:var(--text-secondary)">
                                Dari:
                                <input type="date" v-model="filterTglMul" class="text-xs px-2.5 py-1.5 rounded-lg outline-none"
                                    style="border:1px solid var(--border-input); background:var(--bg-input); color:var(--text-primary)"/>
                                s/d
                                <input type="date" v-model="filterTglAkh" :min="filterTglMul" class="text-xs px-2.5 py-1.5 rounded-lg outline-none"
                                    style="border:1px solid var(--border-input); background:var(--bg-input); color:var(--text-primary)"/>
                            </div>
                            <button v-if="filterJenis!=='semua'||filterTglMul||filterTglAkh||searchQuery"
                                @click="filterJenis='semua';filterTglMul='';filterTglAkh='';searchQuery=''"
                                class="text-xs px-2.5 py-1.5 rounded-lg"
                                style="background:var(--pill-reject-bg); color:var(--pill-reject-color); border:1px solid rgba(231,76,60,0.2)">
                                ✕ Reset
                            </button>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full dark-table row-stagger">
                            <thead>
                                <tr>
                                    <th class="text-left px-5 py-3">Pasien</th>
                                    <th class="text-left px-3 py-3">Jalur</th>
                                    <th class="text-left px-3 py-3 hidden sm:table-cell">Diagnosa</th>
                                    <th class="text-left px-3 py-3">Bed / ICU</th>
                                    <th class="text-left px-3 py-3">Status</th>
                                    <th class="text-left px-3 py-3 hidden lg:table-cell">Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="listFiltered.length === 0">
                                    <td colspan="6" class="text-center py-16">
                                        <svg class="w-12 h-12 mx-auto mb-3" style="color:var(--text-muted); opacity:.4"
                                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        <p class="text-sm" style="color:var(--text-secondary)">Tidak ada pasien aktif</p>
                                    </td>
                                </tr>
                                <tr v-for="(p, i) in listFiltered" :key="p.id"
                                    :style="i === (tickerIdx % Math.max(1, listFiltered.length))
                                        ? 'background:rgba(0,168,132,0.05)'
                                        : ''">
                                    <!-- Pasien -->
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-xl flex items-center justify-center text-xs font-bold flex-shrink-0"
                                                :style="`background:${av(i)[0]}; color:${av(i)[1]}`">
                                                {{ getInitials(p.nama_pasien) }}
                                            </div>
                                            <div class="min-w-0">
                                                <p class="font-semibold truncate" style="color:var(--text-primary); font-size:13.5px">
                                                    {{ p.nama_pasien }}
                                                </p>
                                                <p class="font-mono" style="color:var(--text-muted); font-size:10px">
                                                    {{ p.No_MR ? 'MR: '+p.No_MR : 'NIK: '+(p.no_identitas??'-') }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <!-- Jalur -->
                                    <td class="px-3 py-3">
                                        <span class="badge"
                                            :style="`background:${jalurInfo(p.jalur).bg}; color:${jalurInfo(p.jalur).color}`">
                                            {{ jalurInfo(p.jalur).label }}
                                        </span>
                                    </td>
                                    <!-- Diagnosa -->
                                    <td class="px-3 py-3 hidden sm:table-cell">
                                        <p class="text-xs truncate" style="color:var(--text-primary); max-width:170px">
                                            {{ p.diagnosa ?? p.Diagnosis ?? '—' }}
                                        </p>
                                    </td>
                                    <!-- Bed -->
                                    <td class="px-3 py-3">
                                        <p v-if="p.nama_bed" class="text-xs font-semibold" style="color:#00A884">🏥 {{ p.nama_bed }}</p>
                                        <p v-else class="text-xs" style="color:var(--text-muted)">{{ p.kebutuhan_bed ?? '—' }}</p>
                                    </td>
                                    <!-- Status -->
                                    <td class="px-3 py-3">
                                        <span class="badge"
                                            :style="`background:${statusInfo(p.status).bg}; color:${statusInfo(p.status).color}`">
                                            {{ statusInfo(p.status).label }}
                                        </span>
                                    </td>
                                    <!-- Tanggal -->
                                    <td class="px-3 py-3 hidden lg:table-cell">
                                        <p class="text-xs font-mono" style="color:var(--text-secondary)">{{ p.created_at }}</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Footer -->
                    <div class="px-5 py-3 flex items-center justify-between"
                        style="border-top:1px solid var(--border-default)">
                        <p class="text-xs" style="color:var(--text-secondary)">
                            Menampilkan <strong style="color:var(--text-primary)">{{ listFiltered.length }}</strong>
                            dari <strong style="color:var(--text-primary)">{{ listAktif.length }}</strong> pasien
                        </p>
                        <div class="flex items-center gap-2 text-xs" style="color:var(--text-secondary)">
                            <span class="relative flex h-2 w-2">
                                <span class="ping-dot absolute inline-flex h-full w-full rounded-full" style="background:#00A884; opacity:.75"></span>
                                <span class="relative inline-flex h-2 w-2 rounded-full" style="background:#00A884"></span>
                            </span>
                            Auto-refresh {{ countdown }}s
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
