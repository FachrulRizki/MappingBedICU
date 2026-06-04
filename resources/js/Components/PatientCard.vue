<script setup>
defineProps({
    pasien: { type: Object, required: true },
});
</script>

<template>
    <div class="group bg-gray-50 hover:bg-white border border-gray-100 hover:border-green-200 hover:shadow-sm rounded-xl p-3 transition-all duration-150">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0 flex-1">
                <!-- Nama -->
                <div class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-green-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    <p class="font-semibold text-gray-800 text-sm truncate">{{ pasien.nama_pasien }}</p>
                </div>
                <!-- No MR / Reg -->
                <p class="text-xs text-gray-400 mt-0.5 ml-5">
                    <span v-if="pasien.No_MR">MR: {{ pasien.No_MR }}</span>
                    <span v-if="pasien.No_MR && pasien.No_Reg" class="mx-1">·</span>
                    <span v-if="pasien.No_Reg">Reg: {{ pasien.No_Reg }}</span>
                </p>
                <slot name="meta" />
            </div>
            <div class="flex-shrink-0">
                <slot name="action" />
            </div>
        </div>
    </div>
</template>
