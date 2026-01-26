<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { useOrganizationMembers } from '@/composables/useOrganizationMembers';
import { useOrganizationPermissions } from '@/composables/useOrganizationPermissions';
import { useOrganizations } from '@/composables/useOrganizations';
import { usePage } from '@inertiajs/vue3';
import type { OrganizationMember, OrganizationInvitation } from '@/types/organization';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InviteMemberModal from './InviteMemberModal.vue';
import TransferOwnershipModal from './TransferOwnershipModal.vue';

const props = defineProps<{
    orgId: string;
}>();

const { members, invitations, loading, fetchMembers, fetchInvitations, updateMemberRole, removeMember, cancelInvitation } = useOrganizationMembers();
const { canManageMembers, canInviteMembers, canListMembers } = useOrganizationPermissions();
const { currentOrganization } = useOrganizations();
const page = usePage();

const showInviteModal = ref(false);
const showTransferModal = ref(false);
const editingRole = ref<string | null>(null);
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

const handleRoleChange = async (member: OrganizationMember, newRole: string) => {
    if (!member.id) {
        // Owner - cannot change role
        return;
    }

    try {
        await updateMemberRole(props.orgId, member.id, newRole);
        editingRole.value = null;
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
            <div class="overflow-x-auto">
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
                            <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                Loading members...
                            </td>
                        </tr>
                        <tr v-else-if="members.length === 0">
                            <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
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
                            <td v-if="canManageMembers" class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end space-x-2">
                                    <!-- Role Change Dropdown (only for non-owners) -->
                                    <div v-if="member.role !== 'owner' && member.id" class="relative">
                                        <select
                                            v-if="editingRole === member.id"
                                            v-model="member.role"
                                            @change="handleRoleChange(member, member.role || '')"
                                            @blur="editingRole = null"
                                            class="text-xs rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                                            autofocus
                                        >
                                            <option value="administrator">Administrator</option>
                                            <option value="auditor">Auditor</option>
                                            <option value="viewer">Viewer</option>
                                        </select>
                                        <button
                                            v-else
                                            @click="editingRole = member.id"
                                            class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                                        >
                                            Change Role
                                        </button>
                                    </div>
                                    
                                    <!-- Assign Role (for members with no role) -->
                                    <div v-if="member.role === null && member.id" class="relative">
                                        <select
                                            v-if="editingRole === member.id"
                                            v-model="member.role"
                                            @change="handleRoleChange(member, member.role || '')"
                                            @blur="editingRole = null"
                                            class="text-xs rounded-md border-gray-300 dark:border-gray-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                                            autofocus
                                        >
                                            <option value="">-- Select role --</option>
                                            <option value="administrator">Administrator</option>
                                            <option value="auditor">Auditor</option>
                                            <option value="viewer">Viewer</option>
                                        </select>
                                        <button
                                            v-else
                                            @click="editingRole = member.id"
                                            class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                                        >
                                            Assign Role
                                        </button>
                                    </div>
                                    
                                    <!-- Remove Member (only for non-owners) -->
                                    <button
                                        v-if="member.role !== 'owner' && member.id && member.user_id !== currentUserId"
                                        @click="handleRemoveMember(member)"
                                        class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                    >
                                        Remove
                                    </button>
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
            <div class="overflow-x-auto">
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
    </div>
</template>
