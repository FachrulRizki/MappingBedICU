/**
 * useTheme — Dark / Light mode composable
 * Persists to localStorage, applies data-theme attribute to <html>
 */
import { ref, watch, onMounted } from 'vue';

const STORAGE_KEY = 'icu-theme';

// Shared reactive state (module-level singleton)
const theme = ref('dark'); // 'dark' | 'light'

function applyTheme(value) {
    if (typeof document !== 'undefined') {
        document.documentElement.setAttribute('data-theme', value);
    }
}

export function useTheme() {
    const isDark = () => theme.value === 'dark';

    function init() {
        const saved = typeof localStorage !== 'undefined'
            ? localStorage.getItem(STORAGE_KEY)
            : null;
        theme.value = saved === 'light' ? 'light' : 'dark';
        applyTheme(theme.value);
    }

    function toggle() {
        theme.value = theme.value === 'dark' ? 'light' : 'dark';
    }

    function setTheme(value) {
        theme.value = value;
    }

    // Persist and apply whenever theme changes
    watch(theme, (val) => {
        if (typeof localStorage !== 'undefined') {
            localStorage.setItem(STORAGE_KEY, val);
        }
        applyTheme(val);
    });

    onMounted(() => {
        init();
    });

    return { theme, isDark, toggle, setTheme, init };
}
