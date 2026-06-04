<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout    from '@/Layouts/AppLayout.vue';
import AlertModal   from '@/Components/AlertModal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';

const props = defineProps({
    list:  { type: Array,  default: () => [] },
    flash: { type: Object, default: () => ({}) },
});

// ── Modal state ───────────────────────────────────────────────────────
const alert   = ref({ show: false, type: 'success', title: '', message: '' });
const confirm = ref({ show: false, id: null, nama: '' });

const showAlert = (type, title, msg) => { alert.value = { show: true, type, title, message: msg }; };

watch(() => props.flash, (f) => {
    if (f?.success) showAlert('success', 'Berhasil!', f.success);
    if (f?.error)   showAlert('error',   'Gagal!',    f.error);
}, { immediate: true, deep: true });

// ── Action: kirim ke IGD ──────────────────────────────────────────────
const openConfirm = (p) => { confirm.value = { show: true, id: p.id, nama: p.nama_pasien }; };
const doKirimIgd  = () => {
    router.post(route('icu.kirim_igd', confirm.value.id));
    confirm.value.show = false;
};
</script>

<template>
    <AppLayout :flash="flash" page-title="IGD & Triase">
        <AlertModal   v-bind="alert"   @close="alert.show = false" />
        <ConfirmModal
            :show="confirm.show"
            title="Kirim ke IGD?"
            :message="`Pasien ${confirm.nama} akan dikirim ke IGD untuk diperiksa. Lanjutkan?`"
            confirm-text="Ya, Kirim ke IGD"
            @confirm="doKirimIgd"
            @cancel="confirm.show = false"
        />

        <div class="p-3 sm:p-5 space-y-4 sm:space-y-5">
            <!-- Header -->
            <div>
                <h1 class="text-lg font-bold text-gray-800">IGD — Pasien Menunggu Triase</h1>
                <p class="text-sm text-gray-400 mt-0.5">Pasien yang sudah terdaftar dan siap dikirim ke IGD</p>
            </div>

            <!-- Info -->
            <div class="flex items-start gap-3 bg-amber-50 border border-amber-100 rounded-xl p-4 text-sm text-amber-800">
                <svg class="w-4 h-4 mt-0.5 flex-shrink-0 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Halaman ini menampilkan pasien dengan status <strong>Terdaftar</strong>. Klik tombol <strong>Kirim ke IGD</strong> untuk memulai proses triase.</span>
            </div>

            <!-- Tabel -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                    <span class="text-sm font-bold text-gray-700">Antrian Menuju IGD</span>
                    <span class="text-xs bg-amber-100 text-amber-700 font-semibold px-2.5 py-0.5 rounded-full">{{ list.length }} pasien</span>
                </div>
                <div v-if="list.length === 0" class="text-center py-12 text-gray-300">
                    <svg class="w-10 h-10 mx-auto mb-2 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                    <p class="text-sm">Tidak ada pasien menunggu IGD</p>
                </div>
                <!-- Tabel dengan scroll horizontal -->
                <template v-if="list.length > 0">
                <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-[600px]">
                    <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3 text-left font-semibold">Nama Pasien</th>
                            <th class="px-5 py-3 text-left font-semibold">No. MR</th>
                            <th class="px-5 py-3 text-left font-semibold">No. Registrasi</th>
                            <th class="px-5 py-3 text-left font-semibold">Waktu Daftar</th>
                            <th class="px-5 py-3 text-center font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="p in list" :key="p.id" class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3.5 font-semibold text-gray-800">{{ p.nama_pasien }}</td>
                            <td class="px-5 py-3.5 text-gray-500 font-mono text-xs">{{ p.No_MR }}</td>
                            <td class="px-5 py-3.5 text-gray-500 font-mono text-xs">{{ p.No_Reg }}</td>
                            <td class="px-5 py-3.5 text-gray-400">{{ p.created_at }}</td>
                            <td class="px-5 py-3.5 text-center">
                                <button @click="openConfirm(p)"
                                    class="inline-flex items-center gap-1.5 text-xs font-semibold bg-amber-500 hover:bg-amber-600 text-white px-3 py-1.5 rounded-lg transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                    Kirim ke IGD
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
