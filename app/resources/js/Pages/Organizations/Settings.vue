<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import SidebarAppLayout from '@/Layouts/SidebarAppLayout.vue';
import { useOrganizations } from '@/composables/useOrganizations';
import { useOrganizationPermissions } from '@/composables/useOrganizationPermissions';
import { useNotifications } from '@/composables/useNotifications';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import TeamManagement from './Components/TeamManagement.vue';

const props = defineProps<{
    orgId: string;
}>();

const { organizations, currentOrganization, loading, fetchOrganizations, updateOrganization, deleteOrganization } = useOrganizations();
const { canManageSettings, canManageMembers, getUserRole } = useOrganizationPermissions();
const { showSuccess, showError } = useNotifications();
const page = usePage();

const name = ref('');
const errors = ref<{ name?: string[] }>({});
const saving = ref(false);
const showDeleteModal = ref(false);
const deleting = ref(false);
const activeTab = ref<'general' | 'team'>('general');

const organization = computed(() => {
    return organizations.value.find(org => org.org_id === props.orgId);
});

const canDelete = computed(() => {
    if (!organization.value) return false;
    return !organization.value.is_default && (organization.value.aws_accounts_count || 0) === 0;
});

onMounted(async () => {
    // Fetch organizations if not already loaded
    if (organizations.value.length === 0) {
        await fetchOrganizations();
    }
    
    // Set name from organization data
    if (organization.value) {
        name.value = organization.value.name;
    }
});

const handleSubmit = async () => {
    errors.value = {};
    saving.value = true;

    try {
        await updateOrganization(props.orgId, name.value);
        showSuccess('Organization updated successfully');
        router.reload();
    } catch (err: any) {
        if (err.response?.data?.errors) {
            errors.value = err.response.data.errors;
        } else {
            showError(err.response?.data?.message || 'Failed to update organization');
        }
    } finally {
        saving.value = false;
    }
};

const handleDelete = () => {
    showDeleteModal.value = true;
};

const confirmDelete = async () => {
    deleting.value = true;
    try {
        await deleteOrganization(props.orgId);
        showSuccess('Organization deleted successfully');
        router.visit(route('organizations.index'));
    } catch (err: any) {
        showError(err.response?.data?.error || 'Failed to delete organization');
    } finally {
        deleting.value = false;
        showDeleteModal.value = false;
    }
};
</script>

<template>
    <SidebarAppLayout>
        <Head title="Organization Settings" />

        <div class="py-6">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 md:px-8">
                <!-- Page Header -->
                <div class="mb-8">
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Organization Settings</h1>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Manage your organization details and team</p>
                    <!-- Debug: Remove in production -->
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-500">
                        Debug: Role = {{ getUserRole() }}, Can Manage = {{ canManageSettings }}
                    </p>
                </div>

                <!-- Tabs -->
                <div v-if="organization && canManageSettings" class="mb-6 border-b border-gray-200 dark:border-gray-700">
                    <nav class="-mb-px flex space-x-8">
                        <button
                            @click="activeTab = 'general'"
                            :class="[
                                'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm',
                                activeTab === 'general'
                                    ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'
                            ]"
                        >
                            General
                        </button>
                        <button
                            @click="activeTab = 'team'"
                            :class="[
                                'whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm',
                                activeTab === 'team'
                                    ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'
                            ]"
                        >
                            Team
                        </button>
                    </nav>
                </div>

                <!-- Loading State -->
                <div v-if="loading && !organization" class="text-center py-12">
                    <svg class="animate-spin h-8 w-8 text-gray-400 mx-auto" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Loading organization...</p>
                </div>

                <!-- General Tab -->
                <div v-if="activeTab === 'general' && organization" class="space-y-6">
                    <!-- Settings Form -->
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">General</h2>
                    </div>
                    <form @submit.prevent="handleSubmit" class="px-6 py-6 space-y-6">
                        <div>
                            <InputLabel for="name" value="Organization name" />
                            <TextInput
                                id="name"
                                v-model="name"
                                type="text"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError :message="errors.name?.[0]" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-end pt-4 border-t border-gray-200 dark:border-gray-700">
                            <button
                                type="button"
                                @click="router.visit(route('organizations.index'))"
                                class="mr-3 inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                            >
                                Cancel
                            </button>
                            <PrimaryButton type="submit" :disabled="saving">
                                {{ saving ? 'Saving...' : 'Save changes' }}
                            </PrimaryButton>
                        </div>
                    </form>
                    </div>

                    <!-- Danger Zone -->
                <div
                    v-if="organization && canDelete"
                    class="mt-8 bg-white dark:bg-gray-800 shadow rounded-lg border border-red-200 dark:border-red-800"
                >
                    <div class="px-6 py-4 border-b border-red-200 dark:border-red-800">
                        <h2 class="text-lg font-semibold text-red-600 dark:text-red-400">Danger Zone</h2>
                    </div>
                    <div class="px-6 py-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-medium text-gray-900 dark:text-white">Delete organization</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Once deleted, this organization and all its data cannot be recovered. This action cannot be undone.
                                </p>
                            </div>
                            <button
                                type="button"
                                @click="handleDelete"
                                class="ml-4 inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Cannot Delete Message -->
                <div
                    v-if="organization && !canDelete"
                    class="mt-8 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4"
                >
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                                This organization cannot be deleted
                            </h3>
                            <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                                <p v-if="organization.is_default">
                                    The default organization cannot be deleted.
                                </p>
                                <p v-else-if="(organization.aws_accounts_count || 0) > 0">
                                    This organization has {{ organization.aws_accounts_count }} AWS account(s). Please remove all AWS accounts before deleting this organization.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                </div>

                <!-- Team Tab -->
                <div v-if="activeTab === 'team' && organization && canListMembers">
                    <TeamManagement :org-id="orgId" />
                </div>

                <!-- No Access Message -->
                <div v-if="organization && !canManageSettings" class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                                Access Denied
                            </h3>
                            <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                                <p>You don't have permission to manage organization settings.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

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
                                        Are you sure you want to delete "{{ organization?.name }}"? This action cannot be undone.
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

