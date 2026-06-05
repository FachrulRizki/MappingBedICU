<script setup>
import { ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout    from '@/Layouts/AppLayout.vue';
import AlertModal   from '@/Components/AlertModal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';

const props = defineProps({
    butuhSpri:        { type: Array, default: () => [] },
    menungguApproval: { type: Array, default: () => [] },
    masterKelas:      { type: Array, default: () => [] },
    flash:            { type: Object, default: () => ({}) },
});

const alert   = ref({ show: false, type: 'success', title: '', message: '' });
const confirm = ref({ show: false, id: null, nama: '' });
const showAlert = (t, h, m) => { alert.value = { show: true, type: t, title: h, message: m }; };
watch(() => props.flash, (f) => {
    if (f?.success) showAlert('success', 'Berhasil!', f.success);
    if (f?.error)   showAlert('error',   'Gagal!',    f.error);
}, { immediate: true, deep: true });

const activeTab  = ref('buat');
const expandedId = ref(null);
const spriState  = ref({});
const getForm = (id) => {
    if (!spriState.value[id]) spriState.value[id] = useForm({ Diagnosis: '', IndikasiRI: '', Keterangan: '', required_bed_type: '' });
    return spriState.value[id];
};
const submitSpri = (id) => getForm(id).post(route('icu.buat_spri', id), { onSuccess: () => { expandedId.value = null; } });

const openApprove = (p) => { confirm.value = { show: true, id: p.id, nama: p.nama_pasien }; };
const doApprove   = () => { router.post(route('icu.approve_spri', confirm.value.id)); confirm.value.show = false; };
</script>

<template>
    <AppLayout :flash="flash" page-title="SPRI">
        <AlertModal v-bind="alert" @close="alert.show = false"/>
        <ConfirmModal :show="confirm.show" title="Approve SPRI?"
            :message="`SPRI ${confirm.nama} akan disetujui dan masuk daftar tunggu bed.`"
            confirm-text="Ya, Approve" @confirm="doApprove" @cancel="confirm.show = false"/>

        <div class="p-4 sm:p-6 space-y-4" style="font-family:'DM Sans',sans-serif">
            <div>
                <h1 class="font-sora font-bold text-lg" style="color:var(--text-primary)">Surat Permintaan Rawat Inap</h1>
                <p class="text-sm mt-0.5" style="color:var(--text-secondary)">Buat dan kelola SPRI pasien ICU</p>
            </div>

            <!-- Tabs -->
            <div class="flex gap-1 p-1 rounded-xl w-full sm:w-fit overflow-x-auto" style="background:var(--bg-main); border:1px solid var(--border-default)">
                <button @click="activeTab='buat'" class="flex-1 sm:flex-none px-4 py-1.5 rounded-lg text-sm font-semibold transition-all flex items-center gap-1.5 whitespace-nowrap"
                    :style="activeTab==='buat' ? 'background:var(--bg-surface); color:#1A9E8F; box-shadow:0 1px 4px rgba(26,158,143,.1)' : 'color:var(--text-secondary)'">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Buat SPRI
                    <span class="text-xs px-1.5 rounded-full" style="background:#FDE8CC; color:#E0923A">{{ butuhSpri.length }}</span>
                </button>
                <button @click="activeTab='approve'" class="flex-1 sm:flex-none px-4 py-1.5 rounded-lg text-sm font-semibold transition-all flex items-center gap-1.5 whitespace-nowrap"
                    :style="activeTab==='approve' ? 'background:var(--bg-surface); color:#1A9E8F; box-shadow:0 1px 4px rgba(26,158,143,.1)' : 'color:var(--text-secondary)'">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    Approval
                    <span class="text-xs px-1.5 rounded-full" style="background:#DCF5F2; color:#1A9E8F">{{ menungguApproval.length }}</span>
                </button>
            </div>

            <!-- Buat SPRI -->
            <div v-if="activeTab==='buat'" class="space-y-3">
                <div v-if="butuhSpri.length===0" class="card-teal text-center py-12" style="color:var(--text-secondary)">
                    <p class="text-sm">Tidak ada pasien yang perlu SPRI</p>
                </div>
                <div v-for="p in butuhSpri" :key="p.id" class="card-teal overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-3.5 cursor-pointer transition-colors"
                        onmouseover="this.style.background='var(--bg-row-hover)'" onmouseout="this.style.background='transparent'"
                        @click="expandedId = expandedId === p.id ? null : p.id">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0" style="background:#FDE8CC">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color:#E0923A"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold" style="color:var(--text-primary)">{{ p.nama_pasien }}</p>
                                <p class="text-xs font-mono" style="color:var(--text-secondary)">{{ p.No_MR }} · {{ p.No_Reg }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full hidden sm:inline" style="background:#FDE8CC; color:#E0923A">Di IGD</span>
                            <svg :class="['w-4 h-4 transition-transform', expandedId===p.id?'rotate-180':'']" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color:var(--text-secondary)"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </div>
                    </div>
                    <Transition enter-active-class="transition-all duration-200 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100">
                        <form v-if="expandedId===p.id" @submit.prevent="submitSpri(p.id)"
                            class="px-5 pb-5 pt-2" style="border-top:1px solid var(--border-default); background:var(--bg-main)">
                            <p class="text-xs font-bold uppercase tracking-wide mt-2 mb-3" style="color:#1A9E8F">Isi Data SPRI</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <input v-model="getForm(p.id).Diagnosis" class="px-3 py-2.5 text-sm rounded-xl outline-none" style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)" placeholder="Diagnosis *" required/>
                                <input v-model="getForm(p.id).IndikasiRI" class="px-3 py-2.5 text-sm rounded-xl outline-none" style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)" placeholder="Indikasi RI *" required/>
                                <input v-model="getForm(p.id).Keterangan" class="px-3 py-2.5 text-sm rounded-xl outline-none" style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)" placeholder="Keterangan (opsional)"/>
                                <select v-model="getForm(p.id).required_bed_type" class="px-3 py-2.5 text-sm rounded-xl outline-none" style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)" required>
                                    <option value="" disabled>Jenis Bed *</option>
                                    <option v-for="k in masterKelas" :key="k.kode" :value="k.nama">{{ k.nama }}</option>
                                </select>
                            </div>
                            <div class="flex gap-2 mt-3">
                                <button type="submit" :disabled="getForm(p.id).processing"
                                    class="flex items-center gap-2 text-sm font-semibold px-5 py-2.5 rounded-xl text-white disabled:opacity-50"
                                    style="background:#1A9E8F">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Buat SPRI
                                </button>
                                <button type="button" @click="expandedId=null" class="px-4 py-2.5 text-sm rounded-xl" style="color:var(--text-secondary); background:var(--bg-surface); border:1px solid var(--border-default)">Batal</button>
                            </div>
                        </form>
                    </Transition>
                </div>
            </div>

            <!-- Approval -->
            <div v-if="activeTab==='approve'" class="space-y-3">
                <div v-if="menungguApproval.length===0" class="card-teal text-center py-12" style="color:var(--text-secondary)">
                    <p class="text-sm">Tidak ada SPRI menunggu persetujuan</p>
                </div>
                <div v-for="p in menungguApproval" :key="p.id"
                    class="card-teal p-4 flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0" style="background:#DCF5F2">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color:#1A9E8F"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        </div>
                        <div>
                            <p class="text-sm font-semibold" style="color:var(--text-primary)">{{ p.nama_pasien }}</p>
                            <p class="text-xs font-mono" style="color:var(--text-secondary)">{{ p.No_Reg }}</p>
                            <div class="flex flex-wrap gap-1.5 mt-2">
                                <span class="text-xs px-2 py-0.5 rounded-full" style="background:#DCF5F2; color:#1A9E8F">{{ p.required_bed_type }}</span>
                                <span class="text-xs px-2 py-0.5 rounded-full" style="background:#E8EEEE; color:var(--text-secondary)">{{ p.diagnosis }}</span>
                            </div>
                        </div>
                    </div>
                    <button @click="openApprove(p)"
                        class="flex-shrink-0 flex items-center justify-center gap-1.5 text-sm font-semibold px-4 py-2 rounded-xl text-white w-full sm:w-auto"
                        style="background:#1A9E8F">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        Approve SPRI
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
