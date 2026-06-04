<script setup>
import { computed } from 'vue';

const props = defineProps({
    kamar:   { type: Array,   default: () => [] },
    compact: { type: Boolean, default: false },
});

// ── Group per kelas ───────────────────────────────────────────────────
const grouped = computed(() => {
    const map = {};
    props.kamar.forEach(k => {
        const key = k.kode_kelas ?? 'Lainnya';
        if (!map[key]) map[key] = { nama: k.nama_kelas ?? key, beds: [] };
        map[key].beds.push(k);
    });
    return map;
});

// ── Summary stats ─────────────────────────────────────────────────────
const summary = computed(() => ({
    kosong:  props.kamar.filter(k => k.Status === 'KOSONG').length,
    booking: props.kamar.filter(k => k.Status === 'BOOKING').length,
    isiPria: props.kamar.filter(k => k.Status === 'ISI' && k.jenis_kelamin === 'L').length,
    isiWanita: props.kamar.filter(k => k.Status === 'ISI' && k.jenis_kelamin === 'P').length,
    isiUnknown: props.kamar.filter(k => k.Status === 'ISI' && !k.jenis_kelamin).length,
    total:   props.kamar.length,
}));

const totalIsi = computed(() => summary.value.isiPria + summary.value.isiWanita + summary.value.isiUnknown);

const occupancyPct = computed(() =>
    summary.value.total > 0
        ? Math.round((totalIsi.value / summary.value.total) * 100)
        : 0
);

// ── Warna berdasarkan status + jenis kelamin ──────────────────────────
// KOSONG  → hijau
// BOOKING → amber
// ISI + L → biru
// ISI + P → pink
// ISI + ? → abu
const bedTheme = (bed) => {
    const s = bed.Status?.toUpperCase();
    if (s === 'KOSONG') return {
        card:   'bg-gradient-to-br from-green-50 to-emerald-50 border-green-200 shadow-green-100',
        top:    'bg-green-500',
        icon:   'text-green-500',
        badge:  'bg-green-100 text-green-700 border-green-200',
        label:  'Tersedia',
        dot:    'bg-green-500',
        pulse:  false,
    };
    if (s === 'BOOKING') return {
        card:   'bg-gradient-to-br from-amber-50 to-yellow-50 border-amber-200 shadow-amber-100',
        top:    'bg-amber-400',
        icon:   'text-amber-500',
        badge:  'bg-amber-100 text-amber-700 border-amber-200',
        label:  'Booking',
        dot:    'bg-amber-400',
        pulse:  false,
    };
    if (s === 'ISI') {
        if (bed.jenis_kelamin === 'L') return {
            card:   'bg-gradient-to-br from-blue-50 to-sky-50 border-blue-200 shadow-blue-100',
            top:    'bg-blue-500',
            icon:   'text-blue-500',
            badge:  'bg-blue-100 text-blue-700 border-blue-200',
            label:  'Terisi · Pria',
            dot:    'bg-blue-500',
            pulse:  true,
        };
        if (bed.jenis_kelamin === 'P') return {
            card:   'bg-gradient-to-br from-pink-50 to-rose-50 border-pink-200 shadow-pink-100',
            top:    'bg-pink-500',
            icon:   'text-pink-500',
            badge:  'bg-pink-100 text-pink-700 border-pink-200',
            label:  'Terisi · Wanita',
            dot:    'bg-pink-500',
            pulse:  true,
        };
        return {
            card:   'bg-gradient-to-br from-gray-50 to-slate-50 border-gray-200 shadow-gray-100',
            top:    'bg-gray-500',
            icon:   'text-gray-500',
            badge:  'bg-gray-100 text-gray-700 border-gray-200',
            label:  'Terisi',
            dot:    'bg-gray-500',
            pulse:  true,
        };
    }
    return {
        card:  'bg-gray-50 border-gray-200',
        top:   'bg-gray-400',
        icon:  'text-gray-400',
        badge: 'bg-gray-100 text-gray-600 border-gray-200',
        label: bed.Status,
        dot:   'bg-gray-400',
        pulse: false,
    };
};

// ── Icon SVG path per status ──────────────────────────────────────────
const bedIconPath = (bed) => {
    const s = bed.Status?.toUpperCase();
    if (s === 'KOSONG')  return 'M5 13l4 4L19 7';              // checkmark
    if (s === 'BOOKING') return 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'; // clock
    if (bed.jenis_kelamin === 'L') return 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z';
    if (bed.jenis_kelamin === 'P') return 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z';
    return 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z';
};

// ── Bar warna occupancy per grup ──────────────────────────────────────
const groupStats = (beds) => ({
    kosong:  beds.filter(b => b.Status === 'KOSONG').length,
    booking: beds.filter(b => b.Status === 'BOOKING').length,
    isi:     beds.filter(b => b.Status === 'ISI').length,
    total:   beds.length,
});
</script>

<template>
    <div>
        <!-- ══════════════════════════════════════════════
             HEADER STATS — bar hunian global
        ══════════════════════════════════════════════ -->
        <div class="mb-5 p-4 bg-gradient-to-r from-gray-50 to-white rounded-2xl border border-gray-100">
            <!-- Occupancy bar -->
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Tingkat Hunian</span>
                <span :class="['text-sm font-bold', occupancyPct > 80 ? 'text-rose-600' : occupancyPct > 50 ? 'text-amber-600' : 'text-green-600']">
                    {{ occupancyPct }}%
                </span>
            </div>
            <!-- Segmented bar -->
            <div class="flex w-full h-3 rounded-full overflow-hidden bg-gray-100 gap-px">
                <div class="bg-green-500 transition-all duration-700"
                    :style="{ width: summary.total > 0 ? (summary.kosong / summary.total * 100) + '%' : '0%' }"></div>
                <div class="bg-amber-400 transition-all duration-700"
                    :style="{ width: summary.total > 0 ? (summary.booking / summary.total * 100) + '%' : '0%' }"></div>
                <div class="bg-blue-500 transition-all duration-700"
                    :style="{ width: summary.total > 0 ? (summary.isiPria / summary.total * 100) + '%' : '0%' }"></div>
                <div class="bg-pink-500 transition-all duration-700"
                    :style="{ width: summary.total > 0 ? (summary.isiWanita / summary.total * 100) + '%' : '0%' }"></div>
                <div class="bg-gray-400 transition-all duration-700"
                    :style="{ width: summary.total > 0 ? (summary.isiUnknown / summary.total * 100) + '%' : '0%' }"></div>
            </div>
            <!-- Legend pills — wrap di mobile -->
            <div class="flex flex-wrap items-center gap-1.5 mt-3">
                <span class="flex items-center gap-1.5 text-xs font-medium bg-green-100 text-green-700 px-2 py-0.5 rounded-full">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                    {{ summary.kosong }} Tersedia
                </span>
                <span class="flex items-center gap-1.5 text-xs font-medium bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                    {{ summary.booking }} Booking
                </span>
                <span class="flex items-center gap-1.5 text-xs font-medium bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                    {{ summary.isiPria }} Pria
                </span>
                <span class="flex items-center gap-1.5 text-xs font-medium bg-pink-100 text-pink-700 px-2 py-0.5 rounded-full">
                    <span class="w-1.5 h-1.5 rounded-full bg-pink-500"></span>
                    {{ summary.isiWanita }} Wanita
                </span>
                <span v-if="summary.isiUnknown > 0"
                    class="flex items-center gap-1.5 text-xs font-medium bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">
                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                    {{ summary.isiUnknown }} Terisi
                </span>
                <span class="text-xs text-gray-400 font-medium ml-auto">{{ summary.total }} bed</span>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════
             BED CARDS — per grup kelas
        ══════════════════════════════════════════════ -->
        <div v-for="(group, kode) in grouped" :key="kode" class="mb-6">

            <!-- Grup header -->
            <div class="flex items-center justify-between gap-2 mb-3">
                <div class="flex items-center gap-2 min-w-0">
                    <div class="w-1 h-5 bg-green-600 rounded-full flex-shrink-0"></div>
                    <span class="text-sm font-bold text-gray-700 truncate">{{ kode }}</span>
                    <span class="text-xs text-gray-400 font-medium hidden sm:inline">— {{ group.nama }}</span>
                </div>
                <!-- Mini stats per grup -->
                <div class="flex items-center gap-1 flex-shrink-0">
                    <template v-for="s in [
                        { val: groupStats(group.beds).kosong,  color: 'bg-green-500', tip: 'tersedia' },
                        { val: groupStats(group.beds).booking, color: 'bg-amber-400', tip: 'booking'  },
                        { val: groupStats(group.beds).isi,     color: 'bg-blue-500',  tip: 'terisi'   },
                    ]" :key="s.tip">
                        <span v-if="s.val > 0"
                            :class="['text-xs font-bold text-white px-1.5 py-0.5 rounded-full', s.color]"
                            :title="s.val + ' ' + s.tip"
                        >{{ s.val }}</span>
                    </template>
                    <span class="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full font-medium">
                        {{ group.beds.length }}
                    </span>
                </div>
            </div>

            <!-- Grid kartu bed — responsive kolom -->
            <div :class="[
                'grid gap-2.5',
                compact
                    ? 'grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5'
                    : 'grid-cols-1 xs:grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-3 xl:grid-cols-4'
            ]">
                <div
                    v-for="bed in group.beds"
                    :key="bed.Kode_Ruang"
                    :class="['relative rounded-2xl border shadow-sm overflow-hidden transition-all duration-200 hover:shadow-md hover:-translate-y-0.5', bedTheme(bed).card]"
                >
                    <!-- Top color bar -->
                    <div :class="['h-1.5 w-full', bedTheme(bed).top]"></div>

                    <div class="p-3.5">
                        <!-- Row 1: Nama ruang + Status dot -->
                        <div class="flex items-start justify-between gap-2 mb-2">
                            <div class="min-w-0 flex-1">
                                <p class="font-bold text-gray-800 text-sm leading-tight truncate">
                                    {{ bed.nama_ruang }}
                                </p>
                                <p class="text-xs text-gray-400 mt-0.5 font-mono">{{ bed.Kode_Ruang }}</p>
                            </div>
                            <!-- Status indicator -->
                            <div class="flex-shrink-0 flex items-center gap-1.5 mt-0.5">
                                <span :class="['w-2 h-2 rounded-full', bedTheme(bed).dot, bedTheme(bed).pulse ? 'animate-pulse' : '']"></span>
                            </div>
                        </div>

                        <!-- Row 2: Icon + Info pasien / status -->
                        <div :class="['flex items-center gap-2 rounded-xl px-2.5 py-2', bed.Status === 'KOSONG' ? 'bg-green-100/60' : bed.Status === 'BOOKING' ? 'bg-amber-100/60' : bed.jenis_kelamin === 'L' ? 'bg-blue-100/60' : bed.jenis_kelamin === 'P' ? 'bg-pink-100/60' : 'bg-gray-100/60']">
                            <!-- Icon -->
                            <div :class="['w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0',
                                bed.Status === 'KOSONG'  ? 'bg-green-200' :
                                bed.Status === 'BOOKING' ? 'bg-amber-200' :
                                bed.jenis_kelamin === 'L' ? 'bg-blue-200' :
                                bed.jenis_kelamin === 'P' ? 'bg-pink-200' : 'bg-gray-200'
                            ]">
                                <!-- KOSONG: checkmark -->
                                <svg v-if="bed.Status === 'KOSONG'"
                                    class="w-3.5 h-3.5 text-green-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                                <!-- BOOKING: clock -->
                                <svg v-else-if="bed.Status === 'BOOKING'"
                                    class="w-3.5 h-3.5 text-amber-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <!-- ISI pria: male icon -->
                                <svg v-else-if="bed.jenis_kelamin === 'L'"
                                    class="w-3.5 h-3.5 text-blue-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                <!-- ISI wanita: female icon -->
                                <svg v-else-if="bed.jenis_kelamin === 'P'"
                                    class="w-3.5 h-3.5 text-pink-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                <!-- ISI tidak diketahui -->
                                <svg v-else
                                    class="w-3.5 h-3.5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                </svg>
                            </div>

                            <!-- Teks info -->
                            <div class="min-w-0 flex-1">
                                <p v-if="bed.Status === 'KOSONG'"
                                    class="text-xs font-semibold text-green-700 leading-tight">Bed Tersedia</p>
                                <p v-else-if="bed.Status === 'BOOKING'"
                                    class="text-xs font-semibold text-amber-700 leading-tight">Menunggu Pasien</p>
                                <template v-else>
                                    <p class="text-xs font-semibold leading-tight truncate"
                                        :class="bed.jenis_kelamin === 'L' ? 'text-blue-800' : bed.jenis_kelamin === 'P' ? 'text-pink-800' : 'text-gray-800'">
                                        {{ bed.nama_pasien ?? 'Pasien' }}
                                    </p>
                                    <p class="text-xs leading-tight mt-0.5"
                                        :class="bed.jenis_kelamin === 'L' ? 'text-blue-600' : bed.jenis_kelamin === 'P' ? 'text-pink-600' : 'text-gray-500'">
                                        MR: {{ bed.No_MR ?? '-' }}
                                    </p>
                                </template>
                            </div>
                        </div>

                        <!-- Row 3: Badge status -->
                        <div class="mt-2.5 flex items-center justify-between">
                            <span :class="['text-xs font-semibold px-2.5 py-0.5 rounded-full border', bedTheme(bed).badge]">
                                {{ bedTheme(bed).label }}
                            </span>
                            <!-- Gender icon badge untuk ISI -->
                            <span v-if="bed.Status === 'ISI'" class="text-xs">
                                {{ bed.jenis_kelamin === 'L' ? '♂' : bed.jenis_kelamin === 'P' ? '♀' : '—' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
