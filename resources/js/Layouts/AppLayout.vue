<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import FlashMessage from '@/Components/FlashMessage.vue';
import { useTheme } from '@/composables/useTheme.js';

defineProps({
    flash:     { type: Object, default: () => ({}) },
    pageTitle: { type: String, default: 'Dashboard' },
});

// ── Auth user dari Inertia shared data ─────────────────────
const page    = usePage();
const authUser = computed(() => page.props.auth?.user ?? null);
const userRole = computed(() => authUser.value?.role ?? '');

// Menu visibility per role
const canSee = (roles) => {
    if (!authUser.value) return false;
    if (userRole.value === 'admin') return true;
    return roles.includes(userRole.value);
};

const doLogout = () => router.post(route('logout'));

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
// ── Nav items dengan role guard ────────────────────────────
const navItems = [
    {
        label: 'Dashboard',
        href:  '/dashboard-icu',
        roles: ['admin','admisi','petugas_icu','petugas_ruang'],
        icon:  'M4 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm10 0a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zm10 0a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z',
    },
    {
        label: 'Booking ICU',
        href:  '/icu/booking-external',
        roles: ['admin','admisi','petugas_icu'],
        icon:  'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
    },
    {
        label: 'Permintaan Pindah ICU',
        href:  '/icu/spri-internal',
        roles: ['admin','admisi','petugas_icu','petugas_ruang'],
        icon:  'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    },
    {
        label: 'Denah Bed ICU',
        href:  '/icu/denah-bed',
        roles: ['admin','admisi','petugas_icu','petugas_ruang'],
        icon:  'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
    },
];

const visibleNavItems = computed(() => navItems.filter(item => canSee(item.roles)));

const moreItems = [
    {
        label: 'Kelola User',
        href:  '/settings/users',
        roles: ['admin'],
        icon:  'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
    },
    {
        label: 'Role & Permission',
        href:  '/settings/roles',
        roles: ['admin'],
        icon:  'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
    },
    // {
    //     label: 'Pengaturan',
    //     href:  '#',
    //     roles: ['admin'],
    //     icon:  'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
    // },
    // {
    //     label: 'Bantuan',
    //     href:  '#',
    //     roles: ['admin','admisi','petugas_icu','petugas_ruang'],
    //     icon:  'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    // },
];

const visibleMoreItems = computed(() => moreItems.filter(item => canSee(item.roles)));

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
                    <a v-for="item in visibleNavItems" :key="item.href"
                        :href="item.href"
                        :class="['nav-item', isActive(item.href) ? 'active' : '']">
                        <svg class="flex-shrink-0" style="width:18px;height:18px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon"/>
                        </svg>
                        <span style="font-family:'Plus Jakarta Sans',sans-serif">{{ item.label }}</span>
                    </a>
                </div>

                <hr v-if="visibleMoreItems.length" class="divider my-4">

                <p v-if="visibleMoreItems.length"
                    class="px-3 mb-2 text-xs font-semibold tracking-widest uppercase"
                    style="color:var(--text-muted); font-family:'Plus Jakarta Sans',sans-serif">Lainnya</p>
                <div class="space-y-0.5">
                    <a v-for="item in visibleMoreItems" :key="item.label"
                        :href="item.href"
                        :class="['nav-item', isActive(item.href) ? 'active' : '']">
                        <svg class="flex-shrink-0" style="width:18px;height:18px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon"/>
                        </svg>
                        <span style="font-family:'Plus Jakarta Sans',sans-serif">{{ item.label }}</span>
                    </a>
                </div>
            </nav>

            <div class="px-3 pb-4 flex-shrink-0">
                <div class="promo-card space-y-3">

                    <!-- User Info -->
                    <div class="flex items-center gap-3">
                        
                        <!-- Avatar -->
                        <div
                            class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 font-bold text-sm"
                            :style="`
                                background:${authUser?.role_color ? authUser.role_color + '30' : 'rgba(45,217,164,0.2)'};
                                color:${authUser?.role_color ?? '#2DD9A4'};
                                border:2px solid ${authUser?.role_color ?? '#2DD9A4'}40;
                                font-family:'Plus Jakarta Sans',sans-serif
                            `">
                            {{ authUser?.name?.charAt(0)?.toUpperCase() ?? 'G' }}
                        </div>

                        <!-- Name & Role -->
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-bold truncate"
                                style="color:var(--text-primary); font-family:'Plus Jakarta Sans',sans-serif">
                                {{ authUser?.name ?? 'Guest' }}
                            </p>
                            <p class="text-[10px] font-semibold truncate"
                                :style="`color:${authUser?.role_color ?? '#8EA89E'}; font-family:'DM Mono',monospace`">
                                {{ authUser?.role_label ?? '-' }}
                            </p>
                        </div>
                    </div>

                    <!-- Logout Button -->
                    <button
                        @click="doLogout"
                        class="w-full flex items-center justify-center gap-2 text-xs font-semibold py-2 rounded-lg transition-all"
                        style="background:#FF6363; color:white">

                        <svg style="width:12px;height:12px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M17 16l4-4m0 0l-4-4m4 4H7"/>
                        </svg>

                        Logout
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
                        
                        <!-- Avatar + user info + logout -->
                        <div class="flex items-center gap-2">
                            <!-- User info -->
                            <div class="hidden sm:block text-right">
                                <p class="text-xs font-bold leading-tight" style="color:var(--text-primary); font-family:'Plus Jakarta Sans',sans-serif">
                                    {{ authUser?.name ?? 'Guest' }}
                                </p>
                                <p class="text-xs leading-tight font-semibold"
                                    :style="`color:${authUser?.role_color ?? '#8EA89E'}; font-family:'DM Mono',monospace; font-size:10px`">
                                    {{ authUser?.role_label ?? '' }}
                                </p>
                            </div>
                            <!-- Avatar -->
                            <div class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 font-bold text-sm cursor-pointer"
                                :style="`background:${authUser?.role_color ? authUser.role_color + '30' : 'rgba(45,217,164,0.2)'}; color:${authUser?.role_color ?? '#2DD9A4'}; border:2px solid ${authUser?.role_color ?? '#2DD9A4'}40; font-family:'Plus Jakarta Sans',sans-serif`">
                                {{ authUser?.name?.charAt(0)?.toUpperCase() ?? 'G' }}
                            </div>
                            <!-- Logout -->
                            <button @click="doLogout"
                                title="Logout"
                                class="p-2 rounded-lg transition-all"
                                style="color:var(--text-secondary); background:rgba(224,112,80,0.06); border:1px solid rgba(224,112,80,0.15)"
                                @mouseenter="$el.style.background='rgba(224,112,80,0.15)'; $el.style.color='#E07050'"
                                @mouseleave="$el.style.background='rgba(224,112,80,0.06)'; $el.style.color='var(--text-secondary)'">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                            </button>
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
