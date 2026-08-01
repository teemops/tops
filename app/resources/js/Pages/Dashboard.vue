<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import SidebarAppLayout from '@/Layouts/SidebarAppLayout.vue';
import { useAwsAccounts } from '@/composables/useAwsAccounts';
import { useScans, type Scan } from '@/composables/useScans';
import { useFindings } from '@/composables/useFindings';
import { useOrganizations } from '@/composables/useOrganizations';

const { accounts, fetchAccounts } = useAwsAccounts();
const { scans, fetchScans, startPolling, stopPolling } = useScans();
const { summary: findingsSummary, fetchFindings } = useFindings();
const { currentOrganization } = useOrganizations();

const loading = ref(true);
const loadError = ref<string | null>(null);

const totalAccounts = computed(() => accounts.value.length);

const activeScansCount = computed(
    () => scans.value.filter((s) => ['pending', 'running'].includes(s.status)).length
);

const criticalFindingsCount = computed(
    () => findingsSummary.value?.bySeverity?.critical ?? 0
);

// Was a "Security Score" — 100 minus a weighted penalty, floored at zero, which meant
// every real account read 0 and stayed there no matter what got fixed. Replaced with the
// open count, which is honest and actually moves. See D-12.
const openFindingsCount = computed(() => findingsSummary.value?.total ?? 0);

const recentScans = computed(() => scans.value.slice(0, 5));

const hasScanInsights = computed(() => {
    const hasCompletedScan = scans.value.some((s) => s.status === 'completed');
    const hasFindings = (findingsSummary.value?.total ?? 0) > 0;
    return hasCompletedScan || hasFindings || activeScansCount.value > 0;
});

const loadDashboardData = async () => {
    const orgId = currentOrganization.value?.org_id;
    if (!orgId) {
        loading.value = false;
        return;
    }

    loading.value = true;
    loadError.value = null;
    stopPolling();

    try {
        await Promise.all([
            fetchAccounts(orgId),
            fetchScans(orgId, {
                sortBy: 'started',
                sortOrder: 'desc',
                limit: 5,
                offset: 0,
            }),
            fetchFindings({ limit: 1, offset: 0 }),
        ]);

        scans.value.forEach((scan) => {
            if (['pending', 'running'].includes(scan.status)) {
                startPolling(scan.id);
            }
        });
    } catch (err: any) {
        loadError.value = err.message || 'Failed to load dashboard data';
    } finally {
        loading.value = false;
    }
};

onMounted(async () => {
    await loadDashboardData();
});

onUnmounted(() => {
    stopPolling();
});

watch(
    () => currentOrganization.value?.org_id,
    async () => {
        await loadDashboardData();
    }
);

const getStatusBadge = (status: string) => {
    switch (status) {
        case 'completed':
            return {
                class: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
                text: 'Completed',
                spinning: false,
            };
        case 'running':
            return {
                class: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
                text: 'Running',
                spinning: true,
            };
        case 'pending':
            return {
                class: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
                text: 'Pending',
                spinning: false,
            };
        case 'failed':
            return {
                class: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
                text: 'Failed',
                spinning: false,
            };
        case 'cancelled':
            return {
                class: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                text: 'Cancelled',
                spinning: false,
            };
        default:
            return {
                class: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                text: status,
                spinning: false,
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

const scanSubtitle = (scan: Scan) => {
    const timestamp = formatDate(scan.completedAt || scan.startedAt || scan.createdAt);
    if (scan.status === 'running' || scan.status === 'pending') {
        return `${scan.status === 'running' ? 'Running' : 'Pending'} • ${scan.awsAccountName}`;
    }
    const findingsLabel =
        scan.findingsCount === 1 ? '1 finding' : `${scan.findingsCount} findings`;
    return `${timestamp} • ${findingsLabel}`;
};

const handleViewScan = (scanId: string) => {
    router.visit(`/scans/${scanId}`);
};
</script>

<template>
    <SidebarAppLayout>
        <Head title="Dashboard" />

        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Dashboard</h1>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Overview of your cloud security status
            </p>
        </div>

        <div v-if="loadError" class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
            <p class="text-sm text-red-800 dark:text-red-300">{{ loadError }}</p>
        </div>

        <div v-if="loading" class="flex items-center justify-center py-16">
            <svg
                class="animate-spin h-8 w-8 text-blue-600 dark:text-blue-400"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
            >
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                />
            </svg>
        </div>

        <template v-else-if="!currentOrganization">
            <div
                class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-8 text-center"
            >
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">No organization selected</h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Create or select an organization to view your security dashboard.
                </p>
                <Link
                    :href="route('organizations.index')"
                    class="mt-4 inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400"
                >
                    Manage organizations
                </Link>
            </div>
        </template>

        <template v-else-if="!hasScanInsights">
            <div
                class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-8 text-center"
            >
                <div
                    class="mx-auto h-12 w-12 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center"
                >
                    <svg
                        class="h-6 w-6 text-gray-400 dark:text-gray-500"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                        />
                    </svg>
                </div>
                <h2 class="mt-4 text-lg font-semibold text-gray-900 dark:text-white">
                    No active scan insights yet
                </h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400 max-w-md mx-auto">
                    Connect an AWS account and run a security scan to see findings, compliance scores, and recent activity here.
                </p>
                <div class="mt-6 flex flex-wrap items-center justify-center gap-4">
                    <Link
                        :href="route('aws-accounts.index')"
                        class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
                    >
                        Add AWS account
                    </Link>
                    <Link
                        :href="route('scans.index')"
                        class="inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400"
                    >
                        View scans
                    </Link>
                </div>
            </div>
        </template>

        <template v-else>
            <!-- Stats Grid -->
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
                <div
                    class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-gray-700"
                >
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div
                                    class="h-10 w-10 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center"
                                >
                                    <svg
                                        class="h-6 w-6 text-blue-600 dark:text-blue-400"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"
                                        />
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                        Total Accounts
                                    </dt>
                                    <dd class="text-2xl font-semibold text-gray-900 dark:text-white">
                                        {{ totalAccounts }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-gray-700"
                >
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div
                                    class="h-10 w-10 rounded-full bg-yellow-100 dark:bg-yellow-900/30 flex items-center justify-center"
                                >
                                    <svg
                                        class="h-6 w-6 text-yellow-600 dark:text-yellow-400"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                                        />
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                        Active Scans
                                    </dt>
                                    <dd class="text-2xl font-semibold text-gray-900 dark:text-white">
                                        {{ activeScansCount }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-gray-700"
                >
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div
                                    class="h-10 w-10 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center"
                                >
                                    <svg
                                        class="h-6 w-6 text-red-600 dark:text-red-400"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                                        />
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                        Critical Findings
                                    </dt>
                                    <dd class="text-2xl font-semibold text-gray-900 dark:text-white">
                                        {{ criticalFindingsCount }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="bg-white dark:bg-gray-800 overflow-hidden shadow rounded-lg border border-gray-200 dark:border-gray-700"
                >
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div
                                    class="h-10 w-10 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center"
                                >
                                    <svg
                                        class="h-6 w-6 text-green-600 dark:text-green-400"
                                        fill="none"
                                        stroke="currentColor"
                                        viewBox="0 0 24 24"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                                        />
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                        Open Findings
                                    </dt>
                                    <dd class="text-2xl font-semibold text-gray-900 dark:text-white">
                                        {{ openFindingsCount }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Scans -->
            <div
                class="bg-white dark:bg-gray-800 shadow rounded-lg border border-gray-200 dark:border-gray-700"
            >
                <div
                    class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between"
                >
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Scans</h2>
                    <Link
                        :href="route('scans.index')"
                        class="text-sm font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400"
                    >
                        View all
                    </Link>
                </div>
                <div
                    v-if="recentScans.length === 0"
                    class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400"
                >
                    No scans yet. Start a scan from the Scans page.
                </div>
                <div v-else class="divide-y divide-gray-200 dark:divide-gray-700">
                    <button
                        v-for="scan in recentScans"
                        :key="scan.id"
                        type="button"
                        class="w-full px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors text-left"
                        @click="handleViewScan(scan.id)"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                    {{ scan.awsAccountName }} Scan
                                </p>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                    {{ scanSubtitle(scan) }}
                                </p>
                            </div>
                            <span
                                :class="[
                                    getStatusBadge(scan.status).class,
                                    'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium shrink-0 ml-4',
                                ]"
                            >
                                <svg
                                    v-if="getStatusBadge(scan.status).spinning"
                                    class="animate-spin -ml-1 mr-1.5 h-3 w-3"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle
                                        class="opacity-25"
                                        cx="12"
                                        cy="12"
                                        r="10"
                                        stroke="currentColor"
                                        stroke-width="4"
                                    />
                                    <path
                                        class="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                    />
                                </svg>
                                {{ getStatusBadge(scan.status).text }}
                            </span>
                        </div>
                    </button>
                </div>
            </div>
        </template>
    </SidebarAppLayout>
</template>
