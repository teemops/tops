import axios from 'axios';
import { getIdToken, auth, waitForAuthState } from './composables/useFirebase';

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
// Ensure cookies are sent with requests (for session authentication)
window.axios.defaults.withCredentials = true;

// Initialize organization context from localStorage if available
const storedOrgId = localStorage.getItem('current_organization_id');
if (storedOrgId) {
    window.axios.defaults.headers.common['X-Organization-Id'] = storedOrgId;
}

// Cache for Firebase token to avoid repeated async calls
let cachedToken: string | null = null;
let tokenPromise: Promise<string | null> | null = null;
let authInitialized = false;

// Function to get token with caching and auth state waiting
async function getCachedToken(): Promise<string | null> {
    if (!auth) {
        return null;
    }
    
    // Wait for auth state to initialize if not already done
    if (!authInitialized) {
        try {
            await waitForAuthState();
            authInitialized = true;
        } catch (error) {
            console.warn('Failed to wait for auth state:', error);
        }
    }
    
    // If we have a cached token and user is still logged in, return it
    if (cachedToken && auth.currentUser) {
        return cachedToken;
    }
    
    // If there's already a token request in progress, wait for it
    if (tokenPromise) {
        return tokenPromise;
    }
    
    // Create new token request
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

// Add axios interceptor to include Firebase token in API requests
window.axios.interceptors.request.use(
    async (config) => {
        // Only add token to API requests
        if (config.url?.startsWith('/api/')) {
            try {
                // Try to get Firebase token if auth is available
                if (auth) {
                    const token = await getCachedToken();
                    if (token) {
                        config.headers.Authorization = `Bearer ${token}`;
                    } else {
                        // No token available - session auth will be used as fallback
                        // Don't block the request, let it proceed
                    }
                }
            } catch (error) {
                // If token retrieval fails, continue without token
                // Session-based auth will be used as fallback
                // Don't block the request
                console.warn('Failed to get Firebase token, using session auth:', error);
            }
        }
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

// Add response interceptor to handle errors
window.axios.interceptors.response.use(
    (response) => response,
    (error) => {
        // Handle 401 Unauthorized errors
        if (error.response?.status === 401) {
            // If it's an API request and we get 401, the token might be invalid
            // The middleware will handle session auth as fallback
            console.warn('API request returned 401 Unauthorized');
        }
        
        // Handle 404 "Organization not found or access denied" errors
        if (error.response?.status === 404 && 
            error.response?.data?.error === 'Organization not found or access denied') {
            // Clear invalid organization ID from localStorage
            localStorage.removeItem('current_organization_id');
            // Clear from axios headers
            delete window.axios.defaults.headers.common['X-Organization-Id'];
            
            // Dispatch custom event to notify components
            window.dispatchEvent(new CustomEvent('organization-not-found'));
        }
        
        return Promise.reject(error);
    }
);
