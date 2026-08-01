<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    pasienKeluar: { type: Array,  default: () => [] },
    summary:      { type: Object, default: () => ({}) },
    filters:      { type: Object, default: () => ({}) },
    flash:        { type: Object, default: () => ({}) },
})

const logoUrl      = '/images/logo-urip.png'
const doctorImgUrl = '/images/welcome-doctors.svg'

// ── Filter state ──────────────────────────────────────────────────────────────
const localDate = (n = 0) => {
    const d = new Date(); d.setDate(d.getDate() + n)
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`
}
const today     = localDate(0)
const yesterday = localDate(-1)
const week7     = localDate(-6)

const fTglDari  = ref(props.filters.tgl_dari   || today)
const fTglSampai= ref(props.filters.tgl_sampai || today)
const fJenis    = ref(props.filters.jenis  || '')
const fNama     = ref(props.filters.nama   || '')
let searchTimer = null

const applyFilters = () => router.get(route('icu.laporan.keluar'), {
    tgl_dari: fTglDari.value, tgl_sampai: fTglSampai.value,
    jenis: fJenis.value, nama: fNama.value,
}, { preserveState: true, replace: true, preserveScroll: true })

const onNamaInput = () => { clearTimeout(searchTimer); searchTimer = setTimeout(applyFilters, 400) }
const setPreset = (d, s) => { fTglDari.value = d; fTglSampai.value = s; applyFilters() }
const resetFilter = () => { fTglDari.value = today; fTglSampai.value = today; fJenis.value = ''; fNama.value = ''; applyFilters() }

// ── Export PDF ────────────────────────────────────────────────────────────────
const exportPdf = () => {
    const params = new URLSearchParams({
        tgl_dari:   fTglDari.value,
        tgl_sampai: fTglSampai.value,
        jenis:      fJenis.value,
        nama:       fNama.value,
    })
    window.open(route('icu.laporan.keluar.pdf') + '?' + params.toString(), '_blank')
}

// ── Style helpers ─────────────────────────────────────────────────────────────
const gIcon  = (g) => g === 'L' ? '♂' : g === 'P' ? '♀' : '·'
const gColor = (g) => g === 'L' ? '#00A884' : g === 'P' ? '#8E44AD' : '#aaa'

const SRC = {
    external: { bg: 'rgba(0,168,132,.12)', color: '#00A884' },
    internal: { bg: 'rgba(90,107,124,.12)', color: '#5A6B7C' },
}

// ── Sort ──────────────────────────────────────────────────────────────────────
const sortBy  = ref('keluar_at')
const sortDir = ref('desc')
const toggleSort = (col) => {
    if (sortBy.value === col) sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
    else { sortBy.value = col; sortDir.value = 'desc' }
}
const sortIcon = (col) => sortBy.value !== col ? '↕' : sortDir.value === 'asc' ? '↑' : '↓'

const sortedData = computed(() => {
    const arr = [...props.pasienKeluar]
    arr.sort((a, b) => {
        const va = String(a[sortBy.value] ?? '')
        const vb = String(b[sortBy.value] ?? '')
        return sortDir.value === 'asc' ? va.localeCompare(vb) : vb.localeCompare(va)
    })
    return arr
})
</script>

<template>
<AppLayout :flash="flash" page-title="Laporan Pasien Keluar ICU">
<div class="p-6 sm:p-8 space-y-6" style="font-family:'Inter','Plus Jakarta Sans',sans-serif">

    <!-- ── HERO ── -->
    <div class="db-hero">
        <div class="db-hero-copy">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;flex-wrap:wrap">
                <div class="db-hero-logo">
                    <img :src="logoUrl" alt="Logo" style="width:36px;height:36px;object-fit:contain" @error="$event.target.style.display='none'"/>
                </div>
                <div>
                    <p style="color:rgba(255,255,255,.6);font-size:11px;font-weight:500">ICU Command Center</p>
                    <h1 style="color:#fff;font-size:clamp(18px,4vw,28px);font-weight:900;letter-spacing:-.02em">Laporan Pasien Keluar ICU</h1>
                    <p style="color:rgba(255,255,255,.45);font-size:11px">Riwayat pasien yang sudah tidak lagi dirawat di ICU</p>
                </div>
            </div>
            <!-- Tombol Export PDF di hero -->
            <button @click="exportPdf"
                class="flex items-center gap-2 font-bold px-5 py-2.5 rounded-xl transition-all hover:-translate-y-px mt-1"
                style="background:#fff; color:#E74C3C; font-size:13px; box-shadow:0 4px 14px rgba(0,0,0,.12)">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Export PDF
            </button>
        </div>
        <div class="db-hero-vis" aria-hidden="true">
            <div class="db-char">
                <img :src="doctorImgUrl" alt="" style="width:100%;height:100%;object-fit:contain"/>
            </div>
        </div>
    </div>

    <!-- ── KPI CARDS ── -->
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
        <div v-for="c in [
            { val: summary.total,     label: 'Total Keluar',    color: '#5A6B7C' },
            { val: summary.external,  label: 'Booking Ext',     color: '#00A884' },
            { val: summary.internal,  label: 'Booking Internal',color: '#5A6B7C' },
            { val: summary.laki,      label: 'Laki-Laki',       color: '#0EA5E9' },
            { val: summary.perempuan, label: 'Perempuan',       color: '#8E44AD' },
        ]" :key="c.label"
            class="flex items-center gap-3 p-4 rounded-2xl"
            style="background:var(--bg-card); border:1px solid var(--border-default); box-shadow:var(--shadow-card)">
            <div>
                <p class="text-2xl font-black" :style="`color:${c.color}; font-family:'DM Mono',monospace`">{{ c.val }}</p>
                <p class="text-xs font-semibold mt-0.5" style="color:var(--text-secondary)">{{ c.label }}</p>
            </div>
        </div>
    </div>

    <!-- ── FILTER ── -->
    <div class="rounded-2xl p-5 space-y-4" style="background:var(--bg-surface); border:1px solid var(--border-default); box-shadow:var(--shadow-card)">
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide mb-1.5" style="color:var(--text-muted)">Tgl Mulai</label>
                <input v-model="fTglDari" @change="applyFilters" type="date" class="w-full rounded-xl outline-none"
                    style="padding:10px 14px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); font-size:13px"/>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide mb-1.5" style="color:var(--text-muted)">Tgl Selesai</label>
                <input v-model="fTglSampai" @change="applyFilters" type="date" :min="fTglDari" class="w-full rounded-xl outline-none"
                    style="padding:10px 14px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); font-size:13px"/>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide mb-1.5" style="color:var(--text-muted)">Jenis Booking</label>
                <select v-model="fJenis" @change="applyFilters" class="w-full rounded-xl outline-none"
                    style="padding:10px 14px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); font-size:13px">
                    <option value="">Semua Jenis</option>
                    <option value="external">Booking Eksternal</option>
                    <option value="internal">Booking Internal</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide mb-1.5" style="color:var(--text-muted)">Nama Pasien</label>
                <input v-model="fNama" @input="onNamaInput" placeholder="Cari nama pasien..." class="w-full rounded-xl outline-none"
                    style="padding:10px 14px; border:1.5px solid var(--border-default); background:var(--bg-input); color:var(--text-primary); font-size:13px"/>
            </div>
        </div>
        <!-- Preset + Reset -->
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex gap-1 p-1 rounded-xl" style="background:var(--bg-input)">
                <button v-for="p in [{l:'Hari ini',d:today,s:today},{l:'Kemarin',d:yesterday,s:yesterday},{l:'7 Hari',d:week7,s:today}]"
                    :key="p.l" @click="setPreset(p.d,p.s)"
                    class="px-3 py-1 rounded-lg text-xs font-semibold transition-all"
                    :style="fTglDari===p.d&&fTglSampai===p.s ? 'background:#fff;color:#00A884;box-shadow:0 1px 4px rgba(0,0,0,.08)' : 'color:var(--text-muted)'">
                    {{ p.l }}
                </button>
            </div>
            <button v-if="fNama || fJenis" @click="resetFilter"
                class="text-xs font-semibold px-3 py-1.5 rounded-xl flex items-center gap-1.5"
                style="background:rgba(231,76,60,.1); color:#E74C3C; border:1.5px solid rgba(231,76,60,.25)">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                Reset Filter
            </button>
            <!-- Export di filter bar juga -->
            <button @click="exportPdf" class="ml-auto text-xs font-semibold px-4 py-2 rounded-xl flex items-center gap-2 transition-all hover:-translate-y-px"
                style="background:rgba(231,76,60,.1); color:#E74C3C; border:1.5px solid rgba(231,76,60,.25)">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                Export PDF ({{ summary.total }} data)
            </button>
        </div>
    </div>

    <!-- ── EMPTY ── -->
    <div v-if="!sortedData.length" class="card-dark text-center py-16">
        <div class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-4" style="background:var(--bg-input)">
            <svg class="w-7 h-7" style="color:var(--text-muted)" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
        </div>
        <p class="font-semibold" style="color:var(--text-secondary)">Tidak ada data untuk periode ini</p>
        <p class="text-sm mt-1" style="color:var(--text-muted)">Coba ubah rentang tanggal atau reset filter</p>
    </div>

    <!-- ── TABLE (desktop) ── -->
    <div v-else class="card-dark overflow-hidden">
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full" style="border-collapse:collapse; min-width:900px">
                <thead>
                    <tr style="background:var(--bg-surface-2)">
                        <th class="px-4 py-3.5 text-left w-10" style="color:var(--table-th-color); font-size:11px; font-weight:600; text-transform:uppercase; border-bottom:2px solid var(--border-table)">#</th>
                        <th class="px-4 py-3.5 text-left cursor-pointer" style="color:var(--table-th-color); font-size:11px; font-weight:600; text-transform:uppercase; border-bottom:2px solid var(--border-table); min-width:160px" @click="toggleSort('nama_pasien')">
                            Pasien <span style="opacity:.5">{{ sortIcon('nama_pasien') }}</span>
                        </th>
                        <th class="px-4 py-3.5 text-left" style="color:var(--table-th-color); font-size:11px; font-weight:600; text-transform:uppercase; border-bottom:2px solid var(--border-table)">Jenis</th>
                        <th class="px-4 py-3.5 text-left" style="color:var(--table-th-color); font-size:11px; font-weight:600; text-transform:uppercase; border-bottom:2px solid var(--border-table); min-width:180px">Diagnosa</th>
                        <th class="px-4 py-3.5 text-left" style="color:var(--table-th-color); font-size:11px; font-weight:600; text-transform:uppercase; border-bottom:2px solid var(--border-table)">Bed ICU</th>
                        <th class="px-4 py-3.5 text-left cursor-pointer" style="color:var(--table-th-color); font-size:11px; font-weight:600; text-transform:uppercase; border-bottom:2px solid var(--border-table)" @click="toggleSort('masuk_at')">
                            Masuk ICU <span style="opacity:.5">{{ sortIcon('masuk_at') }}</span>
                        </th>
                        <th class="px-4 py-3.5 text-left cursor-pointer" style="color:var(--table-th-color); font-size:11px; font-weight:600; text-transform:uppercase; border-bottom:2px solid var(--border-table)" @click="toggleSort('keluar_at')">
                            Keluar ICU <span style="opacity:.5">{{ sortIcon('keluar_at') }}</span>
                        </th>
                        <th class="px-4 py-3.5 text-left" style="color:var(--table-th-color); font-size:11px; font-weight:600; text-transform:uppercase; border-bottom:2px solid var(--border-table)">Lama Rawat</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, idx) in sortedData" :key="`row-${item.sumber}-${item.id}`"
                        class="group transition-all hover:bg-[var(--bg-row-hover)]"
                        style="border-bottom:1px solid var(--border-row)">
                        <td class="px-4 py-3.5 text-xs font-mono" style="color:var(--text-muted)">{{ idx + 1 }}</td>
                        <td class="px-4 py-3.5">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0"
                                    :style="`background:${gColor(item.jenis_kelamin)}18; color:${gColor(item.jenis_kelamin)}`">
                                    {{ gIcon(item.jenis_kelamin) }}
                                </div>
                                <div>
                                    <p class="text-sm font-semibold" style="color:var(--text-primary)">{{ item.nama_pasien }}</p>
                                    <p class="text-xs font-mono" style="color:var(--text-muted)">{{ item.No_MR }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3.5">
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-full"
                                :style="`background:${SRC[item.sumber]?.bg}; color:${SRC[item.sumber]?.color}`">
                                {{ item.sumber === 'external' ? 'Ext' : 'Int' }}
                            </span>
                        </td>
                        <td class="px-4 py-3.5 text-sm" style="color:var(--text-primary); max-width:200px">
                            <p class="truncate" :title="item.diagnosa">{{ item.diagnosa }}</p>
                        </td>
                        <td class="px-4 py-3.5 text-sm font-semibold" style="color:#00A884">{{ item.nama_bed }}</td>
                        <td class="px-4 py-3.5 text-xs font-mono whitespace-nowrap" style="color:var(--text-secondary)">{{ item.masuk_at }}</td>
                        <td class="px-4 py-3.5 text-xs font-mono whitespace-nowrap" style="color:var(--text-secondary)">{{ item.keluar_at }}</td>
                        <td class="px-4 py-3.5 text-xs font-semibold" style="color:#5A6B7C">{{ item.lama_rawat }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Mobile cards -->
        <div class="block md:hidden divide-y" style="border-color:var(--border-row)">
            <div v-for="item in sortedData" :key="`mob-${item.sumber}-${item.id}`"
                class="p-4 space-y-2">
                <div class="flex items-center gap-2">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0"
                        :style="`background:${gColor(item.jenis_kelamin)}18; color:${gColor(item.jenis_kelamin)}`">
                        {{ gIcon(item.jenis_kelamin) }}
                    </div>
                    <div class="flex-1">
                        <p class="font-semibold text-sm" style="color:var(--text-primary)">{{ item.nama_pasien }}</p>
                        <p class="text-xs font-mono" style="color:var(--text-muted)">{{ item.No_MR }}</p>
                    </div>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                        :style="`background:${SRC[item.sumber]?.bg}; color:${SRC[item.sumber]?.color}`">
                        {{ item.sumber === 'external' ? 'Ext' : 'Int' }}
                    </span>
                </div>
                <p class="text-xs" style="color:var(--text-secondary)"><span style="color:var(--text-muted)">Diagnosa:</span> {{ item.diagnosa }}</p>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <p><span style="color:var(--text-muted)">Bed:</span> <strong style="color:#00A884">{{ item.nama_bed }}</strong></p>
                    <p><span style="color:var(--text-muted)">Lama:</span> {{ item.lama_rawat }}</p>
                    <p><span style="color:var(--text-muted)">Masuk:</span> {{ item.masuk_at }}</p>
                    <p><span style="color:var(--text-muted)">Keluar:</span> {{ item.keluar_at }}</p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="px-5 py-3 border-t text-xs" style="border-color:var(--border-default);color:var(--text-muted)">
            Menampilkan <strong style="color:var(--text-primary)">{{ sortedData.length }}</strong> pasien keluar ICU
        </div>
    </div>

</div>
</AppLayout>
</template>
