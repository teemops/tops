import { defineStore } from 'pinia';

export const useInsightsStore = defineStore('insights', {
  state: () => ({
    insights: null as any | null,
    loading: false,
    error: null as string | null,
  }),

  actions: {
    async fetchInsights(orgId: string, timeRange: string = '30days') {
      this.loading = true;
      this.error = null;
      try {
        const config = useRuntimeConfig();
        const authStore = useAuthStore();
        const token = await authStore.getIdToken();
        
        const response = await $fetch(`${config.public.apiBaseUrl}/organizations/${orgId}/insights?timeRange=${timeRange}`, {
          headers: {
            Authorization: `Bearer ${token}`,
          },
        });
        
        this.insights = response.insights;
      } catch (error: any) {
        this.error = error.message || 'Failed to fetch insights';
        throw error;
      } finally {
        this.loading = false;
      }
    },
  },
});

