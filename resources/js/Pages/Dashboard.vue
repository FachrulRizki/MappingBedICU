<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout    from '@/Layouts/AppLayout.vue';
import StatCard     from '@/Components/StatCard.vue';
import BedGrid      from '@/Components/BedGrid.vue';
import AlertModal   from '@/Components/AlertModal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';

// ── Props ──────────────────────────────────────────────────────────────
const props = defineProps({
    tahapDaftar:      { type: Array,  default: () => [] },
    tahapIgd:         { type: Array,  default: () => [] },
    tahapSpri:        { type: Array,  default: () => [] },
    tahapNungguKamar: { type: Array,  default: () => [] },
    tahapBooking:     { type: Array,  default: () => [] },
    tahapDiIcu:       { type: Array,  default: () => [] },
    semuaKamar:       { type: Array,  default: () => [] },
    kamarKosong:      { type: Array,  default: () => [] },
    masterKelas:      { type: Array,  default: () => [] },
    flash:            { type: Object, default: () => ({}) },
});

// ── Computed stats ─────────────────────────────────────────────────────
const bedKosong  = computed(() => props.semuaKamar.filter(k => k.Status === 'KOSONG').length);
const bedBooking = computed(() => props.semuaKamar.filter(k => k.Status === 'BOOKING').length);
const bedTerisi  = computed(() => props.semuaKamar.filter(k => k.Status === 'ISI').length);
const totalBed   = computed(() => props.semuaKamar.length);
const occupancyPct = computed(() =>
    totalBed.value > 0 ? Math.round((bedTerisi.value / totalBed.value) * 100) : 0
);
const totalPasienAktif = computed(() =>
    props.tahapDaftar.length + props.tahapIgd.length + props.tahapSpri.length +
    props.tahapNungguKamar.length + props.tahapBooking.length + props.tahapDiIcu.length
);

// ── Modal state ────────────────────────────────────────────────────────
const alert   = ref({ show: false, type: 'success', title: '', message: '' });
const confirm = ref({ show: false, action: null, title: '', message: '', danger: false });

const showAlert  = (type, title, msg) => { alert.value = { show: true, type, title, message: msg }; };
const openConfirm = (cfg) => { confirm.value = { show: true, ...cfg }; };
const doConfirm   = () => { confirm.value.action?.(); confirm.value.show = false; };

watch(() => props.flash, (f) => {
    if (f?.success) showAlert('success', 'Berhasil!', f.success);
    if (f?.error)   showAlert('error',   'Gagal!',    f.error);
}, { immediate: true, deep: true });

// ── Alokasi bed langsung dari dashboard ────────────────────────────────
const bedPilihan    = ref({});
const bedCocokUntuk = (type) => props.kamarKosong.filter(b => b.kode_kelas === type);

const doAlokasi = (pasien) => {
    const kodeRuang = bedPilihan.value[pasien.id];
    if (!kodeRuang) { showAlert('warning', 'Pilih Bed', 'Silakan pilih bed terlebih dahulu.'); return; }
    const namaBed = props.kamarKosong.find(b => b.Kode_Ruang === kodeRuang)?.nama_ruang ?? kodeRuang;
    openConfirm({
        title:   'Konfirmasi Alokasi Bed',
        message: `Alokasikan ${namaBed} untuk pasien ${pasien.nama_pasien}?`,
        danger:  false,
        action:  () => router.post(route('icu.alokasi_bed', pasien.id), { Kode_Ruang: kodeRuang }),
    });
};

const doMasukRuangan = (pasien) => {
    openConfirm({
        title:   'Antar ke Ruangan?',
        message: `Pasien ${pasien.nama_pasien} akan diantar ke ${pasien.nama_bed}. Bed akan berstatus TERISI.`,
        danger:  false,
        action:  () => router.post(route('icu.masuk_ruangan', pasien.id)),
    });
};

const doPulangkan = (pasien) => {
    openConfirm({
        title:   'Pulangkan Pasien?',
        message: `Pasien ${pasien.nama_pasien} akan dipulangkan dan bed ${pasien.nama_bed} kembali KOSONG.`,
        danger:  true,
        action:  () => router.post(route('icu.pulangkan', pasien.id)),
    });
};

// ── Tab ────────────────────────────────────────────────────────────────
const activeTab = ref('overview');

// ── SVG icons ─────────────────────────────────────────────────────────
const icons = {
    bed:     'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
    clock:   'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
    home:    'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
    user:    'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
    heart:   'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z',
    grid:    'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z',
    arrow:   'M13 7l5 5m0 0l-5 5m5-5H6',
    chart:   'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
    link:    'M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14',
};
</script>

<template>
    <AppLayout :flash="flash" page-title="Dashboard ICU">
        <AlertModal   v-bind="alert"   @close="alert.show = false" />
        <ConfirmModal v-bind="confirm" @confirm="doConfirm" @cancel="confirm.show = false" />

        <div class="p-3 sm:p-5 space-y-4 sm:space-y-5">

            <!-- ══════════════════════════════════════════
                 HERO BANNER
            ══════════════════════════════════════════ -->
            <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-green-800 via-green-700 to-teal-600 p-6 text-white shadow-lg">
                <!-- Grid pattern bg -->
                <div class="absolute inset-0 opacity-10">
                    <svg class="w-full h-full" viewBox="0 0 100 100" preserveAspectRatio="none">
                        <defs>
                            <pattern id="g" width="8" height="8" patternUnits="userSpaceOnUse">
                                <path d="M 8 0 L 0 0 0 8" fill="none" stroke="white" stroke-width="0.5"/>
                            </pattern>
                        </defs>
                        <rect width="100" height="100" fill="url(#g)"/>
                    </svg>
                </div>
                <!-- Cross decoration -->
                <div class="absolute right-8 top-1/2 -translate-y-1/2 opacity-[0.07]">
                    <svg class="w-40 h-40" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M19 8H15V4H9v4H5v6h4v4h6v-4h4V8z"/>
                    </svg>
                </div>

                <div class="relative">
                    <div class="mb-3 sm:mb-0">
                        <span class="text-xs bg-white/20 px-2.5 py-0.5 rounded-full font-semibold tracking-widest uppercase">
                            Universal Health Coverage
                        </span>
                        <h2 class="text-lg sm:text-xl font-bold mt-2 leading-tight">Monitoring Alur Pasien ICU</h2>
                        <p class="text-green-100 text-xs sm:text-sm mt-1">Pantau bed, alur pasien, dan alokasi secara real-time</p>
                    </div>
                    <!-- Summary numbers — grid di mobile, flex di desktop -->
                    <div class="grid grid-cols-3 sm:flex sm:items-center gap-3 sm:gap-5 mt-4 sm:mt-3">
                        <div class="text-center">
                            <p class="text-2xl sm:text-3xl font-bold leading-none">{{ occupancyPct }}%</p>
                            <p class="text-green-200 text-xs mt-1">Hunian Bed</p>
                        </div>
                        <div class="hidden sm:block h-12 w-px bg-white/20"></div>
                        <div class="text-center">
                            <p class="text-2xl sm:text-3xl font-bold leading-none">{{ totalPasienAktif }}</p>
                            <p class="text-green-200 text-xs mt-1">Pasien Aktif</p>
                        </div>
                        <div class="hidden sm:block h-12 w-px bg-white/20"></div>
                        <div class="text-center">
                            <p class="text-2xl sm:text-3xl font-bold leading-none">{{ totalBed }}</p>
                            <p class="text-green-200 text-xs mt-1">Total Bed</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════════════
                 STAT CARDS
            ══════════════════════════════════════════ -->
            <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-3">
                <StatCard label="Bed Kosong"   :value="bedKosong"              :sub="`dari ${totalBed} bed`"  :icon="icons.bed"   color="green" />
                <StatCard label="Booking"      :value="bedBooking"             sub="Menunggu transfer"        :icon="icons.clock" color="amber" />
                <StatCard label="Terisi"       :value="bedTerisi"              :sub="`${occupancyPct}% hunian`" :icon="icons.home" color="rose" />
                <StatCard label="Pendaftaran"  :value="tahapDaftar.length"     sub="Antrian masuk"            :icon="icons.user"  color="blue"  />
                <StatCard label="Di IGD"       :value="tahapIgd.length"        sub="Sedang triase"            :icon="icons.heart" color="amber" />
                <StatCard label="Di ICU"       :value="tahapDiIcu.length"      sub="Pasien dirawat"           :icon="icons.heart" color="teal"  />
            </div>

            <!-- ══════════════════════════════════════════
                 QUICK LINKS ke halaman detail
            ══════════════════════════════════════════ -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <a v-for="link in [
                    { label: 'Pendaftaran', sub: `${tahapDaftar.length} antrian`,   href: '/icu/pendaftaran', color: 'border-gray-200 hover:border-gray-300',   dot: 'bg-gray-400'   },
                    { label: 'IGD & SPRI',  sub: `${tahapIgd.length + tahapSpri.length} proses`,  href: '/icu/spri',        color: 'border-amber-200 hover:border-amber-300', dot: 'bg-amber-500'  },
                    { label: 'Alokasi Bed', sub: `${tahapNungguKamar.length} menunggu`, href: '/icu/alokasi-bed',  color: 'border-rose-200 hover:border-rose-300',   dot: 'bg-rose-500'   },
                    { label: 'Pasien ICU',  sub: `${tahapDiIcu.length} aktif`,      href: '/icu/pasien-icu',   color: 'border-green-200 hover:border-green-300', dot: 'bg-green-500'  },
                ]" :key="link.href" :href="link.href"
                    :class="['flex items-center justify-between p-3.5 bg-white rounded-xl border transition-all hover:shadow-sm group', link.color]">
                    <div>
                        <p class="text-sm font-semibold text-gray-700 group-hover:text-gray-900">{{ link.label }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ link.sub }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span :class="['w-2 h-2 rounded-full', link.dot]"></span>
                        <svg class="w-4 h-4 text-gray-300 group-hover:text-gray-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="icons.arrow"/>
                        </svg>
                    </div>
                </a>
            </div>

            <!-- ══════════════════════════════════════════
                 TAB SWITCH — scroll horizontal di mobile
            ══════════════════════════════════════════ -->
            <div class="overflow-x-auto pb-1">
                <div class="flex items-center gap-1 bg-gray-100 rounded-xl p-1 w-max min-w-full sm:w-fit sm:min-w-0">
                    <button v-for="tab in [
                        { key: 'overview', label: 'Overview',    icon: icons.chart },
                        { key: 'alokasi',  label: 'Alokasi Bed', icon: icons.bed   },
                        { key: 'icu',      label: 'Pasien ICU',  icon: icons.heart },
                        { key: 'bed',      label: 'Denah Bed',   icon: icons.grid  },
                    ]" :key="tab.key" @click="activeTab = tab.key"
                        :class="['px-3 py-1.5 rounded-lg text-xs sm:text-sm font-semibold transition-all flex items-center gap-1.5 whitespace-nowrap flex-shrink-0',
                            activeTab === tab.key ? 'bg-white shadow-sm text-green-700' : 'text-gray-500 hover:text-gray-700']"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="tab.icon"/>
                        </svg>
                        {{ tab.label }}
                    </button>
                </div>
            </div>

            <!-- ══════════════════════════════════════════
                 TAB: OVERVIEW ALUR
            ══════════════════════════════════════════ -->
            <div v-if="activeTab === 'overview'" class="grid grid-cols-1 lg:grid-cols-2 gap-4">

                <!-- Ringkasan per tahap -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                    <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-4 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" :d="icons.chart"/></svg>
                        Distribusi Alur Pasien
                    </p>
                    <div class="space-y-3">
                        <div v-for="item in [
                            { label: 'Pendaftaran',    val: tahapDaftar.length,      color: 'bg-gray-400',   href: '/icu/pendaftaran' },
                            { label: 'IGD Triase',     val: tahapIgd.length,         color: 'bg-amber-400',  href: '/icu/igd'  },
                            { label: 'SPRI Dibuat',    val: tahapSpri.length,        color: 'bg-teal-500',   href: '/icu/spri' },
                            { label: 'Tunggu Kamar',   val: tahapNungguKamar.length, color: 'bg-rose-500',   href: '/icu/alokasi-bed' },
                            { label: 'Booking Kamar',  val: tahapBooking.length,     color: 'bg-indigo-500', href: '/icu/alokasi-bed' },
                            { label: 'Di ICU',         val: tahapDiIcu.length,       color: 'bg-green-600',  href: '/icu/pasien-icu' },
                        ]" :key="item.label" class="flex items-center gap-3">
                            <span :class="['w-2.5 h-2.5 rounded-full flex-shrink-0', item.color]"></span>
                            <a :href="item.href" class="text-sm text-gray-600 flex-1 hover:text-green-700 transition-colors">{{ item.label }}</a>
                            <span class="text-sm font-bold text-gray-800 w-6 text-right">{{ item.val }}</span>
                            <div class="w-24 bg-gray-100 rounded-full h-2">
                                <div :class="['h-2 rounded-full transition-all duration-500', item.color]"
                                    :style="{ width: totalPasienAktif > 0 ? (item.val / totalPasienAktif * 100) + '%' : '0%' }">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status bed summary -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                    <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-4 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" :d="icons.bed"/></svg>
                        Kapasitas Bed ICU
                    </p>

                    <!-- Occupancy bar besar -->
                    <div class="mb-5">
                        <div class="flex justify-between text-xs text-gray-500 mb-1.5">
                            <span>Tingkat Hunian</span>
                            <span :class="['font-bold', occupancyPct > 80 ? 'text-rose-600' : occupancyPct > 50 ? 'text-amber-600' : 'text-green-600']">
                                {{ occupancyPct }}%
                            </span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-3">
                            <div :class="['h-3 rounded-full transition-all duration-700', occupancyPct > 80 ? 'bg-rose-500' : occupancyPct > 50 ? 'bg-amber-500' : 'bg-green-500']"
                                :style="{ width: occupancyPct + '%' }">
                            </div>
                        </div>
                    </div>

                    <!-- 3 kolom stat -->
                    <div class="grid grid-cols-3 gap-3">
                        <div class="text-center p-3 bg-green-50 rounded-xl border border-green-100">
                            <p class="text-2xl font-bold text-green-700">{{ bedKosong }}</p>
                            <p class="text-xs text-green-600 font-medium mt-0.5">Kosong</p>
                        </div>
                        <div class="text-center p-3 bg-amber-50 rounded-xl border border-amber-100">
                            <p class="text-2xl font-bold text-amber-600">{{ bedBooking }}</p>
                            <p class="text-xs text-amber-600 font-medium mt-0.5">Booking</p>
                        </div>
                        <div class="text-center p-3 bg-rose-50 rounded-xl border border-rose-100">
                            <p class="text-2xl font-bold text-rose-600">{{ bedTerisi }}</p>
                            <p class="text-xs text-rose-600 font-medium mt-0.5">Terisi</p>
                        </div>
                    </div>

                    <!-- Perlu perhatian -->
                    <div v-if="tahapNungguKamar.length > 0"
                        class="mt-4 flex items-center gap-2.5 bg-rose-50 border border-rose-100 rounded-xl px-3 py-2.5">
                        <svg class="w-4 h-4 text-rose-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <p class="text-xs text-rose-700 font-medium">
                            <strong>{{ tahapNungguKamar.length }} pasien</strong> menunggu alokasi bed.
                            <a href="/icu/alokasi-bed" class="underline hover:no-underline">Alokasi sekarang →</a>
                        </p>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════════════
                 TAB: ALOKASI BED (langsung dari dashboard)
            ══════════════════════════════════════════ -->
            <div v-if="activeTab === 'alokasi'" class="space-y-4">

                <!-- Waiting -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100 bg-rose-50/50">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-rose-500"></span>
                            <span class="text-sm font-bold text-gray-700">Menunggu Alokasi Bed</span>
                        </div>
                        <span class="text-xs bg-rose-100 text-rose-700 font-bold px-2.5 py-0.5 rounded-full">{{ tahapNungguKamar.length }}</span>
                    </div>

                    <div v-if="tahapNungguKamar.length === 0" class="text-center py-10 text-gray-300">
                        <svg class="w-8 h-8 mx-auto mb-2 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-sm">Tidak ada pasien menunggu bed</p>
                    </div>

                    <div v-for="p in tahapNungguKamar" :key="p.id"
                        class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-4 py-3.5 border-b border-gray-50 last:border-0">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-8 h-8 rounded-full bg-rose-100 flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" :d="icons.user"/></svg>
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-800 text-sm truncate">{{ p.nama_pasien }}</p>
                                <p class="text-xs text-gray-400">{{ p.No_Reg }}</p>
                            </div>
                            <span class="text-xs bg-rose-100 text-rose-700 font-semibold px-2 py-0.5 rounded-full flex-shrink-0">{{ p.required_bed_type }}</span>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <select v-model="bedPilihan[p.id]"
                                :disabled="bedCocokUntuk(p.required_bed_type).length === 0"
                                class="flex-1 sm:flex-none text-sm rounded-xl border border-gray-200 px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-green-400 bg-white disabled:opacity-40 sm:min-w-[160px]">
                                <option value="" disabled>-- Pilih Bed --</option>
                                <option v-for="bed in bedCocokUntuk(p.required_bed_type)" :key="bed.Kode_Ruang" :value="bed.Kode_Ruang">
                                    {{ bed.nama_ruang }}
                                </option>
                            </select>
                            <button v-if="bedCocokUntuk(p.required_bed_type).length > 0"
                                @click="doAlokasi(p)"
                                class="flex-shrink-0 flex items-center gap-1.5 bg-green-600 hover:bg-green-700 text-white text-xs font-semibold px-3.5 py-2 rounded-xl transition-colors whitespace-nowrap">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                Alokasi
                            </button>
                            <span v-else class="text-xs text-gray-400 bg-gray-100 px-3 py-2 rounded-xl whitespace-nowrap">Bed penuh</span>
                        </div>
                    </div>
                </div>

                <!-- Booking — siap transfer -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100 bg-green-50/50">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-green-500"></span>
                            <span class="text-sm font-bold text-gray-700">Sudah Dapat Kamar — Siap Transfer</span>
                        </div>
                        <span class="text-xs bg-green-100 text-green-700 font-bold px-2.5 py-0.5 rounded-full">{{ tahapBooking.length }}</span>
                    </div>

                    <div v-if="tahapBooking.length === 0" class="text-center py-10 text-gray-300">
                        <p class="text-sm">Tidak ada pasien booking kamar</p>
                    </div>

                    <div v-for="p in tahapBooking" :key="'b'+p.id"
                        class="flex items-center justify-between gap-4 px-5 py-3.5 border-b border-gray-50 last:border-0 flex-wrap">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                                <svg class="w-4 h-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" :d="icons.user"/></svg>
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-800 text-sm truncate">{{ p.nama_pasien }}</p>
                                <p class="text-xs text-gray-400">{{ p.No_Reg }} · {{ p.required_bed_type }}</p>
                            </div>
                            <span class="text-xs bg-green-100 text-green-700 font-medium px-2 py-0.5 rounded-full flex-shrink-0">
                                🏥 {{ p.nama_bed }}
                            </span>
                        </div>
                        <button @click="doMasukRuangan(p)"
                            class="flex items-center gap-1.5 bg-green-700 hover:bg-green-800 text-white text-xs font-semibold px-3.5 py-2 rounded-xl transition-colors whitespace-nowrap">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" :d="icons.arrow"/></svg>
                            Antar ke Ruangan
                        </button>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════════════
                 TAB: PASIEN DI ICU
            ══════════════════════════════════════════ -->
            <div v-if="activeTab === 'icu'">
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100 bg-green-50/50">
                        <div class="flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-green-500 pulse-green"></span>
                            <span class="text-sm font-bold text-gray-700">Pasien Aktif di ICU</span>
                        </div>
                        <span class="text-xs bg-green-100 text-green-700 font-bold px-2.5 py-0.5 rounded-full">{{ tahapDiIcu.length }}</span>
                    </div>

                    <div v-if="tahapDiIcu.length === 0" class="text-center py-16 text-gray-300">
                        <svg class="w-10 h-10 mx-auto mb-2 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" :d="icons.heart"/></svg>
                        <p class="text-sm">Belum ada pasien di ICU</p>
                    </div>

                    <div v-for="p in tahapDiIcu" :key="p.id"
                        class="flex items-center justify-between gap-4 px-5 py-3.5 border-b border-gray-50 last:border-0 flex-wrap hover:bg-gray-50/50 transition-colors">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="relative">
                                <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center">
                                    <svg class="w-4 h-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" :d="icons.user"/></svg>
                                </div>
                                <span class="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 bg-green-500 rounded-full border-2 border-white pulse-green"></span>
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-800 text-sm truncate">{{ p.nama_pasien }}</p>
                                <p class="text-xs text-gray-400">{{ p.No_Reg }} · MR: {{ p.No_MR }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 flex-shrink-0">
                            <div class="text-right">
                                <p class="text-xs font-semibold text-gray-700">{{ p.nama_bed }}</p>
                                <p class="text-xs text-gray-400">{{ p.required_bed_type }}</p>
                            </div>
                            <button @click="doPulangkan(p)"
                                class="flex items-center gap-1.5 border border-rose-200 text-rose-600 hover:bg-rose-600 hover:text-white text-xs font-semibold px-3 py-1.5 rounded-xl transition-all">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                Pulang
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════════════
                 TAB: DENAH BED
            ══════════════════════════════════════════ -->
            <div v-if="activeTab === 'bed'"
                class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="font-bold text-gray-800">Denah Bed ICU — Real-time</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Diperbarui otomatis setiap 30 detik</p>
                    </div>
                    <div class="flex items-center gap-3 text-xs text-gray-500">
                        <span class="flex items-center gap-1"><span class="w-3 h-2 rounded border-l-2 border-l-green-500 bg-green-50"></span> Kosong</span>
                        <span class="flex items-center gap-1"><span class="w-3 h-2 rounded border-l-2 border-l-amber-500 bg-amber-50"></span> Booking</span>
                        <span class="flex items-center gap-1"><span class="w-3 h-2 rounded border-l-2 border-l-rose-500 bg-rose-50"></span> Terisi</span>
                    </div>
                </div>
                <BedGrid :kamar="semuaKamar" />
            </div>

        </div>
    </AppLayout>
</template>
