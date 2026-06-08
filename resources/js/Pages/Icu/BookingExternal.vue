<script setup>
import { ref, computed, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout    from '@/Layouts/AppLayout.vue';
import AlertModal   from '@/Components/AlertModal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { useAuth }  from '@/composables/useAuth.js';

const {
    canBuatBookingExternal, canKonfirmasiIcu, canValidasiAdmisi,
    canVerifikasiBed, canLinkPasien, canKonfirmasiMasuk, canPulangkan,
    isAdmin, user: authUser,
} = useAuth();

const props = defineProps({
    bookings:    { type: Array,  default: () => [] },
    kamarKosong: { type: Array,  default: () => [] },
    masterKelas: { type: Array,  default: () => [] },
    flash:       { type: Object, default: () => ({}) },
});

const alert   = ref({ show: false, type: 'success', title: '', message: '' });
const confirm = ref({ show: false, action: null, title: '', message: '', danger: false });
const showAlert   = (t, h, m) => { alert.value = { show: true, type: t, title: h, message: m }; };
const openConfirm = (cfg) => { confirm.value = { show: true, ...cfg }; };
const doConfirm   = () => { confirm.value.action?.(); confirm.value.show = false; };

watch(() => props.flash, (f) => {
    if (f?.success) showAlert('success', 'Berhasil!', f.success);
    if (f?.error)   showAlert('error',   'Gagal!',    f.error);
}, { immediate: true, deep: true });

// ── Active tab ─────────────────────────────────────────────
const activeTab = ref('daftar');

// ── Form buat booking baru ─────────────────────────────────
const showForm = ref(false);
const form = useForm({
    nama_pasien: '', jenis_kelamin: '', no_identitas: '',
    asal_rujukan: '', no_telp_keluarga: '',
    diagnosa: '', rencana_tindakan: '', kebutuhan_bed: '', keterangan: '',
});
const submitForm = () => form.post(route('icu.booking_external.store'), {
    onSuccess: () => { form.reset(); showForm.value = false; },
});

// ── Bed selection per booking ──────────────────────────────
const bedPilihan = ref({});
const bedCocok   = (kebutuhan) => props.kamarKosong.filter(b => b.nama_kelas === kebutuhan);

// ── Actions ────────────────────────────────────────────────
const doKonfirmasiIcu = (b) => {
    const kode = bedPilihan.value[b.id];
    if (!kode) { showAlert('warning', 'Pilih Bed', 'Pilih bed terlebih dahulu.'); return; }
    const namaBed = props.kamarKosong.find(x => x.Kode_Ruang === kode)?.nama_ruang ?? kode;
    openConfirm({
        title: 'Konfirmasi Bed?',
        message: `Konfirmasi bed ${namaBed} untuk ${b.nama_pasien}?`,
        danger: false,
        action: () => router.post(route('icu.booking_external.konfirmasi_icu', b.id), { Kode_Ruang: kode }),
    });
};

const alasanTolak   = ref({});
const showTolakForm = ref({});

const doTolakIcu = (b) => {
    const alasan = alasanTolak.value[b.id];
    if (!alasan?.trim()) { showAlert('warning', 'Isi Alasan', 'Alasan penolakan harus diisi.'); return; }
    openConfirm({
        title: 'Tolak Booking?',
        message: `Booking ${b.nama_pasien} akan ditolak.`,
        danger: true,
        action: () => router.post(route('icu.booking_external.tolak_icu', b.id), { alasan_tolak: alasan }),
    });
};

const doValidasiAdmisi = (b) => openConfirm({
    title: 'Validasi Booking?',
    message: `Konfirmasi ICU untuk ${b.nama_pasien} divalidasi. Pasien dapat diarahkan ke RS.`,
    danger: false,
    action: () => router.post(route('icu.booking_external.validasi_admisi', b.id)),
});

// Link pasien tiba
const linkForm = ref({});
const showLinkForm = ref({});
const doLinkPasien = (b) => {
    const d = linkForm.value[b.id];
    if (!d?.No_MR || !d?.No_Reg) { showAlert('warning', 'Data Kurang', 'No. MR dan No. Registrasi wajib diisi.'); return; }
    openConfirm({
        title: 'Link Pasien?',
        message: `Hubungkan booking ${b.nama_pasien} ke No_MR ${d.No_MR}?`,
        danger: false,
        action: () => router.post(route('icu.booking_external.link_pasien', b.id), { No_MR: d.No_MR, No_Reg: d.No_Reg }),
    });
};

const doVerifikasiBed = (b) => openConfirm({
    title: 'Verifikasi Bed?',
    message: `Pasien ${b.nama_pasien} siap diantar ke bed ${b.nama_bed ?? b.allocated_bed_id}.`,
    danger: false,
    action: () => router.post(route('icu.booking_external.verifikasi_bed', b.id)),
});

const doKonfirmasiMasuk = (b) => openConfirm({
    title: 'Konfirmasi Pasien Masuk?',
    message: `Pasien ${b.nama_pasien} masuk ke ${b.nama_bed ?? b.allocated_bed_id}. Bed akan terisi.`,
    danger: false,
    action: () => router.post(route('icu.booking_external.konfirmasi_masuk', b.id)),
});

// ── Filter tabs ────────────────────────────────────────────
const statusGroups = {
    daftar:    ['booking_request', 'pending_icu'],
    icu:       ['bed_confirmed'],
    admisi:    ['admisi_validated', 'pasien_tiba'],
    verifikasi:['bed_verified'],
    aktif:     ['di_icu'],
    ditolak:   ['ditolak'],
};

// Tab visibility per role — hanya tampilkan tab yang relevan
const allTabs = [
    { key:'daftar',     label:'Booking Masuk',    roles: ['admin','admisi','petugas_icu'] },
    { key:'icu',        label:'Konfirmasi ICU',   roles: ['admin','petugas_icu'] },
    { key:'admisi',     label:'Validasi Admisi',  roles: ['admin','admisi'] },
    { key:'verifikasi', label:'Verifikasi Bed',   roles: ['admin','admisi'] },
    { key:'aktif',      label:'Di ICU',           roles: ['admin','admisi','petugas_icu'] },
    { key:'ditolak',    label:'Ditolak',          roles: ['admin','admisi','petugas_icu'] },
];
const visibleTabs = computed(() => allTabs.filter(t => {
    if (!authUser.value) return false;
    if (isAdmin.value)   return true;
    return t.roles.includes(authUser.value.role);
}));
const filtered = computed(() => {
    const keys = statusGroups[activeTab.value] ?? [];
    return keys.length ? props.bookings.filter(b => keys.includes(b.status)) : props.bookings;
});

const tabCounts = computed(() => {
    const counts = {};
    for (const [tab, statuses] of Object.entries(statusGroups)) {
        counts[tab] = props.bookings.filter(b => statuses.includes(b.status)).length;
    }
    return counts;
});

// ── Helpers ────────────────────────────────────────────────
const gBg  = (g) => g === 'L' ? 'rgba(74,144,217,0.15)' : g === 'P' ? 'rgba(217,81,122,0.15)' : 'var(--bg-input)';
const gTxt = (g) => g === 'L' ? '#4A90D9'                : g === 'P' ? '#D9517A'                : 'var(--text-secondary)';
const gIcon = (g) => g === 'L' ? '♂' : g === 'P' ? '♀' : '?';

const statusBadge = (status) => {
    const map = {
        booking_request:  { bg: 'rgba(74,144,217,0.15)',  color: '#4A90D9'  },
        pending_icu:      { bg: 'rgba(224,146,58,0.15)',  color: '#E0923A'  },
        bed_confirmed:    { bg: 'rgba(45,217,164,0.15)',  color: '#2DD9A4'  },
        admisi_validated: { bg: 'rgba(45,217,164,0.15)',  color: '#2DD9A4'  },
        pasien_tiba:      { bg: 'rgba(224,146,58,0.15)',  color: '#E0923A'  },
        bed_verified:     { bg: 'rgba(45,217,164,0.15)',  color: '#2DD9A4'  },
        di_icu:           { bg: 'rgba(61,219,138,0.15)',  color: '#3DDB8A'  },
        ditolak:          { bg: 'rgba(224,112,80,0.15)',  color: '#E07050'  },
    };
    return map[status] ?? { bg: 'var(--bg-input)', color: 'var(--text-secondary)' };
};
</script>

<template>
    <AppLayout :flash="flash" page-title="Booking External ICU">
        <AlertModal   v-bind="alert"   @close="alert.show = false"/>
        <ConfirmModal v-bind="confirm" @confirm="doConfirm" @cancel="confirm.show = false"/>

        <div class="p-4 sm:p-6 space-y-4" style="font-family:'Plus Jakarta Sans',sans-serif">

            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h1 class="font-bold text-lg" style="color:var(--text-primary)">Booking ICU — Pasien Eksternal</h1>
                    <p class="text-sm mt-0.5" style="color:var(--text-secondary)">Kelola booking pasien dari luar RS</p>
                </div>
                <button v-if="canBuatBookingExternal" @click="showForm = !showForm"
                    class="flex items-center gap-2 text-sm font-semibold px-4 py-2.5 rounded-xl self-start sm:self-auto transition-all"
                    :style="showForm
                        ? 'background:var(--bg-main); color:var(--text-secondary); border:1px solid var(--border-default)'
                        : 'background:#2DD9A4; color:#0D1A17'">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="showForm ? 'M6 18L18 6M6 6l12 12' : 'M12 4v16m8-8H4'"/>
                    </svg>
                    {{ showForm ? 'Tutup Form' : 'Booking Baru' }}
                </button>
            </div>

            <!-- ── Form Booking Baru ── -->
            <Transition enter-active-class="transition-all duration-200 ease-out" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0">
                <form v-if="showForm && canBuatBookingExternal" @submit.prevent="submitForm" class="card-dark overflow-hidden">
                    <div class="px-5 py-3" style="background:linear-gradient(90deg,#2DD9A4,#1A9E8F)">
                        <p class="text-sm font-bold" style="color:#0D1A17">Form Booking ICU — Pasien Eksternal</p>
                    </div>
                    <div class="p-5 space-y-4">
                        <!-- Identitas pasien -->
                        <p class="text-xs font-bold uppercase tracking-wide" style="color:var(--text-accent)">Identitas Pasien</p>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">Nama Pasien <span style="color:#E07050">*</span></label>
                                <input v-model="form.nama_pasien" required placeholder="Nama lengkap pasien"
                                    class="w-full px-3 py-2.5 text-sm rounded-xl outline-none"
                                    :style="`border:1px solid ${form.errors.nama_pasien ? '#E07050' : 'var(--border-default)'}; background:var(--bg-surface); color:var(--text-primary)`"/>
                                <p v-if="form.errors.nama_pasien" class="text-xs mt-1" style="color:#E07050">{{ form.errors.nama_pasien }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">Jenis Kelamin <span style="color:#E07050">*</span></label>
                                <div class="flex gap-2">
                                    <button type="button" @click="form.jenis_kelamin='L'"
                                        class="flex-1 py-2.5 rounded-xl text-sm font-semibold"
                                        :style="form.jenis_kelamin==='L' ? 'background:#4A90D9;color:#fff;border:2px solid #4A90D9' : 'background:var(--bg-surface);color:var(--text-secondary);border:2px solid var(--border-default)'">♂ Pria</button>
                                    <button type="button" @click="form.jenis_kelamin='P'"
                                        class="flex-1 py-2.5 rounded-xl text-sm font-semibold"
                                        :style="form.jenis_kelamin==='P' ? 'background:#D9517A;color:#fff;border:2px solid #D9517A' : 'background:var(--bg-surface);color:var(--text-secondary);border:2px solid var(--border-default)'">♀ Wanita</button>
                                </div>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">No. Identitas / NIK</label>
                                <input v-model="form.no_identitas" placeholder="NIK / identitas"
                                    class="w-full px-3 py-2.5 text-sm rounded-xl outline-none font-mono"
                                    style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">Asal Rujukan</label>
                                <input v-model="form.asal_rujukan" placeholder="RS / klinik pengirim"
                                    class="w-full px-3 py-2.5 text-sm rounded-xl outline-none"
                                    style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">No. Telp Keluarga</label>
                                <input v-model="form.no_telp_keluarga" placeholder="08xx-xxxx-xxxx"
                                    class="w-full px-3 py-2.5 text-sm rounded-xl outline-none font-mono"
                                    style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                            </div>
                        </div>

                        <!-- Data klinis -->
                        <p class="text-xs font-bold uppercase tracking-wide pt-2" style="color:var(--text-accent)">Data Klinis</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">Diagnosa <span style="color:#E07050">*</span></label>
                                <input v-model="form.diagnosa" required placeholder="Diagnosa masuk"
                                    class="w-full px-3 py-2.5 text-sm rounded-xl outline-none"
                                    style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">Rencana Tindakan <span style="color:#E07050">*</span></label>
                                <input v-model="form.rencana_tindakan" required placeholder="Rencana tindakan ICU"
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
                                <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary)">Keterangan</label>
                                <input v-model="form.keterangan" placeholder="Informasi tambahan"
                                    class="w-full px-3 py-2.5 text-sm rounded-xl outline-none"
                                    style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                            </div>
                        </div>

                        <!-- Submit -->
                        <div class="flex gap-2 pt-1" style="border-top:1px solid var(--border-default)">
                            <!-- Tampilkan semua validation errors -->
                            <div v-if="Object.keys(form.errors).length" class="w-full mb-2 p-3 rounded-xl text-xs space-y-1"
                                style="background:rgba(224,112,80,0.1); border:1px solid rgba(224,112,80,0.3)">
                                <p class="font-bold" style="color:#E07050">Periksa field berikut:</p>
                                <p v-for="(err, field) in form.errors" :key="field" style="color:#E07050">• {{ err }}</p>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" :disabled="form.processing || !form.jenis_kelamin"
                                class="flex items-center gap-2 text-sm font-bold px-5 py-2.5 rounded-xl disabled:opacity-50"
                                style="background:#2DD9A4; color:#0D1A17">
                                <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                {{ form.processing ? 'Menyimpan...' : 'Buat Booking' }}
                            </button>
                            <button type="button" @click="showForm=false; form.reset()"
                                class="px-5 py-2.5 text-sm rounded-xl"
                                style="background:var(--bg-main); color:var(--text-secondary); border:1px solid var(--border-default)">Batal</button>
                            <p v-if="!form.jenis_kelamin" class="text-xs self-center ml-1" style="color:#E0923A">⚠ Pilih jenis kelamin</p>
                        </div>
                    </div>
                </form>
            </Transition>

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

            <!-- ── List Booking ── -->
            <div v-if="filtered.length === 0" class="card-dark text-center py-14" style="color:var(--text-secondary)">
                <svg class="w-10 h-10 mx-auto mb-2 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <p class="text-sm">Tidak ada data di tab ini</p>
            </div>

            <div v-else class="space-y-3">
                <div v-for="b in filtered" :key="b.id" class="card-dark overflow-hidden">
                    <!-- Card header -->
                    <div class="flex items-center justify-between px-5 py-3.5" style="border-bottom:1px solid var(--border-default)">
                        <div class="flex items-center gap-3">
                            <!-- Gender badge -->
                            <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-sm flex-shrink-0"
                                :style="`background:${gBg(b.jenis_kelamin)}; color:${gTxt(b.jenis_kelamin)}`">
                                {{ gIcon(b.jenis_kelamin) }}
                            </div>
                            <div>
                                <p class="text-sm font-bold" style="color:var(--text-primary)">{{ b.nama_pasien }}</p>
                                <div class="flex items-center gap-2 mt-0.5 flex-wrap">
                                    <span class="text-xs font-mono" style="color:var(--text-secondary)">{{ b.no_identitas ?? 'No NIK' }}</span>
                                    <span v-if="b.asal_rujukan" class="text-xs" style="color:var(--text-secondary)">· {{ b.asal_rujukan }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-full"
                                :style="`background:${statusBadge(b.status).bg}; color:${statusBadge(b.status).color}`">
                                {{ b.status_label }}
                            </span>
                            <span class="text-xs" style="color:var(--text-secondary)">{{ b.created_at }}</span>
                        </div>
                    </div>

                    <!-- Card body -->
                    <div class="px-5 py-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <!-- Klinis info -->
                        <div class="sm:col-span-2 space-y-1.5">
                            <div class="flex gap-2 text-xs">
                                <span style="color:var(--text-secondary); min-width:100px">Diagnosa</span>
                                <span class="font-medium" style="color:var(--text-primary)">{{ b.diagnosa }}</span>
                            </div>
                            <div class="flex gap-2 text-xs">
                                <span style="color:var(--text-secondary); min-width:100px">Rencana Tindakan</span>
                                <span class="font-medium" style="color:var(--text-primary)">{{ b.rencana_tindakan }}</span>
                            </div>
                            <div class="flex gap-2 text-xs">
                                <span style="color:var(--text-secondary); min-width:100px">Kebutuhan Bed</span>
                                <span class="badge text-xs px-2 py-0.5 rounded-full"
                                    style="background:rgba(45,217,164,0.12); color:#2DD9A4">{{ b.kebutuhan_bed }}</span>
                            </div>
                            <div v-if="b.nama_bed" class="flex gap-2 text-xs">
                                <span style="color:var(--text-secondary); min-width:100px">Bed Dialokasikan</span>
                                <span class="font-semibold" style="color:#2DD9A4">🏥 {{ b.nama_bed }}</span>
                            </div>
                            <div v-if="b.No_MR" class="flex gap-2 text-xs">
                                <span style="color:var(--text-secondary); min-width:100px">No. MR</span>
                                <span class="font-mono font-semibold" style="color:var(--text-primary)">{{ b.No_MR }}</span>
                            </div>
                            <div v-if="b.keterangan" class="flex gap-2 text-xs">
                                <span style="color:var(--text-secondary); min-width:100px">Keterangan</span>
                                <span style="color:var(--text-secondary)">{{ b.keterangan }}</span>
                            </div>
                            <div v-if="b.alasan_tolak" class="flex gap-2 text-xs">
                                <span style="color:var(--text-secondary); min-width:100px">Alasan Tolak</span>
                                <span style="color:#E07050">{{ b.alasan_tolak }}</span>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex flex-col gap-2">

                            <!-- ICU: konfirmasi/tolak bed (pending_icu) -->
                            <template v-if="b.status === 'pending_icu'">
                                <template v-if="canKonfirmasiIcu">
                                <select v-model="bedPilihan[b.id]"
                                    :disabled="bedCocok(b.kebutuhan_bed).length === 0"
                                    class="text-xs px-3 py-2 rounded-xl outline-none disabled:opacity-40"
                                    style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)">
                                    <option value="" disabled>-- Pilih Bed --</option>
                                    <option v-for="bed in bedCocok(b.kebutuhan_bed)" :key="bed.Kode_Ruang" :value="bed.Kode_Ruang">
                                        {{ bed.nama_ruang }}
                                    </option>
                                </select>
                                <button v-if="bedCocok(b.kebutuhan_bed).length > 0"
                                    @click="doKonfirmasiIcu(b)"
                                    class="text-xs font-bold py-2 rounded-xl"
                                    style="background:#2DD9A4; color:#0D1A17">
                                    ✓ Konfirmasi Ada Bed
                                </button>
                                <span v-else class="text-xs text-center py-2 rounded-xl" style="background:rgba(224,112,80,0.1); color:#E07050">
                                    Tidak ada bed sesuai
                                </span>
                                <div v-if="!showTolakForm[b.id]">
                                    <button @click="showTolakForm[b.id] = true"
                                        class="w-full text-xs font-semibold py-2 rounded-xl"
                                        style="background:rgba(224,112,80,0.1); color:#E07050; border:1px solid rgba(224,112,80,0.25)">
                                        ✕ Tolak
                                    </button>
                                </div>
                                <div v-else class="space-y-1.5">
                                    <input v-model="alasanTolak[b.id]" placeholder="Alasan penolakan *"
                                        class="w-full text-xs px-3 py-2 rounded-xl outline-none"
                                        style="border:1px solid rgba(224,112,80,0.3); background:var(--bg-surface); color:var(--text-primary)"/>
                                    <button @click="doTolakIcu(b)"
                                        class="w-full text-xs font-bold py-1.5 rounded-xl"
                                        style="background:#E07050; color:#fff">Konfirmasi Tolak</button>
                                </div>
                                </template>
                                <span v-else class="text-xs text-center py-2 rounded-xl" style="background:var(--bg-input); color:var(--text-secondary)">
                                    Menunggu konfirmasi ICU
                                </span>
                            </template>

                            <!-- Admisi: validasi konfirmasi ICU (bed_confirmed) -->
                            <template v-else-if="b.status === 'bed_confirmed'">
                                <div class="text-xs p-2 rounded-xl" style="background:rgba(45,217,164,0.08); border:1px solid rgba(45,217,164,0.2)">
                                    <p style="color:#2DD9A4" class="font-semibold mb-1">ICU Konfirmasi:</p>
                                    <p style="color:var(--text-primary)">🏥 {{ b.nama_bed }}</p>
                                </div>
                                <button v-if="canValidasiAdmisi" @click="doValidasiAdmisi(b)"
                                    class="text-xs font-bold py-2 rounded-xl"
                                    style="background:#2DD9A4; color:#0D1A17">
                                    ✓ Validasi & Kirim Pasien
                                </button>
                                <span v-else class="text-xs text-center py-1.5 rounded-xl" style="background:var(--bg-input); color:var(--text-secondary)">
                                    Menunggu validasi Admisi
                                </span>
                            </template>

                            <!-- Admisi: link No_MR saat pasien tiba (admisi_validated) -->
                            <template v-else-if="b.status === 'admisi_validated'">
                                <p class="text-xs font-semibold" style="color:#E0923A">⏳ Pasien dalam perjalanan...</p>
                                <template v-if="canLinkPasien">
                                <div v-if="!showLinkForm[b.id]">
                                    <button @click="showLinkForm[b.id] = true"
                                        class="w-full text-xs font-bold py-2 rounded-xl"
                                        style="background:#E0923A; color:#fff">
                                        Pasien Tiba — Link No_MR
                                    </button>
                                </div>
                                <div v-else class="space-y-1.5">
                                    <input v-model="(linkForm[b.id] ??= {}).No_MR" placeholder="No. MR *"
                                        class="w-full text-xs px-3 py-2 rounded-xl outline-none font-mono"
                                        style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                                    <input v-model="(linkForm[b.id] ??= {}).No_Reg" placeholder="No. Registrasi *"
                                        class="w-full text-xs px-3 py-2 rounded-xl outline-none font-mono"
                                        style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                                    <button @click="doLinkPasien(b)"
                                        class="w-full text-xs font-bold py-1.5 rounded-xl"
                                        style="background:#2DD9A4; color:#0D1A17">Simpan & Link</button>
                                </div>
                                </template>
                            </template>

                            <!-- Admisi: verifikasi bed setelah pasien tiba (pasien_tiba) -->
                            <template v-else-if="b.status === 'pasien_tiba'">
                                <div class="text-xs p-2 rounded-xl" style="background:rgba(45,217,164,0.08); border:1px solid rgba(45,217,164,0.2)">
                                    <p style="color:var(--text-secondary)">MR: <span class="font-mono font-semibold" style="color:var(--text-primary)">{{ b.No_MR }}</span></p>
                                    <p style="color:var(--text-secondary)">Bed: <span class="font-semibold" style="color:#2DD9A4">{{ b.nama_bed }}</span></p>
                                </div>
                                <button v-if="canVerifikasiBed" @click="doVerifikasiBed(b)"
                                    class="text-xs font-bold py-2 rounded-xl"
                                    style="background:#2DD9A4; color:#0D1A17">
                                    ✓ Verifikasi Bed
                                </button>
                                <span v-else class="text-xs text-center py-1.5 rounded-xl" style="background:var(--bg-input); color:var(--text-secondary)">
                                    Menunggu verifikasi Admisi
                                </span>
                            </template>

                            <!-- ICU: konfirmasi pasien masuk (bed_verified) -->
                            <template v-else-if="b.status === 'bed_verified'">
                                <div class="text-xs p-2 rounded-xl" style="background:rgba(45,217,164,0.08); border:1px solid rgba(45,217,164,0.2)">
                                    <p style="color:#2DD9A4" class="font-semibold">Siap diantar ke bed:</p>
                                    <p style="color:var(--text-primary)">🏥 {{ b.nama_bed }}</p>
                                </div>
                                <button v-if="canKonfirmasiMasuk" @click="doKonfirmasiMasuk(b)"
                                    class="text-xs font-bold py-2 rounded-xl"
                                    style="background:#3DDB8A; color:#0D1A17">
                                    ✓ Pasien Masuk ICU
                                </button>
                                <span v-else class="text-xs text-center py-1.5 rounded-xl" style="background:var(--bg-input); color:var(--text-secondary)">
                                    Menunggu konfirmasi ICU
                                </span>
                            </template>

                            <!-- Di ICU -->
                            <template v-else-if="b.status === 'di_icu'">
                                <span class="text-xs text-center font-semibold py-2 rounded-xl"
                                    style="background:rgba(61,219,138,0.12); color:#3DDB8A">
                                    ✓ Pasien di ICU
                                </span>
                            </template>

                            <!-- Ditolak -->
                            <template v-else-if="b.status === 'ditolak'">
                                <span class="text-xs text-center py-2 rounded-xl"
                                    style="background:rgba(224,112,80,0.1); color:#E07050">
                                    Booking Ditolak
                                </span>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
