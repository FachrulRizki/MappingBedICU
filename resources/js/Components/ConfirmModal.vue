<script setup>
defineProps({
    show:        { type: Boolean, default: false },
    title:       { type: String,  default: 'Konfirmasi' },
    message:     { type: String,  default: 'Apakah Anda yakin?' },
    confirmText: { type: String,  default: 'Ya, Lanjutkan' },
    cancelText:  { type: String,  default: 'Batal' },
    danger:      { type: Boolean, default: false },
});
defineEmits(['confirm', 'cancel']);
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
                @click.self="$emit('cancel')">
                <div class="absolute inset-0" style="background:var(--bg-overlay); backdrop-filter:blur(6px)"></div>
                <Transition
                    enter-active-class="transition ease-out duration-200"
                    enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100">
                    <div v-if="show"
                        class="relative w-full max-w-sm p-6"
                        style="background:var(--bg-card); border-radius:16px; border:1px solid var(--border-default); box-shadow:var(--shadow-modal)">
                        <!-- Icon -->
                        <div class="w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4"
                            :style="danger ? 'background:rgba(224,112,80,0.15)' : 'background:rgba(224,146,58,0.15)'">
                            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                                :style="danger ? 'color:#E07050' : 'color:#E0923A'">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>
                        <!-- Title -->
                        <h3 class="text-base font-bold text-center mb-2"
                            style="color:var(--text-primary); font-family:'Plus Jakarta Sans',sans-serif">
                            {{ title }}
                        </h3>
                        <!-- Message -->
                        <p class="text-sm text-center leading-relaxed mb-6"
                            style="color:var(--text-secondary); font-family:'Plus Jakarta Sans',sans-serif">
                            {{ message }}
                        </p>
                        <!-- Buttons -->
                        <div class="flex gap-3">
                            <button @click="$emit('cancel')"
                                class="flex-1 py-2.5 rounded-xl text-sm font-semibold transition-all"
                                style="background:var(--bg-input); color:var(--text-secondary); border:1px solid var(--border-default); font-family:'Plus Jakarta Sans',sans-serif">
                                {{ cancelText }}
                            </button>
                            <button @click="$emit('confirm')"
                                class="flex-1 py-2.5 rounded-xl text-sm font-bold transition-all"
                                :style="danger
                                    ? 'background:#E07050; color:#FFFFFF; font-family:\'Plus Jakarta Sans\',sans-serif'
                                    : 'background:#2DD9A4; color:var(--text-on-accent); font-family:\'Plus Jakarta Sans\',sans-serif'">
                                {{ confirmText }}
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
