<script setup>
import { ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout    from '@/Layouts/AppLayout.vue';
import AlertModal   from '@/Components/AlertModal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';

const props = defineProps({
    list:  { type: Array,  default: () => [] },
    flash: { type: Object, default: () => ({}) },
});

const alert   = ref({ show: false, type: 'success', title: '', message: '' });
const confirm = ref({ show: false, id: null, nama: '' });
const showAlert = (t, h, m) => { alert.value = { show: true, type: t, title: h, message: m }; };
watch(() => props.flash, (f) => {
    if (f?.success) showAlert('success', 'Berhasil!', f.success);
    if (f?.error)   showAlert('error',   'Gagal!',    f.error);
}, { immediate: true, deep: true });

const showForm = ref(false);
const form = useForm({ Nama_Pasien: '', jenis_kelamin: '', No_Identitas: '', KartuBPJS: '' });
const submit = () => form.post(route('icu.tambah'), { onSuccess: () => { form.reset(); showForm.value = false; } });

const openKirimIgd = (p) => { confirm.value = { show: true, id: p.id, nama: p.nama_pasien }; };
const doKirimIgd   = () => { router.post(route('icu.kirim_igd', confirm.value.id)); confirm.value.show = false; };

const gLabel = (g) => g === 'L' ? 'Laki-laki' : g === 'P' ? 'Perempuan' : '-';
const gIcon  = (g) => g === 'L' ? '♂' : g === 'P' ? '♀' : '—';
const gBg    = (g) => g === 'L' ? '#D8E9F8' : g === 'P' ? '#FAD8E5' : '#E8EEEE';
const gTxt   = (g) => g === 'L' ? '#4A90D9' : g === 'P' ? '#D9517A' : '#8A9E9E';
</script>

<template>
    <AppLayout :flash="flash" page-title="Pendaftaran Pasien">
        <AlertModal   v-bind="alert"   @close="alert.show = false"/>
        <ConfirmModal :show="confirm.show" title="Kirim ke IGD?"
            :message="`Pasien ${confirm.nama} akan dikirim ke IGD.`"
            confirm-text="Ya, Kirim ke IGD"
            @confirm="doKirimIgd" @cancel="confirm.show = false"/>

        <div class="p-4 sm:p-6 space-y-4" style="font-family:'DM Sans',sans-serif">

            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h1 class="font-sora font-bold text-lg" style="color:var(--text-primary)">Pendaftaran Pasien ICU</h1>
                    <p class="text-sm mt-0.5" style="color:var(--text-secondary)">Daftarkan pasien baru dan kelola antrian masuk</p>
                </div>
                <button @click="showForm = !showForm"
                    class="flex items-center gap-2 text-sm font-semibold px-4 py-2.5 rounded-xl transition-all self-start sm:self-auto"
                    :style="showForm ? 'background:var(--bg-main); color:var(--text-secondary); border:1px solid var(--border-default)' : 'background:#1A9E8F; color:#fff'">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="showForm ? 'M6 18L18 6M6 6l12 12' : 'M12 4v16m8-8H4'"/>
                    </svg>
                    {{ showForm ? 'Tutup' : 'Pasien Baru' }}
                </button>
            </div>

            <!-- Form -->
            <Transition enter-active-class="transition-all duration-200 ease-out" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0">
                <form v-if="showForm" @submit.prevent="submit" class="card-teal overflow-hidden">
                    <div class="px-5 py-3" style="background:linear-gradient(90deg,#1A9E8F,#2DC5B2)">
                        <p class="text-sm font-semibold text-white" style="font-family:'DM Sans',sans-serif">Form Pendaftaran Pasien Baru</p>
                    </div>
                    <div class="p-5 space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-primary)">Nama Lengkap <span style="color:#E05A5A">*</span></label>
                                <input v-model="form.Nama_Pasien"
                                    class="w-full px-3 py-2.5 text-sm rounded-xl outline-none transition-all"
                                    style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary); font-family:'DM Sans',sans-serif"
                                    placeholder="Nama pasien" required/>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-primary)">Jenis Kelamin <span style="color:#E05A5A">*</span></label>
                                <div class="flex gap-2">
                                    <button type="button" @click="form.jenis_kelamin = 'L'"
                                        class="flex-1 flex items-center justify-center gap-1.5 py-2.5 rounded-xl text-sm font-semibold transition-all"
                                        :style="form.jenis_kelamin === 'L' ? 'background:#4A90D9; color:#fff; border:2px solid #4A90D9' : 'background:var(--bg-surface); color:var(--text-secondary); border:2px solid var(--border-default)'">
                                        ♂ Pria
                                    </button>
                                    <button type="button" @click="form.jenis_kelamin = 'P'"
                                        class="flex-1 flex items-center justify-center gap-1.5 py-2.5 rounded-xl text-sm font-semibold transition-all"
                                        :style="form.jenis_kelamin === 'P' ? 'background:#D9517A; color:#fff; border:2px solid #D9517A' : 'background:var(--bg-surface); color:var(--text-secondary); border:2px solid var(--border-default)'">
                                        ♀ Wanita
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-primary)">No. KTP / NIK <span style="color:#E05A5A">*</span></label>
                                <input v-model="form.No_Identitas"
                                    class="w-full px-3 py-2.5 text-sm rounded-xl outline-none"
                                    style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary); font-family:'DM Mono',monospace"
                                    placeholder="16 digit NIK" required/>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-primary)">No. BPJS <span style="color:var(--text-secondary); font-weight:400">(opsional)</span></label>
                                <input v-model="form.KartuBPJS"
                                    class="w-full px-3 py-2.5 text-sm rounded-xl outline-none"
                                    style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary); font-family:'DM Mono',monospace"
                                    placeholder="No. BPJS"/>
                            </div>
                        </div>
                        <div class="flex gap-2 pt-1" style="border-top:1px solid var(--border-default)">
                            <button type="submit" :disabled="form.processing || !form.jenis_kelamin"
                                class="flex items-center gap-2 text-sm font-semibold px-5 py-2.5 rounded-xl text-white transition-colors disabled:opacity-50"
                                style="background:#1A9E8F">
                                <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                Simpan & Daftarkan
                            </button>
                            <button type="button" @click="showForm=false; form.reset()"
                                class="px-5 py-2.5 text-sm rounded-xl transition-colors"
                                style="color:var(--text-secondary); background:var(--bg-main); border:1px solid var(--border-default)">Batal</button>
                            <p v-if="!form.jenis_kelamin" class="text-xs self-center ml-1" style="color:#E0923A">⚠ Pilih jenis kelamin</p>
                        </div>
                    </div>
                </form>
            </Transition>

            <!-- Table -->
            <div class="card-teal overflow-hidden">
                <div class="flex items-center justify-between px-5 py-3.5" style="border-bottom:1px solid var(--border-default)">
                    <p class="text-sm font-semibold" style="color:var(--text-primary)">Antrian Pendaftaran</p>
                    <div class="flex items-center gap-2">
                        <span v-if="list.filter(p=>p.jenis_kelamin==='L').length" class="text-xs font-semibold px-2 py-0.5 rounded-full" style="background:#D8E9F8; color:#4A90D9">♂ {{ list.filter(p=>p.jenis_kelamin==='L').length }}</span>
                        <span v-if="list.filter(p=>p.jenis_kelamin==='P').length" class="text-xs font-semibold px-2 py-0.5 rounded-full" style="background:#FAD8E5; color:#D9517A">♀ {{ list.filter(p=>p.jenis_kelamin==='P').length }}</span>
                        <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full" style="background:#F0FBF9; color:#1A9E8F; border:1px solid #A8DDD7">{{ list.length }}</span>
                    </div>
                </div>

                <div v-if="list.length === 0" class="text-center py-12" style="color:var(--text-secondary)">
                    <svg class="w-10 h-10 mx-auto mb-2 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <p class="text-sm" style="font-family:'DM Sans',sans-serif">Belum ada antrian</p>
                </div>
                <template v-else>
                <div class="overflow-x-auto">
                <table class="w-full text-sm" style="min-width:700px">
                    <thead style="background:var(--bg-main)">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color:var(--text-secondary)">Pasien</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color:var(--text-secondary)">No. MR</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color:var(--text-secondary)">No. Registrasi</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color:var(--text-secondary)">Waktu Daftar</th>
                            <th class="px-5 py-3 text-center text-xs font-semibold uppercase tracking-wide" style="color:var(--text-secondary)">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="p in list" :key="p.id" class="transition-colors" style="border-top:1px solid var(--border-default)"
                            onmouseover="this.style.background='var(--bg-row-hover)'" onmouseout="this.style.background='transparent'">
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0"
                                        :style="`background:${gBg(p.jenis_kelamin)}; color:${gTxt(p.jenis_kelamin)}`">
                                        {{ gIcon(p.jenis_kelamin) }}
                                    </div>
                                    <div>
                                        <p class="font-semibold" style="color:var(--text-primary)">{{ p.nama_pasien }}</p>
                                        <span class="text-xs px-1.5 py-0.5 rounded-full" :style="`background:${gBg(p.jenis_kelamin)}; color:${gTxt(p.jenis_kelamin)}`">{{ gLabel(p.jenis_kelamin) }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3.5 text-xs font-mono" style="color:var(--text-secondary)">{{ p.No_MR }}</td>
                            <td class="px-5 py-3.5 text-xs font-mono" style="color:var(--text-secondary)">{{ p.No_Reg }}</td>
                            <td class="px-5 py-3.5 text-xs" style="color:var(--text-secondary)">{{ p.created_at }}</td>
                            <td class="px-5 py-3.5 text-center">
                                <button @click="openKirimIgd(p)"
                                    class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-lg text-white transition-colors"
                                    style="background:#E0923A">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                    Kirim IGD
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
