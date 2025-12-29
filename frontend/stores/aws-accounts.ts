import { defineStore } from 'pinia';
import { useOrganizationStore } from './organization';

export interface AwsAccount {
  id: string;
  name: string | null;
  awsAccountId: string;
  status: 'PENDING' | 'ACTIVE' | 'ERROR';
  createdAt: string;
  lastScanAt: string | null;
}

export const useAwsAccountStore = defineStore('awsAccount', {
  state: () => ({
    accounts: [] as AwsAccount[],
    loading: false,
    error: null as string | null,
  }),

  actions: {
    async fetchAccounts(orgId: string) {
      this.loading = true;
      this.error = null;
      try {
        const config = useRuntimeConfig();
        const authStore = useAuthStore();
        const token = await authStore.getIdToken();
        
        const response = await $fetch(`${config.public.apiBaseUrl}/organizations/${orgId}/aws-accounts`, {
          headers: {
            Authorization: `Bearer ${token}`,
          },
        });
        
        this.accounts = response.accounts || [];
      } catch (error: any) {
        this.error = error.message || 'Failed to fetch AWS accounts';
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async initAccount(orgId: string) {
      this.loading = true;
      this.error = null;
      try {
        const config = useRuntimeConfig();
        const authStore = useAuthStore();
        const token = await authStore.getIdToken();
        
        const response = await $fetch(`${config.public.apiBaseUrl}/organizations/${orgId}/aws-accounts/init`, {
          method: 'POST',
          headers: {
            Authorization: `Bearer ${token}`,
          },
        });
        
        return response;
      } catch (error: any) {
        this.error = error.message || 'Failed to initialize AWS account';
        throw error;
      } finally {
        this.loading = false;
      }
    },
  },
});

