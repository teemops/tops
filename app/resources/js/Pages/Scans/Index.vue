<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import SidebarAppLayout from '@/Layouts/SidebarAppLayout.vue';
import { useScans, type Scan } from '@/composables/useScans';
import { useAwsAccounts } from '@/composables/useAwsAccounts';
import { useOrganizations } from '@/composables/useOrganizations';
import { useNotifications } from '@/composables/useNotifications';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Dropdown from '@/Components/Dropdown.vue';
import NewScanModal from './NewScanModal.vue';

const { scans, loading, error, fetchScans, startPolling, stopPolling, cancelScan } = useScans();
const { accounts, fetchAccounts } = useAwsAccounts();
const { currentOrganization } = useOrganizations();
const { showSuccess, showError } = useNotifications();

const showNewScanModal = ref(false);
const selectedAwsAccountId = ref<string>('');
const selectedStatus = ref<string>('');

// Load scans and accounts
const loadData = async () => {
    if (currentOrganization.value?.org_id) {
        stopPolling(); // Stop any existing polling
        await fetchAccounts();
        await fetchScans(currentOrganization.value.org_id, {
            awsAccountId: selectedAwsAccountId.value || undefined,
            status: selectedStatus.value || undefined,
        });
        
        // Start polling for pending/running scans
        scans.value.forEach(scan => {
            if (['pending', 'running'].includes(scan.status)) {
                startPolling(scan.id);
            }
        });
    }
};

onMounted(async () => {
    await loadData();
});

onUnmounted(() => {
    stopPolling();
});

// Watch for organization changes and refetch
watch(() => currentOrganization.value?.org_id, async () => {
    await loadData();
});

// Watch for filter changes
watch([selectedAwsAccountId, selectedStatus], async () => {
    await loadData();
});

const handleScanCreated = async () => {
    await loadData();
};

const handleViewScan = (scanId: string) => {
    router.visit(`/scans/${scanId}`);
};

const handleCancelScan = async (scan: Scan) => {
    if (!confirm(`Are you sure you want to cancel this scan?`)) {
        return;
    }

    try {
        await cancelScan(scan.id);
        showSuccess('Scan cancelled successfully');
        await loadData();
    } catch (err: any) {
        showError(err.response?.data?.error || 'Failed to cancel scan');
    }
};

const getStatusBadge = (status: string) => {
    switch (status) {
        case 'completed':
            return {
                class: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
                text: 'Completed',
            };
        case 'running':
            return {
                class: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
                text: 'Running',
            };
        case 'pending':
            return {
                class: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
                text: 'Pending',
            };
        case 'failed':
            return {
                class: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
                text: 'Failed',
            };
        case 'cancelled':
            return {
                class: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                text: 'Cancelled',
            };
        default:
            return {
                class: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                text: status,
            };
    }
};

const formatDate = (dateString?: string) => {
    if (!dateString) return '—';
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

const clearFilters = () => {
    selectedAwsAccountId.value = '';
    selectedStatus.value = '';
};

const availableAccounts = computed(() => {
    return accounts.value.filter(acc => acc.status === 'completed');
});
</script>

<template>
    <SidebarAppLayout>
        <Head title="Scans" />

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                <!-- Page Header -->
                <div class="mb-8 flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Scans</h1>
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                            View and manage your security scans
                        </p>
                    </div>
                    <PrimaryButton @click="showNewScanModal = true">
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        New Scan
                    </PrimaryButton>
                </div>

                <!-- Filters -->
                <div class="mb-6 bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Account</label>
                            <select
                                v-model="selectedAwsAccountId"
                                class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-sm"
                            >
                                <option value="">All accounts</option>
                                <option
                                    v-for="account in availableAccounts"
                                    :key="account.id"
                                    :value="account.id"
                                >
                                    {{ account.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Status</label>
                            <select
                                v-model="selectedStatus"
                                class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-sm"
                            >
                                <option value="">All statuses</option>
                                <option value="pending">Pending</option>
                                <option value="running">Running</option>
                                <option value="completed">Completed</option>
                                <option value="failed">Failed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button
                                @click="clearFilters"
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600"
                            >
                                Clear filters
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Error Message -->
                <div v-if="!loading && error" class="mb-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                    <p class="text-sm text-red-800 dark:text-red-200">{{ error }}</p>
                </div>

                <!-- Loading State -->
                <div v-if="loading && scans.length === 0" class="text-center py-12">
                    <svg class="animate-spin h-8 w-8 text-gray-400 mx-auto" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Loading scans...</p>
                </div>

                <!-- Empty State -->
                <div v-else-if="!loading && scans.length === 0" class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No scans</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by running your first security scan.</p>
                    <div class="mt-6">
                        <PrimaryButton @click="showNewScanModal = true">
                            <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            New Scan
                        </PrimaryButton>
                    </div>
                </div>

                <!-- Scans Table -->
                <div v-else-if="scans.length > 0" class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Account</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Findings</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Started</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            <tr
                                v-for="scan in scans"
                                :key="scan.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/50"
                            >
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ scan.awsAccountName }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span
                                        :class="[
                                            'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium',
                                            getStatusBadge(scan.status).class
                                        ]"
                                    >
                                        <svg
                                            v-if="scan.status === 'running'"
                                            class="animate-spin -ml-1 mr-1.5 h-3 w-3"
                                            xmlns="http://www.w3.org/2000/svg"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                        >
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        {{ getStatusBadge(scan.status).text }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                    <span v-if="scan.status === 'completed'">{{ scan.findingsCount }}</span>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ formatDate(scan.startedAt || scan.createdAt) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex items-center space-x-3">
                                        <button
                                            @click="handleViewScan(scan.id)"
                                            class="text-blue-600 hover:text-blue-500 dark:text-blue-400"
                                        >
                                            View
                                        </button>
                                        <button
                                            v-if="['pending', 'running'].includes(scan.status)"
                                            @click="handleCancelScan(scan)"
                                            class="text-red-600 hover:text-red-500 dark:text-red-400"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- New Scan Modal -->
        <NewScanModal
            v-model="showNewScanModal"
            @created="handleScanCreated"
        />
    </SidebarAppLayout>
</template>
