<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    semuaKamar: { type: Array,  default: () => [] },
    summary:    { type: Object, default: () => ({ total: 0, kosong: 0, terisi: 0, booking: 0 }) },
    flash:      { type: Object, default: () => ({}) },
});

// ── Filter ─────────────────────────────────────────────────
const filterStatus = ref('semua');  // semua | KOSONG | ISI | BOOKING
const filterKelas  = ref('semua');
const searchBed    = ref('');       // search by nama ruang, kode, No_MR

const kelasOptions = computed(() => {
    const unique = [...new Set(props.semuaKamar.map(k => k.nama_kelas).filter(Boolean))];
    return unique.sort();
});

const filtered = computed(() => {
    return props.semuaKamar.filter(k => {
        const statusOk = filterStatus.value === 'semua' || k.Status === filterStatus.value;
        const kelasOk  = filterKelas.value  === 'semua' || k.nama_kelas === filterKelas.value;
        let   searchOk = true;
        if (searchBed.value.trim()) {
            const q = searchBed.value.trim().toLowerCase();
            searchOk = (k.nama_ruang ?? '').toLowerCase().includes(q)
                    || (k.Kode_Ruang ?? '').toLowerCase().includes(q)
                    || (k.No_MR      ?? '').toLowerCase().includes(q);
        }
        return statusOk && kelasOk && searchOk;
    });
});

// Kelompokkan per kelas
const grouped = computed(() => {
    const map = {};
    filtered.value.forEach(k => {
        const key = k.nama_kelas ?? 'Lainnya';
        if (!map[key]) map[key] = { nama: key, beds: [] };
        map[key].beds.push(k);
    });
    return Object.entries(map).map(([nama, v]) => ({ nama, beds: v.beds }));
});

// ── Theme helper ───────────────────────────────────────────
const theme = (bed) => {
    const s = (bed.Status ?? '').toUpperCase();
    if (s === 'KOSONG')  return { bg: 'rgba(0,168,132,0.06)',  border: 'rgba(0,168,132,0.2)',  stripe: '#00A884', badge: 'rgba(0,168,132,0.15)',  txt: '#00A884',  label: 'Tersedia',  dot: '#00A884',  pulse: false };
    if (s === 'BOOKING') return { bg: 'rgba(230,126,34,0.06)',  border: 'rgba(230,126,34,0.2)',  stripe: '#E67E22', badge: 'rgba(230,126,34,0.15)',  txt: '#E67E22',  label: 'Booking',   dot: '#E67E22',  pulse: false };
    return                      { bg: 'rgba(52,152,219,0.06)',  border: 'rgba(52,152,219,0.2)',  stripe: '#3498DB', badge: 'rgba(52,152,219,0.15)',  txt: '#3498DB',  label: 'Terisi',    dot: '#3498DB',  pulse: true  };
};

// ── Summary stats ──────────────────────────────────────────
const total   = computed(() => props.summary.total);
const kosong  = computed(() => props.summary.kosong);
const terisi  = computed(() => props.summary.terisi);
const booking = computed(() => props.summary.booking);
const pct     = computed(() => total.value > 0 ? Math.round((terisi.value / total.value) * 100) : 0);
</script>

<template>
    <AppLayout :flash="flash" page-title="Informasi Bed ICU">
        <div class="p-4 sm:p-6 space-y-5" style="font-family:'Inter','Plus Jakarta Sans',sans-serif">

            <!-- ── Header ───────────────────────────────────────────── -->
            <div>
                <h1 class="font-bold text-lg" style="color:var(--text-primary)">Informasi Bed ICU</h1>
                <p class="text-sm mt-0.5" style="color:var(--text-secondary)">
                    Status ketersediaan bed real-time dari sistem manajemen bed RS
                </p>
            </div>

            <!-- ── Summary Cards ─────────────────────────────────────── -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <button @click="filterStatus = 'semua'"
                    class="card-dark p-4 text-left transition-all"
                    :style="filterStatus === 'semua' ? 'border-color:rgba(90,107,124,0.5); background:rgba(90,107,124,0.06)' : ''">
                    <p class="text-xs font-semibold mb-1" style="color:var(--text-secondary)">Total Bed</p>
                    <p class="text-3xl font-bold" style="color:var(--text-primary); font-family:'DM Mono',monospace">{{ total }}</p>
                    <p class="text-xs mt-1" style="color:var(--text-secondary)">Hunian {{ pct }}%</p>
                </button>

                <button @click="filterStatus = filterStatus === 'KOSONG' ? 'semua' : 'KOSONG'"
                    class="card-dark p-4 text-left transition-all"
                    :style="filterStatus === 'KOSONG' ? 'border-color:rgba(0,168,132,0.6); background:rgba(0,168,132,0.06)' : ''">
                    <p class="text-xs font-semibold mb-1" style="color:#00A884">Tersedia</p>
                    <p class="text-3xl font-bold" style="color:#00A884; font-family:'DM Mono',monospace">{{ kosong }}</p>
                    <p class="text-xs mt-1" style="color:var(--text-secondary)">Klik untuk filter</p>
                </button>

                <button @click="filterStatus = filterStatus === 'ISI' ? 'semua' : 'ISI'"
                    class="card-dark p-4 text-left transition-all"
                    :style="filterStatus === 'ISI' ? 'border-color:rgba(52,152,219,0.6); background:rgba(52,152,219,0.06)' : ''">
                    <p class="text-xs font-semibold mb-1" style="color:#3498DB">Terisi</p>
                    <p class="text-3xl font-bold" style="color:#3498DB; font-family:'DM Mono',monospace">{{ terisi }}</p>
                    <p class="text-xs mt-1" style="color:var(--text-secondary)">Klik untuk filter</p>
                </button>

                <button @click="filterStatus = filterStatus === 'BOOKING' ? 'semua' : 'BOOKING'"
                    class="card-dark p-4 text-left transition-all"
                    :style="filterStatus === 'BOOKING' ? 'border-color:rgba(230,126,34,0.6); background:rgba(230,126,34,0.06)' : ''">
                    <p class="text-xs font-semibold mb-1" style="color:#E67E22">Booking</p>
                    <p class="text-3xl font-bold" style="color:#E67E22; font-family:'DM Mono',monospace">{{ booking }}</p>
                    <p class="text-xs mt-1" style="color:var(--text-secondary)">Klik untuk filter</p>
                </button>
            </div>

            <!-- ── Progress Bar ──────────────────────────────────────── -->
            <div class="card-dark p-4 space-y-2">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wide" style="color:var(--text-secondary)">Kapasitas ICU</p>
                    <span class="text-sm font-bold font-mono" :style="`color:${pct > 80 ? '#E74C3C' : pct > 50 ? '#E67E22' : '#00A884'}`">{{ pct }}%</span>
                </div>
                <div class="flex h-2.5 rounded-full overflow-hidden gap-px" style="background:rgba(255,255,255,0.06)">
                    <div class="rounded-full transition-all duration-700" style="background:#00A884"
                        :style="`width:${total > 0 ? kosong/total*100 : 0}%`"></div>
                    <div class="transition-all duration-700" style="background:#E67E22"
                        :style="`width:${total > 0 ? booking/total*100 : 0}%`"></div>
                    <div class="rounded-full transition-all duration-700" style="background:#3498DB"
                        :style="`width:${total > 0 ? terisi/total*100 : 0}%`"></div>
                </div>
                <div class="flex flex-wrap gap-3">
                    <span class="flex items-center gap-1.5 text-xs" style="color:var(--text-secondary)">
                        <span class="w-2 h-2 rounded-full" style="background:#00A884"></span>{{ kosong }} Tersedia
                    </span>
                    <span class="flex items-center gap-1.5 text-xs" style="color:var(--text-secondary)">
                        <span class="w-2 h-2 rounded-full" style="background:#E67E22"></span>{{ booking }} Booking
                    </span>
                    <span class="flex items-center gap-1.5 text-xs" style="color:var(--text-secondary)">
                        <span class="w-2 h-2 rounded-full" style="background:#3498DB"></span>{{ terisi }} Terisi
                    </span>
                </div>
            </div>

            <!-- ── Filter Bar ─────────────────────────────────────────── -->
            <div class="space-y-2">
                <!-- Search -->
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                        style="color:var(--text-secondary)">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input v-model="searchBed" placeholder="Cari nama ruang, kode bed, atau No. MR..."
                        class="w-full pl-9 pr-3 py-2.5 text-sm rounded-xl outline-none"
                        style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                </div>

                <!-- Filter jenis ICU + reset -->
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs font-semibold" style="color:var(--text-secondary)">Jenis ICU:</span>
                    <button @click="filterKelas = 'semua'"
                        class="text-xs font-semibold px-3 py-1.5 rounded-lg transition-all"
                        :style="filterKelas === 'semua'
                            ? 'background:rgba(0,168,132,0.2); color:#00A884; border:1px solid rgba(0,168,132,0.4)'
                            : 'background:var(--bg-input); color:var(--text-secondary); border:1px solid var(--border-default)'">
                        Semua
                    </button>
                    <button v-for="k in kelasOptions" :key="k"
                        @click="filterKelas = filterKelas === k ? 'semua' : k"
                        class="text-xs font-semibold px-3 py-1.5 rounded-lg transition-all"
                        :style="filterKelas === k
                            ? 'background:rgba(0,168,132,0.2); color:#00A884; border:1px solid rgba(0,168,132,0.4)'
                            : 'background:var(--bg-input); color:var(--text-secondary); border:1px solid var(--border-default)'">
                        {{ k }}
                    </button>

                    <!-- Reset -->
                    <button v-if="filterStatus !== 'semua' || filterKelas !== 'semua' || searchBed"
                        @click="filterStatus = 'semua'; filterKelas = 'semua'; searchBed = ''"
                        class="text-xs px-3 py-1.5 rounded-lg ml-auto"
                        style="background:rgba(231,76,60,0.1); color:#E74C3C; border:1px solid rgba(231,76,60,0.25)">
                        ✕ Reset Filter
                    </button>

                    <span class="text-xs" :class="filterStatus !== 'semua' || filterKelas !== 'semua' || searchBed ? '' : 'ml-auto'"
                        style="color:var(--text-secondary)">
                        Menampilkan <strong style="color:var(--text-primary)">{{ filtered.length }}</strong> dari {{ total }} bed
                    </span>
                </div>
            </div>

            <!-- ── Bed Grid ───────────────────────────────────────────── -->
            <div v-if="filtered.length === 0" class="card-dark text-center py-14" style="color:var(--text-secondary)">
                <svg class="w-10 h-10 mx-auto mb-2 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                <p class="text-sm">Tidak ada bed yang sesuai filter</p>
            </div>

            <div v-else class="space-y-5">
                <div v-for="group in grouped" :key="group.nama">

                    <!-- Group header -->
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <div class="w-1 h-4 rounded-full" style="background:#00A884"></div>
                            <p class="text-sm font-bold" style="color:var(--text-primary)">{{ group.nama }}</p>
                        </div>
                        <div class="flex items-center gap-1.5 text-xs">
                            <span class="px-2 py-0.5 rounded-full font-bold"
                                style="background:rgba(0,168,132,0.15); color:#00A884; font-family:'DM Mono',monospace">
                                {{ group.beds.filter(b => b.Status === 'KOSONG').length }} kosong
                            </span>
                            <span class="px-2 py-0.5 rounded-full font-bold"
                                style="background:rgba(255,255,255,0.06); color:var(--text-secondary); font-family:'DM Mono',monospace">
                                {{ group.beds.length }} total
                            </span>
                        </div>
                    </div>

                    <!-- Bed cards -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-3">
                        <div v-for="bed in group.beds" :key="bed.Kode_Ruang"
                            class="relative overflow-hidden rounded-xl transition-all"
                            :style="`background:${theme(bed).bg}; border:1px solid ${theme(bed).border}`">

                            <!-- Color stripe -->
                            <div class="h-1" :style="`background:${theme(bed).stripe}`"></div>

                            <div class="p-3 space-y-2">
                                <!-- Nama + kode + status dot -->
                                <div class="flex items-start justify-between gap-1">
                                    <div class="min-w-0">
                                        <p class="text-xs font-bold truncate" style="color:var(--text-primary)">{{ bed.nama_ruang }}</p>
                                        <p class="text-xs font-mono" style="color:var(--text-secondary); font-size:10px">{{ bed.Kode_Ruang }}</p>
                                    </div>
                                    <span class="w-2 h-2 rounded-full mt-0.5 flex-shrink-0"
                                        :class="theme(bed).pulse ? 'pulse-teal' : ''"
                                        :style="`background:${theme(bed).dot}`"></span>
                                </div>

                                <!-- Status info -->
                                <div class="p-2 rounded-lg" :style="`background:${theme(bed).badge}`">
                                    <!-- Kosong -->
                                    <template v-if="bed.Status === 'KOSONG'">
                                        <p class="text-xs font-semibold" :style="`color:${theme(bed).txt}`">✓ Tersedia</p>
                                    </template>
                                    <!-- Booking -->
                                    <template v-else-if="bed.Status === 'BOOKING'">
                                        <p class="text-xs font-semibold" :style="`color:${theme(bed).txt}`">⏳ Booking</p>
                                    </template>
                                    <!-- Terisi -->
                                    <template v-else>
                                        <p class="text-xs font-semibold" :style="`color:${theme(bed).txt}`">● Terisi</p>
                                        <p v-if="bed.No_MR" class="text-xs font-mono mt-0.5"
                                            style="color:var(--text-secondary); font-size:10px">
                                            MR: {{ bed.No_MR }}
                                        </p>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
