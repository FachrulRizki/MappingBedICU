<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    flash: { type: Object, default: () => ({}) },
});

const visible = ref(false);
const message = ref('');
const type    = ref('success');

watch(() => props.flash, (f) => {
    if (f?.success) {
        message.value = f.success; type.value = 'success'; visible.value = true;
        setTimeout(() => visible.value = false, 4000);
    } else if (f?.error) {
        message.value = f.error; type.value = 'error'; visible.value = true;
        setTimeout(() => visible.value = false, 5000);
    }
}, { immediate: true, deep: true });
</script>

<template>
    <Transition
        enter-active-class="transition ease-out duration-300"
        enter-from-class="translate-y-2 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition ease-in duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div v-if="visible"
            :class="[
                'fixed top-4 right-4 z-[999] flex items-start gap-3 px-4 py-3 rounded-xl shadow-xl border text-sm font-medium max-w-sm',
                type === 'success'
                    ? 'bg-green-50 border-green-200 text-green-800'
                    : 'bg-red-50 border-red-200 text-red-800',
            ]"
        >
            <!-- Icon -->
            <div :class="['w-7 h-7 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5', type === 'success' ? 'bg-green-100' : 'bg-red-100']">
                <svg v-if="type === 'success'" class="w-4 h-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
                <svg v-else class="w-4 h-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </div>
            <span class="flex-1 leading-snug">{{ message }}</span>
            <button @click="visible = false" class="text-current opacity-40 hover:opacity-80 transition-opacity ml-1 mt-0.5">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </Transition>
</template>
