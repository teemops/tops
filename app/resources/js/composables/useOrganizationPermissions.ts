import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useOrganizations } from './useOrganizations';

export type OrganizationRole = 'owner' | 'administrator' | 'auditor' | 'viewer' | null;

export function useOrganizationPermissions() {
    const page = usePage();
    const { currentOrganization } = useOrganizations();

    // Get user's role in current organization from request context
    const getUserRole = (): OrganizationRole => {
        const role = (page.props as any).organization_role;
        return role || null;
    };

    const canManageSettings = computed(() => {
        const role = getUserRole();
        return role === 'owner' || role === 'administrator';
    });

    const canInviteMembers = computed(() => {
        const role = getUserRole();
        return role === 'owner' || role === 'administrator';
    });

    const canManageMembers = computed(() => {
        const role = getUserRole();
        return role === 'owner';
    });

    const canRunScans = computed(() => {
        const role = getUserRole();
        return role === 'owner' || role === 'administrator' || role === 'auditor';
    });

    const canAddAwsAccounts = computed(() => {
        const role = getUserRole();
        return role === 'owner' || role === 'administrator';
    });

    const canViewReports = computed(() => {
        const role = getUserRole();
        return role !== null; // All roles can view reports
    });

    const canViewInsights = computed(() => {
        const role = getUserRole();
        return role !== null; // All roles can view insights
    });

    const canTransferOwnership = computed(() => {
        const role = getUserRole();
        return role === 'owner';
    });

    const canDeleteOrganization = computed(() => {
        const role = getUserRole();
        return role === 'owner';
    });

    const canListMembers = computed(() => {
        const role = getUserRole();
        return role === 'owner' || role === 'administrator';
    });

    return {
        getUserRole,
        canManageSettings,
        canInviteMembers,
        canManageMembers,
        canRunScans,
        canAddAwsAccounts,
        canViewReports,
        canViewInsights,
        canTransferOwnership,
        canDeleteOrganization,
        canListMembers,
    };
}
