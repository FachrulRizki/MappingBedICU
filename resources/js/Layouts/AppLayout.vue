<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import FlashMessage from '@/Components/FlashMessage.vue';
import { useTheme } from '@/composables/useTheme.js';

defineProps({
    flash:     { type: Object, default: () => ({}) },
    pageTitle: { type: String, default: 'Dashboard' },
});

// ── Auth ───────────────────────────────────────────────────
const page     = usePage();
const authUser = computed(() => page.props.auth?.user ?? null);
const userRole = computed(() => authUser.value?.role ?? '');

const canSee = (roles) => {
    if (!authUser.value) return false;
    if (userRole.value === 'admin') return true;
    return roles.includes(userRole.value);
};

const doLogout = () => {
    // WAJIB pakai form submit biasa, bukan router.post() / fetch
    // router.post() = XHR → browser blok redirect cross-origin ke Keycloak (CORS error)
    // Form submit = full page navigation → CORS tidak berlaku
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = route('logout');

    const csrf = document.createElement('input');
    csrf.type  = 'hidden';
    csrf.name  = '_token';
    csrf.value = usePage().props.csrf_token
        ?? document.querySelector('meta[name="csrf-token"]')?.content
        ?? '';

    form.appendChild(csrf);
    document.body.appendChild(form);
    form.submit();
};

// ── Theme ──────────────────────────────────────────────────
const { theme, toggle, init: initTheme } = useTheme();
const isDark = computed(() => theme.value === 'dark');

// ── Sidebar mobile ─────────────────────────────────────────
const mobileOpen = ref(false);
const onResize   = () => { if (window.innerWidth >= 1024) mobileOpen.value = false; };

// ── Clock real-time ────────────────────────────────────────
const now = ref(new Date());
let clockTimer = null;
const formattedTime = computed(() =>
    now.value.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
);
const formattedDate = computed(() =>
    now.value.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
);

onMounted(() => {
    window.addEventListener('resize', onResize);
    initTheme();
    clockTimer = setInterval(() => now.value = new Date(), 1000);
});
onUnmounted(() => {
    window.removeEventListener('resize', onResize);
    clearInterval(clockTimer);
});

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

// ── Nav items ──────────────────────────────────────────────
const navItems = [
    {
        label: 'Dashboard',
        href:  '/dashboard-icu',
        roles: ['admin','admisi','petugas_icu','petugas_ruang'],
        icon:  'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
    },
    {
        label: 'Admision',
        href:  '/icu/menu-admision',
        roles: ['admin','admisi'],
        icon:  'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
    },
    {
        label: 'ICU',
        href:  '/icu/menu-icu',
        roles: ['admin','petugas_icu'],
        icon:  'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z',
    },
    {
        label: 'Rawat Inap',
        href:  '/icu/menu-petugas',
        roles: ['admin','petugas_ruang'],
        icon:  'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
    },
    // {
    //     label: 'Informasi Bed',
    //     href:  '/icu/denah-bed',
    //     roles: ['admin','admisi','petugas_icu','petugas_ruang'],
    //     icon:  'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
    // },
];

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
];

const visibleNavItems  = computed(() => navItems.filter(item => canSee(item.roles)));
const visibleMoreItems = computed(() => moreItems.filter(item => canSee(item.roles)));

const currentPath = typeof window !== 'undefined' ? window.location.pathname : '';
const isActive    = (href) => currentPath === href || currentPath.startsWith(href + '/');

// ── Icon paths ─────────────────────────────────────────────
const iconSun  = 'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M16 12a4 4 0 11-8 0 4 4 0 018 0z';
const iconMoon = 'M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z';

// ── Role color ─────────────────────────────────────────────
const roleColor = computed(() => authUser.value?.role_color ?? 'var(--text-accent)');
</script>

<template>
<div class="flex h-screen overflow-hidden" style="background:var(--bg-main); font-family:'Inter','Plus Jakarta Sans',sans-serif">
    <FlashMessage :flash="flash" />

    <!-- Mobile overlay -->
    <Transition name="sidebar">
        <div v-if="mobileOpen"
            class="fixed inset-0 z-40 lg:hidden"
            style="background:rgba(10,18,26,0.5); backdrop-filter:blur(6px)"
            @click="mobileOpen = false">
        </div>
    </Transition>

    <!-- ══════════════ SIDEBAR — Glassmorphism ═══════════════ -->
    <aside :class="[
        'fixed inset-y-0 left-0 z-50 flex flex-col transition-transform duration-300',
        mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
    ]" style="width:240px; background:var(--bg-sidebar); border-right:1px solid var(--border-default);
              box-shadow:var(--shadow-sidebar); backdrop-filter:blur(18px) saturate(180%);
              -webkit-backdrop-filter:blur(18px) saturate(180%)">

        <!-- Logo -->
        <div class="flex items-center gap-3 px-5 h-16 flex-shrink-0"
            style="border-bottom:1px solid var(--border-default)">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0"
                style="background:linear-gradient(135deg,#00A884,#008C6E); box-shadow:0 4px 12px rgba(0,168,132,0.35)">
                <svg class="w-5 h-5" fill="white" viewBox="0 0 24 24">
                    <path d="M19 8H15V4H9v4H5v6h4v4h6v-4h4V8z"/>
                </svg>
            </div>
            <div>
                <p class="font-bold text-sm leading-tight" style="color:var(--text-primary)">ICU Monitor</p>
                <p class="text-xs leading-tight font-mono" style="color:var(--text-accent); font-size:10px">v2.0 — Medical</p>
            </div>
        </div>

        <!-- Nav -->
        <nav class="flex-1 overflow-y-auto sidebar-scroll py-4 px-3">
            <p class="px-3 mb-2 text-xs font-semibold tracking-widest uppercase"
                style="color:var(--text-muted)">Menu Utama</p>
            <div class="space-y-0.5">
                <a v-for="item in visibleNavItems" :key="item.href"
                    :href="item.href"
                    :class="['nav-item', isActive(item.href) ? 'active' : '']">
                    <svg class="flex-shrink-0" style="width:17px;height:17px" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.85">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon"/>
                    </svg>
                    <span>{{ item.label }}</span>
                </a>
            </div>

            <template v-if="visibleMoreItems.length">
                <hr class="divider my-4">
                <p class="px-3 mb-2 text-xs font-semibold tracking-widest uppercase"
                    style="color:var(--text-muted)">Pengaturan</p>
                <div class="space-y-0.5">
                    <a v-for="item in visibleMoreItems" :key="item.label"
                        :href="item.href"
                        :class="['nav-item', isActive(item.href) ? 'active' : '']">
                        <svg class="flex-shrink-0" style="width:17px;height:17px" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.85">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon"/>
                        </svg>
                        <span>{{ item.label }}</span>
                    </a>
                </div>
            </template>
        </nav>

        <!-- User panel + logout -->
        <div class="px-3 pb-4 flex-shrink-0">
            <div class="promo-card space-y-3">
                <!-- Avatar + nama -->
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 font-bold text-sm"
                        :style="`background:rgba(255,255,255,0.2); color:#fff; border:2px solid rgba(255,255,255,0.35)`">
                        {{ authUser?.name?.charAt(0)?.toUpperCase() ?? 'G' }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-bold truncate text-white">{{ authUser?.name ?? 'Guest' }}</p>
                        <p class="text-xs truncate font-mono" style="color:rgba(255,255,255,0.65); font-size:10px">
                            {{ authUser?.role_label ?? '-' }}
                        </p>
                    </div>
                </div>
                <!-- Logout -->
                <button @click="doLogout"
                    class="w-full flex items-center justify-center gap-2 text-xs font-semibold py-2 rounded-lg transition-all"
                    style="background:rgba(255,255,255,0.15); color:white; border:1px solid rgba(255,255,255,0.2)"
                    @mouseenter="e => e.currentTarget.style.background='rgba(255,255,255,0.25)'"
                    @mouseleave="e => e.currentTarget.style.background='rgba(255,255,255,0.15)'">
                    <svg style="width:12px;height:12px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7"/>
                    </svg>
                    Keluar
                </button>
            </div>
        </div>
    </aside>

    <!-- ══════════════ MAIN AREA ═══════════════════════════ -->
    <div class="flex flex-col flex-1 min-w-0 lg:ml-60">

        <!-- Topbar — sticky glass -->
        <header class="flex-shrink-0 sticky top-0 z-30 glass-panel"
            style="background:var(--bg-topbar); border-bottom:1px solid var(--border-default);
                   box-shadow:var(--shadow-topbar); backdrop-filter:blur(16px) saturate(180%);
                   -webkit-backdrop-filter:blur(16px) saturate(180%)">
            <div class="flex items-center justify-between h-16 px-4 sm:px-6">

                <!-- Left -->
                <div class="flex items-center gap-3">
                    <button @click="mobileOpen = !mobileOpen"
                        class="lg:hidden p-2 rounded-xl transition-all"
                        style="color:var(--text-secondary); background:var(--bg-input); border:1px solid var(--border-default)">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                    <div>
                        <p class="font-bold leading-tight" style="color:var(--text-primary); font-size:15px">
                            {{ pageTitle }}
                        </p>
                        <p class="text-xs leading-tight" style="color:var(--text-secondary)">
                            {{ formattedDate }}
                        </p>
                    </div>
                </div>

                <!-- Right -->
                <div class="flex items-center gap-2 sm:gap-3">
                    <!-- Theme toggle -->
                    <button @click="toggle" class="theme-toggle-wrap"
                        :title="isDark ? 'Mode Terang' : 'Mode Gelap'"
                        :aria-pressed="!isDark">
                        <svg v-if="isDark" style="width:14px;height:14px;color:var(--text-secondary);flex-shrink:0"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="iconSun"/>
                        </svg>
                        <svg v-else style="width:14px;height:14px;color:var(--text-accent);flex-shrink:0"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="iconMoon"/>
                        </svg>
                        <div :class="['theme-toggle-track', !isDark ? 'on' : '']">
                            <div class="theme-toggle-thumb"></div>
                        </div>
                        <span class="theme-toggle-label hidden sm:block">{{ isDark ? 'Dark' : 'Light' }}</span>
                    </button>

                    <!-- User avatar + info -->
                    <div class="flex items-center gap-2">
                        <div class="hidden sm:block text-right">
                            <p class="text-xs font-bold leading-tight" style="color:var(--text-primary)">
                                {{ authUser?.name ?? 'Guest' }}
                            </p>
                            <p class="leading-tight font-semibold" style="font-size:10px; color:var(--text-accent); font-family:'DM Mono',monospace">
                                {{ authUser?.role_label ?? '' }}
                            </p>
                        </div>
                        <div class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0 font-bold text-sm cursor-pointer"
                            style="background:var(--nav-active-bg); color:var(--nav-active-color);
                                   border:2px solid var(--border-card-hover)">
                            {{ authUser?.name?.charAt(0)?.toUpperCase() ?? 'G' }}
                        </div>
                        <!-- Logout btn topbar -->
                        <button @click="doLogout" title="Keluar"
                            class="p-2 rounded-xl transition-all"
                            style="color:var(--text-secondary); background:var(--pill-reject-bg);
                                   border:1px solid rgba(231,76,60,0.15)"
                            @mouseenter="e => { e.currentTarget.style.background='rgba(231,76,60,0.18)'; e.currentTarget.style.color='#E74C3C'; }"
                            @mouseleave="e => { e.currentTarget.style.background='var(--pill-reject-bg)'; e.currentTarget.style.color='var(--text-secondary)'; }">
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
