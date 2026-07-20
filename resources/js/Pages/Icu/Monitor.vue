<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'

const props = defineProps({
    bedData: { type: Array,  default: () => [] },
    antrian: { type: Array,  default: () => [] },
    summary: { type: Object, default: () => ({}) },
})

// Clock
const now = ref(new Date())
let clockTimer = null
const timeStr = computed(() =>
    now.value.toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit', second:'2-digit' }))
const dateStr = computed(() =>
    now.value.toLocaleDateString('id-ID', { weekday:'long', day:'numeric', month:'long', year:'numeric' }))

// Live data via polling
const bedData    = ref(props.bedData)
const antrian    = ref(props.antrian)
const summary    = ref(props.summary)
const lastUpdate = ref('—')
let pollTimer    = null

const fetchData = async () => {
    try {
        const r = await fetch('/monitor/data', { headers: { Accept: 'application/json' } })
        const d = await r.json()
        bedData.value    = d.bedData
        antrian.value    = d.antrian
        summary.value    = d.summary
        lastUpdate.value = d.ts
    } catch(e) { console.warn('[Monitor] polling error', e) }
}

// Bed helpers
const bedTheme = (bed) => {
    const s  = (bed.status ?? '').toUpperCase()
    const jk = (bed.jenis_kelamin ?? '').toUpperCase()
    // Tersedia: putih dengan border hijau terang — kontras jelas di atas bg hijau tua
    if (s === 'KOSONG') return { bg:'#FFFFFF', border:'#86EFAC', label:'Tersedia', icon:'', color:'#166534' }
    if (jk === 'L')     return { bg:'#DBEAFE', border:'#60A5FA', label:'Laki-laki', icon:'♂', color:'#1D4ED8' }
    if (jk === 'P')     return { bg:'#FCE7F3', border:'#F472B6', label:'Perempuan',  icon:'♀', color:'#BE185D' }
    return                     { bg:'#EDE9FE', border:'#A78BFA', label:'Terisi',    icon:'●', color:'#5B21B6' }
}

const bedGrouped = computed(() => {
    const map = {}
    bedData.value.forEach(b => {
        const key = b.nama_kelas ?? 'Lainnya'
        if (!map[key]) map[key] = { nama: key, beds: [] }
        map[key].beds.push(b)
    })
    return Object.values(map)
})

const totalBed   = computed(() => summary.value.total_bed  ?? bedData.value.length)
const bedKosong  = computed(() => summary.value.kosong ?? 0)
const bedTerisi  = computed(() => summary.value.terisi ?? 0)
const bedLaki    = computed(() => bedData.value.filter(b => b.status === 'ISI' && (b.jenis_kelamin ?? '').toUpperCase() === 'L').length)
const bedWanita  = computed(() => bedData.value.filter(b => b.status === 'ISI' && (b.jenis_kelamin ?? '').toUpperCase() === 'P').length)

// Status helpers
const STATUS = {
    pending_icu:     { bg:'#DBEAFE', color:'#1D4ED8', label:'Menunggu ICU' },
    pending_admisi:  { bg:'#DBEAFE', color:'#1D4ED8', label:'Menunggu Admisi' },
    waiting_list:    { bg:'#FEF3C7', color:'#D97706', label:'Waiting List' },
    bed_confirmed:   { bg:'#D1FAE5', color:'#059669', label:'Bed Dikonfirmasi' },
    bed_verified:    { bg:'#D1FAE5', color:'#059669', label:'Bed Terverifikasi' },
    admisi_verified: { bg:'#DCFCE7', color:'#16A34A', label:'Terverifikasi' },
    ditolak:         { bg:'#FEE2E2', color:'#DC2626', label:'Ditolak' },
}
const ss     = (s) => STATUS[s] ?? { bg:'#F1F5F9', color:'#64748B', label: s }
const gIcon  = (g) => g === 'L' ? '♂' : g === 'P' ? '♀' : '—'
const gColor = (g) => g === 'L' ? '#1D4ED8' : g === 'P' ? '#BE185D' : '#64748B'

// Parse & sort─
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

// Kategori antrian
const booking = computed(() =>
    sortByOldest(antrian.value.filter(a => ['pending_icu','pending_admisi'].includes(a.status))))
const terverifikasi = computed(() =>
    sortByOldest(antrian.value.filter(a => ['bed_confirmed','bed_verified','admisi_verified'].includes(a.status))))
const waitingList = computed(() =>
    sortByOldest(antrian.value.filter(a => a.status === 'waiting_list')))

// Duration
const durasi = (str) => {
    const ts   = parseCreated(str)
    if (!ts) return '—'
    const diff = Math.floor((now.value - ts) / 60000)
    if (diff < 1)    return 'Baru saja'
    if (diff < 60)   return `${diff} mnt`
    if (diff < 1440) return `${Math.floor(diff/60)} jam ${diff % 60} mnt`
    return `${Math.floor(diff/1440)} hari`
}

// Highlight & auto-scroll─
const INTERVAL = 3500
const activeBookingIdx  = ref(0)
const activeVerifiedIdx = ref(0)
const activeWaitingIdx  = ref(0)
const bookingBody  = ref(null)
const verifiedBody = ref(null)
const waitingBody  = ref(null)
let highlightTimer = null

const scrollTo = async (bodyRef, idx) => {
    await nextTick()
    const c = bodyRef.value; if (!c) return
    const cards = c.querySelectorAll('.mon-card')
    const card  = cards[idx]; if (!card) return
    c.scrollTo({ top: card.offsetTop - c.clientHeight / 2 + card.offsetHeight / 2, behavior: 'smooth' })
}
const tick = () => {
    if (booking.value.length > 0) { activeBookingIdx.value = (activeBookingIdx.value + 1) % booking.value.length; scrollTo(bookingBody, activeBookingIdx.value) }
    if (terverifikasi.value.length > 0) { activeVerifiedIdx.value = (activeVerifiedIdx.value + 1) % terverifikasi.value.length; scrollTo(verifiedBody, activeVerifiedIdx.value) }
    if (waitingList.value.length > 0) { activeWaitingIdx.value = (activeWaitingIdx.value + 1) % waitingList.value.length; scrollTo(waitingBody, activeWaitingIdx.value) }
}

watch(booking,      (v) => { if (activeBookingIdx.value  >= v.length) activeBookingIdx.value  = 0 })
watch(terverifikasi,(v) => { if (activeVerifiedIdx.value >= v.length) activeVerifiedIdx.value = 0 })
watch(waitingList,  (v) => { if (activeWaitingIdx.value  >= v.length) activeWaitingIdx.value  = 0 })

onMounted(() => {
    clockTimer     = setInterval(() => now.value = new Date(), 1000)
    pollTimer      = setInterval(fetchData, 10000)
    highlightTimer = setInterval(tick, INTERVAL)
    fetchData()
})
onUnmounted(() => {
    clearInterval(clockTimer); clearInterval(pollTimer); clearInterval(highlightTimer)
})
</script>

<template>
<div class="mon-wrap">

  <!-- ══ HEADER ══════════════════════════════════════════════════════════════ -->
  <header class="mon-header">
    <!-- Kiri: back + logo + judul -->
    <div class="mon-header-left">
      <a href="/dashboard-icu" class="mon-back-btn" title="Kembali ke Dashboard">
        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
      </a>
      <div class="mon-logo">
        <svg width="26" height="26" fill="white" viewBox="0 0 24 24">
          <path d="M19 8H15V4H9v4H5v6h4v4h6v-4h4V8z"/>
        </svg>
      </div>
      <div class="mon-title-wrap">
        <p class="mon-sub">ICU · RS Urip Sumoharjo</p>
        <h1 class="mon-title">Monitoring Antrian ICU</h1>
      </div>
    </div>

    <!-- Tengah: stat tiles -->
    <div class="mon-stats">
      <div class="mon-stat">
        <span class="mon-stat-num" style="color:#4ADE80">{{ bedKosong }}</span>
        <span class="mon-stat-lbl">Kosong</span>
      </div>
      <div class="mon-stat">
        <span class="mon-stat-num" style="color:#60A5FA">{{ bedLaki }}</span>
        <span class="mon-stat-lbl">Terisi ♂</span>
      </div>
      <div class="mon-stat">
        <span class="mon-stat-num" style="color:#F472B6">{{ bedWanita }}</span>
        <span class="mon-stat-lbl">Terisi ♀</span>
      </div>
      <div class="mon-stat">
        <span class="mon-stat-num" style="color:#FCA5A5">{{ bedTerisi }}</span>
        <span class="mon-stat-lbl">Total Terisi</span>
      </div>
      <div class="mon-stat">
        <span class="mon-stat-num" style="color:#fff">{{ totalBed }}</span>
        <span class="mon-stat-lbl">Total Bed</span>
      </div>
    </div>

    <!-- Kanan: jam -->
    <div class="mon-clock-wrap">
      <p class="mon-clock">{{ timeStr }}</p>
      <p class="mon-date">{{ dateStr }}</p>
    </div>
  </header>

  <!-- ══ 3-COLUMN ANTRIAN ════════════════════════════════════════════════════ -->
  <main class="mon-grid">

    <!-- KOLOM 1: BOOKING -->
    <section class="mon-col">
      <div class="mon-col-hdr mon-col-hdr--blue">
        <div class="mon-col-ico">
          <svg width="20" height="20" fill="white" viewBox="0 0 24 24">
            <path d="M17 3H7c-1.1 0-2 .9-2 2v16l7-3 7 3V5c0-1.1-.9-2-2-2z"/>
          </svg>
        </div>
        <div class="mon-col-txt">
          <p class="mon-col-title">Pasien Booking</p>
          <p class="mon-col-count">{{ booking.length }} pasien</p>
        </div>
        <span class="live-dot live-dot--blue"></span>
      </div>
      <div class="mon-col-body" ref="bookingBody">
        <div v-if="booking.length === 0" class="mon-empty">
          <svg width="40" height="40" fill="none" stroke="#CBD5E1" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/>
          </svg>
          <p>Tidak ada pasien booking</p>
        </div>
        <TransitionGroup name="slide-card" tag="div" class="cards-wrap">
          <div v-for="(item, idx) in booking" :key="item.id"
            class="mon-card mon-card--blue"
            :class="{ 'mon-card--active': idx === activeBookingIdx }">
            <div class="mc-top">
              <span class="mc-num mc-num--blue">{{ String(idx+1).padStart(2,'0') }}</span>
              <span class="mc-badge" :style="`background:${ss(item.status).bg};color:${ss(item.status).color}`">{{ ss(item.status).label }}</span>
              <span class="mc-src" :class="item.sumber==='external'?'mc-src--ext':'mc-src--int'">{{ item.sumber==='external'?'🌐 Ext':'🏥 Int' }}</span>
            </div>
            <div class="mc-name-row">
              <span class="mc-gender" :style="`color:${gColor(item.jenis_kelamin)}`">{{ gIcon(item.jenis_kelamin) }}</span>
              <span class="mc-name">{{ item.nama_pasien }}</span>
            </div>
            <p class="mc-mr">MR: {{ item.No_MR ?? '—' }}</p>
            <div class="mc-info">
              <span class="mc-info-item"><span class="mc-info-lbl">Diagnosa:</span> {{ item.diagnosa ?? '—' }}</span>
              <span class="mc-info-item"><span class="mc-info-lbl">Bed:</span> {{ item.kebutuhan_bed ?? '—' }}</span>
              <span class="mc-info-item"><span class="mc-info-lbl">Asal:</span> {{ item.asal ?? '—' }}</span>
              <span class="mc-info-item mc-info-dur">⏱ {{ durasi(item.created_at_fmt) }}</span>
            </div>
            <p v-if="item.waiting_estimasi_fmt" class="mc-est">⏰ {{ item.waiting_estimasi_fmt }}</p>
            <div v-if="idx===activeBookingIdx" class="mc-prog mc-prog--blue"></div>
          </div>
        </TransitionGroup>
      </div>
    </section>

    <!-- KOLOM 2: TERVERIFIKASI─ -->
    <section class="mon-col">
      <div class="mon-col-hdr mon-col-hdr--green">
        <div class="mon-col-ico">
          <svg width="20" height="20" fill="white" viewBox="0 0 24 24">
            <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
          </svg>
        </div>
        <div class="mon-col-txt">
          <p class="mon-col-title">Terverifikasi Bed</p>
          <p class="mon-col-count">{{ terverifikasi.length }} pasien</p>
        </div>
        <span class="live-dot live-dot--green"></span>
      </div>
      <div class="mon-col-body" ref="verifiedBody">
        <div v-if="terverifikasi.length === 0" class="mon-empty">
          <svg width="40" height="40" fill="none" stroke="#CBD5E1" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          <p>Tidak ada pasien terverifikasi</p>
        </div>
        <TransitionGroup name="slide-card" tag="div" class="cards-wrap">
          <div v-for="(item, idx) in terverifikasi" :key="item.id"
            class="mon-card mon-card--green"
            :class="{ 'mon-card--active': idx === activeVerifiedIdx }">
            <div class="mc-top">
              <span class="mc-num mc-num--green">{{ String(idx+1).padStart(2,'0') }}</span>
              <span class="mc-badge" :style="`background:${ss(item.status).bg};color:${ss(item.status).color}`">{{ ss(item.status).label }}</span>
              <span class="mc-src" :class="item.sumber==='external'?'mc-src--ext':'mc-src--int'">{{ item.sumber==='external'?'🌐 Ext':'🏥 Int' }}</span>
            </div>
            <div class="mc-name-row">
              <span class="mc-gender" :style="`color:${gColor(item.jenis_kelamin)}`">{{ gIcon(item.jenis_kelamin) }}</span>
              <span class="mc-name">{{ item.nama_pasien }}</span>
            </div>
            <p class="mc-mr">MR: {{ item.No_MR ?? '—' }}</p>
            <div class="mc-info">
              <span class="mc-info-item"><span class="mc-info-lbl">Diagnosa:</span> {{ item.diagnosa ?? '—' }}</span>
              <span class="mc-info-item mc-info-bed">🛏 {{ item.nama_bed ?? '—' }}</span>
              <span class="mc-info-item"><span class="mc-info-lbl">Asal:</span> {{ item.asal ?? '—' }}</span>
              <span class="mc-info-item mc-info-dur">⏱ {{ durasi(item.created_at_fmt) }}</span>
            </div>
            <div v-if="idx===activeVerifiedIdx" class="mc-prog mc-prog--green"></div>
          </div>
        </TransitionGroup>
      </div>
    </section>

    <!-- KOLOM 3: WAITING LIST -->
    <section class="mon-col">
      <div class="mon-col-hdr mon-col-hdr--orange">
        <div class="mon-col-ico">
          <svg width="20" height="20" fill="white" viewBox="0 0 24 24">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>
          </svg>
        </div>
        <div class="mon-col-txt">
          <p class="mon-col-title">Waiting List</p>
          <p class="mon-col-count">{{ waitingList.length }} pasien</p>
        </div>
        <span class="live-dot live-dot--orange"></span>
      </div>
      <div class="mon-col-body" ref="waitingBody">
        <div v-if="waitingList.length === 0" class="mon-empty">
          <svg width="40" height="40" fill="none" stroke="#CBD5E1" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          <p>Tidak ada pasien waiting list</p>
        </div>
        <TransitionGroup name="slide-card" tag="div" class="cards-wrap">
          <div v-for="(item, idx) in waitingList" :key="item.id"
            class="mon-card mon-card--orange"
            :class="{ 'mon-card--active': idx === activeWaitingIdx }">
            <div class="mc-top">
              <span class="mc-num mc-num--orange">{{ String(idx+1).padStart(2,'0') }}</span>
              <span class="mc-badge" style="background:#FEF3C7;color:#D97706">Waiting List</span>
              <span class="mc-src" :class="item.sumber==='external'?'mc-src--ext':'mc-src--int'">{{ item.sumber==='external'?'🌐 Ext':'🏥 Int' }}</span>
            </div>
            <div class="mc-name-row">
              <span class="mc-gender" :style="`color:${gColor(item.jenis_kelamin)}`">{{ gIcon(item.jenis_kelamin) }}</span>
              <span class="mc-name">{{ item.nama_pasien }}</span>
            </div>
            <p class="mc-mr">MR: {{ item.No_MR ?? '—' }}</p>
            <div class="mc-info">
              <span class="mc-info-item"><span class="mc-info-lbl">Diagnosa:</span> {{ item.diagnosa ?? '—' }}</span>
              <span class="mc-info-item"><span class="mc-info-lbl">Bed:</span> {{ item.kebutuhan_bed ?? '—' }}</span>
              <span class="mc-info-item"><span class="mc-info-lbl">Asal:</span> {{ item.asal ?? '—' }}</span>
              <span class="mc-info-item mc-info-warn">⏱ {{ durasi(item.created_at_fmt) }}</span>
            </div>
            <p v-if="item.waiting_estimasi_fmt" class="mc-est">⏰ {{ item.waiting_estimasi_fmt }}</p>
            <div v-if="idx===activeWaitingIdx" class="mc-prog mc-prog--orange"></div>
          </div>
        </TransitionGroup>
      </div>
    </section>

  </main>

  <!-- ══ FOOTER ══════════════════════════════════════════════════════════════ -->
  <footer class="mon-footer">
    <span>ICU Bed Management System · RS Urip Sumoharjo</span>
    <span v-if="lastUpdate !== '—'" style="font-size:10px;opacity:.6">Update: {{ lastUpdate }}</span>
  </footer>

</div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=DM+Mono:wght@500;700&display=swap');
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

/* ══ Root ══════════════════════════════════════════════════════════════════ */
.mon-wrap {
  height: 100dvh; width: 100vw;
  background: #E8F5E9;
  font-family: 'Inter', sans-serif;
  display: flex; flex-direction: column;
  overflow: hidden;
}

/* ══ Header ════════════════════════════════════════════════════════════════ */
.mon-header {
  display: grid;
  grid-template-columns: auto 1fr auto;
  align-items: center;
  gap: 12px;
  padding: 10px 20px;
  background: linear-gradient(135deg, #059669 0%, #047857 100%);
  box-shadow: 0 3px 16px rgba(5,150,105,.35);
  flex-shrink: 0;
}
.mon-header-left { display: flex; align-items: center; gap: 10px; min-width: 0; }
.mon-back-btn {
  display: flex; align-items: center; justify-content: center;
  width: 34px; height: 34px; border-radius: 10px;
  background: rgba(255,255,255,.15); border: 1.5px solid rgba(255,255,255,.3);
  text-decoration: none; flex-shrink: 0;
  transition: background .2s;
}
.mon-back-btn:hover { background: rgba(255,255,255,.28); }
.mon-logo {
  width: 40px; height: 40px; border-radius: 12px;
  background: rgba(255,255,255,.18); border: 1.5px solid rgba(255,255,255,.3);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.mon-title-wrap { min-width: 0; }
.mon-sub   { font-size: 10px; color: rgba(255,255,255,.7); font-weight: 600; white-space: nowrap; }
.mon-title { font-size: clamp(14px, 2vw, 20px); font-weight: 900; color: #fff; letter-spacing: -.02em; white-space: nowrap; }

/* stats */
.mon-stats {
  display: flex; gap: 6px; justify-content: center; flex-wrap: wrap;
}
.mon-stat {
  display: flex; flex-direction: column; align-items: center;
  padding: 6px 12px; border-radius: 10px;
  background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.2);
  min-width: 64px;
}
.mon-stat-num { font-size: clamp(18px,2vw,24px); font-weight: 900; font-family: 'DM Mono', monospace; line-height: 1; }
.mon-stat-lbl { font-size: 9px; font-weight: 700; color: rgba(255,255,255,.8); margin-top: 2px; white-space: nowrap; }

/* clock */
.mon-clock-wrap { text-align: right; flex-shrink: 0; }
.mon-clock { font-size: clamp(22px, 3vw, 32px); font-weight: 900; color: #fff; font-family: 'DM Mono', monospace; line-height: 1; }
.mon-date  { font-size: 10px; color: rgba(255,255,255,.7); margin-top: 1px; white-space: nowrap; }

/* ══ Denah Bed ═════════════════════════════════════════════════════════════ */
.mon-denah {
  padding: 8px 16px 8px;
  background: #1B5E20;
  border-bottom: 1px solid rgba(255,255,255,.12);
  flex: 0 0 auto;
  max-height: 52vh;
  overflow-y: auto;
  overflow-x: hidden;
  scrollbar-width: thin;
  scrollbar-color: rgba(255,255,255,.2) transparent;
}
.mon-denah::-webkit-scrollbar { width: 5px; }
.mon-denah::-webkit-scrollbar-track { background: transparent; }
.mon-denah::-webkit-scrollbar-thumb { background: rgba(255,255,255,.2); border-radius: 10px; }

.mon-denah-bar {
  display: flex; align-items: center; gap: 8px;
  margin-bottom: 6px;
}
.mon-denah-legends { display: flex; gap: 12px; flex-wrap: wrap; }
.mon-leg {
  display: flex; align-items: center; gap: 5px;
  font-size: 10px; font-weight: 700; color: rgba(255,255,255,.7);
}
.mon-leg-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }

.mon-denah-groups { display: flex; flex-direction: column; gap: 6px; width: 100%; }
.mon-denah-group { width: 100%; }
.mon-denah-group-lbl {
  display: flex; align-items: center; gap: 6px;
  font-size: 10px; font-weight: 800; color: rgba(255,255,255,.5);
  text-transform: uppercase; letter-spacing: .07em;
  margin-bottom: 4px;
}
.mon-denah-group-pill {
  background: rgba(255,255,255,.1); border-radius: 20px;
  padding: 0 6px; font-size: 9px; color: rgba(255,255,255,.8);
}

/* Bed grid — mengisi lebar penuh, kolom otomatis, tanpa celah kosong di kanan */
.mon-bed-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(90px, 1fr));
  gap: 5px;
  width: 100%;
}
.mon-bed {
  background: var(--bg);
  border: 1.5px solid var(--br);
  border-radius: 8px;
  padding: 5px 8px;
  display: flex; flex-direction: column; align-items: center; gap: 1px;
  text-align: center;
  transition: transform .15s;
  cursor: default;
  overflow: hidden;
}
.mon-bed:hover { transform: translateY(-2px); }
.mon-bed-ico { font-size: 13px; line-height: 1; color: var(--c); }
.mon-bed-nm  { font-size: 10px; font-weight: 800; color: #0F172A; line-height: 1.2; width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.mon-bed-px  { font-size: 9px; font-weight: 600; color: #1E293B; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; width: 100%; }
.mon-bed-tag { font-size: 8px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--c); margin-top: 1px; }

/* ══ Grid 3 Kolom — Responsive ═════════════════════════════════════════════ */
.mon-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
  padding: 10px 16px;
  flex: 1;
  min-height: 0;
  overflow: hidden;
}

/* ══ Column ════════════════════════════════════════════════════════════════ */
.mon-col {
  display: flex; flex-direction: column;
  background: #fff;
  border-radius: 14px;
  border: 1px solid rgba(0,0,0,.08);
  box-shadow: 0 2px 12px rgba(0,0,0,.08);
  overflow: hidden;
  min-height: 0;
  min-width: 0;
}

/* header */
.mon-col-hdr {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 12px; flex-shrink: 0;
}
.mon-col-hdr--blue   { background: linear-gradient(135deg, #0891B2, #0E7490); }
.mon-col-hdr--green  { background: linear-gradient(135deg, #059669, #047857); }
.mon-col-hdr--orange { background: linear-gradient(135deg, #D97706, #B45309); }

.mon-col-ico {
  width: 36px; height: 36px; border-radius: 10px;
  background: rgba(255,255,255,.18); border: 1px solid rgba(255,255,255,.25);
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.mon-col-txt { display: flex; flex-direction: column; flex: 1; min-width: 0; }
.mon-col-title { font-size: 13px; font-weight: 800; color: #fff; }
.mon-col-count { font-size: 10px; color: rgba(255,255,255,.8); font-weight: 600; }

/* body — scroll */
.mon-col-body {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  min-height: 0;
  padding: 10px 12px;
  scrollbar-width: thin;
  scrollbar-color: rgba(255,255,255,.15) transparent;
}
.mon-col-body::-webkit-scrollbar { width: 5px; }
.mon-col-body::-webkit-scrollbar-track { background: transparent; }
.mon-col-body::-webkit-scrollbar-thumb { background: rgba(255,255,255,.15); border-radius: 10px; }

.cards-wrap { display: flex; flex-direction: column; gap: 8px; }

.mon-empty {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 8px; padding: 24px 12px;
  font-size: 12px; color: #9CA3AF; font-weight: 600; text-align: center;
}

/* ══ Card ══════════════════════════════════════════════════════════════════ */
.mon-card {
  border-radius: 12px;
  padding: 11px 12px;
  border: 1.5px solid #E2E8F0;
  border-left: 4px solid transparent;
  display: flex; flex-direction: column; gap: 7px;
  position: relative; overflow: hidden;
  transition: box-shadow .3s, transform .3s, border-color .3s, background .3s;
  min-width: 0;
  background: #fff;
}
.mon-card--blue   { border-left-color: #0891B2; background: #F0F9FF; }
.mon-card--green  { border-left-color: #059669; background: #F0FDF4; }
.mon-card--orange { border-left-color: #D97706; background: #FFFBEB; }

.mon-card--active { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,.12); z-index: 1; }
.mon-card--blue.mon-card--active   { border-color: #0891B2 !important; background: #E0F2FE !important; box-shadow: 0 6px 20px rgba(8,145,178,.2) !important; }
.mon-card--green.mon-card--active  { border-color: #059669 !important; background: #DCFCE7 !important; box-shadow: 0 6px 20px rgba(5,150,105,.2) !important; }
.mon-card--orange.mon-card--active { border-color: #D97706 !important; background: #FEF3C7 !important; box-shadow: 0 6px 20px rgba(217,119,6,.2) !important; }

/* top row */
.mc-top { display: flex; align-items: center; gap: 6px; flex-wrap: nowrap; min-width: 0; }
.mc-num { font-size: 22px; font-weight: 900; font-family: 'DM Mono', monospace; line-height: 1; flex-shrink: 0; }
.mc-num--blue   { color: #0891B2; }
.mc-num--green  { color: #059669; }
.mc-num--orange { color: #D97706; }
.mc-badge {
  font-size: 10px; font-weight: 700; padding: 3px 8px; border-radius: 20px;
  white-space: nowrap; flex-shrink: 0;
}
.mc-src {
  font-size: 9px; font-weight: 700; padding: 2px 6px; border-radius: 20px;
  white-space: nowrap; flex-shrink: 0; margin-left: auto;
}
.mc-src--ext { background: rgba(52,211,153,.18); color: #6EE7B7; }
.mc-src--int { background: rgba(167,139,250,.18); color: #C4B5FD; }

/* name row */
.mc-name-row { display: flex; align-items: center; gap: 6px; min-width: 0; }
.mc-gender { font-size: 16px; font-weight: 900; flex-shrink: 0; }
.mc-name {
  font-size: clamp(13px, 1.4vw, 15px); font-weight: 800; color: #0F172A;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.2;
}
.mc-mr { font-size: 10px; color: #64748B; font-family: 'DM Mono', monospace; }

/* info baris — menggantikan grid kotak */
.mc-info {
  display: flex; flex-direction: column; gap: 3px;
  border-left: 2px solid rgba(0,0,0,.07);
  padding-left: 8px;
}
.mc-info-item {
  font-size: 11px; color: #475569;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.mc-info-lbl { font-weight: 700; color: #94A3B8; margin-right: 2px; }
.mc-info-dur  { color: #D97706; font-weight: 700; }
.mc-info-warn { color: #DC2626; font-weight: 700; }
.mc-info-bed  { color: #059669; font-weight: 700; }

.mc-est { font-size: 10px; color: #D97706; font-family: 'DM Mono', monospace; font-weight: 700; background: #FEF9C3; border: 1px solid #FDE68A; border-radius: 7px; padding: 4px 8px; }

/* progress */
.mc-prog { position: absolute; bottom: 0; left: 0; right: 0; height: 3px; animation: sweep 3.5s linear forwards; }
.mc-prog--blue   { background: #38BDF8; }
.mc-prog--green  { background: #34D399; }
.mc-prog--orange { background: #FBBF24; }
@keyframes sweep { from { width: 0; } to { width: 100%; } }

/* live dot */
.live-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; margin-left: auto; }
.live-dot--blue   { background: #60A5FA; animation: pulse-b 2s ease-in-out infinite; }
.live-dot--green  { background: #34D399; animation: pulse-g 2s ease-in-out infinite; }
.live-dot--orange { background: #FCD34D; animation: pulse-o 2s ease-in-out infinite; }
@keyframes pulse-b { 0%,100%{box-shadow:0 0 0 0 rgba(96,165,250,.7)} 50%{box-shadow:0 0 0 6px rgba(96,165,250,0)} }
@keyframes pulse-g { 0%,100%{box-shadow:0 0 0 0 rgba(52,211,153,.7)} 50%{box-shadow:0 0 0 6px rgba(52,211,153,0)} }
@keyframes pulse-o { 0%,100%{box-shadow:0 0 0 0 rgba(252,211,77,.7)}  50%{box-shadow:0 0 0 6px rgba(252,211,77,0)} }

/* transition */
.slide-card-enter-active { transition: all .4s cubic-bezier(.22,1,.36,1); }
.slide-card-leave-active { transition: all .3s cubic-bezier(.55,0,1,.45); position: absolute; width: 100%; }
.slide-card-enter-from   { opacity: 0; transform: translateY(-14px) scale(.97); }
.slide-card-leave-to     { opacity: 0; transform: translateY(8px) scale(.97); }
.slide-card-move         { transition: transform .35s cubic-bezier(.22,1,.36,1); }

/* ══ Footer ════════════════════════════════════════════════════════════════ */
.mon-footer {
  display: flex; align-items: center; justify-content: space-between;
  padding: 6px 20px;
  background: #1E293B; border-top: 1px solid rgba(255,255,255,.06);
  font-size: 11px; color: rgba(255,255,255,.5); flex-shrink: 0; font-weight: 600;
}

/* ══ Responsive ════════════════════════════════════════════════════════════ */
/* Tablet: 2 kolom antrian + denah scroll horizontal */
@media (max-width: 1024px) {
  .mon-grid {
    grid-template-columns: repeat(2, minmax(0,1fr));
    overflow-y: auto;
  }
  .mon-col:last-child { grid-column: 1 / -1; }
  .mon-wrap { overflow: auto; }
}

/* Mobile: 1 kolom */
@media (max-width: 640px) {
  .mon-header {
    grid-template-columns: 1fr auto;
    grid-template-rows: auto auto;
    padding: 8px 12px;
  }
  .mon-header-left { grid-column: 1; }
  .mon-clock-wrap  { grid-column: 2; grid-row: 1; }
  .mon-stats       { grid-column: 1 / -1; grid-row: 2; justify-content: flex-start; overflow-x: auto; flex-wrap: nowrap; }
  .mon-logo, .mon-title-wrap .mon-sub { display: none; }
  .mon-grid {
    grid-template-columns: 1fr;
    padding: 8px 10px;
    overflow-y: auto;
    gap: 8px;
  }
  .mon-col:last-child { grid-column: 1; }
  .mon-denah { padding: 6px 10px 4px; }
  .mon-bed { min-width: 70px; max-width: 90px; padding: 4px 6px; }
}
</style>
