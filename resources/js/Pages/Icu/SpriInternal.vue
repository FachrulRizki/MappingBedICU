<script setup>
import { ref, computed, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout    from '@/Layouts/AppLayout.vue';
import AlertModal   from '@/Components/AlertModal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import Icd10Search  from '@/Components/Icd10Search.vue';
import { useAuth }  from '@/composables/useAuth.js';

const {
    canBuatSpriInternal,
    canApproveAdmisi,
    canVerifikasiBedIcu,
    isAdmin,
    user: authUser,
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

// ── Tabs ───────────────────────────────────────────────────
const activeTab = ref('admisi');

// Alur: pending_admisi → pending_icu → bed_verified | ditolak
const statusGroups = {
    admisi:       ['pending_admisi'],
    icu:          ['pending_icu'],
    bed_verified: ['bed_verified'],
    ditolak:      ['ditolak'],
};

const allTabs = [
    { key: 'admisi',       label: 'Waiting Admisi',  roles: ['admin','admisi','petugas_ruang'] },
    { key: 'icu',          label: 'Verifikasi ICU',        roles: ['admin','petugas_icu'] },
    { key: 'bed_verified', label: 'Terverifikasi',      roles: ['admin','admisi','petugas_icu','petugas_ruang'] },
    { key: 'ditolak',      label: 'Ditolak',            roles: ['admin','admisi','petugas_icu','petugas_ruang'] },
];

const visibleTabs = computed(() => allTabs.filter(t =>
    isAdmin.value || t.roles.includes(authUser.value?.role)
));

const filtered = computed(() => {
    const keys = statusGroups[activeTab.value] ?? [];
    return keys.length ? props.spriList.filter(s => keys.includes(s.status)) : props.spriList;
});

const tabCounts = computed(() => {
    const c = {};
    for (const [tab, statuses] of Object.entries(statusGroups))
        c[tab] = props.spriList.filter(s => statuses.includes(s.status)).length;
    return c;
});

// ── Lookup pasien ──────────────────────────────────────────
const lookupLoading = ref(false);
const lookupResult  = ref(null);
const lookupError   = ref('');
const kunjunganList = ref([]);
const diagnosisExisting = ref('');

const doLookup = async (noMr) => {
    lookupResult.value      = null;
    lookupError.value       = '';
    kunjunganList.value     = [];
    form.No_Reg             = '';
    form.Dokter             = '';
    form.asal_ruang         = '';
    diagnosisExisting.value = '';
    if (!noMr || noMr.length < 3) return;

    lookupLoading.value = true;
    try {
        const res  = await fetch(
            route('icu.spri_internal.lookup_pasien') + '?No_MR=' + encodeURIComponent(noMr),
            { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }
        );
        const data = await res.json();
        lookupResult.value = data;
        if (data.found) {
            kunjunganList.value = data.kunjungans ?? [];
            if (kunjunganList.value.length === 1) {
                const k = kunjunganList.value[0];
                form.No_Reg             = k.No_Reg;
                form.Dokter             = k.Dokter     ?? '';
                form.asal_ruang         = k.asal_ruang ?? '';
                diagnosisExisting.value = k.Diagnosis  ?? '';
            }
            if (data.prefill) {
                if (!form.IndikasiRI) form.IndikasiRI = data.prefill.IndikasiRI ?? '';
                if (!form.asal_ruang) form.asal_ruang = data.prefill.asal_ruang ?? '';
                if (!form.Dokter)     form.Dokter     = data.prefill.Dokter     ?? '';
            }
        } else {
            lookupError.value = data.message ?? 'Pasien tidak ditemukan.';
        }
    } catch {
        lookupError.value = 'Gagal menghubungi server.';
    } finally {
        lookupLoading.value = false;
    }
};

// ── Form buat SPRI ─────────────────────────────────────────
const showForm = ref(false);
const form = useForm({
    No_MR: '', No_Reg: '', Diagnosis: '', IndikasiRI: '',
    asal_ruang: '', Dokter: '', spesialis: '', Keterangan: '',
});

watch(() => form.No_MR, (val) => {
    if (val && val.trim().length >= 3) doLookup(val.trim());
    else { lookupResult.value = null; lookupError.value = ''; kunjunganList.value = []; diagnosisExisting.value = ''; }
});

const onKunjunganChange = (noReg) => {
    const k = kunjunganList.value.find(x => x.No_Reg === noReg);
    if (k) {
        form.Dokter             = k.Dokter     ?? '';
        form.asal_ruang         = k.asal_ruang ?? '';
        diagnosisExisting.value = k.Diagnosis  ?? '';
    }
};

const submitForm = () => form.post(route('icu.spri_internal.store'), {
    onSuccess: () => {
        form.reset();
        lookupResult.value = null; lookupError.value = '';
        kunjunganList.value = []; diagnosisExisting.value = '';
        showForm.value = false;
    },
});

// ── Actions Admisi ─────────────────────────────────────────
const catatanAdmisiForm = ref({});
const showCatatanForm   = ref({});
const alasanTolak       = ref({});
const showTolakForm     = ref({});

const doApprove = (s) => openConfirm({
    title:   'Setujui Surat Permintaan?',
    message: `Surat untuk ${s.nama_pasien} disetujui dan diteruskan ke Petugas ICU.`,
    danger:  false,
    action:  () => router.post(
        route('icu.spri_internal.approve_admisi', s.id),
        { catatan_admisi: catatanAdmisiForm.value[s.id] ?? '' }
    ),
});

const doTolakAdmisi = (s) => {
    const alasan = alasanTolak.value['adm_' + s.id];
    if (!alasan?.trim()) { showAlert('warning', 'Isi Alasan', 'Alasan wajib diisi.'); return; }
    openConfirm({
        title: 'Tolak?', message: `Surat untuk ${s.nama_pasien} akan ditolak.`, danger: true,
        action: () => router.post(route('icu.spri_internal.tolak_admisi', s.id), { alasan_tolak: alasan }),
    });
};

// ── Actions ICU — Verifikasi Bed ──────────────────────────
// Hanya mencatat referensi bed, TIDAK mengupdate status bed di RS
const bedPilihan     = ref({});
const kondisiPilihan = ref({});
const bedCocok = (kondisi) => kondisi
    ? props.kamarKosong.filter(b => b.nama_kelas === kondisi)
    : props.kamarKosong;

const doVerifikasiBed = (s) => {
    const kode    = bedPilihan.value[s.id];
    const kondisi = kondisiPilihan.value[s.id];
    if (!kondisi) { showAlert('warning', 'Pilih Jenis ICU', 'Tentukan jenis ICU terlebih dahulu.'); return; }
    if (!kode)    { showAlert('warning', 'Pilih Bed',       'Pilih bed terlebih dahulu.'); return; }
    const namaBed = props.kamarKosong.find(b => b.Kode_Ruang === kode)?.nama_ruang ?? kode;
    openConfirm({
        title:   'Verifikasi Bed?',
        message: `Bed ${namaBed} (${kondisi}) diverifikasi untuk ${s.nama_pasien}. Admisi akan melihat status terverifikasi.`,
        danger:  false,
        action:  () => router.post(route('icu.spri_internal.verifikasi_bed', s.id), {
            Kode_Ruang:    kode,
            kebutuhan_bed: kondisi,
        }),
    });
};

const doTolakIcu = (s) => {
    const alasan = alasanTolak.value['icu_' + s.id];
    if (!alasan?.trim()) { showAlert('warning', 'Isi Alasan', 'Alasan wajib diisi.'); return; }
    openConfirm({
        title: 'Tolak?', message: `Permintaan bed untuk ${s.nama_pasien} akan ditolak.`, danger: true,
        action: () => router.post(route('icu.spri_internal.tolak_icu', s.id), { alasan_tolak: alasan }),
    });
};

// ── Helpers ────────────────────────────────────────────────
const gBg   = (g) => g === 'L' ? 'rgba(74,144,217,0.15)' : g === 'P' ? 'rgba(217,81,122,0.15)' : 'var(--bg-input)';
const gTxt  = (g) => g === 'L' ? '#4A90D9' : g === 'P' ? '#D9517A' : 'var(--text-secondary)';
const gIcon = (g) => g === 'L' ? '♂' : g === 'P' ? '♀' : '?';

const statusBadge = (status) => ({
    pending_admisi: { bg: 'rgba(224,146,58,0.15)',  color: '#E0923A' },
    pending_icu:    { bg: 'rgba(74,144,217,0.15)',  color: '#4A90D9' },
    bed_verified:   { bg: 'rgba(45,217,164,0.15)',  color: '#2DD9A4' },
    ditolak:        { bg: 'rgba(224,112,80,0.15)',  color: '#E07050' },
}[status] ?? { bg: 'var(--bg-input)', color: 'var(--text-secondary)' });
</script>

<template>
    <AppLayout :flash="flash" page-title="Surat Permintaan Rawat ICU — Internal">
        <AlertModal   v-bind="alert"   @close="alert.show = false"/>
        <ConfirmModal v-bind="confirm" @confirm="doConfirm" @cancel="confirm.show = false"/>

        <div class="p-4 sm:p-6 space-y-4" style="font-family:'Plus Jakarta Sans',sans-serif">

            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <h1 class="font-bold text-lg" style="color:var(--text-primary)">Surat Permintaan Rawat ICU</h1>
                <button v-if="canBuatSpriInternal" @click="showForm = !showForm"
                    class="flex items-center gap-2 text-sm font-semibold px-4 py-2.5 rounded-xl self-start sm:self-auto"
                    :style="showForm
                        ? 'background:var(--bg-main); color:var(--text-secondary); border:1px solid var(--border-default)'
                        : 'background:#2DD9A4; color:#0D1A17'">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            :d="showForm ? 'M6 18L18 6M6 6l12 12' : 'M12 4v16m8-8H4'"/>
                    </svg>
                    {{ showForm ? 'Tutup Form' : 'Buat Surat Permintaan' }}
                </button>
            </div>

            <!-- ── Form Buat SPRI ───────────────────────────────────── -->
            <Transition enter-active-class="transition-all duration-200 ease-out"
                        enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0">
                <form v-if="showForm && canBuatSpriInternal" @submit.prevent="submitForm" class="card-dark overflow-hidden">
                    <div class="px-5 py-3" style="background:linear-gradient(90deg,#4A90D9,#2DD9A4)">
                        <p class="text-sm font-bold text-white">Surat Permintaan Rawat ICU — Pasien Internal</p>
                    </div>
                    <div class="p-5 space-y-5">

                        <!-- 1. Verifikasi Pasien -->
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide mb-3" style="color:var(--text-accent)">1. Verifikasi Pasien</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">
                                        No. Medical Record <span style="color:#E07050">*</span>
                                    </label>
                                    <div class="relative">
                                        <input v-model="form.No_MR" required placeholder="Ketik No. MR..."
                                            class="w-full px-3 py-2.5 text-sm rounded-xl outline-none font-mono pr-8"
                                            :style="`border:1px solid ${form.errors.No_MR || lookupError ? '#E07050' : lookupResult?.found ? '#2DD9A4' : 'var(--border-default)'}; background:var(--bg-surface); color:var(--text-primary)`"/>
                                        <div class="absolute right-2.5 top-1/2 -translate-y-1/2">
                                            <svg v-if="lookupLoading" class="w-4 h-4 animate-spin" style="color:var(--text-secondary)" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                            </svg>
                                            <span v-else-if="lookupResult?.found" style="color:#2DD9A4" class="text-sm">✓</span>
                                            <span v-else-if="lookupError" style="color:#E07050" class="text-sm">✕</span>
                                        </div>
                                    </div>
                                    <p v-if="form.errors.No_MR" class="text-xs mt-1" style="color:#E07050">{{ form.errors.No_MR }}</p>
                                    <p v-else-if="lookupError" class="text-xs mt-1" style="color:#E07050">{{ lookupError }}</p>
                                    <p v-else-if="lookupResult?.found" class="text-xs mt-1 font-semibold" style="color:#2DD9A4">✓ {{ lookupResult.nama_pasien }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">
                                        No. Registrasi Kunjungan <span style="color:#E07050">*</span>
                                    </label>
                                    <select v-if="kunjunganList.length > 1" v-model="form.No_Reg"
                                        @change="onKunjunganChange(form.No_Reg)"
                                        class="w-full px-3 py-2.5 text-sm rounded-xl outline-none"
                                        style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)">
                                        <option value="" disabled>-- Pilih Kunjungan --</option>
                                        <option v-for="k in kunjunganList" :key="k.No_Reg" :value="k.No_Reg">
                                            {{ k.No_Reg }}{{ k.asal_ruang ? ' — ' + k.asal_ruang : '' }}
                                        </option>
                                    </select>
                                    <input v-else type="text" :value="form.No_Reg" readonly tabindex="-1"
                                        class="w-full px-3 py-2.5 text-sm rounded-xl outline-none opacity-70 cursor-not-allowed"
                                        :style="`border:1px solid ${form.errors.No_Reg ? '#E07050' : 'var(--border-default)'}; background:var(--bg-surface); color:var(--text-primary)`"
                                        :placeholder="!lookupResult?.found ? 'Isi No. MR dulu' : (kunjunganList.length === 0 ? 'Tidak ada kunjungan aktif' : '')"/>
                                    <p v-if="form.errors.No_Reg" class="text-xs mt-1" style="color:#E07050">{{ form.errors.No_Reg }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Data Klinis dari Rekam Medis (read-only) -->
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide mb-3" style="color:var(--text-accent)">2. Data dari Rekam Medis</p>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary)">Diagnosis (Rekam Medis)</label>
                                    <input type="text" :value="diagnosisExisting" readonly
                                        class="w-full px-3 py-2.5 text-sm rounded-xl outline-none opacity-70 cursor-not-allowed"
                                        style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"
                                        placeholder="Terisi otomatis"/>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary)">Asal Ruang</label>
                                    <input type="text" :value="form.asal_ruang" readonly
                                        class="w-full px-3 py-2.5 text-sm rounded-xl outline-none opacity-70 cursor-not-allowed"
                                        style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"
                                        placeholder="Terisi otomatis"/>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary)">Dokter DPJP</label>
                                    <input type="text" :value="form.Dokter" readonly
                                        class="w-full px-3 py-2.5 text-sm rounded-xl outline-none opacity-70 cursor-not-allowed"
                                        style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"
                                        placeholder="Terisi otomatis"/>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Data Klinis untuk ICU -->
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide mb-3" style="color:var(--text-accent)">3. Data Klinis untuk ICU</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">
                                        Diagnosis ICU <span style="color:#E07050">*</span>
                                    </label>
                                    <Icd10Search v-model="form.Diagnosis" placeholder="Cari kode / keterangan ICD10"
                                        :required="true" :has-error="!!form.errors.Diagnosis"/>
                                    <p v-if="form.errors.Diagnosis" class="text-xs mt-1" style="color:#E07050">{{ form.errors.Diagnosis }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">
                                        Indikasi Rawat ICU <span style="color:#E07050">*</span>
                                    </label>
                                    <input v-model="form.IndikasiRI" required placeholder="Alasan klinis butuh ICU"
                                        class="w-full px-3 py-2.5 text-sm rounded-xl outline-none"
                                        style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                                    <p v-if="form.errors.IndikasiRI" class="text-xs mt-1" style="color:#E07050">{{ form.errors.IndikasiRI }}</p>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary)">Keterangan Klinis</label>
                                    <textarea v-model="form.Keterangan" rows="2"
                                        placeholder="Kondisi terkini, riwayat penyakit, catatan penting untuk ICU"
                                        class="w-full px-3 py-2.5 text-sm rounded-xl outline-none resize-none"
                                        style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                                </div>
                            </div>
                        </div>

                        <!-- Submit -->
                        <div class="pt-1 flex items-center gap-2" style="border-top:1px solid var(--border-default)">
                            <button type="submit"
                                :disabled="form.processing || !lookupResult?.found || !form.No_Reg"
                                class="flex items-center gap-2 text-sm font-bold px-5 py-2.5 rounded-xl disabled:opacity-50"
                                style="background:#2DD9A4; color:#0D1A17">
                                <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                {{ form.processing ? 'Menyimpan...' : 'Kirim ke Admisi' }}
                            </button>
                            <button type="button"
                                @click="showForm=false; form.reset(); lookupResult=null; kunjunganList=[]; diagnosisExisting=''"
                                class="px-5 py-2.5 text-sm rounded-xl"
                                style="background:var(--bg-main); color:var(--text-secondary); border:1px solid var(--border-default)">
                                Batal
                            </button>
                        </div>
                    </div>
                </form>
            </Transition>

            <!-- Tabs -->
            <div class="flex gap-1 p-1 rounded-xl overflow-x-auto" style="background:var(--bg-main); border:1px solid var(--border-default)">
                <button v-for="tab in visibleTabs" :key="tab.key" @click="activeTab = tab.key"
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

            <!-- Empty state -->
            <div v-if="filtered.length === 0" class="card-dark text-center py-14" style="color:var(--text-secondary)">
                <svg class="w-10 h-10 mx-auto mb-2 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <p class="text-sm">Tidak ada data di tab ini</p>
            </div>

            <!-- List SPRI -->
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
                                <div class="flex items-center gap-2 mt-0.5">
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
                                <span style="color:var(--text-secondary); min-width:120px">Diagnosis ICU</span>
                                <span class="font-medium" style="color:var(--text-primary)">{{ s.Diagnosis }}</span>
                            </div>
                            <div class="flex gap-2 text-xs">
                                <span style="color:var(--text-secondary); min-width:120px">Indikasi RI</span>
                                <span class="font-medium" style="color:var(--text-primary)">{{ s.IndikasiRI }}</span>
                            </div>
                            <div v-if="s.kebutuhan_bed" class="flex gap-2 text-xs">
                                <span style="color:var(--text-secondary); min-width:120px">Jenis ICU</span>
                                <span class="text-xs px-2 py-0.5 rounded-full font-semibold"
                                    style="background:rgba(45,217,164,0.12); color:#2DD9A4">{{ s.kebutuhan_bed }}</span>
                            </div>
                            <div v-if="s.Dokter" class="flex gap-2 text-xs">
                                <span style="color:var(--text-secondary); min-width:120px">Dokter DPJP</span>
                                <span style="color:var(--text-primary)">{{ s.Dokter }}</span>
                            </div>
                            <div v-if="s.catatan_admisi" class="flex gap-2 text-xs">
                                <span style="color:var(--text-secondary); min-width:120px">Catatan Admisi</span>
                                <span class="font-medium" style="color:#4A90D9">{{ s.catatan_admisi }}</span>
                            </div>
                            <div v-if="s.nama_bed" class="flex gap-2 text-xs">
                                <span style="color:var(--text-secondary); min-width:120px">Bed Dikonfirmasi</span>
                                <span class="font-semibold" style="color:#2DD9A4">🏥 {{ s.nama_bed }}</span>
                            </div>
                            <div v-if="s.alasan_tolak" class="flex gap-2 text-xs">
                                <span style="color:var(--text-secondary); min-width:120px">Alasan Tolak</span>
                                <span style="color:#E07050">{{ s.alasan_tolak }}</span>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex flex-col gap-2">

                            <!-- pending_admisi: Admisi approve atau tolak -->
                            <template v-if="s.status === 'pending_admisi'">
                                <template v-if="canApproveAdmisi">
                                    <div v-if="!showCatatanForm[s.id]">
                                        <button @click="showCatatanForm[s.id] = true"
                                            class="w-full text-xs font-bold py-2 rounded-xl"
                                            style="background:#2DD9A4; color:#0D1A17">
                                            ✓ Setujui &amp; Isi Catatan
                                        </button>
                                    </div>
                                    <div v-else class="space-y-2">
                                        <p class="text-xs font-semibold" style="color:var(--text-primary)">Catatan Jaminan / Admisi:</p>
                                        <textarea v-model="catatanAdmisiForm[s.id]"
                                            placeholder="Contoh: BPJS aktif kelas I. No. peserta 000..." rows="3"
                                            class="w-full text-xs px-3 py-2 rounded-xl outline-none resize-none"
                                            style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                                        <div class="flex gap-1.5">
                                            <button @click="doApprove(s)" class="flex-1 text-xs font-bold py-2 rounded-xl"
                                                style="background:#2DD9A4; color:#0D1A17">✓ Setujui</button>
                                            <button @click="showCatatanForm[s.id] = false" class="text-xs px-3 py-2 rounded-xl"
                                                style="background:var(--bg-input); color:var(--text-secondary)">Batal</button>
                                        </div>
                                    </div>
                                    <div v-if="!showTolakForm['adm_'+s.id]">
                                        <button @click="showTolakForm['adm_'+s.id] = true"
                                            class="w-full text-xs font-semibold py-2 rounded-xl"
                                            style="background:rgba(224,112,80,0.1); color:#E07050; border:1px solid rgba(224,112,80,0.25)">
                                            ✕ Tolak
                                        </button>
                                    </div>
                                    <div v-else class="space-y-1.5">
                                        <input v-model="alasanTolak['adm_'+s.id]" placeholder="Alasan *"
                                            class="w-full text-xs px-3 py-2 rounded-xl outline-none"
                                            style="border:1px solid rgba(224,112,80,0.3); background:var(--bg-surface); color:var(--text-primary)"/>
                                        <button @click="doTolakAdmisi(s)" class="w-full text-xs font-bold py-1.5 rounded-xl"
                                            style="background:#E07050; color:#fff">Konfirmasi Tolak</button>
                                    </div>
                                </template>
                                <span v-else class="text-xs text-center py-2 rounded-xl block"
                                    style="background:rgba(224,146,58,0.1); color:#E0923A; border:1px solid rgba(224,146,58,0.2)">
                                    Menunggu Admisi
                                </span>
                            </template>

                            <!-- pending_icu: ICU verifikasi bed atau tolak -->
                            <template v-else-if="s.status === 'pending_icu'">
                                <template v-if="canVerifikasiBedIcu">
                                    <div v-if="s.catatan_admisi" class="p-2 rounded-xl text-xs"
                                        style="background:rgba(74,144,217,0.08); border:1px solid rgba(74,144,217,0.2)">
                                        <p class="font-semibold" style="color:#4A90D9">📋 Catatan Admisi:</p>
                                        <p style="color:var(--text-primary)">{{ s.catatan_admisi }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs font-semibold mb-1" style="color:var(--text-primary)">1. Jenis ICU:</p>
                                        <select v-model="kondisiPilihan[s.id]"
                                            class="w-full text-xs px-3 py-2 rounded-xl outline-none"
                                            style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)">
                                            <option value="" disabled>-- Pilih Jenis ICU --</option>
                                            <option v-for="k in masterKelas" :key="k.kode" :value="k.nama">{{ k.nama }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <p class="text-xs font-semibold mb-1" style="color:var(--text-primary)">2. Pilih Bed:</p>
                                        <select v-model="bedPilihan[s.id]"
                                            :disabled="props.kamarKosong.length === 0"
                                            class="w-full text-xs px-3 py-2 rounded-xl outline-none disabled:opacity-40"
                                            style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)">
                                            <option value="" disabled>-- Pilih Bed --</option>
                                            <option v-for="bed in bedCocok(kondisiPilihan[s.id])" :key="bed.Kode_Ruang" :value="bed.Kode_Ruang">
                                                {{ bed.nama_ruang }}<template v-if="!kondisiPilihan[s.id]"> ({{ bed.nama_kelas }})</template>
                                            </option>
                                        </select>
                                    </div>
                                    <p v-if="props.kamarKosong.length === 0" class="text-xs"
                                        style="color:#E0923A; background:rgba(224,146,58,0.1); padding:8px; border-radius:8px">
                                        ⚠ Tidak ada bed kosong saat ini
                                    </p>
                                    <button @click="doVerifikasiBed(s)"
                                        :disabled="props.kamarKosong.length === 0"
                                        class="text-xs font-bold py-2 rounded-xl disabled:opacity-40"
                                        style="background:#2DD9A4; color:#0D1A17">
                                        ✓ Verifikasi Bed
                                    </button>
                                    <div v-if="!showTolakForm['icu_'+s.id]">
                                        <button @click="showTolakForm['icu_'+s.id] = true"
                                            class="w-full text-xs font-semibold py-2 rounded-xl"
                                            style="background:rgba(224,112,80,0.1); color:#E07050; border:1px solid rgba(224,112,80,0.25)">
                                            ✕ Tolak
                                        </button>
                                    </div>
                                    <div v-else class="space-y-1.5">
                                        <input v-model="alasanTolak['icu_'+s.id]" placeholder="Alasan *"
                                            class="w-full text-xs px-3 py-2 rounded-xl outline-none"
                                            style="border:1px solid rgba(224,112,80,0.3); background:var(--bg-surface); color:var(--text-primary)"/>
                                        <button @click="doTolakIcu(s)" class="w-full text-xs font-bold py-1.5 rounded-xl"
                                            style="background:#E07050; color:#fff">Konfirmasi Tolak</button>
                                    </div>
                                </template>
                                <span v-else class="text-xs text-center py-2 rounded-xl block"
                                    style="background:rgba(74,144,217,0.1); color:#4A90D9; border:1px solid rgba(74,144,217,0.2)">
                                    Menunggu Petugas ICU
                                </span>
                            </template>

                            <!-- bed_verified: tampilkan info untuk admisi -->
                            <template v-else-if="s.status === 'bed_verified'">
                                <div class="p-2.5 rounded-xl text-xs space-y-1.5"
                                    style="background:rgba(45,217,164,0.08); border:1px solid rgba(45,217,164,0.2)">
                                    <p class="font-semibold" style="color:#2DD9A4">✓ Bed Terverifikasi ICU</p>
                                    <p v-if="s.nama_bed" style="color:var(--text-primary)">🏥 {{ s.nama_bed }}</p>
                                    <p v-if="s.kebutuhan_bed" style="color:var(--text-secondary)">{{ s.kebutuhan_bed }}</p>
                                    <div class="pt-1" style="border-top:1px solid rgba(45,217,164,0.2)">
                                        <p style="color:var(--text-secondary)">Verifikasi: {{ s.verified_by ?? '-' }}</p>
                                    </div>
                                </div>
                            </template>

            <!-- ditolak -->
                            <template v-else-if="s.status === 'ditolak'">
                                <span class="text-xs text-center py-2 rounded-xl block"
                                    style="background:rgba(224,112,80,0.1); color:#E07050">✕ Ditolak</span>
                                <p v-if="s.alasan_tolak" class="text-xs" style="color:#E07050">{{ s.alasan_tolak }}</p>
                            </template>

                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
