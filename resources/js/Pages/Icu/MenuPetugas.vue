<script setup>
import { ref, computed, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppLayout   from '@/Layouts/AppLayout.vue'
import Icd10Search from '@/Components/Icd10Search.vue'
import { useAuth } from '@/composables/useAuth.js'

const { canBuatSpriInternal, isAdmin } = useAuth()
const props = defineProps({
    spriList:     { type: Array,  default: () => [] },
    summary:      { type: Object, default: () => ({}) },
    filters:      { type: Object, default: () => ({}) },
    pasienAktif:  { type: Array,  default: () => [] },
    wardIds:      { type: Array,  default: () => [] },
    authProvider: { type: String, default: 'local' },
    kamarKosong:  { type: Array,  default: () => [] },
    masterKelas:  { type: Array,  default: () => [] },
    flash:        { type: Object, default: () => ({}) },
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
const pasienSectionTitle = computed(() =>
    isSSO.value
        ? `Pasien Rawat Inap — Bangsal ${props.wardIds.join(', ') || '-'}`
        : 'Daftar Pasien untuk Buat SPRI'
)
const pasienSectionDesc = computed(() =>
    isSSO.value
        ? 'Pasien aktif di bangsal Anda dari DB RS. Klik untuk buat SPRI otomatis.'
        : 'Pilih pasien dari database lokal (mode pengembangan).'
)

// ── Daftar pasien untuk pilih / buat SPRI ─────────────────────────────────────
const showPasienPanel = ref(false)
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
const openModal  = (type) => { if (type === 'spri') resetSpri(); modal.value = { open: true, type } }
const closeModal = () => { modal.value.open = false; setTimeout(() => { modal.value = { open: false, type: '' } }, 200) }

const lookupLoading     = ref(false)
const lookupResult      = ref(null)
const lookupError       = ref('')
const kunjungans        = ref([])
const diagnosisExisting = ref('')
const fmSpri = useForm({ No_MR: '', No_Reg: '', Diagnosis: '', IndikasiRI: '', asal_ruang: '', Dokter: '', spesialis: '', Keterangan: '' })

const resetSpri = () => {
    fmSpri.reset(); lookupResult.value = null; lookupError.value = ''; kunjungans.value = []; diagnosisExisting.value = ''
}
watch(() => fmSpri.No_MR, (val) => {
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
    fmSpri.No_MR = p.No_MR; fmSpri.No_Reg = p.No_Reg ?? ''; fmSpri.asal_ruang = p.Nama_RuangM ?? ''
    showPasienPanel.value = false
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
        <!-- Tombol Pilih Pasien (toggle panel) -->
        <button @click="showPasienPanel = !showPasienPanel"
            class="flex items-center gap-2 font-semibold px-4 py-2.5 rounded-xl transition-all duration-150 hover:-translate-y-px"
            :style="showPasienPanel
                ? 'background:rgba(52,152,219,.15); color:#3498DB; border:1.5px solid rgba(52,152,219,.3); font-size:13px'
                : 'background:var(--bg-surface); color:var(--text-secondary); border:1.5px solid var(--border-default); font-size:13px'">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            Pilih Pasien
            <span v-if="pasienList.length" class="text-[10px] font-bold px-1.5 py-0.5 rounded-full"
                style="background:rgba(52,152,219,.2)">{{ pasienList.length }}</span>
        </button>
        <!-- Tombol Buat SPRI manual -->
        <button v-if="canBuatSpriInternal || isAdmin" @click="openModal('spri')"
            class="flex items-center gap-2 font-bold px-5 py-2.5 rounded-xl transition-all duration-150 hover:-translate-y-px"
            style="background:#00A884; color:#fff; font-size:14px; box-shadow:0 4px 14px rgba(0,168,132,0.3)">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            Buat Permintaan Rawat ICU
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

<!-- ═══ PANEL PILIH PASIEN (toggle) ═════════════════════════════════════════ -->
<Transition
    enter-active-class="transition-all duration-300 ease-out"
    enter-from-class="opacity-0 -translate-y-3"
    leave-active-class="transition-all duration-200 ease-in"
    leave-to-class="opacity-0 -translate-y-3">
<div v-if="showPasienPanel" class="rounded-2xl overflow-hidden"
    style="background:var(--bg-surface); border:1.5px solid rgba(52,152,219,.3); box-shadow:0 8px 32px rgba(52,152,219,.1)">
    <!-- Header panel -->
    <div class="px-5 py-3.5 flex items-center justify-between gap-3 flex-wrap"
        style="background:rgba(52,152,219,.06); border-bottom:1px solid rgba(52,152,219,.2)">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-xl flex items-center justify-center" style="background:rgba(52,152,219,.15)">
                <svg class="w-4 h-4" style="color:#3498DB" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <div>
                <p class="text-sm font-bold" style="color:var(--text-primary)">{{ pasienSectionTitle }}</p>
                <p class="text-xs" style="color:var(--text-secondary)">{{ pasienSectionDesc }}</p>
            </div>
            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full"
                :style="isSSO ? 'background:rgba(0,168,132,.12);color:#00A884' : 'background:rgba(52,152,219,.12);color:#3498DB'">
                {{ isSSO ? 'SSO · Live' : 'Dev · Lokal' }}
            </span>
        </div>
        <!-- Search -->
        <div class="relative w-full sm:w-72">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5" style="color:var(--text-muted)"
                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input v-model="cariPasien"
                :placeholder="isSSO ? 'Cari nama / No. MR...' : 'Cari pasien...'"
                class="w-full text-sm pl-9 pr-9 py-2.5 rounded-xl outline-none"
                style="border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary)"/>
            <svg v-if="cariLoading" class="absolute right-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 animate-spin"
                style="color:var(--text-muted)" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
        </div>
    </div>
    <!-- Empty state -->
    <div v-if="!pasienList.length" class="py-14 text-center">
        <div class="w-12 h-12 rounded-2xl flex items-center justify-center mx-auto mb-3" style="background:var(--bg-input)">
            <svg class="w-6 h-6" style="color:var(--text-muted)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
        </div>
        <p class="text-sm font-semibold" style="color:var(--text-secondary)">
            {{ cariPasien ? 'Tidak ada hasil pencarian' : (isSSO ? 'Tidak ada pasien rawat inap aktif' : 'Tidak ada data pasien lokal') }}
        </p>
        <p v-if="cariPasien" class="text-xs mt-1" style="color:var(--text-muted)">Coba kata kunci lain atau ketik No. MR langsung</p>
        <p v-else-if="!isSSO" class="text-xs mt-1" style="color:var(--text-muted)">Jalankan seeder untuk mengisi data dev</p>
        <p v-else-if="wardIds.length === 0" class="text-xs mt-1 max-w-xs mx-auto" style="color:var(--text-muted)">
            Ward belum terkonfigurasi di token Keycloak Anda.
        </p>
    </div>
    <!-- Pasien list grouped by ruang — tabel mini -->
    <div v-else class="overflow-x-auto">
        <table class="w-full" style="border-collapse:collapse; min-width:560px">
            <thead>
                <tr style="background:var(--bg-surface-2,var(--bg-main))">
                    <th class="px-5 py-3 text-left" style="color:var(--text-muted); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; border-bottom:1.5px solid var(--border-default)">Pasien</th>
                    <th class="px-5 py-3 text-left" style="color:var(--text-muted); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; border-bottom:1.5px solid var(--border-default)">No. MR</th>
                    <th class="px-5 py-3 text-left" style="color:var(--text-muted); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; border-bottom:1.5px solid var(--border-default)">No. Reg</th>
                    <th class="px-5 py-3 text-left" style="color:var(--text-muted); font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.07em; border-bottom:1.5px solid var(--border-default)">Ruang Asal</th>
                    <th class="px-5 py-3 text-center w-28" style="border-bottom:1.5px solid var(--border-default)"></th>
                </tr>
            </thead>
            <tbody>
                <template v-for="(pasiens, ruang) in pasienPerRuang" :key="ruang">
                    <!-- Group header -->
                    <tr>
                        <td colspan="5" class="px-5 py-2"
                            style="background:var(--bg-main); border-bottom:1px solid var(--border-default)">
                            <div class="flex items-center gap-2">
                                <span style="font-size:12px">🏥</span>
                                <span class="text-[10px] font-bold uppercase tracking-widest" style="color:var(--text-accent)">{{ ruang }}</span>
                                <span class="text-[9px] font-semibold px-1.5 py-0.5 rounded-full"
                                    style="background:rgba(52,152,219,.12); color:#3498DB">{{ pasiens.length }}</span>
                            </div>
                        </td>
                    </tr>
                    <!-- Rows -->
                    <tr v-for="p in pasiens" :key="p.No_MR"
                        @click="pilihPasien(p)"
                        class="cursor-pointer group"
                        style="border-bottom:1px solid var(--border-row,var(--border-default)); transition:background .12s"
                        @mouseenter="e => e.currentTarget.style.background='var(--bg-row-hover,var(--bg-surface))'"
                        @mouseleave="e => e.currentTarget.style.background=''">
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0 text-sm font-bold"
                                    :style="`background:${gColor(p.jenis_kelamin)}18; color:${gColor(p.jenis_kelamin)}`">
                                    {{ gIcon(p.jenis_kelamin) }}
                                </div>
                                <span class="font-semibold text-sm" style="color:var(--text-primary)">{{ p.Nama_Pasien }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="font-mono text-xs" style="color:var(--text-secondary)">{{ p.No_MR }}</span>
                        </td>
                        <td class="px-5 py-3.5">
                            <span v-if="p.No_Reg" class="text-xs font-mono px-2 py-0.5 rounded-lg"
                                style="background:rgba(52,152,219,.1); color:#3498DB">{{ p.No_Reg }}</span>
                            <span v-else class="text-xs italic" style="color:var(--text-muted)">—</span>
                        </td>
                        <td class="px-5 py-3.5">
                            <span class="text-xs" style="color:var(--text-secondary)">{{ p.Nama_RuangM ?? '—' }}</span>
                        </td>
                        <td class="px-5 py-3.5 text-center">
                            <button class="inline-flex items-center gap-1.5 text-xs font-bold px-3.5 py-1.5 rounded-xl transition-all group-hover:scale-105"
                                style="background:rgba(0,168,132,.12); color:#00A884; border:1px solid rgba(0,168,132,.3)">
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
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
</div><!-- end panel pasien -->
</Transition>

<!-- ═══ FILTER BAR ════════════════════════════════════════════════════════════ -->
<div class="rounded-2xl p-5 sm:p-6 space-y-4"
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
    <p class="text-sm mt-1" style="color:var(--text-muted)">Klik "Buat SPRI" atau pilih pasien dari daftar di atas</p>
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
                style="background:var(--bg-sidebar,var(--bg-surface)); border:1px solid var(--border-default); box-shadow:0 24px 64px rgba(0,0,0,.3)">
                <!-- Modal Header -->
                <div class="sticky top-0 z-10" style="background:var(--bg-sidebar,var(--bg-surface)); border-bottom:1px solid var(--border-default)">
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
                        <p class="text-sm" style="color:var(--text-secondary)">Isi data klinis dan kirim ke Admisi</p>
                        <button @click="closeModal" class="p-1.5 rounded-lg transition-all hover:scale-110"
                            style="background:var(--bg-input); color:var(--text-secondary)">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
                <!-- Modal Body -->
                <form @submit.prevent="submitSpri" class="p-6 space-y-6">
                    <!-- 1. Verifikasi Pasien -->
                    <div>
                        <div class="flex items-center gap-2 mb-3">
                            <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold text-white" style="background:#3498DB">1</span>
                            <p class="text-xs font-bold uppercase tracking-wide" style="color:var(--text-accent)">Verifikasi Pasien</p>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-primary)">
                                    No. Medical Record <span style="color:#E74C3C">*</span>
                                </label>
                                <div class="relative">
                                    <input v-model="fmSpri.No_MR" required placeholder="Ketik No. MR..."
                                        class="w-full px-3.5 py-2.5 text-sm rounded-xl outline-none font-mono pr-9"
                                        :style="`border:1.5px solid ${fmSpri.errors.No_MR || lookupError ? '#E74C3C' : lookupResult?.found ? '#00A884' : 'var(--border-default)'}; background:var(--bg-input); color:var(--text-primary)`"/>
                                    <div class="absolute right-3 top-1/2 -translate-y-1/2">
                                        <svg v-if="lookupLoading" class="w-4 h-4 animate-spin" style="color:var(--text-secondary)" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                        </svg>
                                        <span v-else-if="lookupResult?.found" style="color:#00A884" class="text-sm font-bold">✓</span>
                                        <span v-else-if="lookupError" style="color:#E74C3C" class="text-sm font-bold">✕</span>
                                    </div>
                                </div>
                                <p v-if="lookupError" class="text-xs mt-1" style="color:#E74C3C">{{ lookupError }}</p>
                                <p v-else-if="lookupResult?.found" class="text-xs mt-1 font-semibold" style="color:#00A884">✓ {{ lookupResult.nama_pasien }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-primary)">
                                    No. Registrasi <span style="color:#E74C3C">*</span>
                                </label>
                                <select v-if="kunjungans.length > 1" v-model="fmSpri.No_Reg"
                                    @change="onKunjunganChange(fmSpri.No_Reg)"
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
                            <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold text-white" style="background:#5A6B7C">2</span>
                            <p class="text-xs font-bold uppercase tracking-wide" style="color:var(--text-accent)">Data dari Rekam Medis</p>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div v-for="(val, lbl) in { 'Diagnosis': diagnosisExisting, 'Asal Ruang': fmSpri.asal_ruang, 'Dokter DPJP': fmSpri.Dokter }" :key="lbl">
                                <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">{{ lbl }}</label>
                                <input :value="val" readonly
                                    class="w-full px-3.5 py-2.5 text-sm rounded-xl outline-none opacity-60 cursor-not-allowed"
                                    style="border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary)"
                                    placeholder="Terisi otomatis"/>
                            </div>
                        </div>
                    </div>
                    <!-- 3. Data Klinis ICU -->
                    <div>
                        <div class="flex items-center gap-2 mb-3">
                            <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold text-white" style="background:#00A884">3</span>
                            <p class="text-xs font-bold uppercase tracking-wide" style="color:var(--text-accent)">Data Klinis untuk ICU</p>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-primary)">
                                    Diagnosis ICU <span style="color:#E74C3C">*</span>
                                </label>
                                <Icd10Search v-model="fmSpri.Diagnosis" placeholder="Cari kode / keterangan ICD-10"
                                    :required="true" :has-error="!!fmSpri.errors.Diagnosis"/>
                                <p v-if="fmSpri.errors.Diagnosis" class="text-xs mt-1" style="color:#E74C3C">{{ fmSpri.errors.Diagnosis }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-primary)">
                                    Indikasi Rawat ICU <span style="color:#E74C3C">*</span>
                                </label>
                                <input v-model="fmSpri.IndikasiRI" required placeholder="Alasan klinis butuh ICU"
                                    class="w-full px-3.5 py-2.5 text-sm rounded-xl outline-none"
                                    :style="`border:1.5px solid ${fmSpri.errors.IndikasiRI ? '#E74C3C' : 'var(--border-default)'}; background:var(--bg-input); color:var(--text-primary)`"/>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary)">Keterangan Tambahan</label>
                                <textarea v-model="fmSpri.Keterangan" rows="2"
                                    placeholder="Kondisi terkini, catatan penting..."
                                    class="w-full px-3.5 py-2.5 text-sm rounded-xl outline-none resize-none"
                                    style="border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary)"/>
                            </div>
                        </div>
                    </div>
                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-3 pt-2" style="border-top:1px solid var(--border-default)">
                        <button type="button" @click="closeModal"
                            class="text-sm px-5 py-2.5 rounded-xl font-semibold"
                            style="background:var(--bg-input); color:var(--text-secondary)">Batal</button>
                        <button type="submit" :disabled="!canSubmit || fmSpri.processing"
                            class="text-sm px-6 py-2.5 rounded-xl font-bold transition-all duration-150"
                            :style="canSubmit && !fmSpri.processing
                                ? 'background:#00A884; color:#fff; box-shadow:0 4px 14px rgba(0,168,132,.3)'
                                : 'background:var(--bg-input); color:var(--text-muted); opacity:.6'">
                            {{ fmSpri.processing ? 'Menyimpan…' : 'Kirim SPRI' }}
                        </button>
                    </div>
                </form>
            </div>
        </Transition>
    </div>
</Transition>

</AppLayout>
</template>
