<script setup>
import { ref, watch } from 'vue';
const props = defineProps({ flash: { type: Object, default: () => ({}) } });
const visible = ref(false);
const message = ref('');
const type    = ref('success');
watch(() => props.flash, (f) => {
    if (f?.success) { message.value = f.success; type.value = 'success'; visible.value = true; setTimeout(() => visible.value = false, 4000); }
    else if (f?.error) { message.value = f.error; type.value = 'error'; visible.value = true; setTimeout(() => visible.value = false, 5000); }
}, { immediate: true, deep: true });

// Accent colors — vivid on both themes
const accentColor  = { success: '#2DD9A4', error: '#E07050' };
const accentBg     = { success: 'rgba(45,217,164,0.15)', error: 'rgba(224,112,80,0.15)' };
const accentBorder = { success: 'rgba(45,217,164,0.3)',  error: 'rgba(224,112,80,0.3)'  };
const iconPath     = { success: 'M5 13l4 4L19 7',        error: 'M6 18L18 6M6 6l12 12' };
</script>

<template>
    <Transition
        enter-active-class="transition ease-out duration-300"
        enter-from-class="translate-y-2 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition ease-in duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0">
        <div v-if="visible"
            class="fixed top-5 right-5 z-[999] flex items-start gap-3 px-4 py-3.5 max-w-sm"
            :style="`
                border-radius: 12px;
                border: 1px solid ${accentBorder[type]};
                box-shadow: var(--shadow-modal);
                background: var(--bg-card);
            `">
            <!-- Accent left bar -->
            <div class="absolute left-0 top-0 bottom-0 w-1 rounded-l-xl"
                :style="`background:${accentColor[type]}`"></div>

            <!-- Icon circle -->
            <div class="w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 mt-0.5 ml-1"
                :style="`background:${accentBg[type]}`">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"
                    :style="`color:${accentColor[type]}`">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="iconPath[type]"/>
                </svg>
            </div>

            <!-- Text block -->
            <div class="flex-1 min-w-0">
                <p class="text-xs font-bold mb-0.5"
                    :style="`color:${accentColor[type]}; font-family:'Plus Jakarta Sans',sans-serif`">
                    {{ type === 'success' ? 'Berhasil' : 'Gagal' }}
                </p>
                <p class="text-sm font-medium leading-snug"
                    style="color:var(--text-primary); font-family:'Plus Jakarta Sans',sans-serif">
                    {{ message }}
                </p>
            </div>

            <!-- Close button -->
            <button @click="visible = false"
                class="flex-shrink-0 mt-0.5 opacity-50 hover:opacity-100 transition-opacity"
                style="color:var(--text-secondary)">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </Transition>
</template>
