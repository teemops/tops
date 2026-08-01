<script setup lang="ts">
/**
 * Scan detail summarises and dispatches. Findings is where a finding is read.
 *
 * The per-finding list that used to live here is gone: it duplicated the Findings page,
 * rendered remediation differently, and neither page was authoritative. Every number here
 * is a link into Findings, filtered — nothing is written twice.
 *
 * See docs/features/scan-detail-summarise-and-dispatch.md.
 */
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import SidebarAppLayout from '@/Layouts/SidebarAppLayout.vue';
import FindingsBreakdown, { type BreakdownTab } from '@/Components/FindingsBreakdown.vue';
import { useScans, type Scan, type Severity } from '@/composables/useScans';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps<{
    scanId: string;
}>();

const { getScan, startPolling, stopPolling } = useScans();

const scan = ref<Scan | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);

const loadScanData = async () => {
    loading.value = true;
    error.value = null;

    try {
        scan.value = await getScan(props.scanId);

        if (scan.value && ['pending', 'running'].includes(scan.value.status)) {
            startPolling(props.scanId);
        }
    } catch (err: any) {
        error.value = err.message || 'Failed to load scan details';
    } finally {
        loading.value = false;
    }
};

onMounted(loadScanData);
onUnmounted(() => stopPolling(props.scanId));

/** Whether there is current state to summarise under this scan at all. */
const showsBreakdown = computed(
    () => scan.value?.status === 'completed' && scan.value?.isLatestForAccount === true && !!scan.value?.breakdown
);

const isSuperseded = computed(
    () => scan.value?.status === 'completed' && scan.value?.isLatestForAccount === false
);

/** "CIS", "Basic + CIS", or "Custom" when a scan predates rulesets being stored. */
const scanGroupLabel = computed(() => {
    const labels = scan.value?.rulesetLabels ?? [];
    return labels.length ? labels.join(' + ') : 'Custom';
});

const scanGroupDetail = computed(() => {
    const count = scan.value?.scanTypes?.length ?? 0;
    return count === 1 ? '1 service' : `${count} services`;
});

const duration = computed(() => {
    if (!scan.value?.startedAt || !scan.value?.completedAt) return null;

    const seconds = Math.round(
        (new Date(scan.value.completedAt).getTime() - new Date(scan.value.startedAt).getTime()) / 1000
    );
    if (seconds < 0) return null;

    const minutes = Math.floor(seconds / 60);
    return minutes > 0 ? `${minutes}m ${seconds % 60}s` : `${seconds}s`;
});

const SEVERITIES: Severity[] = ['critical', 'high', 'medium', 'low'];

const SEVERITY_TEXT: Record<Severity, string> = {
    critical: 'text-red-600 dark:text-red-400',
    high: 'text-orange-600 dark:text-orange-400',
    medium: 'text-amber-600 dark:text-amber-400',
    low: 'text-blue-600 dark:text-blue-400',
};

const SEVERITY_LABEL: Record<Severity, string> = {
    critical: 'Critical',
    high: 'High',
    medium: 'Medium',
    low: 'Low',
};

const breakdownTabs = computed<BreakdownTab[]>(() => {
    const breakdown = scan.value?.breakdown;
    if (!breakdown) return [];

    return [
        { key: 'service', label: 'Service', groups: breakdown.byService, uppercase: true },
        { key: 'finding_type', label: 'Finding type', groups: breakdown.byFindingType },
    ];
});

/**
 * A drill-through is a plain filter on current state — no scan id travels with it, because
 * findings are not scoped to a scan any more.
 */
const openFindings = (tabKey: string, groupKey: string) => {
    router.visit(`/findings?${tabKey}=${encodeURIComponent(groupKey)}`);
};

const getStatusBadge = (status: string) => {
    switch (status) {
        case 'completed':
            return { class: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400', text: 'Completed' };
        case 'running':
            return { class: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400', text: 'Running' };
        case 'pending':
            return { class: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400', text: 'Pending' };
        case 'failed':
            return { class: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400', text: 'Failed' };
        default:
            return { class: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300', text: status };
    }
};

const formatDate = (dateString?: string) => {
    if (!dateString) return '—';
    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const handleRunNewScan = () => router.visit('/scans');

const viewLatestScan = () => {
    if (scan.value?.latestScanId) {
        router.visit(`/scans/${scan.value.latestScanId}`);
    }
};
</script>

<template>
    <SidebarAppLayout>
        <Head :title="scan ? `${scan.awsAccountName} Scan` : 'Scan Details'" />

        <div class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                <div v-if="loading" class="text-center py-12">
                    <svg class="animate-spin h-8 w-8 text-gray-400 mx-auto" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Loading scan details...</p>
                </div>

                <div v-else-if="error" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                    <p class="text-sm text-red-800 dark:text-red-200">{{ error }}</p>
                </div>

                <div v-else-if="scan">
                    <!-- Header -->
                    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ scan.awsAccountName }}</h1>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Scan {{ scan.id.slice(0, 8) }}
                            </p>
                        </div>
                        <PrimaryButton v-if="scan.status === 'completed'" @click="handleRunNewScan">
                            Run New Scan
                        </PrimaryButton>
                    </div>

                    <div v-if="scan.errorMessage" class="mb-6 rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 p-4">
                        <p class="text-sm text-red-800 dark:text-red-200"><strong>Error:</strong> {{ scan.errorMessage }}</p>
                    </div>

                    <!-- Run facts: what ran, when, how it went, where -->
                    <dl class="mb-6 grid grid-cols-1 gap-px overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-200 dark:bg-gray-700 sm:grid-cols-4">
                        <div class="bg-white dark:bg-gray-800 p-4">
                            <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Scan group</dt>
                            <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                                {{ scanGroupLabel }}
                                <span class="block text-xs font-normal text-gray-500 dark:text-gray-400">{{ scanGroupDetail }}</span>
                            </dd>
                        </div>
                        <div class="bg-white dark:bg-gray-800 p-4">
                            <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Ran</dt>
                            <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white">
                                {{ formatDate(scan.completedAt ?? scan.startedAt ?? scan.createdAt) }}
                                <span v-if="duration" class="block text-xs font-normal text-gray-500 dark:text-gray-400">{{ duration }}</span>
                            </dd>
                        </div>
                        <div class="bg-white dark:bg-gray-800 p-4">
                            <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</dt>
                            <dd class="mt-1">
                                <span :class="['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium', getStatusBadge(scan.status).class]">
                                    {{ getStatusBadge(scan.status).text }}
                                </span>
                                <span v-if="showsBreakdown" class="mt-1 block text-xs text-gray-500 dark:text-gray-400">latest for this account</span>
                            </dd>
                        </div>
                        <div class="bg-white dark:bg-gray-800 p-4">
                            <dt class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Account</dt>
                            <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ scan.awsAccountName }}</dd>
                        </div>
                    </dl>

                    <!-- A service never enumerated in a region is not a service with nothing
                         wrong, so the counts below are a floor rather than a total. -->
                    <div v-if="scan.isPartial" class="mb-6 rounded-lg border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 p-4">
                        <p class="text-sm text-amber-900 dark:text-amber-200">
                            <strong>Partial scan.</strong> Some regions did not report, so these counts are a floor, not a
                            total — a service that was never examined cannot be called clean.
                        </p>
                    </div>

                    <!-- An older scan is a thin audit record. Showing current-state numbers
                         under a historical date would be wrong and plausible at once. -->
                    <div v-if="isSuperseded" class="rounded-lg border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 p-4">
                        <p class="text-sm text-amber-900 dark:text-amber-200">
                            <strong>Superseded.</strong> This is not the latest scan for this account, so there is no
                            breakdown to show — findings reflect the current state, not this run.
                        </p>
                        <button
                            v-if="scan.latestScanId"
                            type="button"
                            class="mt-2 text-sm font-medium text-blue-700 dark:text-blue-400 hover:underline"
                            @click="viewLatestScan"
                        >
                            View the latest scan for {{ scan.awsAccountName }} &rarr;
                        </button>
                    </div>

                    <template v-if="showsBreakdown && scan.breakdown">
                        <p class="mb-3 text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Open now, after this scan
                        </p>

                        <!-- Severity totals -->
                        <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-5">
                            <div class="rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 p-4">
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white">{{ scan.breakdown.summary.total }}</p>
                                <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Open now</p>
                            </div>
                            <div
                                v-for="severity in SEVERITIES"
                                :key="severity"
                                class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4"
                            >
                                <p :class="['text-2xl font-semibold', SEVERITY_TEXT[severity]]">
                                    {{ scan.breakdown.summary.bySeverity[severity] }}
                                </p>
                                <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ SEVERITY_LABEL[severity] }}</p>
                            </div>
                        </div>

                        <!-- Fix these first -->
                        <div v-if="scan.breakdown.fixFirst.length" class="mb-6 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm">
                            <div class="mb-3 flex flex-wrap items-baseline gap-2">
                                <h2 class="text-sm font-medium text-gray-900 dark:text-white">Fix these first</h2>
                                <span class="text-xs text-gray-400 dark:text-gray-500">Ranked by what they clear, weighted by severity</span>
                            </div>
                            <ul class="space-y-2">
                                <li
                                    v-for="entry in scan.breakdown.fixFirst"
                                    :key="entry.title"
                                    class="flex items-start gap-3 rounded-md border border-gray-100 dark:border-gray-700 p-3"
                                >
                                    <span :class="['text-xl font-semibold tabular-nums', entry.topSeverity ? SEVERITY_TEXT[entry.topSeverity] : '']">
                                        {{ entry.clears }}
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block text-sm font-medium text-gray-900 dark:text-white">{{ entry.title }}</span>
                                        <span class="block text-xs text-gray-500 dark:text-gray-400">
                                            Clears {{ entry.clears }}
                                            <span v-if="entry.topSeverity">{{ SEVERITY_LABEL[entry.topSeverity] }}</span>
                                            · {{ entry.rules.join(', ') }}
                                        </span>
                                    </span>
                                </li>
                            </ul>
                        </div>

                        <!-- The shared breakdown. Insights renders the same component. -->
                        <FindingsBreakdown title="Break down by" :tabs="breakdownTabs" @select="openFindings" />
                    </template>

                    <!-- Not finished, so there is nothing to summarise yet. -->
                    <div
                        v-else-if="!isSuperseded && scan.status !== 'completed'"
                        class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-8 text-center"
                    >
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            This scan is {{ getStatusBadge(scan.status).text.toLowerCase() }}. The breakdown appears once it
                            completes.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </SidebarAppLayout>
</template>
