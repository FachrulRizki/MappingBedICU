<script setup>
/**
 * Pagination — reusable pagination bar
 * Props mirror the return of usePagination composable.
 */
const props = defineProps({
    page:       { type: Number,  required: true },
    totalPages: { type: Number,  required: true },
    pageRange:  { type: Array,   required: true },
    total:      { type: Number,  default: 0 },
    perPage:    { type: Number,  default: 10 },
    label:      { type: String,  default: 'data' },
});

const emit = defineEmits(['go', 'prev', 'next']);

const from = (page, perPage, total) =>
    total === 0 ? 0 : (page - 1) * perPage + 1;
const to = (page, perPage, total) =>
    Math.min(page * perPage, total);
</script>

<template>
    <div v-if="totalPages > 1 || total > 0"
        class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-3"
        style="border-top: 1px solid var(--border-default)">

        <!-- Info -->
        <p class="text-xs order-2 sm:order-1" style="color: var(--text-muted)">
            Menampilkan
            <strong style="color: var(--text-primary)">{{ from(page, perPage, total) }}–{{ to(page, perPage, total) }}</strong>
            dari
            <strong style="color: var(--text-primary)">{{ total }}</strong>
            {{ label }}
        </p>

        <!-- Page buttons -->
        <div v-if="totalPages > 1" class="flex items-center gap-1 order-1 sm:order-2 flex-wrap justify-center">
            <!-- Prev -->
            <button
                :disabled="page <= 1"
                @click="emit('prev')"
                class="w-8 h-8 rounded-lg flex items-center justify-center transition-all"
                :style="page <= 1
                    ? 'opacity:.35; cursor:not-allowed; background:var(--bg-input); color:var(--text-muted)'
                    : 'background:var(--bg-input); color:var(--text-secondary); cursor:pointer'"
                aria-label="Halaman sebelumnya">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </button>

            <!-- Page numbers -->
            <template v-for="p in pageRange" :key="p ?? `ellipsis-${Math.random()}`">
                <!-- Ellipsis -->
                <span v-if="p === null"
                    class="w-8 h-8 flex items-center justify-center text-xs"
                    style="color: var(--text-muted)">
                    …
                </span>
                <!-- Number -->
                <button v-else
                    @click="emit('go', p)"
                    class="w-8 h-8 rounded-lg text-xs font-semibold transition-all"
                    :style="p === page
                        ? 'background:#00A884; color:#fff; box-shadow:0 2px 8px rgba(0,168,132,.3)'
                        : 'background:var(--bg-input); color:var(--text-secondary)'">
                    {{ p }}
                </button>
            </template>

            <!-- Next -->
            <button
                :disabled="page >= totalPages"
                @click="emit('next')"
                class="w-8 h-8 rounded-lg flex items-center justify-center transition-all"
                :style="page >= totalPages
                    ? 'opacity:.35; cursor:not-allowed; background:var(--bg-input); color:var(--text-muted)'
                    : 'background:var(--bg-input); color:var(--text-secondary); cursor:pointer'"
                aria-label="Halaman berikutnya">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
            </button>
        </div>
    </div>
</template>
