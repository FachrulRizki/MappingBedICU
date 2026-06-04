<script setup>
defineProps({
    label:    { type: String,  required: true },
    value:    { type: [Number, String], required: true },
    sub:      { type: String,  default: null },
    icon:     { type: String,  required: true },  // SVG path d=
    color:    { type: String,  default: 'green' }, // green | amber | rose | blue | teal
    trend:    { type: String,  default: null },    // 'up' | 'down' | null
    trendVal: { type: String,  default: null },
});

const palette = {
    green: { bg: 'bg-green-50',  border: 'border-green-100', icon: 'bg-green-600',  text: 'text-green-700',  val: 'text-green-900'  },
    teal:  { bg: 'bg-teal-50',   border: 'border-teal-100',  icon: 'bg-teal-600',   text: 'text-teal-700',   val: 'text-teal-900'   },
    amber: { bg: 'bg-amber-50',  border: 'border-amber-100', icon: 'bg-amber-500',  text: 'text-amber-700',  val: 'text-amber-900'  },
    rose:  { bg: 'bg-rose-50',   border: 'border-rose-100',  icon: 'bg-rose-600',   text: 'text-rose-700',   val: 'text-rose-900'   },
    blue:  { bg: 'bg-blue-50',   border: 'border-blue-100',  icon: 'bg-blue-600',   text: 'text-blue-700',   val: 'text-blue-900'   },
};
</script>

<template>
    <div :class="['rounded-2xl border p-4 flex items-start gap-4 transition-shadow hover:shadow-md', palette[color].bg, palette[color].border]">
        <!-- Icon -->
        <div :class="['w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0 shadow-sm', palette[color].icon]">
            <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" :d="icon" />
            </svg>
        </div>
        <!-- Content -->
        <div class="flex-1 min-w-0">
            <p :class="['text-xs font-semibold uppercase tracking-wide', palette[color].text]">{{ label }}</p>
            <p :class="['text-2xl font-bold mt-0.5 leading-tight', palette[color].val]">{{ value }}</p>
            <div v-if="sub || trendVal" class="flex items-center gap-2 mt-1">
                <span v-if="trendVal" :class="['flex items-center gap-0.5 text-xs font-semibold', trend === 'up' ? 'text-rose-600' : 'text-green-600']">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="trend === 'up' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7'" />
                    </svg>
                    {{ trendVal }}
                </span>
                <span v-if="sub" class="text-xs text-gray-400">{{ sub }}</span>
            </div>
        </div>
    </div>
</template>
