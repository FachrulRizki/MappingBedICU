<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout    from '@/Layouts/AppLayout.vue';
import AlertModal   from '@/Components/AlertModal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import BedGrid      from '@/Components/BedGrid.vue';

const props = defineProps({
    waiting:     { type: Array, default: () => [] },
    booking:     { type: Array, default: () => [] },
    semuaKamar:  { type: Array, default: () => [] },
    kamarKosong: { type: Array, default: () => [] },
    flash:       { type: Object, default: () => ({}) },
});

const alert   = ref({ show: false, type: 'success', title: '', message: '' });
const confirm = ref({ show: false, action: null, title: '', message: '', danger: false });
const showAlert = (t, h, m) => { alert.value = { show: true, type: t, title: h, message: m }; };
watch(() => props.flash, (f) => {
    if (f?.success) showAlert('success', 'Berhasil!', f.success);
    if (f?.error)   showAlert('error',   'Gagal!',    f.error);
}, { immediate: true, deep: true });
const openConfirm = (cfg) => { confirm.value = { show: true, ...cfg }; };
const doConfirm   = () => { confirm.value.action?.(); confirm.value.show = false; };

const activeTab  = ref('alokasi');
const bedPilihan = ref({});
const bedCocok   = (type) => props.kamarKosong.filter(b => b.nama_kelas === type);

const doAlokasi = (id, nama) => {
    if (!bedPilihan.value[id]) { showAlert('warning', 'Pilih Bed', 'Silakan pilih bed terlebih dahulu.'); return; }
    openConfirm({ title: 'Alokasi Bed?', message: `Alokasikan bed untuk ${nama}?`, danger: false,
        action: () => router.post(route('icu.alokasi_bed', id), { Kode_Ruang: bedPilihan.value[id] }) });
};
const doMasuk = (id, nama) => openConfirm({ title: 'Antar ke Ruangan?', message: `${nama} akan diantar ke ruangan ICU.`, danger: false,
    action: () => router.post(route('icu.masuk_ruangan', id)) });
</script>

<template>
    <AppLayout :flash="flash" page-title="Alokasi Bed ICU">
        <AlertModal   v-bind="alert"   @close="alert.show = false"/>
        <ConfirmModal v-bind="confirm" @confirm="doConfirm" @cancel="confirm.show = false"/>

        <div class="p-4 sm:p-6 space-y-4" style="font-family:'DM Sans',sans-serif">
            <div>
                <h1 class="font-sora font-bold text-lg" style="color:var(--text-primary)">Alokasi Bed ICU</h1>
                <p class="text-sm mt-0.5" style="color:var(--text-secondary)">Pencocokan dan alokasi bed untuk pasien</p>
            </div>

            <!-- Tabs -->
            <div class="flex gap-1 p-1 rounded-xl w-full sm:w-fit overflow-x-auto" style="background:var(--bg-main); border:1px solid var(--border-default)">
                <button @click="activeTab='alokasi'" class="flex-1 sm:flex-none px-4 py-1.5 rounded-lg text-sm font-semibold transition-all flex items-center gap-1.5 whitespace-nowrap"
                    :style="activeTab==='alokasi' ? 'background:var(--bg-surface); color:#008C6E; box-shadow:0 1px 4px rgba(26,158,143,.1)' : 'color:var(--text-secondary)'">
                    Waiting & Booking
                    <span class="text-xs px-1.5 rounded-full" style="background:#FDF2F2; color:#E05A5A">{{ waiting.length + booking.length }}</span>
                </button>
                <button @click="activeTab='denah'" class="flex-1 sm:flex-none px-4 py-1.5 rounded-lg text-sm font-semibold transition-all flex items-center gap-1.5 whitespace-nowrap"
                    :style="activeTab==='denah' ? 'background:var(--bg-surface); color:#008C6E; box-shadow:0 1px 4px rgba(26,158,143,.1)' : 'color:var(--text-secondary)'">
                    Denah Bed
                    <span class="text-xs px-1.5 rounded-full" style="background:#F0FBF9; color:#008C6E">{{ semuaKamar.length }}</span>
                </button>
            </div>

            <!-- Alokasi -->
            <div v-if="activeTab==='alokasi'" class="space-y-5">
                <!-- Waiting -->
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide mb-3 flex items-center gap-1.5" style="color:var(--text-secondary)">
                        <span class="w-2 h-2 rounded-full" style="background:#E05A5A"></span> Menunggu Alokasi ({{ waiting.length }})
                    </p>
                    <div v-if="waiting.length===0" class="card-teal text-center py-8" style="color:var(--text-secondary)"><p class="text-sm">Tidak ada pasien menunggu</p></div>
                    <div v-for="p in waiting" :key="p.id" class="card-teal p-4 mb-3">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold" style="color:var(--text-primary)">{{ p.nama_pasien }}</p>
                                <p class="text-xs font-mono mt-0.5" style="color:var(--text-secondary)">{{ p.No_Reg }}</p>
                                <span class="inline-block mt-1.5 text-xs px-2 py-0.5 rounded-full font-semibold" style="background:#FDE8CC; color:#E67E22">{{ p.required_bed_type }}</span>
                            </div>
                            <div class="flex gap-2 flex-wrap">
                                <select v-model="bedPilihan[p.id]" :disabled="bedCocok(p.required_bed_type).length===0"
                                    class="flex-1 sm:flex-none text-sm px-3 py-2 rounded-xl outline-none disabled:opacity-40"
                                    style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary); font-family:'DM Mono',monospace; min-width:160px">
                                    <option value="" disabled>-- Pilih Bed --</option>
                                    <option v-for="b in bedCocok(p.required_bed_type)" :key="b.Kode_Ruang" :value="b.Kode_Ruang">{{ b.nama_ruang }}</option>
                                </select>
                                <button v-if="bedCocok(p.required_bed_type).length>0" @click="doAlokasi(p.id,p.nama_pasien)"
                                    class="flex items-center gap-1.5 text-sm font-semibold px-4 py-2 rounded-xl text-white flex-shrink-0" style="background:#008C6E">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    Alokasi
                                </button>
                                <span v-else class="text-sm px-3 py-2 rounded-xl" style="background:var(--bg-main); color:var(--text-secondary); border:1px solid var(--border-default)">Tidak ada bed</span>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Booking -->
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide mb-3 flex items-center gap-1.5" style="color:var(--text-secondary)">
                        <span class="w-2 h-2 rounded-full" style="background:#008C6E"></span> Siap Transfer ({{ booking.length }})
                    </p>
                    <div v-if="booking.length===0" class="card-teal text-center py-8" style="color:var(--text-secondary)"><p class="text-sm">Tidak ada booking</p></div>
                    <div v-for="p in booking" :key="p.id" class="card-teal p-4 mb-3" style="border-left:4px solid #008C6E">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold" style="color:var(--text-primary)">{{ p.nama_pasien }}</p>
                                <p class="text-xs font-mono mt-0.5" style="color:var(--text-secondary)">{{ p.No_Reg }} · {{ p.required_bed_type }}</p>
                                <span class="inline-block mt-1.5 text-xs px-2 py-0.5 rounded-full" style="background:#F0FBF9; color:#008C6E">🏥 {{ p.nama_bed }}</span>
                            </div>
                            <button @click="doMasuk(p.id, p.nama_pasien)"
                                class="flex items-center justify-center gap-1.5 text-sm font-semibold px-4 py-2 rounded-xl text-white w-full sm:w-auto flex-shrink-0" style="background:#008C6E">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                Antar ke Ruangan
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Denah Bed -->
            <div v-if="activeTab==='denah'" class="card-teal p-5">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-sm font-semibold" style="color:var(--text-primary)">Denah Bed ICU — Real-time</p>
                        <p class="text-xs mt-0.5" style="color:var(--text-secondary)">Status diperbarui setiap 30 detik</p>
                    </div>
                </div>
                <BedGrid :kamar="semuaKamar"/>
            </div>
        </div>
    </AppLayout>
</template>
