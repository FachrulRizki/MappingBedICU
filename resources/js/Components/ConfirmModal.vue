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
            leave-to-class="opacity-0"
        >
            <div v-if="show"
                class="fixed inset-0 z-[999] flex items-center justify-center p-4"
                @click.self="$emit('cancel')"
            >
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>

                <!-- Modal -->
                <Transition
                    enter-active-class="transition ease-out duration-200"
                    enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100"
                >
                    <div v-if="show"
                        class="relative w-full max-w-sm bg-white rounded-2xl shadow-2xl p-6"
                    >
                        <!-- Icon -->
                        <div :class="['w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4', danger ? 'bg-rose-100' : 'bg-amber-100']">
                            <svg :class="['w-7 h-7', danger ? 'text-rose-600' : 'text-amber-600']" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                        </div>

                        <!-- Title -->
                        <h3 class="text-base font-bold text-gray-900 text-center mb-2">{{ title }}</h3>

                        <!-- Message -->
                        <p class="text-sm text-gray-500 text-center leading-relaxed mb-6">{{ message }}</p>

                        <!-- Buttons -->
                        <div class="flex gap-3">
                            <button @click="$emit('cancel')"
                                class="flex-1 py-2.5 rounded-xl text-sm font-semibold text-gray-600 bg-gray-100 hover:bg-gray-200 transition-colors">
                                {{ cancelText }}
                            </button>
                            <button @click="$emit('confirm')"
                                :class="['flex-1 py-2.5 rounded-xl text-sm font-semibold text-white transition-colors', danger ? 'bg-rose-600 hover:bg-rose-700' : 'bg-green-600 hover:bg-green-700']">
                                {{ confirmText }}
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
