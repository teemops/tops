import { defineStore } from 'pinia';

export const useUserStore = defineStore('user', {
  state: () => ({
    profile: null as any | null,
    loading: false,
    error: null as string | null,
  }),

  actions: {
    async fetchProfile() {
      this.loading = true;
      this.error = null;
      try {
        const { apiCall } = useApi();
        
        const response = await apiCall('/users/profile');
        
        this.profile = response.user;
      } catch (error: any) {
        this.error = error.message || 'Failed to fetch profile';
        throw error;
      } finally {
        this.loading = false;
      }
    },

    async updateProfile(name: string) {
      this.loading = true;
      this.error = null;
      try {
        const { apiCall } = useApi();
        
        const response = await apiCall('/users/profile', {
          method: 'PUT',
          body: { name },
        });
        
        this.profile = response.user;
        return response.user;
      } catch (error: any) {
        this.error = error.message || 'Failed to update profile';
        throw error;
      } finally {
        this.loading = false;
      }
    },
  },
});

