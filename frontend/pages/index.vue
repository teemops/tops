<template>
  <div>
    <!-- Show loading state while checking auth and redirecting -->
    <div style="display: flex; justify-content: center; align-items: center; height: 100vh;">
      <ProgressBar mode="indeterminate" style="height: 6px; width: 200px;" />
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({
  middleware: 'auth',
});

const authStore = useAuthStore();

// Get auth from VueFire and set it in the store
if (import.meta.client) {
  const auth = useFirebaseAuth();
  if (auth) {
    authStore.setAuthInstance(auth);
  }
}

// Initialize auth if not already initialized
if (authStore.loading) {
  try {
    await authStore.initialize();
  } catch (error) {
    console.error('Failed to initialize auth:', error);
    // If auth initialization fails, redirect to login
    await navigateTo('/login');
  }
}

// Redirect based on auth state (only if initialization succeeded)
if (!authStore.loading) {
  if (authStore.isAuthenticated) {
    await navigateTo('/dashboard');
  } else {
    await navigateTo('/login');
  }
}
</script>
