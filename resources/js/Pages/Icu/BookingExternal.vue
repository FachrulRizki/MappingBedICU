<script setup>
import { ref, computed, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout    from '@/Layouts/AppLayout.vue';
import AlertModal   from '@/Components/AlertModal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import Icd10Search  from '@/Components/Icd10Search.vue';
import { useAuth }  from '@/composables/useAuth.js';

const {
    canBuatBookingExternal,
    canVerifikasiAdmisiExt,
    canKonfirmasiIcu,
    isAdmin,
    user: authUser,
} = useAuth();

const props = defineProps({
    bookings:    { type: Array,  default: () => [] },
    kamarKosong: { type: Array,  default: () => [] },
    masterKelas: { type: Array,  default: () => [] },
    caraBayar:   { type: Array,  default: () => [] },
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
const activeTab = ref('menunggu');

// Alur: pending_icu → bed_confirmed → admisi_verified | ditolak
const statusGroups = {
    menunggu:     ['pending_icu'],
    bed_confirmed:['bed_confirmed'],
    verified:     ['admisi_verified'],
    selesai:      ['ditolak'],
};

const allTabs = [
    { key: 'menunggu',      label: 'Waiting ICU',    roles: ['admin','admisi','petugas_icu'] },
    { key: 'bed_confirmed', label: 'Verifikasi Admisi', roles: ['admin','admisi','petugas_icu'] },
    { key: 'verified',      label: 'Terverifikasi',    roles: ['admin','admisi','petugas_icu'] },
    { key: 'selesai',       label: 'Ditolak',          roles: ['admin','admisi','petugas_icu'] },
];

const visibleTabs = computed(() => allTabs.filter(t =>
    isAdmin.value || t.roles.includes(authUser.value?.role)
));

const filtered = computed(() => {
    const keys = statusGroups[activeTab.value] ?? [];
    return keys.length ? props.bookings.filter(b => keys.includes(b.status)) : props.bookings;
});

const tabCounts = computed(() => {
    const c = {};
    for (const [tab, statuses] of Object.entries(statusGroups))
        c[tab] = props.bookings.filter(b => statuses.includes(b.status)).length;
    return c;
});

// ── Form booking baru (Admisi) ─────────────────────────────
const showForm = ref(false);
const form = useForm({
    nama_pasien: '', jenis_kelamin: '', no_identitas: '',
    asal_rujukan: '', no_telp_keluarga: '',
    diagnosa: '', rencana_tindakan: '',
    jaminan: '', catatan_jaminan: '', keterangan: '',
});
const submitForm = () => form.post(route('icu.booking_external.store'), {
    onSuccess: () => { form.reset(); showForm.value = false; },
});

// ── Actions ICU: konfirmasi bed ────────────────────────────
// Hanya mencatat referensi bed, TIDAK mengupdate status bed di RS
const bedPilihan     = ref({});
const kondisiPilihan = ref({});
const alasanTolak    = ref({});
const showTolakForm  = ref({});

const bedCocok = (kondisi) => kondisi
    ? props.kamarKosong.filter(b => b.nama_kelas === kondisi)
    : props.kamarKosong;

const doKonfirmasiIcu = (b) => {
    const kondisi = kondisiPilihan.value[b.id];
    const kode    = bedPilihan.value[b.id];
    if (!kondisi) { showAlert('warning', 'Pilih Jenis ICU', 'Tentukan jenis ICU terlebih dahulu.'); return; }
    if (!kode)    { showAlert('warning', 'Pilih Bed',       'Pilih bed terlebih dahulu.'); return; }
    const namaBed = props.kamarKosong.find(x => x.Kode_Ruang === kode)?.nama_ruang ?? kode;
    openConfirm({
        title:   'Konfirmasi Bed?',
        message: `Bed ${namaBed} (${kondisi}) dicatat untuk ${b.nama_pasien}. Admisi akan diminta verifikasi No_MR pasien.`,
        danger:  false,
        action:  () => router.post(route('icu.booking_external.konfirmasi_icu', b.id), {
            Kode_Ruang:    kode,
            kebutuhan_bed: kondisi,
        }),
    });
};

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

// ── Actions Admisi: verifikasi No_MR setelah pasien tiba ──
// Lookup dari DB RS (PENDAFTARAN) — sama seperti SPRI Internal
const noMrVerifikasi   = ref({});
const noRegVerifikasi  = ref({});
const lookupExtLoading = ref({});
const lookupExtResult  = ref({});   // { found, nama_pasien, kunjungans }
const lookupExtError   = ref({});

const doLookupExt = async (bookingId, noMr) => {
    lookupExtResult.value[bookingId] = null;
    lookupExtError.value[bookingId]  = '';
    noRegVerifikasi.value[bookingId] = '';
    if (!noMr || noMr.trim().length < 3) return;

    lookupExtLoading.value[bookingId] = true;
    try {
        const res  = await fetch(
            route('icu.booking_external.lookup_pasien') + '?No_MR=' + encodeURIComponent(noMr.trim()),
            { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }
        );
        const data = await res.json();
        lookupExtResult.value[bookingId] = data;
        if (!data.found) {
            lookupExtError.value[bookingId] = data.message ?? 'Pasien tidak ditemukan.';
        } else if (data.kunjungans?.length === 1) {
            noRegVerifikasi.value[bookingId] = data.kunjungans[0].No_Reg;
        }
    } catch {
        lookupExtError.value[bookingId] = 'Gagal menghubungi server.';
    } finally {
        lookupExtLoading.value[bookingId] = false;
    }
};

const doVerifikasiAdmisi = (b) => {
    const noMr = noMrVerifikasi.value[b.id]?.trim();
    if (!noMr) { showAlert('warning', 'Isi No. MR', 'No. MR pasien harus diisi.'); return; }
    if (!lookupExtResult.value[b.id]?.found) {
        showAlert('warning', 'Cari Dulu', 'Lakukan pencarian No. MR terlebih dahulu untuk verifikasi data.'); return;
    }
    const namaPasienMr = lookupExtResult.value[b.id]?.nama_pasien ?? noMr;
    openConfirm({
        title:   'Verifikasi Pasien?',
        message: `Konfirmasi: ${namaPasienMr} (No. MR: ${noMr}) adalah pasien ${b.nama_pasien} yang sudah tiba di ICU.`,
        danger:  false,
        action:  () => router.post(route('icu.booking_external.verifikasi_admisi', b.id), {
            No_MR:  noMr,
            No_Reg: noRegVerifikasi.value[b.id] ?? null,
        }),
    });
};

// ── Helpers ────────────────────────────────────────────────
const gBg   = (g) => g === 'L' ? 'rgba(74,144,217,0.15)' : g === 'P' ? 'rgba(217,81,122,0.15)' : 'var(--bg-input)';
const gTxt  = (g) => g === 'L' ? '#4A90D9' : g === 'P' ? '#D9517A' : 'var(--text-secondary)';
const gIcon = (g) => g === 'L' ? '♂' : g === 'P' ? '♀' : '?';

const jaminanColor = (j) => ({ BPJS: '#4A90D9', Umum: '#E0923A', Asuransi: '#2DD9A4', Lainnya: '#8EA89E' }[j] ?? '#8EA89E');

const statusBadge = (status) => ({
    pending_icu:     { bg: 'rgba(224,146,58,0.15)',  color: '#E0923A' },
    bed_confirmed:   { bg: 'rgba(74,144,217,0.15)',  color: '#4A90D9' },
    admisi_verified: { bg: 'rgba(45,217,164,0.15)',  color: '#2DD9A4' },
    ditolak:         { bg: 'rgba(224,112,80,0.15)',  color: '#E07050' },
}[status] ?? { bg: 'var(--bg-input)', color: 'var(--text-secondary)' });
</script>

<template>
    <AppLayout :flash="flash" page-title="Booking ICU — Pasien Eksternal">
        <AlertModal   v-bind="alert"   @close="alert.show = false"/>
        <ConfirmModal v-bind="confirm" @confirm="doConfirm" @cancel="confirm.show = false"/>

        <div class="p-4 sm:p-6 space-y-4" style="font-family:'Plus Jakarta Sans',sans-serif">

            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <h1 class="font-bold text-lg" style="color:var(--text-primary)">Booking ICU — Pasien Eksternal</h1>
                <button v-if="canBuatBookingExternal" @click="showForm = !showForm"
                    class="flex items-center gap-2 text-sm font-semibold px-4 py-2.5 rounded-xl self-start sm:self-auto"
                    :style="showForm
                        ? 'background:var(--bg-main); color:var(--text-secondary); border:1px solid var(--border-default)'
                        : 'background:#2DD9A4; color:#0D1A17'">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            :d="showForm ? 'M6 18L18 6M6 6l12 12' : 'M12 4v16m8-8H4'"/>
                    </svg>
                    {{ showForm ? 'Tutup Form' : 'Booking Baru' }}
                </button>
            </div>

            <!-- ── Form Booking Baru (Admisi) ──────────────────────── -->
            <Transition enter-active-class="transition-all duration-200 ease-out"
                        enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0">
                <form v-if="showForm && canBuatBookingExternal" @submit.prevent="submitForm" class="card-dark overflow-hidden">
                    <div class="px-5 py-3" style="background:linear-gradient(90deg,#2DD9A4,#1A9E8F)">
                        <p class="text-sm font-bold" style="color:#0D1A17">Form Booking ICU — Pasien Eksternal</p>
                    </div>
                    <div class="p-5 space-y-5">

                        <!-- 1. Identitas -->
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide mb-3" style="color:var(--text-accent)">1. Identitas Pasien</p>
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
                                            :style="form.jenis_kelamin==='L'
                                                ? 'background:#4A90D9;color:#fff;border:2px solid #4A90D9'
                                                : 'background:var(--bg-surface);color:var(--text-secondary);border:2px solid var(--border-default)'">
                                            ♂ Pria
                                        </button>
                                        <button type="button" @click="form.jenis_kelamin='P'"
                                            class="flex-1 py-2.5 rounded-xl text-sm font-semibold"
                                            :style="form.jenis_kelamin==='P'
                                                ? 'background:#D9517A;color:#fff;border:2px solid #D9517A'
                                                : 'background:var(--bg-surface);color:var(--text-secondary);border:2px solid var(--border-default)'">
                                            ♀ Wanita
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">No. Identitas / NIK</label>
                                    <input v-model="form.no_identitas" placeholder="NIK / identitas sementara"
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
                        </div>

                        <!-- 2. Data Klinis -->
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide mb-3" style="color:var(--text-accent)">2. Data Klinis</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">Diagnosa <span style="color:#E07050">*</span></label>
                                    <Icd10Search v-model="form.diagnosa" placeholder="Cari kode / keterangan ICD10"
                                        :required="true" :has-error="!!form.errors.diagnosa"/>
                                    <p v-if="form.errors.diagnosa" class="text-xs mt-1" style="color:#E07050">{{ form.errors.diagnosa }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">Rencana Tindakan <span style="color:#E07050">*</span></label>
                                    <input v-model="form.rencana_tindakan" required placeholder="Rencana tindakan ICU"
                                        class="w-full px-3 py-2.5 text-sm rounded-xl outline-none"
                                        style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary)">
                                        Keterangan Klinis
                                        <span class="font-normal">(kondisi terkini, riwayat, catatan dokter pengirim)</span>
                                    </label>
                                    <textarea v-model="form.keterangan" rows="3"
                                        placeholder="Kondisi terkini pasien, riwayat penyakit, catatan dari dokter pengirim, dll..."
                                        class="w-full px-3 py-2.5 text-sm rounded-xl outline-none resize-none"
                                        style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                                    <p class="text-xs mt-1" style="color:var(--text-secondary)">
                                        ℹ Jenis ICU dan bed akan ditentukan oleh Petugas ICU
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Jaminan -->
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide mb-3" style="color:var(--text-accent)">3. Informasi Jaminan</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">Jenis Jaminan <span style="color:#E07050">*</span></label>
                                    <select v-model="form.jaminan" required
                                        class="w-full px-3 py-2.5 text-sm rounded-xl outline-none"
                                        :style="`border:1px solid ${form.errors.jaminan ? '#E07050' : 'var(--border-default)'}; background:var(--bg-surface); color:${form.jaminan ? 'var(--text-primary)' : 'var(--text-secondary)'}`">
                                        <option value="" disabled>-- Pilih Jenis Jaminan --</option>
                                        <option v-for="cb in caraBayar" :key="cb.kode" :value="cb.kode">
                                            {{ cb.nama }}
                                        </option>
                                    </select>
                                    <p v-if="form.errors.jaminan" class="text-xs mt-1" style="color:#E07050">{{ form.errors.jaminan }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">Catatan Jaminan</label>
                                    <textarea v-model="form.catatan_jaminan"
                                        placeholder="No. BPJS / No. Polis / keterangan lain"
                                        rows="3" class="w-full px-3 py-2.5 text-sm rounded-xl outline-none resize-none"
                                        style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                                </div>
                            </div>
                        </div>

                        <!-- Submit -->
                        <div class="pt-1 flex items-center gap-2" style="border-top:1px solid var(--border-default)">
                            <button type="submit"
                                :disabled="form.processing || !form.jenis_kelamin || !form.jaminan"
                                class="flex items-center gap-2 text-sm font-bold px-5 py-2.5 rounded-xl disabled:opacity-50"
                                style="background:#2DD9A4; color:#0D1A17">
                                <svg v-if="form.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                </svg>
                                {{ form.processing ? 'Menyimpan...' : 'Kirim ke ICU' }}
                            </button>
                            <button type="button" @click="showForm=false; form.reset()"
                                class="px-5 py-2.5 text-sm rounded-xl"
                                style="background:var(--bg-main); color:var(--text-secondary); border:1px solid var(--border-default)">
                                Batal
                            </button>
                            <p v-if="!form.jenis_kelamin || !form.jaminan" class="text-xs" style="color:#E0923A">
                                ⚠ {{ !form.jenis_kelamin ? 'Pilih jenis kelamin' : 'Pilih jenis jaminan' }}
                            </p>
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

            <!-- List Booking -->
            <div v-else class="space-y-3">
                <div v-for="b in filtered" :key="b.id" class="card-dark overflow-hidden">

                    <!-- Card header -->
                    <div class="flex items-center justify-between px-5 py-3.5" style="border-bottom:1px solid var(--border-default)">
                        <div class="flex items-center gap-3">
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
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <span v-if="b.jaminan" class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                :style="`background:${jaminanColor(b.jaminan)}20; color:${jaminanColor(b.jaminan)}`">
                                {{ caraBayar.find(c => c.kode === b.jaminan)?.nama ?? b.jaminan }}
                            </span>
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-full"
                                :style="`background:${statusBadge(b.status).bg}; color:${statusBadge(b.status).color}`">
                                {{ b.status_label }}
                            </span>
                            <span class="text-xs hidden sm:block" style="color:var(--text-secondary)">{{ b.created_at }}</span>
                        </div>
                    </div>

                    <!-- Card body -->
                    <div class="px-5 py-4 grid grid-cols-1 sm:grid-cols-3 gap-4">

                        <!-- Info -->
                        <div class="sm:col-span-2 space-y-1.5">
                            <div class="flex gap-2 text-xs">
                                <span style="color:var(--text-secondary); min-width:120px">Diagnosa</span>
                                <span class="font-medium" style="color:var(--text-primary)">{{ b.diagnosa }}</span>
                            </div>
                            <div class="flex gap-2 text-xs">
                                <span style="color:var(--text-secondary); min-width:120px">Rencana Tindakan</span>
                                <span class="font-medium" style="color:var(--text-primary)">{{ b.rencana_tindakan }}</span>
                            </div>
                            <div v-if="b.kebutuhan_bed" class="flex gap-2 text-xs">
                                <span style="color:var(--text-secondary); min-width:120px">Jenis ICU</span>
                                <span class="text-xs px-2 py-0.5 rounded-full font-semibold"
                                    style="background:rgba(45,217,164,0.12); color:#2DD9A4">{{ b.kebutuhan_bed }}</span>
                            </div>
                            <div v-if="b.catatan_jaminan" class="flex gap-2 text-xs">
                                <span style="color:var(--text-secondary); min-width:120px">Catatan Jaminan</span>
                                <span style="color:var(--text-primary)">{{ b.catatan_jaminan }}</span>
                            </div>
                            <div v-if="b.keterangan" class="flex gap-2 text-xs">
                                <span style="color:var(--text-secondary); min-width:120px">Keterangan</span>
                                <span style="color:var(--text-secondary)">{{ b.keterangan }}</span>
                            </div>
                            <div v-if="b.nama_bed" class="flex gap-2 text-xs">
                                <span style="color:var(--text-secondary); min-width:120px">Bed Dikonfirmasi</span>
                                <span class="font-semibold" style="color:#2DD9A4">🏥 {{ b.nama_bed }}</span>
                            </div>
                            <div v-if="b.alasan_tolak" class="flex gap-2 text-xs">
                                <span style="color:var(--text-secondary); min-width:120px">Alasan Tolak</span>
                                <span style="color:#E07050">{{ b.alasan_tolak }}</span>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex flex-col gap-2">

                            <!-- pending_icu: ICU tentukan jenis + bed -->
                            <template v-if="b.status === 'pending_icu'">
                                <template v-if="canKonfirmasiIcu">
                                    <p class="text-xs font-semibold" style="color:#E0923A">⏳ Menunggu Petugas ICU</p>
                                    <div>
                                        <p class="text-xs font-semibold mb-1" style="color:var(--text-primary)">1. Jenis ICU:</p>
                                        <select v-model="kondisiPilihan[b.id]"
                                            class="w-full text-xs px-3 py-2 rounded-xl outline-none"
                                            style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)">
                                            <option value="" disabled>-- Pilih Jenis ICU --</option>
                                            <option v-for="k in masterKelas" :key="k.kode" :value="k.nama">{{ k.nama }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <p class="text-xs font-semibold mb-1" style="color:var(--text-primary)">2. Pilih Bed:</p>
                                        <select v-model="bedPilihan[b.id]"
                                            :disabled="props.kamarKosong.length === 0"
                                            class="w-full text-xs px-3 py-2 rounded-xl outline-none disabled:opacity-40"
                                            style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)">
                                            <option value="" disabled>-- Pilih Bed --</option>
                                            <option v-for="bed in bedCocok(kondisiPilihan[b.id])" :key="bed.Kode_Ruang" :value="bed.Kode_Ruang">
                                                {{ bed.nama_ruang }}<template v-if="!kondisiPilihan[b.id]"> ({{ bed.nama_kelas }})</template>
                                            </option>
                                        </select>
                                    </div>
                                    <p v-if="props.kamarKosong.length === 0" class="text-xs"
                                        style="color:#E0923A; background:rgba(224,146,58,0.1); padding:8px; border-radius:8px">
                                        ⚠ Tidak ada bed kosong saat ini
                                    </p>
                                    <button @click="doKonfirmasiIcu(b)"
                                        :disabled="props.kamarKosong.length === 0"
                                        class="text-xs font-bold py-2 rounded-xl disabled:opacity-40"
                                        style="background:#4A90D9; color:#fff">
                                        ✓ Konfirmasi Bed
                                    </button>
                                    <div v-if="!showTolakForm[b.id]">
                                        <button @click="showTolakForm[b.id] = true"
                                            class="w-full text-xs font-semibold py-2 rounded-xl"
                                            style="background:rgba(224,112,80,0.1); color:#E07050; border:1px solid rgba(224,112,80,0.25)">
                                            ✕ Tolak
                                        </button>
                                    </div>
                                    <div v-else class="space-y-1.5">
                                        <input v-model="alasanTolak[b.id]" placeholder="Alasan *"
                                            class="w-full text-xs px-3 py-2 rounded-xl outline-none"
                                            style="border:1px solid rgba(224,112,80,0.3); background:var(--bg-surface); color:var(--text-primary)"/>
                                        <button @click="doTolakIcu(b)" class="w-full text-xs font-bold py-1.5 rounded-xl"
                                            style="background:#E07050; color:#fff">Konfirmasi Tolak</button>
                                    </div>
                                </template>
                                <span v-else class="text-xs text-center py-2 rounded-xl block"
                                    style="background:rgba(224,146,58,0.1); color:#E0923A; border:1px solid rgba(224,146,58,0.2)">
                                    Menunggu konfirmasi ICU
                                </span>
                            </template>

                            <!-- bed_confirmed: Admisi input No_MR untuk verifikasi -->
                            <template v-else-if="b.status === 'bed_confirmed'">
                                <div class="p-2.5 rounded-xl text-xs space-y-1"
                                    style="background:rgba(74,144,217,0.08); border:1px solid rgba(74,144,217,0.2)">
                                    <p class="font-semibold" style="color:#4A90D9">🏥 Bed Dikonfirmasi ICU</p>
                                    <p style="color:var(--text-primary)">{{ b.nama_bed }} · {{ b.kebutuhan_bed }}</p>
                                    <p class="text-xs" style="color:var(--text-secondary)">oleh: {{ b.confirmed_by }}</p>
                                </div>
                                <template v-if="canVerifikasiAdmisiExt">
                                    <p class="text-xs font-semibold" style="color:#4A90D9">📋 No. MR pasien:</p>

                                    <!-- Input No_MR + live lookup -->
                                    <div class="relative">
                                        <input
                                            :value="noMrVerifikasi[b.id]"
                                            @input="e => { noMrVerifikasi[b.id] = e.target.value; doLookupExt(b.id, e.target.value); }"
                                            placeholder="Ketik No. MR..."
                                            class="w-full text-xs px-3 py-2 rounded-xl outline-none font-mono pr-7"
                                            :style="`border:1px solid ${
                                                lookupExtError[b.id] ? '#E07050'
                                                : lookupExtResult[b.id]?.found ? '#2DD9A4'
                                                : 'var(--border-default)'
                                            }; background:var(--bg-surface); color:var(--text-primary)`"/>
                                        <div class="absolute right-2 top-1/2 -translate-y-1/2 text-xs">
                                            <svg v-if="lookupExtLoading[b.id]" class="w-3 h-3 animate-spin" style="color:var(--text-secondary)" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                            </svg>
                                            <span v-else-if="lookupExtResult[b.id]?.found" style="color:#2DD9A4">✓</span>
                                            <span v-else-if="lookupExtError[b.id]" style="color:#E07050">✕</span>
                                        </div>
                                    </div>

                                    <!-- Hasil lookup -->
                                    <div v-if="lookupExtResult[b.id]?.found" class="text-xs space-y-1.5">
                                        <p class="font-semibold" style="color:#2DD9A4">
                                            ✓ {{ lookupExtResult[b.id].nama_pasien }}
                                        </p>
                                        <!-- Pilih kunjungan jika lebih dari 1 -->
                                        <div v-if="lookupExtResult[b.id].kunjungans?.length > 1">
                                            <p class="mb-1" style="color:var(--text-secondary)">Pilih kunjungan:</p>
                                            <select v-model="noRegVerifikasi[b.id]"
                                                class="w-full text-xs px-2 py-1.5 rounded-lg outline-none"
                                                style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)">
                                                <option value="" disabled>-- Pilih No_Reg --</option>
                                                <option v-for="k in lookupExtResult[b.id].kunjungans" :key="k.No_Reg" :value="k.No_Reg">
                                                    {{ k.No_Reg }}{{ k.nama_ruang ? ' — ' + k.nama_ruang : '' }}
                                                </option>
                                            </select>
                                        </div>
                                        <p v-else-if="lookupExtResult[b.id].kunjungans?.length === 1" style="color:var(--text-secondary)">
                                            Kunjungan: {{ lookupExtResult[b.id].kunjungans[0].No_Reg }}
                                        </p>
                                    </div>
                                    <p v-else-if="lookupExtError[b.id]" class="text-xs" style="color:#E07050">
                                        {{ lookupExtError[b.id] }}
                                    </p>

                                    <button @click="doVerifikasiAdmisi(b)"
                                        :disabled="!lookupExtResult[b.id]?.found"
                                        class="text-xs font-bold py-2 rounded-xl disabled:opacity-40"
                                        style="background:#2DD9A4; color:#0D1A17">
                                        ✓ Verifikasi Pasien Masuk
                                    </button>
                                </template>
                                <span v-else class="text-xs text-center py-2 rounded-xl block"
                                    style="background:rgba(74,144,217,0.1); color:#4A90D9; border:1px solid rgba(74,144,217,0.2)">
                                    Menunggu verifikasi Admisi
                                </span>
                            </template>

                            <!-- admisi_verified: selesai, tampilkan info -->
                            <template v-else-if="b.status === 'admisi_verified'">
                                <div class="p-2.5 rounded-xl text-xs space-y-1.5"
                                    style="background:rgba(45,217,164,0.08); border:1px solid rgba(45,217,164,0.2)">
                                    <p class="font-semibold" style="color:#2DD9A4">✓ Pasien Terverifikasi di ICU</p>
                                    <p v-if="b.nama_bed" style="color:var(--text-primary)">🏥 {{ b.nama_bed }}</p>
                                    <div class="pt-1 space-y-0.5" style="border-top:1px solid rgba(45,217,164,0.2)">
                                        <p v-if="b.No_MR" class="font-mono" style="color:var(--text-secondary)">MR: {{ b.No_MR }}</p>
                                        <p v-if="b.nama_pasien_mr" style="color:var(--text-secondary)">{{ b.nama_pasien_mr }}</p>
                                        <p style="color:var(--text-secondary)">Verifikasi: {{ b.verified_by ?? '-' }}</p>
                                    </div>
                                </div>
                            </template>

                            <!-- ditolak -->
                            <template v-else-if="b.status === 'ditolak'">
                                <span class="text-xs text-center py-2 rounded-xl block"
                                    style="background:rgba(224,112,80,0.1); color:#E07050">✕ Ditolak</span>
                            </template>

                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>