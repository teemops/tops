<template>
  <div>
    <NuxtLayout>
      <NuxtPage />
    </NuxtLayout>
  </div>
</template>

<script setup lang="ts">
const authStore = useAuthStore();

// Get auth instance from VueFire (must be called in setup context)
const auth = useFirebaseAuth();

// Set auth instance in the store and initialize
onMounted(async () => {
  if (auth) {
    authStore.setAuthInstance(auth);
    await authStore.initialize();
  } else {
    console.error('Firebase auth not available from VueFire');
  }
});
</script>
