<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'

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

const fetchData = async () => {
    try {
        const r = await fetch('/monitor/data', { headers: { Accept: 'application/json' } })
        const d = await r.json()
        antrian.value    = d.antrian
        summary.value    = d.summary
        lastUpdate.value = d.ts
    } catch(e) { console.warn('[Monitor] polling error', e) }
}

// ── Status helpers ─────────────────────────────────────────────────────────────
const STATUS = {
    pending_icu:     { bg:'#DBEAFE', color:'#1D4ED8', label:'Menunggu ICU' },
    pending_admisi:  { bg:'#DBEAFE', color:'#1D4ED8', label:'Menunggu Admisi' },
    waiting_list:    { bg:'#FEF3C7', color:'#D97706', label:'Waiting List' },
    bed_confirmed:   { bg:'#D1FAE5', color:'#059669', label:'Bed Dikonfirmasi' },
    bed_verified:    { bg:'#D1FAE5', color:'#059669', label:'Bed Terverifikasi' },
    admisi_verified: { bg:'#DCFCE7', color:'#16A34A', label:'Terverifikasi' },
    ditolak:         { bg:'#FEE2E2', color:'#DC2626', label:'Ditolak' },
}
const ss = (s) => STATUS[s] ?? { bg:'#F1F5F9', color:'#64748B', label: s }

const gIcon  = (g) => g === 'L' ? '♂' : g === 'P' ? '♀' : '—'
const gColor = (g) => g === 'L' ? '#1D4ED8' : g === 'P' ? '#7C3AED' : '#64748B'

// ── Parse created_at_fmt → timestamp (ms) ─────────────────────────────────────
const parseCreated = (str) => {
    if (!str) return 0
    const parts = str.split(' ')
    if (parts.length < 2) return 0
    const [d, t] = parts
    const [dd, mm, yyyy] = d.split('/')
    const dt = new Date(`${yyyy}-${mm}-${dd}T${t}:00`)
    return isNaN(dt.getTime()) ? 0 : dt.getTime()
}

const sortByOldest = (arr) =>
    [...arr].sort((a, b) => parseCreated(a.created_at_fmt) - parseCreated(b.created_at_fmt))

// ── Kategori antrian ──────────────────────────────────────────────────────────
const booking = computed(() =>
    sortByOldest(antrian.value.filter(a => ['pending_icu','pending_admisi'].includes(a.status)))
)
const terverifikasi = computed(() =>
    sortByOldest(antrian.value.filter(a => ['bed_confirmed','bed_verified','admisi_verified'].includes(a.status)))
)
const waitingList = computed(() =>
    sortByOldest(antrian.value.filter(a => a.status === 'waiting_list'))
)

// ── Duration helper ────────────────────────────────────────────────────────────
const durasi = (createdStr) => {
    const ts = parseCreated(createdStr)
    if (!ts) return '—'
    const diff = Math.floor((now.value - ts) / 60000)
    if (diff < 1)    return 'Baru saja'
    if (diff < 60)   return `${diff} mnt`
    if (diff < 1440) return `${Math.floor(diff/60)} jam ${diff % 60} mnt`
    return `${Math.floor(diff/1440)} hari`
}

// ── Highlight aktif bergantian per kolom (tanpa scroll) ───────────────────────
const HIGHLIGHT_INTERVAL = 3500

const activeBookingIdx  = ref(0)
const activeVerifiedIdx = ref(0)
const activeWaitingIdx  = ref(0)

let highlightTimer = null

// ── Template refs untuk auto-scroll ──────────────────────────────────────────
const bookingBody  = ref(null)   // ref ke .mon-col-body
const verifiedBody = ref(null)
const waitingBody  = ref(null)

const scrollToActive = async (bodyRef, activeIdx) => {
    await nextTick()
    const container = bodyRef.value
    if (!container) return
    const cards = container.querySelectorAll('.mon-card')
    const activeCard = cards[activeIdx]
    if (!activeCard) return
    // Scroll container supaya kartu aktif terlihat di tengah
    const containerTop    = container.scrollTop
    const containerHeight = container.clientHeight
    const cardTop         = activeCard.offsetTop
    const cardHeight      = activeCard.offsetHeight
    const targetScroll    = cardTop - (containerHeight / 2) + (cardHeight / 2)
    container.scrollTo({ top: targetScroll, behavior: 'smooth' })
}

const tickHighlight = () => {
    if (booking.value.length > 0) {
        activeBookingIdx.value = (activeBookingIdx.value + 1) % booking.value.length
        scrollToActive(bookingBody, activeBookingIdx.value)
    }
    if (terverifikasi.value.length > 0) {
        activeVerifiedIdx.value = (activeVerifiedIdx.value + 1) % terverifikasi.value.length
        scrollToActive(verifiedBody, activeVerifiedIdx.value)
    }
    if (waitingList.value.length > 0) {
        activeWaitingIdx.value = (activeWaitingIdx.value + 1) % waitingList.value.length
        scrollToActive(waitingBody, activeWaitingIdx.value)
    }
}

watch(booking,      (v) => { if (activeBookingIdx.value  >= v.length) activeBookingIdx.value  = 0 })
watch(terverifikasi,(v) => { if (activeVerifiedIdx.value >= v.length) activeVerifiedIdx.value = 0 })
watch(waitingList,  (v) => { if (activeWaitingIdx.value  >= v.length) activeWaitingIdx.value  = 0 })

onMounted(() => {
    clockTimer     = setInterval(() => now.value = new Date(), 1000)
    pollTimer      = setInterval(fetchData, 10000)
    highlightTimer = setInterval(tickHighlight, HIGHLIGHT_INTERVAL)
    fetchData()
})
onUnmounted(() => {
    clearInterval(clockTimer)
    clearInterval(pollTimer)
    clearInterval(highlightTimer)
})
</script>

<template>
<div class="mon-wrap">

  <!-- ══ HEADER ══════════════════════════════════════════════════════════════ -->
  <header class="mon-header">
    <div class="mon-header-left">
      <a href="/dashboard-icu" class="mon-back-btn" title="Kembali ke Dashboard">
        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
      </a>
      <div class="mon-logo">
        <svg width="30" height="30" fill="white" viewBox="0 0 24 24">
          <path d="M19 8H15V4H9v4H5v6h4v4h6v-4h4V8z"/>
        </svg>
      </div>
      <div>
        <p class="mon-sub">ICU Bed Management · RS Urip Sumoharjo</p>
        <h1 class="mon-title">Monitoring Antrian ICU</h1>
      </div>
    </div>

    <div class="mon-stats">
      <div class="mon-stat">
        <span class="mon-stat-num" style="color:#4ADE80">{{ summary.kosong ?? 0 }}</span>
        <span class="mon-stat-lbl">Bed Kosong</span>
      </div>
      <div class="mon-stat">
        <span class="mon-stat-num" style="color:#FCA5A5">{{ summary.terisi ?? 0 }}</span>
        <span class="mon-stat-lbl">Bed Terisi</span>
      </div>
      <div class="mon-stat">
        <span class="mon-stat-num" style="color:#93C5FD">{{ booking.length }}</span>
        <span class="mon-stat-lbl">Booking</span>
      </div>
      <div class="mon-stat">
        <span class="mon-stat-num" style="color:#fff">{{ antrian.length }}</span>
        <span class="mon-stat-lbl">Total Antrian</span>
      </div>
    </div>

    <div class="mon-clock-wrap">
      <p class="mon-clock">{{ timeStr }}</p>
      <p class="mon-date">{{ dateStr }}</p>
    </div>
  </header>

  <!-- ══ 3-COLUMN GRID ══════════════════════════════════════════════════════ -->
  <main class="mon-grid">

    <!-- ── KOLOM 1: PASIEN BOOKING ──────────────────────────────────────── -->
    <section class="mon-col">
      <div class="mon-col-header mon-col-header--booking">
        <div class="mon-col-icon">
          <svg width="22" height="22" fill="white" viewBox="0 0 24 24">
            <path d="M17 3H7c-1.1 0-2 .9-2 2v16l7-3 7 3V5c0-1.1-.9-2-2-2z"/>
          </svg>
        </div>
        <div class="mon-col-header-text">
          <p class="mon-col-title">Pasien Booking</p>
          <p class="mon-col-count">{{ booking.length }} pasien menunggu</p>
        </div>
        <span class="live-dot live-dot--blue"></span>
      </div>

      <div class="mon-col-body" ref="bookingBody">
        <div v-if="booking.length === 0" class="mon-empty">
          <svg width="44" height="44" fill="none" stroke="#CBD5E1" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/>
          </svg>
          <p>Tidak ada pasien booking</p>
        </div>

        <TransitionGroup name="slide-card" tag="div" class="cards-group">
          <div
            v-for="(item, idx) in booking"
            :key="item.id"
            class="mon-card mon-card--booking"
            :class="{ 'mon-card--active mon-card--active-blue': idx === activeBookingIdx }"
          >
            <!-- Baris 1: Nomor urut + badge status -->
            <div class="card-row-top">
              <span class="mon-num mon-num--blue">{{ String(idx + 1).padStart(2,'0') }}</span>
              <span class="mon-badge" :style="`background:${ss(item.status).bg};color:${ss(item.status).color}`">
                {{ ss(item.status).label }}
              </span>
              <span class="mon-sumber" :class="item.sumber === 'external' ? 'sumber--ext' : 'sumber--int'">
                {{ item.sumber === 'external' ? '🌐 External' : '🏥 Internal' }}
              </span>
            </div>

            <!-- Baris 2: Nama pasien -->
            <div class="card-row-name">
              <span class="gender-icon" :style="`color:${gColor(item.jenis_kelamin)}`">{{ gIcon(item.jenis_kelamin) }}</span>
              <span class="mon-name">{{ item.nama_pasien }}</span>
            </div>

            <!-- Baris 3: MR -->
            <p class="mon-mr">No. MR: {{ item.No_MR ?? '—' }}</p>

            <!-- Baris 4: Info grid 2 kolom -->
            <div class="mon-info-grid">
              <div class="mon-info-box">
                <span class="info-lbl">Diagnosa</span>
                <span class="info-val">{{ item.diagnosa ?? '—' }}</span>
              </div>
              <div class="mon-info-box">
                <span class="info-lbl">Kebutuhan Bed</span>
                <span class="info-val">{{ item.kebutuhan_bed ?? '—' }}</span>
              </div>
              <div class="mon-info-box">
                <span class="info-lbl">Asal Rujukan</span>
                <span class="info-val">{{ item.asal ?? '—' }}</span>
              </div>
              <div class="mon-info-box">
                <span class="info-lbl">Menunggu</span>
                <span class="info-val info-val--dur">{{ durasi(item.created_at_fmt) }}</span>
              </div>
            </div>

            <!-- Estimasi (opsional) -->
            <p v-if="item.waiting_estimasi_fmt" class="mon-estimasi">
              ⏰ Estimasi: {{ item.waiting_estimasi_fmt }}
            </p>

            <!-- Progress bar aktif -->
            <div v-if="idx === activeBookingIdx" class="mon-card-progress mon-card-progress--blue"></div>
          </div>
        </TransitionGroup>
      </div>
    </section>

    <!-- ── KOLOM 2: TERVERIFIKASI BED ───────────────────────────────────── -->
    <section class="mon-col">
      <div class="mon-col-header mon-col-header--verified">
        <div class="mon-col-icon">
          <svg width="22" height="22" fill="white" viewBox="0 0 24 24">
            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
          </svg>
        </div>
        <div class="mon-col-header-text">
          <p class="mon-col-title">Terverifikasi Bed</p>
          <p class="mon-col-count">{{ terverifikasi.length }} pasien terverifikasi</p>
        </div>
        <span class="live-dot live-dot--green"></span>
      </div>

      <div class="mon-col-body" ref="verifiedBody">
        <div v-if="terverifikasi.length === 0" class="mon-empty">
          <svg width="44" height="44" fill="none" stroke="#CBD5E1" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          <p>Tidak ada pasien terverifikasi</p>
        </div>

        <TransitionGroup name="slide-card" tag="div" class="cards-group">
          <div
            v-for="(item, idx) in terverifikasi"
            :key="item.id"
            class="mon-card mon-card--verified"
            :class="{ 'mon-card--active mon-card--active-green': idx === activeVerifiedIdx }"
          >
            <div class="card-row-top">
              <span class="mon-num mon-num--green">{{ String(idx + 1).padStart(2,'0') }}</span>
              <span class="mon-badge" :style="`background:${ss(item.status).bg};color:${ss(item.status).color}`">
                {{ ss(item.status).label }}
              </span>
              <span class="mon-sumber" :class="item.sumber === 'external' ? 'sumber--ext' : 'sumber--int'">
                {{ item.sumber === 'external' ? '🌐 External' : '🏥 Internal' }}
              </span>
            </div>

            <div class="card-row-name">
              <span class="gender-icon" :style="`color:${gColor(item.jenis_kelamin)}`">{{ gIcon(item.jenis_kelamin) }}</span>
              <span class="mon-name">{{ item.nama_pasien }}</span>
            </div>

            <p class="mon-mr">No. MR: {{ item.No_MR ?? '—' }}</p>

            <div class="mon-info-grid">
              <div class="mon-info-box">
                <span class="info-lbl">Diagnosa</span>
                <span class="info-val">{{ item.diagnosa ?? '—' }}</span>
              </div>
              <div class="mon-info-box">
                <span class="info-lbl">Bed Ditetapkan</span>
                <span class="info-val info-val--bed">{{ item.nama_bed ?? '—' }}</span>
              </div>
              <div class="mon-info-box">
                <span class="info-lbl">Asal Rujukan</span>
                <span class="info-val">{{ item.asal ?? '—' }}</span>
              </div>
              <div class="mon-info-box">
                <span class="info-lbl">Menunggu</span>
                <span class="info-val info-val--dur">{{ durasi(item.created_at_fmt) }}</span>
              </div>
            </div>

            <div v-if="idx === activeVerifiedIdx" class="mon-card-progress mon-card-progress--green"></div>
          </div>
        </TransitionGroup>
      </div>
    </section>

    <!-- ── KOLOM 3: WAITING LIST ─────────────────────────────────────────── -->
    <section class="mon-col">
      <div class="mon-col-header mon-col-header--waiting">
        <div class="mon-col-icon">
          <svg width="22" height="22" fill="white" viewBox="0 0 24 24">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>
          </svg>
        </div>
        <div class="mon-col-header-text">
          <p class="mon-col-title">Waiting List</p>
          <p class="mon-col-count">{{ waitingList.length }} pasien menunggu</p>
        </div>
        <span class="live-dot live-dot--orange"></span>
      </div>

      <div class="mon-col-body" ref="waitingBody">
        <div v-if="waitingList.length === 0" class="mon-empty">
          <svg width="44" height="44" fill="none" stroke="#CBD5E1" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          <p>Tidak ada pasien waiting list</p>
        </div>

        <TransitionGroup name="slide-card" tag="div" class="cards-group">
          <div
            v-for="(item, idx) in waitingList"
            :key="item.id"
            class="mon-card mon-card--waiting"
            :class="{ 'mon-card--active mon-card--active-orange': idx === activeWaitingIdx }"
          >
            <div class="card-row-top">
              <span class="mon-num mon-num--orange">{{ String(idx + 1).padStart(2,'0') }}</span>
              <span class="mon-badge" style="background:#FEF3C7;color:#D97706">Waiting List</span>
              <span class="mon-sumber" :class="item.sumber === 'external' ? 'sumber--ext' : 'sumber--int'">
                {{ item.sumber === 'external' ? '🌐 External' : '🏥 Internal' }}
              </span>
            </div>

            <div class="card-row-name">
              <span class="gender-icon" :style="`color:${gColor(item.jenis_kelamin)}`">{{ gIcon(item.jenis_kelamin) }}</span>
              <span class="mon-name">{{ item.nama_pasien }}</span>
            </div>

            <p class="mon-mr">No. MR: {{ item.No_MR ?? '—' }}</p>

            <div class="mon-info-grid">
              <div class="mon-info-box">
                <span class="info-lbl">Diagnosa</span>
                <span class="info-val">{{ item.diagnosa ?? '—' }}</span>
              </div>
              <div class="mon-info-box">
                <span class="info-lbl">Kebutuhan Bed</span>
                <span class="info-val">{{ item.kebutuhan_bed ?? '—' }}</span>
              </div>
              <div class="mon-info-box">
                <span class="info-lbl">Asal Rujukan</span>
                <span class="info-val">{{ item.asal ?? '—' }}</span>
              </div>
              <div class="mon-info-box">
                <span class="info-lbl">Menunggu</span>
                <span class="info-val info-val--warn">{{ durasi(item.created_at_fmt) }}</span>
              </div>
            </div>

            <p v-if="item.waiting_estimasi_fmt" class="mon-estimasi">
              ⏰ Estimasi: {{ item.waiting_estimasi_fmt }}
            </p>

            <div v-if="idx === activeWaitingIdx" class="mon-card-progress mon-card-progress--orange"></div>
          </div>
        </TransitionGroup>
      </div>
    </section>

  </main>

  <!-- ══ FOOTER ══════════════════════════════════════════════════════════════ -->
  <footer class="mon-footer">
    <span>ICU Bed Management System · RS Urip Sumoharjo</span>
  </footer>

</div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&family=DM+Mono:wght@500;700&display=swap');

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

/* ══ Root ══════════════════════════════════════════════════════════════════ */
.mon-wrap {
  height: 100vh; width: 100vw;
  background: #ECFDF5;
  font-family: 'Inter', sans-serif;
  display: flex; flex-direction: column;
  overflow: hidden;
}

/* ══ Header ════════════════════════════════════════════════════════════════ */
.mon-header {
  display: flex; align-items: center; justify-content: space-between;
  gap: 16px; padding: 12px 28px;
  background: linear-gradient(135deg, #059669 0%, #047857 100%);
  box-shadow: 0 4px 24px rgba(5,150,105,.30);
  flex-shrink: 0;
}
.mon-header-left { display: flex; align-items: center; gap: 14px; flex-shrink: 0; }
.mon-back-btn {
  display: flex; align-items: center; justify-content: center;
  width: 38px; height: 38px; border-radius: 11px;
  background: rgba(255,255,255,.18); border: 1.5px solid rgba(255,255,255,.35);
  text-decoration: none; flex-shrink: 0;
  transition: background .2s, transform .15s;
}
.mon-back-btn:hover {
  background: rgba(255,255,255,.30);
  transform: translateX(-2px);
}
.mon-logo {
  width: 48px; height: 48px; border-radius: 14px;
  background: rgba(255,255,255,.2); border: 1.5px solid rgba(255,255,255,.35);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.mon-sub   { font-size: 11px; color: rgba(255,255,255,.8); font-weight: 600; }
.mon-title { font-size: 20px; font-weight: 900; color: #fff; letter-spacing: -.02em; }

.mon-stats { display: flex; gap: 10px; }
.mon-stat {
  display: flex; flex-direction: column; align-items: center;
  padding: 8px 18px; border-radius: 12px;
  background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.25);
  min-width: 84px;
}
.mon-stat-num { font-size: 26px; font-weight: 900; font-family: 'DM Mono', monospace; line-height: 1; }
.mon-stat-lbl { font-size: 10px; font-weight: 700; color: rgba(255,255,255,.85); margin-top: 3px; white-space: nowrap; }

.mon-clock-wrap { text-align: right; flex-shrink: 0; }
.mon-clock { font-size: 36px; font-weight: 900; color: #fff; font-family: 'DM Mono', monospace; line-height: 1; }
.mon-date  { font-size: 11px; color: rgba(255,255,255,.78); margin-top: 2px; }

/* ══ Grid 3 kolom ══════════════════════════════════════════════════════════ */
.mon-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 14px;
  padding: 14px 20px;
  flex: 1;
  min-height: 0;
  overflow: hidden;
}

/* ══ Column ════════════════════════════════════════════════════════════════ */
.mon-col {
  display: flex; flex-direction: column;
  background: #fff;
  border-radius: 18px;
  border: 2px solid #A7F3D0;
  box-shadow: 0 4px 20px rgba(5,150,105,.08);
  overflow: hidden;
  min-height: 0;
  min-width: 0;
}

/* ── Column header ─────────────────────────────────────────────────────── */
.mon-col-header {
  display: flex; align-items: center; gap: 12px;
  padding: 14px 16px; flex-shrink: 0;
}
.mon-col-header--booking  { background: linear-gradient(135deg, #0891B2, #0E7490); }
.mon-col-header--verified { background: linear-gradient(135deg, #059669, #047857); }
.mon-col-header--waiting  { background: linear-gradient(135deg, #D97706, #B45309); }

.mon-col-icon {
  width: 42px; height: 42px; border-radius: 11px;
  background: rgba(255,255,255,.2); border: 1px solid rgba(255,255,255,.3);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.mon-col-header-text { display: flex; flex-direction: column; flex: 1; min-width: 0; }
.mon-col-title { font-size: 15px; font-weight: 800; color: #fff; }
.mon-col-count { font-size: 11px; color: rgba(255,255,255,.85); font-weight: 600; margin-top: 1px; }

/* ── Column body – scroll otomatis, scrollbar tersembunyi ─────────────── */
.mon-col-body {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  min-height: 0;
  padding: 12px 14px;
  scrollbar-width: none;
}
.mon-col-body::-webkit-scrollbar { display: none; }

.cards-group { display: flex; flex-direction: column; gap: 10px; }

.mon-empty {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 12px; padding: 40px 20px;
  font-size: 14px; color: #9CA3AF; font-weight: 600; text-align: center;
}

/* ══ Card ══════════════════════════════════════════════════════════════════ */
.mon-card {
  border-radius: 14px;
  padding: 14px 15px;
  border: 1.5px solid #E2E8F0;
  border-left: 5px solid transparent;
  display: flex; flex-direction: column; gap: 9px;
  position: relative; overflow: hidden;
  transition: box-shadow .35s, transform .35s, border-color .35s, background .35s;
  min-width: 0;
}
.mon-card--booking  { border-left-color: #0891B2; background: #F0F9FF; }
.mon-card--verified { border-left-color: #059669; background: #F0FDF8; }
.mon-card--waiting  { border-left-color: #D97706; background: #FFFBEB; }

/* ── Active highlight (tanpa scroll) ──────────────────────────────────── */
.mon-card--active {
  transform: translateY(-2px);
  box-shadow: 0 10px 30px rgba(0,0,0,.13);
  z-index: 1;
}
.mon-card--active-blue {
  border-color: #0891B2 !important;
  background: #E0F2FE !important;
  box-shadow: 0 10px 30px rgba(8,145,178,.2) !important;
}
.mon-card--active-green {
  border-color: #059669 !important;
  background: #DCFCE7 !important;
  box-shadow: 0 10px 30px rgba(5,150,105,.2) !important;
}
.mon-card--active-orange {
  border-color: #D97706 !important;
  background: #FEF3C7 !important;
  box-shadow: 0 10px 30px rgba(217,119,6,.2) !important;
}

/* ── Progress bar bawah ──────────────────────────────────────────────── */
.mon-card-progress {
  position: absolute; bottom: 0; left: 0; right: 0; height: 4px;
  animation: progress-sweep 3.5s linear forwards;
}
.mon-card-progress--blue   { background: #0891B2; }
.mon-card-progress--green  { background: #059669; }
.mon-card-progress--orange { background: #D97706; }
@keyframes progress-sweep { from { width: 0; } to { width: 100%; } }

/* ── Card baris atas: nomor + badge + sumber ──────────────────────────── */
.card-row-top {
  display: flex; align-items: center; gap: 8px;
  flex-wrap: nowrap; min-width: 0;
}
.mon-num {
  font-size: 26px; font-weight: 900; font-family: 'DM Mono', monospace;
  line-height: 1; flex-shrink: 0;
}
.mon-num--blue   { color: #0891B2; }
.mon-num--green  { color: #059669; }
.mon-num--orange { color: #D97706; }

.mon-badge {
  font-size: 11px; font-weight: 700;
  padding: 4px 10px; border-radius: 20px;
  white-space: nowrap; flex-shrink: 0;
}
.mon-sumber {
  font-size: 10px; font-weight: 700;
  padding: 3px 7px; border-radius: 20px;
  white-space: nowrap; flex-shrink: 0; margin-left: auto;
}
.sumber--ext { background: #D1FAE5; color: #065F46; }
.sumber--int { background: #EDE9FE; color: #5B21B6; }

/* ── Nama pasien ──────────────────────────────────────────────────────── */
.card-row-name {
  display: flex; align-items: center; gap: 8px; min-width: 0;
}
.gender-icon { font-size: 18px; font-weight: 900; flex-shrink: 0; }
.mon-name {
  font-size: 16px; font-weight: 800; color: #0F172A;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  line-height: 1.25;
}

/* ── No MR ────────────────────────────────────────────────────────────── */
.mon-mr {
  font-size: 11px; color: #6B7280;
  font-family: 'DM Mono', monospace;
  font-weight: 500;
}

/* ── Info grid 2×2 ────────────────────────────────────────────────────── */
.mon-info-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 7px;
}
.mon-info-box {
  background: rgba(255,255,255,.85);
  border: 1px solid #E5E7EB;
  border-radius: 9px;
  padding: 7px 10px;
  display: flex; flex-direction: column; gap: 2px;
  min-width: 0;
}
.info-lbl {
  font-size: 10px; font-weight: 700;
  text-transform: uppercase; letter-spacing: .05em; color: #9CA3AF;
}
.info-val {
  font-size: 13px; font-weight: 600; color: #1E293B;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.info-val--dur  { color: #D97706; font-weight: 800; }
.info-val--warn { color: #DC2626; font-weight: 800; }
.info-val--bed  { color: #059669; font-weight: 800; }

/* ── Estimasi ─────────────────────────────────────────────────────────── */
.mon-estimasi {
  font-size: 11px; color: #D97706;
  font-family: 'DM Mono', monospace; font-weight: 700;
  background: #FEF9C3; border: 1px solid #FDE68A;
  border-radius: 8px; padding: 5px 10px;
}

/* ══ TransitionGroup: slide-card ══════════════════════════════════════════ */
.slide-card-enter-active { transition: all .45s cubic-bezier(.22,1,.36,1); }
.slide-card-leave-active { transition: all .35s cubic-bezier(.55,0,1,.45); position: absolute; width: 100%; }
.slide-card-enter-from   { opacity: 0; transform: translateY(-18px) scale(.97); }
.slide-card-leave-to     { opacity: 0; transform: translateY(10px) scale(.97); }
.slide-card-move         { transition: transform .4s cubic-bezier(.22,1,.36,1); }

/* ══ Live dot ══════════════════════════════════════════════════════════════ */
.live-dot {
  display: inline-block; width: 9px; height: 9px; border-radius: 50%;
  background: #34D399; animation: lp-g 2s ease-in-out infinite;
  flex-shrink: 0;
}
.live-dot--blue   { background: #60A5FA; animation-name: lp-b; }
.live-dot--green  { background: #34D399; animation-name: lp-g; }
.live-dot--orange { background: #FCD34D; animation-name: lp-o; }
.mon-col-header .live-dot { margin-left: auto; }

@keyframes lp-g { 0%,100%{box-shadow:0 0 0 0 rgba(52,211,153,.7)} 50%{box-shadow:0 0 0 8px rgba(52,211,153,0)} }
@keyframes lp-b { 0%,100%{box-shadow:0 0 0 0 rgba(96,165,250,.7)} 50%{box-shadow:0 0 0 8px rgba(96,165,250,0)} }
@keyframes lp-o { 0%,100%{box-shadow:0 0 0 0 rgba(252,211,77,.7)}  50%{box-shadow:0 0 0 8px rgba(252,211,77,0)} }

/* ══ Footer ════════════════════════════════════════════════════════════════ */
.mon-footer {
  display: flex; align-items: center; justify-content: space-between;
  padding: 8px 24px; background: #fff;
  border-top: 2px solid #D1FAE5;
  font-size: 12px; color: #6B7280; flex-shrink: 0; font-weight: 600;
}
.mon-footer-live { display: flex; align-items: center; gap: 8px; }
</style>