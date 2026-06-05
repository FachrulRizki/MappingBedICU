<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import FlashMessage from '@/Components/FlashMessage.vue';
import { useTheme } from '@/composables/useTheme.js';

defineProps({
    flash:     { type: Object, default: () => ({}) },
    pageTitle: { type: String, default: 'Dashboard' },
});

// ── Theme ──────────────────────────────────────────────────
const { theme, toggle, init: initTheme } = useTheme();
const isDark = computed(() => theme.value === 'dark');

// ── Sidebar mobile ─────────────────────────────────────────
const mobileOpen  = ref(false);
const closeMobile = () => { mobileOpen.value = false; };
const onResize    = () => { if (window.innerWidth >= 1024) mobileOpen.value = false; };
onMounted(()  => { window.addEventListener('resize', onResize); initTheme(); });
onUnmounted(() => window.removeEventListener('resize', onResize));

// ── Clock ──────────────────────────────────────────────────
const now = ref(new Date());
let clockTimer = null;
onMounted(()  => { clockTimer = setInterval(() => now.value = new Date(), 1000); });
onUnmounted(() => clearInterval(clockTimer));
const formattedTime = computed(() =>
    now.value.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
);
const formattedDate = computed(() =>
    now.value.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
);

// ── Nav items ──────────────────────────────────────────────
const navItems = [
    {
        label: 'Dashboard',
        href:  '/dashboard-icu',
        icon:  'M4 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm10 0a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zm10 0a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z',
    },
    {
        label: 'Pendaftaran',
        href:  '/icu/pendaftaran',
        icon:  'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    },
    {
        label: 'IGD & Triase',
        href:  '/icu/igd',
        icon:  'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z',
    },
    {
        label: 'SPRI',
        href:  '/icu/spri',
        icon:  'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
    },
    {
        label: 'Alokasi Bed',
        href:  '/icu/alokasi-bed',
        icon:  'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
    },
    {
        label: 'Pasien ICU',
        href:  '/icu/pasien-icu',
        icon:  'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
    },
];

const moreItems = [
    {
        label: 'Pengaturan',
        href:  '#',
        icon:  'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
    },
    {
        label: 'Tutorial',
        href:  '#',
        icon:  'M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    },
    {
        label: 'Bantuan',
        href:  '#',
        icon:  'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    },
];

const currentPath = typeof window !== 'undefined' ? window.location.pathname : '';
const isActive = (href) => currentPath === href || currentPath.startsWith(href + '/');

// ── Auto-refresh ───────────────────────────────────────────
const countdown = ref(30);
let refreshTimer = null;
onMounted(() => {
    refreshTimer = setInterval(() => {
        countdown.value--;
        if (countdown.value <= 0) { router.reload(); countdown.value = 30; }
    }, 1000);
});
onUnmounted(() => clearInterval(refreshTimer));
const manualRefresh = () => { router.reload(); countdown.value = 30; };

// SVG icon paths for theme toggle
const iconSun  = 'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M16 12a4 4 0 11-8 0 4 4 0 018 0z';
const iconMoon = 'M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z';
</script>

<template>
    <div class="flex h-screen overflow-hidden" style="background:var(--bg-main)">
        <FlashMessage :flash="flash" />

        <!-- Mobile overlay -->
        <Transition name="sidebar">
            <div v-if="mobileOpen" class="fixed inset-0 z-40 lg:hidden" @click="closeMobile">
                <div class="absolute inset-0" style="background:var(--bg-overlay); backdrop-filter:blur(4px)"></div>
            </div>
        </Transition>

        <!-- ══════════════ SIDEBAR 240px ══════════════════════════ -->
        <aside :class="[
            'fixed inset-y-0 left-0 z-50 flex flex-col transition-transform duration-300',
            mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
        ]" style="width:240px; background:var(--bg-sidebar); border-right:1px solid var(--border-default)">

            <!-- Logo -->
            <div class="flex items-center gap-3 px-5 h-16 flex-shrink-0"
                style="border-bottom:1px solid var(--border-default)">
                <div class="w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0"
                    style="background:linear-gradient(135deg,#2DD9A4,#1A9E8F)">
                    <svg class="w-4 h-4" fill="white" viewBox="0 0 24 24">
                        <path d="M19 8H15V4H9v4H5v6h4v4h6v-4h4V8z"/>
                    </svg>
                </div>
                <div>
                    <p class="font-bold text-sm leading-tight"
                        style="color:var(--text-primary); font-family:'Plus Jakarta Sans',sans-serif">ICU Monitor</p>
                    <p class="text-xs leading-tight"
                        style="color:var(--text-accent); font-family:'DM Mono',monospace">v2.0</p>
                </div>
            </div>

            <!-- Nav Menu -->
            <nav class="flex-1 overflow-y-auto sidebar-scroll py-4 px-3">
                <p class="px-3 mb-2 text-xs font-semibold tracking-widest uppercase"
                    style="color:var(--text-muted); font-family:'Plus Jakarta Sans',sans-serif">Menu</p>
                <div class="space-y-0.5">
                    <a v-for="item in navItems" :key="item.href"
                        :href="item.href"
                        :class="['nav-item', isActive(item.href) ? 'active' : '']">
                        <svg class="flex-shrink-0" style="width:18px;height:18px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon"/>
                        </svg>
                        <span style="font-family:'Plus Jakarta Sans',sans-serif">{{ item.label }}</span>
                    </a>
                </div>

                <hr class="divider my-4">

                <p class="px-3 mb-2 text-xs font-semibold tracking-widest uppercase"
                    style="color:var(--text-muted); font-family:'Plus Jakarta Sans',sans-serif">Lainnya</p>
                <div class="space-y-0.5">
                    <a v-for="item in moreItems" :key="item.label"
                        :href="item.href"
                        class="nav-item">
                        <svg class="flex-shrink-0" style="width:18px;height:18px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon"/>
                        </svg>
                        <span style="font-family:'Plus Jakarta Sans',sans-serif">{{ item.label }}</span>
                    </a>
                </div>
            </nav>

<<<<<<< HEAD
            <!-- Footer user -->
            <div class="px-2.5 py-3 border-t border-green-700/50 flex-shrink-0">
                <div :class="['flex items-center gap-2.5 px-1.5 py-1.5', !sidebarOpen && 'justify-center']">
                    <div class="w-7 h-7 rounded-full bg-green-600 flex items-center justify-center flex-shrink-0 text-white text-xs font-bold">RS</div>
                    <div v-show="sidebarOpen" class="overflow-hidden min-w-0">
                        <p class="text-white text-xs font-semibold truncate">RSUS</p>
                        <p class="text-green-400 text-xs truncate">Admin ICU</p>
=======
            <!-- Promo Card -->
            <div class="px-3 pb-4 flex-shrink-0">
                <div class="promo-card">
                    <div class="flex items-start gap-2 mb-3">
                        <div class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0"
                            style="background:rgba(45,217,164,0.15)">
                            <svg style="width:14px;height:14px;color:#2DD9A4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs font-bold leading-tight"
                                style="color:var(--text-primary); font-family:'Plus Jakarta Sans',sans-serif">ICU Live Mode</p>
                            <p class="text-xs mt-0.5"
                                style="color:var(--text-secondary); font-family:'DM Sans',sans-serif; line-height:1.4">
                                Auto-refresh aktif setiap 30 detik
                            </p>
                        </div>
>>>>>>> 43a8115 (update UI dan revisi mapping nama bed)
                    </div>
                    <button @click="manualRefresh" class="btn-primary w-full justify-center text-xs">
                        <svg style="width:12px;height:12px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Refresh Sekarang
                    </button>
                </div>
            </div>
        </aside>

        <!-- ══════════════ MAIN AREA ══════════════════════════════ -->
        <div class="flex flex-col flex-1 min-w-0 lg:ml-60">

            <!-- Topbar -->
            <header class="flex-shrink-0 z-30"
                style="background:var(--bg-topbar); border-bottom:1px solid var(--border-default); box-shadow:var(--shadow-topbar)">
                <div class="flex items-center justify-between h-16 px-4 sm:px-6">

                    <!-- Left: hamburger + page title -->
                    <div class="flex items-center gap-3">
                        <button @click="mobileOpen = !mobileOpen"
                            class="lg:hidden p-1.5 rounded-lg"
                            style="color:var(--text-secondary); background:var(--bg-input)">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </button>
                        <div>
                            <p class="font-bold text-sm leading-tight"
                                style="color:var(--text-primary); font-family:'Plus Jakarta Sans',sans-serif">
                                {{ pageTitle }}
                            </p>
                            <p class="text-xs leading-tight"
                                style="color:var(--text-secondary); font-family:'DM Mono',monospace">
                                {{ formattedDate }}
                            </p>
                        </div>
                    </div>

                    <!-- Right: theme toggle + LIVE badge + clock + refresh + avatar -->
                    <div class="flex items-center gap-2 sm:gap-3">

                        <!-- ── Theme Toggle ── -->
                        <button
                            @click="toggle"
                            class="theme-toggle-wrap"
                            :title="isDark ? 'Ganti ke Bright Mode' : 'Ganti ke Dark Mode'"
                            :aria-label="isDark ? 'Switch to light mode' : 'Switch to dark mode'"
                            :aria-pressed="!isDark">

                            <!-- Sun icon (shown in dark → clicking goes light) -->
                            <svg v-if="isDark" style="width:15px;height:15px;color:var(--text-secondary);flex-shrink:0"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="iconSun"/>
                            </svg>
                            <!-- Moon icon (shown in light → clicking goes dark) -->
                            <svg v-else style="width:15px;height:15px;color:var(--text-accent);flex-shrink:0"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="iconMoon"/>
                            </svg>

                            <!-- Toggle pill -->
                            <div :class="['theme-toggle-track', !isDark ? 'on' : '']">
                                <div class="theme-toggle-thumb"></div>
                            </div>

                            <!-- Label -->
                            <span class="theme-toggle-label hidden sm:block">
                                {{ isDark ? 'Dark' : 'Light' }}
                            </span>
                        </button>
                        
                        <!-- Avatar -->
                        <div class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 font-bold text-sm"
                            style="background:linear-gradient(135deg,#2DD9A4,#1A9E8F); color:#0D1A17; font-family:'Plus Jakarta Sans',sans-serif">
                            RS
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page content -->
            <main class="flex-1 overflow-y-auto" style="background:var(--bg-main)">
                <slot />
            </main>
        </div>
    </div>
</template>
