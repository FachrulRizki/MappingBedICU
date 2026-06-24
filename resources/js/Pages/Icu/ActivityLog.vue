<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    logs:         { type: Object,  default: () => ({ data: [], links: [], meta: {} }) },
    jenisOptions: { type: Array,   default: () => [] },
    users:        { type: Array,   default: () => [] },
    isAdmin:      { type: Boolean, default: false },
    filters:      { type: Object,  default: () => ({}) },
    flash:        { type: Object,  default: () => ({}) },
});

// ── Filter state ─────────────────────────────────────────────────────────────
const fJenis   = ref(props.filters.jenis   ?? '');
const fTglDari = ref(props.filters.tglDari ?? '');
const fTglAkh  = ref(props.filters.tglAkh  ?? '');
const fUserId  = ref(props.filters.userId  ?? '');
const fPerPage = ref(props.filters.perPage ?? 50);

const localDate = (n = 0) => {
    const d = new Date(); d.setDate(d.getDate() + n);
    return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
};
const today     = localDate(0);
const yesterday = localDate(-1);
const week7     = localDate(-6);
const month30   = localDate(-29);

const applyFilters = () => router.get(
    route('settings.activity_logs'),
    { jenis: fJenis.value, tgl_dari: fTglDari.value, tgl_sampai: fTglAkh.value,
      user_id: fUserId.value, per_page: fPerPage.value },
    { preserveState: true, replace: true, preserveScroll: true }
);

const setPreset = (dari, sampai) => { fTglDari.value = dari; fTglAkh.value = sampai; applyFilters(); };

const resetFilter = () => {
    fJenis.value = ''; fTglDari.value = ''; fTglAkh.value = '';
    fUserId.value = ''; fPerPage.value = 50;
    applyFilters();
};

const goToPage = (url) => { if (url) router.visit(url, { preserveState: true, preserveScroll: true }); };

// ── Badge colors ─────────────────────────────────────────────────────────────
const JENIS_COLOR = {
    'Autentikasi':       { bg: 'rgba(59,130,246,.12)',  color: '#3B82F6' },
    'Buat Data':         { bg: 'rgba(0,168,132,.12)',   color: '#00A884' },
    'Setujui Data':      { bg: 'rgba(16,185,129,.12)',  color: '#059669' },
    'Konfirmasi Bed':    { bg: 'rgba(14,165,233,.12)',  color: '#0EA5E9' },
    'Verifikasi Bed':    { bg: 'rgba(99,102,241,.12)',  color: '#6366F1' },
    'Verifikasi Pasien': { bg: 'rgba(245,158,11,.12)',  color: '#D97706' },
    'Tolak Data':        { bg: 'rgba(239,68,68,.12)',   color: '#DC2626' },
};
const jColor = (j) => JENIS_COLOR[j] ?? { bg: 'rgba(100,116,139,.12)', color: '#64748B' };

const ROLE_COLOR = {
    admin:         { bg: 'rgba(124,58,237,.12)', color: '#7C3AED' },
    admisi:        { bg: 'rgba(0,168,132,.12)',  color: '#00A884' },
    petugas_icu:   { bg: 'rgba(220,38,38,.10)',  color: '#DC2626' },
    petugas_ruang: { bg: 'rgba(14,165,233,.12)', color: '#0EA5E9' },
};
const rColor = (r) => ROLE_COLOR[r] ?? { bg: 'rgba(100,116,139,.12)', color: '#64748B' };

const ROLE_LABEL = {
    admin: 'Admin', admisi: 'Admisi',
    petugas_icu: 'Petugas ICU', petugas_ruang: 'Petugas Ruang',
};
const rLabel = (r) => ROLE_LABEL[r] ?? r ?? '—';

const MODULE_LABEL = {
    auth:             'Autentikasi',
    booking_external: 'Booking External',
    spri_internal:    'Booking Internal',
};
const mLabel = (m) => MODULE_LABEL[m] ?? m ?? '—';

// ── Computed ─────────────────────────────────────────────────────────────────
const meta  = computed(() => props.logs.meta  ?? {});
const links = computed(() => props.logs.links ?? []);

const hasFilters = computed(() =>
    fJenis.value || fTglDari.value || fTglAkh.value || fUserId.value
);

// Label filter aktif untuk badge di header tabel
const activeFilterLabel = computed(() => {
    const parts = [];
    if (fJenis.value)   parts.push(fJenis.value);
    if (fTglDari.value && fTglAkh.value) parts.push(`${fTglDari.value} – ${fTglAkh.value}`);
    else if (fTglDari.value) parts.push(`Dari ${fTglDari.value}`);
    else if (fTglAkh.value)  parts.push(`Sampai ${fTglAkh.value}`);
    if (fUserId.value) {
        const u = props.users.find(u => String(u.id) === String(fUserId.value));
        if (u) parts.push(u.name);
    }
    return parts.join(' · ');
});

// Warna dot untuk jenis aktif
const activeDotColor = computed(() =>
    fJenis.value ? jColor(fJenis.value).color : '#00A884'
);
</script>

<template>
<AppLayout :flash="flash" page-title="Log Aktivitas">
<div class="al-wrap">

  <!-- ══ PAGE HEADER ═══════════════════════════════════════════════════════ -->
  <div class="al-header">
    <div class="al-header-left">
      <div class="al-header-icon">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
        </svg>
      </div>
      <div>
        <h1 class="al-page-title">Log Aktivitas</h1>
        <p class="al-page-sub">Rekam jejak semua aktivitas pengguna dalam sistem</p>
      </div>
    </div>
    <!-- Total badge -->
    <div class="al-total-badge">
      <span class="al-total-dot"></span>
      <span class="al-total-val">{{ meta.total ?? logs.data?.length ?? 0 }}</span>
      <span class="al-total-label">total entri</span>
    </div>
  </div>

  <!-- ══ FILTER BAR ════════════════════════════════════════════════════════ -->
    <div class="al-filter-card">
        <div class="al-filter-grid">

        <!-- Jenis Aktivitas — DROPDOWN -->
        <div class="al-filter-field">
            <label class="al-label">Jenis Aktivitas</label>
            <div class="al-select-wrap">
            <!-- Dot warna jenis aktif -->
            <span v-if="fJenis" class="al-select-dot" :style="`background:${jColor(fJenis).color}`"></span>
            <span v-else class="al-select-dot" style="background:#9CA3AF"></span>
            <select v-model="fJenis" @change="applyFilters" class="al-select">
                <option value="">Semua Jenis</option>
                <option v-for="j in jenisOptions" :key="j" :value="j">{{ j }}</option>
            </select>
            <svg class="al-select-chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
            </svg>
            </div>
        </div>

        <!-- Dari Tanggal -->
        <div class="al-filter-field">
            <label class="al-label">Dari Tanggal</label>
            <input v-model="fTglDari" @change="applyFilters" type="date" class="al-input"/>
        </div>

        <!-- Sampai Tanggal -->
        <div class="al-filter-field">
            <label class="al-label">Sampai Tanggal</label>
            <input v-model="fTglAkh" @change="applyFilters" type="date" :min="fTglDari" class="al-input"/>
        </div>

        <!-- Filter user (admin only) -->
        <div v-if="isAdmin" class="al-filter-field">
            <label class="al-label">Pengguna</label>
            <div class="al-select-wrap">
            <svg class="al-select-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
            <select v-model="fUserId" @change="applyFilters" class="al-select al-select--icon">
                <option value="">Semua Pengguna</option>
                <option v-for="u in users" :key="u.id" :value="u.id">
                {{ u.name }} ({{ rLabel(u.role) }})
                </option>
            </select>
            <svg class="al-select-chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
            </svg>
            </div>
        </div>

        <!-- Per page (jika bukan admin, pakai kolom ke-4) -->
        <div v-if="!isAdmin" class="al-filter-field">
            <label class="al-label">Per Halaman</label>
            <div class="al-select-wrap">
            <select v-model="fPerPage" @change="applyFilters" class="al-select">
                <option :value="25">25 baris</option>
                <option :value="50">50 baris</option>
                <option :value="100">100 baris</option>
            </select>
            <svg class="al-select-chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
            </svg>
            </div>
        </div>
    </div>

        <!-- Row 2: Preset tanggal + per-page (admin) + reset -->
        <div class="al-filter-actions">
        <div class="al-presets">
            <button @click="setPreset(today, today)"        class="al-preset-btn" :class="{active: fTglDari===today     && fTglAkh===today}">Hari ini</button>
            <button @click="setPreset(yesterday, yesterday)" class="al-preset-btn" :class="{active: fTglDari===yesterday && fTglAkh===yesterday}">Kemarin</button>
            <button @click="setPreset(week7, today)"        class="al-preset-btn" :class="{active: fTglDari===week7     && fTglAkh===today}">7 Hari</button>
            <button @click="setPreset(month30, today)"      class="al-preset-btn" :class="{active: fTglDari===month30   && fTglAkh===today}">30 Hari</button>
        </div>

        <div class="al-filter-right">
            <!-- Per-page jika admin (ada di row 2 agar grid tetap 4 kolom) -->
            <div v-if="isAdmin" class="al-select-wrap al-perpage-wrap">
            <select v-model="fPerPage" @change="applyFilters" class="al-select al-select--sm">
                <option :value="25">25 / hal</option>
                <option :value="50">50 / hal</option>
                <option :value="100">100 / hal</option>
            </select>
            <svg class="al-select-chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
            </svg>
            </div>

            <!-- Tombol reset -->
            <button v-if="hasFilters" @click="resetFilter" class="al-reset-btn">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            Reset Filter
            </button>
        </div>
        </div>

        <!-- Active filter summary badge -->
        <Transition name="al-fade">
        <div v-if="hasFilters" class="al-active-filter">
            <span class="al-active-dot" :style="`background:${activeDotColor}`"></span>
            <span class="al-active-text">Filter aktif:</span>
            <span class="al-active-val">{{ activeFilterLabel }}</span>
        </div>
        </Transition>
    </div>

  <!-- ══ TABEL ════════════════════════════════════════════════════════════ -->
  <div class="al-table-card">

    <!-- Empty state -->
    <div v-if="!logs.data?.length" class="al-empty">
      <div class="al-empty-icon">
        <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
        </svg>
      </div>
      <p class="al-empty-title">Belum ada log aktivitas</p>
      <p class="al-empty-sub">Log akan muncul setelah ada aktivitas yang tercatat dalam sistem</p>
    </div>

    <!-- Tabel data -->
    <div v-else class="al-table-wrap">
      <table class="al-table">
        <thead>
          <tr>
            <th>Pengguna</th>
            <th>Jenis Aktivitas</th>
            <th>Aktivitas</th>
            <th>Modul</th>
            <th>Waktu</th>
            <th>IP Address</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="log in logs.data" :key="log.id">

            <!-- Pengguna -->
            <td>
              <div class="al-user-cell">
                <div class="al-user-av" :style="`background:${rColor(log.user_role).bg}; color:${rColor(log.user_role).color}`">
                  {{ log.user_name?.charAt(0)?.toUpperCase() ?? '?' }}
                </div>
                <div class="al-user-info">
                  <p class="al-user-name">{{ log.user_name }}</p>
                  <span class="al-role-badge" :style="`background:${rColor(log.user_role).bg}; color:${rColor(log.user_role).color}`">
                    {{ rLabel(log.user_role) }}
                  </span>
                </div>
              </div>
            </td>

            <!-- Jenis Aktivitas -->
            <td>
              <span class="al-jenis-badge"
                :style="`background:${jColor(log.jenis_aktivitas).bg}; color:${jColor(log.jenis_aktivitas).color}`">
                <span class="al-jenis-dot" :style="`background:${jColor(log.jenis_aktivitas).color}`"></span>
                {{ log.jenis_aktivitas }}
              </span>
            </td>

            <!-- Aktivitas -->
            <td><p class="al-desc">{{ log.aktivitas }}</p></td>

            <!-- Modul -->
            <td>
              <span v-if="log.module" class="al-module-tag">{{ mLabel(log.module) }}</span>
              <span v-else class="al-none">—</span>
            </td>

            <!-- Waktu -->
            <td><p class="al-time">{{ log.created_at }}</p></td>

            <!-- IP -->
            <td><span class="al-ip">{{ log.ip_address }}</span></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Footer: info + pagination -->
    <div v-if="logs.data?.length" class="al-table-footer">
      <p class="al-footer-info">
        Menampilkan
        <strong style="color:var(--text-primary)">{{ meta.from ?? 1 }}</strong>–<strong style="color:var(--text-primary)">{{ meta.to ?? logs.data.length }}</strong>
        dari
        <strong style="color:var(--text-primary)">{{ meta.total ?? logs.data.length }}</strong>
        entri
      </p>
      <div v-if="meta.last_page > 1" class="al-pagination">
        <button v-for="link in links" :key="link.label"
          @click="goToPage(link.url)"
          :disabled="!link.url || link.active"
          class="al-page-btn"
          :class="{ 'al-page-btn--active': link.active, 'al-page-btn--disabled': !link.url }"
          v-html="link.label">
        </button>
      </div>
    </div>
  </div>

</div>
</AppLayout>
</template>

<style scoped>
/* ── Root ── */
.al-wrap {
  min-height: 100%; background: var(--bg-main);
  font-family: 'Inter','Plus Jakarta Sans',sans-serif;
  padding: 20px 16px 32px;
}
@media(min-width:640px) { .al-wrap { padding: 24px 24px 40px; } }

/* ── Header ── */
.al-header {
  display: flex; align-items: flex-start; justify-content: space-between;
  flex-wrap: wrap; gap: 14px; margin-bottom: 20px;
}
.al-header-left { display: flex; align-items: center; gap: 12px; }
.al-header-icon {
  width: 42px; height: 42px; border-radius: 12px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  background: linear-gradient(135deg, #00A884, #007a61);
  box-shadow: 0 4px 12px rgba(0,168,132,.3);
}
.al-page-title { font-size: 20px; font-weight: 800; color: var(--text-primary); letter-spacing: -.01em; margin: 0; }
.al-page-sub   { font-size: 12.5px; color: var(--text-secondary); margin: 3px 0 0; }

.al-total-badge {
  display: flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: 30px;
  background: var(--bg-card); border: 1px solid var(--border-default);
  box-shadow: 0 2px 8px rgba(15,29,46,.05);
}
.al-total-dot {
  width: 8px; height: 8px; border-radius: 50%; background: #00A884; flex-shrink: 0;
  animation: al-pulse 2s ease-in-out infinite;
}
@keyframes al-pulse {
  0%,100% { box-shadow: 0 0 0 0 rgba(0,168,132,.5); }
  50%      { box-shadow: 0 0 0 5px rgba(0,168,132,0); }
}
.al-total-val   { font-size: 18px; font-weight: 800; font-family:'DM Mono',monospace; color: var(--text-primary); }
.al-total-label { font-size: 12px; color: var(--text-muted); font-weight: 500; }

/* ── Filter card ── */
.al-filter-card {
  background: var(--bg-card); border: 1px solid var(--border-default);
  border-radius: 12px; padding: 16px 18px; margin-bottom: 16px;
  box-shadow: 0 2px 10px rgba(15,29,46,.05);
}
.al-filter-grid {
  display: grid; grid-template-columns: repeat(2,1fr); gap: 12px; margin-bottom: 12px;
}
@media(min-width:768px) { .al-filter-grid { grid-template-columns: repeat(4,1fr); } }

.al-filter-field { display: flex; flex-direction: column; gap: 5px; }
.al-label {
  font-size: 10.5px; font-weight: 700; text-transform: uppercase;
  letter-spacing: .06em; color: var(--text-muted);
}

/* ── Custom select wrapper ── */
.al-select-wrap {
  position: relative; display: flex; align-items: center;
}
.al-select-dot {
  position: absolute; left: 11px; top: 50%; transform: translateY(-50%);
  width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; z-index: 1; pointer-events: none;
}
.al-select-icon {
  position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
  width: 15px; height: 15px; color: var(--text-muted); pointer-events: none; z-index: 1;
}
.al-select-chevron {
  position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
  width: 14px; height: 14px; color: var(--text-muted); pointer-events: none; z-index: 1;
}
.al-select {
  width: 100%; padding: 9px 30px 9px 26px;
  border: 1.5px solid var(--border-default); border-radius: 9px;
  font-size: 12.5px; color: var(--text-primary); background: var(--bg-input);
  outline: none; appearance: none; -webkit-appearance: none; cursor: pointer;
  transition: border-color .2s, box-shadow .2s;
}
.al-select:focus { border-color: #00A884; box-shadow: 0 0 0 3px rgba(0,168,132,.1); }
.al-select--icon { padding-left: 32px; }
.al-select--sm   { padding: 7px 28px 7px 12px; font-size: 12px; }

/* Plain input date */
.al-input {
  padding: 9px 12px; border: 1.5px solid var(--border-default);
  border-radius: 9px; font-size: 12.5px; color: var(--text-primary);
  background: var(--bg-input); outline: none;
  transition: border-color .2s, box-shadow .2s;
}
.al-input:focus { border-color: #00A884; box-shadow: 0 0 0 3px rgba(0,168,132,.1); }

/* ── Filter actions row ── */
.al-filter-actions {
  display: flex; align-items: center; justify-content: space-between;
  flex-wrap: wrap; gap: 8px;
}
.al-filter-right { display: flex; align-items: center; gap: 8px; }

.al-presets {
  display: flex; gap: 2px; padding: 3px;
  background: var(--bg-input); border: 1px solid var(--border-default);
  border-radius: 10px; flex-wrap: wrap;
}
.al-preset-btn {
  padding: 5px 12px; border-radius: 8px; border: none; cursor: pointer;
  font-size: 11.5px; font-weight: 600; background: transparent;
  color: var(--text-secondary); transition: all .14s;
}
.al-preset-btn:hover { color: var(--text-primary); }
.al-preset-btn.active { background: var(--bg-card); color: #00A884; box-shadow: 0 1px 5px rgba(0,0,0,.08); }

.al-perpage-wrap { min-width: 100px; }

.al-reset-btn {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 7px 14px; border-radius: 9px; font-size: 12px; font-weight: 600;
  background: rgba(239,68,68,.08); color: #DC2626;
  border: 1.5px solid rgba(239,68,68,.2); cursor: pointer; transition: all .15s;
}
.al-reset-btn:hover { background: rgba(239,68,68,.15); border-color: rgba(239,68,68,.35); }

/* ── Active filter badge ── */
.al-active-filter {
  display: flex; align-items: center; gap: 7px;
  margin-top: 10px; padding: 8px 12px; border-radius: 8px;
  background: var(--bg-input); border: 1px solid var(--border-default);
}
.al-active-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
.al-active-text { font-size: 11px; font-weight: 600; color: var(--text-muted); }
.al-active-val  { font-size: 12px; font-weight: 700; color: var(--text-primary); }

.al-fade-enter-active, .al-fade-leave-active { transition: all .2s ease; }
.al-fade-enter-from, .al-fade-leave-to { opacity: 0; transform: translateY(-4px); }

/* ── Table card ── */
.al-table-card {
  background: var(--bg-card); border: 1px solid var(--border-default);
  border-radius: 12px; box-shadow: 0 2px 10px rgba(15,29,46,.05); overflow: hidden;
}
.al-table-wrap { overflow-x: auto; }
.al-table {
  width: 100%; border-collapse: collapse; font-size: 13px; min-width: 720px;
}
.al-table thead tr {
  background: var(--bg-surface-2, #F8FAFC);
  border-bottom: 2px solid var(--border-table);
}
.al-table th {
  padding: 11px 16px; text-align: left;
  font-size: 10.5px; font-weight: 700; text-transform: uppercase;
  letter-spacing: .07em; color: var(--text-muted); white-space: nowrap;
}
.al-table tbody tr { border-bottom: 1px solid var(--border-row); transition: background .12s; }
.al-table tbody tr:last-child { border-bottom: none; }
.al-table tbody tr:hover td { background: var(--bg-row-hover); }
.al-table td { padding: 12px 16px; vertical-align: middle; }

/* Cells */
.al-user-cell { display: flex; align-items: center; gap: 10px; }
.al-user-av {
  width: 34px; height: 34px; border-radius: 9px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: 13px;
}
.al-user-info { min-width: 0; }
.al-user-name {
  font-size: 13px; font-weight: 600; color: var(--text-primary);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 130px; margin: 0;
}
.al-role-badge {
  display: inline-block; font-size: 10px; font-weight: 700;
  padding: 2px 8px; border-radius: 20px; margin-top: 3px;
}
.al-jenis-badge {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 4px 10px; border-radius: 20px;
  font-size: 11.5px; font-weight: 700; white-space: nowrap;
}
.al-jenis-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
.al-desc {
  font-size: 12.5px; color: var(--text-primary); max-width: 280px;
  line-height: 1.4; margin: 0; overflow: hidden;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
}
.al-module-tag {
  display: inline-block; font-size: 11px; font-weight: 600; padding: 3px 9px;
  border-radius: 8px; background: var(--bg-input); color: var(--text-secondary);
  border: 1px solid var(--border-default);
}
.al-none  { color: var(--text-muted); font-size: 12px; }
.al-time  { font-size: 11.5px; font-family:'DM Mono',monospace; color: var(--text-secondary); white-space: nowrap; margin: 0; }
.al-ip    { font-size: 12px; font-family:'DM Mono',monospace; color: var(--text-muted); white-space: nowrap; }

/* ── Empty ── */
.al-empty {
  display: flex; flex-direction: column; align-items: center;
  justify-content: center; padding: 64px 24px; text-align: center;
}
.al-empty-icon {
  width: 56px; height: 56px; border-radius: 16px; margin-bottom: 16px;
  background: var(--bg-input); display: flex; align-items: center;
  justify-content: center; color: var(--text-muted);
}
.al-empty-title { font-size: 14px; font-weight: 700; color: var(--text-secondary); margin: 0 0 6px; }
.al-empty-sub   { font-size: 12px; color: var(--text-muted); max-width: 280px; }

/* ── Footer ── */
.al-table-footer {
  display: flex; align-items: center; justify-content: space-between;
  flex-wrap: wrap; gap: 10px; padding: 12px 18px;
  border-top: 1px solid var(--border-default);
  background: var(--bg-surface-2, #FAFAFA);
}
.al-footer-info { font-size: 12px; color: var(--text-secondary); }

.al-pagination { display: flex; align-items: center; flex-wrap: wrap; gap: 3px; }
.al-page-btn {
  min-width: 32px; height: 32px; padding: 0 10px; border-radius: 8px;
  border: 1.5px solid var(--border-default); background: var(--bg-card);
  font-size: 12px; font-weight: 600; color: var(--text-secondary);
  cursor: pointer; transition: all .14s; display: flex; align-items: center; justify-content: center;
}
.al-page-btn:hover:not(.al-page-btn--disabled):not(.al-page-btn--active) {
  border-color: rgba(0,168,132,.35); color: #00A884; background: rgba(0,168,132,.05);
}
.al-page-btn--active  { background: #00A884; color: #fff; border-color: #00A884; cursor: default; }
.al-page-btn--disabled { opacity: .35; cursor: not-allowed; }
</style>
