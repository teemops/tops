export default defineNuxtRouteMiddleware(async (to, from) => {
  const authStore = useAuthStore();
  
  // Get auth from VueFire (client-side only)
  if (import.meta.client) {
    const auth = useFirebaseAuth();
    if (auth) {
      authStore.setAuthInstance(auth);
    }
  }

  // Wait for auth to initialize if it's still loading
  if (authStore.loading) {
    try {
      await authStore.initialize();
    } catch (error) {
      console.error('Failed to initialize auth in middleware:', error);
      // If auth initialization fails and not on login/register, redirect to login
      if (to.path !== '/login' && to.path !== '/register' && to.path !== '/') {
        return navigateTo('/login');
      }
      return;
    }
  }

  // If not authenticated and trying to access protected route
  if (!authStore.isAuthenticated && to.path !== '/login' && to.path !== '/register' && to.path !== '/') {
    return navigateTo('/login');
  }

  // If authenticated and trying to access auth pages, redirect to dashboard
  if (authStore.isAuthenticated && (to.path === '/login' || to.path === '/register')) {
    return navigateTo('/dashboard');
  }
});
