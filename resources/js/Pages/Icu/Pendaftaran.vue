<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout  from '@/Layouts/AppLayout.vue';
import AlertModal from '@/Components/AlertModal.vue';

const props = defineProps({
    list:  { type: Array,  default: () => [] },
    flash: { type: Object, default: () => ({}) },
});

// ── Modal ──────────────────────────────────────────────────────────────
const alert = ref({ show: false, type: 'success', title: '', message: '' });
const showAlert = (type, title, msg) => { alert.value = { show: true, type, title, message: msg }; };
watch(() => props.flash, (f) => {
    if (f?.success) showAlert('success', 'Berhasil!', f.success);
    if (f?.error)   showAlert('error',   'Gagal!',    f.error);
}, { immediate: true, deep: true });

// ── Form ───────────────────────────────────────────────────────────────
const showForm = ref(false);
const form = useForm({
    Nama_Pasien:   '',
    jenis_kelamin: '',
    No_Identitas:  '',
    KartuBPJS:     '',
});

const submit = () => {
    form.post(route('icu.tambah'), {
        onSuccess: () => { form.reset(); showForm.value = false; },
    });
};

// ── Helper tampilan ────────────────────────────────────────────────────
const genderLabel = (g) => g === 'L' ? 'Laki-laki' : g === 'P' ? 'Perempuan' : '-';
const genderBadge = (g) => g === 'L'
    ? 'bg-blue-100 text-blue-700 border-blue-200'
    : g === 'P'
        ? 'bg-pink-100 text-pink-700 border-pink-200'
        : 'bg-gray-100 text-gray-500 border-gray-200';
const genderIcon  = (g) => g === 'L' ? '♂' : g === 'P' ? '♀' : '—';
</script>

<template>
    <AppLayout :flash="flash" page-title="Pendaftaran Pasien">
        <AlertModal v-bind="alert" @close="alert.show = false" />

        <div class="p-3 sm:p-5 space-y-4 sm:space-y-5">

            <!-- ── Page Header ────────────────────────────────────── -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-lg font-bold text-gray-800">Pendaftaran Pasien ICU</h1>
                    <p class="text-sm text-gray-400 mt-0.5">Daftarkan pasien baru dan kelola antrian masuk</p>
                </div>
                <button @click="showForm = !showForm"
                    :class="['flex items-center gap-2 text-sm font-semibold px-4 py-2.5 rounded-xl transition-all shadow-sm',
                        showForm
                            ? 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                            : 'bg-green-600 hover:bg-green-700 text-white']">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="showForm ? 'M6 18L18 6M6 6l12 12' : 'M12 4v16m8-8H4'"/>
                    </svg>
                    {{ showForm ? 'Tutup Form' : 'Pasien Baru' }}
                </button>
            </div>

            <!-- ── Form Pendaftaran ───────────────────────────────── -->
            <Transition
                enter-active-class="transition-all duration-200 ease-out"
                enter-from-class="opacity-0 -translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition-all duration-150 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-2"
            >
                <form v-if="showForm" @submit.prevent="submit"
                    class="bg-white border border-green-100 rounded-2xl shadow-sm overflow-hidden">

                    <!-- Form header -->
                    <div class="bg-gradient-to-r from-green-600 to-teal-600 px-5 py-3.5 flex items-center gap-2">
                        <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        <span class="text-sm font-bold text-white">Form Pendaftaran Pasien Baru</span>
                    </div>

                    <div class="p-5 space-y-4">
                        <!-- Baris 1: Nama + Jenis Kelamin -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-gray-600 mb-1.5">
                                    Nama Lengkap Pasien <span class="text-rose-500">*</span>
                                </label>
                                <input v-model="form.Nama_Pasien"
                                    class="w-full px-3 py-2.5 text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-green-400 bg-white"
                                    :class="form.errors.Nama_Pasien ? 'border-rose-300 focus:ring-rose-300' : ''"
                                    placeholder="Contoh: Budi Santoso" required />
                                <p v-if="form.errors.Nama_Pasien" class="text-xs text-rose-500 mt-1">{{ form.errors.Nama_Pasien }}</p>
                            </div>

                            <!-- Jenis Kelamin — toggle button -->
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1.5">
                                    Jenis Kelamin <span class="text-rose-500">*</span>
                                </label>
                                <div class="flex gap-2">
                                    <button type="button" @click="form.jenis_kelamin = 'L'"
                                        :class="['flex-1 flex items-center justify-center gap-1.5 py-2.5 rounded-xl text-sm font-semibold border-2 transition-all',
                                            form.jenis_kelamin === 'L'
                                                ? 'bg-blue-500 border-blue-500 text-white shadow-md shadow-blue-200'
                                                : 'border-gray-200 text-gray-500 hover:border-blue-200 hover:text-blue-600 bg-white']">
                                        <span class="text-base leading-none">♂</span>
                                        <span>Pria</span>
                                    </button>
                                    <button type="button" @click="form.jenis_kelamin = 'P'"
                                        :class="['flex-1 flex items-center justify-center gap-1.5 py-2.5 rounded-xl text-sm font-semibold border-2 transition-all',
                                            form.jenis_kelamin === 'P'
                                                ? 'bg-pink-500 border-pink-500 text-white shadow-md shadow-pink-200'
                                                : 'border-gray-200 text-gray-500 hover:border-pink-200 hover:text-pink-600 bg-white']">
                                        <span class="text-base leading-none">♀</span>
                                        <span>Wanita</span>
                                    </button>
                                </div>
                                <p v-if="form.errors.jenis_kelamin" class="text-xs text-rose-500 mt-1">{{ form.errors.jenis_kelamin }}</p>
                                <!-- Hidden input untuk validasi required -->
                                <input type="hidden" :value="form.jenis_kelamin" required />
                            </div>
                        </div>

                        <!-- Baris 2: NIK + BPJS -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1.5">
                                    No. KTP / NIK <span class="text-rose-500">*</span>
                                </label>
                                <input v-model="form.No_Identitas"
                                    class="w-full px-3 py-2.5 text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-green-400 bg-white font-mono"
                                    :class="form.errors.No_Identitas ? 'border-rose-300' : ''"
                                    placeholder="16 digit NIK" required />
                                <p v-if="form.errors.No_Identitas" class="text-xs text-rose-500 mt-1">{{ form.errors.No_Identitas }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1.5">
                                    No. Kartu BPJS
                                    <span class="text-gray-400 font-normal">(opsional)</span>
                                </label>
                                <input v-model="form.KartuBPJS"
                                    class="w-full px-3 py-2.5 text-sm rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-green-400 bg-white font-mono"
                                    placeholder="13 digit nomor BPJS" />
                            </div>
                        </div>

                        <!-- Action buttons -->
                        <div class="flex items-center gap-3 pt-1 border-t border-gray-100">
                            <button type="submit"
                                :disabled="form.processing || !form.jenis_kelamin"
                                class="flex items-center gap-2 bg-green-600 hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition-colors">
                                <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                                Simpan & Daftarkan
                            </button>
                            <button type="button" @click="showForm = false; form.reset()"
                                class="px-5 py-2.5 text-sm text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-xl transition-colors">
                                Batal
                            </button>
                            <p v-if="!form.jenis_kelamin" class="text-xs text-amber-600 ml-1">
                                ⚠ Pilih jenis kelamin terlebih dahulu
                            </p>
                        </div>
                    </div>
                </form>
            </Transition>

            <!-- ── Tabel Antrian ──────────────────────────────────── -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <span class="text-sm font-bold text-gray-700">Antrian Pendaftaran</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <!-- Mini gender stats -->
                        <span v-if="list.filter(p => p.jenis_kelamin === 'L').length > 0"
                            class="text-xs font-semibold bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">
                            ♂ {{ list.filter(p => p.jenis_kelamin === 'L').length }}
                        </span>
                        <span v-if="list.filter(p => p.jenis_kelamin === 'P').length > 0"
                            class="text-xs font-semibold bg-pink-100 text-pink-700 px-2 py-0.5 rounded-full">
                            ♀ {{ list.filter(p => p.jenis_kelamin === 'P').length }}
                        </span>
                        <span class="text-xs bg-gray-100 text-gray-600 font-semibold px-2.5 py-0.5 rounded-full">
                            {{ list.length }} pasien
                        </span>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-if="list.length === 0" class="text-center py-14">
                    <div class="w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
                        <svg class="w-7 h-7 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-gray-400">Belum ada pasien di antrian</p>
                    <p class="text-xs text-gray-300 mt-1">Klik tombol <strong>Pasien Baru</strong> untuk mendaftarkan pasien</p>
                </div>

                <!-- Tabel data -->
                <template v-if="list.length > 0">
                <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-[560px]">
                    <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wide">
                        <tr>
                            <th class="px-5 py-3 text-left font-semibold">Pasien</th>
                            <th class="px-5 py-3 text-left font-semibold">No. MR</th>
                            <th class="px-5 py-3 text-left font-semibold">No. Registrasi</th>
                            <th class="px-5 py-3 text-left font-semibold">Waktu Daftar</th>
                            <th class="px-5 py-3 text-center font-semibold">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="p in list" :key="p.id" class="hover:bg-gray-50 transition-colors">
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-2">
                                    <div :class="['w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0',
                                        p.jenis_kelamin === 'L' ? 'bg-blue-100 text-blue-700' :
                                        p.jenis_kelamin === 'P' ? 'bg-pink-100 text-pink-700' :
                                        'bg-gray-100 text-gray-500']">
                                        {{ genderIcon(p.jenis_kelamin) }}
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-800">{{ p.nama_pasien }}</p>
                                        <span :class="['text-xs font-medium border px-1.5 py-0.5 rounded-full', genderBadge(p.jenis_kelamin)]">
                                            {{ genderLabel(p.jenis_kelamin) }}
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-gray-500 font-mono text-xs">{{ p.No_MR }}</td>
                            <td class="px-5 py-3.5 text-gray-500 font-mono text-xs">{{ p.No_Reg }}</td>
                            <td class="px-5 py-3.5 text-gray-400 text-xs">{{ p.created_at }}</td>
                            <td class="px-5 py-3.5 text-center">
                                <span class="inline-flex items-center gap-1 bg-gray-100 text-gray-600 text-xs font-semibold px-2.5 py-1 rounded-full">
                                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                                    Terdaftar
                                </span>
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
