<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout   from '@/Layouts/AppLayout.vue'
import Icd10Search from '@/Components/Icd10Search.vue'
import { useAuth } from '@/composables/useAuth.js'

const { canBuatSpriInternal, isAdmin } = useAuth()
const logoUrl      = `${import.meta.env.BASE_URL}images/logo-urip.png`;
const doctorImgUrl = `${import.meta.env.BASE_URL}images/welcome-doctors.svg`;

const props = defineProps({
    spriList:        { type: Array,   default: () => [] },
    summary:         { type: Object,  default: () => ({}) },
    filters:         { type: Object,  default: () => ({}) },
    pasienAktif:     { type: Array,   default: () => [] },
    wardIds:         { type: Array,   default: () => [] },
    authProvider:    { type: String,  default: 'local' },
    isIgdUser:       { type: Boolean, default: false },
    unitKerja:       { type: String,  default: '' },
    kamarKosong:     { type: Array,   default: () => [] },
    masterKelas:     { type: Array,   default: () => [] },
    masterCaraBayar: { type: Array,   default: () => [] },
    flash:           { type: Object,  default: () => ({}) },
})

// ── Filters ──────────────────────────────────────────────────────────────────
// Helper tanggal lokal (bukan UTC — fix timezone WIB/Asia)
const localDate = (offsetDays = 0) => {
    const d = new Date(); d.setDate(d.getDate() + offsetDays)
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`
}
const _today    = localDate(0)
const fStatus  = ref(props.filters.status    ?? '')
const fNama    = ref(props.filters.nama      ?? '')
const fTgl     = ref(props.filters.tgl       ?? '')
const fJaminan = ref(props.filters.jaminan   ?? '')
const fTglDari = ref(props.filters.fTglDari  || _today)
const fTglAkh  = ref(props.filters.fTglAkh   || _today)
const sortBy   = ref(props.filters.sortBy    ?? 'created_at')
const sortDir  = ref(props.filters.sortDir   ?? 'desc')

// Date preset helpers
const today     = _today
const yesterday = localDate(-1)
const week7     = localDate(-6)
const setPreset = (dari, sampai) => { fTglDari.value=dari; fTglAkh.value=sampai; applyFilters() }

let ft = null
const applyFilters = () => router.get(route('icu.menu_petugas'),
    { status: fStatus.value, nama: fNama.value, jaminan: fJaminan.value,
      tgl_dari: fTglDari.value, tgl_sampai: fTglAkh.value,
      sort: sortBy.value, dir: sortDir.value },
    { preserveState: true, replace: true, preserveScroll: true })
const onNamaInput = () => { clearTimeout(ft); ft = setTimeout(applyFilters, 400) }
const resetFilter = () => {
    fStatus.value = ''; fNama.value = ''; fTgl.value = ''; fJaminan.value = ''
    fTglDari.value = localDate(0); fTglAkh.value = localDate(0)
    applyFilters()
}
const toggleSort  = (col) => {
    sortDir.value = sortBy.value === col ? (sortDir.value === 'asc' ? 'desc' : 'asc') : 'desc'
    sortBy.value  = col
    applyFilters()
}
const sortIcon = (col) => sortBy.value !== col ? '↕' : sortDir.value === 'asc' ? '↑' : '↓'

// ── Style helpers ─────────────────────────────────────────────────────────────
const SS = {
    pending_admisi: { bg: 'rgba(245,166,35,.15)',  color: '#E67E22', dot: '#E67E22' },
    pending_icu:    { bg: 'rgba(224,146,58,.15)',  color: '#E0923A', dot: '#E0923A' },
    waiting_list:   { bg: 'rgba(217,119,6,.15)',   color: '#D97706', dot: '#D97706' },
    bed_verified:   { bg: 'rgba(0,168,132,.15)',   color: '#00A884', dot: '#00A884' },
    ditolak:        { bg: 'rgba(231,76,60,.15)',   color: '#E74C3C', dot: '#E74C3C' },
}
const ss     = (s) => SS[s] ?? { bg: 'var(--bg-input)', color: 'var(--text-secondary)', dot: '#888' }
const gIcon  = (g) => g === 'L' ? '♂' : g === 'P' ? '♀' : '·'
const gColor = (g) => g === 'L' ? '#00A884' : g === 'P' ? '#8E44AD' : 'var(--text-secondary)'

// ── Summary cards ─────────────────────────────────────────────────────────────
const CARDS = computed(() => [
    { key: '',             label: 'Total',        val: props.summary.total        ?? 0, color: '#5A6B7C', icon:'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' },
    { key: 'pending_icu',  label: 'Menunggu ICU', val: props.summary.pending_icu  ?? 0, color: '#E0923A', icon:'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
    { key: 'waiting_list', label: 'Waiting List', val: props.spriList.filter(s=>s.status==='waiting_list').length ?? 0, color: '#D97706', icon:'M12 8v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z' },
    { key: 'bed_verified', label: 'Bed Verified', val: props.summary.bed_verified ?? 0, color: '#059669', icon:'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z' },
    { key: 'ditolak',      label: 'Ditolak',      val: props.summary.ditolak      ?? 0, color: '#E74C3C', icon:'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z' },
])

const statusOptions = [
    { value: '',              label: 'Semua Status' },
    { value: 'pending_icu',   label: 'Menunggu ICU' },
    { value: 'waiting_list',  label: 'Waiting List' },
    { value: 'bed_verified',  label: 'Bed Verified' },
    { value: 'ditolak',       label: 'Ditolak' },
]

const isSSO = computed(() => props.authProvider === 'keycloak')

// ── Daftar pasien untuk pilih / buat BU ────────────────────────────────────
const cariPasien  = ref('')
const pasienList  = ref(props.pasienAktif ?? [])
const cariLoading = ref(false)

watch(cariPasien, (val) => {
    let ct = null
    clearTimeout(ct)
    ct = setTimeout(async () => {
        cariLoading.value = true
        try {
            const r = await fetch(route('icu.menu_petugas.pasien_aktif') + '?q=' + encodeURIComponent(val.trim()),
                { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            pasienList.value = await r.json()
        } catch { pasienList.value = [] }
        finally { cariLoading.value = false }
    }, 350)
})

// LOGIKA BARU: Filter pasien agar yang sudah di-request (ada di spriList) menghilang
const requestedRegs = computed(() => props.spriList.map(s => s.No_Reg).filter(Boolean))

const pasienPerRuang = computed(() => {
    const m = {}
    pasienList.value.forEach(p => {
        // Jika No_Reg pasien sudah ada di daftar SPRI Kanan, sembunyikan!
        if (p.No_Reg && requestedRegs.value.includes(p.No_Reg)) return

        const k = p.Nama_RuangM ?? p.Kode_RuangM ?? 'Lainnya'
        if (!m[k]) m[k] = []
        m[k].push(p)
    })
    return m
})

// ── Modal / Form BU (Booking ICU) ────────────────────────────────────────────────────────
const modal = ref({ open: false, type: '' })
const openModal = (type) => {
    if (type === 'spri' && !skipLookupWatch.value) resetSpri()
    modal.value = { open: true, type }
}
const closeModal = () => { modal.value.open = false; setTimeout(() => { modal.value = { open: false, type: '' } }, 200) }

const lookupLoading     = ref(false)
const lookupResult      = ref(null)
const lookupError       = ref('')
const kunjungans        = ref([])
const diagnosisExisting = ref('')
const jaminanExisting   = ref('') // Jaminan dari M_CARABAYAR
const skipLookupWatch   = ref(false)
const fmSpri = useForm({ No_MR: '', No_Reg: '', Diagnosis: '', IndikasiRI: '', asal_ruang: '', Dokter: '', spesialis: '', Keterangan: '' })

const resetSpri = () => {
    skipLookupWatch.value = false
    fmSpri.reset()
    lookupResult.value = null; lookupError.value = ''; kunjungans.value = []
    diagnosisExisting.value = ''; jaminanExisting.value = ''
}
watch(() => fmSpri.No_MR, (val) => {
    if (skipLookupWatch.value) return
    if (val && val.trim().length >= 3) doLookup(val.trim())
    else { lookupResult.value = null; lookupError.value = ''; kunjungans.value = []; diagnosisExisting.value = ''; jaminanExisting.value = '' }
})
const doLookup = async (noMr, preserveFields = false) => {
    lookupResult.value = null; lookupError.value = ''; kunjungans.value = []
    if (!preserveFields) {
        fmSpri.No_Reg = ''; fmSpri.Dokter = ''; fmSpri.asal_ruang = ''; diagnosisExisting.value = ''
    }
    lookupLoading.value = true
    try {
        const r = await fetch(route('icu.menu_petugas.lookup') + '?No_MR=' + encodeURIComponent(noMr),
            { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        const d = await r.json()
        lookupResult.value = d
        if (d.found) {
            kunjungans.value = d.kunjungans ?? []
            if (kunjungans.value.length === 1 && !preserveFields) {
                const k = kunjungans.value[0]
                fmSpri.No_Reg = k.No_Reg; fmSpri.Dokter = k.Dokter; fmSpri.asal_ruang = k.asal_ruang
                diagnosisExisting.value = k.Diagnosis
                jaminanExisting.value   = k.jaminan ?? ''
            }
            if (!preserveFields && d.prefill) {
                if (!fmSpri.IndikasiRI) fmSpri.IndikasiRI = d.prefill.IndikasiRI ?? ''
                if (!fmSpri.asal_ruang) fmSpri.asal_ruang = d.prefill.asal_ruang ?? ''
                if (!fmSpri.Dokter)     fmSpri.Dokter     = d.prefill.Dokter ?? ''
            }
        } else { lookupError.value = d.message ?? 'Pasien tidak ditemukan.' }
    } catch { lookupError.value = 'Gagal menghubungi server.' }
    finally  { lookupLoading.value = false }
}
const onKunjunganChange = (nr) => {
    const k = kunjungans.value.find(x => x.No_Reg === nr)
    if (k) {
        fmSpri.Dokter = k.Dokter; fmSpri.asal_ruang = k.asal_ruang
        diagnosisExisting.value = k.Diagnosis
        jaminanExisting.value   = k.jaminan ?? ''
    }
}
const pilihPasien = (p) => {
    // Reset dulu state form
    resetSpri()
    // Set skip SETELAH reset agar watch tidak memicu lookup saat No_MR diisi
    skipLookupWatch.value = true

    fmSpri.No_MR      = p.No_MR
    fmSpri.No_Reg     = p.No_Reg ?? ''
    fmSpri.asal_ruang = p.Nama_RuangM ?? ''
    fmSpri.Dokter     = p.Dokter ?? ''

    lookupResult.value = {
        found:         true,
        No_MR:         p.No_MR,
        nama_pasien:   p.Nama_Pasien,
        jenis_kelamin: p.jenis_kelamin ?? '',
    }

    if (p.No_Reg) {
        kunjungans.value = [{ No_Reg: p.No_Reg, Dokter: p.Dokter ?? '', asal_ruang: p.Nama_RuangM ?? '', Diagnosis: '' }]
    }

    // Buka modal langsung tanpa memanggil openModal (agar tidak trigger resetSpri lagi)
    modal.value = { open: true, type: 'spri' }

    // Setelah DOM flush, matikan skip dan ambil diagnosis+jaminan dari server
    nextTick(() => {
        skipLookupWatch.value = false
        doLookupDiagnosis(p.No_MR, p.No_Reg)
    })
}

const doLookupDiagnosis = async (noMr, noReg) => {
    try {
        const r = await fetch(route('icu.menu_petugas.lookup') + '?No_MR=' + encodeURIComponent(noMr),
            { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        const d = await r.json()
        if (d.found) {
            lookupResult.value = { ...lookupResult.value, ...d, found: true }
            const kunjungans_baru = d.kunjungans ?? []

            // Cari kunjungan yang cocok — bandingkan dengan trim() agar toleran terhadap spasi
            let kunjungan = noReg
                ? kunjungans_baru.find(k => k.No_Reg?.trim() === noReg?.trim())
                : null

            // Jika tidak ketemu persis, ambil yang pertama (kunjungan terbaru)
            if (!kunjungan && kunjungans_baru.length > 0) {
                kunjungan = kunjungans_baru[0]
            }

            if (kunjungan) {
                diagnosisExisting.value = kunjungan.Diagnosis ?? ''
                jaminanExisting.value   = kunjungan.jaminan   ?? ''
                // Update jika field SSO belum terisi
                if (!fmSpri.Dokter && kunjungan.Dokter)         fmSpri.Dokter     = kunjungan.Dokter
                if (!fmSpri.asal_ruang && kunjungan.asal_ruang) fmSpri.asal_ruang = kunjungan.asal_ruang
                // Juga update No_Reg jika belum terisi (kasus tanpa No_Reg dari SSO)
                if (!fmSpri.No_Reg && kunjungan.No_Reg)         fmSpri.No_Reg     = kunjungan.No_Reg
            }

            // Update kunjungans jika ada banyak pilihan
            if (kunjungans_baru.length > 1) kunjungans.value = kunjungans_baru
        }
    } catch (e) {
        // diagnosis/jaminan tidak wajib, abaikan error
        console.warn('[doLookupDiagnosis] error:', e)
    }
}
const submitSpri = () => fmSpri.post(route('icu.menu_petugas.spri.store'), { onSuccess: closeModal })
const canSubmit  = computed(() =>
    fmSpri.No_MR.trim() && fmSpri.No_Reg.trim() &&
    fmSpri.Diagnosis.trim() && fmSpri.IndikasiRI.trim() &&
    lookupResult.value?.found)

// ── UI-only: tab & detail panel ──────────────────────────────────────────────
const activeTab     = ref('aktif')   // 'aktif' | 'riwayat' | 'waiting_list'
const selectedItem  = ref(null)      // item spriList yang sedang dibuka di panel detail
const showFilter    = ref(false)     // toggle panel filter di mobile

const spriAktif       = computed(() => props.spriList.filter(s => !['bed_verified_done'].includes(s.status) && s.status !== 'selesai'))
const spriRiwayat     = computed(() => props.spriList.filter(s => s.status === 'bed_verified' || s.status === 'selesai' || s.status === 'ditolak'))
const spriWaitingList = computed(() => props.spriList.filter(s => s.status === 'waiting_list'))

const tabList = computed(() => {
    if (activeTab.value === 'riwayat')      return spriRiwayat.value
    if (activeTab.value === 'waiting_list') return spriWaitingList.value
    return props.spriList
})

const selectDetail = (item) => {
    selectedItem.value = selectedItem.value?.id === item.id ? null : item
}

const tabCounts = computed(() => ({
    aktif:        props.spriList.length,
    riwayat:      spriRiwayat.value.length,
    waiting_list: spriWaitingList.value.length,
}))

const hasActiveFilter = computed(() => fStatus.value || fNama.value || fJaminan.value)
</script>

<template>
<AppLayout :flash="flash" page-title="Menu Rawat Inap">
<div class="mp-wrap">

  <!-- ══ PAGE HEADER ══════════════════════════════════════════════ -->
  <div class="bk-hero mb-5">
    <div class="bk-hero-left">
      <div class="bk-hero-logo">
        <img :src="logoUrl" alt="Logo" style="width:36px;height:36px;object-fit:contain" @error="$event.target.style.display='none'"/>
      </div>
      <div>
        <p class="bk-hero-sub">ICU Command Center · {{ isSSO ? 'SSO' : 'Lokal' }}
          <span v-if="isSSO"> · Bangsal: <strong>{{ wardIds.join(', ') || '-' }}</strong></span>
        </p>
        <h1 class="bk-hero-title">Booking ICU</h1>
      </div>
    </div>
    <button v-if="!isSSO && (canBuatSpriInternal || isAdmin)" @click="openModal('spri')" class="bk-btn-new">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
      </svg>
      + Booking Baru ↗
    </button>
  </div>

  <!-- ══ KPI CARDS ════════════════════════════════════════════════ -->
  <div class="bk-summary-row mb-5">
    <button v-for="c in CARDS" :key="c.key" @click="fStatus = c.key; applyFilters()"
      class="bk-card" :class="fStatus === c.key ? 'bk-card--active' : ''"
      :style="fStatus === c.key ? `--card-color:${c.color}` : `--card-color:${c.color}`">
      <p class="bk-card-num" :style="`color:${fStatus===c.key ? c.color : 'var(--text-primary)'}`">{{ c.val }}</p>
      <p class="bk-card-label">{{ c.label }}</p>
    </button>
  </div>

  <!-- ══ MAIN LAYOUT ══════════════════════════════════════════════ -->
  <div class="bk-main">

    <!-- ── LEFT COLUMN: pasien bangsal (SSO) ── -->
    <div v-if="isSSO" class="bk-panel bk-panel--left">
      <div class="bk-panel-head">
        <div class="flex items-center gap-2">
          <div class="bk-panel-icon">
            <svg class="w-4 h-4" style="color:#00A884" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
          </div>
          <div>
            <p class="text-sm font-bold" style="color:var(--text-primary)">{{ isIgdUser ? 'Pasien IGD' : 'Pasien Bangsal' }} <strong style="color:#00A884">{{ wardIds.join(', ') || '-' }}</strong></p>
            <p class="text-xs" style="color:var(--text-muted)">Klik untuk request ICU</p>
          </div>
        </div>
        <div class="bk-search-wrap mt-3">
          <svg class="bk-search-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
          <input v-model="cariPasien" placeholder="Cari nama / No. MR..." class="bk-search"/>
        </div>
      </div>
      <div class="bk-panel-body">
        <div v-if="Object.keys(pasienPerRuang).length === 0" class="bk-empty">
          <svg class="w-8 h-8 mb-2" style="color:var(--text-muted)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
          </svg>
          <p class="text-sm font-semibold" style="color:var(--text-secondary)">Semua pasien sudah diproses</p>
          <p class="text-xs mt-1" style="color:var(--text-muted)">Atau tidak ada data aktif</p>
        </div>
        <template v-for="(pasiens, ruang) in pasienPerRuang" :key="ruang">
          <div v-if="pasiens.length > 0" class="mb-4">
            <div class="bk-ruang-head">
              <span>🏥</span>
              <span class="text-xs font-bold uppercase tracking-widest" style="color:var(--text-accent)">{{ ruang }}</span>
              <span class="bk-ruang-count">{{ pasiens.length }}</span>
            </div>
            <div class="flex flex-col gap-1.5">
              <div v-for="p in pasiens" :key="p.No_MR + (p.No_Reg ?? '')"
                @click="pilihPasien(p)" class="bk-pasien-row group">
                <div class="bk-av" :style="`background:${gColor(p.jenis_kelamin)}15;color:${gColor(p.jenis_kelamin)}`">{{ gIcon(p.jenis_kelamin) }}</div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-bold truncate" style="color:var(--text-primary)">{{ p.Nama_Pasien }}</p>
                  <p class="text-xs mt-0.5 font-mono" style="color:var(--text-muted)">{{ p.No_MR }}<span v-if="p.No_Reg" class="ml-1 px-1.5 py-0.5 rounded" style="color:#00A884;background:rgba(0,168,132,.1)">· {{ p.No_Reg }}</span></p>
                </div>
                <div class="text-right hidden sm:block flex-shrink-0">
                  <p class="text-xs font-semibold truncate max-w-[100px]" style="color:var(--text-primary)">{{ p.Dokter || '—' }}</p>
                  <p class="text-xs truncate max-w-[100px] mt-0.5" style="color:var(--text-muted)">{{ p.Nama_RuangM || '—' }}</p>
                </div>
                <div class="opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0">
                  <div class="bk-arrow-btn"><svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></div>
                </div>
              </div>
            </div>
          </div>
        </template>
      </div>
    </div>

    <!-- ── LOCAL mode note ── -->
    <div v-else class="bk-local-note">
      <svg class="w-8 h-8 mb-3" style="color:var(--text-muted)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
      <p class="text-sm font-semibold" style="color:var(--text-secondary)">Mode Login Lokal</p>
      <p class="text-xs mt-1" style="color:var(--text-muted)">Gunakan tombol "Booking Baru" di atas untuk mencari pasien.</p>
    </div>

    <!-- ── RIGHT COLUMN: tab list + detail ── -->
    <div class="bk-panel bk-panel--right">

      <!-- Tab bar + search + filter toggle -->
      <div class="bk-tab-bar">
        <div class="bk-tabs">
          <button class="bk-tab" :class="activeTab==='aktif'?'bk-tab--active':''" @click="activeTab='aktif'; selectedItem=null">
            Aktif
            <span class="bk-tab-badge" :class="activeTab==='aktif'?'bk-tab-badge--active':''">{{ tabCounts.aktif }}</span>
          </button>
          <button class="bk-tab" :class="activeTab==='riwayat'?'bk-tab--active':''" @click="activeTab='riwayat'; selectedItem=null">
            Riwayat
            <span class="bk-tab-badge" :class="activeTab==='riwayat'?'bk-tab-badge--active':''">{{ tabCounts.riwayat }}</span>
          </button>
          <button class="bk-tab" :class="activeTab==='waiting_list'?'bk-tab--active':''" @click="activeTab='waiting_list'; selectedItem=null">
            Waiting List
            <span class="bk-tab-badge" :class="activeTab==='waiting_list'?'bk-tab-badge--active':''">{{ tabCounts.waiting_list }}</span>
          </button>
        </div>
        <button class="bk-filter-toggle" :class="showFilter?'bk-filter-toggle--active':''" @click="showFilter=!showFilter" title="Filter">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
          </svg>
          Filter
          <span v-if="hasActiveFilter" class="bk-filter-dot"></span>
        </button>
      </div>

      <!-- Search bar (always visible) -->
      <div class="bk-search-wrap" style="padding:10px 14px 0">
        <svg class="bk-search-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <input v-model="fNama" @input="onNamaInput" placeholder="Cari nama pasien atau No. MR..." class="bk-search"/>
      </div>

      <!-- Filter panel (collapsible) -->
      <Transition name="slide-down">
        <div v-if="showFilter" class="bk-filter-panel">
          <div class="bk-filter-grid">
            <div>
              <label class="bk-label">Status</label>
              <select v-model="fStatus" @change="applyFilters" class="bk-select">
                <option v-for="o in statusOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
              </select>
            </div>
            <div>
              <label class="bk-label">Tgl Mulai</label>
              <input v-model="fTglDari" @change="applyFilters" type="date" class="bk-input"/>
            </div>
            <div>
              <label class="bk-label">Tgl Akhir</label>
              <input v-model="fTglAkh" @change="applyFilters" type="date" :min="fTglDari" class="bk-input"/>
            </div>
          </div>
          <!-- Preset buttons & sort -->
          <div class="bk-filter-actions">
            <div class="bk-preset-group">
              <button v-for="p in [{l:'Hari ini',d:today,s:today},{l:'Kemarin',d:yesterday,s:yesterday},{l:'7 Hari',d:week7,s:today}]"
                :key="p.l" @click="setPreset(p.d,p.s)" class="bk-preset-btn"
                :class="fTglDari===p.d&&fTglAkh===p.s?'bk-preset-btn--active':''">
                {{ p.l }}
              </button>
            </div>
            <div class="flex items-center gap-2">
              <button v-for="col in [{key:'created_at',label:'Waktu'},{key:'status',label:'Status'}]" :key="col.key"
                @click="toggleSort(col.key)" class="bk-sort-btn" :class="sortBy===col.key?'bk-sort-btn--active':''">
                {{ col.label }} {{ sortIcon(col.key) }}
              </button>
              <button v-if="hasActiveFilter" @click="resetFilter" class="bk-reset-btn">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                Reset
              </button>
            </div>
          </div>
        </div>
      </Transition>

      <!-- List + detail panel side-by-side -->
      <div class="bk-content">

        <!-- Booking list -->
        <div class="bk-list" :class="selectedItem ? 'bk-list--narrow' : ''">

          <!-- Table header -->
          <div class="bk-list-header">
            <span>PASIEN</span>
            <span>CHECK IN / CHECK OUT</span>
            <span>JAMINAN</span>
            <span>STATUS</span>
            <span></span>
          </div>

          <!-- Empty state -->
          <div v-if="!tabList.length" class="bk-empty" style="padding:48px 16px">
            <svg class="w-8 h-8 mb-2" style="color:var(--text-muted)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p class="text-sm font-semibold" style="color:var(--text-secondary)">Belum ada data</p>
            <p class="text-xs mt-1" style="color:var(--text-muted)">
              {{ activeTab==='aktif' ? (isSSO ? 'Pilih pasien dari daftar kiri.' : 'Klik Booking Baru untuk memulai.') : 'Tidak ada data untuk tab ini.' }}
            </p>
          </div>

          <!-- Rows -->
          <div v-for="item in tabList" :key="`row-${item.id}`"
            class="bk-row" :class="selectedItem?.id===item.id ? 'bk-row--active' : ''"
            @click="selectDetail(item)">
            <div>
              <p class="bk-row-name">{{ item.nama_pasien }}</p>
              <p class="bk-row-mr">{{ item.No_MR }}</p>
            </div>
            <div>
              <p class="bk-row-date">{{ item.checkin_fmt ?? item.created_at_fmt }}</p>
              <p class="bk-row-date-sub">{{ item.checkout_fmt ?? '—' }}</p>
            </div>
            <div class="bk-row-jaminan">{{ item.cara_bayar ?? item.jaminan ?? '—' }}</div>
            <div>
              <span class="bk-pill" :style="`background:${ss(item.status).bg};color:${ss(item.status).color}`">
                <span class="bk-pill-dot" :style="`background:${ss(item.status).dot}`"></span>
                {{ item.status_label }}
              </span>
            </div>
            <div class="bk-row-chevron" :style="selectedItem?.id===item.id?'color:#00A884':''">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
              </svg>
            </div>
          </div>
        </div>

        <!-- Detail panel (inline, slide in) -->
        <Transition name="slide-left">
          <div v-if="selectedItem" class="bk-detail">
            <div class="bk-detail-head">
              <span class="text-xs font-bold uppercase tracking-wider" style="color:var(--text-muted)">Detail Booking</span>
              <button class="bk-detail-close" @click="selectedItem=null">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>

            <!-- Patient -->
            <div class="bk-detail-patient">
              <div class="bk-detail-av" :style="`background:${gColor(selectedItem.jenis_kelamin)}15;color:${gColor(selectedItem.jenis_kelamin)}`">
                {{ gIcon(selectedItem.jenis_kelamin) }}{{ (selectedItem.nama_pasien||'').split(' ').map(w=>w[0]).join('').slice(0,2).toUpperCase() }}
              </div>
              <div>
                <p class="bk-detail-name">{{ selectedItem.nama_pasien }}</p>
                <p class="bk-detail-mr">{{ selectedItem.No_MR }}</p>
                <span class="bk-pill mt-1 inline-flex" :style="`background:${ss(selectedItem.status).bg};color:${ss(selectedItem.status).color}`">
                  <span class="bk-pill-dot" :style="`background:${ss(selectedItem.status).dot}`"></span>
                  {{ selectedItem.status_label }}
                </span>
              </div>
            </div>

            <div class="bk-divider"></div>

            <!-- Jadwal -->
            <div class="bk-detail-section">
              <p class="bk-section-title">Jadwal</p>
              <div class="bk-detail-grid">
                <div>
                  <p class="bk-field-label">Check In</p>
                  <p class="bk-field-val">{{ selectedItem.checkin_fmt ?? selectedItem.created_at_fmt ?? '—' }}</p>
                </div>
                <div>
                  <p class="bk-field-label">Check Out</p>
                  <p class="bk-field-val">{{ selectedItem.checkout_fmt ?? '—' }}</p>
                </div>
              </div>
            </div>

            <div class="bk-divider"></div>

            <!-- Klinis -->
            <div class="bk-detail-section">
              <p class="bk-section-title">Klinis</p>
              <div class="mb-2">
                <p class="bk-field-label">Diagnosis</p>
                <p class="bk-field-val">{{ selectedItem.Diagnosis ?? '—' }}</p>
                <p class="bk-field-sub">{{ selectedItem.IndikasiRI ?? '' }}</p>
              </div>
              <div>
                <p class="bk-field-label">DPJP / Spesialis</p>
                <p class="bk-field-val">{{ selectedItem.Dokter ?? '—' }}</p>
                <p class="bk-field-sub">{{ selectedItem.asal_ruang ?? '' }}</p>
              </div>
            </div>

            <!-- Bed ICU (if assigned) -->
            <template v-if="selectedItem.nama_bed">
              <div class="bk-divider"></div>
              <div class="bk-detail-section">
                <p class="bk-section-title">Bed ICU</p>
                <div>
                  <p class="bk-field-label">Kamar</p>
                  <p class="bk-field-val" style="color:#00A884;font-weight:700">{{ selectedItem.nama_bed }}</p>
                  <p v-if="selectedItem.kebutuhan_bed" class="bk-field-sub">{{ selectedItem.kebutuhan_bed }}</p>
                </div>
              </div>
            </template>

            <!-- Waiting list info -->
            <template v-if="selectedItem.status==='waiting_list' && (selectedItem.waiting_alasan || selectedItem.waiting_estimasi_fmt)">
              <div class="bk-divider"></div>
              <div class="bk-detail-section">
                <div class="bk-waiting-banner">
                  <div class="bk-waiting-banner-head">
                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="#D97706" stroke-width="2.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="text-xs font-bold" style="color:#D97706">Masuk Waiting List</span>
                  </div>
                  <p v-if="selectedItem.waiting_alasan" class="text-xs mt-1" style="color:#78350F">{{ selectedItem.waiting_alasan }}</p>
                  <p v-if="selectedItem.waiting_estimasi_fmt" class="text-xs font-bold font-mono mt-1" style="color:#D97706">Est. {{ selectedItem.waiting_estimasi_fmt }}</p>
                </div>
              </div>
            </template>

            <!-- Alasan tolak -->
            <template v-if="selectedItem.alasan_tolak">
              <div class="bk-divider"></div>
              <div class="bk-detail-section">
                <p class="bk-section-title">Alasan Ditolak</p>
                <p class="text-xs font-semibold" style="color:#E74C3C">{{ selectedItem.alasan_tolak }}</p>
              </div>
            </template>

            <!-- Catatan -->
            <template v-if="selectedItem.catatan_admisi">
              <div class="bk-divider"></div>
              <div class="bk-detail-section">
                <p class="bk-section-title">Catatan Admisi</p>
                <p class="text-xs" style="color:var(--text-secondary)">{{ selectedItem.catatan_admisi }}</p>
              </div>
            </template>

          </div>
        </Transition>

      </div><!-- /bk-content -->
    </div><!-- /bk-panel--right -->

  </div><!-- /bk-main -->
</div>

<!-- ══ MODAL BU ══════════════════════════════════════════════ -->
<Transition enter-active-class="transition-all duration-200" enter-from-class="opacity-0" leave-to-class="opacity-0">
  <div v-if="modal.open" class="mp-modal-overlay" @click.self="closeModal">
    <Transition enter-active-class="transition-all duration-200" enter-from-class="opacity-0 scale-95" leave-to-class="opacity-0 scale-95">
      <div v-if="modal.type === 'spri'" class="mp-modal">
        <!-- Modal header -->
        <div class="mp-modal-header-banner">
          <svg class="w-5 h-5 text-white flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
          <p class="text-sm font-bold text-white">Booking ICU — Pasien Internal</p>
        </div>
        <div class="mp-modal-sub-header">
          <p class="text-sm" style="color:var(--text-secondary)">Isi data klinis dan kirim langsung ke ICU</p>
          <button @click="closeModal" class="mp-modal-close">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
        <!-- Form -->
        <form @submit.prevent="submitSpri" class="mp-modal-body">
          <!-- SSO patient info banner -->
          <div v-if="isSSO && lookupResult?.found" class="mp-modal-patient-banner">
            <div class="mp-modal-patient-av" :style="`background:${gColor(lookupResult.jenis_kelamin||'')}18;color:${gColor(lookupResult.jenis_kelamin||'')}`">
              {{ gIcon(lookupResult.jenis_kelamin || '') }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="font-bold text-sm" style="color:#00A884">{{ lookupResult.nama_pasien }}</p>
              <p class="text-xs mt-0.5 font-mono" style="color:var(--text-secondary)">{{ fmSpri.No_MR }} · {{ fmSpri.No_Reg }} · {{ fmSpri.asal_ruang || '—' }}</p>
            </div>
            <span class="mp-sso-verified hidden sm:flex">SSO ✓</span>
          </div>

          <!-- Step 1: Verifikasi Pasien -->
          <div class="mp-modal-step">
            <div class="mp-step-header">
              <span class="mp-step-num" style="background:#00A884">1</span>
              <p class="mp-step-title" style="color:#00A884">Verifikasi Pasien</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="mp-label">No. Medical Record <span style="color:#E74C3C">*</span></label>
                <div v-if="isSSO" class="relative">
                  <input :value="fmSpri.No_MR" readonly tabindex="-1" class="mp-input-readonly mp-input-verified"/>
                  <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-bold" style="color:#00A884">✓</span>
                </div>
                <div v-else class="relative">
                  <input v-model="fmSpri.No_MR" required placeholder="Ketik No. MR..." class="mp-input"
                    :style="fmSpri.errors.No_MR||lookupError?'border-color:#E74C3C':lookupResult?.found?'border-color:#00A884':''"/>
                </div>
                <p v-if="isSSO && lookupResult?.found" class="text-xs mt-1 font-semibold" style="color:#00A884">✓ {{ lookupResult.nama_pasien }}</p>
              </div>
              <div>
                <label class="mp-label">No. Registrasi <span style="color:#E74C3C">*</span></label>
                <div v-if="isSSO && fmSpri.No_Reg">
                  <input :value="fmSpri.No_Reg" readonly tabindex="-1" class="mp-input-readonly mp-input-verified"/>
                </div>
                <select v-else-if="!isSSO && kunjungans.length > 1" v-model="fmSpri.No_Reg" @change="onKunjunganChange(fmSpri.No_Reg)" class="mp-select">
                  <option value="" disabled>-- Pilih Kunjungan --</option>
                  <option v-for="k in kunjungans" :key="k.No_Reg" :value="k.No_Reg">{{ k.No_Reg }}{{ k.asal_ruang?' — '+k.asal_ruang:'' }}</option>
                </select>
                <input v-else :value="fmSpri.No_Reg" readonly tabindex="-1" class="mp-input-readonly"
                  :placeholder="!lookupResult?.found?'Isi No. MR dulu':'Tidak ada kunjungan'"/>
              </div>
            </div>
          </div>

          <!-- Step 2: Data RM -->
          <div class="mp-modal-step">
            <div class="mp-step-header">
              <span class="mp-step-num" style="background:#5A6B7C">2</span>
              <p class="mp-step-title" style="color:#5A6B7C">Data dari Rekam Medis</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="mp-label">Diagnosis (RM)</label>
                <input :value="diagnosisExisting || '—'" readonly class="mp-input-readonly" :class="isSSO&&diagnosisExisting?'mp-input-verified':''"/>
              </div>
              <div>
                <label class="mp-label">Asal Ruang</label>
                <input :value="fmSpri.asal_ruang || '—'" readonly class="mp-input-readonly" :class="isSSO&&fmSpri.asal_ruang?'mp-input-verified':''"/>
              </div>
              <div>
                <label class="mp-label">Dokter DPJP</label>
                <input :value="fmSpri.Dokter || '—'" readonly class="mp-input-readonly" :class="isSSO&&fmSpri.Dokter?'mp-input-verified':''"/>
              </div>
              <div>
                <label class="mp-label">Jaminan</label>
                <input :value="jaminanExisting || '—'" readonly class="mp-input-readonly" :class="isSSO&&jaminanExisting?'mp-input-verified':''"/>
              </div>
            </div>
          </div>

          <!-- Step 3: Data Klinis -->
          <div class="mp-modal-step">
            <div class="mp-step-header">
              <span class="mp-step-num" style="background:#00A884">3</span>
              <p class="mp-step-title" style="color:#00A884">Data Klinis untuk ICU</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="mp-label">Diagnosis ICU <span style="color:#E74C3C">*</span></label>
                <Icd10Search v-model="fmSpri.Diagnosis" placeholder="Cari kode / keterangan ICD-10" :required="true" :has-error="!!fmSpri.errors.Diagnosis"/>
                <p v-if="fmSpri.errors.Diagnosis" class="text-xs mt-1" style="color:#E74C3C">{{ fmSpri.errors.Diagnosis }}</p>
              </div>
              <div>
                <label class="mp-label">Indikasi Rawat ICU <span style="color:#E74C3C">*</span></label>
                <input v-model="fmSpri.IndikasiRI" required placeholder="Alasan klinis butuh ICU" class="mp-input"
                  :style="fmSpri.errors.IndikasiRI?'border-color:#E74C3C':''"/>
              </div>
              <div class="sm:col-span-2">
                <label class="mp-label">Keterangan Tambahan</label>
                <textarea v-model="fmSpri.Keterangan" rows="2" placeholder="Kondisi terkini, catatan penting..." class="mp-textarea"></textarea>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="mp-modal-footer">
            <button type="button" @click="closeModal" class="mp-btn-cancel">Batal</button>
            <button type="submit" :disabled="!canSubmit || fmSpri.processing" class="mp-btn-submit"
              :style="canSubmit&&!fmSpri.processing?'background:#00A884;box-shadow:0 4px 14px rgba(0,168,132,.3)':'background:#f1f5f9;color:#94a3b8'">
              {{ fmSpri.processing ? 'Menyimpan…' : 'Kirim Booking ICU ke ICU' }}
            </button>
          </div>
        </form>
      </div>
    </Transition>
  </div>
</Transition>

</AppLayout>
</template>

<style scoped>
/* ══ BASE ══════════════════════════════════════════════════════════════════ */
.mp-wrap { min-height:100%; background:var(--bg-main); font-family:'Inter','Plus Jakarta Sans',sans-serif; padding:20px 16px; }
@media (min-width:640px) { .mp-wrap { padding:20px 24px; } }

/* ══ HERO HEADER ═══════════════════════════════════════════════════════════ */
.bk-hero { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;
  background:#00A884; border-radius:14px; padding:16px 20px;
  box-shadow:0 4px 20px rgba(0,168,132,.2); }
.bk-hero-left { display:flex; align-items:center; gap:14px; }
.bk-hero-logo { width:44px; height:44px; border-radius:12px; background:rgba(255,255,255,.18);
  border:1px solid rgba(255,255,255,.22); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.bk-hero-sub { font-size:11px; color:rgba(255,255,255,.65); font-weight:500; margin-bottom:2px; }
.bk-hero-title { font-size:clamp(18px,3vw,24px); font-weight:900; color:#fff; letter-spacing:-.02em; line-height:1.1; }
.bk-btn-new { display:flex; align-items:center; gap:7px; padding:9px 16px; border-radius:10px;
  background:#fff; color:#00A884; font-size:13px; font-weight:700; border:none; cursor:pointer;
  box-shadow:0 2px 10px rgba(0,0,0,.12); transition:all .15s; white-space:nowrap; flex-shrink:0; }
.bk-btn-new:hover { transform:translateY(-1px); box-shadow:0 4px 16px rgba(0,0,0,.15); }

/* ══ SUMMARY CARDS ═════════════════════════════════════════════════════════ */
.bk-summary-row { display:flex; gap:10px; flex-wrap:wrap; }
.bk-card { flex:1; min-width:100px; background:var(--bg-card); border:1px solid var(--border-default);
  border-radius:12px; padding:12px 14px; text-align:left; cursor:pointer;
  transition:all .2s; box-shadow:var(--shadow-card); }
.bk-card:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(0,0,0,.08); }
.bk-card--active { border-color:var(--card-color) !important; box-shadow:0 0 0 3px color-mix(in srgb, var(--card-color) 12%, transparent) !important; }
.bk-card-num { font-size:24px; font-weight:900; color:var(--text-primary); font-family:'DM Mono',monospace; line-height:1.1; }
.bk-card-label { font-size:10px; font-weight:600; color:var(--text-secondary); margin-top:3px; }

/* ══ MAIN LAYOUT ═══════════════════════════════════════════════════════════ */
.bk-main { display:grid; grid-template-columns:1fr; gap:16px; }
@media(min-width:1024px) { .bk-main { grid-template-columns:300px 1fr; } }

/* ══ PANELS ════════════════════════════════════════════════════════════════ */
.bk-panel { background:var(--bg-card); border:1px solid var(--border-default);
  border-radius:14px; box-shadow:var(--shadow-card); overflow:hidden; display:flex; flex-direction:column; }
.bk-panel--left { height:600px; }
.bk-panel--right { min-height:500px; }
.bk-panel-head { padding:14px 14px 10px; border-bottom:1px solid var(--border-default); background:var(--bg-surface); flex-shrink:0; }
.bk-panel-icon { width:32px; height:32px; border-radius:9px; background:rgba(0,168,132,.1);
  display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.bk-panel-body { flex:1; overflow-y:auto; padding:10px; }

/* ══ SEARCH ════════════════════════════════════════════════════════════════ */
.bk-search-wrap { position:relative; }
.bk-search-icon { position:absolute; left:10px; top:50%; transform:translateY(-50%);
  width:15px; height:15px; color:var(--text-muted); }
.bk-search { width:100%; padding:9px 12px 9px 34px; border:1.5px solid var(--border-default);
  border-radius:10px; font-size:13px; color:var(--text-primary); background:var(--bg-input);
  outline:none; transition:border-color .2s; }
.bk-search:focus { border-color:#00A884; box-shadow:0 0 0 3px rgba(0,168,132,.1); }

/* ══ PASIEN BANGSAL LIST ═══════════════════════════════════════════════════ */
.bk-ruang-head { display:flex; align-items:center; gap:6px; padding:5px 10px; border-radius:8px;
  background:rgba(255,255,255,.9); border:1px solid var(--border-default);
  position:sticky; top:0; z-index:5; margin-bottom:6px; }
.bk-ruang-count { font-size:9px; font-weight:700; padding:2px 6px; border-radius:20px;
  background:rgba(0,168,132,.1); color:#00A884; }
.bk-pasien-row { display:flex; align-items:center; gap:9px; padding:9px 10px; border-radius:10px;
  cursor:pointer; border:1px solid var(--border-default); background:var(--bg-surface);
  transition:all .15s; margin-bottom:4px; }
.bk-pasien-row:hover { border-color:rgba(0,168,132,.3); box-shadow:0 3px 10px rgba(0,0,0,.05); transform:translateY(-1px); }
.bk-av { width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center;
  font-size:14px; font-weight:700; flex-shrink:0; }
.bk-arrow-btn { width:24px; height:24px; border-radius:7px; background:#00A884;
  display:flex; align-items:center; justify-content:center; }

/* ══ LOCAL NOTE ════════════════════════════════════════════════════════════ */
.bk-local-note { display:flex; flex-direction:column; align-items:center; justify-content:center;
  padding:48px 24px; background:var(--bg-card); border-radius:14px; border:1px solid var(--border-default);
  text-align:center; }

/* ══ TABS ══════════════════════════════════════════════════════════════════ */
.bk-tab-bar { display:flex; align-items:center; justify-content:space-between; padding:0 14px;
  border-bottom:1px solid var(--border-default); background:var(--bg-surface); flex-shrink:0; }
.bk-tabs { display:flex; gap:0; }
.bk-tab { padding:12px 14px; font-size:13px; font-weight:500; color:var(--text-secondary);
  border:none; background:transparent; cursor:pointer; border-bottom:2px solid transparent;
  margin-bottom:-1px; display:flex; align-items:center; gap:6px; transition:all .15s; }
.bk-tab:hover:not(.bk-tab--active) { color:var(--text-primary); }
.bk-tab--active { color:#00A884; border-bottom-color:#00A884; font-weight:700; }
.bk-tab-badge { font-size:10px; font-weight:700; padding:2px 7px; border-radius:20px;
  background:var(--bg-input); color:var(--text-secondary); }
.bk-tab-badge--active { background:rgba(0,168,132,.12); color:#00A884; }
.bk-filter-toggle { display:flex; align-items:center; gap:5px; padding:7px 11px; border-radius:8px;
  border:1.5px solid var(--border-default); background:var(--bg-input); cursor:pointer;
  font-size:12px; font-weight:600; color:var(--text-secondary); transition:all .15s; position:relative; }
.bk-filter-toggle:hover { border-color:#00A884; color:#00A884; }
.bk-filter-toggle--active { border-color:#00A884; color:#00A884; background:rgba(0,168,132,.07); }
.bk-filter-dot { position:absolute; top:5px; right:5px; width:7px; height:7px;
  border-radius:50%; background:#E74C3C; border:1.5px solid white; }

/* ══ FILTER PANEL ══════════════════════════════════════════════════════════ */
.bk-filter-panel { padding:12px 14px; border-bottom:1px solid var(--border-default);
  background:rgba(0,168,132,.03); flex-shrink:0; }
.bk-filter-grid { display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; margin-bottom:10px; }
@media(max-width:640px) { .bk-filter-grid { grid-template-columns:1fr; } }
.bk-label { display:block; font-size:10px; font-weight:700; color:var(--text-muted);
  margin-bottom:5px; text-transform:uppercase; letter-spacing:.05em; }
.bk-input { width:100%; padding:8px 11px; border:1.5px solid var(--border-default);
  border-radius:9px; font-size:13px; color:var(--text-primary); background:var(--bg-input);
  outline:none; transition:border-color .2s; }
.bk-input:focus { border-color:#00A884; }
.bk-select { width:100%; padding:8px 11px; border:1.5px solid var(--border-default);
  border-radius:9px; font-size:13px; color:var(--text-primary); background:var(--bg-input); outline:none; }
.bk-filter-actions { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px; }
.bk-preset-group { display:flex; gap:2px; padding:3px; border-radius:9px; background:var(--bg-input); }
.bk-preset-btn { padding:5px 10px; border-radius:7px; border:none; font-size:11px; font-weight:600;
  cursor:pointer; background:transparent; color:var(--text-muted); transition:all .15s; }
.bk-preset-btn--active { background:#fff; color:#00A884; box-shadow:0 1px 4px rgba(0,0,0,.08); }
.bk-sort-btn { font-size:11px; font-weight:600; padding:6px 10px; border-radius:8px;
  border:1.5px solid var(--border-default); cursor:pointer; background:var(--bg-input);
  color:var(--text-secondary); transition:all .15s; }
.bk-sort-btn--active { background:rgba(0,168,132,.1); color:#00A884; border-color:rgba(0,168,132,.3); }
.bk-reset-btn { display:flex; align-items:center; gap:5px; padding:6px 10px; border-radius:8px;
  background:#FEF2F2; color:#DC2626; border:1.5px solid rgba(220,38,38,.15);
  cursor:pointer; font-size:11px; font-weight:700; }

/* ══ CONTENT (list + detail) ═══════════════════════════════════════════════ */
.bk-content { display:flex; flex:1; overflow:hidden; min-height:0; }
.bk-list { flex:1; overflow-y:auto; display:flex; flex-direction:column; transition:all .2s; min-width:0; }
.bk-list--narrow { flex:0 0 55%; }

/* ══ TABLE HEADER ══════════════════════════════════════════════════════════ */
.bk-list-header { display:grid; grid-template-columns:1fr 130px 70px 100px 28px;
  padding:8px 14px; font-size:10px; font-weight:700; color:var(--text-muted);
  text-transform:uppercase; letter-spacing:.06em; border-bottom:1px solid var(--border-default);
  background:var(--bg-surface); flex-shrink:0; }
@media(max-width:768px) { .bk-list-header { grid-template-columns:1fr 90px 90px 28px; }
  .bk-list-header span:nth-child(3) { display:none; } }

/* ══ ROWS ══════════════════════════════════════════════════════════════════ */
.bk-row { display:grid; grid-template-columns:1fr 130px 70px 100px 28px;
  padding:11px 14px; border-bottom:1px solid var(--border-default); cursor:pointer;
  align-items:center; transition:background .12s; }
.bk-row:hover { background:var(--bg-row-hover); }
.bk-row--active { background:rgba(0,168,132,.05) !important; border-left:3px solid #00A884; padding-left:11px; }
@media(max-width:768px) { .bk-row { grid-template-columns:1fr 90px 90px 28px; }
  .bk-row > div:nth-child(3) { display:none; } }
.bk-row-name { font-size:13px; font-weight:700; color:var(--text-primary); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.bk-row-mr { font-size:10px; color:var(--text-muted); font-family:'DM Mono',monospace; margin-top:1px; }
.bk-row-date { font-size:11px; color:var(--text-primary); }
.bk-row-date-sub { font-size:10px; color:var(--text-muted); margin-top:1px; }
.bk-row-jaminan { font-size:12px; color:var(--text-secondary); }
.bk-row-chevron { display:flex; justify-content:flex-end; color:var(--text-muted); }

/* ══ STATUS PILL ═══════════════════════════════════════════════════════════ */
.bk-pill { display:inline-flex; align-items:center; gap:4px;
  font-size:10px; font-weight:700; padding:4px 9px; border-radius:20px; }
.bk-pill-dot { width:6px; height:6px; border-radius:50%; flex-shrink:0; }

/* ══ DETAIL PANEL ══════════════════════════════════════════════════════════ */
.bk-detail { width:45%; border-left:1px solid var(--border-default); background:var(--bg-surface);
  overflow-y:auto; flex-shrink:0; display:flex; flex-direction:column; }
.bk-detail-head { display:flex; align-items:center; justify-content:space-between;
  padding:11px 14px; border-bottom:1px solid var(--border-default); flex-shrink:0; }
.bk-detail-close { background:var(--bg-input); border:1px solid var(--border-default);
  border-radius:7px; color:var(--text-secondary); cursor:pointer; width:26px; height:26px;
  display:flex; align-items:center; justify-content:center; transition:all .15s; }
.bk-detail-close:hover { background:var(--bg-card); color:var(--text-primary); }
.bk-detail-patient { display:flex; align-items:flex-start; gap:10px; padding:14px; }
.bk-detail-av { width:40px; height:40px; border-radius:11px; display:flex; align-items:center;
  justify-content:center; font-size:11px; font-weight:700; flex-shrink:0; letter-spacing:-.5px; }
.bk-detail-name { font-size:14px; font-weight:700; color:var(--text-primary); }
.bk-detail-mr { font-size:11px; color:var(--text-muted); font-family:'DM Mono',monospace; margin-top:1px; }
.bk-divider { height:1px; background:var(--border-default); }
.bk-detail-section { padding:12px 14px; }
.bk-section-title { font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:.07em;
  color:var(--text-muted); margin-bottom:8px; }
.bk-detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
.bk-field-label { font-size:9px; color:var(--text-muted); text-transform:uppercase; letter-spacing:.06em; margin-bottom:2px; }
.bk-field-val { font-size:13px; font-weight:600; color:var(--text-primary); }
.bk-field-sub { font-size:11px; color:var(--text-secondary); margin-top:1px; }
.bk-waiting-banner { border-radius:9px; overflow:hidden; border:1.5px solid #FCD34D; }
.bk-waiting-banner-head { display:flex; align-items:center; gap:6px; padding:7px 10px; background:#FEF3C7; }

/* ══ EMPTY ═════════════════════════════════════════════════════════════════ */
.bk-empty { display:flex; flex-direction:column; align-items:center; gap:8px; text-align:center;
  padding:48px 16px; }

/* ══ TRANSITIONS ═══════════════════════════════════════════════════════════ */
.slide-down-enter-active, .slide-down-leave-active { transition:all .2s ease; }
.slide-down-enter-from, .slide-down-leave-to { opacity:0; transform:translateY(-8px); }
.slide-left-enter-active, .slide-left-leave-active { transition:all .2s ease; }
.slide-left-enter-from, .slide-left-leave-to { opacity:0; transform:translateX(12px); }

/* ══ MODAL (unchanged) ══════════════════════════════════════════════════════ */
.mp-modal-overlay { position:fixed; inset:0; z-index:50; display:flex; align-items:center; justify-content:center; padding:16px; background:rgba(0,0,0,.6); backdrop-filter:blur(4px); }
.mp-modal { width:100%; max-width:680px; max-height:92vh; overflow-y:auto; border-radius:20px; background:var(--bg-sidebar,#fff); border:1px solid var(--border-default); box-shadow:0 24px 64px rgba(0,0,0,.3); position:sticky; top:0; }
.mp-modal-header-banner { display:flex; align-items:center; gap:10px; padding:14px 20px; background:#00A884; border-radius:20px 20px 0 0; }
.mp-modal-sub-header { display:flex; align-items:center; justify-content:space-between; padding:10px 20px; border-bottom:1px solid var(--border-default); background:var(--bg-surface); }
.mp-modal-close { padding:6px; border-radius:9px; background:var(--bg-input); border:1px solid var(--border-default); cursor:pointer; color:var(--text-secondary); transition:all .15s; }
.mp-modal-close:hover { background:var(--bg-card-hover); }
.mp-modal-body { padding:20px; display:flex; flex-direction:column; gap:20px; }
.mp-modal-patient-banner { display:flex; align-items:center; gap:12px; padding:12px 16px; border-radius:12px; background:rgba(0,168,132,.07); border:1px solid rgba(0,168,132,.2); }
.mp-modal-patient-av { width:38px; height:38px; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:14px; font-weight:700; flex-shrink:0; }
.mp-sso-verified { font-size:9px; font-weight:700; padding:3px 8px; border-radius:20px; background:rgba(0,168,132,.15); color:#00A884; }
.mp-modal-step { background:var(--bg-surface); border:1px solid var(--border-default); border-radius:14px; padding:16px; }
.mp-step-header { display:flex; align-items:center; gap:8px; margin-bottom:14px; }
.mp-step-num { width:24px; height:24px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; color:#fff; flex-shrink:0; }
.mp-step-title { font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; }
.mp-input-readonly { width:100%; padding:9px 12px; border:1.5px solid var(--border-default); border-radius:11px; font-size:13px; color:var(--text-primary); background:var(--bg-input); outline:none; opacity:.7; cursor:not-allowed; font-family:'DM Mono',monospace; }
.mp-input-verified { border-color:rgba(0,168,132,.4) !important; background:rgba(0,168,132,.05) !important; opacity:1 !important; }
.mp-modal-footer { display:flex; justify-content:flex-end; gap:10px; padding-top:16px; border-top:1px solid var(--border-default); }
.mp-btn-cancel { font-size:13px; font-weight:600; padding:10px 18px; border-radius:11px; background:var(--bg-input); color:var(--text-secondary); border:1px solid var(--border-default); cursor:pointer; }
.mp-btn-submit { font-size:13px; font-weight:700; padding:10px 22px; border-radius:11px; color:#fff; border:none; cursor:pointer; transition:all .15s; }
.mp-btn-submit:disabled { cursor:not-allowed; }
.mp-label { display:block; font-size:11px; font-weight:600; color:var(--text-muted); margin-bottom:6px; text-transform:uppercase; letter-spacing:.05em; }
.mp-input { width:100%; padding:9px 12px; border:1.5px solid var(--border-default); border-radius:11px; font-size:13px; color:var(--text-primary); background:var(--bg-input); outline:none; transition:border-color .2s; }
.mp-input:focus { border-color:#00A884; }
.mp-select { width:100%; padding:9px 12px; border:1.5px solid var(--border-default); border-radius:11px; font-size:13px; color:var(--text-primary); background:var(--bg-input); outline:none; }
.mp-textarea { width:100%; padding:9px 12px; border:1.5px solid var(--border-default); border-radius:11px; font-size:13px; color:var(--text-primary); background:var(--bg-input); outline:none; resize:none; }
</style>