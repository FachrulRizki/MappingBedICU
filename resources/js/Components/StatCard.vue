<script setup>
defineProps({
    label:  { type: String,           required: true },
    value:  { type: [Number, String], required: true },
    sub:    { type: String,           default: null },
    icon:   { type: String,           required: true },
    color:  { type: String,           default: 'teal' },
    trend:  { type: String,           default: null },  // '+1.2%' or '-0.5%'
});

// Dark-mode palette: icon bg, icon color, accent text
const palette = {
    teal:   { bg: 'rgba(45,217,164,0.12)',  icon: '#2DD9A4',  accent: '#2DD9A4'  },
    mint:   { bg: 'rgba(74,234,181,0.12)',  icon: '#4AEAB5',  accent: '#4AEAB5'  },
    coral:  { bg: 'rgba(224,112,80,0.12)',  icon: '#E07050',  accent: '#E07050'  },
    amber:  { bg: 'rgba(224,146,58,0.12)',  icon: '#E0923A',  accent: '#E0923A'  },
    sky:    { bg: 'rgba(74,144,217,0.12)',  icon: '#4A90D9',  accent: '#4A90D9'  },
    gray:   { bg: 'rgba(142,168,158,0.12)', icon: '#8EA89E',  accent: '#8EA89E'  },
};
</script>

<template>
    <div class="kpi-card flex flex-col justify-between p-5 stat-card-kpi"
        style="background:var(--bg-card); border-radius:14px; border:1px solid var(--border-default); min-height:100px; cursor:default"
        @mouseenter="$el.style.borderColor='var(--border-card-hover)'"
        @mouseleave="$el.style.borderColor='var(--border-default)'">

        <!-- Top row: icon + trend -->
        <div class="flex items-center justify-between mb-3">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center"
                :style="`background:${palette[color]?.bg ?? palette.teal.bg}`">
                <svg style="width:18px;height:18px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"
                    :style="`color:${palette[color]?.icon ?? palette.teal.icon}`">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="icon"/>
                </svg>
            </div>
            <span v-if="trend"
                class="text-xs font-semibold px-2 py-0.5 rounded-full"
                :style="trend.startsWith('+')
                    ? 'background:rgba(61,219,138,0.12); color:#3DDB8A'
                    : 'background:rgba(224,112,80,0.12); color:#E07050'"
                style="font-family:'DM Mono',monospace">
                {{ trend }}
            </span>
        </div>

        <!-- Value -->
        <p class="font-bold leading-none mb-1"
            style="font-size:28px; color:var(--text-primary); font-family:'Plus Jakarta Sans',sans-serif; letter-spacing:-0.02em">
            {{ value }}
        </p>

        <!-- Label -->
        <p class="text-xs font-semibold truncate" style="color:var(--text-secondary); font-family:'Plus Jakarta Sans',sans-serif">
            {{ label }}
        </p>

        <!-- Sub -->
        <p v-if="sub" class="text-xs mt-0.5 truncate" :style="`color:${palette[color]?.accent ?? '#2DD9A4'}; font-family:'DM Mono',monospace; font-size:11px`">
            {{ sub }}
        </p>
    </div>
</template>
