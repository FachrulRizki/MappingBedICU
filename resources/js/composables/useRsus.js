import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Cek apakah koneksi SQL Server RS tersedia.
 * Nilai di-share dari HandleInertiaRequests via prop `rsus_available`.
 *
 * Gunakan untuk show/hide fitur yang butuh data RS:
 *   const { rsusAvailable } = useRsus()
 *   v-if="rsusAvailable"  → tampilkan fitur lookup pasien, ICD10, dll
 */
export function useRsus() {
    const page         = usePage();
    const rsusAvailable = computed(() => page.props.rsus_available === true);

    return { rsusAvailable };
}
