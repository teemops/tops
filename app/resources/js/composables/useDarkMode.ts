import { ref, onMounted } from 'vue';

// Initialize from localStorage or system preference
const getInitialDarkMode = (): boolean => {
    if (typeof window === 'undefined') return false;
    const stored = localStorage.getItem('darkMode');
    if (stored !== null) {
        return stored === 'true';
    }
    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

const isDark = ref(getInitialDarkMode());

export function useDarkMode() {
    const applyDarkMode = () => {
        if (typeof document === 'undefined') return;
        if (isDark.value) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    };

    const toggleDarkMode = () => {
        isDark.value = !isDark.value;
        localStorage.setItem('darkMode', isDark.value.toString());
        applyDarkMode();
    };

    const setDarkMode = (value: boolean) => {
        isDark.value = value;
        localStorage.setItem('darkMode', isDark.value.toString());
        applyDarkMode();
    };

    // Sync with current DOM state on mount (in case bootstrap.ts already set it)
    onMounted(() => {
        const currentIsDark = document.documentElement.classList.contains('dark');
        if (currentIsDark !== isDark.value) {
            isDark.value = currentIsDark;
        }

        // Watch for system preference changes
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        const handleChange = (e: MediaQueryListEvent) => {
            // Only update if user hasn't manually set a preference
            if (localStorage.getItem('darkMode') === null) {
                isDark.value = e.matches;
                applyDarkMode();
            }
        };

        // Modern browsers
        if (mediaQuery.addEventListener) {
            mediaQuery.addEventListener('change', handleChange);
        } else {
            // Fallback for older browsers
            mediaQuery.addListener(handleChange);
        }
    });

    return {
        isDark,
        toggleDarkMode,
        setDarkMode,
    };
}
