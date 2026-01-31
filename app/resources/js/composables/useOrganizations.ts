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
    /** User's role in this organization (owner | administrator | auditor | viewer) */
    role?: string | null;
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
            
            // Clear invalid organization ID if it doesn't exist in the list
            const storedOrgId = localStorage.getItem('current_organization_id');
            if (storedOrgId && organizations.value.length > 0) {
                const orgExists = organizations.value.some(org => org.org_id === storedOrgId);
                if (!orgExists) {
                    localStorage.removeItem('current_organization_id');
                    delete axios.defaults.headers.common['X-Organization-Id'];
                    document.cookie = 'current_organization_id=; path=/; max-age=0';
                }
            }

            // Set current organization to default if not set
            if (!currentOrganization.value && organizations.value.length > 0) {
                const defaultOrg = organizations.value.find(org => org.is_default);
                currentOrganization.value = defaultOrg || organizations.value[0];

                if (currentOrganization.value) {
                    const orgId = currentOrganization.value.org_id;
                    localStorage.setItem('current_organization_id', orgId);
                    axios.defaults.headers.common['X-Organization-Id'] = orgId;
                    document.cookie = `current_organization_id=${encodeURIComponent(orgId)}; path=/; max-age=31536000; SameSite=Lax`;
                }
            } else if (organizations.value.length === 0) {
                currentOrganization.value = null;
                localStorage.removeItem('current_organization_id');
                delete axios.defaults.headers.common['X-Organization-Id'];
                document.cookie = 'current_organization_id=; path=/; max-age=0';
            }
        } catch (err: any) {
            error.value = err.response?.data?.message || err.response?.data?.error || 'Failed to load organizations';
            console.error('Error fetching organizations:', err);
            
            // If we get 404, it might mean no organizations exist
            if (err.response?.status === 404) {
                organizations.value = [];
                currentOrganization.value = null;
                localStorage.removeItem('current_organization_id');
                delete axios.defaults.headers.common['X-Organization-Id'];
                document.cookie = 'current_organization_id=; path=/; max-age=0';
            }

            if (err.response?.status === 401) {
                organizations.value = [];
                currentOrganization.value = null;
                localStorage.removeItem('current_organization_id');
                delete axios.defaults.headers.common['X-Organization-Id'];
                document.cookie = 'current_organization_id=; path=/; max-age=0';
            }
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
            const orgId = newOrg.org_id;
            localStorage.setItem('current_organization_id', orgId);
            axios.defaults.headers.common['X-Organization-Id'] = orgId;
            document.cookie = `current_organization_id=${encodeURIComponent(orgId)}; path=/; max-age=31536000; SameSite=Lax`;
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

    const setCurrentOrganizationCookie = (orgId: string) => {
        document.cookie = `current_organization_id=${encodeURIComponent(orgId)}; path=/; max-age=31536000; SameSite=Lax`;
    };

    const switchOrganization = async (orgId: string) => {
        const org = organizations.value.find(o => o.org_id === orgId);
        if (!org) {
            error.value = 'Organization not found';
            return;
        }

        localStorage.setItem('current_organization_id', orgId);
        currentOrganization.value = org;
        axios.defaults.headers.common['X-Organization-Id'] = orgId;
        setCurrentOrganizationCookie(orgId);

        router.reload();
    };

    // Initialize current organization from localStorage and sync cookie so backend has context on every page
    const initCurrentOrganization = () => {
        const storedOrgId = localStorage.getItem('current_organization_id');
        if (storedOrgId && organizations.value.length > 0) {
            const org = organizations.value.find(o => o.org_id === storedOrgId);
            if (org) {
                currentOrganization.value = org;
                axios.defaults.headers.common['X-Organization-Id'] = storedOrgId;
                setCurrentOrganizationCookie(storedOrgId);
            }
        } else if (organizations.value.length > 0) {
            const defaultOrg = organizations.value.find(org => org.is_default);
            if (defaultOrg) {
                currentOrganization.value = defaultOrg;
                const orgId = defaultOrg.org_id;
                localStorage.setItem('current_organization_id', orgId);
                axios.defaults.headers.common['X-Organization-Id'] = orgId;
                setCurrentOrganizationCookie(orgId);
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

