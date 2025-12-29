<template>
  <div>
    <NuxtPage />
  </div>
</template>

<script setup lang="ts">
const authStore = useAuthStore();

// Initialize auth state
onMounted(async () => {
  await authStore.initialize();
  
  // Redirect based on auth state
  if (authStore.isAuthenticated) {
    await navigateTo('/dashboard');
  } else {
    await navigateTo('/login');
  }
});
</script>
