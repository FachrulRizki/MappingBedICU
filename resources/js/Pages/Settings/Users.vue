<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout    from '@/Layouts/AppLayout.vue';
import AlertModal   from '@/Components/AlertModal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';

const props = defineProps({
    users: { type: Array,  default: () => [] },
    flash: { type: Object, default: () => ({}) },
});

const alert   = ref({ show: false, type: 'success', title: '', message: '' });
const confirm = ref({ show: false, action: null, title: '', message: '' });

const showAlert   = (t, h, m) => { alert.value = { show: true, type: t, title: h, message: m }; };
const openConfirm = (cfg)      => { confirm.value = { show: true, ...cfg }; };
const doConfirm   = ()         => { confirm.value.action?.(); confirm.value.show = false; };

watch(() => props.flash, (f) => {
    if (f?.success) showAlert('success', 'Berhasil', f.success);
    if (f?.error)   showAlert('error',   'Gagal',    f.error);
}, { immediate: true, deep: true });

// Toggle aktif — satu klik, confirm dulu
const toggleActive = (u) => openConfirm({
    title:   u.is_active ? 'Nonaktifkan User?' : 'Aktifkan User?',
    message: u.is_active
        ? `${u.name} tidak akan bisa login sampai diaktifkan kembali.`
        : `${u.name} akan bisa login kembali.`,
    danger:  u.is_active,
    action:  () => {
        useForm({ is_active: !u.is_active }).put(route('settings.users.update', u.id));
    },
});

const getInitial = (name) => name?.charAt(0)?.toUpperCase() ?? '?';
</script>

<template>
    <AppLayout :flash="flash" page-title="Kelola User">
        <AlertModal   v-bind="alert"   @close="alert.show = false"/>
        <ConfirmModal v-bind="confirm" @confirm="doConfirm" @cancel="confirm.show = false"/>

        <div class="p-4 sm:p-6 space-y-4" style="font-family:'Inter','Plus Jakarta Sans',sans-serif">

            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                <div>
                    <h1 class="font-bold text-lg" style="color:var(--text-primary)">Kelola User</h1>
                    <p class="text-sm mt-0.5" style="color:var(--text-secondary)">
                        User terdaftar otomatis saat login SSO. Data di-sync dari Keycloak setiap login.
                    </p>
                </div>
                <div class="flex items-center gap-2 text-xs px-3 py-2 rounded-xl flex-shrink-0"
                    style="background:rgba(0,168,132,0.08); border:1px solid rgba(0,168,132,0.2); color:#00A884">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    Dikelola via Keycloak SSO
                </div>
            </div>

            <!-- Tabel User -->
            <div class="card-dark overflow-hidden">
                <div class="px-5 py-3.5 flex items-center justify-between"
                    style="border-bottom:1px solid var(--border-default)">
                    <p class="text-sm font-semibold" style="color:var(--text-primary)">Daftar User</p>
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full"
                        style="background:rgba(0,168,132,0.12); color:#00A884">
                        {{ users.length }} user
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full dark-table" style="min-width:680px">
                        <thead>
                            <tr>
                                <th class="text-left px-5">User</th>
                                <th class="text-left px-4">Role</th>
                                <th class="text-left px-4">Bangsal</th>
                                <th class="text-left px-4">Provider</th>
                                <th class="text-left px-4">Status</th>
                                <th class="text-left px-4">Bergabung</th>
                                <th class="text-center px-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="u in users" :key="u.id">
                                <td class="px-5">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-sm flex-shrink-0"
                                            :style="`background:${u.role_color}22; color:${u.role_color}`">
                                            {{ getInitial(u.name) }}
                                        </div>
                                        <div>
                                            <p class="font-semibold text-sm" style="color:var(--text-primary)">{{ u.name }}</p>
                                            <p class="text-xs font-mono" style="color:var(--text-secondary)">
                                                {{ u.email ?? u.username }}
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4">
                                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full"
                                        :style="`background:${u.role_color}20; color:${u.role_color}`">
                                        {{ u.role_label }}
                                    </span>
                                </td>
                                <td class="px-4 text-xs font-mono" style="color:var(--text-secondary)">
                                    {{ u.ward_ids?.length ? u.ward_ids.join(', ') : '—' }}
                                </td>
                                <td class="px-4">
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                        :style="u.auth_provider === 'keycloak'
                                            ? 'background:rgba(79,70,229,0.12); color:#6366f1'
                                            : 'background:rgba(0,168,132,0.12); color:#00A884'">
                                        {{ u.auth_provider === 'keycloak' ? 'SSO' : 'Lokal' }}
                                    </span>
                                </td>
                                <td class="px-4">
                                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                        :style="u.is_active
                                            ? 'background:rgba(61,219,138,0.12); color:#3DDB8A'
                                            : 'background:rgba(231,76,60,0.12); color:#E74C3C'">
                                        {{ u.is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="px-4 text-xs" style="color:var(--text-secondary)">{{ u.created_at }}</td>
                                <td class="px-4 text-center">
                                    <button @click="toggleActive(u)"
                                        :title="u.is_active ? 'Nonaktifkan' : 'Aktifkan'"
                                        class="p-1.5 rounded-lg transition-all"
                                        :style="u.is_active
                                            ? 'background:rgba(231,76,60,0.1); color:#E74C3C; border:1px solid rgba(231,76,60,0.2)'
                                            : 'background:rgba(61,219,138,0.1); color:#3DDB8A; border:1px solid rgba(61,219,138,0.2)'">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path v-if="u.is_active" stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                            <path v-else stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Info -->
            <div class="p-4 rounded-xl text-xs space-y-1"
                style="background:rgba(79,70,229,0.06); border:1px solid rgba(79,70,229,0.15)">
                <p class="font-bold" style="color:#6366f1">ℹ Tentang User Management</p>
                <p style="color:var(--text-secondary)">
                    Semua data user (nama, role, ward_ids, permissions) dikelola dan di-sync otomatis dari Keycloak SSO setiap login.
                    Satu-satunya yang bisa dikelola dari sini adalah <strong style="color:var(--text-primary)">status aktif</strong> — untuk blokir akses darurat tanpa harus masuk Keycloak Admin Console.
                </p>
            </div>

        </div>
    </AppLayout>
</template>
