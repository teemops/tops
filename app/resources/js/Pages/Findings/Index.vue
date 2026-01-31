<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import SidebarAppLayout from '@/Layouts/SidebarAppLayout.vue';
import { useFindings, type Finding, type Recommendation } from '@/composables/useFindings';
import { useAwsAccounts } from '@/composables/useAwsAccounts';
import { useOrganizations } from '@/composables/useOrganizations';
import { useNotifications } from '@/composables/useNotifications';

const { findings, summary, recommendationsMap, loading, error, fetchFindings, updateFindingStatus } = useFindings();
const { accounts, fetchAccounts } = useAwsAccounts();
const { currentOrganization } = useOrganizations();
const { showSuccess, showError } = useNotifications();

const selectedAwsAccountId = ref<string>('');
const selectedFindingType = ref<string>('');
const selectedStatus = ref<string>('');
const expandedId = ref<string | null>(null);

const loadData = async () => {
    if (currentOrganization.value?.org_id) {
        await fetchAccounts();
        await fetchFindings({
            awsAccountId: selectedAwsAccountId.value || undefined,
            findingType: selectedFindingType.value || undefined,
            status: selectedStatus.value || undefined,
            limit: 100,
            offset: 0,
        });
    }
};

onMounted(() => loadData());

watch(() => currentOrganization.value?.org_id, () => loadData());
watch([selectedAwsAccountId, selectedFindingType, selectedStatus], () => loadData());

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

const uniqueFindingTypes = computed(() => {
    const set = new Set<string>();
    findings.value.forEach((f) => set.add(f.findingType));
    return Array.from(set).sort();
});
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
                        <option v-for="ft in uniqueFindingTypes" :key="ft" :value="ft">{{ ft }}</option>
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
