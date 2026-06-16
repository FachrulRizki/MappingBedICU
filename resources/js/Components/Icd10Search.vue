<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    modelValue:  { type: String,  default: '' },
    placeholder: { type: String,  default: 'Cari kode atau keterangan ICD10' },
    disabled:    { type: Boolean, default: false },
    required:    { type: Boolean, default: false },
    hasError:    { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const query       = ref(props.modelValue ?? '');  
const results     = ref([]);         
const loading     = ref(false);
const showDropdown= ref(false);
const selected    = ref(null); 
const inputRef    = ref(null);

let debounceTimer = null;

watch(() => props.modelValue, (val) => {
    if (val !== query.value) query.value = val ?? '';
});

const doSearch = async (q) => {
    if (!q || q.length < 2) {
        results.value = [];
        showDropdown.value = false;
        return;
    }

    loading.value = true;
    try {
        const url = route('icu.search_icd10') + '?q=' + encodeURIComponent(q) + '&limit=10';
        const res  = await fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await res.json();

        if (Array.isArray(data) && data.length > 0) {
            results.value  = data;
            showDropdown.value = true;
        } else {
            results.value  = [];
            showDropdown.value = false;
        }
    } catch {
        results.value  = [];
        showDropdown.value = false;
    } finally {
        loading.value = false;
    }
};

const onInput = (e) => {
    const val = e.target.value;
    query.value = val;
    selected.value = null;

    emit('update:modelValue', val);

    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => doSearch(val), 300);
};

const selectItem = (item) => {
    selected.value = item;
    const value = item.kode && item.keterangan ? `${item.kode} — ${item.keterangan}` : (item.kode || item.keterangan);
    query.value = value;
    emit('update:modelValue', value);
    showDropdown.value = false;
    results.value = [];
};

const onClickOutside = (e) => {
    if (inputRef.value && !inputRef.value.contains(e.target)) {
        showDropdown.value = false;
    }
};
onMounted(()  => document.addEventListener('click', onClickOutside));
onUnmounted(() => document.removeEventListener('click', onClickOutside));
</script>

<template>
    <div class="relative" ref="inputRef">
        <div class="relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 pointer-events-none"
                style="color:var(--text-secondary)"
                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>

            <input
                :value="query"
                @input="onInput"
                @focus="query.length >= 2 && doSearch(query)"
                :placeholder="placeholder"
                :disabled="disabled"
                :required="required"
                autocomplete="off"
                class="w-full pl-9 pr-8 py-2.5 text-sm rounded-xl outline-none"
                :style="`
                    border: 1px solid ${hasError ? '#E74C3C' : showDropdown ? 'var(--border-input-focus)' : 'var(--border-default)'};
                    background: var(--bg-surface);
                    color: var(--text-primary);
                `"
            />

            <div class="absolute right-2.5 top-1/2 -translate-y-1/2">
                <svg v-if="loading" class="w-3.5 h-3.5 animate-spin" style="color:var(--text-secondary)"
                    fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <span v-else-if="selected" style="color:#00A884" class="text-sm font-bold">✓</span>
            </div>
        </div>

        <!-- Dropdown hasil search -->
        <Transition
            enter-active-class="transition-all duration-150 ease-out"
            enter-from-class="opacity-0 translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition-all duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0">
            <div v-if="showDropdown && results.length"
                class="absolute z-50 w-full mt-1 rounded-xl overflow-hidden shadow-lg"
                style="background:var(--bg-surface); border:1px solid var(--border-default); max-height:260px; overflow-y:auto">

                <button
                    v-for="item in results"
                    :key="item.kode"
                    type="button"
                    @click="selectItem(item)"
                    class="w-full text-left px-4 py-2.5 text-sm transition-all flex items-start gap-3"
                    style="border-bottom:1px solid var(--border-default)"
                    @mouseenter="$el.style.background='var(--bg-input)'"
                    @mouseleave="$el.style.background='transparent'">
                    <!-- Kode badge -->
                    <span class="flex-shrink-0 text-xs font-mono font-bold px-1.5 py-0.5 rounded mt-0.5"
                        style="background:rgba(0,168,132,0.12); color:#00A884; min-width:52px; text-align:center">
                        {{ item.kode }}
                    </span>
                    <!-- Keterangan -->
                    <span style="color:var(--text-primary); line-height:1.4">
                        {{ item.keterangan }}
                    </span>
                </button>            
            </div>
        </Transition>

        <!-- Hint -->
        <p v-if="query.length > 0 && query.length < 2" class="text-xs mt-1" style="color:var(--text-secondary)">
            Ketik minimal 2 karakter untuk mencari
        </p>
    </div>
</template>
