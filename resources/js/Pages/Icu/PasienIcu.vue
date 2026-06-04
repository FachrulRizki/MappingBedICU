<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout    from '@/Layouts/AppLayout.vue';
import AlertModal   from '@/Components/AlertModal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';

const props = defineProps({
    pasienIcu: { type: Array,  default: () => [] },
    riwayat:   { type: Array,  default: () => [] },
    flash:     { type: Object, default: () => ({}) },
});

// ── Modal state ───────────────────────────────────────────────────────
const alert   = ref({ show: false, type: 'success', title: '', message: '' });
const confirm = ref({ show: false, id: null, nama: '' });

const showAlert = (type, title, msg) => { alert.value = { show: true, type, title, message: msg }; };
watch(() => props.flash, (f) => {
    if (f?.success) showAlert('success', 'Berhasil!', f.success);
    if (f?.error)   showAlert('error',   'Gagal!',    f.error);
}, { immediate: true, deep: true });

const activeTab = ref('aktif');

// ── Pulangkan pasien ──────────────────────────────────────────────────
const openConfirm = (p) => { confirm.value = { show: true, id: p.id, nama: p.nama_pasien }; };
const doPulangkan = () => {
    router.post(route('icu.pulangkan', confirm.value.id));
    confirm.value.show = false;
};
</script>

<template>
    <AppLayout :flash="flash" page-title="Pasien di ICU">
        <AlertModal   v-bind="alert"   @close="alert.show = false" />
        <ConfirmModal
            :show="confirm.show"
            title="Pulangkan Pasien?"
            :message="`Pasien ${confirm.nama} akan dipulangkan dari ICU dan bed akan dikosongkan kembali. Lanjutkan?`"
            confirm-text="Ya, Pulangkan"
            :danger="true"
            @confirm="doPulangkan"
            @cancel="confirm.show = false"
        />

        <div class="p-3 sm:p-5 space-y-4 sm:space-y-5">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h1 class="text-lg font-bold text-gray-800">Pasien di ICU</h1>
                    <p class="text-sm text-gray-400 mt-0.5">Monitoring pasien aktif dan riwayat pulang</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="flex items-center gap-1.5 text-xs font-medium text-green-700 bg-green-50 px-3 py-1.5 rounded-full border border-green-200">
                        <span class="w-1.5 h-1.5 bg-green-500 rounded-full pulse-green"></span>
                        {{ pasienIcu.length }} Pasien Aktif
                    </span>
                </div>
            </div>

            <!-- Tab -->
            <div class="flex items-center gap-1 bg-gray-100 rounded-xl p-1 w-full sm:w-fit overflow-x-auto">
                <button @click="activeTab = 'aktif'"
                    :class="['px-4 py-1.5 rounded-lg text-sm font-semibold transition-all flex items-center gap-1.5', activeTab === 'aktif' ? 'bg-white shadow-sm text-green-700' : 'text-gray-500']">
                    Pasien Aktif
                    <span class="bg-green-100 text-green-700 text-xs px-1.5 rounded-full">{{ pasienIcu.length }}</span>
                </button>
                <button @click="activeTab = 'riwayat'"
                    :class="['px-4 py-1.5 rounded-lg text-sm font-semibold transition-all flex items-center gap-1.5', activeTab === 'riwayat' ? 'bg-white shadow-sm text-green-700' : 'text-gray-500']">
                    Riwayat Pulang
                    <span class="bg-gray-200 text-gray-600 text-xs px-1.5 rounded-full">{{ riwayat.length }}</span>
                </button>
            </div>

            <!-- Tab: Aktif -->
            <div v-if="activeTab === 'aktif'">
                <div v-if="pasienIcu.length === 0"
                    class="bg-white rounded-2xl border border-gray-100 shadow-sm text-center py-16 text-gray-300">
                    <svg class="w-12 h-12 mx-auto mb-3 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>
                    <p class="text-sm">Belum ada pasien di ICU</p>
                </div>
                <!-- Grid: 1 kolom di mobile, 2 di md, 3 di xl -->
                <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3 sm:gap-4">
                    <div v-for="p in pasienIcu" :key="p.id"
                        class="bg-white rounded-2xl border border-green-100 shadow-sm p-4 hover:shadow-md transition-shadow">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="w-2.5 h-2.5 bg-green-500 rounded-full pulse-green flex-shrink-0 mt-0.5"></span>
                                <p class="font-bold text-gray-800 truncate">{{ p.nama_pasien }}</p>
                            </div>
                            <span class="text-xs bg-green-100 text-green-700 font-semibold px-2 py-0.5 rounded-full flex-shrink-0 ml-2">Di ICU</span>
                        </div>
                        <div class="space-y-1.5 mb-4 text-xs text-gray-500">
                            <div class="flex items-center gap-2">
                                <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>
                                </svg>
                                Bed: <strong class="text-gray-700">{{ p.nama_bed }}</strong>
                            </div>
                            <div class="flex items-center gap-2">
                                <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                </svg>
                                Kelas: <strong class="text-gray-700">{{ p.nama_kelas }}</strong>
                            </div>
                            <div class="flex items-center gap-2">
                                <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Reg: {{ p.No_Reg }}
                            </div>
                            <div class="flex items-center gap-2">
                                <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Masuk: {{ p.created_at }}
                            </div>
                        </div>
                        <button @click="openConfirm(p)"
                            class="w-full flex items-center justify-center gap-2 border border-rose-200 text-rose-600 hover:bg-rose-600 hover:text-white text-sm font-semibold px-4 py-2 rounded-xl transition-all">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                            Pulangkan Pasien
                        </button>
                    </div>
                </div>
            </div>

            <!-- Tab: Riwayat -->
            <div v-if="activeTab === 'riwayat'" class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100">
                    <span class="text-sm font-bold text-gray-700">Riwayat Pasien Pulang (20 terakhir)</span>
                </div>
                <div v-if="riwayat.length === 0" class="text-center py-10 text-gray-300">
                    <p class="text-sm">Belum ada riwayat pasien pulang</p>
                </div>
                <!-- Mobile: card list -->
                <div v-else class="block sm:hidden divide-y divide-gray-50">
                    <div v-for="p in riwayat" :key="p.id" class="px-4 py-3 hover:bg-gray-50">
                        <p class="font-semibold text-gray-700 text-sm">{{ p.nama_pasien }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">MR: {{ p.No_MR }} · Reg: {{ p.No_Reg }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">Pulang: {{ p.updated_at }}</p>
                    </div>
                </div>
                <!-- Desktop: table -->
                <div class="hidden sm:block overflow-x-auto">
                    <table class="w-full text-sm min-w-[500px]">
                        <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                            <tr>
                                <th class="px-5 py-3 text-left font-semibold">Nama Pasien</th>
                                <th class="px-5 py-3 text-left font-semibold">No. MR</th>
                                <th class="px-5 py-3 text-left font-semibold">No. Registrasi</th>
                                <th class="px-5 py-3 text-left font-semibold">Waktu Pulang</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="p in riwayat" :key="p.id" class="hover:bg-gray-50 transition-colors">
                                <td class="px-5 py-3.5 font-semibold text-gray-700">{{ p.nama_pasien }}</td>
                                <td class="px-5 py-3.5 text-gray-400 font-mono text-xs">{{ p.No_MR }}</td>
                                <td class="px-5 py-3.5 text-gray-400 font-mono text-xs">{{ p.No_Reg }}</td>
                                <td class="px-5 py-3.5 text-gray-400">{{ p.updated_at }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
