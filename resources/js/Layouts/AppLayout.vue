<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, usePage, Link } from '@inertiajs/vue3';
import FlashMessage from '@/Components/FlashMessage.vue';
import { useTheme } from '@/composables/useTheme.js';
import { useNotifikasi, useNotifAlert } from '@/composables/useNotifikasi.js';

defineProps({
    flash:     { type: Object, default: () => ({}) },
    pageTitle: { type: String, default: 'Dashboard' },
});

const page     = usePage();
const authUser = computed(() => page.props.auth?.user ?? null);
const userRole = computed(() => authUser.value?.role ?? '');

// ── Loading modal — hanya untuk navigasi halaman penuh (bukan filter/action) ──
const isNavigating = ref(false);
let navTimer = null;
router.on('start', (event) => {
    // Hanya tampilkan loading untuk navigasi halaman penuh
    // preserveState = true artinya filter/action di halaman yang sama → tidak perlu loading
    const isPageNav = !event.detail?.visit?.preserveState;
    if (isPageNav) {
        navTimer = setTimeout(() => { isNavigating.value = true; }, 80);
    }
});
router.on('finish', () => {
    clearTimeout(navTimer);
    isNavigating.value = false;
});

// Gunakan role_label & role_color dari server (di-sync dari Keycloak via HandleInertiaRequests)
// Tidak perlu hardcode di sini — role baru apapun dari Keycloak otomatis punya label & warna
const roleLabel = computed(() => authUser.value?.role_label ?? userRole.value ?? '—');
const roleColor = computed(() => {
    const color = authUser.value?.role_color ?? '#64748B';
    return { bg: color + '20', color };
});

// Role baru apapun dari Keycloak akan otomatis mendapat akses selama tim Keycloak assign permission yang sesuai ke role tersebut.
const userPermissions = computed(() => authUser.value?.permissions ?? []);

// permission bisa string tunggal atau array (OR logic) — konsisten dengan route middleware
const canSee = (_roles, permission = null) => {
    if (!authUser.value) return false;
    if (!permission) return false;
    const perms = Array.isArray(permission) ? permission : [permission];
    return perms.some(p => userPermissions.value.includes(p));
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

// ── Notifikasi suara (polling) ─────────────────────────────────────────────
const { notifList, dismissNotif, _debug } = useNotifikasi();
const { alertModal } = useNotifAlert();

// Debug helper — bisa akses dari browser console: window.__notif.test()
if (typeof window !== 'undefined') window.__notif = _debug;

// ── Dismiss alert modal ──
const dismissAlert = () => { alertModal.value = null; };

// ── Tipe notif → label & warna ──
const alertConfig = (type) => {
    if (type === 'noning_internal') return { label: '🚨 Booking Internal Baru!', color: '#00A884', bg: 'linear-gradient(135deg,#00875a,#00A884)', glow: 'rgba(0,168,132,0.6)' };
    if (type === 'noning_external') return { label: '🚨 Booking Eksternal Baru!', color: '#0EA5E9', bg: 'linear-gradient(135deg,#0369a1,#0EA5E9)', glow: 'rgba(14,165,233,0.6)' };
    return { label: '🔔 Notifikasi ICU', color: '#7C3AED', bg: 'linear-gradient(135deg,#5b21b6,#7C3AED)', glow: 'rgba(124,58,237,0.6)' };
};

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

onMounted(() => {
    window.addEventListener('resize', onResize);
    initTheme();
    clockTimer = setInterval(() => now.value = new Date(), 1000);
});
onUnmounted(() => {
    window.removeEventListener('resize', onResize);
    clearInterval(clockTimer);
});

const navItems = [
    { label:'Dashboard',     href:'/dashboard-icu',    permission:'dashboard:view',        roles:['admin','admisi','petugas_icu','petugas_ruang'], icon:'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
    { label:'Admisi',        href:'/icu/menu-admision', permission:'booking_ext:view',      roles:['admin','admisi'], icon:'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01' },
    { label:'ICU',           href:'/icu/menu-icu',      permission:['booking_ext:view','booking_int:view'], roles:['admin','petugas_icu'], icon:'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z' },
    { label:'Rawat Inap',    href:'/icu/menu-petugas',  permission:'booking_int:create',    roles:['admin','petugas_ruang'], icon:'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
    { label:'Informasi Bed', href:'/icu/denah-bed',     permission:'denah_bed:view',        roles:['admin','admisi','petugas_icu','petugas_ruang'], icon:'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4' },
    { label:'Monitor Booking ICU', href:'/monitor',     permission:'denah_bed:view',        roles:['admin','admisi','petugas_icu','petugas_ruang'], icon: 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z' },
    { label:'Yanmed', href:'/icu/menu-yanmed',          permission:'yanmed:view',            roles:['yanmed','admin'], icon:'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
];

const moreItems = [
    // Kelola User & Role dikelola penuh oleh Keycloak SSO — tidak ditampilkan di sini.
    { label:'Log Aktivitas', href:'/settings/activity-logs', permission:'activity_log:view', roles:['admin'], icon:'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4' },
];

const visibleNavItems  = computed(() => navItems.filter(i => canSee(i.roles, i.permission)));
const visibleMoreItems = computed(() => moreItems.filter(i => canSee(i.roles, i.permission)));

const currentPath = typeof window !== 'undefined' ? window.location.pathname : '';
const isActive    = (href) => currentPath === href || currentPath.startsWith(href + '/');

const iconSun  = 'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M16 12a4 4 0 11-8 0 4 4 0 018 0z';
const iconMoon = 'M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z';
</script>

<template>
<div class="flex h-screen overflow-hidden" style="background:var(--bg-main); font-family:'Inter','Plus Jakarta Sans',sans-serif">
    <FlashMessage :flash="flash" />

    <!-- ── DANGER ALERT MODAL — muncul di tengah layar saat ada notif ─── -->
    <Teleport to="body">
        <Transition name="danger-modal">
            <div v-if="alertModal" class="danger-overlay" @click.self="dismissAlert" role="alertdialog" aria-modal="true">
                <div class="danger-card" :style="`box-shadow: 0 0 60px ${alertConfig(alertModal.type).glow}, 0 25px 50px rgba(0,0,0,0.5)`">
                    <!-- Pulse ring animasi -->
                    <div class="danger-pulse-ring"></div>
                    <div class="danger-pulse-ring danger-pulse-ring--2"></div>

                    <!-- Icon bahaya berkedip -->
                    <div class="danger-icon-wrap" :style="`background:${alertConfig(alertModal.type).bg}`">
                        <svg class="danger-icon" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                            <path v-if="alertModal.type === 'ningnong'" stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                            <path v-else-if="alertModal.type === 'noning_internal'" stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            <path v-else stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                        </svg>
                    </div>

                    <!-- Judul -->
                    <h2 class="danger-title">{{ alertConfig(alertModal.type).label }}</h2>

                    <!-- Pesan -->
                    <p class="danger-message">{{ alertModal.message }}</p>

                    <!-- Semua notif jika lebih dari 1 -->
                    <div v-if="alertModal.allNotifs && alertModal.allNotifs.length > 1" class="danger-extra">
                        <p v-for="n in alertModal.allNotifs.slice(1)" :key="n.type" class="danger-extra-item">• {{ n.message }}</p>
                    </div>

                    <!-- Tombol dismiss -->
                    <button @click="dismissAlert" class="danger-dismiss-btn">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        Sudah Mengerti
                    </button>

                    <!-- Progress bar auto-dismiss -->
                    <div class="danger-progress"></div>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- ── Navigation Loading Modal ───────────────────────────────────── -->
    <Transition name="nav-overlay">
        <div v-if="isNavigating" class="nav-loading-modal" aria-hidden="true">
            <div class="nav-loading-card">
                <svg class="nav-loading-icon" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <span class="nav-loading-text">Memuat halaman...</span>
            </div>
        </div>
    </Transition>

    <!-- ── Notifikasi Suara Toast ──────────────────────────────────────────── -->
    <Teleport to="body">
        <div class="notif-stack" aria-live="polite">
            <Transition
                v-for="n in notifList" :key="n.id"
                name="notif-slide"
                appear
            >
                <div class="notif-toast" :class="`notif-toast--${n.sound}`">
                    <!-- Icon -->
                    <div class="notif-icon-wrap">
                        <!-- ningnong: ikon bed -->
                        <svg v-if="n.sound === 'ningnong'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                        <!-- noning_internal: ikon clipboard -->
                        <svg v-else-if="n.sound === 'noning_internal'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        <!-- noning_external: ikon user-add -->
                        <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                        </svg>
                    </div>

                    <!-- Konten -->
                    <div class="notif-body">
                        <p class="notif-title">
                            <span v-if="n.sound === 'ningnong'">🔔 Bed Tersedia!</span>
                            <span v-else-if="n.sound === 'noning_internal'">🔔 Booking Internal Baru!</span>
                            <span v-else>🔔 Booking Eksternal Baru!</span>
                        </p>
                        <p class="notif-msg">{{ n.message }}</p>
                    </div>

                    <!-- Tutup -->
                    <button @click="dismissNotif(n.id)" class="notif-close" aria-label="Tutup notifikasi">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>

                    <!-- Progress bar auto-dismiss -->
                    <div class="notif-progress" :class="`notif-progress--${n.sound}`"></div>
                </div>
            </Transition>
        </div>
    </Teleport>

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

            <Link v-for="item in visibleNavItems" :key="item.href" :href="item.href"
                :class="['nav-item', isActive(item.href) ? 'active' : '']">
                <div :class="['nav-icon-wrap', isActive(item.href) ? 'active' : '']">
                    <svg class="flex-shrink-0 w-4 h-4" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon"/>
                    </svg>
                </div>
                <span>{{ item.label }}</span>
                <span v-if="isActive(item.href)" class="nav-active-dot"></span>
            </Link>

            <template v-if="visibleMoreItems.length">
                <hr class="divider my-3">
                <p class="px-3 mb-2 text-xs font-bold tracking-widest uppercase" style="color:var(--text-muted); font-size:10px">Pengaturan</p>
                <Link v-for="item in visibleMoreItems" :key="item.href" :href="item.href"
                    :class="['nav-item', isActive(item.href) ? 'active' : '']">
                    <div :class="['nav-icon-wrap', isActive(item.href) ? 'active' : '']">
                        <svg class="flex-shrink-0 w-4 h-4" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon"/>
                        </svg>
                    </div>
                    <span>{{ item.label }}</span>
                </Link>
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

/* ── Navigation Loading Modal ── */
.nav-loading-modal {
    position: fixed;
    inset: 0;
    z-index: 99999;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.35);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
}
.nav-loading-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 18px 28px;
    border-radius: 16px;
    background: var(--bg-surface);
    border: 1.5px solid var(--border-default);
    box-shadow: 0 8px 32px rgba(0,0,0,0.2);
}
.nav-loading-icon {
    width: 22px;
    height: 22px;
    color: #00A884;
    animation: spin 0.8s linear infinite;
    flex-shrink: 0;
}
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
.nav-loading-text {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-primary);
}
.nav-overlay-enter-active { transition: opacity 0.2s ease; }
.nav-overlay-leave-active { transition: opacity 0.15s ease; }
.nav-overlay-enter-from, .nav-overlay-leave-to { opacity: 0; }

/* ── Notifikasi Stack ── */
.notif-stack {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 9999;
    display: flex;
    flex-direction: column-reverse;
    gap: 10px;
    max-width: 360px;
    width: calc(100vw - 48px);
    pointer-events: none;
}
.notif-toast {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 14px 18px 14px;
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.18), 0 2px 8px rgba(0,0,0,0.1);
    overflow: hidden;
    pointer-events: all;
    cursor: default;
    border: 1.5px solid transparent;
    backdrop-filter: blur(8px);
    animation: notif-pulse 0.4s ease;
}
@keyframes notif-pulse {
    0%   { transform: scale(0.95); }
    50%  { transform: scale(1.02); }
    100% { transform: scale(1); }
}
/* ICU — booking internal: hijau */
.notif-toast--noning_internal {
    background: rgba(0, 168, 132, 0.92);
    border-color: rgba(0, 200, 160, 0.5);
    color: #fff;
}
/* ICU — booking external: biru */
.notif-toast--noning_external {
    background: rgba(14, 120, 200, 0.92);
    border-color: rgba(14, 165, 233, 0.5);
    color: #fff;
}
/* Admisi/Petugas — bed tersedia: ungu/teal */
.notif-toast--ningnong {
    background: rgba(124, 58, 237, 0.92);
    border-color: rgba(167, 139, 250, 0.5);
    color: #fff;
}
.notif-icon-wrap {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.notif-body {
    flex: 1;
    min-width: 0;
}
.notif-title {
    font-size: 12px;
    font-weight: 700;
    margin-bottom: 2px;
    line-height: 1.3;
}
.notif-msg {
    font-size: 12px;
    opacity: 0.9;
    line-height: 1.4;
}
.notif-close {
    flex-shrink: 0;
    width: 24px;
    height: 24px;
    border-radius: 6px;
    background: rgba(255,255,255,0.2);
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    transition: background 0.15s;
    margin-top: -2px;
}
.notif-close:hover { background: rgba(255,255,255,0.35); }
/* Progress bar berjalan habis dalam 8 detik */
.notif-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    height: 3px;
    border-radius: 0 0 16px 16px;
    background: rgba(255,255,255,0.5);
    width: 100%;
    transform-origin: left;
    animation: notif-progress-bar 8s linear forwards;
}
@keyframes notif-progress-bar {
    from { transform: scaleX(1); }
    to   { transform: scaleX(0); }
}
/* Slide in/out transition */
.notif-slide-enter-active {
    transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.notif-slide-leave-active {
    transition: all 0.25s ease-in;
}
.notif-slide-enter-from {
    opacity: 0;
    transform: translateX(60px) scale(0.9);
}
.notif-slide-leave-to {
    opacity: 0;
    transform: translateX(60px) scale(0.9);
}

/* ══════════════════════════════════════════════
   DANGER ALERT MODAL
══════════════════════════════════════════════ */
.danger-overlay {
    position: fixed;
    inset: 0;
    z-index: 99990;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    background: rgba(0, 0, 0, 0.75);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
}

.danger-card {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 40px 32px 32px;
    border-radius: 24px;
    background: #0f1117;
    border: 1.5px solid rgba(255, 255, 255, 0.12);
    max-width: 420px;
    width: 100%;
    overflow: hidden;
    animation: danger-shake 0.5s ease;
}

@keyframes danger-shake {
    0%, 100% { transform: translateX(0); }
    15%       { transform: translateX(-6px); }
    30%       { transform: translateX(6px); }
    45%       { transform: translateX(-4px); }
    60%       { transform: translateX(4px); }
    75%       { transform: translateX(-2px); }
    90%       { transform: translateX(2px); }
}

/* Cincin pulse di belakang kartu */
.danger-pulse-ring {
    position: absolute;
    inset: -20px;
    border-radius: 36px;
    border: 2px solid rgba(231, 76, 60, 0.4);
    animation: danger-ring-pulse 1.5s ease-in-out infinite;
    pointer-events: none;
}
.danger-pulse-ring--2 {
    inset: -40px;
    border-color: rgba(231, 76, 60, 0.2);
    animation-delay: 0.4s;
    animation-duration: 1.8s;
}
@keyframes danger-ring-pulse {
    0%   { opacity: 1; transform: scale(1); }
    100% { opacity: 0; transform: scale(1.06); }
}

/* Icon berkedip */
.danger-icon-wrap {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
    flex-shrink: 0;
    animation: danger-icon-blink 0.8s ease-in-out infinite alternate;
    box-shadow: 0 0 30px rgba(231, 76, 60, 0.5);
}
.danger-icon {
    width: 36px;
    height: 36px;
}
@keyframes danger-icon-blink {
    from { transform: scale(1);    filter: brightness(1); }
    to   { transform: scale(1.08); filter: brightness(1.2); }
}

.danger-title {
    font-size: 20px;
    font-weight: 900;
    color: #fff;
    margin-bottom: 10px;
    letter-spacing: -0.02em;
    line-height: 1.2;
}

.danger-message {
    font-size: 14px;
    color: rgba(255, 255, 255, 0.85);
    line-height: 1.6;
    margin-bottom: 12px;
    max-width: 340px;
}

.danger-extra {
    width: 100%;
    background: rgba(255,255,255,0.06);
    border-radius: 10px;
    padding: 10px 14px;
    margin-bottom: 16px;
}
.danger-extra-item {
    font-size: 12px;
    color: rgba(255,255,255,0.7);
    line-height: 1.7;
    text-align: left;
}

.danger-dismiss-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 28px;
    border-radius: 12px;
    background: rgba(255,255,255,0.12);
    border: 1.5px solid rgba(255,255,255,0.2);
    color: #fff;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    margin-top: 4px;
    position: relative;
    z-index: 1;
}
.danger-dismiss-btn:hover {
    background: rgba(255,255,255,0.22);
    transform: translateY(-1px);
}

/* Progress bar 8 detik auto dismiss */
.danger-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    height: 4px;
    width: 100%;
    background: rgba(255,255,255,0.15);
    transform-origin: left;
    animation: danger-progress-bar 8s linear forwards;
    border-radius: 0 0 24px 24px;
}
@keyframes danger-progress-bar {
    from { transform: scaleX(1); background: rgba(231,76,60,0.7); }
    to   { transform: scaleX(0); background: rgba(255,255,255,0.1); }
}

/* Transition masuk/keluar modal */
.danger-modal-enter-active {
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.danger-modal-leave-active {
    transition: all 0.2s ease-in;
}
.danger-modal-enter-from {
    opacity: 0;
    transform: scale(0.85);
}
.danger-modal-leave-to {
    opacity: 0;
    transform: scale(0.95);
}
</style>
