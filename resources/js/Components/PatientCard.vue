<script setup>
defineProps({ pasien: { type: Object, required: true } });
</script>

<template>
    <div class="card-teal-hover flex items-start justify-between gap-2 p-3 rounded-xl transition-all"
        style="background:var(--bg-main); border:1px solid var(--border-default)">
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color:#2DC5B2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                <p class="text-sm font-semibold truncate" style="color:var(--text-primary); font-family:'DM Sans',sans-serif">{{ pasien.nama_pasien }}</p>
            </div>
            <p class="text-xs mt-0.5 ml-5" style="color:var(--text-secondary); font-family:'DM Mono',monospace">
                <span v-if="pasien.No_MR">MR: {{ pasien.No_MR }}</span>
                <span v-if="pasien.No_MR && pasien.No_Reg" class="mx-1">·</span>
                <span v-if="pasien.No_Reg">Reg: {{ pasien.No_Reg }}</span>
            </p>
            <slot name="meta"/>
        </div>
        <div class="flex-shrink-0"><slot name="action"/></div>
    </div>
</template>
