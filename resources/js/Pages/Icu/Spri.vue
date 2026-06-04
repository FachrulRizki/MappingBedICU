<script setup>
import { ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout    from '@/Layouts/AppLayout.vue';
import AlertModal   from '@/Components/AlertModal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';

const props = defineProps({
    butuhSpri:        { type: Array,  default: () => [] },
    menungguApproval: { type: Array,  default: () => [] },
    masterKelas:      { type: Array,  default: () => [] },
    flash:            { type: Object, default: () => ({}) },
});

// ── Modal state ───────────────────────────────────────────────────────
const alert   = ref({ show: false, type: 'success', title: '', message: '' });
const confirm = ref({ show: false, id: null, nama: '' });

const showAlert = (type, title, msg) => { alert.value = { show: true, type, title, message: msg }; };
watch(() => props.flash, (f) => {
    if (f?.success) showAlert('success', 'Berhasil!', f.success);
    if (f?.error)   showAlert('error',   'Gagal!',    f.error);
}, { immediate: true, deep: true });

// ── Tab ───────────────────────────────────────────────────────────────
const activeTab = ref('buat'); // 'buat' | 'approve'

// ── Form SPRI per pasien ──────────────────────────────────────────────
const spriState  = ref({});
const expandedId = ref(null);

const getSpriForm = (id) => {
    if (!spriState.value[id])
        spriState.value[id] = useForm({ Diagnosis: '', IndikasiRI: '', Keterangan: '', required_bed_type: '' });
    return spriState.value[id];
};

const submitSpri = (id) => {
    getSpriForm(id).post(route('icu.buat_spri', id));
};

// ── Approve SPRI ──────────────────────────────────────────────────────
const openConfirm = (p) => { confirm.value = { show: true, id: p.id, nama: p.nama_pasien }; };
const doApprove   = () => {
    router.post(route('icu.approve_spri', confirm.value.id));
    confirm.value.show = false;
};
</script>

<template>
    <AppLayout :flash="flash" page-title="SPRI">
        <AlertModal   v-bind="alert"   @close="alert.show = false" />
        <ConfirmModal
            :show="confirm.show"
            title="Approve SPRI?"
            :message="`SPRI pasien ${confirm.nama} akan disetujui dan masuk daftar tunggu bed ICU. Lanjutkan?`"
            confirm-text="Ya, Approve SPRI"
            @confirm="doApprove"
            @cancel="confirm.show = false"
        />

        <div class="p-3 sm:p-5 space-y-4 sm:space-y-5">
            <!-- Header -->
            <div>
                <h1 class="text-lg font-bold text-gray-800">Surat Permintaan Rawat Inap (SPRI)</h1>
                <p class="text-sm text-gray-400 mt-0.5">Buat dan kelola SPRI pasien ICU</p>
            </div>

            <!-- Tab -->
            <div class="flex items-center gap-1 bg-gray-100 rounded-xl p-1 w-full sm:w-fit overflow-x-auto">
                <button @click="activeTab = 'buat'"
                    :class="['px-4 py-1.5 rounded-lg text-sm font-semibold transition-all flex items-center gap-1.5', activeTab === 'buat' ? 'bg-white shadow-sm text-green-700' : 'text-gray-500 hover:text-gray-700']">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Buat SPRI
                    <span class="bg-amber-100 text-amber-700 text-xs px-1.5 rounded-full">{{ butuhSpri.length }}</span>
                </button>
                <button @click="activeTab = 'approve'"
                    :class="['px-4 py-1.5 rounded-lg text-sm font-semibold transition-all flex items-center gap-1.5', activeTab === 'approve' ? 'bg-white shadow-sm text-green-700' : 'text-gray-500 hover:text-gray-700']">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    Approval
                    <span class="bg-teal-100 text-teal-700 text-xs px-1.5 rounded-full">{{ menungguApproval.length }}</span>
                </button>
            </div>

            <!-- Tab: Buat SPRI -->
            <div v-if="activeTab === 'buat'" class="space-y-3">
                <div v-if="butuhSpri.length === 0" class="bg-white rounded-2xl border border-gray-100 shadow-sm text-center py-12 text-gray-300">
                    <svg class="w-10 h-10 mx-auto mb-2 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    <p class="text-sm">Tidak ada pasien yang perlu dibuatkan SPRI</p>
                </div>

                <div v-for="p in butuhSpri" :key="p.id"
                    class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    <!-- Card header -->
                    <div class="flex items-center justify-between px-5 py-3.5 cursor-pointer hover:bg-gray-50 transition-colors"
                        @click="expandedId = expandedId === p.id ? null : p.id">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center">
                                <svg class="w-4 h-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-800 text-sm">{{ p.nama_pasien }}</p>
                                <p class="text-xs text-gray-400">MR: {{ p.No_MR }} · Reg: {{ p.No_Reg }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-xs bg-amber-100 text-amber-700 font-semibold px-2 py-0.5 rounded-full">Di IGD</span>
                            <svg :class="['w-4 h-4 text-gray-400 transition-transform', expandedId === p.id ? 'rotate-180' : '']" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </div>
                    </div>

                    <!-- Form SPRI (expand) -->
                    <Transition enter-active-class="transition-all duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100">
                        <form v-if="expandedId === p.id" @submit.prevent="submitSpri(p.id)"
                            class="px-5 pb-5 pt-1 border-t border-gray-50 bg-gray-50/50">
                            <p class="text-xs font-bold text-gray-500 uppercase tracking-wide my-3">Isi Data SPRI</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <input v-model="getSpriForm(p.id).Diagnosis"
                                    class="px-3 py-2.5 text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-green-400 bg-white"
                                    placeholder="Diagnosis Dokter *" required />
                                <input v-model="getSpriForm(p.id).IndikasiRI"
                                    class="px-3 py-2.5 text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-green-400 bg-white"
                                    placeholder="Indikasi Rawat Inap *" required />
                                <input v-model="getSpriForm(p.id).Keterangan"
                                    class="px-3 py-2.5 text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-green-400 bg-white"
                                    placeholder="Keterangan (opsional)" />
                                <select v-model="getSpriForm(p.id).required_bed_type"
                                    class="px-3 py-2.5 text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-green-400 bg-white" required>
                                    <option value="" disabled>Pilih Jenis Bed ICU *</option>
                                    <option v-for="k in masterKelas" :key="k.kode" :value="k.kode">{{ k.kode }} — {{ k.nama }}</option>
                                </select>
                            </div>
                            <div class="flex gap-2 mt-3">
                                <button type="submit" :disabled="getSpriForm(p.id).processing"
                                    class="flex items-center gap-2 bg-rose-600 hover:bg-rose-700 disabled:opacity-50 text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition-colors">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Buat SPRI
                                </button>
                                <button type="button" @click="expandedId = null"
                                    class="px-4 py-2.5 text-sm text-gray-600 hover:bg-gray-200 rounded-xl transition-colors">Tutup</button>
                            </div>
                        </form>
                    </Transition>
                </div>
            </div>

            <!-- Tab: Approve -->
            <div v-if="activeTab === 'approve'" class="space-y-3">
                <div v-if="menungguApproval.length === 0" class="bg-white rounded-2xl border border-gray-100 shadow-sm text-center py-12 text-gray-300">
                    <svg class="w-10 h-10 mx-auto mb-2 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    <p class="text-sm">Tidak ada SPRI menunggu persetujuan</p>
                </div>
                <div v-for="p in menungguApproval" :key="p.id"
                    class="bg-white rounded-2xl border border-teal-100 shadow-sm p-4 flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <div class="w-9 h-9 rounded-xl bg-teal-100 flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-teal-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        </div>
                        <div>
                            <p class="font-semibold text-gray-800 text-sm">{{ p.nama_pasien }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">Reg: {{ p.No_Reg }}</p>
                            <div class="flex flex-wrap gap-1.5 mt-2">
                                <span class="text-xs bg-teal-100 text-teal-700 font-medium px-2 py-0.5 rounded-full">{{ p.required_bed_type }}</span>
                                <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">{{ p.diagnosis }}</span>
                                <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">{{ p.indikasi }}</span>
                            </div>
                        </div>
                    </div>
                    <button @click="openConfirm(p)"
                        class="flex-shrink-0 flex items-center justify-center gap-1.5 text-sm font-semibold bg-teal-600 hover:bg-teal-700 text-white px-4 py-2 rounded-xl transition-colors w-full sm:w-auto">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        Approve SPRI
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
