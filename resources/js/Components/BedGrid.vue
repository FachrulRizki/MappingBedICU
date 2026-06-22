<script setup>
defineProps({ kamar: { type: Array, default: () => [] } });

const bedTheme = (bed) => {
    const s = (bed.Status ?? '').toUpperCase();
    if (s === 'KOSONG')  return { stripe:'#10B981', badgeBg:'#ECFDF5', badgeColor:'#059669', label:'✓ Tersedia' };
    if (s === 'BOOKING') return { stripe:'#F59E0B', badgeBg:'#FEF3C7', badgeColor:'#D97706', label:'⏳ Booking' };
    return                      { stripe:'#00A884', badgeBg:'#ECFDF5', badgeColor:'#00A884', label:'● Terisi'  };
};
</script>

<template>
<div style="font-family:'Inter','Plus Jakarta Sans',sans-serif">
  <div v-if="kamar.length===0" style="text-align:center;padding:40px;color:#94A3B8">
    <p style="font-size:13px">Tidak ada data bed</p>
  </div>
  <div v-else style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px">
    <div v-for="bed in kamar" :key="bed.Kode_Ruang"
      style="background:#fff;border-radius:12px;border:1px solid rgba(0,0,0,0.07);box-shadow:0 1px 4px rgba(0,0,0,0.04);overflow:hidden;transition:all .18s"
      @mouseenter="$event.currentTarget.style.transform='translateY(-2px)';$event.currentTarget.style.boxShadow='0 6px 14px rgba(0,0,0,0.08)'"
      @mouseleave="$event.currentTarget.style.transform='';$event.currentTarget.style.boxShadow='0 1px 4px rgba(0,0,0,0.04)'">
      <!-- Stripe -->
      <div style="height:4px" :style="`background:${bedTheme(bed).stripe}`"></div>
      <div style="padding:11px">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:7px">
          <div style="min-width:0;flex:1">
            <p style="font-size:12px;font-weight:700;color:#0F172A;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ bed.nama_ruang }}</p>
            <p style="font-size:10px;color:#94A3B8;font-family:'DM Mono',monospace;margin-top:1px">{{ bed.Kode_Ruang }}</p>
          </div>
          <span style="width:8px;height:8px;border-radius:50%;flex-shrink:0;margin-top:2px;margin-left:4px" :style="`background:${bedTheme(bed).stripe}`"></span>
        </div>
        <span style="font-size:10px;font-weight:700;padding:2px 7px;border-radius:20px;display:inline-block"
          :style="`background:${bedTheme(bed).badgeBg};color:${bedTheme(bed).badgeColor}`">
          {{ bedTheme(bed).label }}
        </span>
        <!-- Patient info -->
        <template v-if="bed.No_MR">
          <div style="margin-top:7px;padding-top:7px;border-top:1px solid #F1F5F9">
            <p style="font-size:10px;color:#94A3B8;font-family:'DM Mono',monospace">MR: {{ bed.No_MR }}</p>
            <p v-if="bed.nama_pasien" style="font-size:11px;font-weight:600;color:#0F172A;margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" :title="bed.nama_pasien">{{ bed.nama_pasien }}</p>
            <p v-if="bed.diagnosa" style="font-size:10px;color:#64748B;margin-top:1px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" :title="bed.diagnosa">{{ bed.diagnosa }}</p>
            <p v-if="bed.asal_ruang" style="font-size:10px;color:#94A3B8;margin-top:1px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">← {{ bed.asal_ruang }}</p>
          </div>
        </template>
        <p v-else style="font-size:10px;color:#10B981;margin-top:6px;font-weight:500">Tersedia</p>
      </div>
    </div>
  </div>
</div>
</template>
