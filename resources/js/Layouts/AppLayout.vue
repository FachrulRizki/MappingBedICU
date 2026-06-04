<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import FlashMessage from '@/Components/FlashMessage.vue';

defineProps({
    flash:     { type: Object, default: () => ({}) },
    pageTitle: { type: String, default: 'Dashboard' },
});

// ── Sidebar — default tutup di mobile, buka di desktop ────────────────
const sidebarOpen = ref(false);   // desktop collapse/expand
const mobileOpen  = ref(false);   // mobile drawer

// Saat mounted, buka sidebar kalau desktop
onMounted(() => {
    sidebarOpen.value = window.innerWidth >= 1024;
    window.addEventListener('resize', onResize);
});

const toggleSidebar = () => { sidebarOpen.value = !sidebarOpen.value; };
const closeMobile   = () => { mobileOpen.value = false; };
const onResize      = () => {
    if (window.innerWidth >= 1024) mobileOpen.value = false;
};

onUnmounted(() => window.removeEventListener('resize', onResize));

// ── Clock ──────────────────────────────────────────────────────────────
const now = ref(new Date());
let clockTimer = null;
onMounted(()  => { clockTimer = setInterval(() => now.value = new Date(), 1000); });
onUnmounted(() => clearInterval(clockTimer));

const formattedTime = computed(() =>
    now.value.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
);
const formattedDate = computed(() =>
    now.value.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
);

// ── Nav items ──────────────────────────────────────────────────────────
const navItems = [
    {
        group: 'UTAMA',
        items: [
            { label: 'Dashboard',   icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', href: '/dashboard-icu' },
        ]
    },
    {
        group: 'ALUR PASIEN',
        items: [
            { label: 'Pendaftaran',  icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', href: '/icu/pendaftaran' },
            { label: 'IGD & Triase', icon: 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z', href: '/icu/igd' },
            { label: 'SPRI',         icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4', href: '/icu/spri' },
            { label: 'Alokasi Bed',  icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4', href: '/icu/alokasi-bed' },
        ]
    },
    {
        group: 'MONITORING',
        items: [
            { label: 'Pasien ICU', icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z', href: '/icu/pasien-icu' },
        ]
    },
];

const currentPath = typeof window !== 'undefined' ? window.location.pathname : '';

// ── Auto-refresh ────────────────────────────────────────────────────────
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
</script>

<template>
    <div class="flex h-screen bg-gray-50 overflow-hidden">
        <FlashMessage :flash="flash" />

        <!-- Mobile overlay -->
        <Transition name="sidebar">
            <div v-if="mobileOpen" class="fixed inset-0 z-40 lg:hidden" @click="closeMobile">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
            </div>
        </Transition>

        <!-- ════════════════════════════════ SIDEBAR ════════════════════════════ -->
        <aside :class="[
            'fixed inset-y-0 left-0 z-50 flex flex-col transition-all duration-300 ease-in-out',
            'bg-gradient-to-b from-green-900 via-green-800 to-green-900',
            sidebarOpen ? 'w-60' : 'w-16',
            mobileOpen  ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
        ]">
            <!-- Brand -->
            <div class="flex items-center gap-3 px-3.5 py-4 border-b border-green-700/50 flex-shrink-0 min-h-[56px]">
                <div class="w-8 h-8 rounded-xl bg-white/15 flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M19 8H15V4H9v4H5v6h4v4h6v-4h4V8z"/>
                    </svg>
                </div>
                <div v-show="sidebarOpen" class="overflow-hidden">
                    <p class="text-white font-bold text-sm leading-tight whitespace-nowrap">ICU Monitor</p>
                    <p class="text-green-300 text-xs whitespace-nowrap">UHC — Sistem Rujukan</p>
                </div>
            </div>

            <!-- Nav -->
            <nav class="flex-1 overflow-y-auto sidebar-scroll py-3 px-2 space-y-0.5">
                <template v-for="group in navItems" :key="group.group">
                    <div v-show="sidebarOpen" class="px-2 pt-4 pb-1">
                        <p class="text-green-400/60 text-[10px] font-bold tracking-widest uppercase">{{ group.group }}</p>
                    </div>
                    <div v-show="!sidebarOpen" class="border-t border-green-700/30 my-1.5"></div>
                    <a v-for="item in group.items" :key="item.label" :href="item.href"
                        :class="[
                            'flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all duration-150 group',
                            currentPath === item.href
                                ? 'bg-white/15 text-white'
                                : 'text-green-200 hover:bg-white/10 hover:text-white',
                        ]"
                        :title="!sidebarOpen ? item.label : undefined"
                    >
                        <svg :class="['w-5 h-5 flex-shrink-0', currentPath === item.href ? 'text-green-300' : 'text-green-400']"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon"/>
                        </svg>
                        <span v-show="sidebarOpen" class="text-sm font-medium whitespace-nowrap truncate">{{ item.label }}</span>
                        <span v-if="sidebarOpen && currentPath === item.href"
                            class="ml-auto w-1.5 h-1.5 rounded-full bg-green-400 flex-shrink-0"></span>
                    </a>
                </template>
            </nav>

            <!-- Footer user -->
            <div class="px-2.5 py-3 border-t border-green-700/50 flex-shrink-0">
                <div :class="['flex items-center gap-2.5 px-1.5 py-1.5', !sidebarOpen && 'justify-center']">
                    <div class="w-7 h-7 rounded-full bg-green-600 flex items-center justify-center flex-shrink-0 text-white text-xs font-bold">RS</div>
                    <div v-show="sidebarOpen" class="overflow-hidden min-w-0">
                        <p class="text-white text-xs font-semibold truncate">RSUD Kota</p>
                        <p class="text-green-400 text-xs truncate">Admin ICU</p>
                    </div>
                </div>
            </div>
        </aside>

        <!-- ════════════════════════════ MAIN AREA ═════════════════════════════ -->
        <div :class="[
            'flex flex-col flex-1 min-w-0 transition-all duration-300',
            sidebarOpen ? 'lg:ml-60' : 'lg:ml-16',
        ]">
            <!-- Topbar -->
            <header class="bg-white border-b border-gray-200 shadow-sm z-30 flex-shrink-0">
                <div class="flex items-center justify-between h-14 px-3 sm:px-4 gap-2">
                    <!-- Left -->
                    <div class="flex items-center gap-2 min-w-0">
                        <button @click="mobileOpen = !mobileOpen"
                            class="lg:hidden p-1.5 rounded-lg hover:bg-gray-100 text-gray-500 flex-shrink-0">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </button>
                        <button @click="toggleSidebar"
                            class="hidden lg:flex p-1.5 rounded-lg hover:bg-gray-100 text-gray-500 flex-shrink-0">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </button>
                        <div class="hidden sm:block h-5 w-px bg-gray-200 flex-shrink-0"></div>
                        <!-- Breadcrumb truncate di mobile -->
                        <div class="flex items-center gap-1 text-sm min-w-0">
                            <span class="hidden sm:inline text-gray-400 flex-shrink-0">ICU</span>
                            <svg class="hidden sm:block w-3 h-3 text-gray-300 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                            <span class="font-semibold text-gray-700 truncate">{{ pageTitle }}</span>
                        </div>
                    </div>

                    <!-- Right -->
                    <div class="flex items-center gap-1.5 sm:gap-2.5 flex-shrink-0">
                        <!-- LIVE badge — sembunyikan di layar sangat kecil -->
                        <span class="hidden sm:flex items-center gap-1 text-xs font-medium text-green-700 bg-green-50 px-2 py-1 rounded-full border border-green-200">
                            <span class="w-1.5 h-1.5 bg-green-500 rounded-full pulse-green"></span>
                            <span class="hidden md:inline">LIVE</span>
                        </span>
                        <!-- Clock — hanya lg ke atas -->
                        <div class="hidden lg:block text-right">
                            <p class="text-sm font-mono font-bold text-gray-700 leading-tight">{{ formattedTime }}</p>
                            <p class="text-xs text-gray-400">{{ formattedDate }}</p>
                        </div>
                        <!-- Refresh button -->
                        <button @click="manualRefresh"
                            class="flex items-center gap-1 text-xs bg-green-50 hover:bg-green-100 text-green-700 border border-green-200 px-2 sm:px-3 py-1.5 rounded-lg transition-colors font-medium">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            <span class="font-mono hidden sm:inline">{{ countdown }}s</span>
                        </button>
                    </div>
                </div>
            </header>

            <!-- Page content -->
            <main class="flex-1 overflow-y-auto">
                <slot />
            </main>
        </div>
    </div>
</template>
