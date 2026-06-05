<script setup>
defineProps({
    title: { type: String, required: true },
    icon:  { type: String, required: true },
    count: { type: Number, default: 0 },
    color: { type: String, default: 'teal' },
    badge: { type: String, default: null },
});

const accents = {
    teal:  { top: '#1A9E8F', badge: '#F0FBF9', badgeText: '#1A9E8F' },
    mint:  { top: '#2DC5B2', badge: '#E8F9F7', badgeText: '#2DC5B2' },
    coral: { top: '#E05A5A', badge: '#FDF2F2', badgeText: '#E05A5A' },
    amber: { top: '#E0923A', badge: '#FDF6EE', badgeText: '#E0923A' },
    sky:   { top: '#4A90D9', badge: '#EEF5FC', badgeText: '#4A90D9' },
    gray:  { top: '#8A9E9E', badge: '#F4F7F6', badgeText: '#8A9E9E' },
};
</script>

<template>
    <div class="card-teal overflow-hidden">
        <!-- Coloured top stripe -->
        <div class="h-1 w-full" :style="`background:${accents[color]?.top ?? accents.teal.top}`"></div>
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3 border-b" style="border-color:#E8EEEE">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                    :style="`color:${accents[color]?.top ?? accents.teal.top}`">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="icon"/>
                </svg>
                <span class="text-xs font-semibold uppercase tracking-wide" style="color:var(--text-primary); font-family:'DM Sans',sans-serif">{{ title }}</span>
            </div>
            <span class="text-xs font-bold px-2.5 py-0.5 rounded-full"
                :style="`background:${accents[color]?.badge}; color:${accents[color]?.badgeText}`">
                {{ badge ?? count }}
            </span>
        </div>
        <!-- Content -->
        <div class="p-3 space-y-2">
            <slot />
            <div v-if="count === 0" class="text-center py-8" style="color:#A8DDD7">
                <svg class="w-8 h-8 mx-auto mb-2 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                </svg>
                <p class="text-sm" style="font-family:'DM Sans',sans-serif">Tidak ada data</p>
            </div>
        </div>
    </div>
</template>
