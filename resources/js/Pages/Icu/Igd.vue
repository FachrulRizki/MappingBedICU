<script setup>
import { ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout    from '@/Layouts/AppLayout.vue';
import AlertModal   from '@/Components/AlertModal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';

const props = defineProps({
    antrianDaftar: { type: Array,  default: () => [] },
    diIgd:         { type: Array,  default: () => [] },
    masterKelas:   { type: Array,  default: () => [] },
    flash:         { type: Object, default: () => ({}) },
});

const alert   = ref({ show: false, type: 'success', title: '', message: '' });
const confirm = ref({ show: false, action: null, title: '', message: '' });
const showAlert = (t, h, m) => { alert.value = { show: true, type: t, title: h, message: m }; };
watch(() => props.flash, (f) => {
    if (f?.success) showAlert('success', 'Berhasil!', f.success);
    if (f?.error)   showAlert('error',   'Gagal!',    f.error);
}, { immediate: true, deep: true });

const openConfirm = (cfg) => { confirm.value = { show: true, ...cfg }; };
const doConfirm   = () => { confirm.value.action?.(); confirm.value.show = false; };

const kirimIgd = (p) => openConfirm({
    title: 'Kirim ke IGD?', message: `${p.nama_pasien} akan dikirim ke IGD.`,
    action: () => router.post(route('icu.kirim_igd', p.id)),
});

const spriState  = ref({});
const expandedId = ref(null);
const getForm = (id) => {
    if (!spriState.value[id]) spriState.value[id] = useForm({ Diagnosis: '', IndikasiRI: '', Keterangan: '', required_bed_type: '' });
    return spriState.value[id];
};
const submitSpri = (id) => getForm(id).post(route('icu.buat_spri', id), { onSuccess: () => { expandedId.value = null; } });

const gIcon  = (g) => g === 'L' ? '♂' : g === 'P' ? '♀' : '—';
const gBg    = (g) => g === 'L' ? '#D8E9F8' : g === 'P' ? '#FAD8E5' : '#E8EEEE';
const gTxt   = (g) => g === 'L' ? '#4A90D9' : g === 'P' ? '#D9517A' : '#8A9E9E';
</script>

<template>
    <AppLayout :flash="flash" page-title="IGD & Triase">
        <AlertModal   v-bind="alert"   @close="alert.show = false"/>
        <ConfirmModal v-bind="confirm" @confirm="doConfirm" @cancel="confirm.show = false"/>

        <div class="p-4 sm:p-6 space-y-4" style="font-family:'DM Sans',sans-serif">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h1 class="font-sora font-bold text-lg" style="color:var(--text-primary)">IGD & Triase</h1>
                    <p class="text-sm mt-0.5" style="color:var(--text-secondary)">Kelola antrian masuk dan pembuatan SPRI</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold px-3 py-1.5 rounded-full" style="background:var(--bg-main); color:#E0923A; border:1px solid #F5D4A8">{{ antrianDaftar.length }} Antrian</span>
                    <span class="text-xs font-semibold px-3 py-1.5 rounded-full" style="background:#FDF2F2; color:#E05A5A; border:1px solid #F5B8B8">{{ diIgd.length }} Di IGD</span>
                </div>
            </div>

            <!-- Section 1: Antrian Daftar -->
            <div class="card-teal overflow-hidden">
                <div class="flex items-center justify-between px-5 py-3.5" style="border-bottom:1px solid var(--border-default); background:var(--bg-main)">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full" style="background:#8A9E9E"></span>
                        <p class="text-sm font-semibold" style="color:var(--text-primary)">Antrian Pendaftaran</p>
                    </div>
                    <span class="text-xs font-bold px-2.5 py-0.5 rounded-full" style="background:#E8EEEE; color:var(--text-secondary)">{{ antrianDaftar.length }}</span>
                </div>
                <div v-if="antrianDaftar.length === 0" class="text-center py-8" style="color:var(--text-secondary)">
                    <p class="text-sm">Tidak ada antrian pendaftaran</p>
                </div>
                <div v-else class="divide-y" style="border-color:var(--border-default)">
                    <div v-for="p in antrianDaftar" :key="p.id"
                        class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-5 py-3.5 transition-colors"
                        onmouseover="this.style.background='var(--bg-row-hover)'" onmouseout="this.style.background='transparent'">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm flex-shrink-0"
                                :style="`background:${gBg(p.jenis_kelamin)}; color:${gTxt(p.jenis_kelamin)}`">{{ gIcon(p.jenis_kelamin) }}</div>
                            <div>
                                <p class="text-sm font-semibold" style="color:var(--text-primary)">{{ p.nama_pasien }}</p>
                                <p class="text-xs font-mono" style="color:var(--text-secondary)">{{ p.No_MR }} · {{ p.No_Reg }}</p>
                            </div>
                        </div>
                        <button @click="kirimIgd(p)"
                            class="flex-shrink-0 flex items-center justify-center gap-1.5 text-xs font-semibold px-4 py-2 rounded-xl text-white transition-colors w-full sm:w-auto"
                            style="background:#E0923A">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                            Kirim ke IGD
                        </button>
                    </div>
                </div>
            </div>

            <!-- Section 2: Di IGD + Form SPRI -->
            <div class="card-teal overflow-hidden">
                <div class="flex items-center justify-between px-5 py-3.5" style="border-bottom:1px solid var(--border-default); background:var(--bg-main)">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full" style="background:#E0923A"></span>
                        <p class="text-sm font-semibold" style="color:var(--text-primary)">Sedang di IGD — Buat SPRI</p>
                    </div>
                    <span class="text-xs font-bold px-2.5 py-0.5 rounded-full" style="background:#FDE8CC; color:#E0923A">{{ diIgd.length }}</span>
                </div>
                <div v-if="diIgd.length === 0" class="text-center py-8" style="color:var(--text-secondary)">
                    <p class="text-sm">Tidak ada pasien di IGD</p>
                </div>
                <div v-else class="divide-y" style="border-color:var(--border-default)">
                    <div v-for="p in diIgd" :key="p.id">
                        <div class="flex items-center justify-between gap-3 px-5 py-3.5 cursor-pointer transition-colors"
                            onmouseover="this.style.background='rgba(224,146,58,0.08)'" onmouseout="this.style.background='transparent'"
                            @click="expandedId = expandedId === p.id ? null : p.id">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm flex-shrink-0"
                                    :style="`background:${gBg(p.jenis_kelamin)}; color:${gTxt(p.jenis_kelamin)}`">{{ gIcon(p.jenis_kelamin) }}</div>
                                <div>
                                    <p class="text-sm font-semibold" style="color:var(--text-primary)">{{ p.nama_pasien }}</p>
                                    <p class="text-xs font-mono" style="color:var(--text-secondary)">{{ p.No_MR }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full hidden sm:inline" style="background:#FDE8CC; color:#E0923A">Triase</span>
                                <span class="text-xs font-semibold px-2.5 py-1 rounded-xl" style="background:#F0FBF9; color:#1A9E8F; border:1px solid #A8DDD7">
                                    {{ expandedId === p.id ? 'Tutup' : 'Buat SPRI' }}
                                </span>
                                <svg :class="['w-4 h-4 transition-transform', expandedId === p.id ? 'rotate-180' : '']" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color:var(--text-secondary)"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                            </div>
                        </div>
                        <Transition enter-active-class="transition-all duration-200 ease-out" enter-from-class="opacity-0 -translate-y-1" enter-to-class="opacity-100 translate-y-0">
                            <div v-if="expandedId === p.id" class="px-5 pb-5 pt-2" style="background:var(--bg-main); border-top:1px solid var(--border-default)">
                                <p class="text-xs font-bold uppercase tracking-wide mt-2 mb-3 flex items-center gap-1.5" style="color:#1A9E8F">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                                    SPRI — {{ p.nama_pasien }}
                                </p>
                                <form @submit.prevent="submitSpri(p.id)" class="space-y-3">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">Diagnosis <span style="color:#E05A5A">*</span></label>
                                            <input v-model="getForm(p.id).Diagnosis" class="w-full px-3 py-2.5 text-sm rounded-xl outline-none" style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)" placeholder="Contoh: Gagal Napas" required/>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">Indikasi RI <span style="color:#E05A5A">*</span></label>
                                            <input v-model="getForm(p.id).IndikasiRI" class="w-full px-3 py-2.5 text-sm rounded-xl outline-none" style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)" placeholder="Indikasi rawat inap" required/>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">Jenis Bed <span style="color:#E05A5A">*</span></label>
                                            <select v-model="getForm(p.id).required_bed_type" class="w-full px-3 py-2.5 text-sm rounded-xl outline-none" style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)" required>
                                                <option value="" disabled>Pilih jenis bed</option>
                                                <option v-for="k in masterKelas" :key="k.kode" :value="k.kode">{{ k.kode }} — {{ k.nama }}</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary)">Keterangan</label>
                                            <input v-model="getForm(p.id).Keterangan" class="w-full px-3 py-2.5 text-sm rounded-xl outline-none" style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)" placeholder="Opsional"/>
                                        </div>
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="submit" :disabled="getForm(p.id).processing"
                                            class="flex items-center gap-2 text-sm font-semibold px-5 py-2.5 rounded-xl text-white transition-colors disabled:opacity-50"
                                            style="background:#1A9E8F">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            Simpan SPRI
                                        </button>
                                        <button type="button" @click="expandedId=null" class="px-4 py-2.5 text-sm rounded-xl transition-colors" style="color:var(--text-secondary); background:var(--bg-surface); border:1px solid var(--border-default)">Batal</button>
                                    </div>
                                </form>
                            </div>
                        </Transition>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
