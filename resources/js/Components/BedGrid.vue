<script setup>
import { computed } from 'vue';
const props = defineProps({
    kamar:   { type: Array,   default: () => [] },
    compact: { type: Boolean, default: false },
});

const grouped = computed(() => {
    const map = {};
    props.kamar.forEach(k => {
        const key = k.kode_kelas ?? 'Lainnya';
        if (!map[key]) map[key] = { nama: k.nama_kelas ?? key, beds: [] };
        map[key].beds.push(k);
    });
    return map;
});

const summary = computed(() => ({
    kosong:    props.kamar.filter(k => k.Status === 'KOSONG').length,
    booking:   props.kamar.filter(k => k.Status === 'BOOKING').length,
    isiPria:   props.kamar.filter(k => k.Status === 'ISI' && k.jenis_kelamin === 'L').length,
    isiWanita: props.kamar.filter(k => k.Status === 'ISI' && k.jenis_kelamin === 'P').length,
    isiUnk:    props.kamar.filter(k => k.Status === 'ISI' && !k.jenis_kelamin).length,
    total:     props.kamar.length,
}));

const totalIsi    = computed(() => summary.value.isiPria + summary.value.isiWanita + summary.value.isiUnk);
const occupancy   = computed(() => summary.value.total > 0 ? Math.round((totalIsi.value / summary.value.total) * 100) : 0);

const theme = (bed) => {
    const s = bed.Status?.toUpperCase();
    if (s === 'KOSONG')  return { card: 'rgba(45,217,164,0.06)',  border: 'rgba(45,217,164,0.2)',  top: '#2DD9A4', badge: 'rgba(45,217,164,0.15)',  badgeTxt: '#2DD9A4', dot: '#2DD9A4', label: 'Tersedia',  pulse: false };
    if (s === 'BOOKING') return { card: 'rgba(224,146,58,0.06)',  border: 'rgba(224,146,58,0.2)',  top: '#E0923A', badge: 'rgba(224,146,58,0.15)',  badgeTxt: '#E0923A', dot: '#E0923A', label: 'Booking',   pulse: false };
    if (bed.jenis_kelamin === 'L') return { card: 'rgba(74,144,217,0.06)', border: 'rgba(74,144,217,0.2)', top: '#4A90D9', badge: 'rgba(74,144,217,0.15)', badgeTxt: '#4A90D9', dot: '#4A90D9', label: 'Terisi · ♂', pulse: true };
    if (bed.jenis_kelamin === 'P') return { card: 'rgba(217,81,122,0.06)', border: 'rgba(217,81,122,0.2)', top: '#D9517A', badge: 'rgba(217,81,122,0.15)', badgeTxt: '#D9517A', dot: '#D9517A', label: 'Terisi · ♀', pulse: true };
    return                         { card: 'rgba(255,255,255,0.04)',        border: 'rgba(255,255,255,0.1)',  top: '#8EA89E', badge: 'rgba(255,255,255,0.08)',  badgeTxt: '#8EA89E', dot: '#8EA89E', label: 'Terisi',    pulse: true };
};

const groupStats = (beds) => ({
    k: beds.filter(b => b.Status === 'KOSONG').length,
    b: beds.filter(b => b.Status === 'BOOKING').length,
    i: beds.filter(b => b.Status === 'ISI').length,
});
</script>

<template>
    <div>
        <!-- Occupancy summary bar -->
        <div class="mb-4 p-3 rounded-xl" style="background:rgba(255,255,255,0.04); border:1px solid rgba(45,217,164,0.1)">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-xs font-semibold uppercase tracking-wide" style="color:var(--text-secondary); font-family:'Plus Jakarta Sans',sans-serif">Tingkat Hunian</span>
                <span class="text-sm font-bold" :style="`color:${occupancy > 80 ? '#E07050' : occupancy > 50 ? '#E0923A' : '#2DD9A4'}; font-family:'DM Mono',monospace`">{{ occupancy }}%</span>
            </div>
            <!-- Segmented bar -->
            <div class="flex h-2 rounded-full overflow-hidden" style="background:rgba(255,255,255,0.08); gap:1px">
                <div style="background:#2DD9A4; transition:width .7s" :style="`width:${summary.total > 0 ? summary.kosong/summary.total*100 : 0}%`"></div>
                <div style="background:#E0923A; transition:width .7s" :style="`width:${summary.total > 0 ? summary.booking/summary.total*100 : 0}%`"></div>
                <div style="background:#4A90D9; transition:width .7s" :style="`width:${summary.total > 0 ? summary.isiPria/summary.total*100 : 0}%`"></div>
                <div style="background:#D9517A; transition:width .7s" :style="`width:${summary.total > 0 ? summary.isiWanita/summary.total*100 : 0}%`"></div>
                <div style="background:#8EA89E; transition:width .7s" :style="`width:${summary.total > 0 ? summary.isiUnk/summary.total*100 : 0}%`"></div>
            </div>
            <!-- Legend -->
            <div class="flex flex-wrap gap-2 mt-2">
                <span v-for="leg in [
                    { color:'#2DD9A4', val: summary.kosong,   label:'Tersedia' },
                    { color:'#E0923A', val: summary.booking,  label:'Booking'  },
                    { color:'#4A90D9', val: summary.isiPria,  label:'Pria'     },
                    { color:'#D9517A', val: summary.isiWanita,label:'Wanita'   },
                ]" :key="leg.label"
                    class="flex items-center gap-1 text-xs" style="color:var(--text-secondary); font-family:'Plus Jakarta Sans',sans-serif">
                    <span class="w-2 h-2 rounded-full" :style="`background:${leg.color}`"></span>
                    {{ leg.val }} {{ leg.label }}
                </span>
                <span class="ml-auto text-xs font-medium" style="color:var(--text-secondary); font-family:'DM Mono',monospace">{{ summary.total }} bed</span>
            </div>
        </div>

        <!-- Bed groups -->
        <div v-for="(group, kode) in grouped" :key="kode" class="mb-5">
            <!-- Group header -->
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                    <div class="w-1 h-4 rounded-full" style="background:#2DD9A4"></div>
                    <span class="text-xs font-bold uppercase tracking-wide" style="color:var(--text-primary); font-family:'Plus Jakarta Sans',sans-serif">{{ kode }}</span>
                    <span class="text-xs truncate hidden sm:inline" style="color:var(--text-secondary)">— {{ group.nama }}</span>
                </div>
                <div class="flex items-center gap-1">
                    <span v-if="groupStats(group.beds).k > 0" class="text-xs font-bold px-1.5 py-0.5 rounded-full" style="background:rgba(45,217,164,0.2); color:#2DD9A4; font-family:'DM Mono',monospace">{{ groupStats(group.beds).k }}</span>
                    <span v-if="groupStats(group.beds).b > 0" class="text-xs font-bold px-1.5 py-0.5 rounded-full" style="background:rgba(224,146,58,0.2); color:#E0923A; font-family:'DM Mono',monospace">{{ groupStats(group.beds).b }}</span>
                    <span v-if="groupStats(group.beds).i > 0" class="text-xs font-bold px-1.5 py-0.5 rounded-full" style="background:rgba(74,144,217,0.2); color:#4A90D9; font-family:'DM Mono',monospace">{{ groupStats(group.beds).i }}</span>
                    <span class="text-xs px-1.5 py-0.5 rounded-full" style="background:rgba(255,255,255,0.06); color:var(--text-secondary); font-family:'DM Mono',monospace">{{ group.beds.length }}</span>
                </div>
            </div>

            <!-- Bed cards grid -->
            <div :class="['grid gap-2.5', compact ? 'grid-cols-2 sm:grid-cols-3 md:grid-cols-4' : 'grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4']">
                <div v-for="bed in group.beds" :key="bed.Kode_Ruang"
                    class="card-teal-hover relative overflow-hidden cursor-default"
                    :style="`background:${theme(bed).card}; border:1px solid ${theme(bed).border}; border-radius:12px`">
                    <!-- Color stripe top -->
                    <div class="h-1 w-full" :style="`background:${theme(bed).top}`"></div>
                    <div class="p-3">
                        <!-- Bed name + dot -->
                        <div class="flex items-start justify-between gap-1 mb-2">
                            <div class="min-w-0">
                                <p class="text-xs font-bold truncate" style="color:var(--text-primary); font-family:'Plus Jakarta Sans',sans-serif">{{ bed.nama_ruang }}</p>
                                <p class="text-xs font-mono" style="color:var(--text-secondary); font-size:10px">{{ bed.Kode_Ruang }}</p>
                            </div>
                            <span class="w-2 h-2 rounded-full flex-shrink-0 mt-0.5"
                                :class="theme(bed).pulse ? 'pulse-teal' : ''"
                                :style="`background:${theme(bed).dot}`"></span>
                        </div>
                        <!-- Info row -->
                        <div class="flex items-center gap-1.5 px-2 py-1.5 rounded-lg mb-2"
                            :style="`background:${theme(bed).badge}20`">
                            <!-- Status icon -->
                            <div class="w-6 h-6 rounded-md flex items-center justify-center flex-shrink-0"
                                :style="`background:${theme(bed).badge}`">
                                <svg v-if="bed.Status === 'KOSONG'" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" :style="`color:${theme(bed).badgeTxt}`"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                <svg v-else-if="bed.Status === 'BOOKING'" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" :style="`color:${theme(bed).badgeTxt}`"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <svg v-else class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" :style="`color:${theme(bed).badgeTxt}`"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p v-if="bed.Status === 'KOSONG'" class="text-xs font-semibold" :style="`color:${theme(bed).badgeTxt}; font-family:'DM Sans',sans-serif`">Bed Tersedia</p>
                                <p v-else-if="bed.Status === 'BOOKING'" class="text-xs font-semibold" :style="`color:${theme(bed).badgeTxt}; font-family:'DM Sans',sans-serif`">Menunggu Pasien</p>
                                <template v-else>
                                    <p class="text-xs font-semibold truncate" :style="`color:${theme(bed).badgeTxt}; font-family:'Plus Jakarta Sans',sans-serif`">{{ bed.nama_pasien ?? 'Pasien' }}</p>
                                    <p class="text-xs font-mono" style="font-size:10px; color:var(--text-secondary)">MR: {{ bed.No_MR ?? '-' }}</p>
                                </template>
                            </div>
                        </div>
                        <!-- Badge -->
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                            :style="`background:${theme(bed).badge}; color:${theme(bed).badgeTxt}; font-family:'DM Sans',sans-serif`">
                            {{ theme(bed).label }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
