<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useMfa } from '@/composables/useMfa';

const { showAlert, fetchStatus, isConfigured } = useMfa();

function refreshStatus() {
  if (isConfigured.value) {
    fetchStatus();
  }
}

onMounted(() => {
  refreshStatus();
  window.addEventListener('mfa-status-changed', refreshStatus);
});

onUnmounted(() => {
  window.removeEventListener('mfa-status-changed', refreshStatus);
});
</script>

<template>
  <div
    v-if="isConfigured && showAlert"
    class="bg-amber-50 dark:bg-amber-900/20 border-b border-amber-200 dark:border-amber-800"
  >
    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8 py-3">
      <div class="flex items-center justify-between flex-wrap gap-2">
        <div class="flex items-center gap-3">
          <svg
            class="h-5 w-5 text-amber-600 dark:text-amber-500 flex-shrink-0"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
            />
          </svg>
          <p class="text-sm font-medium text-amber-800 dark:text-amber-200">
            Multi-factor authentication is not enabled. Your account is less secure.
          </p>
        </div>
        <Link
          :href="route('profile.edit')"
          class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-amber-900 bg-amber-400 hover:bg-amber-300 dark:bg-amber-600 dark:hover:bg-amber-500 dark:text-amber-900 transition-colors"
        >
          Enable MFA
        </Link>
      </div>
    </div>
  </div>
</template>
