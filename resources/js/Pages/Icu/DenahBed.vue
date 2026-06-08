<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import BedGrid   from '@/Components/BedGrid.vue';

defineProps({
    semuaKamar: { type: Array,  default: () => [] },
    flash:      { type: Object, default: () => ({}) },
});
</script>

<template>
    <AppLayout :flash="flash" page-title="Denah Bed ICU">
        <div class="p-4 sm:p-6 space-y-4" style="font-family:'Plus Jakarta Sans',sans-serif">

            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h1 class="font-bold text-lg" style="color:var(--text-primary)">Denah Bed ICU</h1>
                    <p class="text-sm mt-0.5" style="color:var(--text-secondary)">Visualisasi real-time status semua bed</p>
                </div>
                <!-- Legend -->
                <div class="flex items-center gap-4 flex-wrap">
                    <span class="flex items-center gap-1.5 text-xs" style="color:var(--text-secondary)">
                        <span class="w-3 h-3 rounded" style="background:#2DD9A4"></span>Kosong
                    </span>
                    <span class="flex items-center gap-1.5 text-xs" style="color:var(--text-secondary)">
                        <span class="w-3 h-3 rounded" style="background:#4A90D9"></span>Pria
                    </span>
                    <span class="flex items-center gap-1.5 text-xs" style="color:var(--text-secondary)">
                        <span class="w-3 h-3 rounded" style="background:#D9517A"></span>Wanita
                    </span>
                    <span class="flex items-center gap-1.5 text-xs" style="color:var(--text-secondary)">
                        <span class="w-3 h-3 rounded" style="background:#E0923A"></span>Booking
                    </span>
                </div>
            </div>

            <!-- Bed Grid -->
            <div class="card-dark p-5">
                <BedGrid :kamar="semuaKamar"/>
            </div>
        </div>
    </AppLayout>
</template>
