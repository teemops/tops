<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import SidebarAppLayout from '@/Layouts/SidebarAppLayout.vue';
import { useFindings, type Finding, type Recommendation } from '@/composables/useFindings';
import { useAwsAccounts } from '@/composables/useAwsAccounts';
import { useOrganizations } from '@/composables/useOrganizations';
import { useNotifications } from '@/composables/useNotifications';

const { findings, summary, serviceFacets, recommendationsMap, loading, error, fetchFindings, updateFindingStatus } = useFindings();
const { accounts, fetchAccounts } = useAwsAccounts();
const { currentOrganization } = useOrganizations();
const { showSuccess, showError } = useNotifications();

const VALID_STATUSES = ['open', 'resolved', 'ignored'];

/**
 * Filters come from the URL so a drill-through or a shared link lands filtered.
 *
 * Read at setup rather than in onMounted: assigning to the refs afterwards would trip the
 * filter watcher below and fetch the same list twice.
 */
const readFiltersFromUrl = () => {
    const params = new URLSearchParams(window.location.search);
    const status = params.get('status') ?? '';

    return {
        awsAccountId: params.get('aws_account_id') ?? '',
        findingType: params.get('finding_type') ?? '',
        service: params.get('service') ?? '',
        // The only parameter with a known valid set, so the only one we can reject up
        // front. An unrecognised service or type simply matches nothing, which the empty
        // state already handles.
        status: VALID_STATUSES.includes(status) ? status : '',
    };
};

const initial = readFiltersFromUrl();

const selectedAwsAccountId = ref<string>(initial.awsAccountId);
const selectedFindingType = ref<string>(initial.findingType);
const selectedService = ref<string>(initial.service);
const selectedStatus = ref<string>(initial.status);
const expandedId = ref<string | null>(null);

/** How many service pills to show before the tail collapses. */
const SERVICE_PILL_LIMIT = 6;
const showAllServices = ref(false);

const visibleServiceFacets = computed(() =>
    showAllServices.value ? serviceFacets.value : serviceFacets.value.slice(0, SERVICE_PILL_LIMIT)
);

const hiddenServiceCount = computed(() =>
    Math.max(serviceFacets.value.length - SERVICE_PILL_LIMIT, 0)
);

/** The "All" pill — every service's findings under the other active filters. */
const allServicesCount = computed(() =>
    serviceFacets.value.reduce((sum, facet) => sum + facet.count, 0)
);

/** Clicking the active pill clears the filter rather than reapplying it. */
const selectService = (service: string) => {
    selectedService.value = selectedService.value === service ? '' : service;
};

/**
 * Mirror the filters into the address bar so the view can be bookmarked or sent to a
 * colleague. replaceState rather than pushState — the back button should leave Findings,
 * not step back through every pill the user tried.
 */
const syncUrl = () => {
    const params = new URLSearchParams();
    if (selectedAwsAccountId.value) params.set('aws_account_id', selectedAwsAccountId.value);
    if (selectedFindingType.value) params.set('finding_type', selectedFindingType.value);
    if (selectedService.value) params.set('service', selectedService.value);
    if (selectedStatus.value) params.set('status', selectedStatus.value);

    const query = params.toString();
    window.history.replaceState({}, '', query ? `${window.location.pathname}?${query}` : window.location.pathname);
};

// Options for the Type filter dropdown. Selecting a type filters the findings
// list server-side, so it must NOT be derived from the (now-filtered) findings —
// otherwise the dropdown collapses to only the selected type and you can't switch
// back. Accumulate the union of types seen so the list only grows; reset on org
// change (below).
const allFindingTypes = ref<string[]>([]);

const loadData = async () => {
    if (currentOrganization.value?.org_id) {
        await fetchAccounts();
        await fetchFindings({
            awsAccountId: selectedAwsAccountId.value || undefined,
            findingType: selectedFindingType.value || undefined,
            service: selectedService.value || undefined,
            status: selectedStatus.value || undefined,
            limit: 100,
            offset: 0,
        });
    }
};

onMounted(() => loadData());

watch(() => currentOrganization.value?.org_id, () => {
    // Different org has a different set of finding types, and its own accounts and
    // services — carrying a filter across would point at rows that are not there.
    allFindingTypes.value = [];
    selectedAwsAccountId.value = '';
    selectedFindingType.value = '';
    selectedService.value = '';
    selectedStatus.value = '';
    showAllServices.value = false;
});

// One loader for both the org and the filters. Watching the org here as well as above is
// what keeps a switch to a fresh org reloading even when no filter was set — and keeps it
// to a single fetch, since clearing the filters above would otherwise trigger a second.
watch(
    [
        () => currentOrganization.value?.org_id,
        selectedAwsAccountId,
        selectedFindingType,
        selectedService,
        selectedStatus,
    ],
    () => {
        syncUrl();
        loadData();
    }
);

// Keep the Type dropdown's options as the union of all types seen, so it stays
// stable while a type filter is active.
watch(findings, (list) => {
    if (!list.length) return;
    const set = new Set(allFindingTypes.value);
    list.forEach((f) => set.add(f.findingType));
    allFindingTypes.value = Array.from(set).sort();
}, { immediate: true });

const getRecommendation = (finding: Finding): Recommendation | null => {
    return recommendationsMap.value[finding.findingType] ?? null;
};

const getSeverityBadge = (severity: string) => {
    switch (severity) {
        case 'critical':
            return 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
        case 'high':
            return 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400';
        case 'medium':
            return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400';
        case 'low':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
    }
};

const getStatusBadge = (status: string) => {
    switch (status) {
        case 'open':
            return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400';
        case 'resolved':
            return 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
        case 'ignored':
            return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';
    }
};

const toggleExpand = (id: string) => {
    expandedId.value = expandedId.value === id ? null : id;
};

const handleUpdateStatus = async (finding: Finding, status: 'open' | 'resolved' | 'ignored') => {
    try {
        await updateFindingStatus(finding.id, status);
        showSuccess('Status updated');
        await loadData();
    } catch (e: any) {
        showError(e.message || 'Failed to update status');
    }
};

const goToFindingType = (findingType: string) => {
    router.visit(route('findings.show', { findingType }));
};

</script>

<template>
    <Head title="Findings" />
    <SidebarAppLayout>
        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mb-6">
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Findings</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    View and analyse findings to remediate issues.
                </p>
            </div>

            <div v-if="error" class="mb-4 rounded-md bg-red-50 dark:bg-red-900/20 p-4 text-sm text-red-700 dark:text-red-400">
                {{ error }}
            </div>

            <!-- Executive Summary -->
            <section v-if="summary" class="mb-8">
                <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Executive Summary</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Security Score</dt>
                        <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ summary.securityScore }}</dd>
                    </div>
                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Findings</dt>
                        <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ summary.total }}</dd>
                    </div>
                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Critical</dt>
                        <dd class="mt-1 text-2xl font-semibold text-red-600 dark:text-red-400">{{ summary.bySeverity.critical }}</dd>
                    </div>
                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">High</dt>
                        <dd class="mt-1 text-2xl font-semibold text-orange-600 dark:text-orange-400">{{ summary.bySeverity.high }}</dd>
                    </div>
                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm">
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Medium / Low</dt>
                        <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ summary.bySeverity.medium + summary.bySeverity.low }}</dd>
                    </div>
                </div>
            </section>

            <!-- Filters -->
            <section class="mb-6 flex flex-wrap items-center gap-4">
                <div>
                    <label for="aws-account" class="sr-only">AWS Account</label>
                    <select
                        id="aws-account"
                        v-model="selectedAwsAccountId"
                        class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                    >
                        <option value="">All accounts</option>
                        <option v-for="acc in accounts" :key="acc.id" :value="acc.id">{{ acc.name }}</option>
                    </select>
                </div>
                <div>
                    <label for="finding-type" class="sr-only">Type</label>
                    <select
                        id="finding-type"
                        v-model="selectedFindingType"
                        class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                    >
                        <option value="">All types</option>
                        <option v-for="ft in allFindingTypes" :key="ft" :value="ft">{{ ft }}</option>
                    </select>
                </div>
                <div>
                    <label for="status" class="sr-only">Status</label>
                    <select
                        id="status"
                        v-model="selectedStatus"
                        class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm"
                    >
                        <option value="">All statuses</option>
                        <option value="open">Open</option>
                        <option value="resolved">Resolved</option>
                        <option value="ignored">Ignored</option>
                    </select>
                </div>
            </section>

            <!-- Service pills. Counts are what you would get by clicking, given the other
                 filters — so they stay meaningful while a service is already selected. -->
            <section v-if="serviceFacets.length" class="mb-6" aria-label="Filter by service">
                <h2 class="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    Service
                </h2>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        :aria-pressed="selectedService === ''"
                        :class="[
                            'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-sm transition-colors',
                            selectedService === ''
                                ? 'border-gray-900 bg-gray-900 text-white dark:border-white dark:bg-white dark:text-gray-900'
                                : 'border-gray-300 bg-white text-gray-600 hover:border-gray-400 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-gray-500',
                        ]"
                        @click="selectedService = ''"
                    >
                        All
                        <span class="text-xs tabular-nums opacity-70">{{ allServicesCount }}</span>
                    </button>

                    <button
                        v-for="facet in visibleServiceFacets"
                        :key="facet.service"
                        type="button"
                        :aria-pressed="selectedService === facet.service"
                        :class="[
                            'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-sm uppercase transition-colors',
                            selectedService === facet.service
                                ? 'border-gray-900 bg-gray-900 text-white dark:border-white dark:bg-white dark:text-gray-900'
                                : 'border-gray-300 bg-white text-gray-600 hover:border-gray-400 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-gray-500',
                        ]"
                        @click="selectService(facet.service)"
                    >
                        {{ facet.service }}
                        <span class="text-xs tabular-nums opacity-70">{{ facet.count }}</span>
                    </button>

                    <button
                        v-if="hiddenServiceCount > 0"
                        type="button"
                        class="rounded-full border border-dashed border-gray-300 px-3 py-1 text-sm text-gray-500 hover:border-gray-400 hover:text-gray-700 dark:border-gray-600 dark:text-gray-400 dark:hover:border-gray-500 dark:hover:text-gray-200"
                        @click="showAllServices = !showAllServices"
                    >
                        {{ showAllServices ? 'Show fewer' : `+ ${hiddenServiceCount} more` }}
                    </button>
                </div>
            </section>

            <!-- Detailed Findings -->
            <section>
                <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Detailed Findings</h2>
                <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow overflow-hidden">
                    <div v-if="loading" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                        Loading findings...
                    </div>
                    <div v-else-if="findings.length === 0" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                        No findings match your filters.
                    </div>
                    <ul v-else class="divide-y divide-gray-200 dark:divide-gray-700">
                        <li v-for="finding in findings" :key="finding.id" class="px-6 py-4">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', getSeverityBadge(finding.severity)]">
                                            {{ finding.severity }}
                                        </span>
                                        <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', getStatusBadge(finding.status)]">
                                            {{ finding.status }}
                                        </span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ finding.service }} · {{ finding.findingType }}</span>
                                    </div>
                                    <p class="mt-1 font-medium text-gray-900 dark:text-white">{{ finding.title }}</p>
                                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ finding.awsAccountName }} · {{ finding.resourceId }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="text-sm text-blue-600 dark:text-blue-400 hover:underline"
                                        @click="goToFindingType(finding.findingType)"
                                    >
                                        View all {{ finding.findingType }}
                                    </button>
                                    <template v-if="finding.status === 'open'">
                                        <button
                                            type="button"
                                            class="text-sm text-green-600 dark:text-green-400 hover:underline"
                                            @click="handleUpdateStatus(finding, 'resolved')"
                                        >
                                            Mark resolved
                                        </button>
                                        <button
                                            type="button"
                                            class="text-sm text-gray-600 dark:text-gray-400 hover:underline"
                                            @click="handleUpdateStatus(finding, 'ignored')"
                                        >
                                            Ignore
                                        </button>
                                    </template>
                                    <template v-else>
                                        <button
                                            type="button"
                                            class="text-sm text-gray-600 dark:text-gray-400 hover:underline"
                                            @click="handleUpdateStatus(finding, 'open')"
                                        >
                                            Reopen
                                        </button>
                                    </template>
                                    <button
                                        type="button"
                                        class="text-sm text-gray-600 dark:text-gray-400 hover:underline"
                                        @click="toggleExpand(finding.id)"
                                    >
                                        {{ expandedId === finding.id ? 'Hide' : 'Remediation' }}
                                    </button>
                                </div>
                            </div>
                            <!-- Expanded remediation -->
                            <div v-if="expandedId === finding.id" class="mt-4 rounded-md bg-gray-50 dark:bg-gray-900/50 p-4 text-sm">
                                <template v-if="getRecommendation(finding)">
                                    <p class="font-medium text-gray-900 dark:text-white">{{ getRecommendation(finding)!.recommendation }}</p>
                                    <p class="mt-1 text-gray-600 dark:text-gray-300">{{ getRecommendation(finding)!.description }}</p>
                                    <p class="mt-2 text-gray-500 dark:text-gray-400"><strong>Impact:</strong> {{ getRecommendation(finding)!.impact }}</p>
                                    <ul class="mt-2 list-disc pl-5 space-y-1 text-gray-600 dark:text-gray-300">
                                        <li v-for="(step, i) in getRecommendation(finding)!.steps" :key="i">{{ step }}</li>
                                    </ul>
                                    <div v-if="getRecommendation(finding)!.links?.length" class="mt-2">
                                        <strong class="text-gray-700 dark:text-gray-300">Links:</strong>
                                        <ul class="mt-1 space-y-1">
                                            <li v-for="(link, i) in getRecommendation(finding)!.links" :key="i">
                                                <a :href="link" target="_blank" rel="noopener" class="text-blue-600 dark:text-blue-400 hover:underline">{{ link }}</a>
                                            </li>
                                        </ul>
                                    </div>
                                </template>
                                <p v-else class="text-gray-500 dark:text-gray-400">No remediation steps for this finding type.</p>
                            </div>
                        </li>
                    </ul>
                </div>
            </section>
        </div>
    </SidebarAppLayout>
</template>
