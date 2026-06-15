import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useAuth() {
    const page = usePage();
    const user = computed(() => page.props.auth?.user ?? null);
    const role = computed(() => user.value?.role ?? '');

    const isAdmin        = computed(() => role.value === 'admin');
    const isAdmisi       = computed(() => role.value === 'admisi');
    const isIcu          = computed(() => role.value === 'petugas_icu');
    const isPetugasRuang = computed(() => role.value === 'petugas_ruang');

    const can = (...roles) => isAdmin.value || roles.includes(role.value);

    // ── Menu ICU (petugas_icu) ────────────────────────────────────────────
    const canKonfirmasiIcu = computed(() => can('petugas_icu'));

    // ── Menu Admisi (admisi) ──────────────────────────────────────────────
    const canBuatBookingExternal = computed(() => can('admisi'));
    const canVerifikasiAdmisiExt = computed(() => can('admisi'));
    const canApproveAdmisi       = computed(() => can('admisi'));

    // ── Menu Petugas Ruang ────────────────────────────────────────────────
    const canBuatSpriInternal = computed(() => can('petugas_ruang'));

    // ── Settings ──────────────────────────────────────────────────────────
    const canManageUsers = computed(() => isAdmin.value);

    return {
        user, role,
        isAdmin, isAdmisi, isIcu, isPetugasRuang,
        can,
        canKonfirmasiIcu,
        canBuatBookingExternal,
        canVerifikasiAdmisiExt,
        canApproveAdmisi,
        canBuatSpriInternal,
        canManageUsers,
    };
}
