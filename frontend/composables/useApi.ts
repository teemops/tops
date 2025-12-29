export const useApi = () => {
  const config = useRuntimeConfig();
  const authStore = useAuthStore();

  const apiCall = async (endpoint: string, options: any = {}) => {
    const token = await authStore.getIdToken();
    
    // Ensure endpoint starts with /
    const normalizedEndpoint = endpoint.startsWith('/') ? endpoint : `/${endpoint}`;
    
    return $fetch(`${config.public.apiBaseUrl}${normalizedEndpoint}`, {
      ...options,
      redirect: 'manual', // Prevent automatic redirect following that could trigger Vue Router
      headers: {
        ...options.headers,
        Authorization: `Bearer ${token}`,
      },
    });
  };

  return { apiCall };
};

