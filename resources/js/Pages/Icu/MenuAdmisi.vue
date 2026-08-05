<script setup>
import { ref, computed, watch } from 'vue';
import { router, useForm }  from '@inertiajs/vue3';
import AppLayout  from '@/Layouts/AppLayout.vue';
import Icd10Search from '@/Components/Icd10Search.vue';
import { useAuth } from '@/composables/useAuth.js';

const { canBuatBookingExternal, canVerifikasiAdmisiExt, canApproveAdmisi, canTolakAdmisi, isAdmin } = useAuth();
const logoUrl     = '/images/logo-urip.png';
const doctorImgUrl = '/images/welcome-doctors.svg';

const props = defineProps({
    antrian:     { type: Array,  default: () => [] },
    summary:     { type: Object, default: () => ({}) },
    filters:     { type: Object, default: () => ({}) },
    caraBayar:   { type: Array,  default: () => [] },
    kamarKosong: { type: Array,  default: () => [] },
    masterKelas: { type: Array,  default: () => [] },
    flash:       { type: Object, default: () => ({}) },
});

// Flash ditangani oleh FlashMessage global di AppLayout — tidak perlu toast lokal

// ── Filters ────────────────────────────────────────────────
// fStatus hanya client-side (tidak dikirim ke server)
const fStatus = ref('');
const fJenis  = ref(props.filters.filterJenis  ?? '');
const fNama   = ref(props.filters.filterNama   ?? '');
const sortBy  = ref(props.filters.sortBy       ?? 'created_at');
const sortDir = ref(props.filters.sortDir      ?? 'asc');

const localDate = (n = 0) => {
    const d = new Date(); d.setDate(d.getDate() + n);
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
};
const fTglDari = ref(props.filters.filterTglDari || '');
const fTglAkh  = ref(props.filters.filterTglAkh  || '');
const today     = localDate(0);
const yesterday = localDate(-1);
const week7     = localDate(-6);
const setPreset = (d, s) => { fTglDari.value=d; fTglAkh.value=s; applyFilters(); };

let searchTimer = null;
// Server hanya terima: jenis, nama, tanggal, sort — TIDAK status (status filter client-side)
const applyFilters = () => router.get(route('icu.menu_admisi'), {
    jenis: fJenis.value, nama: fNama.value,
    tgl_dari: fTglDari.value, tgl_sampai: fTglAkh.value,
    sort: sortBy.value, dir: sortDir.value,
}, { preserveState: true, replace: true, preserveScroll: true });

const onNamaInput = () => { clearTimeout(searchTimer); searchTimer = setTimeout(applyFilters, 400); };
const toggleSort  = (col) => {
    sortDir.value = sortBy.value === col ? (sortDir.value === 'asc' ? 'desc' : 'asc') : 'asc';
    sortBy.value  = col; applyFilters();
};
const resetFilter = () => {
    fStatus.value=''; fJenis.value=''; fNama.value=''; fTglDari.value=''; fTglAkh.value='';
    activeCardKey.value='';
    applyFilters();
};
const sortIcon = (col) => sortBy.value !== col ? '↕' : sortDir.value === 'asc' ? '↑' : '↓';

// ── Tab view ────────────────────────────────────────────────
const viewTab = ref('antrian'); // 'antrian' | 'terverifikasi_icu' | 'perlu_verif' | 'pasien_keluar'

// ── Computed views per tab ─────────────────────────────────
const antrianTabView = computed(() => {
    let list = props.antrian.filter(i => ['pending_icu', 'waiting_list', 'pending_admisi'].includes(i.status));
    if (fJenis.value) list = list.filter(i => i.sumber === fJenis.value);
    if (fStatus.value === 'pending_icu')   return list.filter(i => i.status === 'pending_icu');
    if (fStatus.value === 'waiting_list')  return list.filter(i => i.status === 'waiting_list');
    if (fStatus.value === 'pending_admisi') return list.filter(i => i.status === 'pending_admisi');
    if (fStatus.value === 'ditolak')       return props.antrian.filter(i => i.status === 'ditolak' && (!fJenis.value || i.sumber === fJenis.value));
    if (fStatus.value === 'dibatalkan')    return props.antrian.filter(i => i.status === 'dibatalkan' && (!fJenis.value || i.sumber === fJenis.value));
    return list;
});
const terverifikasiIcuView = computed(() => {
    let list = props.antrian.filter(i => ['bed_confirmed', 'bed_verified'].includes(i.status));
    if (fJenis.value) list = list.filter(i => i.sumber === fJenis.value);
    return list;
});
const perluVerifView = computed(() => {
    let list = props.antrian.filter(i => i.status === 'admisi_verified');
    if (fJenis.value) list = list.filter(i => i.sumber === fJenis.value);
    return list;
});
const pasienKeluarView = computed(() => {
    let list = props.antrian.filter(i => i.status === 'selesai');
    if (fJenis.value) list = list.filter(i => i.sumber === fJenis.value);
    return list;
});

const currentView = computed(() => {
    if (viewTab.value === 'terverifikasi_icu') return terverifikasiIcuView.value;
    if (viewTab.value === 'perlu_verif')       return perluVerifView.value;
    if (viewTab.value === 'pasien_keluar')     return pasienKeluarView.value;
    return antrianTabView.value;
});

// ── Filter antrian client-side (status + jenis) — backward compat ──────────
const antrianFiltered = computed(() => currentView.value);

// ── Style helpers ──────────────────────────────────────────
const SS = {
    pending_icu:     { bg: 'rgba(230,126,34,.15)',  color: '#E67E22', dot: '#E67E22' },
    pending_admisi:  { bg: 'rgba(245,166,35,.15)',  color: '#E67E22', dot: '#E67E22' },
    waiting_list:    { bg: 'rgba(217,119,6,.15)',   color: '#D97706', dot: '#D97706' },
    bed_confirmed:   { bg: 'rgba(0,168,132,.15)',   color: '#00A884', dot: '#00A884' },
    bed_verified:    { bg: 'rgba(0,168,132,.15)',   color: '#00A884', dot: '#00A884' },
    admisi_verified: { bg: 'rgba(0,168,132,.15)',   color: '#00A884', dot: '#00A884' },
    ditolak:         { bg: 'rgba(231,76,60,.15)',   color: '#E74C3C', dot: '#E74C3C' },
    dibatalkan:      { bg: 'rgba(120,120,120,.15)', color: '#6B7280', dot: '#6B7280' },
};
const ss = (s) => SS[s] ?? { bg: 'var(--bg-input)', color: 'var(--text-secondary)', dot: '#888' };

const SRC = {
    external: { bg: 'rgba(0,168,132,.12)', color: '#00A884' },
    internal: { bg: 'rgba(90,107,124,.12)', color: '#5A6B7C' },
};
const jaminanLabel = (k) => {
    if (!k) return '—';
    // Cek apakah k adalah kode yang ada di caraBayar
    const found = props.caraBayar.find(c => c.kode === k);
    return found ? found.nama : k; // jika tidak ketemu (sudah berupa nama), tampilkan langsung
};
const gIcon  = (g) => g === 'L' ? '♂' : g === 'P' ? '♀' : '·';
const gColor = (g) => g === 'L' ? '#00A884' : g === 'P' ? '#8E44AD' : 'var(--text-secondary)';

// ── Summary cards ──────────────────────────────────────────
// Key '__bed' dipakai sebagai penanda khusus (multi-status filter di clickCard)
const CARDS = computed(() => [
    { key:'',         label:'Total',           val: props.summary.total ?? 0,
      color:'#5A6B7C', icon:'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' },
    { key:'waiting_list',   label:'Waiting List',    val: props.summary.waiting_list ?? 0,
      color:'#D97706', icon:'M12 8v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z' },
    { key:'__bed',          label:'Terverifikasi ICU',val: props.summary.bed_aktif ?? 0,
      color:'#0EA5E9', icon:'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
    { key:'admisi_verified',label:'Perlu Verif Admisi',   val: props.summary.admisi_verified ?? 0,
      color:'#00A884', icon:'M5 13l4 4L19 7' },
    { key:'ditolak',        label:'Ditolak',         val: props.summary.ditolak ?? 0,
      color:'#E74C3C', icon:'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z' },
    { key:'dibatalkan',     label:'Dibatalkan',      val: props.summary.dibatalkan ?? 0,
      color:'#6B7280', icon:'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636' },
    { key:'selesai',        label:'Keluar ICU',      val: props.summary.selesai ?? 0,
      color:'#475569', icon:'M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1' },
]);

// Card yang aktif untuk styling (bisa multi-status)
const activeCardKey = ref('');

const clickCard = (key) => {
    activeCardKey.value = key;
    if (key === '__bed') {
        viewTab.value = 'terverifikasi_icu';
        fStatus.value = '';
    } else if (key === 'admisi_verified') {
        viewTab.value = 'perlu_verif';
        fStatus.value = '';
    } else if (key === 'selesai') {
        viewTab.value = 'pasien_keluar';
        fStatus.value = '';
    } else {
        viewTab.value = 'antrian';
        fStatus.value = key;
    }
};

// ── Aksi yang tersedia per item — setiap tombol dijaga permission sendiri ──
const canAct = computed(() => canVerifikasiAdmisiExt.value || canApproveAdmisi.value || canTolakAdmisi.value || isAdmin.value);

const actionsOf = (item) => {
    if (!canAct.value) return [];
    const acts = [];
    // ── Internal: HANYA tampil data, TIDAK ada aksi approve/tolak/edit/batal di menu Admisi ──
    // Aksi internal dikelola di Menu Petugas (oleh petugas ruang yang mengajukan)
    if (item.sumber === 'external' && item.status === 'bed_confirmed') {
        if (canVerifikasiAdmisiExt.value || isAdmin.value) {
            acts.push({ id:'verifikasi', label:'Verifikasi Pasien', color:'#00A884', bg:'rgba(0,168,132,.12)', border:'rgba(0,168,132,.3)' });
        }
    }
    // Edit — hanya booking external yang masih pending (bukan ditolak)
    if (item.sumber === 'external' && item.status === 'pending_icu') {
        if (canBuatBookingExternal.value || isAdmin.value) {
            acts.push({ id:'edit', label:'Edit Booking', color:'#0EA5E9', bg:'rgba(14,165,233,.08)', border:'rgba(14,165,233,.25)' });
        }
    }
    // Batal — hanya booking external yang masih bisa dibatalkan
    if (item.sumber === 'external' && ['pending_icu', 'waiting_list', 'bed_confirmed'].includes(item.status)) {
        if (canBuatBookingExternal.value || isAdmin.value) {
            acts.push({ id:'batal', label:'Batal Booking', color:'#D97706', bg:'rgba(217,119,6,.08)', border:'rgba(217,119,6,.25)' });
        }
    }
    // Hapus — hanya booking external pending, ditolak, atau dibatalkan
    if (item.sumber === 'external' && ['pending_icu', 'ditolak', 'dibatalkan'].includes(item.status)) {
        if (canBuatBookingExternal.value || isAdmin.value) {
            acts.push({ id:'hapus', label:'Hapus Booking', color:'#E74C3C', bg:'rgba(231,76,60,.05)', border:'rgba(231,76,60,.2)' });
        }
    }
    return acts;
};

// ── Modal state ────────────────────────────────────────────
const modal = ref({ open: false, type: '', item: null });
const openModal = (type, item = null) => { modal.value = { open: true, type, item }; };
const closeModal = () => {
    modal.value.open = false;
    setTimeout(() => {
        modal.value = { open: false, type: '', item: null };
        verifLookupResult.value  = null;
        verifLookupError.value   = '';
        verifKunjungans.value    = [];
        fmVerif.reset();
        fmEdit.reset();
        fmBatal.reset();
    }, 200);
};

// ── Form: Booking Baru ─────────────────────────────────────
const fmBooking = useForm({
    nama_pasien: '', jenis_kelamin: '', no_identitas: '',
    asal_rujukan: '', no_telp_keluarga: '',
    diagnosa: '', diagnosa_icd: '', rencana_tindakan: '',
    jaminan: '', catatan_jaminan: '', keterangan: '',
});
const submitBooking = () => fmBooking.post(route('icu.menu_admisi.booking.store'), {
    onSuccess: () => { closeModal(); fmBooking.reset(); },
});

// ── Form: Edit Booking ─────────────────────────────────────
const fmEdit = useForm({
    nama_pasien: '', jenis_kelamin: '', no_identitas: '',
    asal_rujukan: '', no_telp_keluarga: '',
    diagnosa: '', diagnosa_icd: '', rencana_tindakan: '',
    jaminan: '', catatan_jaminan: '', keterangan: '',
});
const openEditModal = (item) => {
    fmEdit.nama_pasien      = item.nama_pasien_raw ?? item.nama_pasien ?? '';
    fmEdit.jenis_kelamin    = item.jenis_kelamin   ?? '';
    fmEdit.no_identitas     = item.no_identitas    ?? '';
    fmEdit.asal_rujukan     = item.asal_rujukan    ?? '';
    fmEdit.no_telp_keluarga = item.no_telp_keluarga?? '';
    fmEdit.diagnosa         = item.diagnosa        ?? '';
    fmEdit.diagnosa_icd     = item.diagnosa_icd    ?? '';
    fmEdit.rencana_tindakan = item.rencana_tindakan?? '';
    fmEdit.jaminan          = item.jaminan         ?? '';
    fmEdit.catatan_jaminan  = item.catatan_jaminan ?? '';
    fmEdit.keterangan       = item.keterangan      ?? '';
    openModal('edit', item);
};
const submitEdit = () => {
    if (!modal.value.item) return;
    fmEdit.put(route('icu.menu_admisi.booking.update', modal.value.item.id), {
        onSuccess: () => { closeModal(); fmEdit.reset(); },
    });
};

// ── Form: Batal Booking (External) ────────────────────────
const fmBatal = useForm({ alasan_batal: '' });
const openBatalModal = (item) => { fmBatal.alasan_batal = ''; openModal('batal', item); };
const submitBatal = () => {
    if (!modal.value.item) return;
    fmBatal.post(route('icu.menu_admisi.booking.batal', modal.value.item.id), {
        onSuccess: () => { closeModal(); fmBatal.reset(); },
    });
};

// ── Form: Batal Booking Internal ──────────────────────────
const fmBatalInternal = useForm({ alasan_batal: '' });
const openBatalInternalModal = (item) => { fmBatalInternal.alasan_batal = ''; openModal('batal_internal', item); };
const submitBatalInternal = () => {
    if (!modal.value.item) return;
    fmBatalInternal.post(route('icu.menu_admisi.int.batal', modal.value.item.id), {
        onSuccess: () => { closeModal(); fmBatalInternal.reset(); },
    });
};

// ── Form: Edit Booking Internal (SPRI) ────────────────────
const fmEditInternal = useForm({
    No_MR: '', No_Reg: '',
    Diagnosis: '', Diagnosis_ICD: '', IndikasiRI: '',
    asal_ruang: '', Dokter: '', spesialis: '', Keterangan: '',
});
const openEditInternalModal = (item) => {
    fmEditInternal.No_MR         = item.No_MR        ?? '';
    fmEditInternal.No_Reg        = item.No_Reg       ?? '';
    fmEditInternal.Diagnosis     = item.diagnosa     ?? item.Diagnosis   ?? '';
    fmEditInternal.Diagnosis_ICD = item.diagnosa_icd ?? item.Diagnosis_ICD ?? '';
    fmEditInternal.IndikasiRI    = item.IndikasiRI   ?? '';
    fmEditInternal.asal_ruang    = item.asal_ruang   ?? '';
    fmEditInternal.Dokter        = item.Dokter       ?? '';
    fmEditInternal.spesialis     = item.spesialis    ?? '';
    fmEditInternal.Keterangan    = item.Keterangan   ?? item.keterangan  ?? '';
    openModal('edit_internal', item);
};
const submitEditInternal = () => {
    if (!modal.value.item) return;
    fmEditInternal.put(route('icu.menu_admisi.int.edit', modal.value.item.id), {
        onSuccess: () => { closeModal(); fmEditInternal.reset(); },
    });
};

// ── Aksi: Batal Booking Internal ──────────────────────────
// (handled by fmBatalInternal above)
// ── Aksi: Hapus Booking ────────────────────────────────────
const hapusBooking = (item) => {
    if (!confirm(`Hapus permanen booking untuk ${item.nama_pasien}? Tindakan tidak dapat dibatalkan.`)) return;
    router.delete(route('icu.menu_admisi.booking.delete', item.id), {
        onSuccess: closeModal,
    });
};

// ── Form: Approve SPRI ─────────────────────────────────────
const fmApprove = useForm({ catatan_admisi: '' });
const submitApprove = () => {
    if (!modal.value.item) return;
    fmApprove.post(route('icu.menu_admisi.int.approve', modal.value.item.id), {
        onSuccess: closeModal,
    });
};

// ── Form: Tolak SPRI ───────────────────────────────────────
const fmTolak = useForm({ alasan_tolak: '' });
const submitTolak = () => {
    if (!modal.value.item) return;
    fmTolak.post(route('icu.menu_admisi.int.tolak', modal.value.item.id), {
        onSuccess: closeModal,
    });
};

// ── Form: Verifikasi Pasien (Ext bed_confirmed) ────────────
const fmVerif = useForm({ No_MR: '', No_Reg: '' });
const verifLookupLoading = ref(false);
const verifLookupResult  = ref(null);
const verifLookupError   = ref('');
const verifKunjungans    = ref([]);

const openVerifModal = (item) => {
    fmVerif.No_MR  = item.No_MR ?? '';
    fmVerif.No_Reg = item.No_Reg ?? '';
    verifLookupResult.value  = null;
    verifLookupError.value   = '';
    verifKunjungans.value    = [];
    // Auto-lookup jika sudah ada No_MR
    openModal('verifikasi', item);
    if (fmVerif.No_MR) doVerifLookup();
};

const doVerifLookup = async () => {
    const noMr = fmVerif.No_MR.trim();
    if (noMr.length < 3) return;
    verifLookupLoading.value = true;
    verifLookupResult.value  = null;
    verifLookupError.value   = '';
    verifKunjungans.value    = [];

    try {
        const res  = await fetch(
            route('icu.booking_external.lookup_pasien') + '?No_MR=' + encodeURIComponent(noMr),
            { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }
        );
        const data = await res.json();
        if (!data.found) {
            verifLookupError.value = data.message ?? 'Pasien tidak ditemukan.';
        } else {
            verifLookupResult.value = data;
            verifKunjungans.value   = data.kunjungans ?? [];
            if (verifKunjungans.value.length === 1 && !fmVerif.No_Reg) {
                fmVerif.No_Reg = verifKunjungans.value[0].No_Reg;
            }
        }
    } catch {
        verifLookupError.value = 'Gagal menghubungi server.';
    } finally {
        verifLookupLoading.value = false;
    }
};

const submitVerif = () => {
    if (!modal.value.item) return;
    fmVerif.post(route('icu.menu_admisi.ext.verifikasi', modal.value.item.id), {
        onSuccess: closeModal,
    });
};

const statusOptions = [
    { value:'', label:'Semua Status' },
    { value:'pending_icu',     label:'Menunggu ICU' },
    { value:'waiting_list',    label:'Waiting List' },
    { value:'bed_confirmed',   label:'Bed Dikonfirmasi' },
    { value:'bed_verified',    label:'Bed Terverifikasi' },
    { value:'admisi_verified', label:'Terverifikasi' },
    { value:'ditolak',         label:'Ditolak' },
    { value:'dibatalkan',      label:'Dibatalkan' },
];
const jenisOptions = [
    { value:'', label:'Semua Jenis' },
    { value:'external', label:'Booking Eksternal' },
    { value:'internal', label:'Booking Internal' },
];
</script>

<template>
<AppLayout :flash="flash" page-title="Menu Admisi">

    <div class="p-6 sm:p-8 space-y-6" style="font-family:'Inter','Plus Jakarta Sans',sans-serif">

        <!-- ═══ PAGE HEADER (HERO) ════════════════════════════════════════ -->
        <div class="db-hero">
            <div class="db-hero-copy">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;flex-wrap:wrap">
                    <div class="db-hero-logo"><img :src="logoUrl" alt="Logo" style="width:36px;height:36px;object-fit:contain" @error="$event.target.style.display='none'"/></div>
                    <div style="min-width:0">
                        <p style="color:rgba(255,255,255,.6);font-size:11px;font-weight:500">ICU Command Center</p>
                        <h1 style="color:#fff;font-size:clamp(18px,4vw,30px);font-weight:900;letter-spacing:-.02em;line-height:1.1">Menu Admisi ICU</h1>
                        <p style="color:rgba(255,255,255,.45);font-size:11px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:280px">Verifikasi Booking Eksternal &amp; Persetujuan Booking Internal</p>
                    </div>
                </div>
                <!-- Action Button inside Hero -->
                <button v-if="canBuatBookingExternal" @click="openModal('booking')"
                    class="flex items-center gap-2 font-bold px-5 py-2.5 rounded-xl transition-all duration-150 hover:-translate-y-px mt-2"
                    style="background:#fff; color:#00A884; font-size:14px; box-shadow:0 4px 14px rgba(0,0,0,0.12)">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                    </svg>
                        Booking Baru
                </button>
            </div>

            <!-- Doctor illustration -->
            <div class="db-hero-vis" aria-hidden="true">
                <div class="db-char">
                    <img :src="doctorImgUrl" alt="Dokter ICU" style="width:100%;height:100%;object-fit:contain"/>
                </div>
            </div>
        </div>

        <!-- ═══ KPI SUMMARY CARDS ═══════════════════════════════════════ -->
        <div class="grid grid-cols-2 sm:grid-cols-4 xl:grid-cols-7 gap-2 sm:gap-3">
            <button v-for="c in CARDS" :key="c.key"
                @click="clickCard(c.key)"
                class="group relative flex items-center gap-2.5 p-3 rounded-2xl text-left transition-all duration-200 hover:-translate-y-1 hover:shadow-lg"
                style="background:var(--bg-card); border:1px solid var(--border-default); box-shadow:var(--shadow-card); min-height:72px; width:100%"
                :style="activeCardKey===c.key ? `border:2.5px solid ${c.color}; box-shadow:0 0 0 3px ${c.color}15; background:var(--bg-surface)` : ''">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 transition-transform group-hover:scale-110"
                    :style="`background:${c.color}12`">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" :style="`color:${c.color}`">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="c.icon" />
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-xl font-black tracking-tight" :style="`color:${c.color}`" style="font-family:'DM Mono',monospace; line-height:1.1">{{ c.val }}</p>
                    <p class="text-xs font-semibold mt-0.5 leading-tight" style="color:var(--text-secondary)">{{ c.label }}</p>
                </div>
            </button>
        </div>

        <!-- ═══ BREAKDOWN SUMBER ════════════════════════════════════════ -->
        <div class="flex items-center gap-3 flex-wrap">
            <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium"
                style="background:rgba(52,152,219,.1); border:1px solid rgba(52,152,219,.2)">
                <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:#00A884"></span>
                <span style="color:var(--text-secondary)">Booking Eksternal</span>
                <strong class="font-bold" style="color:#00A884">{{ summary.by_sumber?.external ?? 0 }}</strong>
            </span>
            <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium"
                style="background:rgba(90,107,124,.1); border:1px solid rgba(90,107,124,.2)">
                <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:#5A6B7C"></span>
                <span style="color:var(--text-secondary)">Booking Internal</span>
                <strong class="font-bold" style="color:#5A6B7C">{{ summary.by_sumber?.internal ?? 0 }}</strong>
            </span>
        </div>

        <!-- ═══ FILTER BAR ══════════════════════════════════════════════ -->
        <div class="rounded-2xl p-5 sm:p-6 space-y-4" style="background:var(--bg-surface); border:1px solid var(--border-default); box-shadow:var(--shadow-card)">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-4">
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Status</label>
                    <select v-model="fStatus" class="w-full rounded-xl outline-none"
                        style="padding:10px 14px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); font-size:13px">
                        <option v-for="o in statusOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                </div>
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Jenis</label>
                    <select v-model="fJenis" @change="applyFilters" class="w-full rounded-xl outline-none"
                        style="padding:10px 14px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); font-size:13px">
                        <option v-for="o in jenisOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                </div>
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Nama / No. MR</label>
                    <input v-model="fNama" @input="onNamaInput" placeholder="Cari pasien..." class="w-full rounded-xl outline-none"
                        style="padding:10px 14px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); font-size:13px"/>
                </div>
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Tgl Mulai</label>
                    <input v-model="fTglDari" @change="applyFilters" type="date" class="w-full rounded-xl outline-none"
                        style="padding:10px 14px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); font-size:13px"/>
                </div>
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Tgl Selesai</label>
                    <input v-model="fTglAkh" @change="applyFilters" type="date" :min="fTglDari" class="w-full rounded-xl outline-none"
                        style="padding:10px 14px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); font-size:13px"/>
                </div>
            </div>
            <!-- Row 2: sort + reset -->
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex gap-1 p-1 rounded-xl" style="background:var(--bg-input)">
                    <button v-for="p in [{l:'Hari ini',d:today,s:today},{l:'Kemarin',d:yesterday,s:yesterday},{l:'7 Hari',d:week7,s:today}]"
                        :key="p.l" @click="setPreset(p.d,p.s)"
                        class="px-3 py-1 rounded-lg text-xs font-semibold transition-all"
                        :style="fTglDari===p.d&&fTglAkh===p.s ? 'background:#fff;color:#00A884;box-shadow:0 1px 4px rgba(0,0,0,0.08)' : 'color:var(--text-muted)'">
                        {{ p.l }}
                    </button>
                </div>
                <span class="text-xs font-semibold" style="color:var(--text-muted)">Urutkan:</span>
                <button v-for="col in [{key:'created_at',label:'Waktu'},{key:'nama_pasien',label:'Nama'},{key:'status',label:'Status'}]"
                    :key="col.key" @click="toggleSort(col.key)"
                    class="text-xs font-semibold px-3 py-1.5 rounded-xl transition-all"
                    :style="sortBy===col.key
                        ? 'background:rgba(0,168,132,.15); color:#00A884; border:1.5px solid rgba(0,168,132,.35)'
                        : 'background:var(--bg-input); color:var(--text-secondary); border:1.5px solid var(--border-default)'">
                    {{ col.label }} {{ sortIcon(col.key) }}
                </button>
                <button v-if="fStatus||fJenis||fNama||fTglDari||fTglAkh" @click="resetFilter"
                    class="ml-auto text-xs font-semibold px-3 py-1.5 rounded-xl flex items-center gap-1.5"
                    style="background:rgba(231,76,60,.1); color:#E74C3C; border:1.5px solid rgba(231,76,60,.25)">
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Reset
                </button>
            </div>
        </div>


        <!-- ═══ TAB VIEW ══════════════════════════════════════════════ -->
        <div class="flex items-center gap-2 flex-wrap">
            <div class="flex gap-1 p-1 rounded-xl" style="background:var(--bg-surface); border:1px solid var(--border-default)">
                <!-- Tab: Antrian Menunggu -->
                <button @click="viewTab='antrian'; activeCardKey=''"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold transition-all"
                    :style="viewTab==='antrian' ? 'background:#E67E22;color:#fff;box-shadow:0 2px 8px rgba(230,126,34,.3)' : 'color:var(--text-secondary)'">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Antrian Menunggu
                    <span class="px-1.5 py-0.5 rounded-full text-xs font-bold"
                        :style="viewTab==='antrian' ? 'background:rgba(255,255,255,.25);color:#fff' : 'background:rgba(230,126,34,.15);color:#E67E22'">
                        {{ antrianTabView.length }}
                    </span>
                </button>
                <!-- Tab: Terverifikasi ICU -->
                <button @click="viewTab='terverifikasi_icu'; activeCardKey='__bed'"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold transition-all"
                    :style="viewTab==='terverifikasi_icu' ? 'background:#0EA5E9;color:#fff;box-shadow:0 2px 8px rgba(14,165,233,.3)' : 'color:var(--text-secondary)'">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Terverifikasi ICU
                    <span class="px-1.5 py-0.5 rounded-full text-xs font-bold"
                        :style="viewTab==='terverifikasi_icu' ? 'background:rgba(255,255,255,.25);color:#fff' : 'background:rgba(14,165,233,.15);color:#0EA5E9'">
                        {{ terverifikasiIcuView.length }}
                    </span>
                </button>
                <!-- Tab: Perlu Verif Admisi -->
                <button @click="viewTab='perlu_verif'; activeCardKey='admisi_verified'"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold transition-all"
                    :style="viewTab==='perlu_verif' ? 'background:#00A884;color:#fff;box-shadow:0 2px 8px rgba(0,168,132,.3)' : 'color:var(--text-secondary)'">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    Perlu Verif Admisi
                    <span class="px-1.5 py-0.5 rounded-full text-xs font-bold"
                        :style="viewTab==='perlu_verif' ? 'background:rgba(255,255,255,.25);color:#fff' : 'background:rgba(0,168,132,.15);color:#00A884'">
                        {{ perluVerifView.length }}
                    </span>
                </button>
                <!-- Tab: Pasien Keluar -->
                <button @click="viewTab='pasien_keluar'; activeCardKey='selesai'"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold transition-all"
                    :style="viewTab==='pasien_keluar' ? 'background:#475569;color:#fff;box-shadow:0 2px 8px rgba(71,85,105,.3)' : 'color:var(--text-secondary)'">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    Pasien Keluar
                    <span class="px-1.5 py-0.5 rounded-full text-xs font-bold"
                        :style="viewTab==='pasien_keluar' ? 'background:rgba(255,255,255,.25);color:#fff' : 'background:rgba(71,85,105,.12);color:#475569'">
                        {{ pasienKeluarView.length }}
                    </span>
                </button>
            </div>
            <!-- Deskripsi tab -->
            <p v-if="viewTab==='antrian'" class="text-xs" style="color:var(--text-muted)">Permintaan menunggu konfirmasi bed</p>
            <p v-else-if="viewTab==='terverifikasi_icu'" class="text-xs" style="color:var(--text-muted)">Pasien dengan bed terverifikasi ICU · menunggu verifikasi admisi</p>
            <p v-else-if="viewTab==='perlu_verif'" class="text-xs" style="color:var(--text-muted)">Pasien yang sudah diverifikasi admisi · siap masuk ICU</p>
            <p v-else class="text-xs" style="color:var(--text-muted)">Pasien yang sudah selesai dirawat di ICU</p>
        </div>

        <!-- Empty state -->
        <div v-if="!antrianFiltered.length" class="card-dark text-center py-16">
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-4" style="background:var(--bg-input)">
                <svg class="w-7 h-7" style="color:var(--text-muted)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
            </div>
            <p class="font-semibold" style="color:var(--text-secondary)">Tidak ada antrian</p>
            <p class="text-sm mt-1" style="color:var(--text-muted)">
                {{ viewTab==='terverifikasi_icu' ? 'Belum ada pasien dengan bed terverifikasi ICU.'
                 : viewTab==='perlu_verif' ? 'Belum ada pasien yang perlu verifikasi admisi.'
                 : viewTab==='pasien_keluar' ? 'Belum ada pasien yang keluar ICU pada periode ini.'
                 : 'Coba reset filter atau tambah booking baru' }}
            </p>
        </div>

        <!-- Content Area -->
        <div v-else class="card-dark overflow-hidden">
            
            <!-- TAMPILAN DESKTOP — Tabel modern spacious -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full" style="border-collapse:collapse; min-width:860px">
                    <thead>
                        <tr style="background:var(--bg-surface-2)">
                            <th class="px-4 py-3.5 text-left w-10" style="color:var(--table-th-color); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; border-bottom:2px solid var(--border-table)">#</th>
                            <th class="px-4 py-3.5 text-left cursor-pointer select-none" style="color:var(--table-th-color); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; border-bottom:2px solid var(--border-table); min-width:180px" @click="toggleSort('nama_pasien')">
                                <span class="flex items-center gap-1">Pasien <span style="opacity:.5">{{ sortIcon('nama_pasien') }}</span></span>
                            </th>
                            <th class="px-4 py-3.5 text-left" style="color:var(--table-th-color); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; border-bottom:2px solid var(--border-table); min-width:120px">Jenis</th>
                            <th class="px-4 py-3.5 text-left" style="color:var(--table-th-color); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; border-bottom:2px solid var(--border-table); min-width:180px">Diagnosa / Indikasi</th>
                            <th class="px-4 py-3.5 text-left" style="color:var(--table-th-color); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; border-bottom:2px solid var(--border-table); min-width:130px">Asal / DPJP</th>
                            <th class="px-4 py-3.5 text-left" style="color:var(--table-th-color); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; border-bottom:2px solid var(--border-table); min-width:130px">Dokter Kolab</th>
                            <th class="px-4 py-3.5 text-left" style="color:var(--table-th-color); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; border-bottom:2px solid var(--border-table); min-width:110px">Jaminan</th>
                            <th class="px-4 py-3.5 text-left" style="color:var(--table-th-color); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; border-bottom:2px solid var(--border-table); min-width:150px">Bed</th>
                            <th class="px-4 py-3.5 text-left cursor-pointer select-none" style="color:var(--table-th-color); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; border-bottom:2px solid var(--border-table); min-width:140px" @click="toggleSort('status')">
                                <span class="flex items-center gap-1">Status <span style="opacity:.5">{{ sortIcon('status') }}</span></span>
                            </th>
                            <th class="px-4 py-3.5 text-left cursor-pointer select-none" style="color:var(--table-th-color); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; border-bottom:2px solid var(--border-table); min-width:130px" @click="toggleSort('created_at')">
                                <span class="flex items-center gap-1">Waktu <span style="opacity:.5">{{ sortIcon('created_at') }}</span></span>
                            </th>
                            <th class="px-4 py-3.5 text-center w-12" style="border-bottom:2px solid var(--border-table)"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, idx) in antrianFiltered" :key="`${item.sumber}-${item.id}`"
                            @click="openModal('detail', item)"
                            class="cursor-pointer group"
                            style="border-bottom:1px solid var(--border-row); transition:background .15s ease, transform .15s ease, box-shadow .15s ease"
                            :style="`border-left:4px solid ${ss(item.status).dot}`"
                            @mouseenter="e => { e.currentTarget.style.background='var(--bg-row-hover)'; e.currentTarget.style.transform='translateY(-1px)'; e.currentTarget.style.boxShadow='0 3px 12px rgba(0,0,0,0.07)'; e.currentTarget.style.zIndex='1'; e.currentTarget.style.position='relative'; }"
                            @mouseleave="e => { e.currentTarget.style.background=''; e.currentTarget.style.transform=''; e.currentTarget.style.boxShadow=''; }">
                            <!-- # -->
                            <td class="px-4 py-4">
                                <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold"
                                    style="background:var(--bg-input); color:var(--text-muted); font-family:'DM Mono',monospace">
                                    {{ idx+1 }}
                                </span>
                            </td>
                            <!-- Pasien -->
                            <td class="px-4 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 text-sm font-bold"
                                        :style="`background:${gColor(item.jenis_kelamin)}18; color:${gColor(item.jenis_kelamin)}`">
                                        {{ gIcon(item.jenis_kelamin) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-semibold truncate" style="color:var(--text-primary); font-size:13.5px">{{ item.nama_pasien }}</p>
                                        <p class="font-mono mt-0.5" style="color:var(--text-muted); font-size:10.5px">{{ item.No_MR ?? 'No MR' }}</p>
                                    </div>
                                </div>
                            </td>
                            <!-- Jenis -->
                            <td class="px-4 py-4">
                                <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-lg whitespace-nowrap"
                                    :style="`background:${SRC[item.sumber]?.bg}; color:${SRC[item.sumber]?.color}`">
                                    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" :style="`background:${SRC[item.sumber]?.color}`"></span>
                                    {{ item.sumber_label }}
                                </span>
                            </td>
                            <!-- Diagnosa -->
                            <td class="px-4 py-4">
                                <p class="text-sm break-words whitespace-normal" style="color:var(--text-primary); font-size:12px; max-width:180px" :title="item.diagnosa">{{ item.diagnosa ?? '—' }}</p>
                            </td>
                            <!-- Asal Ruang + DPJP -->
                            <td class="px-4 py-4" style="max-width:130px">
                                <p class="text-sm break-words whitespace-normal" style="color:var(--text-secondary)">{{ item.asal_ruang ?? item.asal_rujukan ?? '—' }}</p>
                                <p v-if="item.Dokter" class="text-sm break-words whitespace-normal" style="color:var(--text-muted)">{{ item.Dokter }}</p>
                            </td>
                            <!-- Dokter Kolab -->
                            <td class="px-5 py-4" style="max-width:160px">
                                <p v-if="item.dokter_kolab && item.dokter_kolab.length > 0" class="text-sm break-words whitespace-normal" :title="item.dokter_kolab.map(d => `${d.nama} (${d.ket})`).join(', ')" style="color:var(--text-primary)">
                                    {{ item.dokter_kolab.map(d => `${d.nama} (${d.ket})`).join(', ') }}
                                </p>
                                <span v-else style="color:var(--text-muted)">—</span>
                            </td>
                            <!-- Jaminan -->
                            <td class="px-4 py-4">
                                <span v-if="item.jaminan" class="text-xs font-semibold px-2.5 py-1 rounded-lg"
                                    style="background:#D1FAF0; color:#00A884">{{ jaminanLabel(item.jaminan) }}</span>
                                <span v-else class="text-xs" style="color:var(--text-muted)">—</span>
                            </td>
                            <!-- Bed -->
                            <td class="px-4 py-4">
                                <div v-if="item.nama_bed" class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0"
                                        style="background:#EBF9F1; color:#00A884; font-size:14px">🏥</div>
                                    <span class="font-semibold text-xs" style="color:#00A884">{{ item.nama_bed }}</span>
                                </div>
                                <span v-else class="text-xs" style="color:var(--text-muted)">Belum dialokasi</span>
                            </td>
                            <!-- Status -->
                            <td class="px-4 py-4">
                                <span class="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-xl whitespace-nowrap"
                                    :style="`background:${ss(item.status).bg}; color:${ss(item.status).color}`">
                                    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" :style="`background:${ss(item.status).color}`"></span>
                                    {{ item.status_label }}
                                </span>
                                <!-- Estimasi waiting list -->
                                <p v-if="item.status === 'waiting_list' && item.waiting_estimasi_fmt"
                                    class="text-xs mt-1 font-mono flex items-center gap-1" style="color:#D97706">
                                    <svg class="w-3 h-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Est. {{ item.waiting_estimasi_fmt }}
                                </p>
                            </td>
                            <!-- Waktu -->
                            <td class="px-4 py-4">
                                <p class="font-mono text-xs" style="color:var(--text-secondary)">{{ item.created_at_fmt }}</p>
                            </td>
                            <!-- Arrow -->
                            <td class="px-4 py-4 text-center">
                                <div class="w-7 h-7 rounded-lg flex items-center justify-center mx-auto transition-all group-hover:translate-x-0.5"
                                    style="background:var(--bg-input); color:var(--text-muted)">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- TAMPILAN MOBILE — Kartu vertikal -->
            <div class="block md:hidden divide-y" style="border-color:var(--border-default)">
                <div v-for="(item, idx) in antrianFiltered" :key="`mob-${item.sumber}-${item.id}`"
                    @click="openModal('detail', item)"
                    class="p-5 cursor-pointer relative"
                    style="border-left:4px solid transparent; transition:background .15s ease"
                    :style="`border-left-color:${ss(item.status).dot}`"
                    @mouseenter="e => e.currentTarget.style.background='var(--bg-row-hover)'"
                    @mouseleave="e => e.currentTarget.style.background=''">
                    
                    <div class="flex justify-between items-start mb-3 gap-2 pr-6">
                        <div class="flex flex-wrap gap-1.5">
                            <span class="inline-flex items-center gap-1 text-xs font-bold px-2.5 py-1 rounded-lg" :style="`background:${ss(item.status).bg}; color:${ss(item.status).color}`">
                                <span class="w-1.5 h-1.5 rounded-full" :style="`background:${ss(item.status).color}`"></span>
                                {{ item.status_label }}
                            </span>
                            <span class="text-xs font-semibold px-2 py-1 rounded-lg" :style="`background:${SRC[item.sumber]?.bg}; color:${SRC[item.sumber]?.color}`">{{ item.sumber_label }}</span>
                        </div>
                        <span class="text-xs font-mono whitespace-nowrap" style="color:var(--text-muted)">{{ item.created_at_fmt?.split(' ')[0] }}</span>
                    </div>

                    <div class="flex items-center gap-3 mb-3 pr-6">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 text-base font-bold"
                            :style="`background:${gColor(item.jenis_kelamin)}18; color:${gColor(item.jenis_kelamin)}`">
                            {{ gIcon(item.jenis_kelamin) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-sm truncate" style="color:var(--text-primary)">{{ item.nama_pasien }}</p>
                            <p class="font-mono text-xs truncate" style="color:var(--text-muted)">{{ item.No_MR ?? '—' }} · {{ jaminanLabel(item.jaminan) }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 text-xs pr-6">
                        <div>
                            <p class="font-medium mb-0.5" style="color:var(--text-muted)">Diagnosa</p>
                            <p class="font-semibold truncate" style="color:var(--text-secondary)">{{ item.diagnosa ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="font-medium mb-0.5" style="color:var(--text-muted)">Bed</p>
                            <p class="font-semibold truncate" :style="item.nama_bed ? 'color:#00A884' : 'color:var(--text-muted)'">{{ item.nama_bed ? '🏥 ' + item.nama_bed : 'Belum dialokasi' }}</p>
                        </div>
                    </div>

                    <div class="absolute right-4 top-1/2 -translate-y-1/2 w-7 h-7 rounded-lg flex items-center justify-center"
                        style="background:var(--bg-input); color:var(--text-muted)">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="px-5 py-3.5 flex items-center justify-between" style="border-top:1px solid var(--border-default); background:var(--bg-surface-2)">
                <p class="text-xs" style="color:var(--text-secondary)">
                    Menampilkan <strong style="color:var(--text-primary)">{{ antrianFiltered.length }}</strong> data
                </p>
            </div>
        </div>
    </div>


    <!-- ══════════════════════════════════════════════════════════════════
         MODAL — pola satu container + inner Transition mode="out-in"
         persis Menu ICU → smooth crossfade, tidak ada pop/jump
    ══════════════════════════════════════════════════════════════════════ -->
    <Transition enter-active-class="transition-all duration-300 ease-out" enter-from-class="opacity-0" leave-to-class="opacity-0">
        <div v-if="modal.open"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
            style="background:rgba(0,0,0,0.65); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px)"
            @click.self="closeModal">

            <!-- Satu container tetap — ukuran berubah soft via CSS transition -->
            <div class="w-full flex flex-col relative overflow-hidden"
                :class="modal.type === 'booking' ? 'max-w-2xl' : modal.type === 'verifikasi' ? 'max-w-md' : 'max-w-lg'"
                style="max-height:92vh; background:var(--bg-surface); border:1px solid var(--border-default); border-radius:20px; box-shadow:0 25px 60px rgba(0,0,0,0.25), 0 8px 24px rgba(0,0,0,0.15); transition:max-width .25s ease"
                @click.stop>

                <!-- Inner Transition mode="out-in" → konten lama fade-out dulu, baru fade-in -->
                <Transition
                    enter-active-class="transition-all duration-220 ease-out"
                    enter-from-class="opacity-0 translate-y-2"
                    leave-active-class="transition-all duration-150 ease-in"
                    leave-to-class="opacity-0 -translate-y-1"
                    mode="out-in">

                <!-- ── VIEW: DETAIL ─────────────────────────────────────────── -->
                <div v-if="modal.type==='detail' && modal.item" key="detail" class="flex flex-col w-full" style="max-height:92vh">
                    <div class="flex items-start justify-between px-6 py-5 flex-shrink-0" style="border-bottom:1px solid var(--border-default)">
                        <div class="flex-1 min-w-0 pr-3">
                            <div class="flex items-center gap-2 flex-wrap mb-2">
                                <span class="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-full"
                                    :style="`background:${ss(modal.item.status).bg}; color:${ss(modal.item.status).color}`">
                                    <span class="w-1.5 h-1.5 rounded-full" :style="`background:${ss(modal.item.status).dot}`"></span>
                                    {{ modal.item.status_label }}
                                </span>
                                <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full"
                                    :style="`background:${SRC[modal.item.sumber]?.bg}; color:${SRC[modal.item.sumber]?.color}`">
                                    {{ modal.item.sumber_label }}
                                </span>
                            </div>
                            <h2 class="text-base font-bold truncate" style="color:var(--text-primary)">{{ modal.item.nama_pasien }}</h2>
                            <p class="text-xs mt-0.5" style="color:var(--text-secondary)">Detail Antrian Pasien</p>
                        </div>
                        <button @click="closeModal" class="w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0 transition-all hover:scale-110" style="background:var(--bg-input); color:var(--text-secondary)">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="overflow-y-auto flex-1 px-6 py-5 space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-0.5">
                                <p class="text-xs font-medium" style="color:var(--text-muted)">No. MR / Identitas</p>
                                <p class="text-sm font-bold font-mono" style="color:var(--text-primary)">{{ modal.item.No_MR ?? modal.item.no_identitas ?? '—' }}</p>
                            </div>
                            <div class="space-y-0.5">
                                <p class="text-xs font-medium" style="color:var(--text-muted)">Jenis Kelamin</p>
                                <p class="text-sm font-bold flex items-center gap-1.5" style="color:var(--text-primary)">
                                    <span :style="`color:${gColor(modal.item.jenis_kelamin)}`">{{ gIcon(modal.item.jenis_kelamin) }}</span>
                                    {{ modal.item.jenis_kelamin==='L'?'Pria':modal.item.jenis_kelamin==='P'?'Wanita':'—' }}
                                </p>
                            </div>
                            <div class="sm:col-span-2 space-y-0.5">
                                <p class="text-xs font-medium" style="color:var(--text-muted)">Diagnosa / Indikasi</p>
                                <p class="text-sm font-bold" style="color:var(--text-primary)">{{ modal.item.diagnosa ?? '—' }}</p>
                            </div>
                            <div class="sm:col-span-2 space-y-0.5">
                                <p class="text-xs font-medium" style="color:var(--text-muted)">Dokter Kolab</p>
                                <p class="text-sm font-bold" style="color:var(--text-primary)"> {{ modal.item.dokter_kolab && modal.item.dokter_kolab.length > 0 ? modal.item.dokter_kolab.map(d => `${d.nama} (${d.ket})`).join(', ') : '—' }}
                                </p>
                            </div>
                            <div class="space-y-0.5">
                                <p class="text-xs font-medium" style="color:var(--text-muted)">Indikasi Rawat ICU</p>
                                <p class="text-sm font-bold" style="color:var(--text-primary)">{{ modal.item.IndikasiRI ?? '—' }}</p>
                            </div>
                            <div class="space-y-0.5">
                                <p class="text-xs font-medium" style="color:var(--text-muted)">DPJP</p>
                                <p class="text-sm font-bold" style="color:var(--text-primary)">{{ modal.item.Dokter ?? '—' }}</p>
                            </div>
                            <div class="space-y-0.5">
                                <p class="text-xs font-medium" style="color:var(--text-muted)">Jaminan</p>
                                <p class="text-sm font-bold" style="color:var(--text-primary)">{{ jaminanLabel(modal.item.jaminan) }}</p>
                            </div>
                            <div class="space-y-0.5">
                                <p class="text-xs font-medium" style="color:var(--text-muted)">Asal Rujukan</p>
                                <p class="text-sm font-bold" style="color:var(--text-primary)">{{ modal.item.asal_rujukan ?? '—' }}</p>
                            </div>
                            <div class="space-y-0.5">
                                <p class="text-xs font-medium" style="color:var(--text-muted)">Alokasi Bed</p>
                                <p class="text-sm font-bold flex items-center gap-1.5" style="color:#00A884">
                                    <svg v-if="modal.item.nama_bed" class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                                    </svg>
                                    {{ modal.item.nama_bed ?? '—' }}
                                </p>
                                <p v-if="modal.item.kebutuhan_bed" class="text-xs mt-0.5" style="color:var(--text-muted)">{{ modal.item.kebutuhan_bed }}</p>
                            </div>
                            <div class="space-y-0.5">
                                <p class="text-xs font-medium" style="color:var(--text-muted)">Waktu Booking</p>
                                <p class="text-sm font-bold font-mono" style="color:var(--text-primary)">{{ modal.item.created_at_fmt }}</p>
                            </div>
                        </div>

                        <!-- ── Timeline Aksi ──────────────────────────────────────── -->
                        <div class="rounded-xl overflow-hidden" style="border:1px solid var(--border-default)">
                            <div class="px-4 py-2.5" style="background:var(--bg-surface-2); border-bottom:1px solid var(--border-default)">
                            <p class="text-xs font-bold uppercase tracking-wider" style="color:var(--text-muted)">Timeline Proses</p>
                            </div>
                            <div class="px-4 py-3 space-y-2.5">
                            <!-- Booking dibuat -->
                            <div class="flex items-start gap-3">
                                <div class="w-2 h-2 rounded-full mt-1.5 flex-shrink-0" style="background:#00A884"></div>
                                <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold" style="color:var(--text-primary)">Booking dibuat</p>
                                <p class="text-xs font-mono" style="color:var(--text-muted)">{{ modal.item.created_at_fmt ?? '—' }}</p>
                                <p v-if="modal.item.created_by" class="text-xs" style="color:var(--text-muted)">oleh {{ modal.item.created_by }}</p>
                                </div>
                            </div>
                            <!-- Konfirmasi bed (external) / Approve admisi (internal) -->
                            <div v-if="modal.item.sumber === 'external' && (modal.item.confirmed_at_fmt || modal.item.confirmed_by)"
                                class="flex items-start gap-3">
                                <div class="w-2 h-2 rounded-full mt-1.5 flex-shrink-0" style="background:#0EA5E9"></div>
                                <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold" style="color:var(--text-primary)">Bed dikonfirmasi ICU</p>
                                <p class="text-xs font-mono" style="color:var(--text-muted)">{{ modal.item.confirmed_at_fmt ?? '—' }}</p>
                                <p v-if="modal.item.confirmed_by" class="text-xs" style="color:var(--text-muted)">oleh {{ modal.item.confirmed_by }}</p>
                                </div>
                            </div>
                            <div v-if="modal.item.sumber === 'internal' && (modal.item.approved_at_fmt || modal.item.approved_by)"
                                class="flex items-start gap-3">
                                <div class="w-2 h-2 rounded-full mt-1.5 flex-shrink-0" style="background:#0EA5E9"></div>
                                <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold" style="color:var(--text-primary)">Disetujui Admisi</p>
                                <p class="text-xs font-mono" style="color:var(--text-muted)">{{ modal.item.approved_at_fmt ?? '—' }}</p>
                                <p v-if="modal.item.approved_by" class="text-xs" style="color:var(--text-muted)">oleh {{ modal.item.approved_by }}</p>
                                </div>
                            </div>
                            <!-- Verifikasi bed -->
                            <div v-if="modal.item.verified_at_fmt || modal.item.verified_by"
                                class="flex items-start gap-3">
                                <div class="w-2 h-2 rounded-full mt-1.5 flex-shrink-0" style="background:#059669"></div>
                                <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold" style="color:var(--text-primary)">
                                    {{ modal.item.sumber === 'external' ? 'Pasien terverifikasi Admisi' : 'Bed terverifikasi ICU' }}
                                </p>
                                <p class="text-xs font-mono" style="color:var(--text-muted)">{{ modal.item.verified_at_fmt ?? '—' }}</p>
                                <p v-if="modal.item.verified_by" class="text-xs" style="color:var(--text-muted)">oleh {{ modal.item.verified_by }}</p>
                                </div>
                            </div>
                            <!-- Durasi total -->
                            <div class="pt-1 border-t" style="border-color:var(--border-default)">
                                <p class="text-xs" style="color:var(--text-muted)">
                                Lama proses:
                                <strong style="color:var(--text-primary)">
                                    {{ modal.item.lama_proses || '—' }}
                                </strong>
                                </p>
                            </div>
                            </div>
                        </div>
                        
                        <div v-if="modal.item.alasan_tolak" class="rounded-xl p-4 space-y-1.5" style="background:rgba(231,76,60,.06); border:1.5px solid rgba(231,76,60,.2)">
                            <p class="text-xs font-bold flex items-center gap-1.5" style="color:#E74C3C">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                Alasan Penolakan
                            </p>
                            <p class="text-sm" style="color:var(--text-primary)">{{ modal.item.alasan_tolak }}</p>
                        </div>

                        <!-- ── Info Pembatalan ──────────────────────────── -->
                        <div v-if="modal.item.status === 'dibatalkan'" class="rounded-xl p-4 space-y-2" style="background:rgba(107,114,128,.06); border:1.5px solid rgba(107,114,128,.25)">
                            <p class="text-xs font-bold flex items-center gap-1.5" style="color:#6B7280">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                </svg>
                                Info Pembatalan
                            </p>
                            <p v-if="modal.item.alasan_batal" class="text-sm font-semibold" style="color:#374151">
                                {{ modal.item.alasan_batal }}
                            </p>
                            <p v-else class="text-xs italic" style="color:var(--text-muted)">Tidak ada catatan pembatalan</p>
                            <p v-if="modal.item.dibatalkan_by" class="text-xs" style="color:var(--text-muted)">
                                Dibatalkan oleh <strong>{{ modal.item.dibatalkan_by }}</strong>
                                <span v-if="modal.item.dibatalkan_at_fmt"> · {{ modal.item.dibatalkan_at_fmt }}</span>
                            </p>
                        </div>

                        <!-- ── Waiting List Banner (Admisi view) ──────── -->
                        <div v-if="modal.item.status === 'waiting_list'"
                            class="rounded-xl overflow-hidden" style="border:2px solid #FCD34D">
                            <div class="flex items-center gap-3 px-4 py-3" style="background:#FEF3C7">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" style="background:#FDE68A">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="#D97706" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs font-black uppercase tracking-wide" style="color:#D97706">Waiting List ICU</p>
                                    <p class="text-xs" style="color:#92400E">Pasien dalam antrian — bed belum tersedia saat ini</p>
                                </div>
                            </div>
                            <div class="px-4 py-3 space-y-2" style="background:#FFFBEB">
                                <div v-if="modal.item.waiting_alasan">
                                    <p class="text-xs font-semibold mb-0.5" style="color:#92400E">Keterangan dari ICU</p>
                                    <p class="text-sm" style="color:#78350F">{{ modal.item.waiting_alasan }}</p>
                                </div>
                                <div v-if="modal.item.waiting_estimasi_fmt"
                                    class="rounded-lg px-3 py-2.5 flex items-center gap-3"
                                    style="background:#FDE68A; border:1px solid #FCD34D">
                                    <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="#D97706" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <div>
                                        <p class="text-xs font-semibold" style="color:#92400E">Estimasi Bed Siap</p>
                                        <p class="text-base font-black font-mono" style="color:#D97706">{{ modal.item.waiting_estimasi_fmt }}</p>
                                    </div>
                                </div>
                                <p v-if="modal.item.waiting_by" class="text-xs" style="color:#A16207">
                                    Diproses oleh ICU: <strong>{{ modal.item.waiting_by }}</strong>
                                </p>
                            </div>
                        </div>
                        <div v-if="modal.item.catatan_admisi" class="rounded-xl p-4 space-y-1" style="background:var(--bg-surface-2); border:1px solid var(--border-default)">
                            <p class="text-xs font-medium" style="color:var(--text-muted)">Catatan Admisi</p>
                            <p class="text-sm" style="color:var(--text-primary)">{{ modal.item.catatan_admisi }}</p>
                        </div>
                    </div>
                    <div class="px-6 py-5 flex-shrink-0 space-y-3" style="border-top:1px solid var(--border-default); background:var(--bg-surface-2)">
                        <p class="text-xs font-bold uppercase tracking-widest" style="color:var(--text-muted)">Tindakan Tersedia</p>
                        <div class="flex flex-col gap-2.5">
                            <template v-for="act in actionsOf(modal.item)" :key="act.id">
                                <button @click="act.id==='verifikasi' ? openVerifModal(modal.item) : act.id==='edit' ? openEditModal(modal.item) : act.id==='batal' ? openBatalModal(modal.item) : act.id==='hapus' ? hapusBooking(modal.item) : openModal(act.id, modal.item)"
                                    class="w-full text-sm font-bold py-3 rounded-xl flex items-center justify-center transition-all duration-150 hover:-translate-y-px hover:brightness-105"
                                    :style="`background:${act.bg}; color:${act.color}; border:1.5px solid ${act.border}`">
                                    {{ act.label }}
                                </button>
                            </template>
                            <p v-if="!actionsOf(modal.item).length" class="text-sm py-1" style="color:var(--text-muted)">Tidak ada aksi yang tersedia untuk status ini.</p>
                        </div>
                    </div>
                </div>

                <!-- ── VIEW: BOOKING BARU ───────────────────────────────────── -->
                <div v-else-if="modal.type==='booking'" key="booking" class="flex flex-col w-full" style="max-height:92vh">
                    <div class="flex items-center justify-between px-6 py-5 flex-shrink-0" style="border-bottom:1px solid var(--border-default)">
                        <div>
                            <h2 class="text-base font-bold" style="color:var(--text-primary)">Booking ICU Baru</h2>
                            <p class="text-xs mt-0.5" style="color:var(--text-secondary)">Pasien eksternal — akan dikirim ke ICU</p>
                        </div>
                        <button @click="closeModal" class="w-8 h-8 rounded-xl flex items-center justify-center transition-all hover:scale-110" style="background:var(--bg-input); color:var(--text-secondary)">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="overflow-y-auto flex-1">
                        <form @submit.prevent="submitBooking" class="p-6 space-y-6">
                            <!-- Identitas -->
                            <div class="space-y-3">
                                <p class="text-xs font-bold uppercase tracking-widest" style="color:var(--text-accent)">Identitas Pasien</p>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div class="sm:col-span-2 space-y-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Nama Pasien <span style="color:#E74C3C">*</span></label>
                                        <input v-model="fmBooking.nama_pasien" required placeholder="Nama lengkap" class="w-full rounded-xl outline-none"
                                            style="padding:10px 14px; font-size:13px"
                                            :style="`border:1.5px solid ${fmBooking.errors.nama_pasien?'#E74C3C':'var(--border-default)'}; background:var(--bg-input); color:var(--text-primary)`"/>
                                        <p v-if="fmBooking.errors.nama_pasien" class="text-xs" style="color:#E74C3C">{{ fmBooking.errors.nama_pasien }}</p>
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Jenis Kelamin <span style="color:#E74C3C">*</span></label>
                                        <div class="flex gap-2">
                                            <button type="button" @click="fmBooking.jenis_kelamin='L'" class="flex-1 py-2.5 rounded-xl text-sm font-semibold transition-colors"
                                                :style="fmBooking.jenis_kelamin==='L'?'background:#00A884;color:#fff;border:2px solid #00A884':'background:var(--bg-input);color:var(--text-secondary);border:2px solid var(--border-default)'">♂ Pria</button>
                                            <button type="button" @click="fmBooking.jenis_kelamin='P'" class="flex-1 py-2.5 rounded-xl text-sm font-semibold transition-colors"
                                                :style="fmBooking.jenis_kelamin==='P'?'background:#8E44AD;color:#fff;border:2px solid #8E44AD':'background:var(--bg-input);color:var(--text-secondary);border:2px solid var(--border-default)'">♀ Wanita</button>
                                        </div>
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">No. Identitas / NIK</label>
                                        <input v-model="fmBooking.no_identitas" placeholder="NIK / sementara" class="w-full rounded-xl outline-none font-mono"
                                            style="padding:10px 14px; font-size:13px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary)"/>
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Asal Rujukan</label>
                                        <input v-model="fmBooking.asal_rujukan" placeholder="RS / klinik pengirim" class="w-full rounded-xl outline-none"
                                            style="padding:10px 14px; font-size:13px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary)"/>
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">No. Telp Keluarga</label>
                                        <input v-model="fmBooking.no_telp_keluarga" placeholder="08xx-xxxx" class="w-full rounded-xl outline-none font-mono"
                                            style="padding:10px 14px; font-size:13px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary)"/>
                                    </div>
                                </div>
                            </div>
                            <!-- Klinis -->
                            <div class="space-y-3">
                            <p class="text-xs font-bold uppercase tracking-widest" style="color:var(--text-accent)">Data Klinis</p>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <!-- Diagnosa - full width -->
                                <div class="sm:col-span-2 space-y-1.5">
                                <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">
                                    Diagnosa Rawat ICU <span style="color:#E74C3C">*</span>
                                </label>
                                <input
                                    v-model="fmBooking.diagnosa"
                                    required
                                    placeholder="Tulis diagnosa pasien untuk rawat ICU..."
                                    class="w-full rounded-xl outline-none"
                                    style="padding:10px 14px; font-size:13px"
                                    :style="`border:1.5px solid ${fmBooking.errors.diagnosa ? '#E74C3C' : 'var(--border-default)'}; background:var(--bg-input); color:var(--text-primary)`"
                                />
                                <p v-if="fmBooking.errors.diagnosa" class="text-xs" style="color:#E74C3C">{{ fmBooking.errors.diagnosa }}</p>
                                </div>

                                <!-- ICD-10 & Rencana Tindakan - berdampingan di sm+, stack di mobile -->
                                <div class="space-y-1.5">
                                <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">
                                    Kode ICD-10
                                    <span class="ml-1 normal-case font-normal text-xs px-2 py-0.5 rounded-full" style="background:rgba(14,165,233,.1);color:#0EA5E9">
                                    Untuk Klaim / Coding
                                    </span>
                                </label>
                                <Icd10Search
                                    v-model="fmBooking.diagnosa_icd"
                                    placeholder="Cari kode ICD-10 (opsional)..."
                                    :required="false"
                                    :has-error="false"
                                />
                                <p class="text-xs" style="color:var(--text-muted)">Opsional diisi untuk keperluan klaim BPJS</p>
                                </div>

                                <div class="space-y-1.5">
                                <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">
                                    Rencana Tindakan <span style="color:#E74C3C">*</span>
                                </label>
                                <input
                                    v-model="fmBooking.rencana_tindakan"
                                    required
                                    placeholder="Rencana tindakan ICU"
                                    class="w-full rounded-xl outline-none"
                                    style="padding:10px 14px; font-size:13px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary)"
                                />
                                </div>

                                <!-- Keterangan Klinis - full width -->
                                <div class="sm:col-span-2 space-y-1.5">
                                <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Keterangan Klinis</label>
                                <textarea
                                    v-model="fmBooking.keterangan"
                                    rows="2"
                                    placeholder="Kondisi, riwayat, catatan dokter pengirim..."
                                    class="w-full rounded-xl outline-none resize-y"
                                    style="padding:10px 14px; font-size:13px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); line-height:1.6"
                                ></textarea>
                                </div>
                            </div>
                            </div>
                            <!-- Jaminan -->
                            <div class="space-y-3">
                                <p class="text-xs font-bold uppercase tracking-widest" style="color:var(--text-accent)">Jaminan</p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Jenis Jaminan <span style="color:#E74C3C">*</span></label>
                                        <select v-model="fmBooking.jaminan" required class="w-full rounded-xl outline-none"
                                            style="padding:10px 14px; font-size:13px"
                                            :style="`border:1.5px solid ${fmBooking.errors.jaminan?'#E74C3C':'var(--border-default)'}; background:var(--bg-input); color:${fmBooking.jaminan?'var(--text-primary)':'var(--text-muted)'}`">
                                            <option value="" disabled>— Pilih Jaminan —</option>
                                            <option v-for="cb in caraBayar" :key="cb.kode" :value="cb.kode">{{ cb.nama }}</option>
                                        </select>
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Catatan Jaminan</label>
                                        <input v-model="fmBooking.catatan_jaminan" placeholder="No. BPJS / No. Polis..." class="w-full rounded-xl outline-none"
                                            style="padding:10px 14px; font-size:13px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary)"/>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 pt-2" style="border-top:1px solid var(--border-default)">
                                <button type="submit" :disabled="fmBooking.processing || !fmBooking.jenis_kelamin || !fmBooking.jaminan"
                                    class="flex items-center gap-2 font-bold px-6 py-3 rounded-xl transition-all duration-150 disabled:opacity-50 hover:-translate-y-px"
                                    style="background:#00A884; color:var(--text-on-accent); font-size:14px">
                                    <svg v-if="fmBooking.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    {{ fmBooking.processing ? 'Menyimpan...' : 'Request ke ICU/HCU' }}
                                </button>
                                <button type="button" @click="closeModal" class="px-6 py-3 rounded-xl font-medium"
                                    style="background:var(--bg-input); color:var(--text-secondary); border:1.5px solid var(--border-default); font-size:14px">Batal</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ── VIEW: APPROVE SPRI ───────────────────────────────────── -->
                <!-- ── VIEW: EDIT BOOKING ─────────────────────────────────────── -->
                <div v-else-if="modal.type==='edit' && modal.item" key="edit" class="flex flex-col w-full" style="max-height:92vh">
                    <div class="flex items-center justify-between px-6 py-5 flex-shrink-0" style="border-bottom:1px solid var(--border-default)">
                        <div class="flex items-center gap-3">
                            <button type="button" @click="openModal('detail', modal.item)" class="w-8 h-8 rounded-xl flex items-center justify-center transition-all hover:scale-110" style="background:var(--bg-input); color:var(--text-secondary)">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <div>
                                <h2 class="text-base font-bold" style="color:var(--text-primary)">Edit Booking</h2>
                                <p class="text-xs mt-0.5" style="color:var(--text-secondary)">{{ modal.item.nama_pasien }}</p>
                            </div>
                        </div>
                        <button @click="closeModal" class="w-8 h-8 rounded-xl flex items-center justify-center transition-all hover:scale-110" style="background:var(--bg-input); color:var(--text-secondary)">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="overflow-y-auto flex-1">
                        <form @submit.prevent="submitEdit" class="p-6 space-y-6">
                            <!-- Identitas -->
                            <div class="space-y-3">
                                <p class="text-xs font-bold uppercase tracking-widest" style="color:var(--text-accent)">Identitas Pasien</p>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div class="sm:col-span-2 space-y-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Nama Pasien <span style="color:#E74C3C">*</span></label>
                                        <input v-model="fmEdit.nama_pasien" required placeholder="Nama lengkap" class="w-full rounded-xl outline-none"
                                            style="padding:10px 14px; font-size:13px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary)"/>
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Jenis Kelamin <span style="color:#E74C3C">*</span></label>
                                        <div class="flex gap-2">
                                            <button type="button" @click="fmEdit.jenis_kelamin='L'" class="flex-1 py-2.5 rounded-xl text-sm font-semibold transition-colors"
                                                :style="fmEdit.jenis_kelamin==='L'?'background:#00A884;color:#fff;border:2px solid #00A884':'background:var(--bg-input);color:var(--text-secondary);border:2px solid var(--border-default)'">♂ Pria</button>
                                            <button type="button" @click="fmEdit.jenis_kelamin='P'" class="flex-1 py-2.5 rounded-xl text-sm font-semibold transition-colors"
                                                :style="fmEdit.jenis_kelamin==='P'?'background:#8E44AD;color:#fff;border:2px solid #8E44AD':'background:var(--bg-input);color:var(--text-secondary);border:2px solid var(--border-default)'">♀ Wanita</button>
                                        </div>
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">No. Identitas / NIK</label>
                                        <input v-model="fmEdit.no_identitas" placeholder="NIK / sementara" class="w-full rounded-xl outline-none font-mono"
                                            style="padding:10px 14px; font-size:13px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary)"/>
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Asal Rujukan</label>
                                        <input v-model="fmEdit.asal_rujukan" placeholder="RS / klinik pengirim" class="w-full rounded-xl outline-none"
                                            style="padding:10px 14px; font-size:13px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary)"/>
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">No. Telp Keluarga</label>
                                        <input v-model="fmEdit.no_telp_keluarga" placeholder="08xx-xxxx" class="w-full rounded-xl outline-none font-mono"
                                            style="padding:10px 14px; font-size:13px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary)"/>
                                    </div>
                                </div>
                            </div>
                            <!-- Klinis -->
                            <div class="space-y-3">
                                <p class="text-xs font-bold uppercase tracking-widest" style="color:var(--text-accent)">Data Klinis</p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div class="sm:col-span-2 space-y-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Diagnosa Rawat ICU <span style="color:#E74C3C">*</span></label>
                                        <input v-model="fmEdit.diagnosa" required placeholder="Tulis diagnosa pasien untuk rawat ICU..." class="w-full rounded-xl outline-none"
                                            style="padding:10px 14px; font-size:13px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary)"/>
                                    </div>
                                    <div class="sm:col-span-2 space-y-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">
                                            Kode ICD-10
                                            <span class="ml-1 normal-case font-normal text-xs px-2 py-0.5 rounded-full" style="background:rgba(14,165,233,.1);color:#0EA5E9">Untuk Klaim / Coding</span>
                                        </label>
                                        <Icd10Search v-model="fmEdit.diagnosa_icd" placeholder="Cari kode ICD-10 (opsional)..." :required="false" :has-error="false"/>
                                        <p class="text-xs" style="color:var(--text-muted)">Opsional — diisi untuk keperluan klaim BPJS / asuransi</p>
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Rencana Tindakan <span style="color:#E74C3C">*</span></label>
                                        <input v-model="fmEdit.rencana_tindakan" required placeholder="Rencana tindakan ICU" class="w-full rounded-xl outline-none"
                                            style="padding:10px 14px; font-size:13px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary)"/>
                                    </div>
                                    <div class="sm:col-span-2 space-y-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Keterangan Klinis</label>
                                        <textarea v-model="fmEdit.keterangan" rows="2" placeholder="Kondisi, riwayat, catatan dokter pengirim..." class="w-full rounded-xl outline-none resize-none"
                                            style="padding:10px 14px; font-size:13px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); line-height:1.6"/>
                                    </div>
                                </div>
                            </div>
                            <!-- Jaminan -->
                            <div class="space-y-3">
                                <p class="text-xs font-bold uppercase tracking-widest" style="color:var(--text-accent)">Jaminan</p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Jenis Jaminan <span style="color:#E74C3C">*</span></label>
                                        <select v-model="fmEdit.jaminan" required class="w-full rounded-xl outline-none"
                                            style="padding:10px 14px; font-size:13px"
                                            :style="`border:1.5px solid var(--border-default); background:var(--bg-input); color:${fmEdit.jaminan?'var(--text-primary)':'var(--text-muted)'}`">
                                            <option value="" disabled>— Pilih Jaminan —</option>
                                            <option v-for="cb in caraBayar" :key="cb.kode" :value="cb.kode">{{ cb.nama }}</option>
                                        </select>
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Catatan Jaminan</label>
                                        <input v-model="fmEdit.catatan_jaminan" placeholder="No. BPJS / No. Polis..." class="w-full rounded-xl outline-none"
                                            style="padding:10px 14px; font-size:13px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary)"/>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 pt-2" style="border-top:1px solid var(--border-default)">
                                <button type="submit" :disabled="fmEdit.processing || !fmEdit.jenis_kelamin || !fmEdit.jaminan"
                                    class="flex items-center gap-2 font-bold px-6 py-3 rounded-xl transition-all duration-150 disabled:opacity-50 hover:-translate-y-px"
                                    style="background:#0EA5E9; color:#fff; font-size:14px">
                                    <svg v-if="fmEdit.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    {{ fmEdit.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                                </button>
                                <button type="button" @click="openModal('detail', modal.item)" class="px-6 py-3 rounded-xl font-medium"
                                    style="background:var(--bg-input); color:var(--text-secondary); border:1.5px solid var(--border-default); font-size:14px">Kembali</button>
                            </div>
                        </form>
                    </div>
                </div>                <!-- ══ MODAL: EDIT BOOKING INTERNAL ══════════════════════════════ -->
                <div v-else-if="modal.type==='edit_internal' && modal.item" key="edit_internal" class="flex flex-col w-full" style="max-height:92vh">
                    <div class="flex items-center justify-between px-6 py-5 flex-shrink-0" style="border-bottom:1px solid var(--border-default)">
                        <div class="flex items-center gap-3">
                            <button type="button" @click="openModal('detail', modal.item)" class="w-8 h-8 rounded-xl flex items-center justify-center transition-all hover:scale-110" style="background:var(--bg-input); color:var(--text-secondary)">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <div>
                                <h2 class="text-base font-bold" style="color:var(--text-primary)">Edit Booking Internal</h2>
                                <p class="text-xs mt-0.5" style="color:var(--text-secondary)">{{ modal.item.nama_pasien }}</p>
                            </div>
                        </div>
                        <button @click="closeModal" class="w-8 h-8 rounded-xl flex items-center justify-center transition-all hover:scale-110" style="background:var(--bg-input); color:var(--text-secondary)">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="overflow-y-auto flex-1">
                        <form @submit.prevent="submitEditInternal" class="p-6 space-y-6">
                            <!-- Data Pasien -->
                            <div class="space-y-3">
                                <p class="text-xs font-bold uppercase tracking-widest" style="color:var(--text-accent)">Data Pasien</p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">No. MR <span style="color:#E74C3C">*</span></label>
                                        <input v-model="fmEditInternal.No_MR" required placeholder="No. MR" class="w-full rounded-xl outline-none font-mono"
                                            style="padding:10px 14px; font-size:13px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary)"/>
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">No. Reg <span style="color:#E74C3C">*</span></label>
                                        <input v-model="fmEditInternal.No_Reg" required placeholder="No. Registrasi" class="w-full rounded-xl outline-none font-mono"
                                            style="padding:10px 14px; font-size:13px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary)"/>
                                    </div>
                                </div>
                            </div>
                            <!-- Data Klinis -->
                            <div class="space-y-3">
                                <p class="text-xs font-bold uppercase tracking-widest" style="color:var(--text-accent)">Data Klinis</p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div class="sm:col-span-2 space-y-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Diagnosis <span style="color:#E74C3C">*</span></label>
                                        <input v-model="fmEditInternal.Diagnosis" required placeholder="Diagnosis pasien" class="w-full rounded-xl outline-none"
                                            style="padding:10px 14px; font-size:13px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary)"/>
                                    </div>
                                    <div class="sm:col-span-2 space-y-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">
                                            Kode ICD-10
                                            <span class="ml-1 normal-case font-normal text-xs px-2 py-0.5 rounded-full" style="background:rgba(14,165,233,.1);color:#0EA5E9">Opsional</span>
                                        </label>
                                        <Icd10Search v-model="fmEditInternal.Diagnosis_ICD" placeholder="Cari kode ICD-10 (opsional)..." :required="false" :has-error="false"/>
                                    </div>
                                    <div class="sm:col-span-2 space-y-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Indikasi Rawat Inap <span style="color:#E74C3C">*</span></label>
                                        <textarea v-model="fmEditInternal.IndikasiRI" required rows="2" placeholder="Indikasi pasien perlu dirawat di ICU..." class="w-full rounded-xl outline-none resize-none"
                                            style="padding:10px 14px; font-size:13px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); line-height:1.6"/>
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Asal Ruang</label>
                                        <input v-model="fmEditInternal.asal_ruang" placeholder="Ruang asal pasien" class="w-full rounded-xl outline-none"
                                            style="padding:10px 14px; font-size:13px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary)"/>
                                    </div>
                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Dokter DPJP</label>
                                        <input v-model="fmEditInternal.Dokter" placeholder="Nama dokter" class="w-full rounded-xl outline-none"
                                            style="padding:10px 14px; font-size:13px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary)"/>
                                    </div>
                                    <div class="sm:col-span-2 space-y-1.5">
                                        <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Keterangan</label>
                                        <textarea v-model="fmEditInternal.Keterangan" rows="2" placeholder="Catatan tambahan..." class="w-full rounded-xl outline-none resize-none"
                                            style="padding:10px 14px; font-size:13px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); line-height:1.6"/>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 pt-2" style="border-top:1px solid var(--border-default)">
                                <button type="submit" :disabled="fmEditInternal.processing || !fmEditInternal.No_MR || !fmEditInternal.No_Reg || !fmEditInternal.Diagnosis || !fmEditInternal.IndikasiRI"
                                    class="flex items-center gap-2 font-bold px-6 py-3 rounded-xl transition-all duration-150 disabled:opacity-50 hover:-translate-y-px"
                                    style="background:#0EA5E9; color:#fff; font-size:14px">
                                    <svg v-if="fmEditInternal.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    {{ fmEditInternal.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                                </button>
                                <button type="button" @click="openModal('detail', modal.item)" class="px-6 py-3 rounded-xl font-medium"
                                    style="background:var(--bg-input); color:var(--text-secondary); border:1.5px solid var(--border-default); font-size:14px">Kembali</button>
                            </div>
                        </form>
                    </div>
                </div>
                <div v-else-if="modal.type==='approve' && modal.item" key="approve" class="flex flex-col w-full" style="max-height:92vh">
                    <div class="flex items-center justify-between px-6 py-5 flex-shrink-0" style="border-bottom:1px solid var(--border-default)">
                        <div class="flex items-center gap-3">
                            <button type="button" @click="openModal('detail', modal.item)" class="w-8 h-8 rounded-xl flex items-center justify-center transition-all hover:scale-110" style="background:var(--bg-input); color:var(--text-secondary)">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <div>
                                <h2 class="text-base font-bold" style="color:var(--text-primary)">Setujui Booking ICU</h2>
                                <p class="text-xs mt-0.5" style="color:var(--text-secondary)">{{ modal.item.nama_pasien }}</p>
                            </div>
                        </div>
                        <button @click="closeModal" class="w-8 h-8 rounded-xl flex items-center justify-center transition-all hover:scale-110" style="background:var(--bg-input); color:var(--text-secondary)">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="px-6 py-4 grid grid-cols-2 gap-3 flex-shrink-0" style="border-bottom:1px solid var(--border-default); background:var(--bg-surface-2)">
                        <div class="rounded-xl p-3 space-y-0.5" style="background:var(--bg-input)">
                            <p class="text-xs" style="color:var(--text-muted)">No. MR</p>
                            <p class="text-sm font-bold font-mono" style="color:var(--text-primary)">{{ modal.item.No_MR ?? '—' }}</p>
                        </div>
                        <div class="rounded-xl p-3 space-y-0.5" style="background:var(--bg-input)">
                            <p class="text-xs" style="color:var(--text-muted)">Asal Ruang</p>
                            <p class="text-sm font-bold truncate" style="color:var(--text-primary)">{{ modal.item.asal_rujukan ?? '—' }}</p>
                        </div>
                        <div class="col-span-2 rounded-xl p-3 space-y-0.5" style="background:var(--bg-input)">
                            <p class="text-xs" style="color:var(--text-muted)">Diagnosa</p>
                            <p class="text-sm font-bold" style="color:var(--text-primary)">{{ modal.item.diagnosa ?? '—' }}</p>
                        </div>
                    </div>
                    <form @submit.prevent="submitApprove" class="px-6 py-5 space-y-4 overflow-y-auto flex-1">
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">
                                Catatan Admisi <span class="normal-case font-normal" style="color:var(--text-muted)">(opsional)</span>
                            </label>
                            <textarea v-model="fmApprove.catatan_admisi" rows="4" placeholder="Informasi jaminan, kondisi khusus, catatan untuk petugas ICU..." class="w-full rounded-xl outline-none resize-none"
                                style="padding:11px 14px; font-size:13px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); line-height:1.6"/>
                        </div>
                        <div class="flex items-center gap-3 pt-1" style="border-top:1px solid var(--border-default)">
                            <button type="submit" :disabled="fmApprove.processing"
                                class="flex-1 font-bold py-3 rounded-xl transition-all duration-150 disabled:opacity-50 hover:-translate-y-px"
                                style="background:#00A884; color:var(--text-on-accent); font-size:14px">
                                {{ fmApprove.processing ? 'Menyimpan...' : '✓ Setujui Booking ICU' }}
                            </button>
                            <button type="button" @click="closeModal" class="px-5 py-3 rounded-xl font-medium"
                                style="background:var(--bg-input); color:var(--text-secondary); border:1.5px solid var(--border-default); font-size:14px">Batal</button>
                        </div>
                    </form>
                </div>

                <!-- ── VIEW: TOLAK SPRI ─────────────────────────────────────── -->
                <div v-else-if="modal.type==='tolak' && modal.item" key="tolak" class="flex flex-col w-full" style="max-height:92vh">
                    <div class="flex items-center justify-between px-6 py-5 flex-shrink-0" style="border-bottom:1px solid var(--border-default)">
                        <div class="flex items-center gap-3">
                            <button type="button" @click="openModal('detail', modal.item)" class="w-8 h-8 rounded-xl flex items-center justify-center transition-all hover:scale-110" style="background:var(--bg-input); color:var(--text-secondary)">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <div>
                                <h2 class="text-base font-bold" style="color:#E74C3C">Tolak Permintaan</h2>
                                <p class="text-xs mt-0.5" style="color:var(--text-secondary)">{{ modal.item.nama_pasien }}</p>
                            </div>
                        </div>
                        <button @click="closeModal" class="w-8 h-8 rounded-xl flex items-center justify-center transition-all hover:scale-110" style="background:var(--bg-input); color:var(--text-secondary)">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <form @submit.prevent="submitTolak" class="px-6 py-5 space-y-4 overflow-y-auto flex-1">
                        <div class="rounded-xl p-4 space-y-1" style="background:rgba(231,76,60,.06); border:1.5px solid rgba(231,76,60,.15)">
                            <p class="text-xs font-bold flex items-center gap-1.5" style="color:#E74C3C">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                Perhatian
                            </p>
                            <p class="text-xs" style="color:var(--text-secondary)">Tindakan ini tidak dapat dibatalkan. Pastikan alasan sudah jelas dan lengkap.</p>
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Alasan Penolakan <span style="color:#E74C3C">*</span></label>
                            <textarea v-model="fmTolak.alasan_tolak" required rows="5" placeholder="Tuliskan alasan penolakan secara jelas dan lengkap..." class="w-full rounded-xl outline-none resize-none"
                                style="padding:11px 14px; font-size:13px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); line-height:1.6"/>
                            <p v-if="fmTolak.errors.alasan_tolak" class="text-xs" style="color:#E74C3C">{{ fmTolak.errors.alasan_tolak }}</p>
                        </div>
                        <div class="flex items-center gap-3 pt-1" style="border-top:1px solid var(--border-default)">
                            <button type="submit" :disabled="fmTolak.processing || !fmTolak.alasan_tolak.trim()"
                                class="flex-1 font-bold py-3 rounded-xl transition-all duration-150 disabled:opacity-40 hover:-translate-y-px"
                                style="background:rgba(231,76,60,.12); color:#E74C3C; border:1.5px solid rgba(231,76,60,.3); font-size:14px">
                                {{ fmTolak.processing ? 'Menyimpan...' : '✕ Proses Penolakan' }}
                            </button>
                            <button type="button" @click="closeModal" class="px-5 py-3 rounded-xl font-medium"
                                style="background:var(--bg-input); color:var(--text-secondary); border:1.5px solid var(--border-default); font-size:14px">Batal</button>
                        </div>
                    </form>
                </div>

                <!-- ── VIEW: VERIFIKASI PASIEN TIBA ─────────────────────────── -->
                <div v-else-if="modal.type==='verifikasi' && modal.item" key="verifikasi" class="flex flex-col w-full" style="max-height:92vh">
                    <div class="flex items-center justify-between px-6 py-5 flex-shrink-0" style="border-bottom:1px solid var(--border-default)">
                        <div class="flex items-center gap-3">
                            <button type="button" @click="openModal('detail', modal.item)" class="w-8 h-8 rounded-xl flex items-center justify-center transition-all hover:scale-110" style="background:var(--bg-input); color:var(--text-secondary)">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <div>
                                <h2 class="text-base font-bold" style="color:var(--text-primary)">Verifikasi Pasien Tiba</h2>
                                <p class="text-xs mt-0.5" style="color:var(--text-secondary)">
                                    {{ modal.item.nama_pasien }} · Bed: <span style="color:#00A884">{{ modal.item.nama_bed }}</span>
                                </p>
                            </div>
                        </div>
                        <button @click="closeModal" class="w-8 h-8 rounded-xl flex items-center justify-center transition-all hover:scale-110" style="background:var(--bg-input); color:var(--text-secondary)">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <!-- Snapshot booking -->
                    <div class="px-6 py-4 grid grid-cols-2 gap-3 flex-shrink-0" style="border-bottom:1px solid var(--border-default); background:var(--bg-surface-2)">
                        <div class="rounded-xl p-3 space-y-0.5" style="background:var(--bg-input)">
                            <p class="text-xs" style="color:var(--text-muted)">Nama Booking</p>
                            <p class="text-sm font-bold" style="color:var(--text-primary)">{{ modal.item.nama_pasien }}</p>
                        </div>
                        <div class="rounded-xl p-3 space-y-0.5" style="background:var(--bg-input)">
                            <p class="text-xs" style="color:var(--text-muted)">Diagnosa</p>
                            <p class="text-sm font-bold truncate" :title="modal.item.diagnosa" style="color:var(--text-primary)">{{ modal.item.diagnosa ?? '—' }}</p>
                        </div>
                    </div>
                    <form @submit.prevent="submitVerif" class="px-6 py-5 space-y-4 overflow-y-auto flex-1">
                        <p class="text-sm" style="color:var(--text-secondary)">Cari No. MR pasien untuk memverifikasi kedatangan.</p>
                        <!-- Input No. MR + Cari -->
                        <div class="space-y-1.5">
                            <div class="flex gap-2">
                                <input v-model="fmVerif.No_MR" @keydown.enter.prevent="doVerifLookup" placeholder="Masukkan No. MR..."
                                    class="flex-1 rounded-xl outline-none font-mono"
                                    style="padding:10px 14px; font-size:13px"
                                    :style="`border:1.5px solid ${verifLookupError?'#E74C3C':verifLookupResult?.found?'#00A884':'var(--border-default)'}; background:var(--bg-input); color:var(--text-primary)`"/>
                                <button type="button" @click="doVerifLookup" :disabled="verifLookupLoading || fmVerif.No_MR.length < 3"
                                    class="flex items-center gap-1.5 font-semibold px-4 rounded-xl transition-all duration-150 disabled:opacity-40"
                                    style="background:rgba(0,168,132,.15); color:#00A884; border:1.5px solid rgba(0,168,132,.3); font-size:13px; white-space:nowrap">
                                    <svg v-if="verifLookupLoading" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                    <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                    Cari
                                </button>
                            </div>
                            <p v-if="fmVerif.errors.No_MR" class="text-xs" style="color:#E74C3C">{{ fmVerif.errors.No_MR }}</p>
                        </div>
                        <!-- Error lookup -->
                        <div v-if="verifLookupError" class="rounded-xl px-4 py-3 text-sm" style="background:rgba(231,76,60,.08); color:#E74C3C; border:1.5px solid rgba(231,76,60,.2)">
                            ⚠ {{ verifLookupError }}
                        </div>
                        <!-- Hasil ditemukan -->
                        <div v-if="verifLookupResult?.found" class="rounded-xl px-4 py-3" style="background:rgba(0,168,132,.08); border:1.5px solid rgba(0,168,132,.2)">
                            <div class="flex items-center gap-3">
                                <span class="text-lg" style="color:#00A884">✓</span>
                                <div>
                                    <p class="text-sm font-bold" style="color:var(--text-primary)">{{ verifLookupResult.nama_pasien }}</p>
                                    <p class="text-xs font-mono mt-0.5" style="color:var(--text-muted)">{{ verifLookupResult.No_MR }}</p>
                                </div>
                            </div>
                        </div>
                        <!-- Pilih kunjungan (>1) -->
                        <div v-if="verifKunjungans.length > 1" class="space-y-1.5">
                            <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Pilih No. Reg Kunjungan <span style="color:#E74C3C">*</span></label>
                            <div class="space-y-2 max-h-40 overflow-y-auto">
                                <button v-for="k in verifKunjungans" :key="k.No_Reg" type="button" @click="fmVerif.No_Reg = k.No_Reg"
                                    class="w-full text-left px-4 py-2.5 rounded-xl text-sm transition-all"
                                    :style="fmVerif.No_Reg===k.No_Reg?'background:rgba(0,168,132,.12); border:1.5px solid rgba(0,168,132,.4); color:var(--text-primary)':'background:var(--bg-input); border:1px solid var(--border-default); color:var(--text-secondary)'">
                                    <span class="font-mono font-semibold">{{ k.No_Reg }}</span>
                                    <span class="ml-2 text-xs" style="color:var(--text-muted)">· {{ k.nama_ruang || k.Kode_Masuk || '-' }}</span>
                                </button>
                            </div>
                        </div>
                        <!-- Auto-fill 1 kunjungan -->
                        <div v-if="verifKunjungans.length===1 && fmVerif.No_Reg" class="text-sm" style="color:var(--text-secondary)">
                            No. Reg: <span class="font-mono font-semibold" style="color:var(--text-primary)">{{ fmVerif.No_Reg }}</span>
                        </div>
                        <!-- Manual No. Reg (0 kunjungan tapi found) -->
                        <div v-if="verifKunjungans.length===0 && verifLookupResult?.found" class="space-y-1.5">
                            <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">No. Reg <span class="normal-case font-normal">(opsional)</span></label>
                            <input v-model="fmVerif.No_Reg" placeholder="No. Reg kunjungan" class="w-full rounded-xl outline-none font-mono"
                                style="padding:10px 14px; font-size:13px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary)"/>
                        </div>
                        <div class="flex items-center gap-3 pt-1" style="border-top:1px solid var(--border-default)">
                            <button type="submit" :disabled="fmVerif.processing || !fmVerif.No_MR.trim() || !verifLookupResult?.found"
                                class="flex-1 font-bold py-3 rounded-xl transition-all duration-150 disabled:opacity-40 hover:-translate-y-px"
                                style="background:#00A884; color:var(--text-on-accent); font-size:14px">
                                {{ fmVerif.processing ? 'Menyimpan...' : '✓ Verifikasi Kedatangan' }}
                            </button>
                            <button type="button" @click="closeModal" class="px-5 py-3 rounded-xl font-medium"
                                style="background:var(--bg-input); color:var(--text-secondary); border:1.5px solid var(--border-default); font-size:14px">Batal</button>
                        </div>
                    </form>
                </div>

                <!-- ── VIEW: BATAL BOOKING EXTERNAL ──────────────────────── -->
                <div v-else-if="modal.type==='batal' && modal.item" key="batal" class="flex flex-col w-full" style="max-height:92vh">
                    <div class="flex items-center justify-between px-6 py-5 flex-shrink-0" style="border-bottom:1px solid var(--border-default)">
                        <div class="flex items-center gap-3">
                            <button type="button" @click="openModal('detail', modal.item)" class="w-8 h-8 rounded-xl flex items-center justify-center transition-all hover:scale-110" style="background:var(--bg-input); color:var(--text-secondary)">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <div>
                                <h2 class="text-base font-bold" style="color:#D97706">Batalkan Booking</h2>
                                <p class="text-xs mt-0.5" style="color:var(--text-secondary)">{{ modal.item.nama_pasien }}</p>
                            </div>
                        </div>
                        <button @click="closeModal" class="w-8 h-8 rounded-xl flex items-center justify-center transition-all hover:scale-110" style="background:var(--bg-input); color:var(--text-secondary)">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <form @submit.prevent="submitBatal" class="px-6 py-5 space-y-4 overflow-y-auto flex-1">
                        <!-- Info pasien -->
                        <div class="rounded-xl p-4 grid grid-cols-2 gap-3" style="background:var(--bg-surface-2); border:1px solid var(--border-default)">
                            <div>
                                <p class="text-xs" style="color:var(--text-muted)">Pasien</p>
                                <p class="text-sm font-bold" style="color:var(--text-primary)">{{ modal.item.nama_pasien }}</p>
                            </div>
                            <div>
                                <p class="text-xs" style="color:var(--text-muted)">Status Saat Ini</p>
                                <span class="inline-flex items-center gap-1.5 text-xs font-bold px-2.5 py-1 rounded-full mt-0.5"
                                    :style="`background:${ss(modal.item.status).bg}; color:${ss(modal.item.status).color}`">
                                    {{ modal.item.status_label }}
                                </span>
                            </div>
                        </div>
                        <!-- Warning -->
                        <div class="rounded-xl p-3.5 flex gap-3" style="background:rgba(217,119,6,.07); border:1.5px solid rgba(217,119,6,.2)">
                            <svg class="w-4 h-4 flex-shrink-0 mt-0.5" style="color:#D97706" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            <p class="text-xs" style="color:#92400E">Booking yang dibatalkan tidak dapat dilanjutkan. Petugas ICU akan menerima notifikasi pembatalan ini.</p>
                        </div>
                        <!-- Alasan batal (opsional) -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">
                                Alasan Pembatalan
                                <span class="normal-case font-normal ml-1" style="color:var(--text-muted)">(opsional)</span>
                            </label>
                            <textarea v-model="fmBatal.alasan_batal" rows="4"
                                placeholder="Mis: Pasien sudah meninggal, kondisi membaik, dirujuk ke RS lain..."
                                class="w-full rounded-xl outline-none resize-none"
                                style="padding:11px 14px; font-size:13px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); line-height:1.6"/>
                        </div>
                        <div class="flex items-center gap-3 pt-1" style="border-top:1px solid var(--border-default)">
                            <button type="submit" :disabled="fmBatal.processing"
                                class="flex-1 font-bold py-3 rounded-xl transition-all duration-150 disabled:opacity-40 hover:-translate-y-px"
                                style="background:rgba(217,119,6,.12); color:#D97706; border:1.5px solid rgba(217,119,6,.3); font-size:14px">
                                {{ fmBatal.processing ? 'Memproses...' : 'Ya, Batalkan Booking' }}
                            </button>
                            <button type="button" @click="openModal('detail', modal.item)" class="px-5 py-3 rounded-xl font-medium"
                                style="background:var(--bg-input); color:var(--text-secondary); border:1.5px solid var(--border-default); font-size:14px">Kembali</button>
                        </div>
                    </form>
                </div>

                <!-- ── VIEW: BATAL BOOKING INTERNAL ──────────────────────── -->
                <div v-else-if="modal.type==='batal_internal' && modal.item" key="batal_internal" class="flex flex-col w-full" style="max-height:92vh">
                    <div class="flex items-center justify-between px-6 py-5 flex-shrink-0" style="border-bottom:1px solid var(--border-default)">
                        <div class="flex items-center gap-3">
                            <button type="button" @click="openModal('detail', modal.item)" class="w-8 h-8 rounded-xl flex items-center justify-center transition-all hover:scale-110" style="background:var(--bg-input); color:var(--text-secondary)">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                            </button>
                            <div>
                                <h2 class="text-base font-bold" style="color:#D97706">Batalkan Booking Internal</h2>
                                <p class="text-xs mt-0.5" style="color:var(--text-secondary)">{{ modal.item.nama_pasien }}</p>
                            </div>
                        </div>
                        <button @click="closeModal" class="w-8 h-8 rounded-xl flex items-center justify-center transition-all hover:scale-110" style="background:var(--bg-input); color:var(--text-secondary)">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <form @submit.prevent="submitBatalInternal" class="px-6 py-5 space-y-4 overflow-y-auto flex-1">
                        <!-- Info pasien -->
                        <div class="rounded-xl p-4 grid grid-cols-2 gap-3" style="background:var(--bg-surface-2); border:1px solid var(--border-default)">
                            <div>
                                <p class="text-xs" style="color:var(--text-muted)">Pasien</p>
                                <p class="text-sm font-bold" style="color:var(--text-primary)">{{ modal.item.nama_pasien }}</p>
                            </div>
                            <div>
                                <p class="text-xs" style="color:var(--text-muted)">Asal Ruang</p>
                                <p class="text-sm font-bold" style="color:var(--text-primary)">{{ modal.item.asal ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs" style="color:var(--text-muted)">No. MR</p>
                                <p class="text-sm font-mono font-bold" style="color:var(--text-primary)">{{ modal.item.No_MR ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs" style="color:var(--text-muted)">Status Saat Ini</p>
                                <span class="inline-flex items-center gap-1.5 text-xs font-bold px-2.5 py-1 rounded-full mt-0.5"
                                    :style="`background:${ss(modal.item.status).bg}; color:${ss(modal.item.status).color}`">
                                    {{ modal.item.status_label }}
                                </span>
                            </div>
                        </div>
                        <!-- Warning -->
                        <div class="rounded-xl p-3.5 flex gap-3" style="background:rgba(217,119,6,.07); border:1.5px solid rgba(217,119,6,.2)">
                            <svg class="w-4 h-4 flex-shrink-0 mt-0.5" style="color:#D97706" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            <p class="text-xs" style="color:#92400E">Booking internal yang dibatalkan tidak dapat dilanjutkan. Petugas yang mengajukan akan menerima notifikasi.</p>
                        </div>
                        <!-- Alasan batal -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">
                                Alasan Pembatalan
                                <span class="normal-case font-normal ml-1" style="color:var(--text-muted)">(opsional)</span>
                            </label>
                            <textarea v-model="fmBatalInternal.alasan_batal" rows="4"
                                placeholder="Mis: Pasien kondisi membaik, sudah dipindah ke ruang biasa..."
                                class="w-full rounded-xl outline-none resize-none"
                                style="padding:11px 14px; font-size:13px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); line-height:1.6"/>
                        </div>
                        <div class="flex items-center gap-3 pt-1" style="border-top:1px solid var(--border-default)">
                            <button type="submit" :disabled="fmBatalInternal.processing"
                                class="flex-1 font-bold py-3 rounded-xl transition-all duration-150 disabled:opacity-40 hover:-translate-y-px"
                                style="background:rgba(217,119,6,.12); color:#D97706; border:1.5px solid rgba(217,119,6,.3); font-size:14px">
                                {{ fmBatalInternal.processing ? 'Memproses...' : 'Ya, Batalkan Booking' }}
                            </button>
                            <button type="button" @click="openModal('detail', modal.item)" class="px-5 py-3 rounded-xl font-medium"
                                style="background:var(--bg-input); color:var(--text-secondary); border:1.5px solid var(--border-default); font-size:14px">Kembali</button>
                        </div>
                    </form>
                </div>

                </Transition>
            </div><!-- end container -->
        </div><!-- end backdrop -->
    </Transition><!-- end outer -->

</AppLayout>
</template>

<style scoped>
/* ── Hero ────────────────────────────────────────────────────────────────── */
.db-hero {
  background:#00A884;
  border-radius:16px; padding:22px 28px 18px; position:relative; overflow:hidden;
  border:1px solid rgba(255,255,255,.1); box-shadow:0 12px 32px rgba(0,168,132,.15);
  display:grid; grid-template-columns:1fr; gap:18px; align-items:center;
}
@media(min-width:860px){ .db-hero { grid-template-columns:1fr auto; } }
.db-hero::before { content:''; position:absolute; width:260px; height:260px; border-radius:50%; right:-80px; top:-100px; background:radial-gradient(circle,rgba(255,255,255,.1),transparent); pointer-events:none; }
.db-hero-copy { position:relative; z-index:2; }
.db-hero-logo { width:44px; height:44px; border-radius:13px; background:rgba(255,255,255,.18); border:1px solid rgba(255,255,255,.22); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.db-hero-vis { position:relative; min-height:140px; min-width:200px; align-self:center; display:none; }
@media(min-width:860px){ .db-hero-vis { display:block; } }
.db-char {
  position:absolute; right:0; bottom:-16px; width:min(200px,100%); aspect-ratio:1;
}
</style>
