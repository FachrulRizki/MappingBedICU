<script setup>
import { ref, computed, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout    from '@/Layouts/AppLayout.vue';
import AlertModal   from '@/Components/AlertModal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { useAuth }  from '@/composables/useAuth.js';

const {
    canBuatSpriInternal, canApproveAdmisi, canBookingBedIcu,
    canVerifikasiAdmisi, canKonfirmasiMasuk, canPulangkan,
    isAdmin, user: authUser,
} = useAuth();

const props = defineProps({
    spriList:    { type: Array,  default: () => [] },
    kamarKosong: { type: Array,  default: () => [] },
    masterKelas: { type: Array,  default: () => [] },
    flash:       { type: Object, default: () => ({}) },
});

const alert   = ref({ show: false, type: 'success', title: '', message: '' });
const confirm = ref({ show: false, action: null, title: '', message: '', danger: false });
const showAlert   = (t, h, m) => { alert.value = { show: true, type: t, title: h, message: m }; };
const openConfirm = (cfg)      => { confirm.value = { show: true, ...cfg }; };
const doConfirm   = ()         => { confirm.value.action?.(); confirm.value.show = false; };

watch(() => props.flash, (f) => {
    if (f?.success) showAlert('success', 'Berhasil!', f.success);
    if (f?.error)   showAlert('error',   'Gagal!',    f.error);
}, { immediate: true, deep: true });

// ── Form buat surat baru ───────────────────────────────────
const showForm = ref(false);
const form = useForm({
    No_MR: '', No_Reg: '',
    Diagnosis: '', IndikasiRI: '', kebutuhan_bed: '',
    asal_ruang: '', Dokter: '', spesialis: '', Keterangan: '',
});
const submitForm = () => form.post(route('icu.spri_internal.store'), {
    onSuccess: () => { form.reset(); showForm.value = false; },
});

// ── Active tab ─────────────────────────────────────────────
const activeTab = ref('masuk');

const statusGroups = {
    masuk:    ['spri_dibuat', 'pending_admisi'],
    icu:      ['admisi_approved', 'pending_icu'],
    verif:    ['bed_booked', 'admisi_verified'],
    aktif:    ['di_icu'],
    ditolak:  ['ditolak'],
};

// Visible tabs berdasarkan role
const allSpriTabs = [
    { key:'masuk',   label:'Masuk / Admisi', roles: ['admin','admisi','petugas_ruang'] },
    { key:'icu',     label:'Proses ICU',     roles: ['admin','petugas_icu'] },
    { key:'verif',   label:'Verifikasi',     roles: ['admin','admisi'] },
    { key:'aktif',   label:'Di ICU',         roles: ['admin','admisi','petugas_icu'] },
    { key:'ditolak', label:'Ditolak',        roles: ['admin','admisi','petugas_icu','petugas_ruang'] },
];
const visibleTabs = computed(() => allSpriTabs.filter(t => {
    if (!authUser.value) return false;
    if (isAdmin.value)   return true;
    return t.roles.includes(authUser.value.role);
}));
const filtered = computed(() => {
    const keys = statusGroups[activeTab.value] ?? [];
    return keys.length ? props.spriList.filter(s => keys.includes(s.status)) : props.spriList;
});
const tabCounts = computed(() => {
    const c = {};
    for (const [tab, statuses] of Object.entries(statusGroups)) {
        c[tab] = props.spriList.filter(s => statuses.includes(s.status)).length;
    }
    return c;
});

// ── Bed selection ──────────────────────────────────────────
const bedPilihan   = ref({});
const alasanTolak  = ref({});
const showTolakForm = ref({});
const bedCocok     = (kebutuhan) => props.kamarKosong.filter(b => b.nama_kelas === kebutuhan);

// ── Actions ────────────────────────────────────────────────
const doApprove = (s) => openConfirm({
    title: 'Setujui Surat Permintaan?',
    message: `Surat Permintaan Rawat ICU untuk ${s.nama_pasien} akan disetujui dan diteruskan ke ICU.`,
    danger: false,
    action: () => router.post(route('icu.spri_internal.approve_admisi', s.id)),
});

const doTolakAdmisi = (s) => {
    const alasan = alasanTolak.value[s.id];
    if (!alasan?.trim()) { showAlert('warning', 'Isi Alasan', 'Alasan penolakan wajib diisi.'); return; }
    openConfirm({
        title: 'Tolak Surat Permintaan?',
        message: `Surat untuk ${s.nama_pasien} akan ditolak.`,
        danger: true,
        action: () => router.post(route('icu.spri_internal.tolak_admisi', s.id), { alasan_tolak: alasan }),
    });
};

const doBookingBed = (s) => {
    const kode = bedPilihan.value[s.id];
    if (!kode) { showAlert('warning', 'Pilih Bed', 'Pilih bed terlebih dahulu.'); return; }
    const namaBed = props.kamarKosong.find(b => b.Kode_Ruang === kode)?.nama_ruang ?? kode;
    openConfirm({
        title: 'Booking Bed?',
        message: `Booking bed ${namaBed} untuk ${s.nama_pasien}?`,
        danger: false,
        action: () => router.post(route('icu.spri_internal.booking_bed', s.id), { Kode_Ruang: kode }),
    });
};

const doTolakIcu = (s) => {
    const alasan = alasanTolak.value['icu_' + s.id];
    if (!alasan?.trim()) { showAlert('warning', 'Isi Alasan', 'Alasan penolakan wajib diisi.'); return; }
    openConfirm({
        title: 'Tolak Booking Bed?',
        message: `Permintaan bed untuk ${s.nama_pasien} akan ditolak.`,
        danger: true,
        action: () => router.post(route('icu.spri_internal.tolak_icu', s.id), { alasan_tolak: alasan }),
    });
};

const doVerifikasiAdmisi = (s) => openConfirm({
    title: 'Verifikasi Pasien Siap?',
    message: `Pasien ${s.nama_pasien} siap diantar ke ${s.nama_bed ?? s.allocated_bed_id}.`,
    danger: false,
    action: () => router.post(route('icu.spri_internal.verifikasi_admisi', s.id)),
});

const doKonfirmasiMasuk = (s) => openConfirm({
    title: 'Konfirmasi Pasien Masuk?',
    message: `Pasien ${s.nama_pasien} masuk ke ${s.nama_bed ?? s.allocated_bed_id}. Bed akan terisi.`,
    danger: false,
    action: () => router.post(route('icu.spri_internal.konfirmasi_masuk', s.id)),
});

// ── Helpers ────────────────────────────────────────────────
const gBg   = (g) => g === 'L' ? 'rgba(74,144,217,0.15)' : g === 'P' ? 'rgba(217,81,122,0.15)' : 'var(--bg-input)';
const gTxt  = (g) => g === 'L' ? '#4A90D9'               : g === 'P' ? '#D9517A'               : 'var(--text-secondary)';
const gIcon = (g) => g === 'L' ? '♂' : g === 'P' ? '♀' : '?';

const statusBadge = (status) => {
    const map = {
        spri_dibuat:     { bg: 'rgba(74,144,217,0.15)',  color: '#4A90D9'  },
        pending_admisi:  { bg: 'rgba(224,146,58,0.15)',  color: '#E0923A'  },
        admisi_approved: { bg: 'rgba(45,217,164,0.15)',  color: '#2DD9A4'  },
        pending_icu:     { bg: 'rgba(224,146,58,0.15)',  color: '#E0923A'  },
        bed_booked:      { bg: 'rgba(45,217,164,0.15)',  color: '#2DD9A4'  },
        admisi_verified: { bg: 'rgba(45,217,164,0.15)',  color: '#2DD9A4'  },
        di_icu:          { bg: 'rgba(61,219,138,0.15)',  color: '#3DDB8A'  },
        ditolak:         { bg: 'rgba(224,112,80,0.15)',  color: '#E07050'  },
    };
    return map[status] ?? { bg: 'var(--bg-input)', color: 'var(--text-secondary)' };
};
</script>

<template>
    <AppLayout :flash="flash" page-title="Surat Permintaan Rawat ICU — Internal">
        <AlertModal   v-bind="alert"   @close="alert.show = false"/>
        <ConfirmModal v-bind="confirm" @confirm="doConfirm" @cancel="confirm.show = false"/>

        <div class="p-4 sm:p-6 space-y-4" style="font-family:'Plus Jakarta Sans',sans-serif">

            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h1 class="font-bold text-lg" style="color:var(--text-primary)">Surat Permintaan Rawat ICU</h1>
                    <p class="text-sm mt-0.5" style="color:var(--text-secondary)">Jalur internal — pasien sudah ada di RS</p>
                </div>
                <button v-if="canBuatSpriInternal" @click="showForm = !showForm"
                    class="flex items-center gap-2 text-sm font-semibold px-4 py-2.5 rounded-xl self-start sm:self-auto transition-all"
                    :style="showForm
                        ? 'background:var(--bg-main); color:var(--text-secondary); border:1px solid var(--border-default)'
                        : 'background:#2DD9A4; color:#0D1A17'">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="showForm ? 'M6 18L18 6M6 6l12 12' : 'M12 4v16m8-8H4'"/>
                    </svg>
                    {{ showForm ? 'Tutup Form' : 'Buat Surat Permintaan' }}
                </button>
            </div>

            <!-- ── Form Buat Surat ── -->
            <Transition enter-active-class="transition-all duration-200 ease-out" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0">
                <form v-if="showForm && canBuatSpriInternal" @submit.prevent="submitForm" class="card-dark overflow-hidden">
                    <div class="px-5 py-3" style="background:linear-gradient(90deg,#4A90D9,#2DD9A4)">
                        <p class="text-sm font-bold" style="color:#fff">Surat Permintaan Rawat ICU — Pasien Internal</p>
                    </div>
                    <div class="p-5 space-y-4">
                        <!-- Referensi pasien -->
                        <p class="text-xs font-bold uppercase tracking-wide" style="color:var(--text-accent)">Referensi Pasien (dari sistem)</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">No. MR <span style="color:#E07050">*</span></label>
                                <input v-model="form.No_MR" required placeholder="Nomor Medical Record"
                                    class="w-full px-3 py-2.5 text-sm rounded-xl outline-none font-mono"
                                    :style="`border:1px solid ${form.errors.No_MR ? '#E07050' : 'var(--border-default)'}; background:var(--bg-surface); color:var(--text-primary)`"/>
                                <p v-if="form.errors.No_MR" class="text-xs mt-1" style="color:#E07050">{{ form.errors.No_MR }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">No. Registrasi <span style="color:#E07050">*</span></label>
                                <input v-model="form.No_Reg" required placeholder="No. Registrasi kunjungan"
                                    class="w-full px-3 py-2.5 text-sm rounded-xl outline-none font-mono"
                                    :style="`border:1px solid ${form.errors.No_Reg ? '#E07050' : 'var(--border-default)'}; background:var(--bg-surface); color:var(--text-primary)`"/>
                                <p v-if="form.errors.No_Reg" class="text-xs mt-1" style="color:#E07050">{{ form.errors.No_Reg }}</p>
                            </div>
                        </div>

                        <!-- Data klinis -->
                        <p class="text-xs font-bold uppercase tracking-wide pt-1" style="color:var(--text-accent)">Data Klinis & Kebutuhan</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">Diagnosis <span style="color:#E07050">*</span></label>
                                <input v-model="form.Diagnosis" required placeholder="Diagnosis"
                                    class="w-full px-3 py-2.5 text-sm rounded-xl outline-none"
                                    style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">Indikasi Rawat ICU <span style="color:#E07050">*</span></label>
                                <input v-model="form.IndikasiRI" required placeholder="Indikasi rawat ICU"
                                    class="w-full px-3 py-2.5 text-sm rounded-xl outline-none"
                                    style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">Kebutuhan Bed <span style="color:#E07050">*</span></label>
                                <select v-model="form.kebutuhan_bed" required
                                    class="w-full px-3 py-2.5 text-sm rounded-xl outline-none"
                                    style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)">
                                    <option value="" disabled>Pilih jenis bed</option>
                                    <option v-for="k in masterKelas" :key="k.kode" :value="k.nama">{{ k.nama }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">Asal Ruang</label>
                                <input v-model="form.asal_ruang" placeholder="Nama / kode ruang asal"
                                    class="w-full px-3 py-2.5 text-sm rounded-xl outline-none"
                                    style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">Dokter DPJP</label>
                                <input v-model="form.Dokter" placeholder="Nama dokter"
                                    class="w-full px-3 py-2.5 text-sm rounded-xl outline-none"
                                    style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary)">Keterangan</label>
                                <input v-model="form.Keterangan" placeholder="Informasi tambahan"
                                    class="w-full px-3 py-2.5 text-sm rounded-xl outline-none"
                                    style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                            </div>
                        </div>

                        <!-- Submit -->
                        <div class="flex flex-col gap-2 pt-1" style="border-top:1px solid var(--border-default)">
                            <!-- Validation errors -->
                            <div v-if="Object.keys(form.errors).length" class="p-3 rounded-xl text-xs space-y-1"
                                style="background:rgba(224,112,80,0.1); border:1px solid rgba(224,112,80,0.3)">
                                <p class="font-bold" style="color:#E07050">Periksa field berikut:</p>
                                <p v-for="(err, field) in form.errors" :key="field" style="color:#E07050">• {{ err }}</p>
                            </div>
                            <div class="flex gap-2">
                                <button type="submit" :disabled="form.processing"
                                    class="flex items-center gap-2 text-sm font-bold px-5 py-2.5 rounded-xl disabled:opacity-50"
                                    style="background:#2DD9A4; color:#0D1A17">
                                    <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    {{ form.processing ? 'Menyimpan...' : 'Kirim ke Admisi' }}
                                </button>
                                <button type="button" @click="showForm=false; form.reset()"
                                    class="px-5 py-2.5 text-sm rounded-xl"
                                    style="background:var(--bg-main); color:var(--text-secondary); border:1px solid var(--border-default)">Batal</button>
                            </div>
                        </div>
                    </div>
                </form>
            </Transition>

            <!-- ── Status Tabs ── -->
            <!-- ── Status Tabs ── -->
            <div class="flex gap-1 p-1 rounded-xl overflow-x-auto" style="background:var(--bg-main); border:1px solid var(--border-default)">
                <button v-for="tab in visibleTabs" :key="tab.key"
                    @click="activeTab = tab.key"
                    class="flex-shrink-0 px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap flex items-center gap-1.5 transition-all"
                    :style="activeTab === tab.key
                        ? 'background:var(--bg-surface); color:#2DD9A4; box-shadow:0 1px 4px rgba(45,217,164,.15)'
                        : 'color:var(--text-secondary)'">
                    {{ tab.label }}
                    <span v-if="tabCounts[tab.key]" class="text-xs px-1.5 rounded-full"
                        :style="activeTab === tab.key
                            ? 'background:rgba(45,217,164,0.2); color:#2DD9A4'
                            : 'background:var(--bg-input); color:var(--text-secondary)'">
                        {{ tabCounts[tab.key] }}
                    </span>
                </button>
            </div>

            <!-- ── List Surat ── -->
            <div v-if="filtered.length === 0" class="card-dark text-center py-14" style="color:var(--text-secondary)">
                <svg class="w-10 h-10 mx-auto mb-2 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <p class="text-sm">Tidak ada data di tab ini</p>
            </div>

            <div v-else class="space-y-3">
                <div v-for="s in filtered" :key="s.id" class="card-dark overflow-hidden">

                    <!-- Card header -->
                    <div class="flex items-center justify-between px-5 py-3.5" style="border-bottom:1px solid var(--border-default)">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-sm flex-shrink-0"
                                :style="`background:${gBg(s.jenis_kelamin)}; color:${gTxt(s.jenis_kelamin)}`">
                                {{ gIcon(s.jenis_kelamin) }}
                            </div>
                            <div>
                                <p class="text-sm font-bold" style="color:var(--text-primary)">{{ s.nama_pasien }}</p>
                                <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                                    <span class="text-xs font-mono" style="color:var(--text-secondary)">{{ s.No_MR }}</span>
                                    <span v-if="s.asal_ruang" class="text-xs" style="color:var(--text-secondary)">· {{ s.asal_ruang }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-full"
                                :style="`background:${statusBadge(s.status).bg}; color:${statusBadge(s.status).color}`">
                                {{ s.status_label }}
                            </span>
                            <span class="text-xs hidden sm:block" style="color:var(--text-secondary)">{{ s.created_at }}</span>
                        </div>
                    </div>

                    <!-- Card body -->
                    <div class="px-5 py-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <!-- Info klinis -->
                        <div class="sm:col-span-2 space-y-1.5">
                            <div class="flex gap-2 text-xs">
                                <span style="color:var(--text-secondary); min-width:110px">Diagnosis</span>
                                <span class="font-medium" style="color:var(--text-primary)">{{ s.Diagnosis }}</span>
                            </div>
                            <div class="flex gap-2 text-xs">
                                <span style="color:var(--text-secondary); min-width:110px">Indikasi RI</span>
                                <span class="font-medium" style="color:var(--text-primary)">{{ s.IndikasiRI }}</span>
                            </div>
                            <div class="flex gap-2 text-xs">
                                <span style="color:var(--text-secondary); min-width:110px">Kebutuhan Bed</span>
                                <span class="text-xs px-2 py-0.5 rounded-full font-semibold"
                                    style="background:rgba(45,217,164,0.12); color:#2DD9A4">{{ s.kebutuhan_bed }}</span>
                            </div>
                            <div v-if="s.Dokter" class="flex gap-2 text-xs">
                                <span style="color:var(--text-secondary); min-width:110px">Dokter DPJP</span>
                                <span style="color:var(--text-primary)">{{ s.Dokter }}</span>
                            </div>
                            <div v-if="s.nama_bed" class="flex gap-2 text-xs">
                                <span style="color:var(--text-secondary); min-width:110px">Bed Dipesan</span>
                                <span class="font-semibold" style="color:#2DD9A4">🏥 {{ s.nama_bed }}</span>
                            </div>
                            <div v-if="s.Keterangan" class="flex gap-2 text-xs">
                                <span style="color:var(--text-secondary); min-width:110px">Keterangan</span>
                                <span style="color:var(--text-secondary)">{{ s.Keterangan }}</span>
                            </div>
                            <div v-if="s.alasan_tolak" class="flex gap-2 text-xs">
                                <span style="color:var(--text-secondary); min-width:110px">Alasan Tolak</span>
                                <span style="color:#E07050">{{ s.alasan_tolak }}</span>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex flex-col gap-2">

                            <!-- Admisi: approve / tolak (pending_admisi) -->
                            <template v-if="s.status === 'pending_admisi'">
                                <template v-if="canApproveAdmisi">
                                    <button @click="doApprove(s)"
                                        class="text-xs font-bold py-2 rounded-xl"
                                        style="background:#2DD9A4; color:#0D1A17">
                                        ✓ Setujui Surat
                                    </button>
                                    <div v-if="!showTolakForm['adm_'+s.id]">
                                        <button @click="showTolakForm['adm_'+s.id] = true"
                                            class="w-full text-xs font-semibold py-2 rounded-xl"
                                            style="background:rgba(224,112,80,0.1); color:#E07050; border:1px solid rgba(224,112,80,0.25)">
                                            ✕ Tolak
                                        </button>
                                    </div>
                                    <div v-else class="space-y-1.5">
                                        <input v-model="alasanTolak[s.id]" placeholder="Alasan penolakan *"
                                            class="w-full text-xs px-3 py-2 rounded-xl outline-none"
                                            style="border:1px solid rgba(224,112,80,0.3); background:var(--bg-surface); color:var(--text-primary)"/>
                                        <button @click="doTolakAdmisi(s)"
                                            class="w-full text-xs font-bold py-1.5 rounded-xl"
                                            style="background:#E07050; color:#fff">Konfirmasi Tolak</button>
                                    </div>
                                </template>
                                <span v-else class="text-xs text-center py-2 rounded-xl"
                                    style="background:rgba(224,146,58,0.1); color:#E0923A; border:1px solid rgba(224,146,58,0.2)">
                                    Menunggu persetujuan Admisi
                                </span>
                            </template>

                            <!-- ICU: booking bed (pending_icu) -->
                            <template v-else-if="s.status === 'pending_icu'">
                                <template v-if="canBookingBedIcu">
                                    <select v-model="bedPilihan[s.id]"
                                        :disabled="bedCocok(s.kebutuhan_bed).length === 0"
                                        class="text-xs px-3 py-2 rounded-xl outline-none disabled:opacity-40"
                                        style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)">
                                        <option value="" disabled>-- Pilih Bed --</option>
                                        <option v-for="bed in bedCocok(s.kebutuhan_bed)" :key="bed.Kode_Ruang" :value="bed.Kode_Ruang">
                                            {{ bed.nama_ruang }}
                                        </option>
                                    </select>
                                    <button v-if="bedCocok(s.kebutuhan_bed).length > 0"
                                        @click="doBookingBed(s)"
                                        class="text-xs font-bold py-2 rounded-xl"
                                        style="background:#2DD9A4; color:#0D1A17">
                                        ✓ Booking Bed
                                    </button>
                                    <span v-else class="text-xs text-center py-2 rounded-xl"
                                        style="background:rgba(224,112,80,0.1); color:#E07050">
                                        Tidak ada bed sesuai
                                    </span>
                                    <!-- Tolak ICU -->
                                    <div v-if="!showTolakForm['icu_'+s.id]">
                                        <button @click="showTolakForm['icu_'+s.id] = true"
                                            class="w-full text-xs font-semibold py-2 rounded-xl"
                                            style="background:rgba(224,112,80,0.1); color:#E07050; border:1px solid rgba(224,112,80,0.25)">
                                            ✕ Tolak
                                        </button>
                                    </div>
                                    <div v-else class="space-y-1.5">
                                        <input v-model="alasanTolak['icu_'+s.id]" placeholder="Alasan penolakan *"
                                            class="w-full text-xs px-3 py-2 rounded-xl outline-none"
                                            style="border:1px solid rgba(224,112,80,0.3); background:var(--bg-surface); color:var(--text-primary)"/>
                                        <button @click="doTolakIcu(s)"
                                            class="w-full text-xs font-bold py-1.5 rounded-xl"
                                            style="background:#E07050; color:#fff">Konfirmasi Tolak</button>
                                    </div>
                                </template>
                                <span v-else class="text-xs text-center py-2 rounded-xl"
                                    style="background:rgba(224,146,58,0.1); color:#E0923A; border:1px solid rgba(224,146,58,0.2)">
                                    Menunggu booking ICU
                                </span>
                            </template>

                            <!-- Admisi: verifikasi akhir (bed_booked) -->
                            <template v-else-if="s.status === 'bed_booked'">
                                <div class="text-xs p-2 rounded-xl" style="background:rgba(45,217,164,0.08); border:1px solid rgba(45,217,164,0.2)">
                                    <p style="color:#2DD9A4" class="font-semibold">ICU Pesan Bed:</p>
                                    <p style="color:var(--text-primary)">🏥 {{ s.nama_bed }}</p>
                                </div>
                                <button v-if="canVerifikasiAdmisi" @click="doVerifikasiAdmisi(s)"
                                    class="text-xs font-bold py-2 rounded-xl"
                                    style="background:#2DD9A4; color:#0D1A17">
                                    ✓ Verifikasi — Pasien Siap
                                </button>
                                <span v-else class="text-xs text-center py-2 rounded-xl"
                                    style="background:rgba(224,146,58,0.1); color:#E0923A; border:1px solid rgba(224,146,58,0.2)">
                                    Menunggu verifikasi Admisi
                                </span>
                            </template>

                            <!-- ICU: konfirmasi masuk (admisi_verified) -->
                            <template v-else-if="s.status === 'admisi_verified'">
                                <div class="text-xs p-2 rounded-xl" style="background:rgba(45,217,164,0.08); border:1px solid rgba(45,217,164,0.2)">
                                    <p style="color:#2DD9A4" class="font-semibold">Siap diantar:</p>
                                    <p style="color:var(--text-primary)">🏥 {{ s.nama_bed }}</p>
                                </div>
                                <button v-if="canKonfirmasiMasuk" @click="doKonfirmasiMasuk(s)"
                                    class="text-xs font-bold py-2 rounded-xl"
                                    style="background:#3DDB8A; color:#0D1A17">
                                    ✓ Pasien Masuk ICU
                                </button>
                                <span v-else class="text-xs text-center py-2 rounded-xl"
                                    style="background:rgba(224,146,58,0.1); color:#E0923A; border:1px solid rgba(224,146,58,0.2)">
                                    Menunggu konfirmasi ICU
                                </span>
                            </template>

                            <!-- Di ICU -->
                            <template v-else-if="s.status === 'di_icu'">
                                <span class="text-xs text-center font-semibold py-2 rounded-xl"
                                    style="background:rgba(61,219,138,0.12); color:#3DDB8A">
                                    ✓ Pasien di ICU
                                </span>
                            </template>

                            <!-- Ditolak -->
                            <template v-else-if="s.status === 'ditolak'">
                                <span class="text-xs text-center py-2 rounded-xl"
                                    style="background:rgba(224,112,80,0.1); color:#E07050">
                                    Ditolak
                                </span>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
