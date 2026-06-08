/**
 * useAuth — composable untuk akses data user + role checks.
 *
 * ALUR BARU (internal & external sama):
 *   Petugas Ruang/Admisi → buat request (tanpa pilih bed)
 *   Admisi → setujui + isi catatan jaminan (tanpa pilih bed)
 *   Petugas ICU → pilih bed + konfirmasi masuk (LANGSUNG di_icu, skip verif admisi)
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

    const can = (...roles) => isAdmin.value || roles.includes(role.value);

    // ── Booking External ──────────────────────────────────────────────
    // Admisi: buat booking + isi jaminan (tanpa pilih bed)
    const canBuatBookingExternal = computed(() => can('admisi'));
    // ICU: satu-satunya penentu bed + konfirmasi masuk
    const canKonfirmasiIcu       = computed(() => can('petugas_icu'));
    const canKonfirmasiMasuk     = computed(() => can('petugas_icu'));
    const canPulangkan           = computed(() => can('petugas_icu'));
    // Dihapus: canValidasiAdmisi, canVerifikasiBed, canLinkPasien
    // (tidak ada lagi step validasi admisi setelah ICU konfirmasi bed)

    // ── SPRI Internal ─────────────────────────────────────────────────
    // Petugas Ruang: buat surat (tanpa pilih bed)
    const canBuatSpriInternal = computed(() => can('petugas_ruang'));
    // Admisi: setujui + isi catatan (tanpa pilih bed)
    const canApproveAdmisi    = computed(() => can('admisi'));
    // ICU: satu-satunya penentu bed + konfirmasi masuk
    const canBookingBedIcu    = computed(() => can('petugas_icu'));
    // Dihapus: canVerifikasiAdmisi
    // (tidak ada lagi step verifikasi admisi setelah ICU booking bed)

    // ── Settings ─────────────────────────────────────────────────────
    const canManageUsers = computed(() => isAdmin.value);

    return {
        user, role,
        isAdmin, isAdmisi, isIcu, isPetugasRuang,
        can,
        // Booking External
        canBuatBookingExternal,
        canKonfirmasiIcu,
        canKonfirmasiMasuk,
        canPulangkan,
        // SPRI Internal
        canBuatSpriInternal,
        canApproveAdmisi,
        canBookingBedIcu,
        // Settings
        canManageUsers,
    };
}
