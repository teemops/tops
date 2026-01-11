<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import SidebarAppLayout from '@/Layouts/SidebarAppLayout.vue';
import { useScans, type ScanResult } from '@/composables/useScans';
import { useOrganizations } from '@/composables/useOrganizations';
import { useNotifications } from '@/composables/useNotifications';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps<{
    scanId: string;
}>();

const { getScan, getScanResults, startPolling, stopPolling } = useScans();
const { currentOrganization } = useOrganizations();
const { showError } = useNotifications();

const scan = ref<any>(null);
const findings = ref<ScanResult[]>([]);
const summary = ref({
    total: 0,
    critical: 0,
    high: 0,
    medium: 0,
    low: 0,
});
const loading = ref(true);
const error = ref<string | null>(null);
const selectedSeverity = ref<string>('');

const loadScanData = async () => {
    loading.value = true;
    error.value = null;

    try {
        // Load scan details
        scan.value = await getScan(props.scanId);
        
        // Load scan results
        const results = await getScanResults(props.scanId, {
            severity: selectedSeverity.value || undefined,
        });
        findings.value = results.findings;
        summary.value = results.summary;

        // Start polling if scan is pending or running
        if (scan.value && ['pending', 'running'].includes(scan.value.status)) {
            startPolling(props.scanId);
        }
    } catch (err: any) {
        error.value = err.message || 'Failed to load scan details';
        console.error('Error loading scan:', err);
    } finally {
        loading.value = false;
    }
};

onMounted(async () => {
    await loadScanData();
});

onUnmounted(() => {
    stopPolling(props.scanId);
});

// Watch for severity filter changes
const watchSeverity = async () => {
    if (scan.value) {
        try {
            loading.value = true;
            const results = await getScanResults(props.scanId, {
                severity: selectedSeverity.value || undefined,
            });
            findings.value = results.findings;
        } catch (err: any) {
            showError('Failed to filter findings');
        } finally {
            loading.value = false;
        }
    }
};

const getSeverityBadge = (severity: string) => {
    switch (severity) {
        case 'critical':
            return {
                class: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
                text: 'Critical',
            };
        case 'high':
            return {
                class: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
                text: 'High',
            };
        case 'medium':
            return {
                class: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
                text: 'Medium',
            };
        case 'low':
            return {
                class: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
                text: 'Low',
            };
        default:
            return {
                class: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                text: severity,
            };
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
    return date.toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric', 
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

const handleRunNewScan = () => {
    router.visit('/scans');
};
</script>

<template>
    <SidebarAppLayout>
        <Head :title="scan ? `${scan.awsAccountName} Scan` : 'Scan Details'" />

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                <!-- Loading State -->
                <div v-if="loading" class="text-center py-12">
                    <svg class="animate-spin h-8 w-8 text-gray-400 mx-auto" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Loading scan details...</p>
                </div>

                <!-- Error State -->
                <div v-else-if="error" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                    <p class="text-sm text-red-800 dark:text-red-200">{{ error }}</p>
                </div>

                <!-- Scan Details -->
                <div v-else-if="scan">
                    <!-- Header -->
                    <div class="mb-8">
                        <div class="flex items-center justify-between">
                            <div>
                                <h1 class="text-3xl font-bold text-gray-900 dark:text-white">{{ scan.awsAccountName }} Scan</h1>
                                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                    <span v-if="scan.completedAt">Completed {{ formatDate(scan.completedAt) }}</span>
                                    <span v-else-if="scan.startedAt">Started {{ formatDate(scan.startedAt) }}</span>
                                    <span v-else>Created {{ formatDate(scan.createdAt) }}</span>
                                </p>
                            </div>
                            <div class="flex items-center space-x-3">
                                <PrimaryButton
                                    v-if="scan.status === 'completed'"
                                    @click="handleRunNewScan"
                                >
                                    Run New Scan
                                </PrimaryButton>
                            </div>
                        </div>
                        <div class="mt-4">
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
                        </div>
                        <div v-if="scan.errorMessage" class="mt-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                            <p class="text-sm text-red-800 dark:text-red-200">
                                <strong>Error:</strong> {{ scan.errorMessage }}
                            </p>
                        </div>
                    </div>

                    <!-- Summary Cards -->
                    <div v-if="scan.status === 'completed'" class="grid grid-cols-1 gap-5 sm:grid-cols-4 mb-8">
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-gray-700">
                            <div class="p-5">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div class="h-10 w-10 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                                            <span class="text-red-600 dark:text-red-400 text-lg font-bold">{{ summary.critical }}</span>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Critical</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-gray-700">
                            <div class="p-5">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div class="h-10 w-10 rounded-full bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center">
                                            <span class="text-orange-600 dark:text-orange-400 text-lg font-bold">{{ summary.high }}</span>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">High</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-gray-700">
                            <div class="p-5">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div class="h-10 w-10 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                                            <span class="text-amber-600 dark:text-amber-400 text-lg font-bold">{{ summary.medium }}</span>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Medium</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-gray-700">
                            <div class="p-5">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div class="h-10 w-10 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                            <span class="text-blue-600 dark:text-blue-400 text-lg font-bold">{{ summary.low }}</span>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Low</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Findings -->
                    <div v-if="scan.status === 'completed'" class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Findings</h2>
                            <div class="flex items-center space-x-2">
                                <select
                                    v-model="selectedSeverity"
                                    @change="watchSeverity"
                                    class="text-sm border-gray-300 dark:border-gray-600 rounded-md dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500"
                                >
                                    <option value="">All severities</option>
                                    <option value="critical">Critical</option>
                                    <option value="high">High</option>
                                    <option value="medium">Medium</option>
                                    <option value="low">Low</option>
                                </select>
                            </div>
                        </div>
                        <div v-if="findings.length === 0" class="px-6 py-12 text-center">
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ selectedSeverity ? `No ${selectedSeverity} findings` : 'No findings' }}
                            </p>
                        </div>
                        <div v-else class="divide-y divide-gray-200 dark:divide-gray-700">
                            <div
                                v-for="finding in findings"
                                :key="finding.id"
                                class="px-6 py-4"
                            >
                                <div class="flex items-start justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center">
                                            <span
                                                :class="[
                                                    'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium mr-3',
                                                    getSeverityBadge(finding.severity).class
                                                ]"
                                            >
                                                {{ getSeverityBadge(finding.severity).text }}
                                            </span>
                                            <h3 class="text-sm font-medium text-gray-900 dark:text-white">{{ finding.title }}</h3>
                                        </div>
                                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                            {{ finding.description }}
                                        </p>
                                        <div class="mt-3 flex items-center space-x-4 text-xs text-gray-500 dark:text-gray-400">
                                            <span>Resource: {{ finding.resourceId }}</span>
                                            <span>Service: {{ finding.service }}</span>
                                            <span>Type: {{ finding.resourceType }}</span>
                                        </div>
                                        <div v-if="finding.remediation" class="mt-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3">
                                            <p class="text-xs font-medium text-blue-900 dark:text-blue-200 mb-1">Remediation:</p>
                                            <p class="text-xs text-blue-800 dark:text-blue-300">{{ finding.remediation }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Running/Pending State -->
                    <div v-else class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700 p-12 text-center">
                        <svg
                            v-if="scan.status === 'running'"
                            class="animate-spin h-12 w-12 text-blue-600 dark:text-blue-400 mx-auto"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                            {{ scan.status === 'running' ? 'Scan is in progress...' : 'Scan is pending...' }}
                        </p>
                        <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">
                            Results will appear here when the scan completes.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </SidebarAppLayout>
</template>
