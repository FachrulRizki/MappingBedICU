<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout   from '@/Layouts/AppLayout.vue'
import Icd10Search from '@/Components/Icd10Search.vue'
import { useAuth } from '@/composables/useAuth.js'

const { canBuatSpriInternal, isAdmin } = useAuth()
const logoUrl      = `${import.meta.env.BASE_URL}images/logo-urip.png`
const doctorImgUrl = `${import.meta.env.BASE_URL}images/welcome-doctors.svg`

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

// ── Helpers ──────────────────────────────────────────────────────────────────
const localDate = (n = 0) => {
    const d = new Date(); d.setDate(d.getDate() + n)
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`
}
const _today = localDate(0)

// ── Filters ───────────────────────────────────────────────────────────────────
const fStatus  = ref(props.filters.status   ?? '')
const fNama    = ref(props.filters.nama     ?? '')
const fJaminan = ref(props.filters.jaminan  ?? '')
const fTglDari = ref(props.filters.fTglDari || _today)
const fTglAkh  = ref(props.filters.fTglAkh  || _today)
const sortBy   = ref(props.filters.sortBy   ?? 'created_at')
const sortDir  = ref(props.filters.sortDir  ?? 'desc')

const today     = _today
const yesterday = localDate(-1)
const week7     = localDate(-6)
const setPreset = (d, s) => { fTglDari.value = d; fTglAkh.value = s; applyFilters() }

let ft = null
const applyFilters = () => router.get(route('icu.menu_petugas'),
    { status: fStatus.value, nama: fNama.value, jaminan: fJaminan.value,
      tgl_dari: fTglDari.value, tgl_sampai: fTglAkh.value,
      sort: sortBy.value, dir: sortDir.value },
    { preserveState: true, replace: true, preserveScroll: true })
const onNamaInput = () => { clearTimeout(ft); ft = setTimeout(applyFilters, 400) }
const resetFilter = () => {
    fStatus.value = ''; fNama.value = ''; fJaminan.value = ''
    fTglDari.value = localDate(0); fTglAkh.value = localDate(0)
    applyFilters()
}
const toggleSort = (col) => {
    sortDir.value = sortBy.value === col ? (sortDir.value === 'asc' ? 'desc' : 'asc') : 'desc'
    sortBy.value  = col; applyFilters()
}
const sortIcon = (col) => sortBy.value !== col ? '↕' : sortDir.value === 'asc' ? '↑' : '↓'

// ── Styles ────────────────────────────────────────────────────────────────────
const SS = {
    pending_admisi: { bg:'rgba(245,166,35,.15)',  color:'#E67E22', dot:'#E67E22' },
    pending_icu:    { bg:'rgba(224,146,58,.15)',  color:'#E0923A', dot:'#E0923A' },
    waiting_list:   { bg:'rgba(217,119,6,.15)',   color:'#D97706', dot:'#D97706' },
    bed_verified:   { bg:'rgba(0,168,132,.15)',   color:'#00A884', dot:'#00A884' },
    ditolak:        { bg:'rgba(231,76,60,.15)',   color:'#E74C3C', dot:'#E74C3C' },
}
const ss     = (s) => SS[s] ?? { bg:'var(--bg-input)', color:'var(--text-secondary)', dot:'#888' }
const gIcon  = (g) => g === 'L' ? '♂' : g === 'P' ? '♀' : '·'
const gColor = (g) => g === 'L' ? '#00A884' : g === 'P' ? '#8E44AD' : 'var(--text-secondary)'

// ── Summary ────────────────────────────────────────────────────────────────────
const CARDS = computed(() => [
    { key:'',             label:'Total',        val: props.summary.total        ?? 0, color:'#5A6B7C', icon:'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' },
    { key:'pending_icu',  label:'Menunggu ICU', val: props.summary.pending_icu  ?? 0, color:'#E0923A', icon:'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
    { key:'waiting_list', label:'Waiting List', val: props.spriList.filter(s=>s.status==='waiting_list').length, color:'#D97706', icon:'M12 8v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z' },
    { key:'bed_verified', label:'Bed Verified', val: props.summary.bed_verified ?? 0, color:'#059669', icon:'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z' },
    { key:'ditolak',      label:'Ditolak',      val: props.summary.ditolak      ?? 0, color:'#E74C3C', icon:'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z' },
])

const statusOptions = [
    { value:'',             label:'Semua Status' },
    { value:'pending_icu',  label:'Menunggu ICU' },
    { value:'waiting_list', label:'Waiting List' },
    { value:'bed_verified', label:'Bed Verified' },
    { value:'ditolak',      label:'Ditolak' },
]

const isSSO          = computed(() => props.authProvider === 'keycloak')
const hasActiveFilter = computed(() => fStatus.value || fNama.value || fJaminan.value)

// ── Pasien bangsal (SSO) ─────────────────────────────────────────────────────
const cariPasien  = ref('')
const pasienList  = ref(props.pasienAktif ?? [])
const cariLoading = ref(false)

watch(cariPasien, (val) => {
    let ct = null; clearTimeout(ct)
    ct = setTimeout(async () => {
        cariLoading.value = true
        try {
            const r = await fetch(route('icu.menu_petugas.pasien_aktif') + '?q=' + encodeURIComponent(val.trim()),
                { headers: { Accept:'application/json', 'X-Requested-With':'XMLHttpRequest' } })
            pasienList.value = await r.json()
        } catch { pasienList.value = [] }
        finally { cariLoading.value = false }
    }, 350)
})

const requestedRegs  = computed(() => props.spriList.map(s => s.No_Reg).filter(Boolean))
const pasienPerRuang = computed(() => {
    const m = {}
    pasienList.value.forEach(p => {
        if (p.No_Reg && requestedRegs.value.includes(p.No_Reg)) return
        const k = p.Nama_RuangM ?? p.Kode_RuangM ?? 'Lainnya'
        if (!m[k]) m[k] = []
        m[k].push(p)
    })
    return m
})

// ── Tabs ──────────────────────────────────────────────────────────────────────
const activeTab    = ref('aktif')
const selectedItem = ref(null)
const showFilter   = ref(false)
const showPasienPanel = ref(false)  // mobile: toggle panel pasien bangsal

const spriRiwayat     = computed(() => props.spriList.filter(s => ['bed_verified','ditolak'].includes(s.status)))
const spriWaitingList = computed(() => props.spriList.filter(s => s.status === 'waiting_list'))
const tabList = computed(() => {
    if (activeTab.value === 'riwayat')      return spriRiwayat.value
    if (activeTab.value === 'waiting_list') return spriWaitingList.value
    return props.spriList
})
const tabCounts = computed(() => ({
    aktif:        props.spriList.length,
    riwayat:      spriRiwayat.value.length,
    waiting_list: spriWaitingList.value.length,
}))
const selectDetail = (item) => {
    selectedItem.value = selectedItem.value?.id === item.id ? null : item
}

// ── Modal & Form BU ──────────────────────────────────────────────────────────
const modal = ref({ open: false, type: '' })
const openModal = (type) => {
    if (type === 'spri' && !skipLookupWatch.value) resetSpri()
    modal.value = { open: true, type }
}
const closeModal = () => { modal.value.open = false; setTimeout(() => { modal.value = { open:false, type:'' } }, 200) }

const lookupLoading     = ref(false)
const lookupResult      = ref(null)
const lookupError       = ref('')
const kunjungans        = ref([])
const diagnosisExisting = ref('')
const jaminanExisting   = ref('')
const skipLookupWatch   = ref(false)
const fmSpri = useForm({ No_MR:'', No_Reg:'', Diagnosis:'', IndikasiRI:'', asal_ruang:'', Dokter:'', spesialis:'', Keterangan:'' })

const resetSpri = () => {
    skipLookupWatch.value = false; fmSpri.reset()
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
    if (!preserveFields) { fmSpri.No_Reg = ''; fmSpri.Dokter = ''; fmSpri.asal_ruang = ''; diagnosisExisting.value = '' }
    lookupLoading.value = true
    try {
        const r = await fetch(route('icu.menu_petugas.lookup') + '?No_MR=' + encodeURIComponent(noMr),
            { headers: { Accept:'application/json', 'X-Requested-With':'XMLHttpRequest' } })
        const d = await r.json()
        lookupResult.value = d
        if (d.found) {
            kunjungans.value = d.kunjungans ?? []
            if (kunjungans.value.length === 1 && !preserveFields) {
                const k = kunjungans.value[0]
                fmSpri.No_Reg = k.No_Reg; fmSpri.Dokter = k.Dokter; fmSpri.asal_ruang = k.asal_ruang
                diagnosisExisting.value = k.Diagnosis; jaminanExisting.value = k.jaminan ?? ''
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
    if (k) { fmSpri.Dokter = k.Dokter; fmSpri.asal_ruang = k.asal_ruang; diagnosisExisting.value = k.Diagnosis; jaminanExisting.value = k.jaminan ?? '' }
}

const pilihPasien = (p) => {
    resetSpri(); skipLookupWatch.value = true
    fmSpri.No_MR = p.No_MR; fmSpri.No_Reg = p.No_Reg ?? ''; fmSpri.asal_ruang = p.Nama_RuangM ?? ''; fmSpri.Dokter = p.Dokter ?? ''
    lookupResult.value = { found:true, No_MR:p.No_MR, nama_pasien:p.Nama_Pasien, jenis_kelamin:p.jenis_kelamin ?? '' }
    if (p.No_Reg) kunjungans.value = [{ No_Reg:p.No_Reg, Dokter:p.Dokter ?? '', asal_ruang:p.Nama_RuangM ?? '', Diagnosis:'' }]
    modal.value = { open:true, type:'spri' }
    showPasienPanel.value = false
    nextTick(() => { skipLookupWatch.value = false; doLookupDiagnosis(p.No_MR, p.No_Reg) })
}
const doLookupDiagnosis = async (noMr, noReg) => {
    try {
        const r = await fetch(route('icu.menu_petugas.lookup') + '?No_MR=' + encodeURIComponent(noMr),
            { headers: { Accept:'application/json', 'X-Requested-With':'XMLHttpRequest' } })
        const d = await r.json()
        if (d.found) {
            lookupResult.value = { ...lookupResult.value, ...d, found:true }
            const ks = d.kunjungans ?? []
            let k = noReg ? ks.find(x => x.No_Reg?.trim() === noReg?.trim()) : null
            if (!k && ks.length > 0) k = ks[0]
            if (k) {
                diagnosisExisting.value = k.Diagnosis ?? ''; jaminanExisting.value = k.jaminan ?? ''
                if (!fmSpri.Dokter && k.Dokter)         fmSpri.Dokter     = k.Dokter
                if (!fmSpri.asal_ruang && k.asal_ruang) fmSpri.asal_ruang = k.asal_ruang
                if (!fmSpri.No_Reg && k.No_Reg)         fmSpri.No_Reg     = k.No_Reg
            }
            if (ks.length > 1) kunjungans.value = ks
        }
    } catch(e) { console.warn('[doLookupDiagnosis]', e) }
}
const submitSpri = () => fmSpri.post(route('icu.menu_petugas.spri.store'), { onSuccess: closeModal })
const canSubmit  = computed(() => fmSpri.No_MR.trim() && fmSpri.No_Reg.trim() && fmSpri.Diagnosis.trim() && fmSpri.IndikasiRI.trim() && lookupResult.value?.found)
</script>

<template>
<AppLayout :flash="flash" page-title="Menu Rawat Inap">
<div class="mp-page">

  <!-- ══ HERO (sama dengan MenuAdmisi) ════════════════════════════════════ -->
  <div class="db-hero">
    <div class="db-hero-copy">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;flex-wrap:wrap">
        <div class="db-hero-logo">
          <img :src="logoUrl" alt="Logo" style="width:36px;height:36px;object-fit:contain" @error="$event.target.style.display='none'"/>
        </div>
        <div style="min-width:0">
          <p style="color:rgba(255,255,255,.6);font-size:11px;font-weight:500">
            ICU Command Center
            <span v-if="isSSO"> · <strong style="color:#fff">{{ wardIds.join(', ') || '-' }}</strong></span>
          </p>
          <h1 style="color:#fff;font-size:clamp(18px,4vw,30px);font-weight:900;letter-spacing:-.02em;line-height:1.1">Booking ICU Internal</h1>
          <p style="color:rgba(255,255,255,.45);font-size:11px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:320px">
            {{ isSSO ? (isIgdUser ? 'Pasien IGD · Klik baris untuk request ICU' : 'Pasien Bangsal · Klik baris untuk request ICU') : 'Request Booking ICU untuk pasien rawat inap' }}
          </p>
        </div>
      </div>
      <!-- Tombol aksi di hero -->
      <div class="flex flex-wrap gap-2 mt-1">
        <button v-if="!isSSO && (canBuatSpriInternal || isAdmin)" @click="openModal('spri')"
          class="flex items-center gap-2 font-bold px-5 py-2.5 rounded-xl transition-all duration-150 hover:-translate-y-px"
          style="background:#fff;color:#00A884;font-size:13px;box-shadow:0 4px 14px rgba(0,0,0,.12)">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
          </svg>
          Booking Baru
        </button>
        <!-- Mobile: toggle panel pasien -->
        <button v-if="isSSO" @click="showPasienPanel = !showPasienPanel"
          class="flex items-center gap-2 font-bold px-4 py-2.5 rounded-xl transition-all duration-150 lg:hidden"
          style="background:rgba(255,255,255,.2);color:#fff;font-size:13px;border:1px solid rgba(255,255,255,.3)">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
          </svg>
          {{ showPasienPanel ? 'Tutup Daftar Pasien' : 'Lihat Pasien Bangsal' }}
        </button>
      </div>
    </div>
    <div class="db-hero-vis" aria-hidden="true">
      <div class="db-char">
        <img :src="doctorImgUrl" alt="Dokter" style="width:100%;height:100%;object-fit:contain"/>
      </div>
    </div>
  </div>

  <!-- ══ KPI CARDS ════════════════════════════════════════════════════════ -->
  <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4">
    <button v-for="c in CARDS" :key="c.key" @click="fStatus = c.key; applyFilters()"
      class="group relative flex items-center gap-3 p-3.5 sm:p-4 rounded-2xl text-left transition-all duration-200 hover:-translate-y-1 hover:shadow-lg"
      style="background:var(--bg-card); border:1px solid var(--border-default); box-shadow:var(--shadow-card); min-height:80px"
      :style="fStatus===c.key ? `border:2.5px solid ${c.color}; box-shadow:0 0 0 3px ${c.color}15` : ''">
      <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 transition-transform group-hover:scale-110"
        :style="`background:${c.color}12`">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" :style="`color:${c.color}`">
          <path stroke-linecap="round" stroke-linejoin="round" :d="c.icon"/>
        </svg>
      </div>
      <div class="min-w-0 flex-1">
        <p class="text-xl sm:text-2xl font-black tracking-tight" :style="`color:${c.color}`" style="font-family:'DM Mono',monospace;line-height:1.1">{{ c.val }}</p>
        <p class="text-xs font-semibold mt-0.5" style="color:var(--text-secondary);line-height:1.2">{{ c.label }}</p>
      </div>
    </button>
  </div>


  <!-- ══ MAIN GRID ══════════════════════════════════════════════════════════ -->
  <div class="mp-grid">

    <!-- ── Panel Pasien Bangsal (SSO) — collapsible mobile, always visible desktop ── -->
    <Transition name="slide-down">
      <div v-if="isSSO && (showPasienPanel || true)" class="mp-left-panel"
        :class="showPasienPanel ? 'mp-left-panel--mobile-open' : 'mp-left-panel--mobile-hidden'">
        <div class="mp-panel-head">
          <div class="flex items-center gap-2">
            <div class="mp-panel-icon">
              <svg class="w-4 h-4" style="color:#00A884" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
              </svg>
            </div>
            <div>
              <p class="text-sm font-bold" style="color:var(--text-primary)">
                {{ isIgdUser ? 'Pasien IGD' : 'Pasien Bangsal' }}
              </p>
              <p class="text-xs" style="color:var(--text-muted)">Klik untuk request ICU</p>
            </div>
          </div>
          <!-- Search pasien -->
          <div class="mp-search-wrap mt-3">
            <svg class="mp-search-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input v-model="cariPasien" placeholder="Cari nama / No. MR..." class="mp-search-input"/>
          </div>
        </div>
        <!-- Pasien list -->
        <div class="mp-panel-body">
          <div v-if="Object.keys(pasienPerRuang).length === 0" class="mp-empty-state">
            <svg class="w-8 h-8" style="color:var(--text-muted)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
            <p class="text-sm font-semibold mt-2" style="color:var(--text-secondary)">Semua pasien sudah diproses</p>
            <p class="text-xs mt-1" style="color:var(--text-muted)">Atau tidak ada data aktif</p>
          </div>
          <template v-for="(pasiens, ruang) in pasienPerRuang" :key="ruang">
            <div v-if="pasiens.length > 0" class="mb-4">
              <div class="mp-ruang-header">
                <span>🏥</span>
                <span class="text-xs font-bold uppercase tracking-wider" style="color:var(--text-accent)">{{ ruang }}</span>
                <span class="mp-ruang-badge">{{ pasiens.length }}</span>
              </div>
              <div class="space-y-1.5">
                <div v-for="p in pasiens" :key="p.No_MR + (p.No_Reg ?? '')"
                  @click="pilihPasien(p)" class="mp-pasien-row group">
                  <div class="mp-av" :style="`background:${gColor(p.jenis_kelamin)}15;color:${gColor(p.jenis_kelamin)}`">{{ gIcon(p.jenis_kelamin) }}</div>
                  <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold truncate" style="color:var(--text-primary)">{{ p.Nama_Pasien }}</p>
                    <p class="text-xs font-mono mt-0.5" style="color:var(--text-muted)">
                      {{ p.No_MR }}
                      <span v-if="p.No_Reg" class="ml-1 px-1.5 py-0.5 rounded" style="color:#00A884;background:rgba(0,168,132,.1)">{{ p.No_Reg }}</span>
                    </p>
                  </div>
                  <div class="hidden sm:block text-right flex-shrink-0">
                    <p class="text-xs font-semibold truncate max-w-24" style="color:var(--text-primary)">{{ p.Dokter || '—' }}</p>
                    <p class="text-xs truncate max-w-24 mt-0.5" style="color:var(--text-muted)">{{ p.Nama_RuangM || '—' }}</p>
                  </div>
                  <div class="opacity-0 group-hover:opacity-100 transition-opacity">
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
    </Transition>

    <!-- Local mode placeholder (no SSO) -->
    <div v-if="!isSSO" class="mp-local-note">
      <svg class="w-8 h-8" style="color:var(--text-muted)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
      <p class="text-sm font-semibold mt-3" style="color:var(--text-secondary)">Mode Login Lokal</p>
      <p class="text-xs mt-1 max-w-48 text-center" style="color:var(--text-muted)">Gunakan tombol "Booking Baru" di header untuk mencari pasien.</p>
    </div>


    <!-- ── Panel Kanan: Tabs + Filter + List + Detail ── -->
    <div class="mp-right-panel">

      <!-- Tab bar -->
      <div class="mp-tab-bar">
        <div class="mp-tabs">
          <button v-for="tab in [{key:'aktif',label:'Aktif'},{key:'riwayat',label:'Riwayat'},{key:'waiting_list',label:'Waiting List'}]"
            :key="tab.key" class="mp-tab" :class="activeTab===tab.key?'mp-tab--active':''"
            @click="activeTab=tab.key; selectedItem=null">
            {{ tab.label }}
            <span class="mp-tab-count" :class="activeTab===tab.key?'mp-tab-count--active':''">{{ tabCounts[tab.key] }}</span>
          </button>
        </div>
        <button class="mp-filter-btn" :class="showFilter?'mp-filter-btn--on':''" @click="showFilter=!showFilter">
          <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
          </svg>
          Filter
          <span v-if="hasActiveFilter" class="mp-filter-dot"></span>
        </button>
      </div>

      <!-- Search -->
      <div class="px-4 pt-3 pb-1">
        <div class="mp-search-wrap">
          <svg class="mp-search-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
          <input v-model="fNama" @input="onNamaInput" placeholder="Cari nama pasien atau No. MR..." class="mp-search-input"/>
        </div>
      </div>

      <!-- Filter panel collapsible -->
      <Transition name="slide-down">
        <div v-if="showFilter" class="mp-filter-panel">
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3">
            <div>
              <label class="mp-label">Status</label>
              <select v-model="fStatus" @change="applyFilters" class="mp-select">
                <option v-for="o in statusOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
              </select>
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
          <div class="flex flex-wrap items-center gap-2">
            <div class="mp-preset-group">
              <button v-for="p in [{l:'Hari ini',d:today,s:today},{l:'Kemarin',d:yesterday,s:yesterday},{l:'7 Hari',d:week7,s:today}]"
                :key="p.l" @click="setPreset(p.d,p.s)" class="mp-preset-btn"
                :class="fTglDari===p.d&&fTglAkh===p.s?'mp-preset-btn--on':''">{{ p.l }}</button>
            </div>
            <div class="flex gap-2 ml-auto">
              <button v-for="col in [{key:'created_at',label:'Waktu'},{key:'status',label:'Status'}]" :key="col.key"
                @click="toggleSort(col.key)" class="mp-sort-btn" :class="sortBy===col.key?'mp-sort-btn--on':''">
                {{ col.label }} {{ sortIcon(col.key) }}
              </button>
              <button v-if="hasActiveFilter" @click="resetFilter" class="mp-reset-btn">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>Reset
              </button>
            </div>
          </div>
        </div>
      </Transition>

      <!-- Content: list + detail side by side -->
      <div class="mp-content">

        <!-- List -->
        <div class="mp-list" :class="selectedItem ? 'mp-list--narrow' : ''">

          <!-- Table header (desktop) -->
          <div class="mp-list-header hidden sm:grid">
            <span>PASIEN</span>
            <span>DIAGNOSA</span>
            <span>JAMINAN</span>
            <span>BED / RUANG</span>
            <span>STATUS</span>
            <span>WAKTU</span>
            <span></span>
          </div>

          <!-- Empty state -->
          <div v-if="!tabList.length" class="mp-empty-state" style="padding:56px 24px">
            <svg class="w-10 h-10 mb-3" style="color:var(--text-muted)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p class="text-sm font-semibold" style="color:var(--text-secondary)">Belum ada data</p>
            <p class="text-xs mt-1" style="color:var(--text-muted)">
              {{ activeTab==='aktif' ? (isSSO ? 'Pilih pasien dari panel kiri' : 'Klik Booking Baru di header') : 'Tidak ada data untuk tab ini' }}
            </p>
          </div>

          <!-- Row desktop -->
          <div v-for="item in tabList" :key="`row-${item.id}`"
            class="mp-row hidden sm:grid"
            :class="selectedItem?.id===item.id ? 'mp-row--active' : ''"
            :style="`border-left:3px solid ${ss(item.status).dot}`"
            @click="selectDetail(item)">
            <div class="min-w-0">
              <div class="flex items-center gap-2">
                <div class="mp-av-sm" :style="`background:${gColor(item.jenis_kelamin)}15;color:${gColor(item.jenis_kelamin)}`">{{ gIcon(item.jenis_kelamin) }}</div>
                <div class="min-w-0">
                  <p class="text-sm font-semibold truncate" style="color:var(--text-primary)">{{ item.nama_pasien }}</p>
                  <p class="text-xs font-mono" style="color:var(--text-muted)">{{ item.No_MR }}</p>
                </div>
              </div>
            </div>
            <div class="min-w-0">
              <p class="text-xs truncate" style="color:var(--text-primary)">{{ item.Diagnosis ?? '—' }}</p>
              <p class="text-xs truncate mt-0.5" style="color:var(--text-muted)">{{ item.asal_ruang ?? '' }}</p>
            </div>
            <div>
              <span v-if="item.jaminan_nama || item.jaminan" class="text-xs font-medium px-2 py-0.5 rounded-lg"
                style="background:rgba(0,168,132,.1);color:#00A884">{{ item.jaminan_nama || item.jaminan }}</span>
              <span v-else class="text-xs" style="color:var(--text-muted)">—</span>
            </div>
            <div class="min-w-0">
              <p v-if="item.nama_bed" class="text-xs font-semibold flex items-center gap-1" style="color:#00A884">
                <svg class="w-3 h-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                <span class="truncate">{{ item.nama_bed }}</span>
              </p>
              <span v-else-if="item.kebutuhan_bed" class="text-xs font-semibold px-2 py-0.5 rounded-lg" style="background:rgba(0,168,132,.1);color:#00A884">{{ item.kebutuhan_bed }}</span>
              <span v-else class="text-xs" style="color:var(--text-muted)">—</span>
            </div>
            <div>
              <span class="mp-pill" :style="`background:${ss(item.status).bg};color:${ss(item.status).color}`">
                <span class="mp-pill-dot" :style="`background:${ss(item.status).dot}`"></span>
                {{ item.status_label }}
              </span>
              <p v-if="item.status==='waiting_list' && item.waiting_estimasi_fmt" class="text-xs mt-0.5 font-mono" style="color:#D97706">
                ⏰ {{ item.waiting_estimasi_fmt }}
              </p>
            </div>
            <div>
              <p class="text-xs font-mono" style="color:var(--text-muted)">{{ item.created_at_fmt }}</p>
            </div>
            <div class="flex justify-end">
              <svg class="w-4 h-4 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color:var(--text-muted)">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
              </svg>
            </div>
          </div>

          <!-- Row mobile card -->
          <div v-for="item in tabList" :key="`mob-${item.id}`"
            class="mp-mobile-card sm:hidden"
            :class="selectedItem?.id===item.id ? 'mp-row--active' : ''"
            :style="`border-left:3px solid ${ss(item.status).dot}`"
            @click="selectDetail(item)">
            <div class="flex justify-between items-start mb-2 gap-2">
              <div class="flex flex-wrap gap-1.5">
                <span class="mp-pill" :style="`background:${ss(item.status).bg};color:${ss(item.status).color}`">
                  <span class="mp-pill-dot" :style="`background:${ss(item.status).dot}`"></span>
                  {{ item.status_label }}
                </span>
              </div>
              <span class="text-xs font-mono flex-shrink-0" style="color:var(--text-muted)">{{ item.created_at_fmt?.split(' ')[0] }}</span>
            </div>
            <!-- Waiting list info di mobile -->
            <div v-if="item.status==='waiting_list' && item.waiting_estimasi_fmt"
              class="mb-2 rounded-lg px-3 py-2 flex items-center gap-2" style="background:#FFFBEB;border:1px solid #FCD34D">
              <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="#D97706" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              <div>
                <p class="text-xs font-semibold" style="color:#92400E">{{ item.waiting_alasan }}</p>
                <p class="text-xs font-bold font-mono" style="color:#D97706">Est. {{ item.waiting_estimasi_fmt }}</p>
              </div>
            </div>
            <div class="flex items-center gap-2.5 mb-2">
              <div class="mp-av" :style="`background:${gColor(item.jenis_kelamin)}15;color:${gColor(item.jenis_kelamin)}`">{{ gIcon(item.jenis_kelamin) }}</div>
              <div class="min-w-0 flex-1">
                <p class="text-sm font-bold truncate" style="color:var(--text-primary)">{{ item.nama_pasien }}</p>
                <p class="text-xs font-mono" style="color:var(--text-muted)">{{ item.No_MR }}</p>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-2 text-xs">
              <div>
                <p style="color:var(--text-muted)">Diagnosa</p>
                <p class="font-semibold truncate" style="color:var(--text-secondary)">{{ item.Diagnosis ?? '—' }}</p>
              </div>
              <div>
                <p style="color:var(--text-muted)">Jaminan</p>
                <p class="font-semibold truncate" style="color:var(--text-secondary)">{{ item.jaminan_nama || item.jaminan || '—' }}</p>
              </div>
              <div class="col-span-2">
                <p style="color:var(--text-muted)">Bed / Ruang</p>
                <p class="font-semibold truncate" :style="item.nama_bed ? 'color:#00A884' : 'color:var(--text-secondary)'">
                  {{ item.nama_bed ?? (item.kebutuhan_bed || item.asal_ruang || '—') }}
                </p>
              </div>
            </div>
          </div>

        </div><!-- /mp-list -->

        <!-- Detail panel (slide-in) -->
        <Transition name="slide-left">
          <div v-if="selectedItem" class="mp-detail">
            <div class="mp-detail-head">
              <span class="text-xs font-bold uppercase tracking-wider" style="color:var(--text-muted)">Detail Booking</span>
              <button class="mp-detail-close" @click="selectedItem=null">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>
            <!-- Patient header -->
            <div class="flex items-start gap-3 p-4">
              <div class="mp-av" :style="`background:${gColor(selectedItem.jenis_kelamin)}15;color:${gColor(selectedItem.jenis_kelamin)};width:42px;height:42px;border-radius:12px;font-size:16px`">
                {{ gIcon(selectedItem.jenis_kelamin) }}
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-bold truncate" style="color:var(--text-primary)">{{ selectedItem.nama_pasien }}</p>
                <p class="text-xs font-mono mt-0.5" style="color:var(--text-muted)">{{ selectedItem.No_MR }}</p>
                <span class="mp-pill mt-1 inline-flex" :style="`background:${ss(selectedItem.status).bg};color:${ss(selectedItem.status).color}`">
                  <span class="mp-pill-dot" :style="`background:${ss(selectedItem.status).dot}`"></span>
                  {{ selectedItem.status_label }}
                </span>
              </div>
            </div>
            <div class="mp-divider"></div>
            <!-- Klinis -->
            <div class="p-4 space-y-3">
              <div>
                <p class="mp-detail-label">Diagnosa</p>
                <p class="mp-detail-val">{{ selectedItem.Diagnosis ?? '—' }}</p>
                <p v-if="selectedItem.IndikasiRI" class="mp-detail-sub">{{ selectedItem.IndikasiRI }}</p>
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <p class="mp-detail-label">DPJP</p>
                  <p class="mp-detail-val">{{ selectedItem.Dokter ?? '—' }}</p>
                </div>
                <div>
                  <p class="mp-detail-label">Asal Ruang</p>
                  <p class="mp-detail-val">{{ selectedItem.asal_ruang ?? '—' }}</p>
                </div>
                <div>
                  <p class="mp-detail-label">Jaminan</p>
                  <p class="mp-detail-val">{{ selectedItem.jaminan_nama || selectedItem.jaminan || '—' }}</p>
                </div>
                <div>
                  <p class="mp-detail-label">Waktu</p>
                  <p class="mp-detail-val font-mono text-xs">{{ selectedItem.created_at_fmt }}</p>
                </div>
              </div>
            </div>

            <!-- Timeline aksi -->
            <template v-if="selectedItem.approved_at_fmt || selectedItem.approved_by || selectedItem.verified_at_fmt || selectedItem.verified_by">
              <div class="mp-divider"></div>
              <div class="p-4">
                <p class="mp-detail-label mb-2">Timeline Proses</p>
                <div class="space-y-2.5">
                  <!-- Booking dibuat -->
                  <div class="flex items-start gap-2">
                    <div class="w-1.5 h-1.5 rounded-full mt-1.5 flex-shrink-0" style="background:#00A884"></div>
                    <div>
                      <p class="text-xs font-semibold" style="color:var(--text-primary)">Booking dibuat</p>
                      <p class="text-xs font-mono" style="color:var(--text-muted)">{{ selectedItem.created_at_fmt ?? '—' }}</p>
                      <p v-if="selectedItem.created_by" class="text-xs" style="color:var(--text-muted)">oleh {{ selectedItem.created_by }}</p>
                    </div>
                  </div>
                  <!-- Approve Admisi -->
                  <div v-if="selectedItem.approved_at_fmt || selectedItem.approved_by" class="flex items-start gap-2">
                    <div class="w-1.5 h-1.5 rounded-full mt-1.5 flex-shrink-0" style="background:#0EA5E9"></div>
                    <div>
                      <p class="text-xs font-semibold" style="color:var(--text-primary)">Disetujui Admisi</p>
                      <p class="text-xs font-mono" style="color:var(--text-muted)">{{ selectedItem.approved_at_fmt ?? '—' }}</p>
                      <p v-if="selectedItem.approved_by" class="text-xs" style="color:var(--text-muted)">oleh {{ selectedItem.approved_by }}</p>
                    </div>
                  </div>
                  <!-- Verifikasi bed ICU -->
                  <div v-if="selectedItem.verified_at_fmt || selectedItem.verified_by" class="flex items-start gap-2">
                    <div class="w-1.5 h-1.5 rounded-full mt-1.5 flex-shrink-0" style="background:#059669"></div>
                    <div>
                      <p class="text-xs font-semibold" style="color:var(--text-primary)">Bed terverifikasi ICU</p>
                      <p class="text-xs font-mono" style="color:var(--text-muted)">{{ selectedItem.verified_at_fmt ?? '—' }}</p>
                      <p v-if="selectedItem.verified_by" class="text-xs" style="color:var(--text-muted)">oleh {{ selectedItem.verified_by }}</p>
                    </div>
                  </div>
                </div>
              </div>
            </template>
            <!-- Bed -->
            <template v-if="selectedItem.nama_bed || selectedItem.kebutuhan_bed">
              <div class="mp-divider"></div>
              <div class="p-4">
                <p class="mp-detail-label">Bed ICU</p>
                <p class="mp-detail-val" style="color:#00A884;font-weight:700">{{ selectedItem.nama_bed ?? selectedItem.kebutuhan_bed }}</p>
              </div>
            </template>
            <!-- Waiting list -->
            <template v-if="selectedItem.status==='waiting_list' && selectedItem.waiting_alasan">
              <div class="mp-divider"></div>
              <div class="p-4">
                <div class="rounded-xl overflow-hidden" style="border:1.5px solid #FCD34D">
                  <div class="flex items-center gap-2 px-3 py-2.5" style="background:#FEF3C7">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="#D97706" stroke-width="2.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span class="text-xs font-black" style="color:#D97706">Waiting List ICU</span>
                  </div>
                  <div class="px-3 py-2.5 space-y-1.5" style="background:#FFFBEB">
                    <p class="text-xs" style="color:#78350F">{{ selectedItem.waiting_alasan }}</p>
                    <p v-if="selectedItem.waiting_estimasi_fmt" class="text-sm font-black font-mono" style="color:#D97706">Est. {{ selectedItem.waiting_estimasi_fmt }}</p>
                    <p v-if="selectedItem.waiting_by" class="text-xs" style="color:#A16207">Diproses: {{ selectedItem.waiting_by }}</p>
                  </div>
                </div>
              </div>
            </template>
            <!-- Tolak -->
            <template v-if="selectedItem.alasan_tolak">
              <div class="mp-divider"></div>
              <div class="p-4">
                <p class="mp-detail-label">Alasan Ditolak</p>
                <p class="text-xs font-semibold" style="color:#E74C3C">{{ selectedItem.alasan_tolak }}</p>
              </div>
            </template>
            <!-- Catatan admisi -->
            <template v-if="selectedItem.catatan_admisi">
              <div class="mp-divider"></div>
              <div class="p-4">
                <p class="mp-detail-label">Catatan Admisi</p>
                <p class="text-xs" style="color:var(--text-secondary)">{{ selectedItem.catatan_admisi }}</p>
              </div>
            </template>
          </div>
        </Transition>

      </div><!-- /mp-content -->

      <!-- Footer row count -->
      <div class="px-4 py-3 border-t text-xs" style="border-color:var(--border-default);color:var(--text-secondary)">
        Menampilkan <strong style="color:var(--text-primary)">{{ tabList.length }}</strong> data
      </div>

    </div><!-- /mp-right-panel -->

  </div><!-- /mp-grid -->
</div>

<!-- ══ MODAL BOOKING BU ══════════════════════════════════════════════════ -->
<Transition enter-active-class="transition-all duration-200" enter-from-class="opacity-0" leave-to-class="opacity-0">
  <div v-if="modal.open" class="mp-modal-overlay" @click.self="closeModal">
    <Transition enter-active-class="transition-all duration-200" enter-from-class="opacity-0 scale-95" leave-to-class="opacity-0 scale-95">
      <div v-if="modal.type==='spri'" class="mp-modal">
        <!-- Header -->
        <div class="flex items-center gap-3 px-5 py-4 rounded-t-2xl" style="background:#00A884">
          <svg class="w-5 h-5 text-white flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
          <p class="text-sm font-bold text-white flex-1">Booking ICU — Pasien Internal</p>
          <button @click="closeModal" class="w-7 h-7 rounded-lg flex items-center justify-center transition-all hover:bg-white/20" style="color:rgba(255,255,255,.8)">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
        <!-- Sub-header -->
        <div class="flex items-center justify-between px-5 py-2.5 border-b" style="border-color:var(--border-default);background:var(--bg-surface)">
          <p class="text-xs" style="color:var(--text-secondary)">Isi data klinis dan kirim langsung ke ICU</p>
        </div>

        <!-- Form -->
        <form @submit.prevent="submitSpri" class="p-5 space-y-5 overflow-y-auto" style="max-height:calc(92vh - 120px)">

          <!-- Patient banner (SSO) -->
          <div v-if="isSSO && lookupResult?.found" class="flex items-center gap-3 px-4 py-3 rounded-xl" style="background:rgba(0,168,132,.07);border:1px solid rgba(0,168,132,.2)">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 text-sm font-bold"
              :style="`background:${gColor(lookupResult.jenis_kelamin||'')}15;color:${gColor(lookupResult.jenis_kelamin||'')}`">
              {{ gIcon(lookupResult.jenis_kelamin||'') }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="font-bold text-sm" style="color:#00A884">{{ lookupResult.nama_pasien }}</p>
              <p class="text-xs font-mono mt-0.5" style="color:var(--text-secondary)">{{ fmSpri.No_MR }} · {{ fmSpri.No_Reg }} · {{ fmSpri.asal_ruang || '—' }}</p>
            </div>
            <span class="text-xs font-bold px-2 py-0.5 rounded-full flex-shrink-0" style="background:rgba(0,168,132,.15);color:#00A884">SSO ✓</span>
          </div>

          <!-- Step 1: Verifikasi -->
          <div class="mp-form-step">
            <div class="flex items-center gap-2 mb-3">
              <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold text-white flex-shrink-0" style="background:#00A884">1</span>
              <p class="text-xs font-bold uppercase tracking-wider" style="color:#00A884">Verifikasi Pasien</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="mp-label">No. Medical Record <span style="color:#E74C3C">*</span></label>
                <input v-if="isSSO" :value="fmSpri.No_MR" readonly class="mp-input mp-input--verified"/>
                <input v-else v-model="fmSpri.No_MR" required placeholder="Ketik No. MR..." class="mp-input"
                  :style="lookupResult?.found ? 'border-color:#00A884' : (lookupError ? 'border-color:#E74C3C' : '')"/>
                <p v-if="!isSSO && lookupResult?.found" class="text-xs mt-1 font-semibold" style="color:#00A884">✓ {{ lookupResult.nama_pasien }}</p>
                <p v-if="lookupError" class="text-xs mt-1" style="color:#E74C3C">{{ lookupError }}</p>
              </div>
              <div>
                <label class="mp-label">No. Registrasi <span style="color:#E74C3C">*</span></label>
                <input v-if="isSSO && fmSpri.No_Reg" :value="fmSpri.No_Reg" readonly class="mp-input mp-input--verified"/>
                <select v-else-if="!isSSO && kunjungans.length > 1" v-model="fmSpri.No_Reg" @change="onKunjunganChange(fmSpri.No_Reg)" class="mp-select">
                  <option value="" disabled>-- Pilih Kunjungan --</option>
                  <option v-for="k in kunjungans" :key="k.No_Reg" :value="k.No_Reg">{{ k.No_Reg }}{{ k.asal_ruang ? ' — '+k.asal_ruang : '' }}</option>
                </select>
                <input v-else :value="fmSpri.No_Reg" readonly class="mp-input mp-input--muted" :placeholder="!lookupResult?.found ? 'Isi No. MR dulu' : 'Tidak ada kunjungan'"/>
              </div>
            </div>
          </div>

          <!-- Step 2: Data RM (readonly) -->
          <div class="mp-form-step">
            <div class="flex items-center gap-2 mb-3">
              <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold text-white flex-shrink-0" style="background:#5A6B7C">2</span>
              <p class="text-xs font-bold uppercase tracking-wider" style="color:#5A6B7C">Data Rekam Medis</p>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mp-label">Diagnosis (RM)</label>
                <input :value="diagnosisExisting || '—'" readonly class="mp-input mp-input--muted" :class="diagnosisExisting ? 'mp-input--verified' : ''"/>
              </div>
              <div>
                <label class="mp-label">Asal Ruang</label>
                <input :value="fmSpri.asal_ruang || '—'" readonly class="mp-input mp-input--muted" :class="fmSpri.asal_ruang ? 'mp-input--verified' : ''"/>
              </div>
              <div>
                <label class="mp-label">Dokter DPJP</label>
                <input :value="fmSpri.Dokter || '—'" readonly class="mp-input mp-input--muted" :class="fmSpri.Dokter ? 'mp-input--verified' : ''"/>
              </div>
              <div>
                <label class="mp-label">Jaminan</label>
                <input :value="jaminanExisting || '—'" readonly class="mp-input mp-input--muted" :class="jaminanExisting ? 'mp-input--verified' : ''"/>
              </div>
            </div>
          </div>

          <!-- Step 3: Klinis untuk ICU -->
          <div class="mp-form-step">
            <div class="flex items-center gap-2 mb-3">
              <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold text-white flex-shrink-0" style="background:#00A884">3</span>
              <p class="text-xs font-bold uppercase tracking-wider" style="color:#00A884">Data Klinis untuk ICU</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="mp-label">Diagnosis ICU <span style="color:#E74C3C">*</span></label>
                <Icd10Search v-model="fmSpri.Diagnosis" placeholder="Cari kode / keterangan ICD-10" :required="true" :has-error="!!fmSpri.errors.Diagnosis"/>
                <p v-if="fmSpri.errors.Diagnosis" class="text-xs mt-1" style="color:#E74C3C">{{ fmSpri.errors.Diagnosis }}</p>
              </div>
              <div>
                <label class="mp-label">Indikasi Rawat ICU <span style="color:#E74C3C">*</span></label>
                <input v-model="fmSpri.IndikasiRI" required placeholder="Alasan klinis butuh ICU" class="mp-input"
                  :style="fmSpri.errors.IndikasiRI ? 'border-color:#E74C3C' : ''"/>
              </div>
              <div class="sm:col-span-2">
                <label class="mp-label">Keterangan Tambahan</label>
                <textarea v-model="fmSpri.Keterangan" rows="2" placeholder="Kondisi terkini, catatan penting..." class="mp-textarea"></textarea>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex justify-end gap-3 pt-2 border-t" style="border-color:var(--border-default)">
            <button type="button" @click="closeModal" class="px-4 py-2.5 rounded-xl text-sm font-semibold cursor-pointer" style="background:var(--bg-input);color:var(--text-secondary);border:1px solid var(--border-default)">
              Batal
            </button>
            <button type="submit" :disabled="!canSubmit || fmSpri.processing"
              class="px-5 py-2.5 rounded-xl text-sm font-bold text-white cursor-pointer transition-all disabled:opacity-40 disabled:cursor-not-allowed hover:-translate-y-px"
              style="background:#00A884;box-shadow:0 4px 14px rgba(0,168,132,.3)">
              {{ fmSpri.processing ? 'Menyimpan…' : 'Kirim Booking ICU' }}
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
/* ── Page wrapper ─────────────────────────────────────────────────────────── */
.mp-page { padding:20px 16px 32px; background:var(--bg-main); min-height:100%; font-family:'Inter','Plus Jakarta Sans',sans-serif; display:flex; flex-direction:column; gap:20px; }
@media(min-width:640px) { .mp-page { padding:24px 24px 40px; } }

/* ── Hero (shared with MenuAdmisi) ────────────────────────────────────────── */
.db-hero { background:#00A884; border-radius:16px; padding:22px 28px 18px; position:relative; overflow:hidden;
  border:1px solid rgba(255,255,255,.1); box-shadow:0 12px 32px rgba(0,168,132,.15);
  display:grid; grid-template-columns:1fr; gap:18px; align-items:center; }
@media(min-width:860px) { .db-hero { grid-template-columns:1fr auto; } }
.db-hero::before { content:''; position:absolute; width:260px; height:260px; border-radius:50%; right:-80px; top:-100px;
  background:radial-gradient(circle,rgba(255,255,255,.1),transparent); pointer-events:none; }
.db-hero-copy { position:relative; z-index:2; }
.db-hero-logo { width:44px; height:44px; border-radius:13px; background:rgba(255,255,255,.18);
  border:1px solid rgba(255,255,255,.22); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.db-hero-vis { position:relative; min-height:140px; min-width:200px; align-self:center; display:none; }
@media(min-width:860px) { .db-hero-vis { display:block; } }
.db-char { position:absolute; right:0; bottom:-16px; width:min(200px,100%); aspect-ratio:1; }

/* ── Main grid ────────────────────────────────────────────────────────────── */
.mp-grid { display:grid; grid-template-columns:1fr; gap:16px; }
@media(min-width:1024px) { .mp-grid { grid-template-columns:500px 1fr; align-items:start; } }

/* ── Left panel (pasien bangsal) ─────────────────────────────────────────── */
.mp-left-panel { background:var(--bg-card); border:1px solid var(--border-default); border-radius:14px;
  box-shadow:var(--shadow-card); overflow:hidden; display:flex; flex-direction:column; height:580px; }
/* Mobile: hidden by default, toggle via class */
@media(max-width:1023px) {
  .mp-left-panel--mobile-hidden { display:none; }
  .mp-left-panel--mobile-open   { display:flex; height:400px; }
}
.mp-panel-head { padding:14px; border-bottom:1px solid var(--border-default); background:var(--bg-surface); flex-shrink:0; }
.mp-panel-icon { width:32px; height:32px; border-radius:9px; background:rgba(0,168,132,.1);
  display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.mp-panel-body { flex:1; overflow-y:auto; padding:10px; }

/* ── Local note ─────────────────────────────────────────────────────────── */
.mp-local-note { display:flex; flex-direction:column; align-items:center; justify-content:center;
  padding:40px 24px; background:var(--bg-card); border:1px solid var(--border-default);
  border-radius:14px; text-align:center; }
@media(min-width:1024px) { .mp-local-note { height:200px; } }

/* ── Right panel ─────────────────────────────────────────────────────────── */
.mp-right-panel { background:var(--bg-card); border:1px solid var(--border-default); border-radius:14px;
  box-shadow:var(--shadow-card); overflow:hidden; display:flex; flex-direction:column; }

/* ── Tabs ────────────────────────────────────────────────────────────────── */
.mp-tab-bar { display:flex; align-items:center; justify-content:space-between;
  padding:0 14px; border-bottom:1px solid var(--border-default); background:var(--bg-surface); flex-shrink:0; }
.mp-tabs { display:flex; }
.mp-tab { padding:11px 12px; font-size:12px; font-weight:500; color:var(--text-secondary);
  border:none; background:transparent; cursor:pointer; border-bottom:2.5px solid transparent;
  margin-bottom:-1px; display:flex; align-items:center; gap:5px; transition:all .15s; white-space:nowrap; }
.mp-tab:hover:not(.mp-tab--active) { color:var(--text-primary); }
.mp-tab--active { color:#00A884; border-bottom-color:#00A884; font-weight:700; }
.mp-tab-count { font-size:10px; font-weight:700; padding:1px 6px; border-radius:20px; background:var(--bg-input); color:var(--text-secondary); }
.mp-tab-count--active { background:rgba(0,168,132,.12); color:#00A884; }
.mp-filter-btn { display:flex; align-items:center; gap:5px; padding:6px 11px; border-radius:8px;
  border:1.5px solid var(--border-default); background:var(--bg-input); cursor:pointer;
  font-size:11px; font-weight:600; color:var(--text-secondary); transition:all .15s; position:relative; white-space:nowrap; }
.mp-filter-btn:hover, .mp-filter-btn--on { border-color:#00A884; color:#00A884; background:rgba(0,168,132,.07); }
.mp-filter-dot { position:absolute; top:4px; right:4px; width:6px; height:6px;
  border-radius:50%; background:#E74C3C; border:1.5px solid white; }

/* ── Filter panel ────────────────────────────────────────────────────────── */
.mp-filter-panel { padding:12px 14px; border-bottom:1px solid var(--border-default); background:rgba(0,168,132,.02); flex-shrink:0; }
.mp-preset-group { display:flex; gap:2px; padding:3px; border-radius:9px; background:var(--bg-input); }
.mp-preset-btn { padding:4px 10px; border-radius:7px; border:none; font-size:11px; font-weight:600;
  cursor:pointer; background:transparent; color:var(--text-muted); transition:all .15s; }
.mp-preset-btn--on { background:var(--bg-card); color:#00A884; box-shadow:0 1px 4px rgba(0,0,0,.08); }
.mp-sort-btn { font-size:11px; font-weight:600; padding:5px 9px; border-radius:8px;
  border:1.5px solid var(--border-default); cursor:pointer; background:var(--bg-input); color:var(--text-secondary); }
.mp-sort-btn--on { background:rgba(0,168,132,.1); color:#00A884; border-color:rgba(0,168,132,.3); }
.mp-reset-btn { display:flex; align-items:center; gap:4px; padding:5px 9px; border-radius:8px;
  background:#FEF2F2; color:#DC2626; border:1.5px solid rgba(220,38,38,.15); cursor:pointer; font-size:11px; font-weight:700; }

/* ── Content (list + detail) ─────────────────────────────────────────────── */
.mp-content { display:flex; flex:1; overflow:hidden; min-height:400px; }
.mp-list { flex:1; overflow-y:auto; display:flex; flex-direction:column; min-width:0; transition:flex .2s; }
.mp-list--narrow { flex:0 0 55%; }

/* ── Table header ────────────────────────────────────────────────────────── */
.mp-list-header { grid-template-columns:1.2fr 1fr 0.7fr 0.8fr 0.9fr 0.8fr 28px;
  padding:9px 14px; font-size:10px; font-weight:700; color:var(--text-muted);
  text-transform:uppercase; letter-spacing:.06em; border-bottom:1px solid var(--border-default);
  background:var(--bg-surface); flex-shrink:0; gap:8px; }

/* ── Row desktop ─────────────────────────────────────────────────────────── */
.mp-row { grid-template-columns:1.2fr 1fr 0.7fr 0.8fr 0.9fr 0.8fr 28px;
  padding:10px 14px; border-bottom:1px solid var(--border-default); cursor:pointer;
  align-items:center; transition:background .12s; gap:8px; }
.mp-row:hover { background:var(--bg-row-hover); }
.mp-row--active { background:rgba(0,168,132,.04) !important; }

/* ── Row mobile card ─────────────────────────────────────────────────────── */
.mp-mobile-card { padding:12px 14px; border-bottom:1px solid var(--border-default); cursor:pointer; transition:background .12s; }
.mp-mobile-card:hover { background:var(--bg-row-hover); }
.mp-mobile-card.mp-row--active { background:rgba(0,168,132,.04) !important; }

/* ── Pill ────────────────────────────────────────────────────────────────── */
.mp-pill { display:inline-flex; align-items:center; gap:4px; font-size:10px; font-weight:700; padding:3px 8px; border-radius:20px; white-space:nowrap; }
.mp-pill-dot { width:5px; height:5px; border-radius:50%; flex-shrink:0; }

/* ── Avatar ──────────────────────────────────────────────────────────────── */
.mp-av { width:36px; height:36px; border-radius:10px; display:flex; align-items:center;
  justify-content:center; font-size:14px; font-weight:700; flex-shrink:0; }
.mp-av-sm { width:30px; height:30px; border-radius:8px; display:flex; align-items:center;
  justify-content:center; font-size:12px; font-weight:700; flex-shrink:0; }

/* ── Detail panel ────────────────────────────────────────────────────────── */
.mp-detail { width:45%; border-left:1px solid var(--border-default); background:var(--bg-surface);
  overflow-y:auto; flex-shrink:0; display:flex; flex-direction:column; }
.mp-detail-head { display:flex; align-items:center; justify-content:space-between;
  padding:10px 14px; border-bottom:1px solid var(--border-default); flex-shrink:0; }
.mp-detail-close { background:var(--bg-input); border:1px solid var(--border-default);
  border-radius:7px; color:var(--text-secondary); cursor:pointer; width:26px; height:26px;
  display:flex; align-items:center; justify-content:center; }
.mp-detail-label { font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--text-muted); margin-bottom:2px; }
.mp-detail-val { font-size:13px; font-weight:600; color:var(--text-primary); }
.mp-detail-sub { font-size:11px; color:var(--text-secondary); margin-top:1px; }
.mp-divider { height:1px; background:var(--border-default); flex-shrink:0; }

/* ── Pasien bangsal list ─────────────────────────────────────────────────── */
.mp-ruang-header { display:flex; align-items:center; gap:6px; padding:5px 10px; border-radius:8px;
  background:var(--bg-card); border:1px solid var(--border-default);
  position:sticky; top:0; z-index:5; margin-bottom:6px; }
.mp-ruang-badge { font-size:9px; font-weight:700; padding:2px 6px; border-radius:20px;
  background:rgba(0,168,132,.1); color:#00A884; }
.mp-pasien-row { display:flex; align-items:center; gap:9px; padding:9px 10px; border-radius:10px;
  cursor:pointer; border:1px solid var(--border-default); background:var(--bg-surface);
  transition:all .15s; margin-bottom:4px; }
.mp-pasien-row:hover { border-color:rgba(0,168,132,.3); box-shadow:0 3px 10px rgba(0,0,0,.05); transform:translateY(-1px); }
.mp-arrow-btn { width:24px; height:24px; border-radius:7px; background:#00A884;
  display:flex; align-items:center; justify-content:center; }

/* ── Search input ────────────────────────────────────────────────────────── */
.mp-search-wrap { position:relative; }
.mp-search-icon { position:absolute; left:10px; top:50%; transform:translateY(-50%); width:15px; height:15px; color:var(--text-muted); }
.mp-search-input { width:100%; padding:9px 12px 9px 34px; border:1.5px solid var(--border-default);
  border-radius:10px; font-size:13px; color:var(--text-primary); background:var(--bg-input); outline:none; }
.mp-search-input:focus { border-color:#00A884; box-shadow:0 0 0 3px rgba(0,168,132,.1); }

/* ── Form elements ───────────────────────────────────────────────────────── */
.mp-label { display:block; font-size:10px; font-weight:700; color:var(--text-muted); margin-bottom:5px; text-transform:uppercase; letter-spacing:.05em; }
.mp-input { width:100%; padding:9px 12px; border:1.5px solid var(--border-default); border-radius:11px;
  font-size:13px; color:var(--text-primary); background:var(--bg-input); outline:none; transition:border-color .2s; }
.mp-input:focus { border-color:#00A884; box-shadow:0 0 0 3px rgba(0,168,132,.1); }
.mp-input--verified { border-color:rgba(0,168,132,.4) !important; background:rgba(0,168,132,.04) !important; }
.mp-input--muted { opacity:.65; cursor:not-allowed; font-family:'DM Mono',monospace; }
.mp-select { width:100%; padding:9px 12px; border:1.5px solid var(--border-default); border-radius:11px;
  font-size:13px; color:var(--text-primary); background:var(--bg-input); outline:none; }
.mp-textarea { width:100%; padding:9px 12px; border:1.5px solid var(--border-default); border-radius:11px;
  font-size:13px; color:var(--text-primary); background:var(--bg-input); outline:none; resize:none; }
.mp-form-step { background:var(--bg-surface); border:1px solid var(--border-default); border-radius:14px; padding:16px; }

/* ── Empty state ─────────────────────────────────────────────────────────── */
.mp-empty-state { display:flex; flex-direction:column; align-items:center; text-align:center; padding:32px 16px; }

/* ── Modal ───────────────────────────────────────────────────────────────── */
.mp-modal-overlay { position:fixed; inset:0; z-index:50; display:flex; align-items:center; justify-content:center; padding:16px; background:rgba(0,0,0,.6); backdrop-filter:blur(6px); }
.mp-modal { width:100%; max-width:680px; max-height:92vh; border-radius:20px;
  background:var(--bg-card); border:1px solid var(--border-default); box-shadow:0 24px 64px rgba(0,0,0,.3);
  display:flex; flex-direction:column; overflow:hidden; }

/* ── Transitions ─────────────────────────────────────────────────────────── */
.slide-down-enter-active, .slide-down-leave-active { transition:all .2s ease; }
.slide-down-enter-from, .slide-down-leave-to { opacity:0; transform:translateY(-8px); }
.slide-left-enter-active, .slide-left-leave-active { transition:all .2s ease; }
.slide-left-enter-from, .slide-left-leave-to { opacity:0; transform:translateX(12px); }
</style>
