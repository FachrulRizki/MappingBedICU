import { ref, computed } from 'vue';

/**
 * usePagination — client-side pagination composable.
 * Halaman reset ke 1 hanya saat reset() dipanggil secara eksplisit
 * (misal saat filter/tab berubah), bukan saat sort atau data reload.
 *
 * @param {import('vue').ComputedRef<Array>|import('vue').Ref<Array>} listRef
 * @param {number} perPage
 */
export function usePagination(listRef, perPage = 10) {
    const page = ref(1);

    const totalPages = computed(() =>
        Math.max(1, Math.ceil((listRef.value?.length ?? 0) / perPage))
    );

    // currentPage: clamp tanpa mutasi ref — aman untuk reactive chain
    const currentPage = computed(() => Math.min(page.value, totalPages.value));

    const paginated = computed(() => {
        const p     = currentPage.value;
        const start = (p - 1) * perPage;
        return (listRef.value ?? []).slice(start, start + perPage);
    });

    const goTo = (n) => {
        page.value = Math.max(1, Math.min(n, totalPages.value));
    };
    const next  = () => goTo(page.value + 1);
    const prev  = () => goTo(page.value - 1);
    const reset = () => { page.value = 1; };

    const pageRange = computed(() => {
        const total = totalPages.value;
        const cur   = currentPage.value;
        if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
        const range = new Set([1, total]);
        for (let i = Math.max(2, cur - 2); i <= Math.min(total - 1, cur + 2); i++) range.add(i);
        const sorted = [...range].sort((a, b) => a - b);
        const result = [];
        let last = null;
        for (const p of sorted) {
            if (last !== null && p - last > 1) result.push(null);
            result.push(p);
            last = p;
        }
        return result;
    });

    return {
        page: currentPage, // computed readonly — untuk template & display
        perPage,
        totalPages,
        paginated,
        pageRange,
        goTo,
        next,
        prev,
        reset,
    };
}
