/**
 * useAuth — composable untuk akses data user + role checks di semua pages.
 * Data berasal dari HandleInertiaRequests shared data.
 */
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

    /** Admin + role yang diberikan */
    const can = (...roles) => isAdmin.value || roles.includes(role.value);

    // Shortcuts per fitur
    const canBuatBookingExternal  = computed(() => can('admisi'));
    const canKonfirmasiIcu        = computed(() => can('petugas_icu'));
    const canValidasiAdmisi       = computed(() => can('admisi'));
    const canVerifikasiBed        = computed(() => can('admisi'));
    const canLinkPasien           = computed(() => can('admisi'));
    const canKonfirmasiMasuk      = computed(() => can('petugas_icu'));
    const canPulangkan            = computed(() => can('petugas_icu'));

    const canBuatSpriInternal     = computed(() => can('petugas_ruang'));
    const canApproveAdmisi        = computed(() => can('admisi'));
    const canBookingBedIcu        = computed(() => can('petugas_icu'));
    const canVerifikasiAdmisi     = computed(() => can('admisi'));

    const canManageUsers          = computed(() => isAdmin.value);

    return {
        user,
        role,
        isAdmin,
        isAdmisi,
        isIcu,
        isPetugasRuang,
        can,
        canBuatBookingExternal,
        canKonfirmasiIcu,
        canValidasiAdmisi,
        canVerifikasiBed,
        canLinkPasien,
        canKonfirmasiMasuk,
        canPulangkan,
        canBuatSpriInternal,
        canApproveAdmisi,
        canBookingBedIcu,
        canVerifikasiAdmisi,
        canManageUsers,
    };
}
