<script setup>
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    semuaKamar: { type: Array,  default: () => [] },
    summary:    { type: Object, default: () => ({ total: 0, kosong: 0, terisi: 0, booking: 0 }) },
    flash:      { type: Object, default: () => ({}) },
});

const filterStatus = ref('semua');
const filterKelas  = ref('semua');
const searchBed    = ref('');

const kelasOptions = computed(() => {
    const unique = [...new Set(props.semuaKamar.map(k => k.nama_kelas).filter(Boolean))];
    return unique.sort();
});

const filtered = computed(() => {
    return props.semuaKamar.filter(k => {
        const statusOk = filterStatus.value === 'semua' || k.Status === filterStatus.value;
        const kelasOk  = filterKelas.value  === 'semua' || k.nama_kelas === filterKelas.value;
        let   searchOk = true;
        if (searchBed.value.trim()) {
            const q = searchBed.value.trim().toLowerCase();
            searchOk = (k.nama_ruang ?? '').toLowerCase().includes(q)
                    || (k.Kode_Ruang ?? '').toLowerCase().includes(q)
                    || (k.No_MR      ?? '').toLowerCase().includes(q);
        }
        return statusOk && kelasOk && searchOk;
    });
});

const grouped = computed(() => {
    const map = {};
    filtered.value.forEach(k => {
        const key = k.nama_kelas ?? 'Lainnya';
        if (!map[key]) map[key] = { nama: key, beds: [] };
        map[key].beds.push(k);
    });
    return Object.entries(map).map(([nama, v]) => ({ nama, beds: v.beds }));
});

const total   = computed(() => props.summary.total);
const kosong  = computed(() => props.summary.kosong);
const terisi  = computed(() => props.summary.terisi);
const booking = computed(() => props.summary.booking);
const pct     = computed(() => total.value > 0 ? Math.round((terisi.value / total.value) * 100) : 0);

const bedTheme = (bed) => {
    const s = (bed.Status ?? '').toUpperCase();
    if (s === 'KOSONG')  return { stripe:'#10B981', badgeBg:'#ECFDF5', badgeColor:'#059669', label:'Tersedia', dot:'#10B981', cardBorder:'rgba(16,185,129,0.15)' };
    if (s === 'BOOKING') return { stripe:'#F59E0B', badgeBg:'#FEF3C7', badgeColor:'#D97706', label:'Booking',  dot:'#F59E0B', cardBorder:'rgba(245,158,11,0.15)' };
    return                      { stripe:'#00A884', badgeBg:'#ECFDF5', badgeColor:'#00A884', label:'Terisi',   dot:'#00A884', cardBorder:'rgba(0,168,132,0.15)' };
};

const kpiCards = computed(() => [
    { label:'Total Bed',  val:total.value,   filter:'semua',   icon:'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5', iconBg:'#ECFDF5',  iconColor:'#00A884' },
    { label:'Tersedia',   val:kosong.value,  filter:'KOSONG',  icon:'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',                         iconBg:'#ECFDF5',  iconColor:'#059669' },
    { label:'Terisi',     val:terisi.value,  filter:'ISI',     icon:'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z', iconBg:'#ECFDF5', iconColor:'#00A884' },
    { label:'Booking',    val:booking.value, filter:'BOOKING', icon:'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', iconBg:'#FEF3C7', iconColor:'#D97706' },
]);
</script>

<template>
<AppLayout :flash="flash" page-title="Informasi Bed ICU">
<div class="denah-wrap">

  <!-- Page header -->
  <div class="denah-page-header">
    <div class="denah-page-header-inner">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <div class="denah-header-icon">
            <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
            </svg>
          </div>
          <h1 class="denah-page-title">Informasi Bed ICU</h1>
        </div>
        <p class="denah-page-subtitle">Status ketersediaan bed real-time dari sistem manajemen bed RS</p>
      </div>
      <div class="denah-header-live">
        <span class="ping-dot" style="width:7px;height:7px;border-radius:50%;background:#10B981;display:inline-block"></span>
        <span>Live Update</span>
      </div>
    </div>
  </div>

  <!-- KPI Cards -->
  <div class="denah-kpi-grid">
    <div v-for="c in kpiCards" :key="c.label"
      @click="filterStatus = filterStatus===c.filter ? 'semua' : c.filter"
      class="denah-kpi-card kpi-card"
      :class="filterStatus===c.filter ? 'denah-kpi-active' : ''"
      :style="filterStatus===c.filter ? `border-color:${c.iconColor};box-shadow:0 0 0 3px ${c.iconColor}15` : ''">
      <div class="flex items-center gap-3">
        <div class="denah-kpi-icon" :style="`background:${c.iconBg}`">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" :style="`color:${c.iconColor}`">
            <path stroke-linecap="round" stroke-linejoin="round" :d="c.icon"/>
          </svg>
        </div>
        <div>
          <p class="denah-kpi-val" :style="`color:${c.iconColor}`">{{ c.val }}</p>
          <p class="denah-kpi-label">{{ c.label }}</p>
        </div>
      </div>
      <!-- Active indicator -->
      <div v-if="filterStatus===c.filter" class="denah-kpi-check">
        <svg class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
          <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
        </svg>
      </div>
    </div>
  </div>

  <!-- Occupancy bar -->
  <div class="denah-section">
    <div class="denah-occ-card">
      <div class="flex justify-between items-center mb-2">
        <div class="flex items-center gap-2">
          <p class="text-sm font-bold" style="color:var(--text-primary)">Kapasitas ICU</p>
          <span class="text-xs font-semibold px-2 py-0.5 rounded-full" style="background:var(--bg-input);color:var(--text-muted)">{{ total }} total bed</span>
        </div>
        <span class="text-sm font-bold" style="font-family:'DM Mono',monospace" :style="`color:${pct>80?'#DC2626':pct>50?'#D97706':'#059669'}`">{{ pct }}% terisi</span>
      </div>
      <div class="denah-occ-bar">
        <div class="denah-occ-seg" :style="`width:${total>0?(kosong/total*100).toFixed(0)+'%':'0%'};background:#10B981`"></div>
        <div class="denah-occ-seg" :style="`width:${total>0?(booking/total*100).toFixed(0)+'%':'0%'};background:#F59E0B`"></div>
        <div class="denah-occ-seg" :style="`width:${total>0?(terisi/total*100).toFixed(0)+'%':'0%'};background:#00A884;border-radius:0 99px 99px 0`"></div>
      </div>
      <div class="flex gap-4 mt-2 flex-wrap">
        <span v-for="leg in [{c:'#10B981',l:'Tersedia',v:kosong},{c:'#F59E0B',l:'Booking',v:booking},{c:'#00A884',l:'Terisi',v:terisi}]" :key="leg.l"
          class="flex items-center gap-1.5 text-xs" style="color:var(--text-muted)">
          <span class="w-2 h-2 rounded-full flex-shrink-0" :style="`background:${leg.c}`"></span>
          <strong style="color:var(--text-primary)">{{ leg.v }}</strong> {{ leg.l }}
        </span>
      </div>
    </div>
  </div>

  <!-- Filter bar -->
  <div class="denah-section">
    <div class="denah-filter-card">
      <div class="flex gap-3 items-center flex-wrap">
        <!-- Search -->
        <div class="relative flex-1 min-w-0" style="min-width:200px;max-width:400px">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4" style="color:#94A3B8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
          <input v-model="searchBed" placeholder="Cari nama ruang, kode, No. MR..."
            class="w-full pl-9 pr-3 py-2 text-sm rounded-xl outline-none"
            style="border:1.5px solid var(--border-default);background:var(--bg-input);color:var(--text-primary)"
            @focus="$event.target.style.borderColor='#00A884'" @blur="$event.target.style.borderColor='var(--border-default)'"/>
        </div>
        <!-- Kelas chips -->
        <div class="flex gap-2 flex-wrap items-center">
          <span class="text-xs font-semibold" style="color:var(--text-muted)">Jenis:</span>
          <button @click="filterKelas='semua'" class="denah-chip" :class="filterKelas==='semua'?'active':''">Semua</button>
          <button v-for="k in kelasOptions" :key="k" @click="filterKelas=filterKelas===k?'semua':k"
            class="denah-chip" :class="filterKelas===k?'active':''">{{ k }}</button>
        </div>
        <!-- Reset -->
        <button v-if="filterStatus!=='semua'||filterKelas!=='semua'||searchBed"
          @click="filterStatus='semua';filterKelas='semua';searchBed=''"
          class="denah-reset-btn">✕ Reset</button>
        <span class="text-xs ml-auto" style="color:var(--text-muted)">
          <strong style="color:var(--text-primary)">{{ filtered.length }}</strong> / {{ total }} bed
        </span>
      </div>
    </div>
  </div>

  <!-- Empty state -->
  <div v-if="filtered.length===0" class="denah-section">
    <div class="denah-empty-card">
      <div class="denah-empty-icon">
        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
        </svg>
      </div>
      <p class="text-sm font-semibold" style="color:var(--text-secondary)">Tidak ada bed yang sesuai filter</p>
    </div>
  </div>

  <!-- Bed groups -->
  <div v-else class="denah-section denah-groups">
    <div v-for="group in grouped" :key="group.nama" class="denah-group">
      <!-- Group header -->
      <div class="denah-group-header">
        <div class="flex items-center gap-3">
          <div class="denah-group-accent"></div>
          <div>
            <p class="text-sm font-bold" style="color:var(--text-primary)">{{ group.nama }}</p>
            <p class="text-xs" style="color:var(--text-muted)">{{ group.beds.length }} bed total</p>
          </div>
        </div>
        <div class="flex gap-2">
          <span class="denah-group-badge available">
            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
            {{ group.beds.filter(b=>b.Status==='KOSONG').length }} Tersedia
          </span>
          <span class="denah-group-badge total">{{ group.beds.length }} total</span>
        </div>
      </div>

      <!-- Bed grid -->
      <div class="denah-bed-grid">
        <div v-for="bed in group.beds" :key="bed.Kode_Ruang" class="denah-bed-card"
          :style="`border-color:${bedTheme(bed).cardBorder}`"
          @mouseenter="$event.currentTarget.style.transform='translateY(-3px)';$event.currentTarget.style.boxShadow='0 8px 20px rgba(0,0,0,0.09)'"
          @mouseleave="$event.currentTarget.style.transform='';$event.currentTarget.style.boxShadow=''">
          <!-- Top stripe -->
          <div class="denah-bed-stripe" :style="`background:${bedTheme(bed).stripe}`"></div>
          <div class="denah-bed-body">
            <!-- Name + dot -->
            <div class="flex items-start justify-between gap-2 mb-2">
              <div class="min-w-0">
                <p class="text-xs font-bold truncate" style="color:var(--text-primary)">{{ bed.nama_ruang }}</p>
                <p class="text-xs mt-0.5" style="color:var(--text-muted);font-family:'DM Mono',monospace;font-size:10px">{{ bed.Kode_Ruang }}</p>
              </div>
              <span class="denah-bed-dot flex-shrink-0" :style="`background:${bedTheme(bed).dot}`"></span>
            </div>
            <!-- Status badge -->
            <span class="denah-bed-badge" :style="`background:${bedTheme(bed).badgeBg};color:${bedTheme(bed).badgeColor}`">
              {{ bedTheme(bed).label }}
            </span>
            <!-- Patient info -->
            <div v-if="bed.No_MR" class="denah-bed-patient">
              <p class="text-xs" style="color:var(--text-muted);font-family:'DM Mono',monospace">MR: {{ bed.No_MR }}</p>
              <p v-if="bed.nama_pasien" class="text-xs font-semibold mt-1 truncate" style="color:var(--text-primary)">{{ bed.nama_pasien }}</p>
              <p v-if="bed.diagnosa" class="text-xs mt-0.5 truncate" style="color:var(--text-secondary)">{{ bed.diagnosa }}</p>
              <p v-if="bed.asal_ruang" class="text-xs mt-0.5 truncate" style="color:var(--text-muted)">← {{ bed.asal_ruang }}</p>
            </div>
            <!-- Empty bed indicator -->
            <div v-else class="denah-bed-empty-indicator">
              <svg class="w-5 h-5" style="color:var(--text-muted);opacity:0.4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
              </svg>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>
</AppLayout>
</template>

<style scoped>
.denah-wrap { min-height: 100%; background: var(--bg-main); font-family:'Inter','Plus Jakarta Sans',sans-serif; }
.denah-section { padding: 0 16px 16px; }
@media (min-width: 640px) { .denah-section { padding: 0 24px 16px; } }

/* Page header */
.denah-page-header { padding: 20px 16px 0; }
@media (min-width: 640px) { .denah-page-header { padding: 20px 24px 0; } }
.denah-page-header-inner {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 10px; margin-bottom: 16px;
}
.denah-header-icon {
    width: 32px; height: 32px; border-radius: 9px;
    background: linear-gradient(135deg, #00A884, #007a61);
    display: flex; align-items: center; justify-content: center;
}
.denah-page-title { font-size: 20px; font-weight: 800; color: var(--text-primary); }
.denah-page-subtitle { font-size: 13px; color: var(--text-muted); margin-top: 2px; }
.denah-header-live {
    display: flex; align-items: center; gap: 6px;
    font-size: 12px; font-weight: 600; color: #059669;
    background: #ECFDF5; border: 1px solid rgba(16,185,129,0.2);
    padding: 5px 12px; border-radius: 20px;
}

/* KPI grid */
.denah-kpi-grid {
    padding: 0 16px 16px;
    display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;
}
@media (min-width: 640px) { .denah-kpi-grid { padding: 0 24px 16px; grid-template-columns: repeat(4, 1fr); } }
.denah-kpi-card {
    background: var(--bg-card); border-radius: 14px; border: 2px solid var(--border-default);
    box-shadow: var(--shadow-card); padding: 16px; cursor: pointer;
    transition: all .18s; position: relative; overflow: hidden;
}
.denah-kpi-card:hover { border-color: var(--border-card-hover); transform: translateY(-2px); }
.denah-kpi-active { background: var(--bg-card-hover) !important; }
.denah-kpi-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.denah-kpi-val { font-size: 28px; font-weight: 800; font-family:'DM Mono',monospace; line-height: 1; }
.denah-kpi-label { font-size: 12px; color: var(--text-muted); font-weight: 500; margin-top: 3px; }
.denah-kpi-check {
    position: absolute; top: 10px; right: 10px;
    width: 20px; height: 20px; border-radius: 50%;
    background: #00A884; display: flex; align-items: center; justify-content: center;
}

/* Occupancy */
.denah-occ-card { background: var(--bg-card); border-radius: 14px; border: 1px solid var(--border-default); padding: 16px 20px; box-shadow: var(--shadow-card); }
.denah-occ-bar { height: 10px; border-radius: 99px; background: var(--bg-input); overflow: hidden; display: flex; gap: 1px; }
.denah-occ-seg { height: 100%; transition: width .6s ease; border-radius: 99px; }

/* Filter */
.denah-filter-card { background: var(--bg-card); border-radius: 14px; border: 1px solid var(--border-default); padding: 14px 16px; box-shadow: var(--shadow-card); }
.denah-chip {
    padding: 5px 12px; border-radius: 8px; font-size: 11px; font-weight: 600;
    border: 1.5px solid var(--border-default); cursor: pointer; transition: all .15s;
    background: var(--bg-input); color: var(--text-secondary);
}
.denah-chip.active { background: #ECFDF5; color: #00A884; border-color: #6EE7CF; }
.denah-reset-btn { padding: 5px 10px; border-radius: 8px; font-size: 11px; font-weight: 600; background: #FEF2F2; color: #DC2626; border: 1.5px solid rgba(220,38,38,0.15); cursor: pointer; }

/* Empty */
.denah-empty-card { background: var(--bg-card); border-radius: 14px; border: 1px solid var(--border-default); padding: 48px; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 10px; }
.denah-empty-icon { width: 48px; height: 48px; border-radius: 14px; background: var(--bg-input); display: flex; align-items: center; justify-content: center; color: var(--text-muted); }
.denah-empty-icon svg { width: 24px; height: 24px; }

/* Groups */
.denah-groups { display: flex; flex-direction: column; gap: 24px; padding-bottom: 24px !important; }
.denah-group { }
.denah-group-header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 12px; flex-wrap: wrap; gap: 8px;
}
.denah-group-accent { width: 4px; height: 40px; border-radius: 4px; background: linear-gradient(180deg,#00A884,#007a61); flex-shrink: 0; }
.denah-group-badge {
    display: flex; align-items: center; gap: 5px;
    font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 20px;
}
.denah-group-badge.available { background: #ECFDF5; color: #059669; }
.denah-group-badge.total { background: var(--bg-input); color: var(--text-muted); }

/* Bed grid */
.denah-bed-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; }
@media (min-width: 640px) { .denah-bed-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); } }
@media (min-width: 1024px) { .denah-bed-grid { grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); } }

.denah-bed-card {
    background: var(--bg-card); border-radius: 12px; border: 1.5px solid;
    box-shadow: 0 1px 4px rgba(0,0,0,0.04); overflow: hidden;
    transition: all .18s; cursor: default;
}
.denah-bed-stripe { height: 4px; width: 100%; }
.denah-bed-body { padding: 12px; }
.denah-bed-dot { width: 8px; height: 8px; border-radius: 50%; margin-top: 2px; }
.denah-bed-badge { font-size: 10px; font-weight: 700; padding: 3px 8px; border-radius: 20px; display: inline-block; }
.denah-bed-patient { margin-top: 8px; padding-top: 8px; border-top: 1px solid var(--border-default); }
.denah-bed-empty-indicator { margin-top: 10px; display: flex; justify-content: center; opacity: 0.5; }
</style>
