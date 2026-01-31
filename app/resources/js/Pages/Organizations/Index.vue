<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import SidebarAppLayout from '@/Layouts/SidebarAppLayout.vue';
import { useOrganizations, type Organization } from '@/composables/useOrganizations';
import { useNotifications } from '@/composables/useNotifications';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import CreateOrganizationModal from './CreateOrganizationModal.vue';

const { organizations, currentOrganization, loading, error, fetchOrganizations, switchOrganization, deleteOrganization } = useOrganizations();
const { showSuccess, showError } = useNotifications();
const showCreateModal = ref(false);
const showDeleteModal = ref(false);
const organizationToDelete = ref<Organization | null>(null);
const deleting = ref(false);

onMounted(async () => {
    try {
        await fetchOrganizations();
    } catch (error) {
        console.error('Failed to fetch organizations:', error);
    }
});

const handleSwitch = async (org: Organization) => {
    try {
        await switchOrganization(org.org_id);
        showSuccess(`Switched to ${org.name}`);
    } catch (err: any) {
        showError(err.message || 'Failed to switch organization');
    }
};

const handleDelete = (org: Organization) => {
    organizationToDelete.value = org;
    showDeleteModal.value = true;
};

const confirmDelete = async () => {
    if (!organizationToDelete.value) return;

    deleting.value = true;
    try {
        await deleteOrganization(organizationToDelete.value.org_id);
        showSuccess('Organization deleted successfully');
        showDeleteModal.value = false;
        organizationToDelete.value = null;
    } catch (err: any) {
        showError(err.response?.data?.error || 'Failed to delete organization');
    } finally {
        deleting.value = false;
    }
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
};
</script>

<template>
    <SidebarAppLayout>
        <Head title="Organizations" />
        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                <!-- Page Header -->
                <div class="mb-8 flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Organizations</h1>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Manage your organizations and switch between them</p>
                    </div>
                    <PrimaryButton @click="showCreateModal = true">
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Add Organization
                    </PrimaryButton>
                </div>

                <!-- Error Message -->
                <div v-if="!loading && error" class="mb-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                    <p class="text-sm text-red-800 dark:text-red-200">{{ error }}</p>
                </div>

                <!-- Loading State -->
                <div v-if="loading && organizations.length === 0" class="text-center py-12">
                    <svg class="animate-spin h-8 w-8 text-gray-400 mx-auto" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Loading organizations...</p>
                </div>

                <!-- Empty State -->
                    <div v-else-if="!loading && organizations.length === 0" class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No organizations</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by creating a new organization.</p>
                    <div class="mt-6">
                        <PrimaryButton @click="showCreateModal = true">
                            <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Create Organization
                        </PrimaryButton>
                    </div>
                </div>

                <!-- Organizations Grid -->
                <div v-else-if="organizations.length > 0" class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="org in organizations"
                        :key="org.id"
                        class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md transition-shadow"
                    >
                        <div class="p-6">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center">
                                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">{{ org.name }}</h3>
                                        <span
                                            v-if="org.is_default"
                                            class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400"
                                        >
                                            Default
                                        </span>
                                    </div>
                                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                        {{ org.aws_accounts_count || 0 }} AWS {{ org.aws_accounts_count === 1 ? 'account' : 'accounts' }}
                                    </p>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-500">
                                        Created {{ formatDate(org.created_at) }}
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
                                            <DropdownLink :href="route('organizations.settings', org.org_id)">
                                                Settings
                                            </DropdownLink>
                                            <button
                                                v-if="!org.is_default && (org.aws_accounts_count || 0) === 0"
                                                @click="handleDelete(org)"
                                                class="block w-full text-left px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-gray-100 dark:hover:bg-gray-700"
                                            >
                                                Delete
                                            </button>
                                        </template>
                                    </Dropdown>
                                </div>
                            </div>
                            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                <button
                                    v-if="currentOrganization?.org_id !== org.org_id"
                                    @click="handleSwitch(org)"
                                    class="w-full text-sm font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400"
                                >
                                    Switch to this organization
                                </button>
                                <span
                                    v-else
                                    class="w-full text-sm font-medium text-gray-500 dark:text-gray-400"
                                >
                                    Current organization
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Organization Modal -->
        <CreateOrganizationModal
            v-model="showCreateModal"
            @created="fetchOrganizations"
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
                                    Delete Organization
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Are you sure you want to delete "{{ organizationToDelete?.name }}"? This action cannot be undone.
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
                            {{ deleting ? 'Deleting...' : 'Delete' }}
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


