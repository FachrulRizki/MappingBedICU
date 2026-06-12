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

    // ── Booking External ──────────────────────────────────────────────────
    // Admisi: buat booking, verifikasi No_MR setelah pasien tiba
    const canBuatBookingExternal  = computed(() => can('admisi'));
    const canVerifikasiAdmisiExt  = computed(() => can('admisi'));
    // ICU: konfirmasi bed, tolak
    const canKonfirmasiIcu        = computed(() => can('petugas_icu'));

    // ── SPRI Internal ─────────────────────────────────────────────────────
    // Petugas Ruang: buat SPRI
    const canBuatSpriInternal     = computed(() => can('petugas_ruang'));
    // Admisi: approve/tolak, isi catatan jaminan
    const canApproveAdmisi        = computed(() => can('admisi'));
    // ICU: verifikasi bed, tolak
    const canVerifikasiBedIcu     = computed(() => can('petugas_icu'));

    // ── Settings ─────────────────────────────────────────────────────────
    const canManageUsers = computed(() => isAdmin.value);

    return {
        user, role,
        isAdmin, isAdmisi, isIcu, isPetugasRuang,
        can,
        // Booking External
        canBuatBookingExternal,
        canVerifikasiAdmisiExt,
        canKonfirmasiIcu,
        // SPRI Internal
        canBuatSpriInternal,
        canApproveAdmisi,
        canVerifikasiBedIcu,
        // Settings
        canManageUsers,
    };
}
