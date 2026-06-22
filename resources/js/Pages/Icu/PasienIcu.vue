<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout    from '@/Layouts/AppLayout.vue';
import AlertModal   from '@/Components/AlertModal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { useAuth }  from '@/composables/useAuth.js';

const { canPulangkan } = useAuth();

const props = defineProps({
    pasienIcu: { type: Array, default: () => [] },
    riwayat:   { type: Array, default: () => [] },
    flash:     { type: Object, default: () => ({}) },
});

const alert   = ref({ show: false, type: 'success', title: '', message: '' });
const confirm = ref({ show: false, action: null, title: '', message: '', danger: false });
const showAlert   = (t, h, m) => { alert.value = { show: true, type: t, title: h, message: m }; };
const openConfirm = (cfg) => { confirm.value = { show: true, ...cfg }; };
const doConfirm   = () => { confirm.value.action?.(); confirm.value.show = false; };

watch(() => props.flash, (f) => {
    if (f?.success) showAlert('success', 'Berhasil!', f.success);
    if (f?.error)   showAlert('error',   'Gagal!',    f.error);
}, { immediate: true, deep: true });

const activeTab = ref('aktif');

// ── Pulang per sumber ──────────────────────────────────────
const doPulang = (p) => {
    let routeName, params;
    if (p.sumber === 'external') {
        routeName = 'icu.pulangkan_external';
        params    = p.raw_id;
    } else if (p.sumber === 'internal') {
        routeName = 'icu.pulangkan_internal';
        params    = p.raw_id;
    } else {
        routeName = 'icu.pulangkan';
        params    = p.raw_id;
    }
    openConfirm({
        title:   'Pulangkan Pasien?',
        message: `${p.nama_pasien} akan dipulangkan dan bed kembali kosong.`,
        danger:  true,
        action:  () => router.post(route(routeName, params)),
    });
};

// ── Gender theme ───────────────────────────────────────────
const gCardBorder = (g) => g === 'L' ? 'rgba(52,152,219,0.45)'  : g === 'P' ? 'rgba(142,68,173,0.45)'  : 'var(--border-default)';
const gStripe     = (g) => g === 'L' ? '#00A884'                : g === 'P' ? '#8E44AD'                : '#00A884';
const gBadgeBg    = (g) => g === 'L' ? 'rgba(0,168,132,0.15)'  : g === 'P' ? 'rgba(142,68,173,0.15)'  : 'rgba(0,168,132,0.12)';
const gBadgeColor = (g) => g === 'L' ? '#00A884'                : g === 'P' ? '#8E44AD'                : '#00A884';
const gLabel      = (g) => g === 'L' ? '♂ Pria' : g === 'P' ? '♀ Wanita' : '— ?';

// ── Sumber badge ───────────────────────────────────────────
const sumberBadge = (sumber) => ({
    lama:     { label: 'IGD',      bg: 'rgba(230,126,34,0.15)',  color: '#E67E22' },
    external: { label: 'External', bg: 'rgba(0,168,132,0.15)',  color: '#00A884' },
    internal: { label: 'Internal', bg: 'rgba(0,168,132,0.15)',  color: '#00A884' },
}[sumber] ?? { label: sumber, bg: 'var(--bg-input)', color: 'var(--text-secondary)' });

// ── Counts ─────────────────────────────────────────────────
const countByGender = computed(() => ({
    L: props.pasienIcu.filter(p => p.jenis_kelamin === 'L').length,
    P: props.pasienIcu.filter(p => p.jenis_kelamin === 'P').length,
}));
</script>

<template>
    <AppLayout :flash="flash" page-title="Pasien di ICU">
        <AlertModal   v-bind="alert"   @close="alert.show = false"/>
        <ConfirmModal v-bind="confirm" @confirm="doConfirm" @cancel="confirm.show = false"/>

        <div class="p-4 sm:p-6 space-y-4" style="font-family:'Inter','Plus Jakarta Sans',sans-serif">

            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h1 class="font-bold text-lg" style="color:var(--text-primary)">Pasien di ICU</h1>
                    <p class="text-sm mt-0.5" style="color:var(--text-secondary)">Monitoring pasien aktif dari semua jalur masuk</p>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full"
                        style="background:rgba(0,168,132,0.15); color:#00A884; border:1px solid rgba(0,168,132,0.3)">
                        ♂ {{ countByGender.L }} Pria
                    </span>
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full"
                        style="background:rgba(142,68,173,0.15); color:#8E44AD; border:1px solid rgba(142,68,173,0.3)">
                        ♀ {{ countByGender.P }} Wanita
                    </span>
                    <span class="flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full"
                        style="background:rgba(0,168,132,0.12); color:#00A884; border:1px solid rgba(0,168,132,0.2)">
                        <span class="w-1.5 h-1.5 rounded-full pulse-teal" style="background:#2DC5B2"></span>
                        {{ pasienIcu.length }} Aktif
                    </span>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex gap-1 p-1 rounded-xl w-full sm:w-fit" style="background:var(--bg-main); border:1px solid var(--border-default)">
                <button @click="activeTab='aktif'"
                    class="flex-1 sm:flex-none px-4 py-1.5 rounded-lg text-sm font-semibold transition-all flex items-center gap-1.5"
                    :style="activeTab==='aktif'
                        ? 'background:var(--bg-surface); color:#008C6E; box-shadow:0 1px 4px rgba(26,158,143,.12)'
                        : 'color:var(--text-secondary)'">
                    Pasien Aktif
                    <span class="text-xs px-1.5 rounded-full" style="background:rgba(0,168,132,0.15); color:#00A884">{{ pasienIcu.length }}</span>
                </button>
                <button @click="activeTab='riwayat'"
                    class="flex-1 sm:flex-none px-4 py-1.5 rounded-lg text-sm font-semibold transition-all flex items-center gap-1.5"
                    :style="activeTab==='riwayat'
                        ? 'background:var(--bg-surface); color:#008C6E; box-shadow:0 1px 4px rgba(26,158,143,.12)'
                        : 'color:var(--text-secondary)'">
                    Riwayat
                    <span class="text-xs px-1.5 rounded-full" style="background:var(--bg-input); color:var(--text-secondary)">{{ riwayat.length }}</span>
                </button>
            </div>

            <!-- ── Pasien Aktif ── -->
            <div v-if="activeTab==='aktif'">
                <div v-if="pasienIcu.length===0" class="card-dark text-center py-16" style="color:var(--text-secondary)">
                    <svg class="w-12 h-12 mx-auto mb-3 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>
                    <p class="text-sm">Belum ada pasien di ICU</p>
                </div>

                <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 sm:gap-4">
                    <div v-for="p in pasienIcu" :key="p.id"
                        class="card-teal-hover flex flex-col overflow-hidden"
                        :style="`
                            background: var(--bg-card);
                            border-radius: 14px;
                            border: 1px solid ${gCardBorder(p.jenis_kelamin)};
                            box-shadow: var(--shadow-card);
                        `">

                        <!-- Gender color stripe -->
                        <div class="h-1.5 w-full flex-shrink-0" :style="`background:${gStripe(p.jenis_kelamin)}`"></div>

                        <div class="p-4 flex flex-col flex-1">
                            <!-- Name row -->
                            <div class="flex items-start justify-between mb-3 gap-2">
                                <div class="flex items-center gap-2 min-w-0">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 text-sm font-bold"
                                        :style="`background:${gBadgeBg(p.jenis_kelamin)}; color:${gBadgeColor(p.jenis_kelamin)}`">
                                        {{ p.jenis_kelamin === 'L' ? '♂' : p.jenis_kelamin === 'P' ? '♀' : '?' }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold truncate leading-tight" style="color:var(--text-primary)">
                                            {{ p.nama_pasien }}
                                        </p>
                                        <span class="text-xs font-semibold px-1.5 py-0.5 rounded-full mt-0.5 inline-block"
                                            :style="`background:${gBadgeBg(p.jenis_kelamin)}; color:${gBadgeColor(p.jenis_kelamin)}`">
                                            {{ gLabel(p.jenis_kelamin) }}
                                        </span>
                                    </div>
                                </div>
                                <!-- Sumber badge -->
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full flex-shrink-0 mt-0.5"
                                    :style="`background:${sumberBadge(p.sumber).bg}; color:${sumberBadge(p.sumber).color}`">
                                    {{ sumberBadge(p.sumber).label }}
                                </span>
                            </div>

                            <!-- Info -->
                            <div class="space-y-1.5 mb-4 flex-1 text-xs" style="color:var(--text-secondary)">
                                <div class="flex items-center gap-2">
                                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                                        :style="`color:${gStripe(p.jenis_kelamin)}`">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>
                                    </svg>
                                    <span>Bed: <strong style="color:var(--text-primary)">{{ p.nama_bed ?? '—' }}</strong></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                                        style="color:var(--text-secondary)">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                    </svg>
                                    <span>{{ p.nama_kelas ?? p.required_bed_type ?? '—' }}</span>
                                </div>
                                <p v-if="p.No_MR" class="font-mono" style="font-size:10px; color:var(--text-secondary); opacity:0.7">
                                    MR: {{ p.No_MR }} · {{ p.created_at }}
                                </p>
                                <p v-else class="font-mono" style="font-size:10px; color:var(--text-secondary); opacity:0.7">
                                    {{ p.created_at }}
                                </p>
                            </div>

                            <!-- Pulangkan -->
                            <button v-if="canPulangkan" @click="doPulang(p)"
                                class="w-full flex items-center justify-center gap-2 text-xs font-semibold px-4 py-2 rounded-xl transition-all"
                                style="border:1px solid rgba(231,76,60,0.35); color:#E74C3C; background:rgba(231,76,60,0.06)"
                                @mouseenter="$el.style.background='rgba(231,76,60,0.14)'"
                                @mouseleave="$el.style.background='rgba(231,76,60,0.06)'">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                </svg>
                                Pulangkan
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Riwayat ── -->
            <div v-if="activeTab==='riwayat'" class="card-dark overflow-hidden">
                <div class="px-5 py-3.5" style="border-bottom:1px solid var(--border-default)">
                    <p class="text-sm font-semibold" style="color:var(--text-primary)">Riwayat Pasien Pulang (20 terakhir)</p>
                </div>
                <div v-if="riwayat.length===0" class="text-center py-10" style="color:var(--text-secondary)">
                    <p class="text-sm">Belum ada riwayat</p>
                </div>
                <!-- Mobile -->
                <div class="block sm:hidden">
                    <div v-for="p in riwayat" :key="p.id" class="px-4 py-3" style="border-bottom:1px solid var(--border-default)">
                        <div class="flex items-center gap-2">
                            <span class="text-xs px-1.5 py-0.5 rounded-full font-semibold"
                                :style="`background:${sumberBadge(p.sumber).bg}; color:${sumberBadge(p.sumber).color}`">
                                {{ sumberBadge(p.sumber).label }}
                            </span>
                            <p class="text-sm font-semibold" style="color:var(--text-primary)">{{ p.nama_pasien }}</p>
                        </div>
                        <p class="text-xs font-mono mt-0.5" style="color:var(--text-secondary)">{{ p.No_MR }} · {{ p.No_Reg }}</p>
                        <p class="text-xs mt-0.5" style="color:var(--text-secondary); opacity:0.7">Pulang: {{ p.updated_at }}</p>
                    </div>
                </div>
                <!-- Desktop -->
                <div class="hidden sm:block overflow-x-auto">
                    <table class="w-full text-sm dark-table" style="min-width:520px">
                        <thead>
                            <tr>
                                <th class="text-left px-5">Nama</th>
                                <th class="text-left px-5">Sumber</th>
                                <th class="text-left px-5">No. MR</th>
                                <th class="text-left px-5">Waktu Pulang</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="p in riwayat" :key="p.id">
                                <td class="px-5 font-semibold">{{ p.nama_pasien }}</td>
                                <td class="px-5">
                                    <span class="text-xs px-2 py-0.5 rounded-full font-semibold"
                                        :style="`background:${sumberBadge(p.sumber).bg}; color:${sumberBadge(p.sumber).color}`">
                                        {{ sumberBadge(p.sumber).label }}
                                    </span>
                                </td>
                                <td class="px-5 text-xs font-mono" style="color:var(--text-secondary)">{{ p.No_MR ?? '—' }}</td>
                                <td class="px-5 text-xs" style="color:var(--text-secondary)">{{ p.updated_at }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
