<script setup>
defineProps({
    show:    { type: Boolean, default: false },
    type:    { type: String,  default: 'success' }, // 'success' | 'error' | 'warning' | 'info'
    title:   { type: String,  default: '' },
    message: { type: String,  default: '' },
});
defineEmits(['close']);

const config = {
    success: {
        bg:     'bg-green-50',
        border: 'border-green-200',
        icon:   'bg-green-100',
        iconColor: 'text-green-600',
        titleColor: 'text-green-900',
        msgColor:   'text-green-700',
        btnColor:   'bg-green-600 hover:bg-green-700',
        iconPath: 'M5 13l4 4L19 7',
    },
    error: {
        bg:     'bg-red-50',
        border: 'border-red-200',
        icon:   'bg-red-100',
        iconColor: 'text-red-600',
        titleColor: 'text-red-900',
        msgColor:   'text-red-700',
        btnColor:   'bg-red-600 hover:bg-red-700',
        iconPath: 'M6 18L18 6M6 6l12 12',
    },
    warning: {
        bg:     'bg-amber-50',
        border: 'border-amber-200',
        icon:   'bg-amber-100',
        iconColor: 'text-amber-600',
        titleColor: 'text-amber-900',
        msgColor:   'text-amber-700',
        btnColor:   'bg-amber-600 hover:bg-amber-700',
        iconPath: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
    },
    info: {
        bg:     'bg-blue-50',
        border: 'border-blue-200',
        icon:   'bg-blue-100',
        iconColor: 'text-blue-600',
        titleColor: 'text-blue-900',
        msgColor:   'text-blue-700',
        btnColor:   'bg-blue-600 hover:bg-blue-700',
        iconPath: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    },
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
            leave-to-class="opacity-0"
        >
            <div v-if="show"
                class="fixed inset-0 z-[999] flex items-center justify-center p-4"
                @click.self="$emit('close')"
            >
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>

                <!-- Modal -->
                <Transition
                    enter-active-class="transition ease-out duration-200"
                    enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100"
                    leave-active-class="transition ease-in duration-150"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-95"
                >
                    <div v-if="show"
                        :class="['relative w-full max-w-sm rounded-2xl border shadow-2xl p-6 text-center', config[type].bg, config[type].border]"
                    >
                        <!-- Icon -->
                        <div :class="['w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-4', config[type].icon]">
                            <svg :class="['w-7 h-7', config[type].iconColor]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="config[type].iconPath" />
                            </svg>
                        </div>

                        <!-- Title -->
                        <h3 :class="['text-base font-bold mb-2', config[type].titleColor]">{{ title }}</h3>

                        <!-- Message -->
                        <p :class="['text-sm leading-relaxed mb-5', config[type].msgColor]">{{ message }}</p>

                        <!-- Button -->
                        <button @click="$emit('close')"
                            :class="['w-full py-2.5 rounded-xl text-sm font-semibold text-white transition-colors', config[type].btnColor]">
                            Tutup
                        </button>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
