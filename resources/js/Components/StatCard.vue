<script setup>
defineProps({
    label: { type: String,           required: true },
    value: { type: [Number, String], required: true },
    sub:   { type: String,           default: null },
    icon:  { type: String,           required: true },
    color: { type: String,           default: 'emerald' },
    trend: { type: String,           default: null },
});

// Design system — Soft Modernism palette aligned to design.md
const palette = {
    emerald: { bg: 'rgba(0,168,132,0.10)', icon: '#00A884', accent: '#00A884',  tint: 'rgba(0,168,132,0.06)'  },
    teal:    { bg: 'rgba(0,207,163,0.10)', icon: '#00CFA3', accent: '#00CFA3',  tint: 'rgba(0,207,163,0.06)'  },
    coral:   { bg: 'rgba(231,76,60,0.10)', icon: '#E74C3C', accent: '#E74C3C',  tint: 'rgba(231,76,60,0.06)'  },
    amber:   { bg: 'rgba(230,126,34,0.10)',icon: '#E67E22', accent: '#E67E22',  tint: 'rgba(230,126,34,0.06)' },
    blue:    { bg: 'rgba(52,152,219,0.10)',icon: '#3498DB', accent: '#3498DB',  tint: 'rgba(52,152,219,0.06)' },
    purple:  { bg: 'rgba(142,68,173,0.10)',icon: '#8E44AD', accent: '#8E44AD',  tint: 'rgba(142,68,173,0.06)' },
    gray:    { bg: 'rgba(90,107,124,0.10)',icon: '#5A6B7C', accent: '#5A6B7C',  tint: 'rgba(90,107,124,0.06)' },
    // legacy aliases
    sky:     { bg: 'rgba(52,152,219,0.10)',icon: '#3498DB', accent: '#3498DB',  tint: 'rgba(52,152,219,0.06)' },
    mint:    { bg: 'rgba(0,168,132,0.10)', icon: '#00A884', accent: '#00A884',  tint: 'rgba(0,168,132,0.06)'  },
};

const p = (color) => palette[color] ?? palette.emerald;
</script>

<template>
<div class="kpi-card flex flex-col justify-between p-5 card-dark"
    style="min-height:108px; cursor:default; transition:transform .25s ease, box-shadow .25s ease, border-color .22s ease"
    @mouseenter="$el.style.transform='translateY(-4px)'; $el.style.boxShadow='var(--shadow-card-hover)'"
    @mouseleave="$el.style.transform=''; $el.style.boxShadow=''">

    <!-- Top: icon + trend -->
    <div class="flex items-center justify-between mb-3">
        <div class="w-11 h-11 rounded-xl flex items-center justify-center"
            :style="`background:${p(color).bg}`">
            <svg style="width:20px;height:20px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"
                :style="`color:${p(color).icon}`">
                <path stroke-linecap="round" stroke-linejoin="round" :d="icon"/>
            </svg>
        </div>
        <span v-if="trend"
            class="text-xs font-semibold px-2 py-1 rounded-full"
            :style="trend.startsWith('+')
                ? 'background:rgba(39,174,96,0.12); color:#27AE60'
                : 'background:rgba(231,76,60,0.12); color:#E74C3C'"
            style="font-family:'DM Mono',monospace">
            {{ trend }}
        </span>
    </div>

    <!-- Value — KPI number large -->
    <p class="font-extrabold leading-none mb-1"
        style="font-size:clamp(26px,3vw,34px); color:var(--text-primary); letter-spacing:-0.03em; font-family:'Inter',sans-serif">
        {{ value }}
    </p>

    <!-- Label -->
    <p class="text-xs font-semibold truncate" style="color:var(--text-secondary)">{{ label }}</p>

    <!-- Sub -->
    <p v-if="sub" class="mt-0.5 truncate" :style="`color:${p(color).accent}; font-family:'DM Mono',monospace; font-size:10px`">
        {{ sub }}
    </p>
</div>
</template>
