import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

// Use the configured axios instance from bootstrap.ts
const axios = (window as any).axios;

export interface Organization {
    id: string;
    name: string;
    org_id: string;
    is_default: boolean;
    aws_accounts_count?: number;
    created_at: string;
    updated_at?: string;
}

const organizations = ref<Organization[]>([]);
const currentOrganization = ref<Organization | null>(null);
const loading = ref(false);
const error = ref<string | null>(null);

export function useOrganizations() {
    const fetchOrganizations = async () => {
        loading.value = true;
        error.value = null;
        
        try {
            const response = await axios.get('/api/organizations');
            organizations.value = response.data.organizations;
            
            // Set current organization to default if not set
            if (!currentOrganization.value && organizations.value.length > 0) {
                const defaultOrg = organizations.value.find(org => org.is_default);
                currentOrganization.value = defaultOrg || organizations.value[0];
                
                // Store in localStorage
                if (currentOrganization.value) {
                    localStorage.setItem('current_organization_id', currentOrganization.value.org_id);
                }
            }
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to load organizations';
            console.error('Error fetching organizations:', err);
        } finally {
            loading.value = false;
        }
    };

    const fetchCurrentOrganization = async () => {
        try {
            const response = await axios.get('/api/organizations/current');
            currentOrganization.value = response.data;
        } catch (err: any) {
            console.error('Error fetching current organization:', err);
        }
    };

    const createOrganization = async (name: string) => {
        loading.value = true;
        error.value = null;
        
        try {
            const response = await axios.post('/api/organizations', { name });
            const newOrg = response.data;
            organizations.value.push(newOrg);
            currentOrganization.value = newOrg;
            localStorage.setItem('current_organization_id', newOrg.org_id);
            return newOrg;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to create organization';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const updateOrganization = async (orgId: string, name: string) => {
        loading.value = true;
        error.value = null;
        
        try {
            const response = await axios.put(`/api/organizations/${orgId}`, { name });
            const updatedOrg = response.data;
            
            // Update in organizations array
            const index = organizations.value.findIndex(org => org.org_id === orgId);
            if (index !== -1) {
                organizations.value[index] = { ...organizations.value[index], ...updatedOrg };
            }
            
            // Update current if it's the one being updated
            if (currentOrganization.value?.org_id === orgId) {
                currentOrganization.value = { ...currentOrganization.value, ...updatedOrg };
            }
            
            return updatedOrg;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to update organization';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const deleteOrganization = async (orgId: string) => {
        loading.value = true;
        error.value = null;
        
        try {
            await axios.delete(`/api/organizations/${orgId}`);
            
            // Remove from organizations array
            organizations.value = organizations.value.filter(org => org.org_id !== orgId);
            
            // If deleted org was current, switch to default
            if (currentOrganization.value?.org_id === orgId) {
                const defaultOrg = organizations.value.find(org => org.is_default);
                currentOrganization.value = defaultOrg || organizations.value[0] || null;
            }
            
            return true;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to delete organization';
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const switchOrganization = async (orgId: string) => {
        const org = organizations.value.find(o => o.org_id === orgId);
        if (!org) {
            error.value = 'Organization not found';
            return;
        }
        
        // Store in localStorage for persistence
        localStorage.setItem('current_organization_id', orgId);
        currentOrganization.value = org;
        
        // Update axios default header for future API calls
        axios.defaults.headers.common['X-Organization-Id'] = orgId;
        
        // Reload the page to update context
        router.reload();
    };

    // Initialize current organization from localStorage
    const initCurrentOrganization = () => {
        const storedOrgId = localStorage.getItem('current_organization_id');
        if (storedOrgId && organizations.value.length > 0) {
            const org = organizations.value.find(o => o.org_id === storedOrgId);
            if (org) {
                currentOrganization.value = org;
                axios.defaults.headers.common['X-Organization-Id'] = storedOrgId;
            }
        } else if (organizations.value.length > 0) {
            // Set default organization
            const defaultOrg = organizations.value.find(org => org.is_default);
            if (defaultOrg) {
                currentOrganization.value = defaultOrg;
                localStorage.setItem('current_organization_id', defaultOrg.org_id);
                axios.defaults.headers.common['X-Organization-Id'] = defaultOrg.org_id;
            }
        }
    };

    return {
        organizations: computed(() => organizations.value),
        currentOrganization: computed(() => currentOrganization.value),
        loading: computed(() => loading.value),
        error: computed(() => error.value),
        fetchOrganizations,
        fetchCurrentOrganization,
        createOrganization,
        updateOrganization,
        deleteOrganization,
        switchOrganization,
        initCurrentOrganization,
    };
}

