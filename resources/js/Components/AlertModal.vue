<script setup>
defineProps({
    show:    { type: Boolean, default: false },
    type:    { type: String,  default: 'success' },
    title:   { type: String,  default: '' },
    message: { type: String,  default: '' },
});
defineEmits(['close']);

// Icon paths per type
const iconPath = {
    success: 'M5 13l4 4L19 7',
    error:   'M6 18L18 6M6 6l12 12',
    warning: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
    info:    'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
};

// Accent colors — always vivid regardless of theme
const accentColor = {
    success: '#2DD9A4',
    error:   '#E07050',
    warning: '#E0923A',
    info:    '#4A90D9',
};
const accentBg = {
    success: 'rgba(45,217,164,0.15)',
    error:   'rgba(224,112,80,0.15)',
    warning: 'rgba(224,146,58,0.15)',
    info:    'rgba(74,144,217,0.15)',
};
const accentBorder = {
    success: 'rgba(45,217,164,0.25)',
    error:   'rgba(224,112,80,0.25)',
    warning: 'rgba(224,146,58,0.25)',
    info:    'rgba(74,144,217,0.25)',
};
const btnHover = {
    success: '#4AEAB5',
    error:   '#C44E4E',
    warning: '#C47E30',
    info:    '#3A7AC4',
};
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0">
            <div v-if="show"
                class="fixed inset-0 z-[999] flex items-center justify-center p-4"
                @click.self="$emit('close')">
                <div class="absolute inset-0" style="background:var(--bg-overlay); backdrop-filter:blur(6px)"></div>
                <Transition
                    enter-active-class="transition ease-out duration-200"
                    enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100">
                    <div v-if="show"
                        class="relative w-full max-w-sm p-6 text-center"
                        :style="`
                            background: var(--bg-card);
                            border: 1px solid ${accentBorder[type]};
                            border-radius: 16px;
                            box-shadow: var(--shadow-modal);
                        `">
                        <!-- Icon circle -->
                        <div class="w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4"
                            :style="`background:${accentBg[type]}`">
                            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"
                                :style="`color:${accentColor[type]}`">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="iconPath[type]"/>
                            </svg>
                        </div>
                        <!-- Title -->
                        <h3 class="text-base font-bold mb-2"
                            style="color:var(--text-primary); font-family:'Plus Jakarta Sans',sans-serif">
                            {{ title }}
                        </h3>
                        <!-- Message -->
                        <p class="text-sm leading-relaxed mb-5"
                            style="color:var(--text-secondary); font-family:'Plus Jakarta Sans',sans-serif">
                            {{ message }}
                        </p>
                        <!-- Button -->
                        <button @click="$emit('close')"
                            class="w-full py-2.5 rounded-xl text-sm font-bold transition-all"
                            :style="`background:${accentColor[type]}; color:var(--text-on-accent); font-family:'Plus Jakarta Sans',sans-serif`"
                            @mouseover="(e) => e.currentTarget.style.background = btnHover[type]"
                            @mouseout="(e) => e.currentTarget.style.background = accentColor[type]">
                            Tutup
                        </button>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
