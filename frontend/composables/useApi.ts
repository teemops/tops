export const useApi = () => {
  const config = useRuntimeConfig();
  const authStore = useAuthStore();

  const handleAuthError = async () => {
    // Use Firebase signOut to properly clear auth state
    // This will trigger onAuthStateChanged and update the store correctly
    try {
      await authStore.signOut();
    } catch (error) {
      // If signOut fails, manually clear the state as fallback
      authStore.user = null;
    }
    
    // Redirect to login (only if not already on login/register page)
    const route = useRoute();
    if (route.path !== '/login' && route.path !== '/register') {
      navigateTo('/login');
    }
  };

  const apiCall = async (endpoint: string, options: any = {}) => {
    // Wait for auth to initialize before checking
    if (authStore.loading) {
      await authStore.initialize();
    }
    
    // Ensure user is authenticated
    if (!authStore.isAuthenticated) {
      await handleAuthError();
      throw new Error('User is not authenticated');
    }

    const token = await authStore.getIdToken();
    
    // Ensure we have a valid token
    if (!token) {
      await handleAuthError();
      throw new Error('Failed to get authentication token');
    }
    
    // Ensure endpoint starts with /
    const normalizedEndpoint = endpoint.startsWith('/') ? endpoint : `/${endpoint}`;
    
    try {
      return await $fetch(`${config.public.apiBaseUrl}${normalizedEndpoint}`, {
        ...options,
        redirect: 'manual', // Prevent automatic redirect following that could trigger Vue Router
        headers: {
          ...options.headers,
          Authorization: `Bearer ${token}`,
        },
      });
    } catch (error: any) {
      // Check for authentication errors
      const status = error.status || error.statusCode || error.response?.status;
      const message = error.message || error.data?.message || '';
      
      // Handle 401 (Unauthorized) or 403 (Forbidden) status codes
      if (status === 401 || status === 403) {
        await handleAuthError();
        throw error;
      }
      
      // Handle authentication-related error messages
      const authErrorMessages = [
        'not authenticated',
        'unauthorized',
        'authentication',
        'invalid token',
        'token expired',
        'forbidden'
      ];
      
      if (authErrorMessages.some(msg => message.toLowerCase().includes(msg))) {
        await handleAuthError();
        throw error;
      }
      
      // Re-throw other errors
      throw error;
    }
  };

  return { apiCall };
};

