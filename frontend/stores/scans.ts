import { defineStore } from 'pinia';

export interface Scan {
  id: string;
  status: string;
  scanType: string;
  startedAt: string;
  completedAt: string | null;
  awsAccount: {
    id: string;
    name: string | null;
    awsAccountId: string;
  };
  resultCount?: number;
}

export const useScanStore = defineStore('scan', {
  state: () => ({
    scans: [] as Scan[],
    currentScan: null as any | null,
    loading: false,
    error: null as string | null,
  }),

  actions: {
    async fetchScans(orgId: string, filters?: { accountId?: string; status?: string }) {
      this.loading = true;
      this.error = null;
      try {
        const { apiCall } = useApi();
        
        let endpoint = `/organizations/${orgId}/scans`;
        const params = new URLSearchParams();
        if (filters?.accountId) params.append('accountId', filters.accountId);
        if (filters?.status) params.append('status', filters.status);
        if (params.toString()) endpoint += `?${params.toString()}`;
        
        const response = await apiCall(endpoint);
        
        this.scans = response.scans || [];
      } catch (error: any) {
        this.error = error.message || 'Failed to fetch scans';
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async createScan(orgId: string, awsAccountId: string, scanType: string = 'full', servicesScanned?: string[]) {
      this.loading = true;
      this.error = null;
      try {
        const { apiCall } = useApi();
        
        const response = await apiCall(`/organizations/${orgId}/scans`, {
          method: 'POST',
          body: {
            awsAccountId,
            scanType,
            servicesScanned,
          },
        });
        
        return response.scan;
      } catch (error: any) {
        this.error = error.message || 'Failed to create scan';
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async getScan(orgId: string, scanId: string) {
      this.loading = true;
      this.error = null;
      try {
        const { apiCall } = useApi();
        
        const response = await apiCall(`/organizations/${orgId}/scans/${scanId}`);
        
        this.currentScan = response.scan;
        return response;
      } catch (error: any) {
        this.error = error.message || 'Failed to fetch scan';
        throw error;
      } finally {
        this.loading = false;
      }
    },
  },
});

