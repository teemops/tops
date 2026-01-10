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

const { accounts, loading, error, fetchAccounts, deleteAccount, startPolling, stopPolling } = useAwsAccounts();
const { currentOrganization } = useOrganizations();
const { showSuccess, showError } = useNotifications();

const showAddModal = ref(false);
const showDeleteModal = ref(false);
const accountToDelete = ref<AwsAccount | null>(null);
const deleting = ref(false);

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

const handleDelete = (account: AwsAccount) => {
    accountToDelete.value = account;
    showDeleteModal.value = true;
};

const confirmDelete = async () => {
    if (!accountToDelete.value) return;

    deleting.value = true;
    try {
        await deleteAccount(accountToDelete.value.id);
        showSuccess('AWS account deleted successfully');
        showDeleteModal.value = false;
        accountToDelete.value = null;
    } catch (err: any) {
        showError(err.response?.data?.error || 'Failed to delete AWS account');
    } finally {
        deleting.value = false;
    }
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
                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
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
        <div
            v-if="showDeleteModal"
            class="fixed z-10 inset-0 overflow-y-auto"
            @click.self="showDeleteModal = false"
        >
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 dark:bg-red-900/30 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white">
                                    {{ accountToDelete?.status === 'pending' ? 'Cancel AWS Account Setup' : 'Remove AWS Account' }}
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        <span v-if="accountToDelete?.status === 'pending'">
                                            Are you sure you want to cancel the setup for "{{ accountToDelete?.name }}"? This will remove the pending account.
                                        </span>
                                        <span v-else>
                                            Are you sure you want to remove "{{ accountToDelete?.name }}"? This action cannot be undone.
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button
                            @click="confirmDelete"
                            :disabled="deleting"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
                        >
                            {{ deleting ? 'Removing...' : (accountToDelete?.status === 'pending' ? 'Cancel Setup' : 'Remove') }}
                        </button>
                        <button
                            @click="showDeleteModal = false"
                            :disabled="deleting"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </SidebarAppLayout>
</template>
