import { defineStore } from 'pinia';

export interface Organization {
  id: string;
  name: string;
  orgId: string;
  isDefault: boolean;
  createdAt: string;
}

export const useOrganizationStore = defineStore('organization', {
  state: () => ({
    organizations: [] as Organization[],
    currentOrganization: null as Organization | null,
    loading: false,
    error: null as string | null,
  }),

  getters: {
    hasOrganizations: (state) => state.organizations.length > 0,
    currentOrgId: (state) => state.currentOrganization?.orgId || null,
  },

  actions: {
    async fetchOrganizations() {
      this.loading = true;
      this.error = null;
      try {
        const { apiCall } = useApi();
        
        const response = await apiCall('/organizations');
        
        this.organizations = response.organizations || [];
        
        // Set current organization if not set
        if (!this.currentOrganization && this.organizations.length > 0) {
          this.currentOrganization = this.organizations.find(org => org.isDefault) || this.organizations[0];
        }
      } catch (error: any) {
        this.error = error.message || 'Failed to fetch organizations';
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async createOrganization(name: string) {
      this.loading = true;
      this.error = null;
      try {
        const { apiCall } = useApi();
        
        const response = await apiCall('/organizations', {
          method: 'POST',
          body: { name },
        });
        
        this.organizations.push(response.organization);
        return response.organization;
      } catch (error: any) {
        this.error = error.message || 'Failed to create organization';
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async getCurrentOrganization() {
      this.loading = true;
      this.error = null;
      try {
        const { apiCall } = useApi();
        
        const response = await apiCall('/organizations/current');
        
        this.currentOrganization = response.organization;
      } catch (error: any) {
        this.error = error.message || 'Failed to get current organization';
        throw error;
      } finally {
        this.loading = false;
      }
    },

    setCurrentOrganization(org: Organization) {
      this.currentOrganization = org;
    },
  },
});

