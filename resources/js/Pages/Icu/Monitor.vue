<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'

const props = defineProps({
    bedData: { type: Array,  default: () => [] },
    antrian: { type: Array,  default: () => [] },
    summary: { type: Object, default: () => ({}) },
})

// ── Clock ──────────────────────────────────────────────────────────────────────
const now = ref(new Date())
let clockTimer = null
const timeStr = computed(() =>
    now.value.toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit', second:'2-digit' }))
const dateStr = computed(() =>
    now.value.toLocaleDateString('id-ID', { weekday:'long', day:'numeric', month:'long', year:'numeric' }))

// ── Live data via polling ──────────────────────────────────────────────────────
const antrian    = ref(props.antrian)
const summary    = ref(props.summary)
const lastUpdate = ref('—')
let pollTimer    = null
let tickerTimer  = null

const fetchData = async () => {
    try {
        const r = await fetch('/monitor/data', { headers: { Accept: 'application/json' } })
        const d = await r.json()
        antrian.value     = d.antrian
        summary.value     = d.summary
        lastUpdate.value  = d.ts
    } catch(e) { console.warn('[Monitor] polling error', e) }
}

// ── Ticker / scroll index ──────────────────────────────────────────────────────
const tickerIdx = ref(0)
const TICKER_INTERVAL = 6000   // 6 detik per baris

const activeRow  = computed(() => antrian.value[tickerIdx.value] ?? null)
const totalRows  = computed(() => antrian.value.length)

// ── Status helpers ─────────────────────────────────────────────────────────────
const STATUS = {
    pending_icu:    { bg:'#FEF3C7', color:'#D97706', label:'Menunggu ICU' },
    pending_admisi: { bg:'#FEF3C7', color:'#D97706', label:'Menunggu Admisi' },
    waiting_list:   { bg:'#FFF7ED', color:'#EA580C', label:'Waiting List' },
    bed_confirmed:  { bg:'#D1FAE5', color:'#059669', label:'Bed Dikonfirmasi' },
    bed_verified:   { bg:'#D1FAE5', color:'#059669', label:'Bed Terverifikasi' },
    admisi_verified:{ bg:'#DCFCE7', color:'#16A34A', label:'Terverifikasi' },
    ditolak:        { bg:'#FEE2E2', color:'#DC2626', label:'Ditolak' },
}
const ss = (s) => STATUS[s] ?? { bg:'#F1F5F9', color:'#64748B', label: s }

const gIcon  = (g) => g === 'L' ? '♂' : g === 'P' ? '♀' : '—'
const gColor = (g) => g === 'L' ? '#2563EB' : g === 'P' ? '#7C3AED' : '#64748B'

// Duration helper — hitung selisih waktu dari created_at
const durasi = (createdStr) => {
    if (!createdStr) return ''
    const parts = createdStr.split(' ')
    if (parts.length < 2) return ''
    const [d, t] = parts
    const [dd, mm, yyyy] = d.split('/')
    const dt = new Date(`${yyyy}-${mm}-${dd}T${t}:00`)
    const diff = Math.floor((now.value - dt) / 60000) // menit
    if (diff < 1)   return 'Baru saja'
    if (diff < 60)  return `${diff} mnt`
    if (diff < 1440) return `${Math.floor(diff/60)} jam ${diff%60} mnt`
    return `${Math.floor(diff/1440)} hari`
}

onMounted(() => {
    clockTimer  = setInterval(() => now.value = new Date(), 1000)
    pollTimer   = setInterval(fetchData, 30000)
    tickerTimer = setInterval(() => {
        if (antrian.value.length > 0) {
            tickerIdx.value = (tickerIdx.value + 1) % antrian.value.length
        }
    }, TICKER_INTERVAL)
})
onUnmounted(() => {
    clearInterval(clockTimer)
    clearInterval(pollTimer)
    clearInterval(tickerTimer)
})
</script>

<template>
<div class="mon-wrap">

  <!-- ══ HEADER ═══════════════════════════════════════════════════════════════ -->
  <header class="mon-header">
    <div class="flex items-center gap-4">
      <div class="mon-logo">
        <svg class="w-7 h-7" fill="white" viewBox="0 0 24 24">
          <path d="M19 8H15V4H9v4H5v6h4v4h6v-4h4V8z"/>
        </svg>
      </div>
      <div>
        <p class="mon-sub">ICU Bed Management · RS Urip Sumoharjo</p>
        <h1 class="mon-title">Monitoring Antrian ICU</h1>
      </div>
    </div>

    <!-- Summary stats -->
    <div class="mon-stats">
      <div class="mon-stat" style="--c:#059669">
        <span class="mon-stat-num">{{ summary.kosong ?? 0 }}</span>
        <span class="mon-stat-lbl">Bed Kosong</span>
      </div>
      <div class="mon-stat" style="--c:#DC2626">
        <span class="mon-stat-num">{{ summary.terisi ?? 0 }}</span>
        <span class="mon-stat-lbl">Terisi</span>
      </div>
      <div class="mon-stat" style="--c:#D97706">
        <span class="mon-stat-num">{{ summary.booking ?? 0 }}</span>
        <span class="mon-stat-lbl">Booking</span>
      </div>
      <div class="mon-stat" style="--c:#2563EB">
        <span class="mon-stat-num">{{ totalRows }}</span>
        <span class="mon-stat-lbl">Antrian Aktif</span>
      </div>
    </div>

    <!-- Clock -->
    <div class="mon-clock-wrap">
      <p class="mon-clock">{{ timeStr }}</p>
      <p class="mon-date">{{ dateStr }}</p>
    </div>
  </header>

  <!-- ══ FEATURED CARD (antrian yang sedang berjalan) ══════════════════════ -->
  <div class="mon-featured-wrap">
    <Transition name="slide-up" mode="out-in">
      <div v-if="activeRow" :key="activeRow.id" class="mon-featured">
        <!-- Queue number -->
        <div class="mon-queue-num">
          <p class="mon-queue-label">NOMOR ANTRIAN</p>
          <p class="mon-queue-val">#{{ (tickerIdx + 1).toString().padStart(2,'0') }}</p>
          <p class="mon-queue-of">dari {{ totalRows }}</p>
        </div>
        <!-- Patient info -->
        <div class="mon-featured-patient">
          <div class="mon-featured-av" :style="`background:${gColor(activeRow.jenis_kelamin)}15;color:${gColor(activeRow.jenis_kelamin)}`">
            {{ gIcon(activeRow.jenis_kelamin) }}
          </div>
          <div>
            <p class="mon-featured-name">{{ activeRow.nama_pasien }}</p>
            <p class="mon-featured-mr">No. MR: {{ activeRow.No_MR ?? '—' }}</p>
            <div class="flex items-center gap-2 mt-2 flex-wrap">
              <span class="mon-featured-status" :style="`background:${ss(activeRow.status).bg};color:${ss(activeRow.status).color}`">
                {{ ss(activeRow.status).label }}
              </span>
              <span class="mon-source-tag" :style="activeRow.sumber==='external' ? 'background:#D1FAE5;color:#059669' : 'background:#EDE9FE;color:#7C3AED'">
                {{ activeRow.sumber === 'external' ? 'Booking External' : 'Booking Internal' }}
              </span>
            </div>
          </div>
        </div>
        <!-- Details -->
        <div class="mon-featured-details">
          <div class="mon-detail-item">
            <p class="mon-detail-lbl">Diagnosa</p>
            <p class="mon-detail-val">{{ activeRow.diagnosa ?? '—' }}</p>
          </div>
          <div class="mon-detail-item">
            <p class="mon-detail-lbl">Asal Rujukan</p>
            <p class="mon-detail-val">{{ activeRow.asal ?? '—' }}</p>
          </div>
          <div class="mon-detail-item">
            <p class="mon-detail-lbl">Jenis Bed</p>
            <p class="mon-detail-val" :style="activeRow.nama_bed ? 'color:#059669;font-weight:800' : ''">
              {{ activeRow.nama_bed ?? activeRow.kebutuhan_bed ?? '—' }}
            </p>
          </div>
          <div class="mon-detail-item">
            <p class="mon-detail-lbl">Waktu Booking</p>
            <p class="mon-detail-val">{{ activeRow.created_at_fmt ?? '—' }}</p>
          </div>
          <div class="mon-detail-item">
            <p class="mon-detail-lbl">Lama Menunggu</p>
            <p class="mon-detail-val mon-durasi">{{ durasi(activeRow.created_at_fmt) }}</p>
          </div>
          <div v-if="activeRow.waiting_estimasi_fmt" class="mon-detail-item mon-detail-item--warning">
            <p class="mon-detail-lbl">Estimasi Bed Siap</p>
            <p class="mon-detail-val" style="color:#D97706;font-weight:800">{{ activeRow.waiting_estimasi_fmt }}</p>
          </div>
        </div>
        <!-- Progress bar ticker -->
        <div class="mon-progress-bar">
          <div class="mon-progress-fill" :style="`animation-duration:${TICKER_INTERVAL}ms`"></div>
        </div>
      </div>
      <!-- Empty state -->
      <div v-else class="mon-featured mon-featured--empty">
        <svg class="w-16 h-16 mb-4" style="color:#D1D5DB" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
        </svg>
        <p style="font-size:20px;font-weight:700;color:#9CA3AF">Tidak ada antrian saat ini</p>
        <p style="font-size:14px;color:#D1D5DB;margin-top:8px">Semua pasien sudah diproses atau belum ada booking baru</p>
      </div>
    </Transition>
  </div>

  <!-- ══ ANTRIAN TABLE (semua baris, berjalan) ═══════════════════════════════ -->
  <div class="mon-table-section">
    <div class="mon-table-header">
      <p class="mon-table-title">
        <span class="mon-live-dot"></span>
        Seluruh Antrian Aktif
      </p>
      <p class="mon-table-refresh">Diperbarui: {{ lastUpdate }}</p>
    </div>

    <div v-if="antrian.length === 0" class="mon-table-empty">
      Tidak ada antrian aktif
    </div>

    <div v-else class="mon-ticker-viewport">
      <div class="mon-ticker-track">
        <!-- Duplikasi untuk infinite scroll effect -->
        <div v-for="(item, idx) in [...antrian, ...antrian]" :key="`${item.id}-${idx}`"
          class="mon-ticker-card"
          :class="{ 'mon-ticker-card--active': antrian[tickerIdx]?.id === item.id && idx < antrian.length }"
          :style="item.status === 'waiting_list' ? 'border-color:#FCD34D;background:#FFFBEB' : ''">
          <!-- Row number -->
          <span class="mon-ticker-num">{{ (idx % antrian.length) + 1 }}</span>
          <!-- Gender + name -->
          <div class="mon-ticker-patient">
            <span class="mon-ticker-gender" :style="`color:${gColor(item.jenis_kelamin)}`">{{ gIcon(item.jenis_kelamin) }}</span>
            <div>
              <p class="mon-ticker-name">{{ item.nama_pasien }}</p>
              <p class="mon-ticker-mr">{{ item.No_MR ?? '—' }}</p>
            </div>
          </div>
          <!-- Source -->
          <span class="mon-ticker-src" :style="item.sumber==='external' ? 'background:#D1FAE5;color:#059669' : 'background:#EDE9FE;color:#7C3AED'">
            {{ item.sumber === 'external' ? 'External' : 'Internal' }}
          </span>
          <!-- Diagnosa -->
          <p class="mon-ticker-diag">{{ item.diagnosa ?? '—' }}</p>
          <!-- Bed -->
          <p class="mon-ticker-bed" :style="item.nama_bed ? 'color:#059669;font-weight:700' : 'color:#9CA3AF'">
            {{ item.nama_bed ?? item.kebutuhan_bed ?? '—' }}
          </p>
          <!-- Status -->
          <span class="mon-ticker-status" :style="`background:${ss(item.status).bg};color:${ss(item.status).color}`">
            {{ ss(item.status).label }}
          </span>
          <!-- Durasi -->
          <p class="mon-ticker-dur">{{ durasi(item.created_at_fmt) }}</p>
          <!-- Waiting estimasi -->
          <p v-if="item.waiting_estimasi_fmt" class="mon-ticker-est">
            ⏰ {{ item.waiting_estimasi_fmt }}
          </p>
          <p v-else class="mon-ticker-est" style="color:transparent">—</p>
        </div>
      </div>
    </div>
  </div>

  <!-- ══ FOOTER ════════════════════════════════════════════════════════════════ -->
  <footer class="mon-footer">
    <span>ICU Bed Management System · RS Urip Sumoharjo</span>
    <span class="flex items-center gap-1.5">
      <span class="mon-live-dot"></span>
      Auto-refresh 30 detik
    </span>
  </footer>

</div>
</template>

<style scoped>
/* ══ Base ══════════════════════════════════════════════════════════════════ */
.mon-wrap {
  min-height: 100vh; max-height: 100vh;
  background: #F0F4F8;
  font-family: 'Inter', 'Plus Jakarta Sans', sans-serif;
  display: flex; flex-direction: column; overflow: hidden;
}

/* ══ Header ════════════════════════════════════════════════════════════════ */
.mon-header {
  display: flex; align-items: center; justify-content: space-between;
  flex-wrap: wrap; gap: 12px;
  padding: 14px 28px;
  background: linear-gradient(135deg, #00A884 0%, #007a61 100%);
  box-shadow: 0 4px 20px rgba(0,168,132,.25);
  flex-shrink: 0;
}
.mon-logo {
  width: 48px; height: 48px; border-radius: 14px;
  background: rgba(255,255,255,.2); border: 1.5px solid rgba(255,255,255,.3);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.mon-sub   { font-size: 11px; color: rgba(255,255,255,.75); font-weight: 600; }
.mon-title { font-size: 22px; font-weight: 900; color: #fff; letter-spacing: -.02em; }

/* Stats */
.mon-stats { display: flex; gap: 10px; flex-wrap: wrap; }
.mon-stat {
  display: flex; flex-direction: column; align-items: center;
  padding: 8px 18px; border-radius: 12px;
  background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.2);
  min-width: 80px;
}
.mon-stat-num { font-size: 26px; font-weight: 900; color: var(--c, #fff); font-family: 'DM Mono', monospace; line-height: 1; }
.mon-stat-lbl { font-size: 10px; font-weight: 600; color: rgba(255,255,255,.8); margin-top: 2px; white-space: nowrap; }

/* Clock */
.mon-clock-wrap { text-align: right; }
.mon-clock { font-size: 36px; font-weight: 900; color: #fff; font-family: 'DM Mono', monospace; line-height: 1; }
.mon-date  { font-size: 11px; color: rgba(255,255,255,.75); margin-top: 3px; }

/* ══ Featured card ═════════════════════════════════════════════════════════ */
.mon-featured-wrap {
  padding: 20px 28px 0;
  flex-shrink: 0;
}
.mon-featured {
  background: #fff;
  border-radius: 20px;
  padding: 24px 28px;
  box-shadow: 0 8px 40px rgba(0,0,0,.08);
  border: 2px solid #E2E8F0;
  display: grid;
  grid-template-columns: 140px 1fr auto;
  gap: 24px;
  align-items: center;
  position: relative;
  overflow: hidden;
}
.mon-featured--empty {
  grid-template-columns: 1fr;
  text-align: center;
  padding: 40px;
}
.mon-featured::before {
  content: '';
  position: absolute; left: 0; top: 0; bottom: 0; width: 6px;
  background: linear-gradient(to bottom, #00A884, #007a61);
  border-radius: 20px 0 0 20px;
}

/* Queue number */
.mon-queue-num { text-align: center; }
.mon-queue-label { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: #9CA3AF; }
.mon-queue-val   { font-size: 56px; font-weight: 900; color: #00A884; font-family: 'DM Mono', monospace; line-height: 1; }
.mon-queue-of    { font-size: 12px; color: #9CA3AF; margin-top: 2px; }

/* Patient info */
.mon-featured-patient { display: flex; align-items: center; gap: 16px; }
.mon-featured-av {
  width: 64px; height: 64px; border-radius: 18px;
  display: flex; align-items: center; justify-content: center;
  font-size: 28px; font-weight: 900; flex-shrink: 0;
}
.mon-featured-name { font-size: 24px; font-weight: 800; color: #0F172A; line-height: 1.2; }
.mon-featured-mr   { font-size: 13px; color: #6B7280; font-family: 'DM Mono', monospace; margin-top: 4px; }
.mon-featured-status {
  display: inline-block; font-size: 12px; font-weight: 700;
  padding: 4px 12px; border-radius: 20px;
}
.mon-source-tag {
  display: inline-block; font-size: 11px; font-weight: 700;
  padding: 3px 10px; border-radius: 20px;
}

/* Details grid */
.mon-featured-details {
  display: grid; grid-template-columns: 1fr 1fr;
  gap: 10px 20px;
}
.mon-detail-item { background: #F8FAFC; border-radius: 10px; padding: 10px 14px; }
.mon-detail-item--warning { background: #FFFBEB; border: 1px solid #FCD34D; }
.mon-detail-lbl  { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #9CA3AF; margin-bottom: 3px; }
.mon-detail-val  { font-size: 14px; font-weight: 600; color: #1E293B; }
.mon-durasi      { color: #D97706; font-weight: 800; }

/* Progress bar ticker */
.mon-progress-bar {
  position: absolute; bottom: 0; left: 0; right: 0; height: 4px;
  background: #E2E8F0; border-radius: 0 0 20px 20px; overflow: hidden;
}
.mon-progress-fill {
  height: 100%; background: #00A884;
  animation: progress-fill linear;
  animation-fill-mode: both;
  animation-iteration-count: 1;
}
@keyframes progress-fill {
  from { width: 0 }
  to   { width: 100% }
}

/* ══ Table section ═════════════════════════════════════════════════════════ */
.mon-table-section {
  flex: 1; min-height: 0;
  padding: 14px 28px 0;
  display: flex; flex-direction: column; overflow: hidden;
}
.mon-table-header {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 10px; flex-shrink: 0;
}
.mon-table-title {
  display: flex; align-items: center; gap: 8px;
  font-size: 13px; font-weight: 800; color: #374151;
  text-transform: uppercase; letter-spacing: .06em;
}
.mon-table-refresh { font-size: 11px; color: #9CA3AF; }
.mon-table-empty   { font-size: 14px; color: #9CA3AF; text-align: center; padding: 20px; }

/* Ticker viewport — horizontal scroll berjalan */
.mon-ticker-viewport {
  flex: 1; overflow: hidden; position: relative;
}
.mon-ticker-track {
  display: flex; gap: 14px;
  animation: ticker-scroll 60s linear infinite;
  width: max-content;
  padding-bottom: 8px;
}
.mon-ticker-track:hover { animation-play-state: paused; }
@keyframes ticker-scroll {
  0%   { transform: translateX(0); }
  100% { transform: translateX(-50%); }
}

/* Ticker card */
.mon-ticker-card {
  background: #fff;
  border: 2px solid #E2E8F0;
  border-radius: 16px;
  padding: 16px 18px;
  width: 280px;
  flex-shrink: 0;
  display: flex; flex-direction: column; gap: 8px;
  box-shadow: 0 2px 12px rgba(0,0,0,.06);
  transition: border-color .2s;
}
.mon-ticker-card--active {
  border-color: #00A884 !important;
  box-shadow: 0 4px 20px rgba(0,168,132,.15) !important;
}
.mon-ticker-num {
  font-size: 10px; font-weight: 700; color: #9CA3AF;
  font-family: 'DM Mono', monospace;
}
.mon-ticker-patient {
  display: flex; align-items: center; gap: 10px;
}
.mon-ticker-gender {
  font-size: 20px; font-weight: 900; flex-shrink: 0; line-height: 1;
}
.mon-ticker-name {
  font-size: 14px; font-weight: 700; color: #0F172A;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 160px;
}
.mon-ticker-mr { font-size: 10px; color: #9CA3AF; font-family: 'DM Mono', monospace; }
.mon-ticker-src {
  display: inline-block; font-size: 9px; font-weight: 700;
  padding: 2px 8px; border-radius: 20px; width: fit-content;
}
.mon-ticker-diag {
  font-size: 12px; color: #374151; font-weight: 500;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.mon-ticker-bed  { font-size: 12px; }
.mon-ticker-status {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: 10px; font-weight: 700; padding: 3px 8px; border-radius: 20px; width: fit-content;
}
.mon-ticker-dur  { font-size: 11px; font-weight: 700; color: #D97706; }
.mon-ticker-est  { font-size: 10px; color: #D97706; font-family: 'DM Mono', monospace; }

/* ══ Live dot ══════════════════════════════════════════════════════════════ */
.mon-live-dot {
  display: inline-block; width: 8px; height: 8px; border-radius: 50%;
  background: #00A884;
  animation: live-pulse 2s ease-in-out infinite;
}
@keyframes live-pulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(0,168,132,.5); }
  50%       { box-shadow: 0 0 0 6px rgba(0,168,132,0); }
}

/* ══ Footer ═════════════════════════════════════════════════════════════════ */
.mon-footer {
  display: flex; align-items: center; justify-content: space-between;
  padding: 8px 28px;
  background: #fff;
  border-top: 1px solid #E2E8F0;
  font-size: 11px; color: #9CA3AF;
  flex-shrink: 0;
}

/* ══ Transitions ════════════════════════════════════════════════════════════ */
.slide-up-enter-active, .slide-up-leave-active { transition: all .4s ease; }
.slide-up-enter-from { opacity: 0; transform: translateY(16px); }
.slide-up-leave-to   { opacity: 0; transform: translateY(-16px); }
</style>
