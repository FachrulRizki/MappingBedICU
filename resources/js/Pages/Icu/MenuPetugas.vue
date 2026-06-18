<script setup>
import { ref, computed, watch } from 'vue'
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
const fStatus = ref(props.filters.status ?? '')
const fNama   = ref(props.filters.nama   ?? '')
const fTgl    = ref(props.filters.tgl    ?? '')
const sortBy  = ref(props.filters.sortBy ?? 'created_at')
const sortDir = ref(props.filters.sortDir ?? 'desc')

let ft = null
const applyFilters = () => router.get(route('icu.menu_petugas'),
    { status: fStatus.value, nama: fNama.value, tgl: fTgl.value, sort: sortBy.value, dir: sortDir.value },
    { preserveState: true, replace: true })
const onNamaInput = () => { clearTimeout(ft); ft = setTimeout(applyFilters, 400) }
const resetFilter = () => { fStatus.value = ''; fNama.value = ''; fTgl.value = ''; applyFilters() }
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
const gColor = (g) => g === 'L' ? '#3498DB' : g === 'P' ? '#8E44AD' : 'var(--text-secondary)'

// ── Summary cards ─────────────────────────────────────────────────────────────
const CARDS = computed(() => [
    { key: '',               label: 'Total',          val: props.summary.total          ?? 0, color: '#5A6B7C' },
    { key: 'pending_admisi', label: 'Menunggu Admisi', val: props.summary.pending_admisi ?? 0, color: '#E67E22' },
    { key: 'pending_icu',    label: 'Menunggu ICU',    val: props.summary.pending_icu    ?? 0, color: '#E0923A' },
    { key: 'bed_verified',   label: 'Bed Verified',    val: props.summary.bed_verified   ?? 0, color: '#00A884' },
    { key: 'ditolak',        label: 'Ditolak',         val: props.summary.ditolak        ?? 0, color: '#E74C3C' },
])

const statusOptions = [
    { value: '',               label: 'Semua Status' },
    { value: 'pending_admisi', label: 'Menunggu Admisi' },
    { value: 'pending_icu',    label: 'Menunggu ICU' },
    { value: 'bed_verified',   label: 'Bed Verified' },
    { value: 'ditolak',        label: 'Ditolak' },
]

// ── SSO vs Lokal ──────────────────────────────────────────────────────────────
const isSSO = computed(() => props.authProvider === 'keycloak')

// ── Daftar pasien untuk pilih / buat SPRI ────────────────────────────────────
const cariPasien  = ref('')
const pasienList  = ref(props.pasienAktif ?? [])
const cariLoading = ref(false)
let ct = null
watch(cariPasien, (val) => {
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
const pasienPerRuang = computed(() => {
    const m = {}
    pasienList.value.forEach(p => {
        const k = p.Nama_RuangM ?? p.Kode_RuangM ?? 'Lainnya'
        if (!m[k]) m[k] = []
        m[k].push(p)
    })
    return m
})

// ── Modal / Form SPRI ────────────────────────────────────────────────────────
const modal = ref({ open: false, type: '' })
const openModal = (type) => {
    if (type === 'spri') resetSpri()
    modal.value = { open: true, type }
}
const closeModal = () => { modal.value.open = false; setTimeout(() => { modal.value = { open: false, type: '' } }, 200) }

const lookupLoading     = ref(false)
const lookupResult      = ref(null)
const lookupError       = ref('')
const kunjungans        = ref([])
const diagnosisExisting = ref('')
// Flag agar watch No_MR tidak trigger lookup saat prefill dari SSO
const skipLookupWatch   = ref(false)
const fmSpri = useForm({ No_MR: '', No_Reg: '', Diagnosis: '', IndikasiRI: '', asal_ruang: '', Dokter: '', spesialis: '', Keterangan: '' })

const resetSpri = () => {
    skipLookupWatch.value = false
    fmSpri.reset()
    lookupResult.value = null; lookupError.value = ''; kunjungans.value = []; diagnosisExisting.value = ''
}
watch(() => fmSpri.No_MR, (val) => {
    // Skip jika sedang prefill dari SSO (pilihPasien)
    if (skipLookupWatch.value) return
    if (val && val.trim().length >= 3) doLookup(val.trim())
    else { lookupResult.value = null; lookupError.value = ''; kunjungans.value = []; diagnosisExisting.value = '' }
})
const doLookup = async (noMr) => {
    lookupResult.value = null; lookupError.value = ''; kunjungans.value = []
    fmSpri.No_Reg = ''; fmSpri.Dokter = ''; fmSpri.asal_ruang = ''; diagnosisExisting.value = ''
    lookupLoading.value = true
    try {
        const r = await fetch(route('icu.menu_petugas.lookup') + '?No_MR=' + encodeURIComponent(noMr),
            { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        const d = await r.json()
        lookupResult.value = d
        if (d.found) {
            kunjungans.value = d.kunjungans ?? []
            if (kunjungans.value.length === 1) {
                const k = kunjungans.value[0]
                fmSpri.No_Reg = k.No_Reg; fmSpri.Dokter = k.Dokter; fmSpri.asal_ruang = k.asal_ruang; diagnosisExisting.value = k.Diagnosis
            }
            if (d.prefill) {
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
    if (k) { fmSpri.Dokter = k.Dokter; fmSpri.asal_ruang = k.asal_ruang; diagnosisExisting.value = k.Diagnosis }
}
const pilihPasien = (p) => {
    resetSpri()
    // Set flag agar watch tidak trigger doLookup (data sudah ada dari SSO)
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
    diagnosisExisting.value = ''
    if (p.No_Reg) {
        kunjungans.value = [{ No_Reg: p.No_Reg, Dokter: p.Dokter ?? '', asal_ruang: p.Nama_RuangM ?? '', Diagnosis: '' }]
    } else {
        // No_Reg belum ada → lookup untuk ambil kunjungans, tapi jangan reset field yang sudah terisi
        skipLookupWatch.value = false
        doLookup(p.No_MR)
    }
    // Delay kecil agar Vue selesai set No_MR sebelum watch jalan lagi
    setTimeout(() => { skipLookupWatch.value = false }, 100)
    openModal('spri')
}
const submitSpri = () => fmSpri.post(route('icu.menu_petugas.spri.store'), { onSuccess: closeModal })
const canSubmit  = computed(() =>
    fmSpri.No_MR.trim() && fmSpri.No_Reg.trim() &&
    fmSpri.Diagnosis.trim() && fmSpri.IndikasiRI.trim() &&
    lookupResult.value?.found)
</script>

<template>
<AppLayout :flash="flash" page-title="Menu Petugas Internal">
<div class="p-6 sm:p-8 space-y-6" style="font-family:'Inter','Plus Jakarta Sans',sans-serif">

<!-- ═══ PAGE HEADER ══════════════════════════════════════════════════════════ -->
<div class="flex items-center justify-between gap-4 flex-wrap">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl flex items-center justify-center flex-shrink-0"
            style="background:rgba(0,168,132,.15)">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="#00A884" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
        </div>
        <div>
            <h1 class="text-2xl font-bold tracking-tight" style="color:var(--text-primary)">Permintaan Rawat ICU</h1>
            <p class="text-sm" style="color:var(--text-secondary)">
                <span v-if="isSSO" class="inline-flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full inline-block" style="background:#00A884"></span>
                    SSO · Bangsal: <strong style="color:#00A884">{{ wardIds.join(', ') || '-' }}</strong>
                </span>
                <span v-else class="inline-flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full inline-block" style="background:#3498DB"></span>
                    Login Lokal · Data MySQL
                </span>
            </p>
        </div>
    </div>
    <div class="flex items-center gap-2">
        <!-- Tombol hanya tampil untuk user NON-SSO (admin/lokal) -->
        <button v-if="!isSSO && (canBuatSpriInternal || isAdmin)" @click="openModal('spri')"
            class="flex items-center gap-2 font-bold px-5 py-2.5 rounded-xl transition-all duration-150 hover:-translate-y-px"
            style="background:#00A884; color:#fff; font-size:14px; box-shadow:0 4px 14px rgba(0,168,132,0.3)">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            Buat SPRI
        </button>
    </div>
</div>

<!-- ═══ KPI SUMMARY CARDS ════════════════════════════════════════════════════ -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
    <button v-for="c in CARDS" :key="c.key"
        @click="fStatus = c.key; applyFilters()"
        class="relative flex flex-col gap-2 p-5 rounded-2xl text-left transition-all duration-200 hover:-translate-y-1"
        style="background:var(--bg-card); border:1px solid var(--border-default); box-shadow:var(--shadow-card); min-height:100px"
        :style="fStatus === c.key ? `border:2.5px solid ${c.color}; box-shadow:0 0 0 3px ${c.color}18` : ''">
        <span class="absolute left-0 top-6 bottom-6 w-1 rounded-r-full"
            :style="`background:${c.color}; opacity:${fStatus===c.key?'1':'0.35'}`"></span>
        <span class="text-3xl font-bold tracking-tight" :style="`color:${c.color}`">{{ c.val }}</span>
        <span class="text-xs font-medium leading-tight" style="color:var(--text-secondary)">{{ c.label }}</span>
    </button>
</div>

<!-- ═══ DAFTAR PASIEN SSO ═════════════════════════════════════════════════════ -->
<div v-if="isSSO" class="rounded-2xl overflow-hidden"
    style="background:var(--bg-card); border:1px solid var(--border-default); box-shadow:var(--shadow-card)">

    <!-- Header -->
    <div class="px-5 py-4 flex items-center justify-between gap-3 flex-wrap"
        style="border-bottom:1px solid var(--border-default)">
        <div class="flex items-center gap-3 min-w-0">
            <div class="w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0"
                style="background:rgba(0,168,132,.15)">
                <svg class="w-4 h-4" style="color:#00A884" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <p class="text-sm font-bold" style="color:var(--text-primary)">
                        {{ isIgdUser ? 'Pasien Aktif — IGD' : `Pasien Rawat Inap — Bangsal ${wardIds.join(', ')}` }}
                    </p>
                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full"
                        style="background:rgba(0,168,132,.12);color:#00A884">SSO · Live</span>
                    <span v-if="pasienList.length" class="text-[10px] font-semibold px-2 py-0.5 rounded-full"
                        style="background:var(--bg-input);color:var(--text-secondary)">
                        {{ pasienList.length }} pasien
                    </span>
                </div>
                <p class="text-xs mt-0.5" style="color:var(--text-secondary)">
                    Klik baris pasien untuk membuat permintaan rawat ICU
                </p>
            </div>
        </div>
        <!-- Search -->
        <div class="relative w-full sm:w-72">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5" style="color:var(--text-muted)"
                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input v-model="cariPasien" placeholder="Cari nama / No. MR..."
                class="w-full text-sm pl-9 pr-9 py-2.5 rounded-xl outline-none"
                style="border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary)"/>
            <svg v-if="cariLoading" class="absolute right-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 animate-spin"
                style="color:var(--text-muted)" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
        </div>
    </div>

    <!-- Empty -->
    <div v-if="!pasienList.length" class="py-14 text-center">
        <div class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-3" style="background:var(--bg-input)">
            <svg class="w-7 h-7" style="color:var(--text-muted)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
        </div>
        <p class="font-semibold" style="color:var(--text-secondary)">Tidak ada pasien aktif</p>
        <p v-if="!cariPasien && wardIds.length === 0" class="text-sm mt-1" style="color:var(--text-muted)">
            Ward belum terkonfigurasi di token Keycloak Anda
        </p>
        <p v-else-if="cariPasien" class="text-sm mt-1" style="color:var(--text-muted)">Coba kata kunci lain</p>
    </div>

    <!-- Tabel pasien -->
    <div v-else class="overflow-x-auto">
        <table class="w-full" style="border-collapse:collapse; min-width:620px">
            <thead>
                <tr style="background:var(--bg-surface-2,var(--bg-surface))">
                    <th class="px-5 py-3 text-left" style="color:var(--text-muted); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; border-bottom:2px solid var(--border-default)">Pasien</th>
                    <th class="px-5 py-3 text-left" style="color:var(--text-muted); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; border-bottom:2px solid var(--border-default)">No. MR / Reg</th>
                    <th class="px-5 py-3 text-left" style="color:var(--text-muted); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; border-bottom:2px solid var(--border-default)">Ruang</th>
                    <th class="px-5 py-3 text-left" style="color:var(--text-muted); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; border-bottom:2px solid var(--border-default)">Dokter</th>
                    <th class="px-5 py-3 text-center w-32" style="border-bottom:2px solid var(--border-default)"></th>
                </tr>
            </thead>
            <tbody>
                <template v-for="(pasiens, ruang) in pasienPerRuang" :key="ruang">
                    <tr>
                        <td colspan="5" class="px-5 py-2"
                            style="background:var(--bg-main); border-bottom:1px solid var(--border-default)">
                            <div class="flex items-center gap-2">
                                <span>🏥</span>
                                <span class="text-[10px] font-bold uppercase tracking-widest" style="color:var(--text-accent)">{{ ruang }}</span>
                                <span class="text-[9px] font-semibold px-1.5 py-0.5 rounded-full"
                                    style="background:rgba(0,168,132,.1);color:#00A884">{{ pasiens.length }}</span>
                            </div>
                        </td>
                    </tr>
                    <tr v-for="p in pasiens" :key="p.No_MR + (p.No_Reg ?? '')"
                        @click="pilihPasien(p)"
                        class="cursor-pointer group"
                        style="border-bottom:1px solid var(--border-row,var(--border-default)); transition:background .12s, transform .12s, box-shadow .12s"
                        @mouseenter="e => { e.currentTarget.style.background='var(--bg-row-hover,var(--bg-surface))'; e.currentTarget.style.transform='translateY(-1px)'; e.currentTarget.style.boxShadow='0 2px 8px rgba(0,0,0,.06)'; }"
                        @mouseleave="e => { e.currentTarget.style.background=''; e.currentTarget.style.transform=''; e.currentTarget.style.boxShadow=''; }">
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 text-base font-bold"
                                    :style="`background:${gColor(p.jenis_kelamin)}18; color:${gColor(p.jenis_kelamin)}`">
                                    {{ gIcon(p.jenis_kelamin) }}
                                </div>
                                <span class="font-semibold text-sm" style="color:var(--text-primary)">{{ p.Nama_Pasien }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-3.5">
                            <p class="font-mono text-xs" style="color:var(--text-secondary)">{{ p.No_MR }}</p>
                            <span v-if="p.No_Reg" class="inline-block text-[10px] font-mono px-1.5 py-0.5 rounded mt-0.5"
                                style="background:rgba(52,152,219,.1);color:#3498DB">{{ p.No_Reg }}</span>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="text-xs" style="color:var(--text-secondary)">{{ p.Nama_RuangM || '—' }}</span>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="text-xs" style="color:var(--text-secondary)">{{ p.Dokter || '—' }}</span>
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            <button class="inline-flex items-center gap-1.5 text-xs font-bold px-3.5 py-2 rounded-xl transition-all group-hover:scale-105"
                                style="background:rgba(0,168,132,.12); color:#00A884; border:1px solid rgba(0,168,132,.3)">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                </svg>
                                Buat SPRI
                            </button>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
</div>
<!-- end daftar pasien SSO -->

<!-- ═══ FILTER BAR ════════════════════════════════════════════════════════════ -->
<div class="rounded-2xl p-5 sm:p-6 space-y-4" v-if="!isSSO && (canBuatSpriInternal || isAdmin)"
    style="background:var(--bg-surface); border:1px solid var(--border-default); box-shadow:var(--shadow-card)">
    <p class="text-sm font-bold" style="color:var(--text-primary)">Riwayat SPRI Saya</p>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="space-y-1.5">
            <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Status</label>
            <select v-model="fStatus" @change="applyFilters" class="w-full rounded-xl outline-none"
                style="padding:10px 14px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); font-size:13px">
                <option v-for="o in statusOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
        </div>
        <div class="space-y-1.5">
            <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Nama / No. MR</label>
            <input v-model="fNama" @input="onNamaInput" placeholder="Cari pasien..."
                class="w-full rounded-xl outline-none"
                style="padding:10px 14px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); font-size:13px"/>
        </div>
        <div class="space-y-1.5">
            <label class="block text-xs font-semibold uppercase tracking-wide" style="color:var(--text-muted)">Tanggal</label>
            <input v-model="fTgl" @change="applyFilters" type="date"
                class="w-full rounded-xl outline-none"
                style="padding:10px 14px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); font-size:13px"/>
        </div>
    </div>
    <div class="flex items-center gap-2.5 flex-wrap">
        <span class="text-xs font-semibold" style="color:var(--text-muted)">Urutkan:</span>
        <button v-for="col in [{key:'created_at',label:'Waktu'},{key:'nama_pasien',label:'Nama'},{key:'status',label:'Status'}]"
            :key="col.key" @click="toggleSort(col.key)"
            class="text-xs font-semibold px-3.5 py-2 rounded-xl transition-all duration-150"
            :style="sortBy === col.key
                ? 'background:rgba(0,168,132,.15); color:#00A884; border:1.5px solid rgba(0,168,132,.35)'
                : 'background:var(--bg-input); color:var(--text-secondary); border:1.5px solid var(--border-default)'">
            {{ col.label }} {{ sortIcon(col.key) }}
        </button>
        <button v-if="fStatus || fNama || fTgl" @click="resetFilter"
            class="ml-auto text-xs font-semibold px-3.5 py-2 rounded-xl flex items-center gap-1.5"
            style="background:rgba(231,76,60,.1); color:#E74C3C; border:1.5px solid rgba(231,76,60,.25)">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            Reset Filter
        </button>
    </div>
</div>

<!-- ═══ EMPTY STATE ════════════════════════════════════════════════════════════ -->
<div v-if="!spriList.length"
    class="rounded-2xl text-center py-16"
    style="background:var(--bg-card); border:1px solid var(--border-default); box-shadow:var(--shadow-card)">
    <div class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-4" style="background:var(--bg-input)">
        <svg class="w-7 h-7" style="color:var(--text-muted)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round"
                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
    </div>
    <p class="font-semibold" style="color:var(--text-secondary)">Belum ada SPRI</p>
    <p class="text-sm mt-1" style="color:var(--text-muted)">
        {{ isSSO ? 'Klik baris pasien di daftar atas untuk membuat SPRI' : 'Klik "Buat SPRI" atau pilih pasien dari daftar' }}
    </p>
</div>

<!-- ═══ TABEL RIWAYAT SPRI (Desktop) ══════════════════════════════════════════ -->
<div v-else class="rounded-2xl overflow-hidden"
    style="background:var(--bg-card); border:1px solid var(--border-default); box-shadow:var(--shadow-card)">

    <!-- DESKTOP TABLE -->
    <div class="hidden md:block overflow-x-auto">
        <table class="w-full" style="border-collapse:collapse; min-width:780px">
            <thead>
                <tr style="background:var(--bg-surface-2,var(--bg-surface))">
                    <th class="px-4 py-3.5 text-left w-10"
                        style="color:var(--table-th-color,var(--text-muted)); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; border-bottom:2px solid var(--border-table,var(--border-default))">#</th>
                    <th class="px-4 py-3.5 text-left cursor-pointer select-none"
                        style="color:var(--table-th-color,var(--text-muted)); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; border-bottom:2px solid var(--border-table,var(--border-default)); min-width:180px"
                        @click="toggleSort('nama_pasien')">
                        <span class="flex items-center gap-1">Pasien <span style="opacity:.5">{{ sortIcon('nama_pasien') }}</span></span>
                    </th>
                    <th class="px-4 py-3.5 text-left"
                        style="color:var(--table-th-color,var(--text-muted)); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; border-bottom:2px solid var(--border-table,var(--border-default)); min-width:200px">Diagnosa / Indikasi</th>
                    <th class="px-4 py-3.5 text-left"
                        style="color:var(--table-th-color,var(--text-muted)); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; border-bottom:2px solid var(--border-table,var(--border-default)); min-width:80px">Asal Ruang</th>
                    <th class="px-4 py-3.5 text-left"
                        style="color:var(--table-th-color,var(--text-muted)); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; border-bottom:2px solid var(--border-table,var(--border-default)); min-width:140px">Bed</th>
                    <th class="px-4 py-3.5 text-left cursor-pointer select-none"
                        style="color:var(--table-th-color,var(--text-muted)); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; border-bottom:2px solid var(--border-table,var(--border-default)); min-width:140px"
                        @click="toggleSort('status')">
                        <span class="flex items-center gap-1">Status <span style="opacity:.5">{{ sortIcon('status') }}</span></span>
                    </th>
                    <th class="px-4 py-3.5 text-left cursor-pointer select-none"
                        style="color:var(--table-th-color,var(--text-muted)); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; border-bottom:2px solid var(--border-table,var(--border-default)); min-width:120px"
                        @click="toggleSort('created_at')">
                        <span class="flex items-center gap-1">Waktu <span style="opacity:.5">{{ sortIcon('created_at') }}</span></span>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(item, idx) in spriList" :key="item.id"
                    class="group"
                    style="border-bottom:1px solid var(--border-row,var(--border-default)); transition:background .12s ease, transform .12s ease, box-shadow .12s ease"
                    :style="`border-left:4px solid ${ss(item.status).dot}`"
                    @mouseenter="e => { e.currentTarget.style.background='var(--bg-row-hover,var(--bg-surface))'; e.currentTarget.style.transform='translateY(-1px)'; e.currentTarget.style.boxShadow='0 3px 12px rgba(0,0,0,.07)'; e.currentTarget.style.position='relative'; e.currentTarget.style.zIndex='1'; }"
                    @mouseleave="e => { e.currentTarget.style.background=''; e.currentTarget.style.transform=''; e.currentTarget.style.boxShadow=''; }">
                    <!-- # -->
                    <td class="px-4 py-4">
                        <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold"
                            style="background:var(--bg-input); color:var(--text-muted); font-family:'DM Mono',monospace">
                            {{ idx + 1 }}
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
                                <p class="font-mono mt-0.5" style="color:var(--text-muted); font-size:10.5px">{{ item.No_MR }} · {{ item.No_Reg }}</p>
                            </div>
                        </div>
                    </td>
                    <!-- Diagnosa / Indikasi -->
                    <td class="px-4 py-4">
                        <p class="font-medium truncate" style="color:var(--text-primary); font-size:13px; max-width:200px" :title="item.Diagnosis">{{ item.Diagnosis ?? '—' }}</p>
                        <p v-if="item.IndikasiRI" class="text-xs mt-0.5 truncate" style="color:var(--text-muted); max-width:200px" :title="item.IndikasiRI">{{ item.IndikasiRI }}</p>
                    </td>
                    <!-- Asal Ruang -->
                    <td class="px-4 py-4">
                        <span class="text-xs" style="color:var(--text-secondary)">{{ item.asal_ruang ?? '—' }}</span>
                    </td>
                    <!-- Bed -->
                    <td class="px-4 py-4">
                        <div v-if="item.nama_bed" class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0"
                                style="background:#EBF9F1; color:#00A884; font-size:13px">🏥</div>
                            <div>
                                <p class="font-semibold text-xs" style="color:#00A884">{{ item.nama_bed }}</p>
                                <p v-if="item.kebutuhan_bed" class="text-[10px]" style="color:var(--text-muted)">{{ item.kebutuhan_bed }}</p>
                            </div>
                        </div>
                        <span v-else class="text-xs" style="color:var(--text-muted)">Belum dialokasi</span>
                    </td>
                    <!-- Status -->
                    <td class="px-4 py-4">
                        <div>
                            <span class="inline-flex items-center gap-1.5 text-xs font-bold px-3 py-1.5 rounded-xl whitespace-nowrap"
                                :style="`background:${ss(item.status).bg}; color:${ss(item.status).color}`">
                                <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" :style="`background:${ss(item.status).color}`"></span>
                                {{ item.status_label }}
                            </span>
                            <p v-if="item.alasan_tolak" class="text-[10px] mt-1 max-w-[120px] truncate" style="color:#E74C3C" :title="item.alasan_tolak">{{ item.alasan_tolak }}</p>
                        </div>
                    </td>
                    <!-- Waktu -->
                    <td class="px-4 py-4">
                        <p class="font-mono text-xs" style="color:var(--text-secondary)">{{ item.created_at_fmt }}</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- MOBILE CARDS -->
    <div class="block md:hidden divide-y" style="border-color:var(--border-default)">
        <div v-for="item in spriList" :key="`mob-${item.id}`"
            class="p-5 relative"
            style="border-left:4px solid transparent"
            :style="`border-left-color:${ss(item.status).dot}`">
            <div class="flex justify-between items-start mb-3 gap-2">
                <span class="inline-flex items-center gap-1.5 text-xs font-bold px-2.5 py-1 rounded-xl"
                    :style="`background:${ss(item.status).bg}; color:${ss(item.status).color}`">
                    <span class="w-1.5 h-1.5 rounded-full" :style="`background:${ss(item.status).color}`"></span>
                    {{ item.status_label }}
                </span>
                <span class="text-xs font-mono" style="color:var(--text-muted)">{{ item.created_at_fmt }}</span>
            </div>
            <div class="flex items-center gap-3 mb-3">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 text-sm font-bold"
                    :style="`background:${gColor(item.jenis_kelamin)}18; color:${gColor(item.jenis_kelamin)}`">
                    {{ gIcon(item.jenis_kelamin) }}
                </div>
                <div class="min-w-0">
                    <p class="font-semibold text-sm truncate" style="color:var(--text-primary)">{{ item.nama_pasien }}</p>
                    <p class="font-mono text-xs" style="color:var(--text-muted)">{{ item.No_MR }} · {{ item.No_Reg }}</p>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-xs">
                <div>
                    <p style="color:var(--text-muted)">Diagnosa</p>
                    <p class="font-medium mt-0.5 truncate" style="color:var(--text-primary)" :title="item.Diagnosis">{{ item.Diagnosis ?? '—' }}</p>
                </div>
                <div>
                    <p style="color:var(--text-muted)">Indikasi RI</p>
                    <p class="font-medium mt-0.5 truncate" style="color:var(--text-primary)" :title="item.IndikasiRI">{{ item.IndikasiRI ?? '—' }}</p>
                </div>
                <div>
                    <p style="color:var(--text-muted)">Asal Ruang</p>
                    <p class="mt-0.5" style="color:var(--text-primary)">{{ item.asal_ruang ?? '—' }}</p>
                </div>
                <div>
                    <p style="color:var(--text-muted)">Dokter</p>
                    <p class="mt-0.5 truncate" style="color:var(--text-primary)">{{ item.Dokter ?? '—' }}</p>
                </div>
                <div v-if="item.nama_bed" class="col-span-2">
                    <p style="color:var(--text-muted)">Bed</p>
                    <p class="font-semibold mt-0.5" style="color:#00A884">🏥 {{ item.nama_bed }}{{ item.kebutuhan_bed ? ' · ' + item.kebutuhan_bed : '' }}</p>
                </div>
                <div v-if="item.catatan_admisi" class="col-span-2">
                    <p style="color:var(--text-muted)">Catatan Admisi</p>
                    <p class="mt-0.5" style="color:var(--text-primary)">{{ item.catatan_admisi }}</p>
                </div>
                <div v-if="item.alasan_tolak" class="col-span-2">
                    <p style="color:var(--text-muted)">Alasan Penolakan</p>
                    <p class="mt-0.5 font-medium" style="color:#E74C3C">{{ item.alasan_tolak }}</p>
                </div>
            </div>
            <!-- Progress steps -->
            <div class="flex items-center gap-1.5 flex-wrap mt-3 pt-3" style="border-top:1px solid var(--border-default)">
                <template v-for="(step, i) in [{k:'pending_admisi',l:'Menunggu Admisi'},{k:'pending_icu',l:'Menunggu ICU'},{k:'bed_verified',l:'Bed Verified'}]" :key="step.k">
                    <div class="w-4 h-4 rounded-full flex items-center justify-center text-[8px] font-bold"
                        :style="item.status==='bed_verified' || (item.status==='pending_icu' && i===0)
                            ? 'background:rgba(0,168,132,.2);color:#00A884'
                            : item.status===step.k ? 'background:rgba(224,146,58,.2);color:#E0923A'
                            : 'background:var(--bg-input);color:var(--text-muted)'">
                        {{ i + 1 }}
                    </div>
                    <span class="text-[9px]" style="color:var(--text-muted)">{{ step.l }}</span>
                    <div v-if="i < 2" class="w-5 h-px" style="background:var(--border-default)"></div>
                </template>
            </div>
        </div>
    </div>

</div><!-- end tabel card -->

</div><!-- end p-6 -->

<!-- ═══ MODAL BUAT SPRI ════════════════════════════════════════════════════════ -->
<Transition enter-active-class="transition-all duration-200" enter-from-class="opacity-0" leave-to-class="opacity-0">
    <div v-if="modal.open" class="fixed inset-0 z-50 flex items-center justify-center p-4"
        style="background:rgba(0,0,0,.6); backdrop-filter:blur(4px)"
        @click.self="closeModal">
        <Transition enter-active-class="transition-all duration-200" enter-from-class="opacity-0 scale-95" leave-to-class="opacity-0 scale-95">
            <div v-if="modal.type === 'spri'" class="w-full max-w-2xl max-h-[92vh] overflow-y-auto rounded-2xl"
                style="background:var(--bg-sidebar,#ffffff); border:1px solid var(--border-default,#e2e8f0); box-shadow:0 24px 64px rgba(0,0,0,.3)">
                <!-- Modal Header -->
                <div class="sticky top-0 z-10" style="background:var(--bg-sidebar,#ffffff); border-bottom:1px solid var(--border-default,#e2e8f0)">
                    <div class="px-6 py-3.5" style="background:linear-gradient(90deg,#3498DB,#00A884)">
                        <div class="flex items-center gap-2.5">
                            <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <p class="text-sm font-bold text-white">Surat Permintaan Rawat ICU — Pasien Internal</p>
                        </div>
                    </div>
                    <div class="flex items-center justify-between px-6 py-3">
                        <p class="text-sm" style="color:#64748b">Isi data klinis dan kirim ke Admisi</p>
                        <button @click="closeModal" class="p-1.5 rounded-lg transition-all hover:scale-110"
                            style="background:#f1f5f9; color:#475569; border:1px solid #e2e8f0">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <!-- Modal Body -->
                <form @submit.prevent="submitSpri" class="p-6 space-y-6" style="color:var(--text-primary, #1a1a1a)">
                    <!-- Banner info pasien terpilih (SSO) -->
                    <div v-if="isSSO && lookupResult?.found"
                        class="flex items-center gap-3 px-4 py-3 rounded-xl"
                        style="background:rgba(0,168,132,.08); border:1px solid rgba(0,168,132,.25)">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 font-bold text-base"
                            :style="`background:${gColor(lookupResult.jenis_kelamin || '')}18; color:${gColor(lookupResult.jenis_kelamin || '')}`">
                            {{ gIcon(lookupResult.jenis_kelamin || '') }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-bold text-sm" style="color:#00A884">{{ lookupResult.nama_pasien }}</p>
                            <p class="font-mono text-[11px] mt-0.5" style="color:var(--text-secondary, #666)">
                                {{ fmSpri.No_MR }} · {{ fmSpri.No_Reg }} · {{ fmSpri.asal_ruang || '—' }}
                            </p>
                        </div>
                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full flex-shrink-0"
                            style="background:rgba(0,168,132,.15); color:#00A884">SSO Verified ✓</span>
                    </div>
                    <div>
                        <div class="flex items-center gap-2 mb-3">
                            <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold text-white flex-shrink-0" style="background:#3498DB">1</span>
                            <p class="text-xs font-bold uppercase tracking-wide" style="color:#3498DB">Verifikasi Pasien</p>
                            <span v-if="isSSO && lookupResult?.found"
                                class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full"
                                style="background:rgba(0,168,132,.12); color:#00A884">
                                ✓ Terisi otomatis dari SSO
                            </span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- No. MR — read-only saat SSO -->
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-primary,#1a1a1a)">
                                    No. Medical Record <span style="color:#E74C3C">*</span>
                                </label>
                                <div v-if="isSSO" class="relative">
                                    <input :value="fmSpri.No_MR" readonly tabindex="-1"
                                        class="w-full px-3.5 py-2.5 text-sm rounded-xl outline-none font-mono cursor-not-allowed"
                                        style="border:1.5px solid rgba(0,168,132,.4); background:rgba(0,168,132,.05); color:#1a1a1a; opacity:1"/>
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-bold" style="color:#00A884">✓</span>
                                </div>
                                <div v-else class="relative">
                                    <input v-model="fmSpri.No_MR" required placeholder="Ketik No. MR..."
                                        class="w-full px-3.5 py-2.5 text-sm rounded-xl outline-none font-mono pr-9"
                                        :style="`border:1.5px solid ${fmSpri.errors.No_MR || lookupError ? '#E74C3C' : lookupResult?.found ? '#00A884' : 'var(--border-default,#e2e8f0)'}; background:var(--bg-input,#f8fafc); color:var(--text-primary,#1a1a1a)`"/>
                                    <div class="absolute right-3 top-1/2 -translate-y-1/2">
                                        <svg v-if="lookupLoading" class="w-4 h-4 animate-spin" style="color:#888" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                        </svg>
                                        <span v-else-if="lookupResult?.found" style="color:#00A884" class="text-sm font-bold">✓</span>
                                        <span v-else-if="lookupError" style="color:#E74C3C" class="text-sm font-bold">✕</span>
                                    </div>
                                </div>
                                <p v-if="!isSSO && lookupError" class="text-xs mt-1" style="color:#E74C3C">{{ lookupError }}</p>
                                <p v-else-if="lookupResult?.found" class="text-xs mt-1 font-semibold" style="color:#00A884">✓ {{ lookupResult.nama_pasien }}</p>
                            </div>
                            <!-- No. Registrasi — read-only saat SSO dengan No_Reg sudah terisi -->
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-primary,#1a1a1a)">
                                    No. Registrasi <span style="color:#E74C3C">*</span>
                                </label>
                                <!-- SSO + sudah ada No_Reg → read-only -->
                                <div v-if="isSSO && fmSpri.No_Reg" class="relative">
                                    <input :value="fmSpri.No_Reg" readonly tabindex="-1"
                                        class="w-full px-3.5 py-2.5 text-sm rounded-xl outline-none font-mono cursor-not-allowed"
                                        style="border:1.5px solid rgba(0,168,132,.4); background:rgba(0,168,132,.05); color:#1a1a1a; opacity:1"/>
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-bold" style="color:#00A884">✓</span>
                                </div>
                                <!-- SSO + belum ada No_Reg → pilih dari kunjungans (jarang terjadi) -->
                                <select v-else-if="isSSO && !fmSpri.No_Reg && kunjungans.length > 1"
                                    v-model="fmSpri.No_Reg" @change="onKunjunganChange(fmSpri.No_Reg)"
                                    class="w-full px-3.5 py-2.5 text-sm rounded-xl outline-none"
                                    style="border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary)">
                                    <option value="" disabled>-- Pilih Kunjungan --</option>
                                    <option v-for="k in kunjungans" :key="k.No_Reg" :value="k.No_Reg">
                                        {{ k.No_Reg }}{{ k.asal_ruang ? ' — ' + k.asal_ruang : '' }}
                                    </option>
                                </select>
                                <!-- Lokal → select jika banyak kunjungan, atau read-only -->
                                <select v-else-if="!isSSO && kunjungans.length > 1"
                                    v-model="fmSpri.No_Reg" @change="onKunjunganChange(fmSpri.No_Reg)"
                                    class="w-full px-3.5 py-2.5 text-sm rounded-xl outline-none"
                                    style="border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary)">
                                    <option value="" disabled>-- Pilih Kunjungan --</option>
                                    <option v-for="k in kunjungans" :key="k.No_Reg" :value="k.No_Reg">
                                        {{ k.No_Reg }}{{ k.asal_ruang ? ' — ' + k.asal_ruang : '' }}
                                    </option>
                                </select>
                                <input v-else :value="fmSpri.No_Reg" readonly tabindex="-1"
                                    class="w-full px-3.5 py-2.5 text-sm rounded-xl outline-none opacity-60 cursor-not-allowed"
                                    style="border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary)"
                                    :placeholder="!lookupResult?.found ? 'Isi No. MR dulu' : 'Tidak ada kunjungan'"/>
                            </div>
                        </div>
                    </div>
                    <!-- 2. Data Rekam Medis -->
                    <div>
                        <div class="flex items-center gap-2 mb-3">
                            <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold text-white flex-shrink-0" style="background:#5A6B7C">2</span>
                            <p class="text-xs font-bold uppercase tracking-wide" style="color:#5A6B7C">Data dari Rekam Medis</p>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary,#555)">Diagnosis (RM)</label>
                                <input :value="diagnosisExisting || '—'" readonly
                                    class="w-full px-3.5 py-2.5 text-sm rounded-xl outline-none cursor-not-allowed"
                                    :style="isSSO && diagnosisExisting
                                        ? 'border:1.5px solid rgba(0,168,132,.35); background:rgba(0,168,132,.05); color:#1a1a1a'
                                        : 'border:1.5px solid #e2e8f0; background:#f8fafc; color:#64748b; opacity:1'"/>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary,#555)">Asal Ruang</label>
                                <input :value="fmSpri.asal_ruang || '—'" readonly
                                    class="w-full px-3.5 py-2.5 text-sm rounded-xl outline-none cursor-not-allowed"
                                    :style="isSSO && fmSpri.asal_ruang
                                        ? 'border:1.5px solid rgba(0,168,132,.35); background:rgba(0,168,132,.05); color:#1a1a1a'
                                        : 'border:1.5px solid #e2e8f0; background:#f8fafc; color:#64748b; opacity:1'"/>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary,#555)">Dokter DPJP</label>
                                <input :value="fmSpri.Dokter || '—'" readonly
                                    class="w-full px-3.5 py-2.5 text-sm rounded-xl outline-none cursor-not-allowed"
                                    :style="isSSO && fmSpri.Dokter
                                        ? 'border:1.5px solid rgba(0,168,132,.35); background:rgba(0,168,132,.05); color:#1a1a1a'
                                        : 'border:1.5px solid #e2e8f0; background:#f8fafc; color:#64748b; opacity:1'"/>
                            </div>
                        </div>
                    </div>
                    <!-- 3. Data Klinis ICU -->
                    <div>
                        <div class="flex items-center gap-2 mb-3">
                            <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold text-white flex-shrink-0" style="background:#00A884">3</span>
                            <p class="text-xs font-bold uppercase tracking-wide" style="color:#00A884">Data Klinis untuk ICU</p>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-primary,#1a1a1a)">
                                    Diagnosis ICU <span style="color:#E74C3C">*</span>
                                </label>
                                <Icd10Search v-model="fmSpri.Diagnosis" placeholder="Cari kode / keterangan ICD-10"
                                    :required="true" :has-error="!!fmSpri.errors.Diagnosis"/>
                                <p v-if="fmSpri.errors.Diagnosis" class="text-xs mt-1" style="color:#E74C3C">{{ fmSpri.errors.Diagnosis }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-primary,#1a1a1a)">
                                    Indikasi Rawat ICU <span style="color:#E74C3C">*</span>
                                </label>
                                <input v-model="fmSpri.IndikasiRI" required placeholder="Alasan klinis butuh ICU"
                                    class="w-full px-3.5 py-2.5 text-sm rounded-xl outline-none"
                                    :style="`border:1.5px solid ${fmSpri.errors.IndikasiRI ? '#E74C3C' : 'var(--border-default,#e2e8f0)'}; background:var(--bg-input,#f8fafc); color:var(--text-primary,#1a1a1a)`"/>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary,#555)">Keterangan Tambahan</label>
                                <textarea v-model="fmSpri.Keterangan" rows="2"
                                    placeholder="Kondisi terkini, catatan penting..."
                                    class="w-full px-3.5 py-2.5 text-sm rounded-xl outline-none resize-none"
                                    style="border:1.5px solid var(--border-default,#e2e8f0); background:var(--bg-input,#f8fafc); color:var(--text-primary,#1a1a1a)"/>
                            </div>
                        </div>
                    </div>
                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-3 pt-2" style="border-top:1px solid var(--border-default,#e2e8f0)">
                        <button type="button" @click="closeModal"
                            class="text-sm px-5 py-2.5 rounded-xl font-semibold"
                            style="background:#f1f5f9; color:#475569; border:1px solid #e2e8f0">Batal</button>
                        <button type="submit" :disabled="!canSubmit || fmSpri.processing"
                            class="text-sm px-6 py-2.5 rounded-xl font-bold transition-all duration-150"
                            :style="canSubmit && !fmSpri.processing
                                ? 'background:#00A884; color:#fff; box-shadow:0 4px 14px rgba(0,168,132,.3)'
                                : 'background:#f1f5f9; color:#94a3b8; opacity:.7'">
                            {{ fmSpri.processing ? 'Menyimpan…' : 'Kirim SPRI ke Admisi' }}
                        </button>
                    </div>
                </form>
            </div>
        </Transition>
    </div>
</Transition>

</AppLayout>
</template>
