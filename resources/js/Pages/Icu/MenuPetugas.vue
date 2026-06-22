<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout   from '@/Layouts/AppLayout.vue'
import Icd10Search from '@/Components/Icd10Search.vue'
import { useAuth } from '@/composables/useAuth.js'

const { canBuatSpriInternal, isAdmin } = useAuth()
const props = defineProps({
    spriList:     { type: Array,   default: () => [] },
    summary:      { type: Object,  default: () => ({}) },
    filters:      { type: Object,  default: () => ({}) },
    pasienAktif:  { type: Array,   default: () => [] },
    wardIds:      { type: Array,   default: () => [] },
    authProvider: { type: String,  default: 'local' },
    isIgdUser:    { type: Boolean, default: false },
    unitKerja:    { type: String,  default: '' },
    kamarKosong:  { type: Array,   default: () => [] },
    masterKelas:  { type: Array,   default: () => [] },
    flash:        { type: Object,  default: () => ({}) },
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
    { status: fStatus.value, nama: fNama.value,
      tgl_dari: fTglDari.value, tgl_sampai: fTglAkh.value,
      sort: sortBy.value, dir: sortDir.value },
    { preserveState: true, replace: true, preserveScroll: true })
const onNamaInput = () => { clearTimeout(ft); ft = setTimeout(applyFilters, 400) }
const resetFilter = () => {
    fStatus.value = ''; fNama.value = ''; fTgl.value = ''
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
    bed_verified:   { bg: 'rgba(0,168,132,.15)',   color: '#00A884', dot: '#00A884' },
    ditolak:        { bg: 'rgba(231,76,60,.15)',   color: '#E74C3C', dot: '#E74C3C' },
}
const ss     = (s) => SS[s] ?? { bg: 'var(--bg-input)', color: 'var(--text-secondary)', dot: '#888' }
const gIcon  = (g) => g === 'L' ? '♂' : g === 'P' ? '♀' : '·'
const gColor = (g) => g === 'L' ? '#00A884' : g === 'P' ? '#8E44AD' : 'var(--text-secondary)'

// ── Summary cards ─────────────────────────────────────────────────────────────
const CARDS = computed(() => [
    { key: '',              label: 'Total',      val: props.summary.total        ?? 0, color: '#5A6B7C' },
    { key: 'pending_icu',   label: 'Menunggu ICU', val: props.summary.pending_icu  ?? 0, color: '#E0923A' },
    { key: 'bed_verified',  label: 'Bed Verified', val: props.summary.bed_verified  ?? 0, color: '#00A884' },
    { key: 'ditolak',       label: 'Ditolak',      val: props.summary.ditolak       ?? 0, color: '#E74C3C' },
])

const statusOptions = [
    { value: '',              label: 'Semua Status' },
    { value: 'pending_icu',   label: 'Menunggu ICU' },
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
</script>


<template>
<AppLayout :flash="flash" page-title="Menu Rawat Inap">
<div class="mp-wrap">

  <!-- ══ PAGE HEADER ══════════════════════════════════════════ -->
  <div class="mp-header">
    <div class="mp-header-left">
      <div class="mp-header-icon">
        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
      </div>
      <div>
        <h1 class="mp-page-title">Permintaan Rawat ICU</h1>
        <p class="mp-page-sub">
          <span v-if="isSSO" class="mp-sso-badge">
            <span class="mp-live-dot" style="background:#00A884"></span>
            SSO · Bangsal: <strong style="color:#00A884">{{ wardIds.join(', ') || '-' }}</strong>
          </span>
          <span v-else class="mp-sso-badge">
            <span class="mp-live-dot" style="background:#00A884"></span>
            Login Lokal · Data MySQL
          </span>
        </p>
      </div>
    </div>
    <button v-if="!isSSO && (canBuatSpriInternal || isAdmin)" @click="openModal('spri')" class="mp-add-btn">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
      </svg>
      Buat BU Manual
    </button>
  </div>

  <!-- ══ KPI CARDS ════════════════════════════════════════════ -->
  <div class="mp-kpi-grid">
    <button v-for="c in CARDS" :key="c.key" @click="fStatus = c.key; applyFilters()" class="mp-kpi-card kpi-card"
      :style="fStatus === c.key ? `border-color:${c.color}; box-shadow:0 0 0 3px ${c.color}18` : ''">
      <span class="mp-kpi-bar" :style="`background:${c.color}; opacity:${fStatus===c.key?'1':'0.3'}`"></span>
      <div class="mp-kpi-inner">
        <div class="mp-kpi-icon-wrap" :style="`background:${c.color}12`">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" :style="`color:${c.color}`">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
        </div>
        <div>
          <p class="mp-kpi-val" :style="`color:${c.color}`">{{ c.val }}</p>
          <p class="mp-kpi-label">{{ c.label }}</p>
        </div>
      </div>
    </button>
  </div>

  <!-- ══ MAIN 2-COLUMN GRID ══════════════════════════════════ -->
  <div class="mp-main-grid">

    <!-- LEFT: Daftar Pasien (SSO) -->
    <div>
      <div v-if="isSSO" class="mp-panel mp-pasien-panel">
        <!-- Panel header -->
        <div class="mp-panel-header">
          <div class="flex items-center gap-3">
            <div class="mp-panel-icon">
              <svg class="w-4 h-4" style="color:#00A884" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
              </svg>
            </div>
            <div>
              <p class="text-sm font-bold" style="color:var(--text-primary)">
                {{ isIgdUser ? 'Pasien Aktif — IGD' : 'Pasien Bangsal' }}
                <strong style="color:#00A884">{{ wardIds.join(', ') || '-' }}</strong>
              </p>
              <p class="text-xs" style="color:var(--text-muted)">Klik untuk request ICU</p>
            </div>
          </div>
          <!-- Search -->
          <div class="relative w-full mt-3">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4" style="color:var(--text-muted)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input v-model="cariPasien" placeholder="Cari nama / No. MR..." class="mp-search-input"/>
          </div>
        </div>
        <!-- Patient list -->
        <div class="mp-pasien-list">
          <div v-if="Object.keys(pasienPerRuang).length === 0" class="mp-empty">
            <div class="mp-empty-icon"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></div>
            <p class="text-sm font-semibold" style="color:var(--text-secondary)">Semua pasien sudah diproses</p>
            <p class="text-xs mt-1" style="color:var(--text-muted)">Atau tidak ada data aktif</p>
          </div>
          <template v-for="(pasiens, ruang) in pasienPerRuang" :key="ruang">
            <div v-if="pasiens.length > 0" class="mb-4">
              <div class="mp-ruang-header">
                <span>🏥</span>
                <span class="text-xs font-bold uppercase tracking-widest" style="color:var(--text-accent)">{{ ruang }}</span>
                <span class="mp-ruang-count">{{ pasiens.length }}</span>
              </div>
              <div class="flex flex-col gap-2">
                <div v-for="p in pasiens" :key="p.No_MR + (p.No_Reg ?? '')"
                  @click="pilihPasien(p)" class="mp-pasien-item group">
                  <div class="mp-pasien-av" :style="`background:${gColor(p.jenis_kelamin)}18; color:${gColor(p.jenis_kelamin)}`">
                    {{ gIcon(p.jenis_kelamin) }}
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold truncate" style="color:var(--text-primary)">{{ p.Nama_Pasien }}</p>
                    <p class="text-xs mt-0.5" style="color:var(--text-muted);font-family:'DM Mono',monospace">
                      {{ p.No_MR }}
                      <span v-if="p.No_Reg" class="ml-1 px-1.5 py-0.5 rounded text-xs" style="color:#00A884;background:#ECFDF5">· {{ p.No_Reg }}</span>
                    </p>
                  </div>
                  <div class="text-right flex-shrink-0 hidden sm:block">
                    <p class="text-xs font-semibold truncate max-w-[100px]" style="color:var(--text-primary)">{{ p.Dokter || '—' }}</p>
                    <p class="text-xs truncate max-w-[100px] mt-0.5" style="color:var(--text-muted)">{{ p.Nama_RuangM || '—' }}</p>
                  </div>
                  <div class="opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0">
                    <div class="mp-arrow-btn">
                      <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                      </svg>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </div>
      </div>
      <div v-else class="mp-local-note">
        <svg class="w-8 h-8 mb-3" style="color:var(--text-muted)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-sm font-semibold" style="color:var(--text-secondary)">Mode Login Lokal</p>
        <p class="text-xs mt-1" style="color:var(--text-muted)">Gunakan tombol "Buat BU Manual" di atas untuk mencari pasien.</p>
      </div>
    </div>

    <!-- RIGHT: Filter + Riwayat BU -->
    <div class="flex flex-col gap-4">

      <!-- Filter card -->
      <div class="mp-filter-card">
        <div class="flex items-center gap-2 mb-3">
          <svg class="w-4 h-4" style="color:var(--text-accent)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
          </svg>
          <p class="text-sm font-bold" style="color:var(--text-primary)">Filter Riwayat BU</p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
          <div>
            <label class="mp-label">Status</label>
            <select v-model="fStatus" @change="applyFilters" class="mp-select">
              <option v-for="o in statusOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
          </div>
          <div>
            <label class="mp-label">Cari Nama / No. MR</label>
            <input v-model="fNama" @input="onNamaInput" placeholder="Nama / No. MR..." class="mp-input"/>
          </div>
          <div>
            <label class="mp-label">Tgl Mulai</label>
            <input v-model="fTglDari" @change="applyFilters" type="date" class="mp-input"/>
          </div>
          <div>
            <label class="mp-label">Tgl Akhir</label>
            <input v-model="fTglAkh" @change="applyFilters" type="date" :min="fTglDari" class="mp-input"/>
          </div>
        </div>
        <!-- Preset + sort row -->
        <div class="flex flex-wrap items-center gap-2">
          <div class="flex gap-1 p-1 rounded-xl" style="background:var(--bg-input)">
            <button v-for="p in [{l:'Hari ini',d:today,s:today},{l:'Kemarin',d:yesterday,s:yesterday},{l:'7 Hari',d:week7,s:today}]"
              :key="p.l" @click="setPreset(p.d,p.s)"
              class="px-2.5 py-1 rounded-lg text-xs font-semibold transition-all"
              :style="fTglDari===p.d&&fTglAkh===p.s ? 'background:#fff;color:#00A884;box-shadow:0 1px 4px rgba(0,0,0,0.08)' : 'color:var(--text-muted)'">
              {{ p.l }}
            </button>
          </div>
          <div class="flex gap-2 flex-wrap">
            <button v-for="col in [{key:'created_at',label:'Waktu'},{key:'status',label:'Status'}]" :key="col.key"
              @click="toggleSort(col.key)" class="mp-sort-btn" :class="sortBy===col.key?'active':''">
              {{ col.label }} {{ sortIcon(col.key) }}
            </button>
            <button v-if="fStatus||fNama||fTgl||fTglDari||fTglAkh" @click="resetFilter" class="mp-reset-btn">✕</button>
          </div>
        </div>
      </div>
      <!-- Riwayat BU list -->
      <div class="mp-panel mp-riwayat-panel">
        <div class="mp-panel-header" style="padding-bottom:12px">
          <div class="flex items-center justify-between">
            <p class="text-sm font-bold" style="color:var(--text-primary)">Riwayat BU (Booking ICU)</p>
            <span v-if="spriList.length" class="mp-count-badge">{{ spriList.length }}</span>
          </div>
        </div>

        <!-- Empty -->
        <div v-if="!spriList.length" class="mp-empty" style="padding:48px 16px">
          <div class="mp-empty-icon">
            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
          </div>
          <p class="text-sm font-semibold" style="color:var(--text-secondary)">Belum ada BU (Booking ICU)</p>
          <p class="text-xs mt-1" style="color:var(--text-muted)">{{ isSSO ? 'Pilih pasien di daftar kiri untuk request.' : 'Klik Buat BU Manual untuk membuat.' }}</p>
        </div>

        <!-- List -->
        <div v-else class="mp-riwayat-list">
          <div v-for="item in spriList" :key="`hist-${item.id}`" class="mp-riwayat-item"
            :style="`border-left-color:${ss(item.status).dot}`">
            <!-- Top row -->
            <div class="flex justify-between items-start mb-2 gap-2">
              <span class="mp-status-pill" :style="`background:${ss(item.status).bg};color:${ss(item.status).color}`">
                <span class="w-1.5 h-1.5 rounded-full" :style="`background:${ss(item.status).color}`"></span>
                {{ item.status_label }}
              </span>
              <span class="text-xs font-mono" style="color:var(--text-muted)">{{ item.created_at_fmt }}</span>
            </div>
            <!-- Patient row -->
            <div class="flex items-center gap-2.5 mb-3">
              <div class="mp-riwayat-av" :style="`background:${gColor(item.jenis_kelamin)}18;color:${gColor(item.jenis_kelamin)}`">
                {{ gIcon(item.jenis_kelamin) }}
              </div>
              <div class="min-w-0 flex-1">
                <p class="text-sm font-bold truncate" style="color:var(--text-primary)">{{ item.nama_pasien }}</p>
                <p class="text-xs font-mono mt-0.5" style="color:var(--text-muted)">{{ item.No_MR }} · {{ item.No_Reg }}</p>
              </div>
            </div>
            <!-- Progress steps -->
            <div class="flex items-center justify-between px-2 mb-3">
              <div v-for="(step, idx) in [{id:'pending_icu',label:'ICU'},{id:'bed_verified',label:'Selesai'}]"
                :key="step.id" class="flex flex-col items-center flex-1 relative">
                <div v-if="idx < 1" class="absolute top-2.5 left-1/2 w-full h-0.5"
                  :style="`background:${item.status==='bed_verified'?'#00A884':'#e2e8f0'}`"></div>
                <div class="w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold z-10 relative"
                  :style="`background:${item.status===step.id?ss(item.status).color:item.status==='bed_verified'?'#00A884':'#cbd5e1'};color:white`">
                  {{ idx+1 }}
                </div>
                <span class="text-xs mt-1 font-semibold" :style="`color:${item.status===step.id?ss(item.status).color:'#94a3b8'}`">{{ step.label }}</span>
              </div>
            </div>
            <!-- Detail card -->
            <div class="mp-riwayat-detail">
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <p class="mp-detail-label">Diagnosa</p>
                  <p class="mp-detail-val truncate" :title="item.Diagnosis">{{ item.Diagnosis ?? '—' }}</p>
                  <p class="mp-detail-sub truncate" :title="item.IndikasiRI">{{ item.IndikasiRI ?? '—' }}</p>
                </div>
                <div>
                  <p class="mp-detail-label">Asal / DPJP</p>
                  <p class="mp-detail-val truncate">{{ item.asal_ruang ?? '—' }}</p>
                  <p class="mp-detail-sub truncate">{{ item.Dokter ?? '—' }}</p>
                </div>
              </div>
              <div v-if="item.nama_bed || item.catatan_admisi || item.alasan_tolak" class="mt-2 pt-2 border-t" style="border-color:var(--border-default)">
                <span v-if="item.nama_bed" class="mp-bed-badge">
                  🏥 {{ item.nama_bed }}{{ item.kebutuhan_bed ? ' · '+item.kebutuhan_bed : '' }}
                </span>
                <p v-if="item.catatan_admisi" class="text-xs mt-1" style="color:var(--text-secondary)">
                  <span class="font-semibold">Catatan:</span> {{ item.catatan_admisi }}
                </p>
                <p v-if="item.alasan_tolak" class="text-xs mt-1 font-semibold" style="color:#E74C3C">
                  <span style="color:#ef4444">Tolak:</span> {{ item.alasan_tolak }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
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
          <p class="text-sm font-bold text-white">BU (Booking ICU) — Pasien Internal</p>
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
              {{ fmSpri.processing ? 'Menyimpan…' : 'Kirim BU ke ICU' }}
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
.mp-wrap { min-height:100%; background:var(--bg-main); font-family:'Inter','Plus Jakarta Sans',sans-serif; padding:20px 16px; }
@media (min-width:640px) { .mp-wrap { padding:20px 24px; } }

/* Header */
.mp-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:20px; }
.mp-header-left { display:flex; align-items:center; gap:12px; }
.mp-header-icon { width:42px; height:42px; border-radius:12px; background:linear-gradient(135deg,#00A884,#007a61); display:flex; align-items:center; justify-content:center; flex-shrink:0; box-shadow:0 4px 12px rgba(0,168,132,.3); }
.mp-page-title { font-size:20px; font-weight:800; color:var(--text-primary); letter-spacing:-0.02em; }
.mp-page-sub { font-size:12px; color:var(--text-muted); margin-top:2px; }
.mp-sso-badge { display:inline-flex; align-items:center; gap:5px; }
.mp-live-dot { width:7px; height:7px; border-radius:50%; display:inline-block; flex-shrink:0; }
.mp-add-btn { display:flex; align-items:center; gap:7px; padding:9px 18px; border-radius:12px; font-size:13px; font-weight:700; color:#fff; background:#00A884; border:none; cursor:pointer; box-shadow:0 4px 14px rgba(0,168,132,.3); transition:all .15s; }
.mp-add-btn:hover { filter:brightness(1.08); transform:translateY(-1px); }

/* KPI */
.mp-kpi-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:10px; margin-bottom:20px; }
@media (min-width:640px) { .mp-kpi-grid { grid-template-columns:repeat(4,1fr); } }
.mp-kpi-card { background:var(--bg-card); border-radius:13px; border:2px solid var(--border-default); box-shadow:var(--shadow-card); padding:14px 16px; text-align:left; cursor:pointer; transition:all .18s; position:relative; overflow:hidden; }
.mp-kpi-bar { position:absolute; left:0; top:20%; bottom:20%; width:3px; border-radius:0 3px 3px 0; transition:opacity .18s; }
.mp-kpi-inner { display:flex; align-items:center; gap:12px; padding-left:6px; }
.mp-kpi-icon-wrap { width:40px; height:40px; border-radius:11px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.mp-kpi-val { font-size:26px; font-weight:800; font-family:'DM Mono',monospace; line-height:1; margin-bottom:4px; }
.mp-kpi-label { font-size:11px; color:var(--text-muted); font-weight:500; }

/* Main grid */
.mp-main-grid { display:grid; grid-template-columns:1fr; gap:20px; }
@media (min-width:1024px) { .mp-main-grid { grid-template-columns:1fr 1fr; } }

/* Panel */
.mp-panel { background:var(--bg-card); border-radius:16px; border:1px solid var(--border-default); box-shadow:var(--shadow-card); overflow:hidden; display:flex; flex-direction:column; }
.mp-pasien-panel { height:620px; }
.mp-riwayat-panel { flex:1; min-height:400px; }
.mp-panel-header { padding:14px 16px; border-bottom:1px solid var(--border-default); background:var(--bg-surface); flex-shrink:0; }
.mp-panel-icon { width:34px; height:34px; border-radius:10px; background:rgba(0,168,132,.1); display:flex; align-items:center; justify-content:center; flex-shrink:0; }

/* Search */
.mp-search-input { width:100%; padding:9px 12px 9px 36px; border:1.5px solid var(--border-default); border-radius:11px; font-size:13px; color:var(--text-primary); background:var(--bg-input); outline:none; transition:border-color .2s; }
.mp-search-input:focus { border-color:#00A884; box-shadow:0 0 0 3px rgba(0,168,132,.1); }

/* Pasien list */
.mp-pasien-list { overflow-y:auto; flex:1; padding:12px; background:rgba(248,250,252,0.5); }
.mp-ruang-header { padding:6px 12px; border-radius:9px; display:flex; align-items:center; gap:6px; margin-bottom:8px; background:rgba(255,255,255,0.9); border:1px solid var(--border-default); backdrop-filter:blur(8px); position:sticky; top:0; z-index:5; }
.mp-ruang-count { font-size:9px; font-weight:700; padding:2px 6px; border-radius:20px; background:rgba(0,168,132,.1); color:#00A884; }
.mp-pasien-item { display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:12px; cursor:pointer; border:1px solid var(--border-default); background:var(--bg-surface); transition:all .15s; }
.mp-pasien-item:hover { transform:translateY(-1px); border-color:rgba(0,168,132,.35); box-shadow:0 4px 12px rgba(0,0,0,.05); }
.mp-pasien-av { width:38px; height:38px; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:14px; font-weight:700; flex-shrink:0; }
.mp-arrow-btn { width:26px; height:26px; border-radius:8px; background:#00A884; display:flex; align-items:center; justify-content:center; }

/* Local note */
.mp-local-note { display:flex; flex-direction:column; align-items:center; justify-content:center; padding:48px 24px; background:var(--bg-card); border-radius:16px; border:1px solid var(--border-default); text-align:center; }

/* Filter */
.mp-filter-card { background:var(--bg-card); border-radius:14px; border:1px solid var(--border-default); padding:16px; box-shadow:var(--shadow-card); }
.mp-label { display:block; font-size:11px; font-weight:600; color:var(--text-muted); margin-bottom:6px; text-transform:uppercase; letter-spacing:.05em; }
.mp-input { width:100%; padding:9px 12px; border:1.5px solid var(--border-default); border-radius:11px; font-size:13px; color:var(--text-primary); background:var(--bg-input); outline:none; transition:border-color .2s; }
.mp-input:focus { border-color:#00A884; }
.mp-select { width:100%; padding:9px 12px; border:1.5px solid var(--border-default); border-radius:11px; font-size:13px; color:var(--text-primary); background:var(--bg-input); outline:none; }
.mp-textarea { width:100%; padding:9px 12px; border:1.5px solid var(--border-default); border-radius:11px; font-size:13px; color:var(--text-primary); background:var(--bg-input); outline:none; resize:none; }
.mp-sort-btn { flex:1; font-size:11px; font-weight:600; padding:8px 6px; border-radius:9px; border:1.5px solid var(--border-default); cursor:pointer; transition:all .15s; background:var(--bg-input); color:var(--text-secondary); }
.mp-sort-btn.active { background:rgba(0,168,132,.12); color:#00A884; border-color:rgba(0,168,132,.3); }
.mp-reset-btn { padding:8px 10px; border-radius:9px; background:#FEF2F2; color:#DC2626; border:1.5px solid rgba(220,38,38,.15); cursor:pointer; font-weight:700; font-size:12px; }

/* Count badge */
.mp-count-badge { background:#ECFDF5; color:#00A884; font-size:11px; font-weight:700; padding:2px 8px; border-radius:20px; }

/* Riwayat */
.mp-riwayat-list { overflow-y:auto; flex:1; }
.mp-riwayat-item { padding:14px 16px; border-left:4px solid transparent; border-bottom:1px solid var(--border-default); transition:background .12s; }
.mp-riwayat-item:hover { background:var(--bg-row-hover); }
.mp-status-pill { display:inline-flex; align-items:center; gap:5px; font-size:10px; font-weight:700; padding:4px 10px; border-radius:20px; }
.mp-riwayat-av { width:34px; height:34px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; flex-shrink:0; }
.mp-riwayat-detail { background:var(--bg-surface); border:1px solid var(--border-default); border-radius:10px; padding:10px 12px; }
.mp-detail-label { font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--text-muted); margin-bottom:3px; }
.mp-detail-val { font-size:12px; font-weight:600; color:var(--text-primary); }
.mp-detail-sub { font-size:11px; color:var(--text-secondary); margin-top:2px; }
.mp-bed-badge { display:inline-block; font-size:10px; font-weight:700; color:#059669; background:#ECFDF5; padding:3px 8px; border-radius:8px; border:1px solid rgba(16,185,129,.2); }

/* Empty */
.mp-empty { display:flex; flex-direction:column; align-items:center; gap:8px; text-align:center; }
.mp-empty-icon { width:44px; height:44px; border-radius:12px; background:var(--bg-input); display:flex; align-items:center; justify-content:center; color:var(--text-muted); }
.mp-empty-icon svg { width:22px; height:22px; }

/* Modal */
.mp-modal-overlay { position:fixed; inset:0; z-index:50; display:flex; align-items:center; justify-content:center; padding:16px; background:rgba(0,0,0,.6); backdrop-filter:blur(4px); }
.mp-modal { width:100%; max-width:680px; max-height:92vh; overflow-y:auto; border-radius:20px; background:var(--bg-sidebar,#fff); border:1px solid var(--border-default); box-shadow:0 24px 64px rgba(0,0,0,.3); position:sticky; top:0; }
.mp-modal-header-banner { display:flex; align-items:center; gap:10px; padding:14px 20px; background:linear-gradient(90deg,#00A884,#00A884); border-radius:20px 20px 0 0; }
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
</style>
