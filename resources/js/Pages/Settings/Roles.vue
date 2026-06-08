<script setup>
import { ref, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout  from '@/Layouts/AppLayout.vue';
import AlertModal from '@/Components/AlertModal.vue';

const props = defineProps({
    matrix:     { type: Object, default: () => ({}) },
    roles:      { type: Array,  default: () => [] },
    userCounts: { type: Object, default: () => ({}) },
    flash:      { type: Object, default: () => ({}) },
});

const alert = ref({ show: false, type: 'success', title: '', message: '' });
const showAlert = (t, h, m) => { alert.value = { show: true, type: t, title: h, message: m }; };

watch(() => props.flash, (f) => {
    if (f?.success) showAlert('success', 'Berhasil!', f.success);
    if (f?.error)   showAlert('error',   'Gagal!',    f.error);
}, { immediate: true, deep: true });

// ── Active tab ─────────────────────────────────────────────
const activeTab = ref('matrix');

// ── Permission label formatter ─────────────────────────────
const permLabel = (key) => ({
    view:              'Lihat',
    buat_booking:      'Buat Booking',
    konfirmasi_icu:    'Konfirmasi ICU',
    tolak_icu:         'Tolak ICU',
    validasi_admisi:   'Validasi Admisi',
    link_pasien_tiba:  'Link Pasien Tiba',
    verifikasi_bed:    'Verifikasi Bed',
    konfirmasi_masuk:  'Konfirmasi Masuk',
    pulangkan:         'Pulangkan Pasien',
    buat_surat:        'Buat Surat',
    approve_admisi:    'Approve Admisi',
    tolak_admisi:      'Tolak Admisi',
    booking_bed_icu:   'Booking Bed ICU',
    verifikasi_admisi: 'Verifikasi Admisi',
    tambah:            'Tambah',
    edit:              'Edit',
    hapus:             'Hapus',
    reset_pw:          'Reset Password',
}[key] ?? key);

// ── Check if role has permission ───────────────────────────
const hasPerm = (roles, roleValue) => roles.includes(roleValue);

// ── Role color ─────────────────────────────────────────────
const roleColor = (value) => props.roles.find(r => r.value === value)?.color ?? '#8EA89E';
const roleLabel = (value) => props.roles.find(r => r.value === value)?.label ?? value;

// ── Matrix as flat rows for table ─────────────────────────
const matrixRows = computed(() => {
    const rows = [];
    for (const [moduleKey, module] of Object.entries(props.matrix)) {
        for (const [permKey, allowedRoles] of Object.entries(module.perms)) {
            rows.push({
                moduleKey,
                moduleLabel: module.label,
                permKey,
                permLabel: permLabel(permKey),
                allowedRoles,
            });
        }
    }
    return rows;
});

// ── Role detail: permissions per role ────────────────────
const selectedRole = ref('admin');
const rolePerms = computed(() => {
    const result = {};
    for (const [moduleKey, module] of Object.entries(props.matrix)) {
        result[moduleKey] = {
            label: module.label,
            perms: {},
        };
        for (const [permKey, allowedRoles] of Object.entries(module.perms)) {
            result[moduleKey].perms[permKey] = allowedRoles.includes(selectedRole.value);
        }
    }
    return result;
});
</script>

<template>
    <AppLayout :flash="flash" page-title="Role & Permission">
        <AlertModal v-bind="alert" @close="alert.show = false"/>

        <div class="p-4 sm:p-6 space-y-4" style="font-family:'Plus Jakarta Sans',sans-serif">

            <!-- Header -->
            <div>
                <h1 class="font-bold text-lg" style="color:var(--text-primary)">Role & Permission</h1>
                <p class="text-sm mt-0.5" style="color:var(--text-secondary)">Matriks akses per role dalam sistem</p>
            </div>

            <!-- Role Cards Summary -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div v-for="role in roles" :key="role.value"
                    class="card-dark p-4 flex items-center gap-3 cursor-pointer transition-all"
                    :style="`border-color:${activeTab==='detail' && selectedRole===role.value ? role.color+'60' : 'var(--border-default)'}`"
                    @click="selectedRole = role.value; activeTab = 'detail'">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 font-bold"
                        :style="`background:${role.color}20; color:${role.color}`">
                        {{ role.label.charAt(0) }}
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-bold truncate" :style="`color:${role.color}`">{{ role.label }}</p>
                        <p class="text-xs" style="color:var(--text-secondary)">
                            {{ userCounts[role.value] ?? 0 }} user
                        </p>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex gap-1 p-1 rounded-xl w-full sm:w-fit overflow-x-auto"
                style="background:var(--bg-main); border:1px solid var(--border-default)">
                <button v-for="tab in [
                    { key:'matrix', label:'Matriks Penuh' },
                    { key:'detail', label:'Detail per Role' },
                ]" :key="tab.key"
                    @click="activeTab = tab.key"
                    class="flex-shrink-0 px-4 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-all"
                    :style="activeTab === tab.key
                        ? 'background:var(--bg-surface); color:#2DD9A4; box-shadow:0 1px 4px rgba(45,217,164,.15)'
                        : 'color:var(--text-secondary)'">
                    {{ tab.label }}
                </button>
            </div>

            <!-- ── Matriks Penuh ── -->
            <div v-if="activeTab === 'matrix'" class="card-dark overflow-hidden">
                <div class="px-5 py-3.5" style="border-bottom:1px solid var(--border-default)">
                    <p class="text-sm font-semibold" style="color:var(--text-primary)">Matriks Akses — Semua Role</p>
                    <p class="text-xs mt-0.5" style="color:var(--text-secondary)">✓ = Diizinkan · — = Tidak ada akses</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full" style="min-width:680px">
                        <thead>
                            <tr style="background:var(--table-th-bg)">
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color:var(--text-secondary); min-width:160px">Modul</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color:var(--text-secondary); min-width:140px">Permission</th>
                                <th v-for="role in roles" :key="role.value"
                                    class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide whitespace-nowrap"
                                    :style="`color:${role.color}`">
                                    {{ role.label }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="(row, idx) in matrixRows" :key="`${row.moduleKey}_${row.permKey}`">
                                <tr :style="`border-top:1px solid var(--border-row); background:${idx%2===0?'transparent':'var(--bg-row-hover)'}`">
                                    <td class="px-5 py-2.5">
                                        <span v-if="idx === 0 || matrixRows[idx-1]?.moduleKey !== row.moduleKey"
                                            class="text-xs font-bold" :style="`color:var(--text-primary)`">
                                            {{ row.moduleLabel }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2.5 text-xs" style="color:var(--text-secondary)">
                                        {{ row.permLabel }}
                                    </td>
                                    <td v-for="role in roles" :key="role.value" class="px-4 py-2.5 text-center">
                                        <span v-if="hasPerm(row.allowedRoles, role.value)"
                                            class="inline-flex items-center justify-center w-6 h-6 rounded-full"
                                            :style="`background:${role.color}20; color:${role.color}`">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                            </svg>
                                        </span>
                                        <span v-else class="text-xs" style="color:var(--text-secondary); opacity:0.3">—</span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ── Detail per Role ── -->
            <div v-if="activeTab === 'detail'" class="space-y-4">
                <!-- Role selector -->
                <div class="flex gap-2 flex-wrap">
                    <button v-for="role in roles" :key="role.value"
                        @click="selectedRole = role.value"
                        class="px-4 py-2 rounded-xl text-xs font-bold transition-all"
                        :style="selectedRole === role.value
                            ? `background:${role.color}; color:#fff`
                            : `background:${role.color}15; color:${role.color}; border:1px solid ${role.color}30`">
                        {{ role.label }}
                        <span class="ml-1.5 opacity-70">({{ userCounts[role.value] ?? 0 }})</span>
                    </button>
                </div>

                <!-- Permission cards per modul -->
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    <div v-for="(module, key) in rolePerms" :key="key" class="card-dark p-4">
                        <p class="text-xs font-bold mb-3 pb-2" style="color:var(--text-primary); border-bottom:1px solid var(--border-default)">
                            {{ module.label }}
                        </p>
                        <div class="space-y-2">
                            <div v-for="(allowed, perm) in module.perms" :key="perm"
                                class="flex items-center justify-between">
                                <span class="text-xs" style="color:var(--text-secondary)">{{ permLabel(perm) }}</span>
                                <span class="flex items-center gap-1 text-xs font-semibold"
                                    :style="allowed
                                        ? `color:${roleColor(selectedRole)}`
                                        : 'color:var(--text-secondary); opacity:0.4'">
                                    <svg v-if="allowed" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                    {{ allowed ? 'Diizinkan' : 'Tidak ada' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Info role -->
                <div class="card-dark p-5 flex items-start gap-4">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0 font-bold text-lg"
                        :style="`background:${roleColor(selectedRole)}20; color:${roleColor(selectedRole)}`">
                        {{ roleLabel(selectedRole).charAt(0) }}
                    </div>
                    <div>
                        <p class="font-bold" :style="`color:${roleColor(selectedRole)}`">{{ roleLabel(selectedRole) }}</p>
                        <p class="text-xs mt-1" style="color:var(--text-secondary)">
                            {{ {
                                admin:         'Administrator — Akses penuh ke seluruh sistem tanpa batasan.',
                                admisi:        'Petugas Admisi — Mengelola booking external, validasi, verifikasi pasien masuk dan keluar.',
                                petugas_icu:   'Petugas ICU — Mengkonfirmasi ketersediaan bed, verifikasi pasien masuk, dan memulangkan pasien.',
                                petugas_ruang: 'Petugas Ruang — Membuat Surat Permintaan Rawat ICU untuk pasien di bangsal masing-masing.',
                            }[selectedRole] }}
                        </p>
                        <p class="text-xs mt-2 font-semibold" style="color:var(--text-secondary)">
                            {{ userCounts[selectedRole] ?? 0 }} user dengan role ini
                        </p>
                    </div>
                </div>

                <!-- Catatan penting -->
                <div class="p-4 rounded-xl text-xs space-y-1.5"
                    style="background:rgba(224,146,58,0.08); border:1px solid rgba(224,146,58,0.2)">
                    <p class="font-bold" style="color:#E0923A">⚠ Catatan Penting</p>
                    <p style="color:var(--text-secondary)">Permission di atas bersifat <strong>tetap berdasarkan role</strong>. Untuk mengubah role seorang user, gunakan halaman <a href="/settings/users" style="color:#2DD9A4; text-decoration:underline">Kelola User</a>.</p>
                    <p style="color:var(--text-secondary)">Integrasi Keycloak akan menggantikan mekanisme login ini dan mapping role akan dilakukan dari claim JWT Keycloak.</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
