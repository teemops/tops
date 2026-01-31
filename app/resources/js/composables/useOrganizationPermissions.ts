import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export type OrganizationRole = 'owner' | 'administrator' | 'auditor' | 'viewer' | null;

export function useOrganizationPermissions() {
    const page = usePage();

    // User's role in current organization (from backend; set from cookie so sidebar is correct on every page)
    const getUserRole = (): OrganizationRole => {
        const role = (page.props as any).organization_role;
        return role ?? null;
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
        return role === 'owner' || role === 'administrator';
    });

    const canViewAwsAccounts = computed(() => {
        const role = getUserRole();
        return role !== null && role !== 'viewer';
    });

    const canViewScans = computed(() => {
        const role = getUserRole();
        return role !== null && role !== 'viewer';
    });

    const canDeleteAwsAccounts = computed(() => {
        const role = getUserRole();
        return role === 'owner' || role === 'administrator';
    });

    const canRunScans = computed(() => {
        const role = getUserRole();
        return role === 'owner' || role === 'administrator' || role === 'auditor';
    });

    const canAddAwsAccounts = computed(() => {
        const role = getUserRole();
        return role === 'owner' || role === 'administrator';
    });

    const canViewFindings = computed(() => {
        const role = getUserRole();
        return role !== null; // All roles can view findings
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
        canViewAwsAccounts,
        canViewScans,
        canDeleteAwsAccounts,
        canRunScans,
        canAddAwsAccounts,
        canViewFindings,
        canViewInsights,
        canTransferOwnership,
        canDeleteOrganization,
        canListMembers,
    };
}
