<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout    from '@/Layouts/AppLayout.vue';
import AlertModal   from '@/Components/AlertModal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import BedGrid      from '@/Components/BedGrid.vue';

const props = defineProps({
    waiting:     { type: Array,  default: () => [] },
    booking:     { type: Array,  default: () => [] },
    semuaKamar:  { type: Array,  default: () => [] },
    kamarKosong: { type: Array,  default: () => [] },
    flash:       { type: Object, default: () => ({}) },
});

// ── Modal state ───────────────────────────────────────────────────────
const alert   = ref({ show: false, type: 'success', title: '', message: '' });
const confirm = ref({ show: false, action: null, title: '', message: '', danger: false });

const showAlert = (type, title, msg) => { alert.value = { show: true, type, title, message: msg }; };
watch(() => props.flash, (f) => {
    if (f?.success) showAlert('success', 'Berhasil!', f.success);
    if (f?.error)   showAlert('error',   'Gagal!',    f.error);
}, { immediate: true, deep: true });

const openConfirm = (cfg) => { confirm.value = { show: true, ...cfg }; };
const doConfirm   = () => { confirm.value.action?.(); confirm.value.show = false; };

// ── Tab ───────────────────────────────────────────────────────────────
const activeTab = ref('alokasi');

// ── Alokasi bed ───────────────────────────────────────────────────────
const bedPilihan    = ref({});
const bedCocokUntuk = (type) => props.kamarKosong.filter(b => b.kode_kelas === type);

const doAlokasi = (id, nama) => {
    if (!bedPilihan.value[id]) { showAlert('warning', 'Pilih Bed', 'Silakan pilih bed terlebih dahulu.'); return; }
    openConfirm({
        title:   'Konfirmasi Alokasi Bed',
        message: `Alokasikan bed untuk pasien ${nama}? Bed akan berstatus BOOKING.`,
        danger:  false,
        action:  () => router.post(route('icu.alokasi_bed', id), { Kode_Ruang: bedPilihan.value[id] }),
    });
};

const doMasukRuangan = (id, nama) => {
    openConfirm({
        title:   'Antar ke Ruangan?',
        message: `Pasien ${nama} akan diantar ke ruangan ICU. Bed akan berstatus TERISI.`,
        danger:  false,
        action:  () => router.post(route('icu.masuk_ruangan', id)),
    });
};
</script>

<template>
    <AppLayout :flash="flash" page-title="Alokasi Bed ICU">
        <AlertModal   v-bind="alert"   @close="alert.show = false" />
        <ConfirmModal v-bind="confirm" @confirm="doConfirm" @cancel="confirm.show = false" />

        <div class="p-3 sm:p-5 space-y-4 sm:space-y-5">
            <!-- Header -->
            <div>
                <h1 class="text-lg font-bold text-gray-800">Alokasi Bed ICU</h1>
                <p class="text-sm text-gray-400 mt-0.5">Pencocokan dan alokasi bed untuk pasien yang membutuhkan</p>
            </div>

            <!-- Tab -->
            <div class="flex items-center gap-1 bg-gray-100 rounded-xl p-1 w-full sm:w-fit overflow-x-auto">
                <button @click="activeTab = 'alokasi'"
                    :class="['px-4 py-1.5 rounded-lg text-sm font-semibold transition-all flex items-center gap-1.5', activeTab === 'alokasi' ? 'bg-white shadow-sm text-green-700' : 'text-gray-500']">
                    Waiting & Booking
                    <span class="bg-rose-100 text-rose-700 text-xs px-1.5 rounded-full">{{ waiting.length + booking.length }}</span>
                </button>
                <button @click="activeTab = 'denah'"
                    :class="['px-4 py-1.5 rounded-lg text-sm font-semibold transition-all flex items-center gap-1.5', activeTab === 'denah' ? 'bg-white shadow-sm text-green-700' : 'text-gray-500']">
                    Denah Bed
                    <span class="bg-green-100 text-green-700 text-xs px-1.5 rounded-full">{{ semuaKamar.length }}</span>
                </button>
            </div>

            <!-- Tab: Alokasi -->
            <div v-if="activeTab === 'alokasi'" class="space-y-5">

                <!-- Waiting ICU -->
                <div>
                    <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-3 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-rose-500"></span> Menunggu Alokasi Bed ({{ waiting.length }})
                    </p>
                    <div v-if="waiting.length === 0" class="bg-white rounded-2xl border border-gray-100 shadow-sm text-center py-10 text-gray-300">
                        <p class="text-sm">Tidak ada pasien menunggu bed</p>
                    </div>
                    <div v-for="p in waiting" :key="p.id"
                        class="bg-white rounded-2xl border border-rose-100 shadow-sm p-4 mb-3">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <p class="font-semibold text-gray-800">{{ p.nama_pasien }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">Reg: {{ p.No_Reg }}</p>
                                <span class="mt-1.5 inline-block text-xs bg-rose-100 text-rose-700 font-semibold px-2 py-0.5 rounded-full">
                                    Butuh: {{ p.required_bed_type }}
                                </span>
                            </div>
                            <div class="flex gap-2 items-center flex-wrap">
                                <select v-model="bedPilihan[p.id]"
                                    :disabled="bedCocokUntuk(p.required_bed_type).length === 0"
                                    class="flex-1 sm:flex-none px-3 py-2 text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-green-400 bg-white sm:min-w-[180px] disabled:opacity-40">
                                    <option value="" disabled>-- Pilih Bed --</option>
                                    <option v-for="bed in bedCocokUntuk(p.required_bed_type)" :key="bed.Kode_Ruang" :value="bed.Kode_Ruang">
                                        {{ bed.nama_ruang }}
                                    </option>
                                </select>
                                <button v-if="bedCocokUntuk(p.required_bed_type).length > 0"
                                    @click="doAlokasi(p.id, p.nama_pasien)"
                                    class="flex-shrink-0 flex items-center gap-1.5 bg-green-600 hover:bg-green-700 text-white text-sm font-semibold px-4 py-2 rounded-xl transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    Alokasi
                                </button>
                                <span v-else class="text-xs text-gray-400 bg-gray-100 px-3 py-2 rounded-xl">Tidak ada bed cocok</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Booking ICU -->
                <div>
                    <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-3 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-green-500"></span> Sudah Dapat Kamar — Menunggu Transfer ({{ booking.length }})
                    </p>
                    <div v-if="booking.length === 0" class="bg-white rounded-2xl border border-gray-100 shadow-sm text-center py-10 text-gray-300">
                        <p class="text-sm">Tidak ada pasien booking kamar</p>
                    </div>
                    <div v-for="p in booking" :key="p.id"
                        class="bg-white rounded-2xl border-l-4 border-l-green-500 border border-green-100 shadow-sm p-4 mb-3">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <p class="font-semibold text-gray-800">{{ p.nama_pasien }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">Reg: {{ p.No_Reg }} · {{ p.required_bed_type }}</p>
                                <span class="mt-1.5 inline-block text-xs bg-green-100 text-green-700 font-medium px-2 py-0.5 rounded-full">
                                    🏥 {{ p.nama_bed }}
                                </span>
                            </div>
                            <button @click="doMasukRuangan(p.id, p.nama_pasien)"
                                class="flex-shrink-0 flex items-center gap-1.5 bg-green-700 hover:bg-green-800 text-white text-sm font-semibold px-4 py-2 rounded-xl transition-colors w-full sm:w-auto justify-center">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                Antar ke Ruangan
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab: Denah Bed -->
            <div v-if="activeTab === 'denah'" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="font-bold text-gray-800">Denah Bed ICU — Real-time</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Semua bed dan statusnya saat ini</p>
                    </div>
                </div>
                <BedGrid :kamar="semuaKamar" />
            </div>
        </div>
    </AppLayout>
</template>
