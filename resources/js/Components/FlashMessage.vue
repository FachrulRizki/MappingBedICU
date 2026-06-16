<script setup>
import { ref, watch } from 'vue';

const props = defineProps({ flash: { type: Object, default: () => ({}) } });

const visible  = ref(false);
const message  = ref('');
const type     = ref('success');
let hideTimer  = null;

watch(() => props.flash, (f) => {
    if (f?.success) { message.value = f.success; type.value = 'success'; show(); }
    else if (f?.error) { message.value = f.error; type.value = 'error'; show(); }
}, { immediate: true, deep: true });

function show() {
    clearTimeout(hideTimer);
    visible.value = true;
    hideTimer = setTimeout(() => visible.value = false, type.value === 'error' ? 5000 : 4000);
}

const cfg = {
    success: { border: 'rgba(39,174,96,0.3)',  bg: '#EBF9F1', color: '#27AE60', icon: 'M5 13l4 4L19 7' },
    error:   { border: 'rgba(231,76,60,0.3)',   bg: '#FDEDEC', color: '#E74C3C', icon: 'M6 18L18 6M6 6l12 12' },
};
</script>

<template>
<Transition
    enter-active-class="transition ease-out duration-300"
    enter-from-class="translate-y-2 opacity-0 scale-95"
    enter-to-class="translate-y-0 opacity-100 scale-100"
    leave-active-class="transition ease-in duration-200"
    leave-from-class="opacity-100"
    leave-to-class="opacity-0 translate-y-1">
    <div v-if="visible"
        class="fixed top-5 right-5 z-[999] flex items-start gap-3 px-4 py-3.5 rounded-2xl max-w-xs"
        :style="`background:${cfg[type].bg}; border:1px solid ${cfg[type].border};
                 box-shadow:0 8px 24px rgba(0,0,0,0.12), 0 2px 8px rgba(0,0,0,0.08);`">

        <!-- Icon -->
        <div class="w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5"
            :style="`background:${cfg[type].color}20`">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"
                :style="`color:${cfg[type].color}`">
                <path stroke-linecap="round" stroke-linejoin="round" :d="cfg[type].icon"/>
            </svg>
        </div>

        <!-- Text -->
        <div class="flex-1 min-w-0">
            <p class="text-xs font-bold" :style="`color:${cfg[type].color}`">
                {{ type === 'success' ? 'Berhasil' : 'Gagal' }}
            </p>
            <p class="text-sm font-medium mt-0.5 leading-snug" style="color:#1A2B3C">{{ message }}</p>
        </div>

        <!-- Close -->
        <button @click="visible = false" class="flex-shrink-0 mt-0.5 opacity-50 hover:opacity-100 transition-opacity">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                :style="`color:${cfg[type].color}`">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
</Transition>
</template>
