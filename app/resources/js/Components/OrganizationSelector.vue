<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import { useOrganizations, type Organization } from '@/composables/useOrganizations';
import { useOrganizationPermissions } from '@/composables/useOrganizationPermissions';
import { useNotifications } from '@/composables/useNotifications';
import CreateOrganizationModal from '@/Pages/Organizations/CreateOrganizationModal.vue';

const { organizations, currentOrganization, loading, fetchOrganizations, switchOrganization } = useOrganizations();
const { canManageSettings } = useOrganizationPermissions();
const { showSuccess, showError } = useNotifications();
const page = usePage();

const showCreateModal = ref(false);

// Listen for organization-not-found events
const handleOrganizationNotFound = async () => {
    // Refresh organizations list
    await fetchOrganizations();
    
    // If no organizations exist, show create modal
    if (organizations.value.length === 0) {
        showCreateModal.value = true;
    }
};

onMounted(async () => {
    // Fetch organizations if not already loaded
    try {
        if (organizations.value.length === 0) {
            await fetchOrganizations();
        }
    } catch (error) {
        console.error('Failed to fetch organizations in OrganizationSelector:', error);
        // Don't block rendering if fetch fails
    }
    
    // Listen for organization-not-found events
    window.addEventListener('organization-not-found', handleOrganizationNotFound);
});

onUnmounted(() => {
    window.removeEventListener('organization-not-found', handleOrganizationNotFound);
});

// Watch for when organizations become empty and prompt to create
watch(() => organizations.value.length, (newLength, oldLength) => {
    // Only prompt if we've finished loading and have no organizations
    if (newLength === 0 && !loading.value) {
        // If organizations were deleted (went from some to none), show modal
        // For initial load with no organizations, the empty state in dropdown handles the prompt
        if (oldLength !== undefined && oldLength > 0) {
            setTimeout(() => {
                showCreateModal.value = true;
            }, 500);
        }
    }
}, { immediate: false });

const handleSwitch = async (org: Organization) => {
    try {
        await switchOrganization(org.org_id);
        showSuccess(`Switched to ${org.name}`);
    } catch (err: any) {
        showError(err.message || 'Failed to switch organization');
    }
};

const handleCreate = () => {
    showCreateModal.value = true;
};

const onOrganizationCreated = async () => {
    await fetchOrganizations();
    showCreateModal.value = false;
};
</script>

<template>
    <div class="relative">
        <Dropdown align="left" width="48">
            <template #trigger>
                <button
                    type="button"
                    class="inline-flex items-center w-48 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                >
                    <span class="flex-1 text-left truncate">
                        {{ currentOrganization?.name || 'Select Organization' }}
                    </span>
                    <svg
                        class="ml-2 -mr-1 h-5 w-5 text-gray-400"
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
                <div class="py-1 bg-white dark:bg-gray-700">
                    <!-- Loading State -->
                    <div v-if="loading && organizations.length === 0" class="px-4 py-2 text-sm text-gray-500 dark:text-gray-400">
                        Loading organizations...
                    </div>

                    <!-- Empty State - Prompt to Create -->
                    <div v-else-if="!loading && organizations.length === 0" class="px-4 py-3">
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                            No organizations found
                        </p>
                        <button
                            @click.stop="handleCreate"
                            class="w-full px-3 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-md transition-colors"
                        >
                            Create Your First Organization
                        </button>
                    </div>

                    <!-- Organizations List -->
                    <template v-else>
                        <div
                            v-for="org in organizations"
                            :key="org.id"
                            :class="[
                                'px-4 py-2 text-sm transition-colors',
                                currentOrganization?.org_id === org.org_id
                                    ? 'bg-blue-50 dark:bg-blue-900/30'
                                    : 'hover:bg-gray-100 dark:hover:bg-gray-600'
                            ]"
                        >
                            <div class="flex items-center justify-between">
                                <div
                                    @click.stop="handleSwitch(org)"
                                    :class="[
                                        'flex-1 cursor-pointer',
                                        currentOrganization?.org_id === org.org_id
                                            ? 'text-blue-600 dark:text-blue-400 font-medium'
                                            : 'text-gray-700 dark:text-gray-300'
                                    ]"
                                >
                                    <div class="flex items-center">
                                        <span class="truncate">{{ org.name }}</span>
                                        <span
                                            v-if="org.is_default"
                                            class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400"
                                        >
                                            Default
                                        </span>
                                    </div>
                                </div>
                                <!-- Settings Icon (only for administrators/owner) -->
                                <Link
                                    v-if="canManageSettings"
                                    :href="route('organizations.settings', { orgId: org.org_id })"
                                    @click.stop
                                    class="ml-2 p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded"
                                    title="Organization Settings"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </Link>
                            </div>
                        </div>

                        <!-- Divider -->
                        <div v-if="organizations.length > 0" class="border-t border-gray-200 dark:border-gray-600 my-1"></div>

                        <!-- Add Organization -->
                        <button
                            @click.stop="handleCreate"
                            class="w-full px-4 py-2 text-sm text-left text-blue-600 dark:text-blue-400 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors"
                        >
                            <div class="flex items-center">
                                <svg
                                    class="mr-2 h-4 w-4"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M12 4v16m8-8H4"
                                    />
                                </svg>
                                Add Organization
                            </div>
                        </button>
                    </template>
                </div>
            </template>
        </Dropdown>

        <!-- Create Organization Modal -->
        <CreateOrganizationModal
            v-model="showCreateModal"
            @created="onOrganizationCreated"
        />
    </div>
</template>
