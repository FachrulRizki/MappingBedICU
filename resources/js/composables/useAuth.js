import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useAuth() {
    const page        = usePage();
    const user        = computed(() => page.props.auth?.user ?? null);
    const role        = computed(() => user.value?.role ?? '');
    const permissions = computed(() => user.value?.permissions ?? []);

    /** Cek apakah user punya salah satu permission (OR logic). */
    const can = (...perms) => perms.some(p => permissions.value.includes(p));

    /** Cek apakah user punya semua permission (AND logic). */
    const canAll = (...perms) => perms.every(p => permissions.value.includes(p));

    // ── Dashboard ────────────────────────────────────────────────────────────
    const canViewDashboard      = computed(() => can('dashboard:view'));

    // ── Denah Bed ────────────────────────────────────────────────────────────
    const canViewDenahBed       = computed(() => can('denah_bed:view'));

    // ── Booking External (Menu Admisi & Menu ICU) ────────────────────────────
    const canViewBookingExt         = computed(() => can('booking_ext:view'));
    const canBuatBookingExternal    = computed(() => can('booking_ext:create'));
    const canKonfirmasiIcu          = computed(() => can('booking_ext:konfirmasi_bed'));
    const canTolakBookingExt        = computed(() => can('booking_ext:tolak'));
    const canWaitingListExt         = computed(() => can('booking_ext:waiting_list'));
    const canVerifikasiAdmisiExt    = computed(() => can('booking_ext:verifikasi_pasien'));

    // ── Booking Internal / SPRI (Menu ICU & Menu Petugas) ────────────────────
    const canViewBookingInt         = computed(() => can('booking_int:view'));
    const canBuatSpriInternal       = computed(() => can('booking_int:create'));
    const canApproveAdmisi          = computed(() => can('booking_int:approve'));
    const canTolakAdmisi            = computed(() => can('booking_int:tolak_admisi'));
    const canVerifikasiIcuInt       = computed(() => can('booking_int:verifikasi_bed'));
    const canTolakIcu               = computed(() => can('booking_int:tolak_icu'));
    const canWaitingListInt         = computed(() => can('booking_int:waiting_list'));

    // ── Settings ─────────────────────────────────────────────────────────────
    // Kelola User & Role dikelola penuh oleh Keycloak SSO.
    // Tidak ada permission settings_users / settings_roles di aplikasi ini.

    // ── Activity Log ─────────────────────────────────────────────────────────
    const canViewActivityLog        = computed(() => can('activity_log:view'));

    // ── Gabungan / shorthand navigasi ────────────────────────────────────────
    /** Bisa akses menu ICU (lihat antrian booking ext + int) */
    const canAccessMenuIcu          = computed(() => can('booking_ext:view', 'booking_int:view'));

    /** Bisa akses menu Admisi */
    const canAccessMenuAdmisi       = computed(() => can('booking_ext:view'));

    /** Bisa akses menu Petugas Ruang */
    const canAccessMenuPetugas      = computed(() => can('booking_int:create'));

    /**
     * isAdmin — true jika user punya permission admin-only (activity log).
     * Tidak bergantung pada nama role — role baru dari Keycloak dengan
     * permission yang sama otomatis dianggap setara admin.
     */
    const isAdmin = computed(() => can('activity_log:view'));

    return {
        user,
        role,
        permissions,
        can,
        canAll,

        // Dashboard
        canViewDashboard,

        // Denah Bed
        canViewDenahBed,

        // Booking External
        canViewBookingExt,
        canBuatBookingExternal,
        canKonfirmasiIcu,
        canTolakBookingExt,
        canWaitingListExt,
        canVerifikasiAdmisiExt,

        // Booking Internal
        canViewBookingInt,
        canBuatSpriInternal,
        canApproveAdmisi,
        canTolakAdmisi,
        canVerifikasiIcuInt,
        canTolakIcu,
        canWaitingListInt,

        // Activity Log
        canViewActivityLog,

        // Navigasi
        canAccessMenuIcu,
        canAccessMenuAdmisi,
        canAccessMenuPetugas,

        // Shorthand level akses
        isAdmin,
    };
}
