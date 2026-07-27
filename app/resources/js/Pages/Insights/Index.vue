<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { Head } from '@inertiajs/vue3';
import SidebarAppLayout from '@/Layouts/SidebarAppLayout.vue';
import { useInsights } from '@/composables/useInsights';
import { useOrganizations } from '@/composables/useOrganizations';

const { insights, loading, error, fetchInsights } = useInsights();
const { currentOrganization } = useOrganizations();

const selectedPeriod = ref('30d');

const loadData = async () => {
    if (currentOrganization.value?.org_id) {
        await fetchInsights(selectedPeriod.value);
    }
};

onMounted(() => loadData());
watch(() => currentOrganization.value?.org_id, () => loadData());
watch(selectedPeriod, () => loadData());

const summary = computed(() => insights.value?.summary);
const severityDistribution = computed(() => insights.value?.severityDistribution);
const trend = computed(() => insights.value?.trend ?? []);
const topServices = computed(() => insights.value?.topServices ?? []);
const keyInsights = computed(() => insights.value?.keyInsights ?? []);

const trendMax = computed(() => {
    const values = trend.value.flatMap((point) => [point.new, point.resolved]);
    return values.length ? Math.max(...values, 1) : 1;
});

const severityStyles = {
    critical: 'bg-red-600',
    high: 'bg-orange-600',
    medium: 'bg-amber-500',
    low: 'bg-blue-500',
};

const severityLabels = {
    critical: 'Critical',
    high: 'High',
    medium: 'Medium',
    low: 'Low',
};

const insightStyles = {
    positive: 'border-green-200 bg-green-50 text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-300',
    warning: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300',
    danger: 'border-red-200 bg-red-50 text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300',
};
</script>

<template>
    <Head title="Insights" />
    <SidebarAppLayout>
        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Insights & Analytics</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Track recent posture trends, severity distribution, and the highest-impact issues.
                    </p>
                </div>
                <div>
                    <label for="period" class="sr-only">Period</label>
                    <select
                        id="period"
                        v-model="selectedPeriod"
                        class="rounded-md border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
                    >
                        <option value="30d">Last 30 days</option>
                        <option value="90d">Last 90 days</option>
                        <option value="365d">Last year</option>
                    </select>
                </div>
            </div>

            <div v-if="error" class="mb-6 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300">
                {{ error }}
            </div>

            <div v-if="loading" class="rounded-lg border border-gray-200 bg-white p-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                Loading insights...
            </div>

            <div v-else-if="summary" class="space-y-6">
                <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total findings</div>
                        <div class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">{{ summary.totalFindings }}</div>
                        <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">In the selected period</div>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Open findings</div>
                        <div class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">{{ summary.openFindings }}</div>
                        <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">Awaiting remediation</div>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Critical open</div>
                        <div class="mt-2 text-3xl font-semibold text-red-600 dark:text-red-400">{{ summary.criticalOpen }}</div>
                        <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">Highest severity backlog</div>
                    </div>
                    <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Remediation rate</div>
                        <div class="mt-2 text-3xl font-semibold text-emerald-600 dark:text-emerald-400">{{ summary.remediationRate }}%</div>
                        <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">Resolved vs. total findings</div>
                    </div>
                </section>

                <section class="grid gap-6 xl:grid-cols-[2fr_1fr]">
                    <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="mb-5 flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Findings trend</h2>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">New findings vs. resolved findings over time</p>
                            </div>
                            <div class="flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                                <span class="flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-blue-500"></span>New</span>
                                <span class="flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-green-500"></span>Resolved</span>
                            </div>
                        </div>
                        <div class="flex h-64 items-end gap-3 rounded-lg bg-gray-50 p-4 dark:bg-gray-900/60">
                            <div v-for="point in trend" :key="point.date" class="flex flex-1 flex-col items-center gap-2">
                                <div class="flex h-40 w-full items-end gap-1">
                                    <div
                                        class="w-1/2 rounded-t-md bg-blue-500"
                                        :style="{ height: `${Math.max(8, (point.new / trendMax) * 100)}%` }"
                                    ></div>
                                    <div
                                        class="w-1/2 rounded-t-md bg-green-500"
                                        :style="{ height: `${Math.max(8, (point.resolved / trendMax) * 100)}%` }"
                                    ></div>
                                </div>
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ point.date }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Severity distribution</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Current backlog by severity</p>
                        <div class="mt-6 space-y-4">
                            <div v-for="(count, severity) in severityDistribution" :key="severity" class="space-y-2">
                                <div class="flex items-center justify-between text-sm">
                                    <div class="flex items-center gap-2">
                                        <span :class="['h-3 w-3 rounded-full', severityStyles[severity as keyof typeof severityStyles]]"></span>
                                        <span class="text-gray-700 dark:text-gray-300">{{ severityLabels[severity as keyof typeof severityLabels] }}</span>
                                    </div>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ count }}</span>
                                </div>
                                <div class="h-2 rounded-full bg-gray-200 dark:bg-gray-700">
                                    <div
                                        :class="['h-2 rounded-full', severityStyles[severity as keyof typeof severityStyles]]"
                                        :style="{ width: `${Math.max(6, (count / Math.max(summary.totalFindings, 1)) * 100)}%` }"
                                    ></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="grid gap-6 xl:grid-cols-[1.3fr_0.7fr]">
                    <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="mb-5 flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Compliance posture</h2>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Overall security posture score based on current findings</p>
                            </div>
                            <span class="text-2xl font-semibold text-gray-900 dark:text-white">{{ summary.averageCompliance }}%</span>
                        </div>
                        <div class="h-3 rounded-full bg-gray-200 dark:bg-gray-700">
                            <div class="h-3 rounded-full bg-emerald-500" :style="{ width: `${summary.averageCompliance}%` }"></div>
                        </div>
                        <div class="mt-4 flex items-center justify-between text-sm text-gray-500 dark:text-gray-400">
                            <span>Higher is better</span>
                            <span>Critical indicators: {{ summary.criticalOpen }}</span>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Top affected services</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Highest-volume services in the selected period</p>
                        <div class="mt-6 space-y-4">
                            <div v-for="service in topServices" :key="service.service" class="flex items-center justify-between rounded-md bg-gray-50 px-3 py-2 dark:bg-gray-900/60">
                                <span class="font-medium text-gray-900 dark:text-white">{{ service.service }}</span>
                                <span class="text-sm text-gray-500 dark:text-gray-400">{{ service.count }} findings</span>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Key insights</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Actionable takeaways based on the current data</p>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div
                            v-for="insight in keyInsights"
                            :key="insight.title"
                            :class="['rounded-lg border p-4', insightStyles[insight.severity as keyof typeof insightStyles]]"
                        >
                            <div class="font-semibold">{{ insight.title }}</div>
                            <div class="mt-1 text-sm">{{ insight.detail }}</div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </SidebarAppLayout>
</template>
