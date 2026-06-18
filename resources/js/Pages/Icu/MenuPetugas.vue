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

const isSSO = computed(() => props.authProvider === 'keycloak')

// ── Daftar pasien untuk pilih / buat SPRI ────────────────────────────────────
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

// ── Modal / Form SPRI ────────────────────────────────────────────────────────
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
<div class="p-4 sm:p-6 lg:p-8 space-y-6" style="font-family:'Inter','Plus Jakarta Sans',sans-serif">

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
            <h1 class="text-xl sm:text-2xl font-bold tracking-tight" style="color:var(--text-primary)">Permintaan Rawat ICU</h1>
            <p class="text-xs sm:text-sm" style="color:var(--text-secondary)">
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
    <div class="flex items-center gap-2 w-full sm:w-auto">
        <button v-if="!isSSO && (canBuatSpriInternal || isAdmin)" @click="openModal('spri')"
            class="w-full sm:w-auto flex items-center justify-center gap-2 font-bold px-5 py-2.5 rounded-xl transition-all duration-150 hover:-translate-y-px"
            style="background:#00A884; color:#fff; font-size:14px; box-shadow:0 4px 14px rgba(0,168,132,0.3)">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            Buat SPRI Manual
        </button>
    </div>
</div>

<div class="grid grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-4">
    <button v-for="c in CARDS" :key="c.key"
        @click="fStatus = c.key; applyFilters()"
        class="relative flex flex-col gap-1 sm:gap-2 p-4 sm:p-5 rounded-2xl text-left transition-all duration-200 hover:-translate-y-1"
        style="background:var(--bg-card); border:1px solid var(--border-default); box-shadow:var(--shadow-card); min-height:90px"
        :style="fStatus === c.key ? `border:2.5px solid ${c.color}; box-shadow:0 0 0 3px ${c.color}18` : ''">
        <span class="absolute left-0 top-4 bottom-4 w-1 rounded-r-full"
            :style="`background:${c.color}; opacity:${fStatus===c.key?'1':'0.35'}`"></span>
        <span class="text-2xl sm:text-3xl font-bold tracking-tight" :style="`color:${c.color}`">{{ c.val }}</span>
        <span class="text-[11px] sm:text-xs font-medium leading-tight" style="color:var(--text-secondary)">{{ c.label }}</span>
    </button>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
    
    <div>
        <div v-if="isSSO" class="rounded-2xl overflow-hidden flex flex-col h-[600px] lg:h-[800px]"
            style="background:var(--bg-card); border:1px solid var(--border-default); box-shadow:var(--shadow-card)">

            <div class="px-4 py-4 flex flex-col gap-3" style="border-bottom:1px solid var(--border-default)">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0" style="background:rgba(0,168,132,.15)">
                        <svg class="w-4 h-4" style="color:#00A884" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <p class="text-sm font-bold truncate" style="color:var(--text-primary)">
                                {{ isIgdUser ? 'Pasien Aktif — IGD' : `Pasien Bangsal` }} <strong style="color:#00A884">{{ wardIds.join(', ') || '-' }}</strong>
                            </p>
                            <!-- <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full" style="background:rgba(0,168,132,.12);color:#00A884">Klik untuk request</span> -->
                        </div>
                    </div>
                </div>
                <div class="relative w-full">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4" style="color:var(--text-muted)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input v-model="cariPasien" placeholder="Cari nama / No. MR..."
                        class="w-full text-sm pl-9 pr-9 py-2.5 rounded-xl outline-none"
                        style="border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary)"/>
                </div>
            </div>

            <div class="overflow-y-auto flex-1 p-2 sm:p-3 bg-gray-50/50">
                <div v-if="Object.keys(pasienPerRuang).length === 0" class="py-14 text-center">
                    <div class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-3" style="background:var(--bg-input)">
                        <svg class="w-7 h-7" style="color:var(--text-muted)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <p class="font-semibold" style="color:var(--text-secondary)">Semua pasien sudah diproses</p>
                    <p class="text-sm mt-1" style="color:var(--text-muted)">Atau tidak ada data aktif</p>
                </div>

                <template v-for="(pasiens, ruang) in pasienPerRuang" :key="ruang">
                    <div v-if="pasiens.length > 0" class="mb-4">
                        <div class="px-3 py-2 sticky top-0 z-10 rounded-lg flex items-center gap-2 mb-2 backdrop-blur-md" 
                            style="background:rgba(255,255,255,0.9); border:1px solid var(--border-default)">
                            <span>🏥</span>
                            <span class="text-[10px] font-bold uppercase tracking-widest" style="color:var(--text-accent)">{{ ruang }}</span>
                            <span class="text-[9px] font-semibold px-1.5 py-0.5 rounded-full" style="background:rgba(0,168,132,.1);color:#00A884">{{ pasiens.length }}</span>
                        </div>
                        
                        <div class="flex flex-col gap-2">
                            <div v-for="p in pasiens" :key="p.No_MR + (p.No_Reg ?? '')"
                                @click="pilihPasien(p)"
                                class="cursor-pointer group flex items-center gap-3 p-3 sm:p-4 rounded-xl transition-all"
                                style="background:var(--bg-surface); border:1px solid var(--border-default)"
                                @mouseenter="e => { e.currentTarget.style.transform='translateY(-1px)'; e.currentTarget.style.borderColor='rgba(0,168,132,0.4)'; e.currentTarget.style.boxShadow='0 4px 12px rgba(0,0,0,.04)' }"
                                @mouseleave="e => { e.currentTarget.style.transform=''; e.currentTarget.style.borderColor='var(--border-default)'; e.currentTarget.style.boxShadow='' }">
                                
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 text-base font-bold"
                                    :style="`background:${gColor(p.jenis_kelamin)}18; color:${gColor(p.jenis_kelamin)}`">
                                    {{ gIcon(p.jenis_kelamin) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-bold text-sm truncate" style="color:var(--text-primary)">{{ p.Nama_Pasien }}</p>
                                    <p class="font-mono text-[10px] sm:text-[11px] mt-0.5 text-gray-500">
                                        {{ p.No_MR }} 
                                        <span v-if="p.No_Reg" class="ml-1 px-1.5 py-0.5 rounded text-[#3498DB] bg-blue-50">· {{ p.No_Reg }}</span>
                                    </p>
                                </div>
                                <div class="text-right flex-shrink-0 max-w-[35%] sm:max-w-[40%]">
                                    <p class="text-[11px] font-semibold truncate" style="color:var(--text-primary)">{{ p.Dokter || '—' }}</p>
                                    <p class="text-[10px] text-gray-500 truncate mt-0.5">{{ p.Nama_RuangM || '—' }}</p>
                                </div>
                                
                                <div class="opacity-0 group-hover:opacity-100 transition-opacity ml-1 flex-shrink-0">
                                    <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
        <div v-else class="text-center py-10" style="color:var(--text-secondary)">
            Mode Lokal: Silakan klik tombol "Buat SPRI Manual" di atas untuk mencari pasien.
        </div>
    </div>

    <div class="flex flex-col space-y-4 lg:space-y-6">
        
        <div class="rounded-2xl p-4 sm:p-5 space-y-4"
            style="background:var(--bg-surface); border:1px solid var(--border-default); box-shadow:var(--shadow-card)">
            <p class="text-sm font-bold" style="color:var(--text-primary)">Riwayat Permintaan Rawat ICU</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 sm:gap-4">
                <div>
                    <select v-model="fStatus" @change="applyFilters" class="w-full rounded-xl outline-none"
                        style="padding:8px 12px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); font-size:12px">
                        <option v-for="o in statusOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                    </select>
                </div>
                <div>
                    <input v-model="fNama" @input="onNamaInput" placeholder="Cari Riwayat..."
                        class="w-full rounded-xl outline-none"
                        style="padding:8px 12px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); font-size:12px"/>
                </div>
                <div class="flex gap-2">
                    <button v-for="col in [{key:'created_at',label:'Waktu'},{key:'status',label:'Status'}]" :key="col.key" @click="toggleSort(col.key)"
                        class="flex-1 text-[11px] font-semibold px-2 py-2 rounded-xl transition-all"
                        :style="sortBy === col.key ? 'background:rgba(0,168,132,.15); color:#00A884' : 'background:var(--bg-input); color:var(--text-secondary)'">
                        {{ col.label }} {{ sortIcon(col.key) }}
                    </button>
                    <button v-if="fStatus || fNama || fTgl" @click="resetFilter"
                        class="px-3 rounded-xl bg-red-50 text-red-500 font-bold flex items-center justify-center">
                        ✕
                    </button>
                </div>
            </div>
        </div>

        <div class="rounded-2xl overflow-hidden flex flex-col h-[600px] lg:h-[650px]"
            style="background:var(--bg-card); border:1px solid var(--border-default); box-shadow:var(--shadow-card)">
            
            <div v-if="!spriList.length" class="text-center py-16 m-auto">
                <div class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-4" style="background:var(--bg-input)">
                    <svg class="w-7 h-7" style="color:var(--text-muted)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <p class="font-semibold" style="color:var(--text-secondary)">Belum ada SPRI</p>
                <p class="text-xs mt-1" style="color:var(--text-muted)">Pilih pasien di daftar kiri untuk request.</p>
            </div>

            <div v-else class="overflow-y-auto flex-1 divide-y" style="border-color:var(--border-default)">
                <div v-for="item in spriList" :key="`hist-${item.id}`"
                    class="p-4 sm:p-5 relative transition-colors hover:bg-gray-50/50"
                    style="border-left:4px solid transparent"
                    :style="`border-left-color:${ss(item.status).dot}`">
                    
                    <div class="flex justify-between items-start mb-3 gap-2">
                        <span class="inline-flex items-center gap-1.5 text-[10px] sm:text-xs font-bold px-2.5 py-1 rounded-xl"
                            :style="`background:${ss(item.status).bg}; color:${ss(item.status).color}`">
                            <span class="w-1.5 h-1.5 rounded-full" :style="`background:${ss(item.status).color}`"></span>
                            {{ item.status_label }}
                        </span>
                        <span class="text-[10px] sm:text-xs font-mono" style="color:var(--text-muted)">{{ item.created_at_fmt }}</span>
                    </div>
                    
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 text-sm font-bold"
                            :style="`background:${gColor(item.jenis_kelamin)}18; color:${gColor(item.jenis_kelamin)}`">
                            {{ gIcon(item.jenis_kelamin) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-bold text-sm truncate" style="color:var(--text-primary)">{{ item.nama_pasien }}</p>
                            <p class="font-mono text-[10px] sm:text-[11px]" style="color:var(--text-muted)">{{ item.No_MR }} · {{ item.No_Reg }}</p>
                        </div>
                    </div>

                    <div class="flex items-center justify-between mb-4 px-1">
                        <div v-for="(step, idx) in 
                            [
                                {id: 'pending_admisi', label: 'Admisi'},
                                {id: 'pending_icu', label: 'ICU'},
                                {id: 'bed_verified', label: 'Selesai'}
                            ]" :key="step.id" class="flex flex-col items-center flex-1 relative">
                            
                            <div v-if="idx < 2" class="absolute top-[9px] left-1/2 w-full h-[2px]"
                                :style="`background: ${ ( (item.status === 'pending_icu' && idx === 0) || item.status === 'bed_verified' ) ? '#00A884' : '#e2e8f0' }`"></div>
                            
                            <div class="w-5 h-5 rounded-full flex items-center justify-center text-[9px] font-bold z-10 transition-all"
                                :style="`background: ${ item.status === step.id ? ss(item.status).color : ( (item.status === 'bed_verified') || (item.status === 'pending_icu' && idx === 0) ? '#00A884' : '#cbd5e1' ) }; color: white`">
                                {{ idx + 1 }}
                            </div>
                            <span class="text-[9px] mt-1 font-semibold" :style="`color: ${ item.status === step.id ? ss(item.status).color : '#94a3b8' }`">{{ step.label }}</span>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2 mt-2 bg-gray-50/50 p-3 rounded-xl border border-gray-100">
                        <div class="flex flex-col sm:flex-row gap-2 sm:gap-4">
                            <div class="flex-1 min-w-0">
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-0.5">Diagnosa & Indikasi</p>
                                <p class="text-xs text-gray-800 truncate font-medium" :title="item.Diagnosis">{{ item.Diagnosis ?? '—' }}</p>
                                <p class="text-xs text-gray-600 truncate mt-0.5" :title="item.IndikasiRI">{{ item.IndikasiRI ?? '—' }}</p>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-0.5">Asal / DPJP</p>
                                <p class="text-xs text-gray-800 truncate font-medium">{{ item.asal_ruang ?? '—' }}</p>
                                <p class="text-xs text-gray-600 truncate mt-0.5">{{ item.Dokter ?? '—' }}</p>
                            </div>
                        </div>

                        <div v-if="item.nama_bed || item.catatan_admisi || item.alasan_tolak" class="pt-2 mt-1 border-t border-gray-200/60">
                            <div v-if="item.nama_bed" class="mb-1">
                                <span class="inline-block text-[10px] font-bold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-lg border border-emerald-200">
                                    🏥 {{ item.nama_bed }}{{ item.kebutuhan_bed ? ' · ' + item.kebutuhan_bed : '' }}
                                </span>
                            </div>
                            <p v-if="item.catatan_admisi" class="text-xs text-gray-600"><span class="font-semibold text-gray-500">Catatan:</span> {{ item.catatan_admisi }}</p>
                            <p v-if="item.alasan_tolak" class="text-xs font-semibold text-red-500"><span class="text-red-400">Tolak:</span> {{ item.alasan_tolak }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div><Transition enter-active-class="transition-all duration-200" enter-from-class="opacity-0" leave-to-class="opacity-0">
    <div v-if="modal.open" class="fixed inset-0 z-50 flex items-center justify-center p-4"
        style="background:rgba(0,0,0,.6); backdrop-filter:blur(4px)"
        @click.self="closeModal">
        <Transition enter-active-class="transition-all duration-200" enter-from-class="opacity-0 scale-95" leave-to-class="opacity-0 scale-95">
            <div v-if="modal.type === 'spri'" class="w-full max-w-2xl max-h-[92vh] overflow-y-auto rounded-2xl"
                style="background:var(--bg-sidebar,#ffffff); border:1px solid var(--border-default,#e2e8f0); box-shadow:0 24px 64px rgba(0,0,0,.3)">
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
                <form @submit.prevent="submitSpri" class="p-6 space-y-6" style="color:var(--text-primary, #1a1a1a)">
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
                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full flex-shrink-0 hidden sm:block"
                            style="background:rgba(0,168,132,.15); color:#00A884">SSO Verified ✓</span>
                    </div>
                    <div>
                        <div class="flex items-center gap-2 mb-3">
                            <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold text-white flex-shrink-0" style="background:#3498DB">1</span>
                            <p class="text-xs font-bold uppercase tracking-wide" style="color:#3498DB">Verifikasi Pasien</p>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-primary,#1a1a1a)">
                                    No. Medical Record <span style="color:#E74C3C">*</span>
                                </label>
                                <div v-if="isSSO" class="relative">
                                    <input :value="fmSpri.No_MR" readonly tabindex="-1"
                                        class="w-full px-3.5 py-2.5 text-sm rounded-xl outline-none font-mono cursor-not-allowed pr-9"
                                        style="border:1.5px solid rgba(0,168,132,.4); background:rgba(0,168,132,.05); color:#1a1a1a; opacity:1"/>
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm font-bold" style="color:#00A884">✓</span>
                                </div>
                                <p v-if="isSSO && lookupResult?.found" class="text-xs mt-1 font-semibold" style="color:#00A884">
                                    ✓ {{ lookupResult.nama_pasien }}
                                </p>
                                <div v-else class="relative">
                                    <input v-model="fmSpri.No_MR" required placeholder="Ketik No. MR..."
                                        class="w-full px-3.5 py-2.5 text-sm rounded-xl outline-none font-mono pr-9"
                                        :style="`border:1.5px solid ${fmSpri.errors.No_MR || lookupError ? '#E74C3C' : lookupResult?.found ? '#00A884' : 'var(--border-default,#e2e8f0)'}; background:var(--bg-input,#f8fafc); color:var(--text-primary,#1a1a1a)`"/>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-primary,#1a1a1a)">
                                    No. Registrasi <span style="color:#E74C3C">*</span>
                                </label>
                                <div v-if="isSSO && fmSpri.No_Reg" class="relative">
                                    <input :value="fmSpri.No_Reg" readonly tabindex="-1"
                                        class="w-full px-3.5 py-2.5 text-sm rounded-xl outline-none font-mono cursor-not-allowed"
                                        style="border:1.5px solid rgba(0,168,132,.4); background:rgba(0,168,132,.05); color:#1a1a1a; opacity:1"/>
                                </div>
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
                    <div>
                        <div class="flex items-center gap-2 mb-3">
                            <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold text-white flex-shrink-0" style="background:#5A6B7C">2</span>
                            <p class="text-xs font-bold uppercase tracking-wide" style="color:#5A6B7C">Data dari Rekam Medis</p>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary,#555)">Diagnosis (RM)</label>
                                <input :value="diagnosisExisting || '—'" readonly class="w-full px-3.5 py-2.5 text-sm rounded-xl outline-none cursor-not-allowed" :style="isSSO && diagnosisExisting ? 'border:1.5px solid rgba(0,168,132,.35); background:rgba(0,168,132,.05); color:#1a1a1a' : 'border:1.5px solid #e2e8f0; background:#f8fafc; color:#64748b; opacity:1'"/>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary,#555)">Asal Ruang</label>
                                <input :value="fmSpri.asal_ruang || '—'" readonly class="w-full px-3.5 py-2.5 text-sm rounded-xl outline-none cursor-not-allowed" :style="isSSO && fmSpri.asal_ruang ? 'border:1.5px solid rgba(0,168,132,.35); background:rgba(0,168,132,.05); color:#1a1a1a' : 'border:1.5px solid #e2e8f0; background:#f8fafc; color:#64748b; opacity:1'"/>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary,#555)">Dokter DPJP</label>
                                <input :value="fmSpri.Dokter || '—'" readonly class="w-full px-3.5 py-2.5 text-sm rounded-xl outline-none cursor-not-allowed" :style="isSSO && fmSpri.Dokter ? 'border:1.5px solid rgba(0,168,132,.35); background:rgba(0,168,132,.05); color:#1a1a1a' : 'border:1.5px solid #e2e8f0; background:#f8fafc; color:#64748b; opacity:1'"/>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary,#555)">Jaminan</label>
                                <input :value="jaminanExisting || '—'" readonly class="w-full px-3.5 py-2.5 text-sm rounded-xl outline-none cursor-not-allowed" :style="isSSO && jaminanExisting ? 'border:1.5px solid rgba(0,168,132,.35); background:rgba(0,168,132,.05); color:#1a1a1a' : 'border:1.5px solid #e2e8f0; background:#f8fafc; color:#64748b; opacity:1'"/>
                            </div>
                        </div>

                    </div>
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
                                <Icd10Search v-model="fmSpri.Diagnosis" placeholder="Cari kode / keterangan ICD-10" :required="true" :has-error="!!fmSpri.errors.Diagnosis"/>
                                <p v-if="fmSpri.errors.Diagnosis" class="text-xs mt-1" style="color:#E74C3C">{{ fmSpri.errors.Diagnosis }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-primary,#1a1a1a)">
                                    Indikasi Rawat ICU <span style="color:#E74C3C">*</span>
                                </label>
                                <input v-model="fmSpri.IndikasiRI" required placeholder="Alasan klinis butuh ICU" class="w-full px-3.5 py-2.5 text-sm rounded-xl outline-none" :style="`border:1.5px solid ${fmSpri.errors.IndikasiRI ? '#E74C3C' : 'var(--border-default,#e2e8f0)'}; background:var(--bg-input,#f8fafc); color:var(--text-primary,#1a1a1a)`"/>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold mb-1.5" style="color:var(--text-secondary,#555)">Keterangan Tambahan</label>
                                <textarea v-model="fmSpri.Keterangan" rows="2" placeholder="Kondisi terkini, catatan penting..." class="w-full px-3.5 py-2.5 text-sm rounded-xl outline-none resize-none" style="border:1.5px solid var(--border-default,#e2e8f0); background:var(--bg-input,#f8fafc); color:var(--text-primary,#1a1a1a)"/>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center justify-end gap-3 pt-4" style="border-top:1px solid var(--border-default,#e2e8f0)">
                        <button type="button" @click="closeModal" class="text-sm px-5 py-2.5 rounded-xl font-semibold" style="background:#f1f5f9; color:#475569; border:1px solid #e2e8f0">Batal</button>
                        <button type="submit" :disabled="!canSubmit || fmSpri.processing" class="text-sm px-6 py-2.5 rounded-xl font-bold transition-all duration-150" :style="canSubmit && !fmSpri.processing ? 'background:#00A884; color:#fff; box-shadow:0 4px 14px rgba(0,168,132,.3)' : 'background:#f1f5f9; color:#94a3b8; opacity:.7'">
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