<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import FlashMessage from '@/Components/FlashMessage.vue';
import { useTheme } from '@/composables/useTheme.js';

defineProps({
    flash:     { type: Object, default: () => ({}) },
    pageTitle: { type: String, default: 'Dashboard' },
});

const page     = usePage();
const authUser = computed(() => page.props.auth?.user ?? null);
const userRole = computed(() => authUser.value?.role ?? '');

const canSee = (roles) => {
    if (!authUser.value) return false;
    if (userRole.value === 'admin') return true;
    return roles.includes(userRole.value);
};

const doLogout = () => {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = route('logout');
    const csrf = document.createElement('input');
    csrf.type = 'hidden'; csrf.name = '_token';
    csrf.value = usePage().props.csrf_token ?? document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    form.appendChild(csrf); document.body.appendChild(form); form.submit();
};

const { theme, toggle, init: initTheme } = useTheme();
const isDark = computed(() => theme.value === 'dark');

const mobileOpen = ref(false);
const onResize   = () => { if (window.innerWidth >= 1024) mobileOpen.value = false; };

const now = ref(new Date());
let clockTimer = null;
const formattedTime = computed(() =>
    now.value.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
);
const formattedDate = computed(() =>
    now.value.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
);

const countdown = ref(30);
let refreshTimer = null;

onMounted(() => {
    window.addEventListener('resize', onResize);
    initTheme();
    clockTimer = setInterval(() => now.value = new Date(), 1000);
    refreshTimer = setInterval(() => {
        countdown.value--;
        if (countdown.value <= 0) { router.reload(); countdown.value = 30; }
    }, 1000);
});
onUnmounted(() => {
    window.removeEventListener('resize', onResize);
    clearInterval(clockTimer);
    clearInterval(refreshTimer);
});

const navItems = [
    { label:'Dashboard',     href:'/dashboard-icu',    roles:['admin','admisi','petugas_icu','petugas_ruang'], icon:'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
    { label:'Admisi',        href:'/icu/menu-admision', roles:['admin','admisi'], icon:'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01' },
    { label:'ICU',           href:'/icu/menu-icu',      roles:['admin','petugas_icu'], icon:'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z' },
    { label:'Rawat Inap',    href:'/icu/menu-petugas',  roles:['admin','petugas_ruang'], icon:'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
    { label:'Informasi Bed', href:'/icu/denah-bed',     roles:['admin','admisi','petugas_icu','petugas_ruang'], icon:'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4' },
];

const moreItems = [
    { label:'Kelola User',      href:'/settings/users',         roles:['admin'], icon:'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z' },
    { label:'Role & Permission',href:'/settings/roles',         roles:['admin'], icon:'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z' },
    { label:'Log Aktivitas',    href:'/settings/activity-logs', roles:['admin'], icon:'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4' },
];

const visibleNavItems  = computed(() => navItems.filter(i => canSee(i.roles)));
const visibleMoreItems = computed(() => moreItems.filter(i => canSee(i.roles)));

const currentPath = typeof window !== 'undefined' ? window.location.pathname : '';
const isActive    = (href) => currentPath === href || currentPath.startsWith(href + '/');

const iconSun  = 'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M16 12a4 4 0 11-8 0 4 4 0 018 0z';
const iconMoon = 'M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z';

const roleLabels = {
    admin: 'Administrator', admisi: 'Petugas Admisi',
    petugas_icu: 'Petugas ICU', petugas_ruang: 'Petugas Ruang',
};
const roleLabel  = computed(() => roleLabels[userRole.value] ?? userRole.value ?? '—');
const roleColors = {
    admin: { bg:'rgba(124,58,237,0.12)', color:'#7C3AED' },
    admisi: { bg:'rgba(0,168,132,0.12)', color:'#00A884' },
    petugas_icu: { bg:'rgba(220,38,38,0.10)', color:'#DC2626' },
    petugas_ruang: { bg:'rgba(0,168,132,0.12)', color:'#00A884' },
};
const roleColor = computed(() => roleColors[userRole.value] ?? { bg:'rgba(100,116,139,0.12)', color:'#64748B' });
</script>

<template>
<div class="flex h-screen overflow-hidden" style="background:var(--bg-main); font-family:'Inter','Plus Jakarta Sans',sans-serif">
    <FlashMessage :flash="flash" />

    <!-- Mobile overlay -->
    <Transition name="sidebar">
        <div v-if="mobileOpen" class="fixed inset-0 z-40 lg:hidden sidebar-overlay"
            @click="mobileOpen=false"></div>
    </Transition>

    <!-- ══ SIDEBAR ══════════════════════════════════════════ -->
    <aside :class="['fixed inset-y-0 left-0 z-50 flex flex-col transition-transform duration-300 ease-in-out sidebar-panel',
        mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0']">

        <!-- Brand -->
        <div class="flex items-center gap-3 px-5 h-16 flex-shrink-0 sidebar-brand">
            <div class="sidebar-logo-wrap">
                <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M19 8H15V4H9v4H5v6h4v4h6v-4h4V8z"/>
                </svg>
            </div>
            <div>
                <p class="font-bold text-sm leading-tight" style="color:var(--text-primary)">ICU Monitor</p>
                <p class="font-mono" style="color:var(--text-accent); font-size:10px; font-weight:600; letter-spacing:0.05em">v3.0 Medical</p>
            </div>
            <!-- Close btn mobile -->
            <button @click="mobileOpen=false" class="ml-auto lg:hidden sidebar-close-btn">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Nav -->
        <nav class="flex-1 overflow-y-auto sidebar-scroll py-4 px-3 space-y-0.5">
            <p class="px-3 mb-2 text-xs font-bold tracking-widest uppercase" style="color:var(--text-muted); font-size:10px">Menu Utama</p>

            <a v-for="item in visibleNavItems" :key="item.href" :href="item.href"
                :class="['nav-item', isActive(item.href) ? 'active' : '']">
                <div :class="['nav-icon-wrap', isActive(item.href) ? 'active' : '']">
                    <svg class="flex-shrink-0 w-4 h-4" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon"/>
                    </svg>
                </div>
                <span>{{ item.label }}</span>
                <span v-if="isActive(item.href)" class="nav-active-dot"></span>
            </a>

            <template v-if="visibleMoreItems.length">
                <hr class="divider my-3">
                <p class="px-3 mb-2 text-xs font-bold tracking-widest uppercase" style="color:var(--text-muted); font-size:10px">Pengaturan</p>
                <a v-for="item in visibleMoreItems" :key="item.href" :href="item.href"
                    :class="['nav-item', isActive(item.href) ? 'active' : '']">
                    <div :class="['nav-icon-wrap', isActive(item.href) ? 'active' : '']">
                        <svg class="flex-shrink-0 w-4 h-4" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon"/>
                        </svg>
                    </div>
                    <span>{{ item.label }}</span>
                </a>
            </template>
        </nav>

        <!-- User panel -->
        <div class="px-3 pb-4 pt-2 flex-shrink-0 sidebar-user-wrap">
            <div class="sidebar-user-card">
                <div class="flex items-center gap-2.5 mb-3">
                    <div class="sidebar-avatar">
                        {{ authUser?.name?.charAt(0)?.toUpperCase() ?? 'G' }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-bold truncate" style="color:var(--text-primary)">{{ authUser?.name ?? 'Guest' }}</p>
                        <span class="inline-block text-xs font-semibold px-2 py-0.5 rounded-full mt-0.5"
                            :style="`background:${roleColor.bg}; color:${roleColor.color}; font-size:9px`">
                            {{ roleLabel }}
                        </span>
                    </div>
                </div>
                <button @click="doLogout" class="sidebar-logout-btn">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7"/>
                    </svg>
                    Keluar
                </button>
            </div>
        </div>
    </aside>

    <!-- ══ MAIN AREA ════════════════════════════════════════ -->
    <div class="flex flex-col flex-1 min-w-0 lg:ml-60">

        <!-- Topbar -->
        <header class="flex-shrink-0 sticky top-0 z-30 glass-panel topbar-panel">
            <div class="flex items-center justify-between h-14 px-4 sm:px-5">

                <!-- Left: hamburger + title -->
                <div class="flex items-center gap-3">
                    <button @click="mobileOpen=!mobileOpen" class="lg:hidden topbar-menu-btn">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                    <div>
                        <p class="font-bold" style="color:var(--text-primary); font-size:14px; line-height:1.2">{{ pageTitle }}</p>
                        <p class="text-xs hidden sm:block" style="color:var(--text-muted); font-size:11px">{{ formattedDate }}</p>
                    </div>
                </div>

                <!-- Right: clock, refresh, theme, avatar -->
                <div class="flex items-center gap-2">
                    <!-- Theme toggle -->
                    <button @click="toggle" class="theme-toggle-wrap" :title="isDark ? 'Mode Terang' : 'Mode Gelap'">
                        <svg v-if="isDark" style="width:13px;height:13px;color:var(--text-muted);flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="iconSun"/>
                        </svg>
                        <svg v-else style="width:13px;height:13px;color:var(--text-accent);flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="iconMoon"/>
                        </svg>
                        <div :class="['theme-toggle-track', !isDark ? 'on' : '']">
                            <div class="theme-toggle-thumb"></div>
                        </div>
                    </button>

                    <!-- Avatar -->
                    <div class="flex items-center gap-2 pl-1">
                        <div class="hidden sm:block text-right">
                            <p class="text-xs font-bold leading-tight" style="color:var(--text-primary); max-width:100px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap">{{ authUser?.name ?? 'Guest' }}</p>
                            <p class="leading-tight font-semibold" style="font-size:10px; color:var(--text-muted)">{{ roleLabel }}</p>
                        </div>
                        <div class="topbar-avatar">
                            {{ authUser?.name?.charAt(0)?.toUpperCase() ?? 'G' }}
                        </div>
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

<style scoped>
/* ── Overlay ── */
.sidebar-overlay {
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(4px);
}

/* ── Sidebar panel ── */
.sidebar-panel {
    width: 240px;
    background: var(--bg-sidebar);
    border-right: 1px solid var(--border-default);
    box-shadow: var(--shadow-sidebar);
}

/* Brand section */
.sidebar-brand {
    border-bottom: 1px solid var(--border-default);
    background: var(--bg-sidebar);
}
.sidebar-logo-wrap {
    width: 34px; height: 34px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    background: linear-gradient(135deg, #00A884, #007a61);
    box-shadow: 0 4px 12px rgba(0,168,132,0.35);
}
.sidebar-close-btn {
    padding: 6px;
    border-radius: 8px;
    color: var(--text-muted);
    background: var(--bg-input);
    border: 1px solid var(--border-default);
    cursor: pointer;
    transition: all 0.2s;
}
.sidebar-close-btn:hover { background: var(--bg-card-hover); color: var(--text-primary); }

/* Nav icon wrap */
.nav-icon-wrap {
    width: 30px; height: 30px;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    background: transparent;
    transition: background 0.2s;
    color: var(--text-secondary);
}
.nav-item:hover .nav-icon-wrap {
    background: rgba(0,168,132,0.08);
    color: var(--text-accent);
}
.nav-icon-wrap.active {
    background: rgba(255,255,255,0.2);
    color: white;
}

/* Active dot */
.nav-active-dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    background: rgba(255,255,255,0.7);
    margin-left: auto;
    flex-shrink: 0;
}

/* Nav override for icon gap */
.nav-item {
    gap: 10px;
}

/* User card */
.sidebar-user-wrap {
    border-top: 1px solid var(--border-default);
}
.sidebar-user-card {
    border-radius: 12px;
    padding: 12px;
    background: var(--bg-input);
    border: 1px solid var(--border-default);
}
.sidebar-avatar {
    width: 34px; height: 34px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    font-weight: 700;
    font-size: 12px;
    color: #fff;
    background: linear-gradient(135deg, #00A884, #007a61);
    box-shadow: 0 2px 8px rgba(0,168,132,0.3);
}
.sidebar-logout-btn {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 7px 12px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid rgba(185,28,28,0.15);
    background: rgba(185,28,28,0.07);
    color: #B91C1C;
    transition: all 0.2s;
}
.sidebar-logout-btn:hover {
    background: rgba(185,28,28,0.14);
    border-color: rgba(185,28,28,0.3);
}

/* Topbar */
.topbar-panel {
    background: var(--bg-topbar);
    border-bottom: 1px solid var(--border-default);
    box-shadow: var(--shadow-topbar);
}
.topbar-chip {
    background: var(--bg-input);
    border: 1px solid var(--border-default);
}
.topbar-menu-btn {
    padding: 8px;
    border-radius: 10px;
    color: var(--text-secondary);
    background: var(--bg-input);
    border: 1.5px solid var(--border-default);
    cursor: pointer;
    transition: all 0.2s;
}
.topbar-menu-btn:hover { background: var(--bg-card-hover); }
.topbar-avatar {
    width: 34px; height: 34px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    font-weight: 700;
    font-size: 12px;
    color: #fff;
    background: linear-gradient(135deg, #00A884, #007a61);
    box-shadow: 0 2px 8px rgba(0,168,132,0.25);
    cursor: pointer;
}

/* Sidebar transition */
.sidebar-enter-active, .sidebar-leave-active { transition: opacity 0.3s ease; }
.sidebar-enter-from, .sidebar-leave-to { opacity: 0; }
</style>
