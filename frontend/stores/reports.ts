import { defineStore } from 'pinia';

export interface Report {
  id: string;
  scanId: string;
  accountName: string;
  generatedAt: string;
  findings: {
    total: number;
    critical: number;
    high: number;
    medium: number;
  };
}

export const useReportStore = defineStore('report', {
  state: () => ({
    reports: [] as Report[],
    currentReport: null as any | null,
    loading: false,
    error: null as string | null,
  }),

  actions: {
    async fetchReports(orgId: string) {
      this.loading = true;
      this.error = null;
      try {
        const config = useRuntimeConfig();
        const authStore = useAuthStore();
        const token = await authStore.getIdToken();
        
        const response = await $fetch(`${config.public.apiBaseUrl}/organizations/${orgId}/reports`, {
          headers: {
            Authorization: `Bearer ${token}`,
          },
        });
        
        this.reports = response.reports || [];
      } catch (error: any) {
        this.error = error.message || 'Failed to fetch reports';
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async getReport(orgId: string, scanId: string) {
      this.loading = true;
      this.error = null;
      try {
        const config = useRuntimeConfig();
        const authStore = useAuthStore();
        const token = await authStore.getIdToken();
        
        const response = await $fetch(`${config.public.apiBaseUrl}/organizations/${orgId}/reports/${scanId}`, {
          headers: {
            Authorization: `Bearer ${token}`,
          },
        });
        
        this.currentReport = response.report;
        return response.report;
      } catch (error: any) {
        this.error = error.message || 'Failed to fetch report';
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async exportReport(orgId: string, scanId: string, format: 'json' | 'csv') {
      const config = useRuntimeConfig();
      const authStore = useAuthStore();
      const token = await authStore.getIdToken();
      
      const url = `${config.public.apiBaseUrl}/organizations/${orgId}/reports/${scanId}/export/${format}`;
      
      const response = await fetch(url, {
        headers: {
          Authorization: `Bearer ${token}`,
        },
      });
      
      const blob = await response.blob();
      const downloadUrl = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = downloadUrl;
      a.download = `report-${scanId}.${format}`;
      document.body.appendChild(a);
      a.click();
      window.URL.revokeObjectURL(downloadUrl);
      document.body.removeChild(a);
    },
  },
});

