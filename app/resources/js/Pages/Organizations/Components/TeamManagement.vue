<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useOrganizationMembers } from '@/composables/useOrganizationMembers';
import { useOrganizationPermissions } from '@/composables/useOrganizationPermissions';
import { useOrganizations } from '@/composables/useOrganizations';
import { usePage } from '@inertiajs/vue3';
import type { OrganizationMember, OrganizationInvitation } from '@/types/organization';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Dropdown from '@/Components/Dropdown.vue';
import InviteMemberModal from './InviteMemberModal.vue';
import TransferOwnershipModal from './TransferOwnershipModal.vue';

const props = defineProps<{
    orgId: string;
}>();

const { members, invitations, loading, error, fetchMembers, fetchInvitations, updateMemberRole, removeMember, cancelInvitation } = useOrganizationMembers();
const { canManageMembers, canInviteMembers, canListMembers } = useOrganizationPermissions();
const { currentOrganization } = useOrganizations();
const page = usePage();

const showInviteModal = ref(false);
const showTransferModal = ref(false);
const showRoleModal = ref(false);
const selectedMemberForRole = ref<OrganizationMember | null>(null);
const selectedRole = ref<string>('auditor');
const removingMemberId = ref<string | null>(null);

const currentUserId = computed(() => (page.props as any).auth?.user?.id || '');

const loadData = async () => {
    if (props.orgId) {
        await Promise.all([
            fetchMembers(props.orgId),
            fetchInvitations(props.orgId),
        ]);
    }
};

onMounted(() => {
    loadData();
});

watch(() => props.orgId, () => {
    loadData();
});

const getRoleBadgeClass = (role: string | null) => {
    if (!role) return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
    
    const classes: Record<string, string> = {
        owner: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
        administrator: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
        auditor: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
        viewer: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
    };
    return classes[role] || classes.viewer;
};

const openRoleModal = (member: OrganizationMember) => {
    if (!member.id || member.role === 'owner') {
        return;
    }
    selectedMemberForRole.value = member;
    selectedRole.value = member.role || 'auditor'; // Default to auditor if no role
    showRoleModal.value = true;
};

const handleRoleChange = async () => {
    if (!selectedMemberForRole.value?.id || !selectedRole.value) {
        return;
    }

    try {
        await updateMemberRole(props.orgId, selectedMemberForRole.value.id, selectedRole.value);
        showRoleModal.value = false;
        selectedMemberForRole.value = null;
        selectedRole.value = 'auditor';
    } catch (err) {
        // Error handled by composable
    }
};

const handleRemoveMember = async (member: OrganizationMember) => {
    if (!member.id || !confirm(`Are you sure you want to remove ${member.user.name} from this organization?`)) {
        return;
    }

    try {
        await removeMember(props.orgId, member.id);
        removingMemberId.value = null;
    } catch (err) {
        // Error handled by composable
    }
};

const handleCancelInvitation = async (invitation: OrganizationInvitation) => {
    if (!confirm(`Are you sure you want to cancel the invitation to ${invitation.email}?`)) {
        return;
    }

    try {
        await cancelInvitation(props.orgId, invitation.id);
    } catch (err) {
        // Error handled by composable
    }
};

const formatExpiresAt = (expiresAt: string) => {
    const date = new Date(expiresAt);
    const now = new Date();
    const diff = date.getTime() - now.getTime();
    const hours = Math.floor(diff / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    
    if (hours > 0) {
        return `${hours}h ${minutes}m`;
    }
    return `${minutes}m`;
};
</script>

<template>
    <div class="space-y-6">
        <!-- Error Message -->
        <div v-if="error" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800 dark:text-red-200">Error loading team data</h3>
                    <div class="mt-2 text-sm text-red-700 dark:text-red-300">
                        <p>{{ error }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Invite Member Section -->
        <div v-if="canInviteMembers" class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Invite Team Member</h3>
            </div>
            <div class="px-6 py-4">
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    Invite a team member by email. They will receive an invitation link and can join your organization. A role will be assigned after they accept the invitation.
                </p>
                <PrimaryButton @click="showInviteModal = true">
                    Invite Member
                </PrimaryButton>
            </div>
        </div>

        <!-- Members List -->
        <div v-if="canListMembers" class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Team Members</h3>
            </div>
            <div >
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Role</th>
                            <th v-if="canManageMembers" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-if="loading && members.length === 0">
                            <td :colspan="canManageMembers ? 4 : 3" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                Loading members...
                            </td>
                        </tr>
                        <tr v-else-if="members.length === 0">
                            <td :colspan="canManageMembers ? 4 : 3" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                No members found
                            </td>
                        </tr>
                        <tr
                            v-for="member in members"
                            :key="member.user_id"
                            :class="{
                                'bg-yellow-50 dark:bg-yellow-900/20': member.role === null
                            }"
                        >
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ member.user.name }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ member.user.email }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    v-if="member.role"
                                    :class="['inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium', getRoleBadgeClass(member.role)]"
                                >
                                    {{ member.role.charAt(0).toUpperCase() + member.role.slice(1) }}
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300"
                                >
                                    No role assigned
                                </span>
                            </td>
                            <td v-if="canManageMembers" class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium relative z-10">
                                <div v-if="member.role !== 'owner' && member.id" class="flex items-center justify-end">
                                    <Dropdown align="right" width="48">
                                        <template #trigger>
                                            <button
                                                type="button"
                                                class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                            >
                                                Actions
                                                <svg
                                                    class="ml-2 -mr-1 h-4 w-4 text-gray-400"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path
                                                        stroke-linecap="round"
                                                        stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M19 9l-7 7-7-7"
                                                    />
                                                </svg>
                                            </button>
                                        </template>
                                        <template #content>
                                            <button
                                                v-if="member.role === null"
                                                @click="openRoleModal(member)"
                                                class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100 focus:bg-gray-100 focus:outline-none dark:text-gray-300 dark:hover:bg-gray-800 dark:focus:bg-gray-800"
                                            >
                                                Assign Role
                                            </button>
                                            <button
                                                v-else
                                                @click="openRoleModal(member)"
                                                class="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100 focus:bg-gray-100 focus:outline-none dark:text-gray-300 dark:hover:bg-gray-800 dark:focus:bg-gray-800"
                                            >
                                                Change Role
                                            </button>
                                            <button
                                                v-if="member.user_id !== currentUserId"
                                                @click="handleRemoveMember(member)"
                                                class="block w-full px-4 py-2 text-start text-sm leading-5 text-red-600 transition duration-150 ease-in-out hover:bg-gray-100 focus:bg-gray-100 focus:outline-none dark:text-red-400 dark:hover:bg-gray-800 dark:focus:bg-gray-800"
                                            >
                                                Remove
                                            </button>
                                        </template>
                                    </Dropdown>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pending Invitations -->
        <div v-if="canInviteMembers" class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Pending Invitations</h3>
            </div>
            <div class="overflow-x-auto overflow-y-visible">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Invited By</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Expires In</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <tr v-if="loading && invitations.length === 0">
                            <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                Loading invitations...
                            </td>
                        </tr>
                        <tr v-else-if="invitations.length === 0">
                            <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                No pending invitations
                            </td>
                        </tr>
                        <tr
                            v-for="invitation in invitations"
                            :key="invitation.id"
                        >
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ invitation.email }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ invitation.invited_by.name }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ formatExpiresAt(invitation.expires_at) }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button
                                    @click="handleCancelInvitation(invitation)"
                                    class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                >
                                    Cancel
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Transfer Ownership (Owner only) -->
        <div v-if="canManageMembers" class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Transfer Ownership</h3>
            </div>
            <div class="px-6 py-4">
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    Transfer ownership of this organization to another team member. You will lose owner privileges and will need to be assigned a new role.
                </p>
                <PrimaryButton
                    @click="showTransferModal = true"
                    class="bg-red-600 hover:bg-red-700"
                >
                    Transfer Ownership
                </PrimaryButton>
            </div>
        </div>

        <!-- Modals -->
        <InviteMemberModal
            v-model="showInviteModal"
            :org-id="orgId"
            @invited="loadData"
        />

        <TransferOwnershipModal
            v-model="showTransferModal"
            :org-id="orgId"
            :members="members"
            :current-user-id="currentUserId"
            @transferred="loadData"
        />

        <!-- Role Selection Modal -->
        <div
            v-if="showRoleModal && selectedMemberForRole"
            class="fixed z-10 inset-0 overflow-y-auto"
            @click.self="showRoleModal = false"
        >
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form @submit.prevent="handleRoleChange">
                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
                                        {{ selectedMemberForRole.role === null ? 'Assign Role' : 'Change Role' }}
                                    </h3>
                                    <div class="mt-4">
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                                            {{ selectedMemberForRole.role === null 
                                                ? `Assign a role to ${selectedMemberForRole.user.name}` 
                                                : `Change role for ${selectedMemberForRole.user.name}` }}
                                        </p>
                                        <label for="role" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                            Role
                                        </label>
                                        <select
                                            id="role"
                                            v-model="selectedRole"
                                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:text-white sm:text-sm"
                                            required
                                        >
                                            <option value="administrator">Administrator</option>
                                            <option value="auditor">Auditor</option>
                                            <option value="viewer">Viewer</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <PrimaryButton
                                type="submit"
                                class="sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                {{ selectedMemberForRole.role === null ? 'Assign Role' : 'Update Role' }}
                            </PrimaryButton>
                            <button
                                type="button"
                                @click="showRoleModal = false; selectedMemberForRole = null; selectedRole = 'auditor'"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
