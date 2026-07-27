import axios from 'axios';

const firebaseAuthEnabled =
    document.querySelector('meta[name="teemops-firebase-auth"]')?.getAttribute('content') !== '0';

// Initialize dark mode before app renders to prevent flash
(function initDarkMode() {
    const stored = localStorage.getItem('darkMode');
    const isDark = stored !== null
        ? stored === 'true'
        : window.matchMedia('(prefers-color-scheme: dark)').matches;

    if (isDark) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
})();

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;

const storedOrgId = localStorage.getItem('current_organization_id');
if (storedOrgId) {
    window.axios.defaults.headers.common['X-Organization-Id'] = storedOrgId;
    document.cookie = `current_organization_id=${encodeURIComponent(storedOrgId)}; path=/; max-age=31536000; SameSite=Lax`;
} else {
    document.cookie = 'current_organization_id=; path=/; max-age=0';
}

if (firebaseAuthEnabled) {
    void import('./composables/useFirebase').then(({ getIdToken, auth, waitForAuthState }) => {
        let cachedToken: string | null = null;
        let tokenPromise: Promise<string | null> | null = null;
        let authInitialized = false;

        async function getCachedToken(): Promise<string | null> {
            if (!auth) {
                return null;
            }

            if (!authInitialized) {
                try {
                    await waitForAuthState();
                    authInitialized = true;
                } catch (error) {
                    console.warn('Failed to wait for auth state:', error);
                }
            }

            if (cachedToken && auth.currentUser) {
                return cachedToken;
            }

            if (tokenPromise) {
                return tokenPromise;
            }

            tokenPromise = (async () => {
                try {
                    if (auth.currentUser) {
                        const token = await getIdToken();
                        cachedToken = token;
                        return token;
                    }
                    return null;
                } catch (error) {
                    console.warn('Failed to get Firebase token:', error);
                    return null;
                } finally {
                    tokenPromise = null;
                }
            })();

            return tokenPromise;
        }

        window.axios.interceptors.request.use(
            async (config) => {
                if (config.url?.startsWith('/api/')) {
                    try {
                        if (auth) {
                            const token = await getCachedToken();
                            if (token) {
                                config.headers.Authorization = `Bearer ${token}`;
                            }
                        }
                    } catch (error) {
                        console.warn('Failed to get Firebase token, using session auth:', error);
                    }
                }
                return config;
            },
            (error) => Promise.reject(error),
        );
    });
}

window.axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            console.warn('API request returned 401 Unauthorized');
        }

        if (error.response?.status === 404 &&
            error.response?.data?.error === 'Organization not found or access denied') {
            localStorage.removeItem('current_organization_id');
            delete window.axios.defaults.headers.common['X-Organization-Id'];
            window.dispatchEvent(new CustomEvent('organization-not-found'));
        }

        return Promise.reject(error);
    },
);
