import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * useAuth — helper composable untuk cek permission & role di Vue.
 * Permission datang dari Keycloak via session, tidak hardcode di sini.
 */
export function useAuth() {
    const page        = usePage();
    const user        = computed(() => page.props.auth?.user ?? null);
    const role        = computed(() => user.value?.role ?? '');
    const permissions = computed(() => user.value?.permissions ?? []);

    /** Cek apakah user punya permission tertentu (OR logic). */
    const can = (...perms) => perms.some(p => permissions.value.includes(p));

    /** Cek apakah user punya semua permission yang diminta (AND logic). */
    const canAll = (...perms) => perms.every(p => permissions.value.includes(p));

    // ── Shorthand per fitur — berdasarkan permission Keycloak ─────────────

    const canKonfirmasiIcu       = computed(() => can('booking_ext:konfirmasi_bed'));
    const canVerifikasiIcuInt    = computed(() => can('booking_int:verifikasi_bed'));
    const canBuatBookingExternal = computed(() => can('booking_ext:create'));
    const canVerifikasiAdmisiExt = computed(() => can('booking_ext:verifikasi'));
    const canApproveAdmisi       = computed(() => can('booking_int:approve'));
    const canBuatSpriInternal    = computed(() => can('booking_int:create'));
    const canManageUsers         = computed(() => can('settings_users:view'));

    return {
        user,
        role,
        permissions,
        can,
        canAll,
        canKonfirmasiIcu,
        canVerifikasiIcuInt,
        canBuatBookingExternal,
        canVerifikasiAdmisiExt,
        canApproveAdmisi,
        canBuatSpriInternal,
        canManageUsers,
    };
}
