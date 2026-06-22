<script setup>
import { ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout    from '@/Layouts/AppLayout.vue';
import AlertModal   from '@/Components/AlertModal.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';

const props = defineProps({
    users: { type: Array,  default: () => [] },
    roles: { type: Array,  default: () => [] },
    flash: { type: Object, default: () => ({}) },
});

const alert   = ref({ show: false, type: 'success', title: '', message: '' });
const confirm = ref({ show: false, action: null, title: '', message: '', danger: false });
const showAlert   = (t, h, m) => { alert.value = { show: true, type: t, title: h, message: m }; };
const openConfirm = (cfg) => { confirm.value = { show: true, ...cfg }; };
const doConfirm   = () => { confirm.value.action?.(); confirm.value.show = false; };

watch(() => props.flash, (f) => {
    if (f?.success) showAlert('success', 'Berhasil!', f.success);
    if (f?.error)   showAlert('error',   'Gagal!',    f.error);
}, { immediate: true, deep: true });

// ── Tambah User ────────────────────────────────────────────
const showAddForm = ref(false);
const addForm = useForm({
    name: '', username: '', email: '', password: '', role: '', unit_kerja: '',
});
const submitAdd = () => addForm.post(route('settings.users.store'), {
    onSuccess: () => { addForm.reset(); showAddForm.value = false; },
});

// ── Edit inline ────────────────────────────────────────────
const editId   = ref(null);
const editForm = useForm({ name: '', role: '', unit_kerja: '', is_active: true });

const openEdit = (u) => {
    editId.value = u.id;
    editForm.name       = u.name;
    editForm.role       = u.role;
    editForm.unit_kerja = u.unit_kerja ?? '';
    editForm.is_active  = u.is_active;
};
const cancelEdit = () => { editId.value = null; editForm.reset(); };
const submitEdit = (id) => {
    editForm.put(route('settings.users.update', id), {
        onSuccess: () => { editId.value = null; },
    });
};

// ── Reset Password ─────────────────────────────────────────
const resetId   = ref(null);
const resetForm = useForm({ password: '' });
const openReset = (id) => { resetId.value = id; resetForm.password = ''; };
const submitReset = (id) => {
    resetForm.post(route('settings.users.reset_password', id), {
        onSuccess: () => { resetId.value = null; resetForm.reset(); },
    });
};

// ── Hapus ──────────────────────────────────────────────────
const doHapus = (u) => openConfirm({
    title:   'Hapus User?',
    message: `${u.name} (${u.email}) akan dihapus permanen.`,
    danger:  true,
    action:  () => router.delete(route('settings.users.destroy', u.id)),
});

// ── Helpers ────────────────────────────────────────────────
const getRoleLabel = (role) => props.roles.find(r => r.value === role)?.label ?? role;
const getRoleColor = (role) => props.roles.find(r => r.value === role)?.color ?? '#5A6B7C';
const getInitial   = (name) => name?.charAt(0)?.toUpperCase() ?? '?';
</script>

<template>
    <AppLayout :flash="flash" page-title="Kelola User">
        <AlertModal   v-bind="alert"   @close="alert.show = false"/>
        <ConfirmModal v-bind="confirm" @confirm="doConfirm" @cancel="confirm.show = false"/>

        <div class="p-4 sm:p-6 space-y-4" style="font-family:'Inter','Plus Jakarta Sans',sans-serif">

            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h1 class="font-bold text-lg" style="color:var(--text-primary)">Kelola User</h1>
                    <p class="text-sm mt-0.5" style="color:var(--text-secondary)">Atur akun, role, dan permission petugas</p>
                </div>
                <button @click="showAddForm = !showAddForm"
                    class="flex items-center gap-2 text-sm font-semibold px-4 py-2.5 rounded-xl self-start sm:self-auto"
                    :style="showAddForm
                        ? 'background:var(--bg-main); color:var(--text-secondary); border:1px solid var(--border-default)'
                        : 'background:#00A884; color:var(--text-on-accent)'">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="showAddForm ? 'M6 18L18 6M6 6l12 12' : 'M12 4v16m8-8H4'"/>
                    </svg>
                    {{ showAddForm ? 'Tutup' : 'Tambah User' }}
                </button>
            </div>

            <!-- ── Form Tambah ── -->
            <Transition enter-active-class="transition-all duration-200 ease-out" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0">
                <form v-if="showAddForm" @submit.prevent="submitAdd" class="card-dark overflow-hidden">
                    <div class="px-5 py-3" style="background:linear-gradient(90deg,#00A884,#008C6E)">
                        <p class="text-sm font-bold" style="color:var(--text-on-accent)">Tambah User Baru</p>
                    </div>
                    <div class="p-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">Nama Lengkap <span style="color:#E74C3C">*</span></label>
                            <input v-model="addForm.name" required placeholder="Nama lengkap"
                                class="w-full px-3 py-2.5 text-sm rounded-xl outline-none"
                                :style="`border:1px solid ${addForm.errors.name?'#E74C3C':'var(--border-default)'}; background:var(--bg-surface); color:var(--text-primary)`"/>
                            <p v-if="addForm.errors.name" class="text-xs mt-1" style="color:#E74C3C">{{ addForm.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">
                                Username <span style="color:#E74C3C">*</span>
                                <span class="font-normal ml-1" style="color:var(--text-secondary)">(untuk login)</span>
                            </label>
                            <input v-model="addForm.username" required placeholder="contoh: icu1, admisi2"
                                class="w-full px-3 py-2.5 text-sm rounded-xl outline-none font-mono"
                                :style="`border:1px solid ${addForm.errors.username?'#E74C3C':'var(--border-default)'}; background:var(--bg-surface); color:var(--text-primary)`"/>
                            <p v-if="addForm.errors.username" class="text-xs mt-1" style="color:#E74C3C">{{ addForm.errors.username }}</p>
                            <p v-else class="text-xs mt-1" style="color:var(--text-secondary)">Huruf, angka, titik, dash — tidak boleh spasi</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">Email <span style="color:var(--text-secondary); font-weight:400">(opsional)</span></label>
                            <input v-model="addForm.email" type="email" placeholder="email@rs.id (opsional)"
                                class="w-full px-3 py-2.5 text-sm rounded-xl outline-none font-mono"
                                :style="`border:1px solid ${addForm.errors.email?'#E74C3C':'var(--border-default)'}; background:var(--bg-surface); color:var(--text-primary)`"/>
                            <p v-if="addForm.errors.email" class="text-xs mt-1" style="color:#E74C3C">{{ addForm.errors.email }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">Password <span style="color:#E74C3C">*</span></label>
                            <input v-model="addForm.password" type="password" required placeholder="Min 6 karakter"
                                class="w-full px-3 py-2.5 text-sm rounded-xl outline-none"
                                :style="`border:1px solid ${addForm.errors.password?'#E74C3C':'var(--border-default)'}; background:var(--bg-surface); color:var(--text-primary)`"/>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">Role <span style="color:#E74C3C">*</span></label>
                            <select v-model="addForm.role" required
                                class="w-full px-3 py-2.5 text-sm rounded-xl outline-none"
                                style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)">
                                <option value="" disabled>Pilih role</option>
                                <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">Unit Kerja</label>
                            <input v-model="addForm.unit_kerja" placeholder="Contoh: ICU, Admisi, Poli Dalam"
                                class="w-full px-3 py-2.5 text-sm rounded-xl outline-none"
                                style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                        </div>
                    </div>
                    <!-- Error summary -->
                    <div v-if="Object.keys(addForm.errors).length" class="mx-5 mb-3 p-3 rounded-xl text-xs"
                        style="background:rgba(231,76,60,0.1); border:1px solid rgba(231,76,60,0.3); color:#E74C3C">
                        <p v-for="(err,f) in addForm.errors" :key="f">• {{ err }}</p>
                    </div>
                    <div class="flex gap-2 px-5 pb-5">
                        <button type="submit" :disabled="addForm.processing"
                            class="flex items-center gap-2 text-sm font-bold px-5 py-2.5 rounded-xl disabled:opacity-50"
                            style="background:#00A884; color:var(--text-on-accent)">
                            <svg v-if="addForm.processing" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            Simpan
                        </button>
                        <button type="button" @click="showAddForm=false; addForm.reset()"
                            class="px-5 py-2.5 text-sm rounded-xl"
                            style="background:var(--bg-main); color:var(--text-secondary); border:1px solid var(--border-default)">Batal</button>
                    </div>
                </form>
            </Transition>

            <!-- ── Tabel User ── -->
            <div class="card-dark overflow-hidden">
                <div class="px-5 py-3.5 flex items-center justify-between" style="border-bottom:1px solid var(--border-default)">
                    <p class="text-sm font-semibold" style="color:var(--text-primary)">Daftar User</p>
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full"
                        style="background:rgba(0,168,132,0.12); color:#00A884">{{ users.length }} user</span>
                </div>

                <!-- Desktop table -->
                <div class="overflow-x-auto">
                    <table class="w-full dark-table" style="min-width:700px">
                        <thead>
                            <tr>
                                <th class="text-left px-5">User</th>
                                <th class="text-left px-4">Username</th>
                                <th class="text-left px-4">Role</th>
                                <th class="text-left px-4">Unit Kerja</th>
                                <th class="text-left px-4">Status</th>
                                <th class="text-left px-4">Bergabung</th>
                                <th class="text-center px-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="u in users" :key="u.id">
                                <!-- Row normal -->
                                <tr v-if="editId !== u.id">
                                    <td class="px-5">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-sm flex-shrink-0"
                                                :style="`background:${getRoleColor(u.role)}22; color:${getRoleColor(u.role)}`">
                                                {{ getInitial(u.name) }}
                                            </div>
                                            <div>
                                                <p class="font-semibold text-sm" style="color:var(--text-primary)">{{ u.name }}</p>
                                                <p class="text-xs font-mono" style="color:var(--text-secondary)">{{ u.email ?? '—' }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4">
                                        <span class="text-xs font-mono font-semibold px-2 py-1 rounded-lg"
                                            style="background:var(--bg-input); color:var(--text-accent)">
                                            {{ u.username }}
                                        </span>
                                    </td>
                                    <td class="px-4">
                                        <span class="text-xs font-semibold px-2.5 py-1 rounded-full"
                                            :style="`background:${getRoleColor(u.role)}20; color:${getRoleColor(u.role)}`">
                                            {{ u.role_label }}
                                        </span>
                                    </td>
                                    <td class="px-4 text-xs" style="color:var(--text-secondary)">{{ u.unit_kerja ?? '—' }}</td>
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
                                        <div class="flex items-center justify-center gap-1.5">
                                            <!-- Edit -->
                                            <button @click="openEdit(u)" title="Edit"
                                                class="p-1.5 rounded-lg transition-all"
                                                style="background:rgba(0,168,132,0.1); color:#00A884; border:1px solid rgba(0,168,132,0.2)">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </button>
                                            <!-- Reset Password -->
                                            <button @click="openReset(u.id)" title="Reset Password"
                                                class="p-1.5 rounded-lg transition-all"
                                                style="background:rgba(52,152,219,0.1); color:#00A884; border:1px solid rgba(0,168,132,0.2)">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                                                </svg>
                                            </button>
                                            <!-- Hapus -->
                                            <button @click="doHapus(u)" title="Hapus"
                                                class="p-1.5 rounded-lg transition-all"
                                                style="background:rgba(231,76,60,0.1); color:#E74C3C; border:1px solid rgba(231,76,60,0.2)">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Row edit inline -->
                                <tr v-else style="background:var(--bg-surface-2)">
                                    <td colspan="7" class="px-5 py-4">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-3">
                                            <div>
                                                <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">Nama</label>
                                                <input v-model="editForm.name"
                                                    class="w-full px-3 py-2 text-sm rounded-xl outline-none"
                                                    style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">Role</label>
                                                <select v-model="editForm.role"
                                                    class="w-full px-3 py-2 text-sm rounded-xl outline-none"
                                                    style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)">
                                                    <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">Unit Kerja</label>
                                                <input v-model="editForm.unit_kerja"
                                                    class="w-full px-3 py-2 text-sm rounded-xl outline-none"
                                                    style="border:1px solid var(--border-default); background:var(--bg-surface); color:var(--text-primary)"/>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-semibold mb-1" style="color:var(--text-primary)">Status</label>
                                                <div class="flex gap-2 pt-1">
                                                    <button type="button" @click="editForm.is_active = true"
                                                        class="flex-1 py-2 rounded-xl text-xs font-semibold"
                                                        :style="editForm.is_active ? 'background:#3DDB8A; color:var(--text-on-accent)' : 'background:var(--bg-surface); color:var(--text-secondary); border:1px solid var(--border-default)'">
                                                        Aktif
                                                    </button>
                                                    <button type="button" @click="editForm.is_active = false"
                                                        class="flex-1 py-2 rounded-xl text-xs font-semibold"
                                                        :style="!editForm.is_active ? 'background:#E74C3C; color:#fff' : 'background:var(--bg-surface); color:var(--text-secondary); border:1px solid var(--border-default)'">
                                                        Nonaktif
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex gap-2">
                                            <button @click="submitEdit(u.id)" :disabled="editForm.processing"
                                                class="text-sm font-bold px-4 py-2 rounded-xl disabled:opacity-50"
                                                style="background:#00A884; color:var(--text-on-accent)">Simpan</button>
                                            <button @click="cancelEdit"
                                                class="text-sm px-4 py-2 rounded-xl"
                                                style="background:var(--bg-main); color:var(--text-secondary); border:1px solid var(--border-default)">Batal</button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Reset password row -->
                                <tr v-if="resetId === u.id" style="background:rgba(52,152,219,0.04)">
                                    <td colspan="7" class="px-5 py-3">
                                        <div class="flex items-center gap-3 flex-wrap">
                                            <span class="text-xs font-semibold" style="color:#00A884">Reset Password — {{ u.name }}</span>
                                            <input v-model="resetForm.password" type="password" placeholder="Password baru (min 6 karakter)"
                                                class="text-sm px-3 py-2 rounded-xl outline-none flex-1"
                                                style="border:1px solid rgba(0,168,132,0.3); background:var(--bg-surface); color:var(--text-primary); min-width:200px"/>
                                            <button @click="submitReset(u.id)" :disabled="resetForm.processing || !resetForm.password"
                                                class="text-xs font-bold px-4 py-2 rounded-xl disabled:opacity-50"
                                                style="background:#00A884; color:#fff">Reset</button>
                                            <button @click="resetId = null"
                                                class="text-xs px-3 py-2 rounded-xl"
                                                style="background:var(--bg-main); color:var(--text-secondary); border:1px solid var(--border-default)">Batal</button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Role Legend -->
            <div class="card-dark p-4">
                <p class="text-xs font-bold uppercase tracking-wide mb-3" style="color:var(--text-secondary)">Keterangan Role</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div v-for="r in roles" :key="r.value" class="flex items-start gap-3 p-3 rounded-xl"
                        :style="`background:${r.color}10; border:1px solid ${r.color}25`">
                        <div class="w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0 font-bold text-xs"
                            :style="`background:${r.color}20; color:${r.color}`">
                            {{ r.label.charAt(0) }}
                        </div>
                        <div>
                            <p class="text-xs font-bold" :style="`color:${r.color}`">{{ r.label }}</p>
                            <p class="text-xs mt-0.5" style="color:var(--text-secondary)">
                                {{ {
                                    admin:          'Full akses semua fitur',
                                    admisi:         'Booking, validasi, verifikasi',
                                    petugas_icu:    'Konfirmasi bed, pasien masuk',
                                    petugas_ruang:  'Buat surat permintaan ICU',
                                }[r.value] }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
