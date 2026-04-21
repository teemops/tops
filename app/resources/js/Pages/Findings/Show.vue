<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import SidebarAppLayout from '@/Layouts/SidebarAppLayout.vue';
import { useFindings, type FindingByTypeResponse } from '@/composables/useFindings';
import { useOrganizations } from '@/composables/useOrganizations';
import { useNotifications } from '@/composables/useNotifications';

const props = defineProps<{
    findingType: string;
}>();

const { fetchFindingsByType, loading, error } = useFindings();
const { currentOrganization } = useOrganizations();
const { showError } = useNotifications();

const data = ref<FindingByTypeResponse | null>(null);

const loadData = async () => {
    if (!currentOrganization.value?.org_id) return;
    data.value = await fetchFindingsByType(props.findingType);
};

onMounted(() => loadData());
watch(() => [currentOrganization.value?.org_id, props.findingType], () => loadData());

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
</script>

<template>
    <Head :title="data?.title ?? props.findingType" />
    <SidebarAppLayout>
        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mb-6">
                <Link :href="route('findings.index')" class="text-sm text-blue-600 dark:text-blue-400 hover:underline mb-2 inline-block">
                    ← Back to Findings
                </Link>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">{{ data?.title ?? props.findingType }}</h1>
                <p v-if="data?.description" class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ data.description }}</p>
            </div>

            <div v-if="error" class="mb-4 rounded-md bg-red-50 dark:bg-red-900/20 p-4 text-sm text-red-700 dark:text-red-400">
                {{ error }}
            </div>

            <div v-if="loading" class="text-center py-12 text-gray-500 dark:text-gray-400">
                Loading...
            </div>

            <template v-else-if="data">
                <!-- Remediation -->
                <section v-if="data.recommendation" class="mb-8">
                    <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Remediation</h2>
                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 shadow-sm">
                        <p class="font-medium text-gray-900 dark:text-white">{{ data.recommendation.recommendation }}</p>
                        <p class="mt-2 text-gray-600 dark:text-gray-300">{{ data.recommendation.description }}</p>
                        <p class="mt-2 text-gray-500 dark:text-gray-400"><strong>Impact:</strong> {{ data.recommendation.impact }}</p>
                        <ul class="mt-3 list-disc pl-5 space-y-1 text-gray-600 dark:text-gray-300">
                            <li v-for="(step, i) in data.recommendation.steps" :key="i">{{ step }}</li>
                        </ul>
                        <div v-if="data.recommendation.links?.length" class="mt-4">
                            <strong class="text-gray-700 dark:text-gray-300">References:</strong>
                            <ul class="mt-2 space-y-1">
                                <li v-for="(link, i) in data.recommendation.links" :key="i">
                                    <a :href="link" target="_blank" rel="noopener" class="text-blue-600 dark:text-blue-400 hover:underline break-all">{{ link }}</a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </section>

                <!-- All resources with this finding -->
                <section>
                    <h2 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                        Affected resources ({{ data.total }})
                    </h2>
                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow overflow-hidden">
                        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                            <li
                                v-for="f in data.findings"
                                :key="f.id"
                                class="px-6 py-4 flex flex-wrap items-center justify-between gap-2"
                            >
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', getSeverityBadge(f.severity)]">
                                            {{ f.severity }}
                                        </span>
                                        <span :class="['inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium', getStatusBadge(f.status)]">
                                            {{ f.status }}
                                        </span>
                                    </div>
                                    <p class="mt-1 font-medium text-gray-900 dark:text-white">{{ f.resourceId }}</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ f.awsAccountName }} · {{ f.resourceType }}</p>
                                </div>
                                <Link
                                    :href="`/scans/${f.scanId}`"
                                    class="text-sm text-blue-600 dark:text-blue-400 hover:underline"
                                >
                                    View scan
                                </Link>
                            </li>
                        </ul>
                    </div>
                </section>
            </template>
        </div>
    </SidebarAppLayout>
</template>
