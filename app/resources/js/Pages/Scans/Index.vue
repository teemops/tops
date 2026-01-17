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

const { scans, loading, error, fetchScans, startPolling, stopPolling, cancelScan, scanTypes, fetchScanTypes, pagination } = useScans();
const { accounts, fetchAccounts } = useAwsAccounts();
const { currentOrganization } = useOrganizations();
const { showSuccess, showError } = useNotifications();

const showNewScanModal = ref(false);
const selectedAwsAccountId = ref<string>('');
const selectedStatus = ref<string>('');
const selectedScanType = ref<string>('');
const sortBy = ref<string>('started');
const sortOrder = ref<'asc' | 'desc'>('desc');
const currentPage = ref<number>(1);
const pageSize = ref<number>(20);

// Load scans and accounts
const loadData = async () => {
    if (currentOrganization.value?.org_id) {
        stopPolling(); // Stop any existing polling
        await fetchAccounts();
        const offset = (currentPage.value - 1) * pageSize.value;
        await fetchScans(currentOrganization.value.org_id, {
            awsAccountId: selectedAwsAccountId.value || undefined,
            status: selectedStatus.value || undefined,
            scanType: selectedScanType.value || undefined,
            sortBy: sortBy.value,
            sortOrder: sortOrder.value,
            limit: pageSize.value,
            offset: offset,
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
    await fetchScanTypes();
    await loadData();
});

onUnmounted(() => {
    stopPolling();
});

// Watch for organization changes and refetch
watch(() => currentOrganization.value?.org_id, async () => {
    await loadData();
});

// Watch for filter changes - reset to page 1 when filters change
watch([selectedAwsAccountId, selectedStatus, selectedScanType, sortBy, sortOrder], async () => {
    currentPage.value = 1;
    await loadData();
});

// Watch for page changes
watch(currentPage, async () => {
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
    selectedScanType.value = '';
    sortBy.value = 'started';
    sortOrder.value = 'desc';
    currentPage.value = 1;
};

const goToPage = (page: number) => {
    const totalPages = Math.ceil((pagination.value.total || 0) / pageSize.value);
    if (page >= 1 && page <= totalPages) {
        currentPage.value = page;
    }
};

const totalPages = computed(() => {
    return Math.ceil((pagination.value.total || 0) / pageSize.value);
});

const getPageNumbers = computed(() => {
    const pages: (number | string)[] = [];
    const total = totalPages.value;
    const current = currentPage.value;
    
    if (total <= 7) {
        // Show all pages if 7 or fewer
        for (let i = 1; i <= total; i++) {
            pages.push(i);
        }
    } else {
        // Show first page
        pages.push(1);
        
        if (current > 3) {
            pages.push('...');
        }
        
        // Show pages around current
        const start = Math.max(2, current - 1);
        const end = Math.min(total - 1, current + 1);
        
        for (let i = start; i <= end; i++) {
            pages.push(i);
        }
        
        if (current < total - 2) {
            pages.push('...');
        }
        
        // Show last page
        pages.push(total);
    }
    
    return pages;
});

const handleSort = (field: string) => {
    if (sortBy.value === field) {
        // Toggle sort order if clicking the same field
        sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc';
    } else {
        // Set new sort field with default descending order
        sortBy.value = field;
        sortOrder.value = 'desc';
    }
};

const formatScanTypes = (types: string[]) => {
    if (!types || types.length === 0) return '—';
    
    const typeLabels = types.map(type => {
        const scanType = scanTypes.value.find(st => st.value === type);
        return scanType ? scanType.label : type.toUpperCase();
    });
    
    return typeLabels.join(', ');
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
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
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
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Scan Type</label>
                            <select
                                v-model="selectedScanType"
                                class="block w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white text-sm"
                            >
                                <option value="">All types</option>
                                <option
                                    v-for="scanType in scanTypes"
                                    :key="scanType.value"
                                    :value="scanType.value"
                                >
                                    {{ scanType.label }}
                                </option>
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
                                <th 
                                    @click="handleSort('account')"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600"
                                >
                                    <div class="flex items-center space-x-1">
                                        <span>Account</span>
                                        <svg 
                                            v-if="sortBy === 'account'"
                                            class="h-4 w-4"
                                            fill="none" 
                                            stroke="currentColor" 
                                            viewBox="0 0 24 24"
                                        >
                                            <path 
                                                v-if="sortOrder === 'asc'"
                                                stroke-linecap="round" 
                                                stroke-linejoin="round" 
                                                stroke-width="2" 
                                                d="M5 15l7-7 7 7"
                                            />
                                            <path 
                                                v-else
                                                stroke-linecap="round" 
                                                stroke-linejoin="round" 
                                                stroke-width="2" 
                                                d="M19 9l-7 7-7-7"
                                            />
                                        </svg>
                                    </div>
                                </th>
                                <th 
                                    @click="handleSort('status')"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600"
                                >
                                    <div class="flex items-center space-x-1">
                                        <span>Status</span>
                                        <svg 
                                            v-if="sortBy === 'status'"
                                            class="h-4 w-4"
                                            fill="none" 
                                            stroke="currentColor" 
                                            viewBox="0 0 24 24"
                                        >
                                            <path 
                                                v-if="sortOrder === 'asc'"
                                                stroke-linecap="round" 
                                                stroke-linejoin="round" 
                                                stroke-width="2" 
                                                d="M5 15l7-7 7 7"
                                            />
                                            <path 
                                                v-else
                                                stroke-linecap="round" 
                                                stroke-linejoin="round" 
                                                stroke-width="2" 
                                                d="M19 9l-7 7-7-7"
                                            />
                                        </svg>
                                    </div>
                                </th>
                                <th 
                                    @click="handleSort('types')"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600"
                                >
                                    <div class="flex items-center space-x-1">
                                        <span>Scan Types</span>
                                        <svg 
                                            v-if="sortBy === 'types'"
                                            class="h-4 w-4"
                                            fill="none" 
                                            stroke="currentColor" 
                                            viewBox="0 0 24 24"
                                        >
                                            <path 
                                                v-if="sortOrder === 'asc'"
                                                stroke-linecap="round" 
                                                stroke-linejoin="round" 
                                                stroke-width="2" 
                                                d="M5 15l7-7 7 7"
                                            />
                                            <path 
                                                v-else
                                                stroke-linecap="round" 
                                                stroke-linejoin="round" 
                                                stroke-width="2" 
                                                d="M19 9l-7 7-7-7"
                                            />
                                        </svg>
                                    </div>
                                </th>
                                <th 
                                    @click="handleSort('findings')"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600"
                                >
                                    <div class="flex items-center space-x-1">
                                        <span>Findings</span>
                                        <svg 
                                            v-if="sortBy === 'findings'"
                                            class="h-4 w-4"
                                            fill="none" 
                                            stroke="currentColor" 
                                            viewBox="0 0 24 24"
                                        >
                                            <path 
                                                v-if="sortOrder === 'asc'"
                                                stroke-linecap="round" 
                                                stroke-linejoin="round" 
                                                stroke-width="2" 
                                                d="M5 15l7-7 7 7"
                                            />
                                            <path 
                                                v-else
                                                stroke-linecap="round" 
                                                stroke-linejoin="round" 
                                                stroke-width="2" 
                                                d="M19 9l-7 7-7-7"
                                            />
                                        </svg>
                                    </div>
                                </th>
                                <th 
                                    @click="handleSort('started')"
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600"
                                >
                                    <div class="flex items-center space-x-1">
                                        <span>Started</span>
                                        <svg 
                                            v-if="sortBy === 'started'"
                                            class="h-4 w-4"
                                            fill="none" 
                                            stroke="currentColor" 
                                            viewBox="0 0 24 24"
                                        >
                                            <path 
                                                v-if="sortOrder === 'asc'"
                                                stroke-linecap="round" 
                                                stroke-linejoin="round" 
                                                stroke-width="2" 
                                                d="M5 15l7-7 7 7"
                                            />
                                            <path 
                                                v-else
                                                stroke-linecap="round" 
                                                stroke-linejoin="round" 
                                                stroke-width="2" 
                                                d="M19 9l-7 7-7-7"
                                            />
                                        </svg>
                                    </div>
                                </th>
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
                                <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">
                                    {{ formatScanTypes(scan.scanTypes || []) }}
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
                    
                    <!-- Pagination -->
                    <div v-if="pagination.total > 0" class="bg-white dark:bg-gray-800 px-4 py-3 flex items-center justify-between border-t border-gray-200 dark:border-gray-700 sm:px-6">
                        <div class="flex-1 flex justify-between sm:hidden">
                            <button
                                @click="goToPage(currentPage - 1)"
                                :disabled="currentPage === 1"
                                class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Previous
                            </button>
                            <button
                                @click="goToPage(currentPage + 1)"
                                :disabled="currentPage >= totalPages"
                                class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Next
                            </button>
                        </div>
                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                            <div>
                                <p class="text-sm text-gray-700 dark:text-gray-300">
                                    Showing
                                    <span class="font-medium">{{ (currentPage - 1) * pageSize + 1 }}</span>
                                    to
                                    <span class="font-medium">{{ Math.min(currentPage * pageSize, pagination.total) }}</span>
                                    of
                                    <span class="font-medium">{{ pagination.total }}</span>
                                    results
                                </p>
                            </div>
                            <div>
                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                    <button
                                        @click="goToPage(currentPage - 1)"
                                        :disabled="currentPage === 1"
                                        class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        <span class="sr-only">Previous</span>
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                    <button
                                        v-for="(page, index) in getPageNumbers"
                                        :key="index"
                                        @click="typeof page === 'number' ? goToPage(page) : null"
                                        :disabled="typeof page !== 'number'"
                                        :class="[
                                            'relative inline-flex items-center px-4 py-2 border text-sm font-medium',
                                            typeof page === 'number'
                                                ? page === currentPage
                                                    ? 'z-10 bg-blue-50 dark:bg-blue-900/30 border-blue-500 dark:border-blue-500 text-blue-600 dark:text-blue-400'
                                                    : 'bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 cursor-pointer'
                                                : 'bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400 cursor-default'
                                        ]"
                                    >
                                        {{ page }}
                                    </button>
                                    <button
                                        @click="goToPage(currentPage + 1)"
                                        :disabled="currentPage >= totalPages"
                                        class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        <span class="sr-only">Next</span>
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </nav>
                            </div>
                        </div>
                    </div>
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
