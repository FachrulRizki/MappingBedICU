<script setup>
defineProps({
    title:  { type: String,  required: true },
    icon:   { type: String,  required: true }, // SVG path d=
    count:  { type: Number,  default: 0 },
    color:  { type: String,  default: 'gray' },
    badge:  { type: String,  default: null },  // teks badge override
});

const palette = {
    gray:   { top: 'border-t-gray-400',   head: 'bg-gray-50',   cnt: 'bg-gray-100 text-gray-700',   icon: 'text-gray-400'   },
    amber:  { top: 'border-t-amber-400',  head: 'bg-amber-50',  cnt: 'bg-amber-100 text-amber-700',  icon: 'text-amber-500'  },
    teal:   { top: 'border-t-teal-500',   head: 'bg-teal-50',   cnt: 'bg-teal-100 text-teal-700',    icon: 'text-teal-500'   },
    rose:   { top: 'border-t-rose-500',   head: 'bg-rose-50',   cnt: 'bg-rose-100 text-rose-700',    icon: 'text-rose-500'   },
    green:  { top: 'border-t-green-600',  head: 'bg-green-50',  cnt: 'bg-green-100 text-green-700',  icon: 'text-green-600'  },
    blue:   { top: 'border-t-blue-500',   head: 'bg-blue-50',   cnt: 'bg-blue-100 text-blue-700',    icon: 'text-blue-500'   },
    indigo: { top: 'border-t-indigo-500', head: 'bg-indigo-50', cnt: 'bg-indigo-100 text-indigo-700',icon: 'text-indigo-500' },
};
</script>

<template>
    <div :class="['bg-white rounded-2xl shadow-sm border border-gray-100 border-t-4 overflow-hidden', palette[color]?.top]">
        <!-- Header -->
        <div :class="['flex items-center justify-between px-4 py-3 border-b border-gray-100', palette[color]?.head]">
            <div class="flex items-center gap-2">
                <svg :class="['w-4 h-4', palette[color]?.icon]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="icon"/>
                </svg>
                <span class="text-xs font-bold tracking-wide uppercase text-gray-600">{{ title }}</span>
            </div>
            <span :class="['text-xs font-bold px-2.5 py-0.5 rounded-full', palette[color]?.cnt]">
                {{ badge ?? count }}
            </span>
        </div>
        <!-- Content -->
        <div class="p-3 space-y-2">
            <slot />
            <div v-if="count === 0" class="text-center py-8 text-gray-300">
                <svg class="w-8 h-8 mx-auto mb-2 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                </svg>
                <p class="text-sm">Tidak ada data</p>
            </div>
        </div>
    </div>
</template>
