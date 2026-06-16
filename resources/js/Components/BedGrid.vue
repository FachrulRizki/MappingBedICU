<script setup>
defineProps({ kamar: { type: Array, default: () => [] } });

const theme = (bed) => {
    const s = (bed.Status ?? '').toUpperCase();
    if (s === 'KOSONG')  return { bg: '#EBF9F1', border: 'rgba(39,174,96,0.3)',  stripe: '#27AE60', txt: '#27AE60', label: '✓ Tersedia' };
    if (s === 'BOOKING') return { bg: '#FDF3E9', border: 'rgba(230,126,34,0.3)', stripe: '#E67E22', txt: '#E67E22', label: '⏳ Booking' };
    return                      { bg: '#EAF4FB', border: 'rgba(52,152,219,0.3)',  stripe: '#3498DB', txt: '#3498DB', label: '● Terisi'  };
};

const grouped = (() => {
    const map = {};
    // computed inline saat render
    return map;
})();
</script>

<template>
<div class="space-y-4" style="font-family:'Inter','Plus Jakarta Sans',sans-serif">
    <div v-if="kamar.length === 0" class="text-center py-10" style="color:var(--text-secondary)">
        <p class="text-sm">Tidak ada data bed</p>
    </div>
    <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-6 gap-3">
        <div v-for="bed in kamar" :key="bed.Kode_Ruang"
            class="relative overflow-hidden rounded-xl transition-all"
            :style="`background:${theme(bed).bg}; border:1px solid ${theme(bed).border}`">
            <div class="h-1" :style="`background:${theme(bed).stripe}`"></div>
            <div class="p-3 space-y-1.5">
                <p class="text-xs font-bold truncate" style="color:var(--text-primary)">{{ bed.nama_ruang }}</p>
                <p class="text-xs font-mono" style="color:var(--text-muted); font-size:10px">{{ bed.Kode_Ruang }}</p>
                <p class="text-xs font-semibold" :style="`color:${theme(bed).txt}`">{{ theme(bed).label }}</p>
                <p v-if="bed.No_MR" class="text-xs font-mono" style="color:var(--text-secondary); font-size:10px">
                    MR: {{ bed.No_MR }}
                </p>
            </div>
        </div>
    </div>
</div>
</template>
