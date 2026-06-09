<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { Line, Doughnut, Bar } from 'vue-chartjs';
import {
    Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement,
    BarElement, ArcElement, Filler, Tooltip, Legend
} from 'chart.js';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement,
    BarElement, ArcElement, Filler, Tooltip, Legend);

import AppLayout    from '@/Layouts/AppLayout.vue';
import StatCard     from '@/Components/StatCard.vue';
import BedGrid      from '@/Components/BedGrid.vue';
import AlertModal   from '@/Components/AlertModal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { useTheme } from '@/composables/useTheme.js';

// Theme
const { theme } = useTheme();
const isDark = computed(() => theme.value === 'dark');

// Logo — gunakan BASE_URL Vite agar file dari public/ tidak di-bundle
const logoUrl = `${import.meta.env.BASE_URL}images/logo-urip.png`;

// Chart color helpers that react to theme
const chartText  = computed(() => isDark.value ? '#8EA89E' : '#4A7A68');
const chartGrid  = computed(() => isDark.value ? 'rgba(255,255,255,0.04)' : 'rgba(0,0,0,0.06)');
const tooltipBg  = computed(() => isDark.value ? '#1A3D32' : '#FFFFFF');
const tooltipBdr = computed(() => isDark.value ? 'rgba(45,217,164,0.2)' : 'rgba(15,138,114,0.2)');
const tooltipTxt = computed(() => isDark.value ? '#FFFFFF' : '#0F2A22');
const tooltipSub = computed(() => isDark.value ? '#8EA89E' : '#4A7A68');

const props = defineProps({
    tahapDaftar:      { type: Array,  default: () => [] },
    tahapIgd:         { type: Array,  default: () => [] },
    tahapSpri:        { type: Array,  default: () => [] },
    tahapNungguKamar: { type: Array,  default: () => [] },
    tahapBooking:     { type: Array,  default: () => [] },
    tahapDiIcu:       { type: Array,  default: () => [] },
    semuaKamar:       { type: Array,  default: () => [] },
    kamarKosong:      { type: Array,  default: () => [] },
    masterKelas:      { type: Array,  default: () => [] },
    statsExternal:    { type: Object, default: () => ({ proses: 0, di_icu: 0 }) },
    statsInternal:    { type: Object, default: () => ({ proses: 0, di_icu: 0 }) },
    flash:            { type: Object, default: () => ({}) },
});

// ── Stats ──────────────────────────────────────────────────
const bedKosong  = computed(() => props.semuaKamar.filter(k => k.Status === 'KOSONG').length);
const bedBooking = computed(() => props.semuaKamar.filter(k => k.Status === 'BOOKING').length);
const bedTerisi  = computed(() => props.semuaKamar.filter(k => k.Status === 'ISI').length);
const totalBed   = computed(() => props.semuaKamar.length);
const occupancyPct = computed(() => totalBed.value > 0 ? Math.round((bedTerisi.value / totalBed.value) * 100) : 0);
const totalPasienAktif = computed(() =>
    props.tahapDaftar.length + props.tahapIgd.length + props.tahapSpri.length +
    props.tahapNungguKamar.length + props.tahapBooking.length + props.tahapDiIcu.length
);
const pria   = computed(() => props.semuaKamar.filter(k => k.Status === 'ISI' && k.jenis_kelamin === 'L').length);
const wanita = computed(() => props.semuaKamar.filter(k => k.Status === 'ISI' && k.jenis_kelamin === 'P').length);

// ── Modal ──────────────────────────────────────────────────
const alert   = ref({ show: false, type: 'success', title: '', message: '' });
const confirm = ref({ show: false, action: null, title: '', message: '', danger: false });
const showAlert   = (type, title, msg) => { alert.value = { show: true, type, title, message: msg }; };
const openConfirm = (cfg) => { confirm.value = { show: true, ...cfg }; };
const doConfirm   = () => { confirm.value.action?.(); confirm.value.show = false; };
watch(() => props.flash, (f) => {
    if (f?.success) showAlert('success', 'Berhasil!', f.success);
    if (f?.error)   showAlert('error',   'Gagal!',    f.error);
}, { immediate: true, deep: true });

// ── Alokasi bed ────────────────────────────────────────────
const bedPilihan    = ref({});
const bedCocokUntuk = (type) => props.kamarKosong.filter(b => b.nama_kelas === type);
const doAlokasi = (p) => {
    const kode = bedPilihan.value[p.id];
    if (!kode) { showAlert('warning', 'Pilih Bed', 'Silakan pilih bed terlebih dahulu.'); return; }
    const nama = props.kamarKosong.find(b => b.Kode_Ruang === kode)?.nama_ruang ?? kode;
    openConfirm({ title: 'Konfirmasi Alokasi', message: `Alokasikan ${nama} untuk ${p.nama_pasien}?`, danger: false,
        action: () => router.post(route('icu.alokasi_bed', p.id), { Kode_Ruang: kode }) });
};
const doMasuk = (p) => openConfirm({ title: 'Antar ke Ruangan?', message: `${p.nama_pasien} → ${p.nama_bed}`, danger: false,
    action: () => router.post(route('icu.masuk_ruangan', p.id)) });
const doPulang = (p) => openConfirm({ title: 'Pulangkan Pasien?', message: `${p.nama_pasien} akan dipulangkan dan bed kembali kosong.`, danger: true,
    action: () => router.post(route('icu.pulangkan', p.id)) });

// ── Charts: dark mode palette ──────────────────────────────
const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Okt','Nov','Des'];

// Line chart — Adherence / Aktivitas Bed
const lineChartData = computed(() => ({
    labels: months,
    datasets: [
        {
            label: 'Bed Terisi',
            data: [3,4,3,3,5,4,6,5,4,6,5,bedTerisi.value],
            borderColor: '#2DD9A4',
            backgroundColor: 'rgba(45,217,164,0.08)',
            tension: 0.4,
            fill: true,
            pointBackgroundColor: '#2DD9A4',
            pointRadius: 3,
            pointHoverRadius: 5,
            borderWidth: 2,
        },
        {
            label: 'Booking',
            data: [1,2,1,2,2,2,3,3,2,3,2,bedBooking.value],
            borderColor: 'rgba(74,234,181,0.5)',
            backgroundColor: 'transparent',
            tension: 0.4,
            fill: false,
            pointRadius: 0,
            borderWidth: 1.5,
            borderDash: [4, 4],
        },
    ],
}));
const lineChartOptions = computed(() => ({
    responsive: true, maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: tooltipBg.value,
            borderColor: tooltipBdr.value,
            borderWidth: 1,
            titleColor: tooltipTxt.value,
            bodyColor: tooltipSub.value,
            titleFont: { family: 'Plus Jakarta Sans', weight: '600' },
            bodyFont: { family: 'DM Mono' },
            padding: 10,
        },
    },
    scales: {
        x: {
            grid: { color: chartGrid.value, drawBorder: false },
            ticks: { font: { family: 'DM Mono', size: 10 }, color: chartText.value },
            border: { display: false },
        },
        y: {
            grid: { color: chartGrid.value, drawBorder: false },
            ticks: { font: { family: 'DM Mono', size: 10 }, color: chartText.value },
            border: { display: false },
        },
    },
}));

// Donut chart — bed occupancy
const donutData = computed(() => ({
    datasets: [{
        data: [bedTerisi.value || 1, (bedKosong.value + bedBooking.value) || 0],
        backgroundColor: ['#2DD9A4', 'rgba(45,217,164,0.12)'],
        borderWidth: 0,
        hoverOffset: 6,
    }],
}));
const donutOptions = {
    responsive: true, maintainAspectRatio: false,
    cutout: '76%',
    plugins: {
        legend: { display: false },
        tooltip: { enabled: false },
    },
};

// Bar chart — alur pasien per tahap
const barData = computed(() => ({
    labels: ['Daftar', 'IGD', 'SPRI', 'Tunggu', 'Booking', 'Di ICU'],
    datasets: [
        {
            label: 'Pasien',
            data: [
                props.tahapDaftar.length,
                props.tahapIgd.length,
                props.tahapSpri.length,
                props.tahapNungguKamar.length,
                props.tahapBooking.length,
                props.tahapDiIcu.length,
            ],
            backgroundColor: [
                '#2DD9A4','#4AEAB5','rgba(45,217,164,0.6)',
                'rgba(224,146,58,0.8)','rgba(74,144,217,0.8)','#2DD9A4'
            ],
            borderRadius: 6,
            borderSkipped: false,
        },
    ],
}));
const barOptions = computed(() => ({
    responsive: true, maintainAspectRatio: false,
    indexAxis: 'y',
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: tooltipBg.value,
            borderColor: tooltipBdr.value,
            borderWidth: 1,
            titleColor: tooltipTxt.value,
            bodyColor: tooltipSub.value,
            titleFont: { family: 'Plus Jakarta Sans', weight: '600' },
            bodyFont: { family: 'DM Mono' },
            padding: 10,
        },
    },
    scales: {
        x: {
            grid: { color: chartGrid.value, drawBorder: false },
            ticks: { font: { family: 'DM Mono', size: 10 }, color: chartText.value, stepSize: 1 },
            border: { display: false },
        },
        y: {
            grid: { display: false },
            ticks: { font: { family: 'Plus Jakarta Sans', size: 11 }, color: chartText.value },
            border: { display: false },
        },
    },
}));

// ── Task/progress list ─────────────────────────────────────
const tasks = computed(() => [
    { label: 'Pendaftaran', val: props.tahapDaftar.length,      max: 10, color: '#4A90D9' },
    { label: 'IGD Triase',  val: props.tahapIgd.length,         max: 10, color: '#E07050' },
    { label: 'SPRI',        val: props.tahapSpri.length,        max: 10, color: '#2DD9A4' },
    { label: 'Tunggu Bed',  val: props.tahapNungguKamar.length, max: 10, color: '#E0923A' },
    { label: 'Di ICU',      val: props.tahapDiIcu.length,       max: 10, color: '#4AEAB5' },
]);

// ── Avatar color map ───────────────────────────────────────
const avatarColors = [
    { bg: 'rgba(45,217,164,0.2)',  color: '#2DD9A4' },
    { bg: 'rgba(74,144,217,0.2)',  color: '#4A90D9' },
    { bg: 'rgba(224,112,80,0.2)',  color: '#E07050' },
    { bg: 'rgba(224,146,58,0.2)',  color: '#E0923A' },
    { bg: 'rgba(74,234,181,0.2)',  color: '#4AEAB5' },
];
const getAvatarColor = (idx) => avatarColors[idx % avatarColors.length];
const getInitials = (name) => {
    if (!name) return '?';
    const parts = name.split(' ');
    return parts.length >= 2 ? (parts[0][0] + parts[1][0]).toUpperCase() : name.substring(0, 2).toUpperCase();
};

// ── Pagination (for pasien table) ─────────────────────────
const currentPage  = ref(1);
const perPage      = ref(5);
const allPasien    = computed(() => [...props.tahapDiIcu, ...props.tahapBooking, ...props.tahapNungguKamar]);
const totalPages   = computed(() => Math.max(1, Math.ceil(allPasien.value.length / perPage.value)));
const pagedPasien  = computed(() => {
    const start = (currentPage.value - 1) * perPage.value;
    return allPasien.value.slice(start, start + perPage.value);
});
const goPage = (p) => { if (p >= 1 && p <= totalPages.value) currentPage.value = p; };

// ── Icons ──────────────────────────────────────────────────
const icons = {
    bed:    'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
    clock:  'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
    home:   'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
    user:   'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
    heart:  'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z',
    users:  'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
};

// ── Quick nav links ────────────────────────────────────────
const quickLinks = computed(() => [
    { label: 'Booking External', href: '/icu/booking-external', cnt: props.statsExternal.proses, icon: icons.home,   color: '#4A90D9' },
    { label: 'Surat Internal',   href: '/icu/spri-internal',    cnt: props.statsInternal.proses, icon: icons.user,   color: '#E0923A' },
    { label: 'Alokasi Bed',      href: '/icu/alokasi-bed',      cnt: props.tahapNungguKamar.length + props.tahapBooking.length, icon: icons.bed, color: '#2DD9A4' },
    { label: 'Pasien ICU',       href: '/icu/pasien-icu',       cnt: props.tahapDiIcu.length,    icon: icons.heart,  color: '#3DDB8A' },
]);

// Stage label helper
const getStageLabel = (p) => {
    if (props.tahapDiIcu.find(x => x.id === p.id))       return { label: 'Di ICU',  color: '#3DDB8A', bg: 'rgba(61,219,138,0.12)' };
    if (props.tahapBooking.find(x => x.id === p.id))      return { label: 'Booking', color: '#4A90D9', bg: 'rgba(74,144,217,0.12)' };
    if (props.tahapNungguKamar.find(x => x.id === p.id))  return { label: 'Tunggu',  color: '#E0923A', bg: 'rgba(224,146,58,0.12)' };
    return { label: 'Aktif', color: '#2DD9A4', bg: 'rgba(45,217,164,0.12)' };
};
</script>

<template>
    <AppLayout :flash="flash" page-title="Dashboard ICU">
        <AlertModal   v-bind="alert"   @close="alert.show = false"/>
        <ConfirmModal v-bind="confirm" @confirm="doConfirm" @cancel="confirm.show = false"/>

        <div class="p-5 sm:p-6 space-y-5" style="font-family:'Plus Jakarta Sans',sans-serif; background:var(--bg-main)">

            <!-- ══ ROW 1: Hero Banner + Stats Cards ══════════════ -->
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">

                <!-- Hero Banner — 2 cols -->
                <div class="xl:col-span-2 hero-banner p-6 sm:p-8 flex items-center justify-between gap-4" style="min-height:160px">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium mb-2" style="color:rgba(255, 255, 255, 1)"> 
                            Selamat datang 👋
                        </p>
                        <h1 class="font-bold leading-tight mb-2"
                            style="font-size:clamp(24px,3vw,36px); color:rgba(255, 255, 255, 1); letter-spacing:-0.02em">
                            Monitor ICU Real-time
                        </h1>
                        <p class="text-sm" style="color:rgba(255, 255, 255, 1); max-width:360px; line-height:1.6">
                            Pantau kondisi ruang ICU, alur pasien, dan hunian bed secara live — semua dalam satu layar.
                        </p>
                        
                    </div>
                    <!-- Logo RS -->
                    <div class="hidden sm:flex flex-shrink-0 items-center justify-center w-28 h-28 rounded-2xl overflow-hidden"
                        style="background:rgba(45,217,164,0.06); border:1px solid rgba(45,217,164,0.2)">
                        <img :src="logoUrl"
                            alt="Logo URIP"
                            class="w-24 h-24 object-contain"
                            style="filter: drop-shadow(0 2px 8px rgba(45,217,164,0.25))"
                            @error="$event.target.style.display='none'"/>
                    </div>
                </div>

                <!-- Donut: Hunian Bed -->
                    <div class="card-dark p-5 flex items-center gap-5">
                        <div class="relative flex-shrink-0" style="width:100px; height:100px">
                            <Doughnut :data="donutData" :options="donutOptions"/>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <div class="text-center">
                                    <p class="font-bold leading-none" style="font-size:20px; color:var(--text-primary); font-family:'Plus Jakarta Sans',sans-serif">{{ occupancyPct }}%</p>
                                    <p class="text-xs mt-0.5" style="color:var(--text-secondary); font-family:'DM Mono',monospace; font-size:9px">Hunian</p>
                                </div>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm mb-3" style="color:var(--text-primary); font-family:'Plus Jakarta Sans',sans-serif">Hunian Bed</p>
                            <div class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="flex items-center gap-2 text-xs" style="color:var(--text-secondary)">
                                        <span class="w-2 h-2 rounded-full" style="background:#2DD9A4"></span>Terisi
                                    </span>
                                    <span class="text-xs font-bold" style="color:var(--text-primary); font-family:'DM Mono',monospace">{{ bedTerisi }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="flex items-center gap-2 text-xs" style="color:var(--text-secondary)">
                                        <span class="w-2 h-2 rounded-full" style="background:rgba(45,217,164,0.2)"></span>Kosong
                                    </span>
                                    <span class="text-xs font-bold" style="color:var(--text-primary); font-family:'DM Mono',monospace">{{ bedKosong }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="flex items-center gap-2 text-xs" style="color:var(--text-secondary)">
                                        <span class="w-2 h-2 rounded-full" style="background:#E0923A"></span>Booking
                                    </span>
                                    <span class="text-xs font-bold" style="color:var(--text-primary); font-family:'DM Mono',monospace">{{ bedBooking }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
            </div>

            <!-- ══ ROW 2: KPI Cards ═══════════════════════════════ -->
            <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-3 sm:gap-4">
                <StatCard label="Bed Tersedia"  :value="bedKosong"          :sub="`dari ${totalBed} total`"   :icon="icons.bed"   color="teal" />
                <StatCard label="Booking"       :value="bedBooking"         sub="Menunggu transfer"            :icon="icons.clock" color="amber" />
                <StatCard label="Hunian"        :value="occupancyPct + '%'" :sub="`${bedTerisi} terisi`"      :icon="icons.home"  color="coral" />
                <StatCard label="Pasien Aktif"  :value="totalPasienAktif"   sub="Semua tahap"                 :icon="icons.users" color="sky"   />
                <StatCard label="Pria di ICU"   :value="pria"               sub="♂ Terisi"                    :icon="icons.user"  color="sky"   />
                <StatCard label="Wanita di ICU" :value="wanita"             sub="♀ Terisi"                    :icon="icons.heart" color="mint"  />
            </div>

            <!-- ══ ROW 3: Quick Links + Stats Jalur Baru ═════════ -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Booking External -->
                <a href="/icu/booking-external" class="card-dark p-5 flex items-center gap-4 group cursor-pointer"
                    style="text-decoration:none; border-color:rgba(74,144,217,0.2)">
                    <div class="w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0"
                        style="background:rgba(74,144,217,0.15)">
                        <svg style="width:20px;height:20px;color:#4A90D9" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="icons.home"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold" style="color:var(--text-secondary)">Booking External</p>
                        <p class="text-2xl font-bold leading-tight" style="color:#4A90D9; font-family:'DM Mono',monospace">{{ statsExternal.proses }}</p>
                        <p class="text-xs" style="color:var(--text-secondary)">Proses · {{ statsExternal.di_icu }} di ICU</p>
                    </div>
                </a>

                <!-- Surat Permintaan Internal -->
                <a href="/icu/spri-internal" class="card-dark p-5 flex items-center gap-4 cursor-pointer"
                    style="text-decoration:none; border-color:rgba(224,146,58,0.2)">
                    <div class="w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0"
                        style="background:rgba(224,146,58,0.15)">
                        <svg style="width:20px;height:20px;color:#E0923A" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="icons.user"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold" style="color:var(--text-secondary)">Surat Permintaan Internal</p>
                        <p class="text-2xl font-bold leading-tight" style="color:#E0923A; font-family:'DM Mono',monospace">{{ statsInternal.proses }}</p>
                        <p class="text-xs" style="color:var(--text-secondary)">Proses · {{ statsInternal.di_icu }} di ICU</p>
                    </div>
                </a>

                <!-- Alokasi Bed -->
                <a href="/icu/alokasi-bed" class="card-dark p-5 flex items-center gap-4 cursor-pointer"
                    style="text-decoration:none; border-color:rgba(45,217,164,0.2)">
                    <div class="w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0"
                        style="background:rgba(45,217,164,0.15)">
                        <svg style="width:20px;height:20px;color:#2DD9A4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="icons.bed"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold" style="color:var(--text-secondary)">Alokasi Bed</p>
                        <p class="text-2xl font-bold leading-tight" style="color:#2DD9A4; font-family:'DM Mono',monospace">{{ tahapNungguKamar.length + tahapBooking.length }}</p>
                        <p class="text-xs" style="color:var(--text-secondary)">Menunggu · {{ tahapBooking.length }} booking</p>
                    </div>
                </a>

                <!-- Pasien ICU -->
                <a href="/icu/pasien-icu" class="card-dark p-5 flex items-center gap-4 cursor-pointer"
                    style="text-decoration:none; border-color:rgba(61,219,138,0.2)">
                    <div class="w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0"
                        style="background:rgba(61,219,138,0.15)">
                        <svg style="width:20px;height:20px;color:#3DDB8A" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="icons.heart"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold" style="color:var(--text-secondary)">Pasien di ICU</p>
                        <p class="text-2xl font-bold leading-tight" style="color:#3DDB8A; font-family:'DM Mono',monospace">{{ tahapDiIcu.length }}</p>
                        <p class="text-xs" style="color:var(--text-secondary)">♂ {{ pria }} · ♀ {{ wanita }}</p>
                    </div>
                </a>
            </div>

            <!-- ══ Pasien Table ════════════════════════════ -->
            <div class="card-dark">
                <!-- Header -->
                <div class="flex items-center justify-between px-5 py-4" style="border-bottom:1px solid var(--border-default)">
                    <div>
                        <p class="font-semibold text-sm" style="color:var(--text-primary); font-family:'Plus Jakarta Sans',sans-serif">Pasien Aktif</p>
                        <p class="text-xs mt-0.5" style="color:var(--text-secondary)">Semua pasien dalam sistem saat ini</p>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="dark-table w-full">
                        <thead>
                            <tr>
                                <th class="text-left pl-5">Nama Pasien</th>
                                <th class="text-left">Diagnosis</th>
                                <th class="text-left">Jenis Kelamin</th>
                                <th class="text-left">Tipe Bed</th>
                                <th class="text-left">Status</th>
                                <th class="text-left">Bed / Kamar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="allPasien.length === 0">
                                <td colspan="6" class="text-center py-12" style="color:var(--text-secondary)">
                                    <svg class="w-10 h-10 mx-auto mb-2 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                                    </svg>
                                    <p class="text-sm" style="font-family:'Plus Jakarta Sans',sans-serif">Tidak ada pasien aktif</p>
                                </td>
                            </tr>
                            <tr v-for="(p, idx) in pagedPasien" :key="p.id">
                                <!-- Nama -->
                                <td class="pl-5">
                                    <div class="flex items-center gap-3">
                                        <div class="avatar-initials text-xs font-bold"
                                            :style="`background:${getAvatarColor(idx).bg}; color:${getAvatarColor(idx).color}`">
                                            {{ getInitials(p.nama_pasien) }}
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold" style="color:var(--text-primary); font-family:'Plus Jakarta Sans',sans-serif">{{ p.nama_pasien }}</p>
                                            <p class="text-xs" style="color:var(--text-secondary); font-family:'DM Mono',monospace">No_MR: {{ p.No_MR }}</p>
                                        </div>
                                    </div>
                                </td>
                                <!-- Diagnosis -->
                                 <td>
                                    <span class="badge badge-range text-xs" style="font-family:'DM Mono',monospace">
                                        {{ p.diagnosis ?? '-' }}
                                    </span>
                                </td>
                                <!-- Jenis Kelamin -->
                                <td>
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                        :style="p.jenis_kelamin === 'L'
                                            ? 'background:rgba(74,144,217,0.15); color:#4A90D9'
                                            : p.jenis_kelamin === 'P'
                                            ? 'background:rgba(217,81,122,0.15); color:#D9517A'
                                            : 'background:var(--bg-input); color:var(--text-secondary)'">
                                        {{ p.jenis_kelamin === 'L' ? '♂ Pria' : p.jenis_kelamin === 'P' ? '♀ Wanita' : '—' }}
                                    </span>
                                </td>
                                <!-- Tipe Bed -->
                                <td>
                                    <span class="badge badge-range text-xs" style="font-family:'DM Mono',monospace">
                                        {{ p.required_bed_type ?? '-' }}
                                    </span>
                                </td>
                                <!-- Status -->
                                <td>
                                    <span class="badge text-xs"
                                        :style="`background:${getStageLabel(p).bg}; color:${getStageLabel(p).color}`">
                                        {{ getStageLabel(p).label }}
                                    </span>
                                </td>
                                <!-- Bed -->
                                <td>
                                    <span class="text-xs" style="color:var(--text-secondary); font-family:'DM Mono',monospace">
                                        {{ p.nama_bed ?? '— Belum diset —' }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination footer -->
                <div class="flex items-center justify-between px-5 py-4" style="border-top:1px solid var(--border-default)">
                    <div class="flex items-center gap-2">
                        <span class="text-xs" style="color:var(--text-secondary)">Tampil</span>
                        <select v-model="perPage" class="text-xs px-2 py-1 rounded-lg"
                            style="background:var(--bg-input); border:1px solid rgba(45,217,164,0.12); color:var(--text-secondary); outline:none">
                            <option :value="5">5</option>
                            <option :value="10">10</option>
                            <option :value="20">20</option>
                        </select>
                        <span class="text-xs" style="color:var(--text-secondary)">data dari {{ allPasien.length }}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <button @click="goPage(currentPage - 1)" :disabled="currentPage === 1"
                            class="page-btn disabled:opacity-30 disabled:cursor-not-allowed text-xs px-3"
                            style="width:auto; padding:0 12px">
                            ← Prev
                        </button>
                        <button v-for="p in totalPages" :key="p" @click="goPage(p)"
                            :class="['page-btn', p === currentPage ? 'active' : '']">
                            {{ p }}
                        </button>
                        <button @click="goPage(currentPage + 1)" :disabled="currentPage === totalPages"
                            class="page-btn disabled:opacity-30 disabled:cursor-not-allowed text-xs px-3"
                            style="width:auto; padding:0 12px">
                            Next →
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
