/**
 * useTheme — Dark / Light mode composable
 * Default: 'light'  (ICU Monitor v2.0 design system)
 * Persists to localStorage key 'icu-theme'
 */
import { ref, watch, onMounted } from 'vue';

const STORAGE_KEY = 'icu-theme';

// Module-level singleton — shared across all component instances
const theme = ref('light');

function applyTheme(value) {
    if (typeof document !== 'undefined') {
        document.documentElement.setAttribute('data-theme', value);
    }
}

export function useTheme() {
    function init() {
        const saved = typeof localStorage !== 'undefined'
            ? localStorage.getItem(STORAGE_KEY)
            : null;
        // Default to light if no preference saved
        theme.value = saved === 'dark' ? 'dark' : 'light';
        applyTheme(theme.value);
    }

    function toggle() {
        theme.value = theme.value === 'dark' ? 'light' : 'dark';
    }

    function setTheme(value) {
        theme.value = value;
    }

    const isDark = () => theme.value === 'dark';

    // Persist + apply on every change
    watch(theme, (val) => {
        if (typeof localStorage !== 'undefined') {
            localStorage.setItem(STORAGE_KEY, val);
        }
        applyTheme(val);
    });

    onMounted(init);

    return { theme, isDark, toggle, setTheme, init };
}
