<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import SidebarAppLayout from '@/Layouts/SidebarAppLayout.vue';
import { useAwsAccounts, type AwsAccount } from '@/composables/useAwsAccounts';
import { useOrganizations } from '@/composables/useOrganizations';
import { useNotifications } from '@/composables/useNotifications';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import AddAwsAccountModal from './AddAwsAccountModal.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';

const { accounts, loading, error, fetchAccounts, updateAccount, deleteAccount, startPolling, stopPolling } = useAwsAccounts();
const { currentOrganization } = useOrganizations();
const { showSuccess, showError } = useNotifications();

const showAddModal = ref(false);
const showDeleteModal = ref(false);
const deleteStep = ref<'confirm' | 'instructions'>('confirm');
const accountToDelete = ref<AwsAccount | null>(null);
const deleting = ref(false);
const awsAccountIdInput = ref('');
const awsAccountIdError = ref('');

// Inline editing state
const editingAccountId = ref<string | null>(null);
const editingName = ref('');
const saving = ref(false);

const loadAccounts = async () => {
    if (currentOrganization.value?.org_id) {
        stopPolling(); // Stop any existing polling
        await fetchAccounts();
        
        // Start polling for pending accounts
        accounts.value.forEach(account => {
            if (account.status === 'pending') {
                startPolling(account.id);
            }
        });
    }
};

onMounted(async () => {
    await loadAccounts();
});

onUnmounted(() => {
    stopPolling();
});

// Watch for organization changes and refetch accounts
watch(() => currentOrganization.value?.org_id, async () => {
    await loadAccounts();
});

// Inline editing functions
const startEditing = (account: AwsAccount) => {
    // Only allow editing for completed accounts
    if (account.status !== 'completed') return;
    
    editingAccountId.value = account.id;
    editingName.value = account.name;
};

const cancelEditing = () => {
    editingAccountId.value = null;
    editingName.value = '';
};

const saveEdit = async (accountId: string) => {
    if (!editingName.value.trim()) {
        showError('Account name cannot be empty');
        return;
    }

    // Check if name actually changed
    const account = accounts.value.find(acc => acc.id === accountId);
    if (account && editingName.value.trim() === account.name) {
        cancelEditing();
        return;
    }

    saving.value = true;
    try {
        await updateAccount(accountId, editingName.value.trim());
        showSuccess('Account name updated successfully');
        editingAccountId.value = null;
        editingName.value = '';
    } catch (err: any) {
        showError(err.response?.data?.message || err.response?.data?.error || 'Failed to update account name');
        // Revert to original name on error
        if (account) {
            editingName.value = account.name;
        }
    } finally {
        saving.value = false;
    }
};

const handleKeydown = (event: KeyboardEvent, accountId: string) => {
    if (event.key === 'Enter') {
        event.preventDefault();
        saveEdit(accountId);
    } else if (event.key === 'Escape') {
        event.preventDefault();
        cancelEditing();
    }
};

// Delete functions
const handleDelete = (account: AwsAccount) => {
    // Always show the modal - never delete directly
    accountToDelete.value = account;
    deleteStep.value = 'confirm';
    awsAccountIdInput.value = '';
    awsAccountIdError.value = '';
    showDeleteModal.value = true;
};

const validateAwsAccountId = () => {
    if (!accountToDelete.value) return false;

    // For pending accounts, skip validation
    if (!accountToDelete.value.awsAccountId) {
        return true;
    }

    // Validate that entered ID matches
    if (awsAccountIdInput.value.trim() !== accountToDelete.value.awsAccountId) {
        awsAccountIdError.value = 'AWS account ID does not match';
        return false;
    }

    awsAccountIdError.value = '';
    return true;
};

const proceedToInstructions = async () => {
    if (!accountToDelete.value) return;

    // For pending accounts (no AWS Account ID), allow immediate deletion
    if (!accountToDelete.value.awsAccountId) {
        try {
            deleting.value = true;
            await deleteAccount(accountToDelete.value.id);
            showSuccess('AWS account deleted successfully');
            closeDeleteModal();
        } catch (err: any) {
            showError(err.response?.data?.error || 'Failed to delete AWS account');
        } finally {
            deleting.value = false;
        }
        return;
    }

    // For completed accounts, validate AWS account ID and move to instructions
    // DO NOT delete here - deletion happens via SQS when CloudFormation stack is deleted
    if (!validateAwsAccountId()) {
        return;
    }

    // Move to instructions step
    deleteStep.value = 'instructions';
};

const openCloudFormationConsole = async () => {
    if (!accountToDelete.value) return;

    try {
        // Fetch CloudFormation URL from backend
        const axios = (window as any).axios;
        const response = await axios.get(`/api/aws-accounts/${accountToDelete.value.id}/cloudformation-url`);
        
        // Open AWS Console in new window
        window.open(response.data.cloudFormationUrl, '_blank');
        
        showSuccess(`Opened AWS Console. Please delete the CloudFormation stack "${response.data.stackName}" to complete account removal.`);
    } catch (err: any) {
        showError('Failed to open AWS Console. Please manually navigate to CloudFormation in your AWS Console.');
    }
};

const closeDeleteModal = () => {
    showDeleteModal.value = false;
    deleteStep.value = 'confirm';
    accountToDelete.value = null;
    awsAccountIdInput.value = '';
    awsAccountIdError.value = '';
};

const getStatusBadge = (status: string) => {
    switch (status) {
        case 'completed':
            return {
                class: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
                text: 'Completed',
                icon: '🟢',
            };
        case 'pending':
            return {
                class: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
                text: 'Pending',
                icon: '🟡',
            };
        case 'error':
            return {
                class: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
                text: 'Error',
                icon: '🔴',
            };
        default:
            return {
                class: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                text: status,
                icon: '',
            };
    }
};

const formatDate = (dateString?: string) => {
    if (!dateString) return 'Never';
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins} ${diffMins === 1 ? 'minute' : 'minutes'} ago`;
    if (diffHours < 24) return `${diffHours} ${diffHours === 1 ? 'hour' : 'hours'} ago`;
    if (diffDays < 7) return `${diffDays} ${diffDays === 1 ? 'day' : 'days'} ago`;
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

const handleAccountCreated = async () => {
    await fetchAccounts();
    // Start polling for the new account if it's pending
    const pendingAccounts = accounts.value.filter(acc => acc.status === 'pending');
    pendingAccounts.forEach(account => {
        startPolling(account.id);
    });
};
</script>

<template>
    <SidebarAppLayout>
        <Head title="AWS Accounts" />

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                <!-- Page Header -->
                <div class="mb-8 flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">AWS Accounts</h1>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                            Manage AWS accounts for {{ currentOrganization?.name || 'your organization' }}
                        </p>
                    </div>
                    <PrimaryButton @click="showAddModal = true">
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add AWS Account
                    </PrimaryButton>
                </div>

                <!-- Error Message -->
                <div v-if="!loading && error" class="mb-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                    <p class="text-sm text-red-800 dark:text-red-200">{{ error }}</p>
                </div>

                <!-- Loading State -->
                <div v-if="loading && accounts.length === 0" class="text-center py-12">
                    <svg class="animate-spin h-8 w-8 text-gray-400 mx-auto" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Loading AWS accounts...</p>
                </div>

                <!-- Empty State -->
                <div v-else-if="!loading && accounts.length === 0" class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No AWS accounts</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by adding your first AWS account.</p>
                    <div class="mt-6">
                        <PrimaryButton @click="showAddModal = true">
                            <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Add AWS Account
                        </PrimaryButton>
                    </div>
                </div>

                <!-- Accounts Grid -->
                <div v-else-if="accounts.length > 0" class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="account in accounts"
                        :key="account.id"
                        class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition-shadow"
                    >
                        <div class="p-6">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center">
                                        <div v-if="editingAccountId === account.id" class="flex-1">
                                            <TextInput
                                                v-model="editingName"
                                                @keydown="handleKeydown($event, account.id)"
                                                @blur="saveEdit(account.id)"
                                                class="text-lg font-semibold w-full"
                                                autofocus
                                            />
                                        </div>
                                        <h3
                                            v-else
                                            @click="startEditing(account)"
                                            :class="[
                                                'text-lg font-semibold text-gray-900 dark:text-white',
                                                account.status === 'completed' ? 'cursor-pointer hover:text-blue-600 dark:hover:text-blue-400 transition-colors' : ''
                                            ]"
                                            :title="account.status === 'completed' ? 'Click to edit name' : ''"
                                        >
                                            {{ account.name }}
                                        </h3>
                                    </div>
                                    <p v-if="account.awsAccountId" class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                        Account ID: {{ account.awsAccountId }}
                                    </p>
                                    <p v-else class="mt-2 text-sm text-gray-500 dark:text-gray-500 italic">
                                        Account ID: Pending...
                                    </p>
                                    <div class="mt-2 flex items-center space-x-2">
                                        <span
                                            :class="[
                                                'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                                                getStatusBadge(account.status).class
                                            ]"
                                        >
                                            <span class="mr-1">{{ getStatusBadge(account.status).icon }}</span>
                                            {{ getStatusBadge(account.status).text }}
                                        </span>
                                        <span v-if="account.status === 'completed' && account.lastScanAt" class="text-xs text-gray-500 dark:text-gray-400">
                                            Last Scan: {{ formatDate(account.lastScanAt) }}
                                        </span>
                                    </div>
                                    <p v-if="account.status === 'pending'" class="mt-2 text-xs text-yellow-600 dark:text-yellow-400">
                                        Waiting for CloudFormation completion...
                                    </p>
                                </div>
                                <div class="ml-4">
                                    <Dropdown align="right" width="48">
                                        <template #trigger>
                                            <button class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
                                                </svg>
                                            </button>
                                        </template>
                                        <template #content>
                                            <button
                                                v-if="account.status !== 'pending'"
                                                @click="handleDelete(account)"
                                                class="block w-full text-left px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-gray-100 dark:hover:bg-gray-700"
                                            >
                                                Remove
                                            </button>
                                            <button
                                                v-else
                                                @click="handleDelete(account)"
                                                class="block w-full text-left px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-gray-100 dark:hover:bg-gray-700"
                                            >
                                                Cancel
                                            </button>
                                        </template>
                                    </Dropdown>
                                </div>
                            </div>
                            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                <div class="flex space-x-2">
                                    <button
                                        v-if="account.status === 'completed'"
                                        class="flex-1 text-sm font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400"
                                    >
                                        View
                                    </button>
                                    <button
                                        v-if="account.status === 'pending'"
                                        @click="startPolling(account.id)"
                                        class="flex-1 text-sm font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400"
                                    >
                                        Refresh Status
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add AWS Account Modal -->
        <AddAwsAccountModal
            v-model="showAddModal"
            @created="handleAccountCreated"
        />

        <!-- Delete Confirmation Modal -->
        <Teleport to="body">
            <div
                v-if="showDeleteModal"
                class="fixed z-50 inset-0 overflow-y-auto"
                @click.self="closeDeleteModal"
            >
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <!-- Step 1: AWS Account ID Confirmation -->
                    <div v-if="deleteStep === 'confirm'">
                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900/30 sm:mx-0 sm:h-10 sm:w-10">
                                    <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                </div>
                                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                                        {{ accountToDelete?.status === 'pending' ? 'Cancel AWS Account Setup' : 'Remove AWS Account' }}
                                    </h3>
                                    <div class="mt-4">
                                        <p v-if="accountToDelete?.status === 'pending'" class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                                            Are you sure you want to cancel the setup for "{{ accountToDelete?.name }}"? This will remove the pending account.
                                        </p>
                                        <div v-else>
                                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                                                Please enter the AWS account ID in the box below to remove this AWS account from your Organization in Teemops.
                                            </p>
                                            <div class="mb-4">
                                                <p class="text-xs text-gray-600 dark:text-gray-400 mb-2">
                                                    Account: <span class="font-semibold">{{ accountToDelete?.name }}</span>
                                                </p>
                                                <p class="text-xs text-gray-600 dark:text-gray-400">
                                                    AWS Account ID: <span class="font-mono font-semibold">{{ accountToDelete?.awsAccountId }}</span>
                                                </p>
                                            </div>
                                            <div>
                                                <InputLabel for="aws_account_id" value="AWS Account ID" />
                                                <TextInput
                                                    id="aws_account_id"
                                                    v-model="awsAccountIdInput"
                                                    type="text"
                                                    class="mt-1 block w-full"
                                                    placeholder="123456789012"
                                                    maxlength="12"
                                                    @input="awsAccountIdError = ''"
                                                />
                                                <InputError :message="awsAccountIdError" class="mt-2" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button
                                @click="proceedToInstructions"
                                :disabled="!!(deleting || (accountToDelete?.awsAccountId && awsAccountIdInput.trim() !== accountToDelete.awsAccountId))"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
                            >
                                {{ deleting ? 'Processing...' : (accountToDelete?.status === 'pending' ? 'Cancel Setup' : 'Proceed') }}
                            </button>
                            <button
                                @click="closeDeleteModal"
                                :disabled="deleting"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>

                    <!-- Step 2: AWS Console Instructions -->
                    <div v-else-if="deleteStep === 'instructions'">
                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 dark:bg-blue-900/30 sm:mx-0 sm:h-10 sm:w-10">
                                    <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                                        Delete CloudFormation Stack
                                    </h3>
                                    <div class="mt-4">
                                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                                            To complete the removal of this AWS account, please delete the CloudFormation stack <span class="font-mono font-semibold">tops-vendor-audit</span> in your AWS Console.
                                        </p>
                                        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-4">
                                            <p class="text-sm text-blue-800 dark:text-blue-200 font-medium mb-2">Instructions:</p>
                                            <ol class="list-decimal list-inside text-sm text-blue-700 dark:text-blue-300 space-y-1">
                                                <li>Click "Proceed to AWS Console" below</li>
                                                <li>Find and select the stack named <span class="font-mono font-semibold">tops-vendor-audit</span></li>
                                                <li>Delete the stack from your AWS Console</li>
                                                <li>The account will be automatically removed from Teemops once the stack is deleted</li>
                                            </ol>
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            Note: The account removal will be processed automatically via SQS when the CloudFormation stack is deleted.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <PrimaryButton
                                @click="openCloudFormationConsole"
                                class="sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                </svg>
                                Proceed to AWS Console
                            </PrimaryButton>
                            <button
                                @click="closeDeleteModal"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                            >
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            </div>
        </Teleport>
    </SidebarAppLayout>
</template>
