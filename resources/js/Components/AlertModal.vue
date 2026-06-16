<script setup>
defineProps({
    show:    { type: Boolean, default: false },
    type:    { type: String,  default: 'success' },
    title:   { type: String,  default: '' },
    message: { type: String,  default: '' },
});
defineEmits(['close']);

const cfg = {
    success: { icon: 'M5 13l4 4L19 7',  color: '#27AE60', bg: '#EBF9F1', border: 'rgba(39,174,96,0.2)',  btn: '#27AE60' },
    error:   { icon: 'M6 18L18 6M6 6l12 12', color: '#E74C3C', bg: '#FDEDEC', border: 'rgba(231,76,60,0.2)',  btn: '#E74C3C' },
    warning: { icon: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z', color:'#E67E22', bg:'#FDF3E9', border:'rgba(230,126,34,0.2)', btn:'#E67E22' },
    info:    { icon: 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z', color:'#3498DB', bg:'#EAF4FB', border:'rgba(52,152,219,0.2)', btn:'#3498DB' },
};
</script>

<template>
<Teleport to="body">
    <Transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0"
        leave-active-class="transition ease-in duration-150" leave-to-class="opacity-0">
        <div v-if="show" class="fixed inset-0 z-[999] flex items-center justify-center p-4"
            style="background:var(--bg-overlay); backdrop-filter:blur(8px)"
            @click.self="$emit('close')">
            <Transition enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0 scale-90" enter-to-class="opacity-100 scale-100">
                <div v-if="show" class="relative w-full max-w-sm p-6 text-center"
                    :style="`background:var(--bg-surface); border-radius:20px;
                             border:1px solid ${cfg[type].border};
                             box-shadow:var(--shadow-modal)`">
                    <div class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-4"
                        :style="`background:${cfg[type].bg}`">
                        <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"
                            :style="`color:${cfg[type].color}`">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="cfg[type].icon"/>
                        </svg>
                    </div>
                    <h3 class="text-base font-bold mb-2" style="color:var(--text-primary)">{{ title }}</h3>
                    <p class="text-sm leading-relaxed mb-5" style="color:var(--text-secondary)">{{ message }}</p>
                    <button @click="$emit('close')"
                        class="w-full py-2.5 rounded-xl text-sm font-semibold transition-all"
                        :style="`background:${cfg[type].btn}; color:#fff`"
                        @mouseenter="e => e.currentTarget.style.filter='brightness(1.08)'"
                        @mouseleave="e => e.currentTarget.style.filter=''">
                        Tutup
                    </button>
                </div>
            </Transition>
        </div>
    </Transition>
</Teleport>
</template>
