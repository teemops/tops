import { ref, computed } from 'vue';
import { useNotifications } from './useNotifications';
import type { OrganizationMember, OrganizationInvitation } from '@/types/organization';

// Use the configured axios instance from bootstrap.ts
const axios = (window as any).axios;

const members = ref<OrganizationMember[]>([]);
const invitations = ref<OrganizationInvitation[]>([]);
const loading = ref(false);
const error = ref<string | null>(null);

export function useOrganizationMembers() {
    const { showSuccess, showError } = useNotifications();

    const fetchMembers = async (orgId: string) => {
        loading.value = true;
        error.value = null;

        try {
            const response = await axios.get(`/api/organizations/${orgId}/members`);
            members.value = response.data.members;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to load members';
            console.error('Error fetching members:', err);
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const inviteMember = async (orgId: string, email: string) => {
        loading.value = true;
        error.value = null;

        try {
            const response = await axios.post(`/api/organizations/${orgId}/members/invite`, { email });
            showSuccess('Invitation sent successfully');
            await fetchInvitations(orgId); // Refresh invitations list
            return response.data;
        } catch (err: any) {
            const errorMessage = err.response?.data?.error || err.response?.data?.message || 'Failed to send invitation';
            error.value = errorMessage;
            showError(errorMessage);
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const updateMemberRole = async (orgId: string, memberId: string, role: string) => {
        loading.value = true;
        error.value = null;

        try {
            const response = await axios.put(`/api/organizations/${orgId}/members/${memberId}`, { role });
            showSuccess('Member role updated successfully');
            await fetchMembers(orgId); // Refresh members list
            return response.data;
        } catch (err: any) {
            const errorMessage = err.response?.data?.error || err.response?.data?.message || 'Failed to update member role';
            error.value = errorMessage;
            showError(errorMessage);
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const removeMember = async (orgId: string, memberId: string) => {
        loading.value = true;
        error.value = null;

        try {
            await axios.delete(`/api/organizations/${orgId}/members/${memberId}`);
            showSuccess('Member removed successfully');
            await fetchMembers(orgId); // Refresh members list
            return true;
        } catch (err: any) {
            const errorMessage = err.response?.data?.error || err.response?.data?.message || 'Failed to remove member';
            error.value = errorMessage;
            showError(errorMessage);
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const fetchInvitations = async (orgId: string) => {
        loading.value = true;
        error.value = null;

        try {
            const response = await axios.get(`/api/organizations/${orgId}/invitations`);
            invitations.value = response.data.invitations;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Failed to load invitations';
            console.error('Error fetching invitations:', err);
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const cancelInvitation = async (orgId: string, invitationId: string) => {
        loading.value = true;
        error.value = null;

        try {
            await axios.delete(`/api/organizations/${orgId}/invitations/${invitationId}`);
            showSuccess('Invitation cancelled successfully');
            await fetchInvitations(orgId); // Refresh invitations list
            return true;
        } catch (err: any) {
            const errorMessage = err.response?.data?.error || err.response?.data?.message || 'Failed to cancel invitation';
            error.value = errorMessage;
            showError(errorMessage);
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const transferOwnership = async (orgId: string, newOwnerUserId: string) => {
        loading.value = true;
        error.value = null;

        try {
            const response = await axios.post(`/api/organizations/${orgId}/transfer-ownership`, {
                user_id: newOwnerUserId,
            });
            showSuccess('Ownership transferred successfully');
            await fetchMembers(orgId); // Refresh members list
            return response.data;
        } catch (err: any) {
            const errorMessage = err.response?.data?.error || err.response?.data?.message || 'Failed to transfer ownership';
            error.value = errorMessage;
            showError(errorMessage);
            throw err;
        } finally {
            loading.value = false;
        }
    };

    const acceptInvitation = async (token: string) => {
        loading.value = true;
        error.value = null;

        try {
            const response = await axios.post(`/api/organizations/invitations/${token}/accept`);
            showSuccess('Invitation accepted successfully');
            return response.data;
        } catch (err: any) {
            const errorMessage = err.response?.data?.error || err.response?.data?.message || 'Failed to accept invitation';
            error.value = errorMessage;
            showError(errorMessage);
            throw err;
        } finally {
            loading.value = false;
        }
    };

    return {
        members: computed(() => members.value),
        invitations: computed(() => invitations.value),
        loading: computed(() => loading.value),
        error: computed(() => error.value),
        fetchMembers,
        inviteMember,
        updateMemberRole,
        removeMember,
        fetchInvitations,
        cancelInvitation,
        transferOwnership,
        acceptInvitation,
    };
}
