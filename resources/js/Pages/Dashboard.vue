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
        backgroundColor: ['#4A90D9', '#E0923A', '#2DD9A4'],
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

// ── Helpers ────────────────────────────────────────────────
const gBg   = (g) => g === 'L' ? 'rgba(74,144,217,0.2)' : g === 'P' ? 'rgba(217,81,122,0.2)' : 'rgba(142,168,158,0.2)';
const gTxt  = (g) => g === 'L' ? '#4A90D9' : g === 'P' ? '#D9517A' : '#8EA89E';
const gIcon = (g) => g === 'L' ? '♂' : g === 'P' ? '♀' : '?';

const statusInfo = (status) => ({
    pending_icu:     { color: '#E0923A', label: 'Menunggu ICU', bg: 'rgba(224,146,58,0.15)' },
    bed_confirmed:   { color: '#4A90D9', label: 'Perlu Verifikasi', bg: 'rgba(74,144,217,0.15)' },
    admisi_verified: { color: '#2DD9A4', label: 'Terverifikasi', bg: 'rgba(45,217,164,0.15)' },
    pending_admisi:  { color: '#E0923A', label: 'Tunggu Admisi', bg: 'rgba(224,146,58,0.15)' },
    pending_icu_int: { color: '#4A90D9', label: 'Antrian ICU', bg: 'rgba(74,144,217,0.15)' },
    bed_verified:    { color: '#2DD9A4', label: 'Bed Terverif', bg: 'rgba(45,217,164,0.15)' },
    ditolak:         { color: '#E07050', label: 'Ditolak', bg: 'rgba(224,112,80,0.15)' },
}[status] ?? { color: '#8EA89E', label: status, bg: 'rgba(142,168,158,0.15)' });

const jalurInfo = (jalur) => jalur === 'external'
    ? { color: '#4A90D9', label: 'External', bg: 'rgba(74,144,217,0.15)' }
    : { color: '#E0923A', label: 'Internal', bg: 'rgba(224,146,58,0.15)' };

const bedStatusColor = (s) => ({
    KOSONG:  '#2DD9A4',
    BOOKING: '#E0923A',
    ISI:     '#4A90D9',
}[s?.toUpperCase()] ?? '#8EA89E');

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

const avatarPalette = [
    ['rgba(45,217,164,0.25)', '#2DD9A4'],
    ['rgba(74,144,217,0.25)', '#4A90D9'],
    ['rgba(224,112,80,0.25)', '#E07050'],
    ['rgba(224,146,58,0.25)', '#E0923A'],
    ['rgba(74,234,181,0.25)', '#4AEAB5'],
];
const av = (i) => avatarPalette[i % avatarPalette.length];
</script>

<template>
    <AppLayout :flash="flash" page-title="Dashboard ICU">
        <div class="min-h-screen" style="background:var(--bg-main); font-family:'Plus Jakarta Sans',sans-serif">

            <!-- ═══════════════════════════════════════════════════════════
                 HERO BANNER — full bleed, cocok ditampilkan di TV monitor
            ═══════════════════════════════════════════════════════════ -->
            <div class="p-4 sm:p-6 space-y-2">
                <div class="hero-banner relative overflow-hidden" style="min-height:220px">

                    <!-- Decorative circles -->
                    <div class="absolute -top-20 -right-20 w-72 h-72 rounded-full opacity-10"
                        style="background:radial-gradient(circle, #2DD9A4, transparent)"></div>
                    <div class="absolute -bottom-10 right-1/3 w-48 h-48 rounded-full opacity-10"
                        style="background:radial-gradient(circle, #4A90D9, transparent)"></div>

                    <div class="relative px-6 sm:px-10 py-8 flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6">

                        <!-- Kiri: identitas -->
                        <div class="flex items-center gap-5">
                            <div class="w-16 h-16 rounded-2xl overflow-hidden flex-shrink-0 hidden sm:flex items-center justify-center"
                                style="background:rgba(45,217,164,0.12); border:1px solid rgba(45,217,164,0.25)">
                                <img :src="logoUrl" alt="Logo RS" class="w-14 h-14 object-contain"
                                    @error="$event.target.style.display='none'"/>
                            </div>
                            <div>
                                <p class="text-white/70 text-sm font-medium">Monitoring ICU Real-time</p>
                                <h1 class="text-white font-bold leading-tight"
                                    style="font-size:clamp(22px,3.5vw,40px); letter-spacing:-0.02em">
                                    Ruang ICU &amp; HCU
                                </h1>
                                <p class="text-white/60 text-sm mt-0.5">
                                    {{ authUser?.unit_kerja ?? 'Intensive Care Unit' }}
                                </p>
                            </div>
                        </div>

                        <!-- Tengah: stats besar -->
                        <div class="flex items-center gap-6 sm:gap-10">
                            <div class="text-center">
                                <p class="text-white/60 text-xs uppercase tracking-widest">Kosong</p>
                                <p class="font-bold text-white leading-none mt-1"
                                    style="font-size:clamp(36px,5vw,64px); font-family:'DM Mono',monospace;
                                        text-shadow:0 0 30px rgba(45,217,164,0.4)">
                                    {{ bedKosong }}
                                </p>
                                <p class="text-white/50 text-xs mt-1">dari {{ totalBed }} bed</p>
                            </div>
                            <div class="w-px h-16 rounded-full opacity-20" style="background:white"></div>
                            <div class="text-center">
                                <p class="text-white/60 text-xs uppercase tracking-widest">Terisi</p>
                                <p class="font-bold leading-none mt-1"
                                    style="font-size:clamp(36px,5vw,64px); font-family:'DM Mono',monospace;
                                        color:#FF0505; text-shadow:0 0 30px rgba(74, 193, 217, 0.4)">
                                    {{ bedTerisi }}
                                </p>
                                <p class="text-white/50 text-xs mt-1">hunian {{ occupancy }}%</p>
                            </div>
                            <div class="w-px h-16 rounded-full opacity-20" style="background:white"></div>
                            <div class="text-center">
                                <p class="text-white/60 text-xs uppercase tracking-widest">Booking</p>
                                <p class="font-bold leading-none mt-1"
                                    style="font-size:clamp(36px,5vw,64px); font-family:'DM Mono',monospace;
                                        color:#F2FF00; text-shadow:0 0 30px rgba(224,146,58,0.4)">
                                    {{ bedBooking }}
                                </p>
                                <p class="text-white/50 text-xs mt-1">menunggu transfer</p>
                            </div>
                        </div>
                    </div>

                    <!-- Ticker berjalan di bawah hero -->
                <div v-if="tickerItem"
                        class="flex items-center gap-4 px-4 sm:px-8 md:px-12 py-3 overflow-hidden"
                        style="background:rgba(0,0,0,0.25); backdrop-filter:blur(6px)"
                        @mouseenter="tickerPause=true" @mouseleave="tickerPause=false">

                        <!-- LIVE badge -->
                        <span class="font-bold px-2.5 py-1 rounded flex-shrink-0"
                            style="background:#2DD9A4; color:#0D1A17; letter-spacing:.05em;
                                font-size:clamp(10px,1vw,14px)">
                            LIVE
                        </span>

                        <!-- Content -->
                        <div class="flex items-center gap-3 overflow-hidden flex-1"
                            style="font-size:clamp(12px,1.2vw,18px)">

                            <span class="font-bold text-white truncate">
                                {{ tickerItem.nama_pasien }}
                            </span>

                            <span class="text-white/40">·</span>

                            <span class="px-2.5 py-1 rounded-full flex-shrink-0"
                                :style="`background:${jalurInfo(tickerItem.jalur).bg}; color:${jalurInfo(tickerItem.jalur).color};
                                        font-size:clamp(10px,0.9vw,14px)`">
                                {{ jalurInfo(tickerItem.jalur).label }}
                            </span>

                            <span class="px-2.5 py-1 rounded-full flex-shrink-0"
                                :style="`background:${statusInfo(tickerItem.status).bg}; color:${statusInfo(tickerItem.status).color};
                                        font-size:clamp(10px,0.9vw,14px)`">
                                {{ statusInfo(tickerItem.status).label }}
                            </span>

                            <span v-if="tickerItem.nama_bed"
                                class="text-white/70 flex-shrink-0"
                                style="font-size:clamp(11px,1vw,15px)">
                                🏥 {{ tickerItem.nama_bed }}
                            </span>

                            <span class="text-white/50 truncate"
                                style="font-size:clamp(11px,1vw,15px)">
                                {{ tickerItem.diagnosa ?? tickerItem.Diagnosis ?? '' }}
                            </span>
                        </div>

                        <!-- Progress dots -->
                        <div class="flex gap-1.5 flex-shrink-0">
                            <span v-for="(_, i) in Array(Math.min(10, props.listAktif.length))"
                                :key="i"
                                class="rounded-full transition-all duration-300"
                                :style="i === (tickerIdx % Math.min(10, props.listAktif.length))
                                    ? 'width:18px; height:6px; background:#2DD9A4'
                                    : 'width:6px; height:6px; background:rgba(255,255,255,0.25)'">
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════════
                 BODY
            ═══════════════════════════════════════════════════════════ -->
            <div class="p-4 sm:p-6 space-y-5">
                <div class="card-dark overflow-hidden">
                    <!-- Header + filter -->
                    <div class="px-5 pt-4 pb-3 space-y-3" style="border-bottom:1px solid var(--border-default)">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold" style="color:var(--text-primary)">
                                    Daftar Pasien Aktif
                                    <span class="ml-2 text-xs font-mono px-2 py-0.5 rounded-full"
                                        style="background:rgba(45,217,164,0.15); color:#2DD9A4">
                                        {{ listFiltered.length }}
                                    </span>
                                </p>
                            </div>
                            <!-- Search -->
                            <div class="relative">
                                <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                                    style="color:var(--text-secondary)">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                <input v-model="searchQuery" placeholder="Cari nama, No_MR, diagnosa, bed..."
                                    class="text-xs pl-8 pr-3 py-2 rounded-xl outline-none w-full sm:w-64"
                                    style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                            </div>
                        </div>

                        <!-- Filter row -->
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="flex gap-0.5 p-0.5 rounded-lg" style="background:var(--bg-main); border:1px solid var(--border-default)">
                                <button v-for="opt in [{v:'semua',l:'Semua'},{v:'external',l:'External'},{v:'internal',l:'Internal'}]"
                                    :key="opt.v" @click="filterJenis=opt.v"
                                    class="text-xs font-semibold px-3 py-1 rounded-md transition-all"
                                    :style="filterJenis===opt.v
                                        ? 'background:var(--bg-surface); color:#2DD9A4'
                                        : 'color:var(--text-secondary)'">
                                    {{ opt.l }}
                                </button>
                            </div>
                            <div class="flex items-center gap-1.5 text-xs" style="color:var(--text-secondary)">
                                Dari:
                                <input type="date" v-model="filterTglMul" class="text-xs px-2 py-1.5 rounded-lg outline-none"
                                    style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                                s/d
                                <input type="date" v-model="filterTglAkh" :min="filterTglMul" class="text-xs px-2 py-1.5 rounded-lg outline-none"
                                    style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                            </div>
                            <button v-if="filterJenis!=='semua'||filterTglMul||filterTglAkh||searchQuery"
                                @click="filterJenis='semua';filterTglMul='';filterTglAkh='';searchQuery=''"
                                class="text-xs px-2.5 py-1.5 rounded-lg"
                                style="background:rgba(224,112,80,0.1); color:#E07050; border:1px solid rgba(224,112,80,0.25)">
                                ✕ Reset
                            </button>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr style="border-bottom:1px solid var(--border-default)">
                                    <th class="text-left px-5 py-3 text-xs font-semibold uppercase tracking-wide" style="color:var(--text-secondary)">Pasien</th>
                                    <th class="text-left px-3 py-3 text-xs font-semibold uppercase tracking-wide" style="color:var(--text-secondary)">Jalur</th>
                                    <th class="text-left px-3 py-3 text-xs font-semibold uppercase tracking-wide hidden sm:table-cell" style="color:var(--text-secondary)">Diagnosa</th>
                                    <th class="text-left px-3 py-3 text-xs font-semibold uppercase tracking-wide" style="color:var(--text-secondary)">Bed / Jenis ICU</th>
                                    <th class="text-left px-3 py-3 text-xs font-semibold uppercase tracking-wide" style="color:var(--text-secondary)">Status</th>
                                    <th class="text-left px-3 py-3 text-xs font-semibold uppercase tracking-wide hidden lg:table-cell" style="color:var(--text-secondary)">Tanggal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="listFiltered.length === 0">
                                    <td colspan="6" class="text-center py-16" style="color:var(--text-secondary)">
                                        <svg class="w-12 h-12 mx-auto mb-3 opacity-20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2"
                                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        <p class="text-sm">Tidak ada pasien aktif</p>
                                    </td>
                                </tr>
                                <tr v-for="(p, i) in listFiltered" :key="p.id"
                                    class="transition-colors"
                                    style="border-bottom:1px solid var(--border-default)"
                                    :style="i === (tickerIdx % Math.max(1, listFiltered.length))
                                        ? 'background:rgba(45,217,164,0.04)'
                                        : ''">
                                    <!-- Pasien -->
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0"
                                                :style="`background:${av(i)[0]}; color:${av(i)[1]}`">
                                                {{ getInitials(p.nama_pasien) }}
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold truncate" style="color:var(--text-primary)">
                                                    {{ p.nama_pasien }}
                                                </p>
                                                <p class="text-xs font-mono" style="color:var(--text-secondary); font-size:10px">
                                                    {{ p.No_MR ? 'MR: '+p.No_MR : 'NIK: '+(p.no_identitas??'-') }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <!-- Jalur -->
                                    <td class="px-3 py-3">
                                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                            :style="`background:${jalurInfo(p.jalur).bg}; color:${jalurInfo(p.jalur).color}`">
                                            {{ jalurInfo(p.jalur).label }}
                                        </span>
                                    </td>
                                    <!-- Diagnosa -->
                                    <td class="px-3 py-3 hidden sm:table-cell">
                                        <p class="text-xs truncate" style="color:var(--text-primary); max-width:160px">
                                            {{ p.diagnosa ?? p.Diagnosis ?? '—' }}
                                        </p>
                                    </td>
                                    <!-- Bed -->
                                    <td class="px-3 py-3">
                                        <p v-if="p.nama_bed" class="text-xs font-semibold" style="color:#2DD9A4">🏥 {{ p.nama_bed }}</p>
                                        <p v-else class="text-xs" style="color:var(--text-secondary)">
                                            {{ p.kebutuhan_bed ?? '—' }}
                                        </p>
                                    </td>
                                    <!-- Status -->
                                    <td class="px-3 py-3">
                                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
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

                    <!-- Footer total -->
                    <div class="px-5 py-3 flex items-center justify-between"
                        style="border-top:1px solid var(--border-default)">
                        <p class="text-xs" style="color:var(--text-secondary)">
                            Menampilkan <strong style="color:var(--text-primary)">{{ listFiltered.length }}</strong>
                            dari <strong style="color:var(--text-primary)">{{ listAktif.length }}</strong> pasien
                        </p>
                        <div class="flex items-center gap-1.5 text-xs" style="color:var(--text-secondary)">
                            <span class="w-1.5 h-1.5 rounded-full animate-pulse" style="background:#2DD9A4"></span>
                            Auto-refresh {{ countdown }}s
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AppLayout>
</template>
