export default defineNuxtRouteMiddleware((to, from) => {
  const authStore = useAuthStore();

  // Wait for auth to initialize
  if (authStore.loading) {
    return;
  }

  // If not authenticated and trying to access protected route
  if (!authStore.isAuthenticated && to.path !== '/login' && to.path !== '/register') {
    return navigateTo('/login');
  }

  // If authenticated and trying to access auth pages, redirect to dashboard
  if (authStore.isAuthenticated && (to.path === '/login' || to.path === '/register')) {
    return navigateTo('/dashboard');
  }
});

