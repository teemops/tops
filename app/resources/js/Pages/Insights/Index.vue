<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import SidebarAppLayout from '@/Layouts/SidebarAppLayout.vue';
import FindingsTrendChart from '@/Components/Charts/FindingsTrendChart.vue';
import SeverityDonutChart from '@/Components/Charts/SeverityDonutChart.vue';
import { useInsights } from '@/composables/useInsights';
import { useOrganizations } from '@/composables/useOrganizations';

const { insights, loading, error, fetchInsights } = useInsights();
const { currentOrganization } = useOrganizations();

const PERIODS = ['30d', '90d', '365d'] as const;
type Period = (typeof PERIODS)[number];

const isPeriod = (value: string | null): value is Period => PERIODS.includes(value as Period);

// Seed from the URL so a shared link opens on the same period.
const periodFromUrl = (): Period => {
    if (typeof window === 'undefined') {
        return '30d';
    }
    const value = new URLSearchParams(window.location.search).get('period');
    return isPeriod(value) ? value : '30d';
};

const selectedPeriod = ref<Period>(periodFromUrl());

const syncUrl = (period: Period) => {
    if (typeof window === 'undefined') {
        return;
    }
    const url = new URL(window.location.href);
    url.searchParams.set('period', period);
    window.history.replaceState({}, '', url);
};

const loadData = async () => {
    if (currentOrganization.value?.org_id) {
        await fetchInsights(selectedPeriod.value);
    }
};

onMounted(() => {
    syncUrl(selectedPeriod.value);
    loadData();
});
watch(() => currentOrganization.value?.org_id, () => loadData());
watch(selectedPeriod, (period) => {
    syncUrl(period);
    loadData();
});

const summary = computed(() => insights.value?.summary);
const severityDistribution = computed(() => insights.value?.severityDistribution);
const frameworks = computed(() => insights.value?.compliance?.frameworks ?? []);
const trend = computed(() => insights.value?.trend ?? []);
const topServices = computed(() => insights.value?.topServices ?? []);
const keyInsights = computed(() => insights.value?.keyInsights ?? []);

const periodLabels: Record<Period, string> = {
    '30d': 'Last 30 days',
    '90d': 'Last 90 days',
    '365d': 'Last year',
};

/** No completed scans at all in the window — nothing to analyse, so prompt a scan. */
const hasNoScans = computed(() => !!summary.value && summary.value.scanCount === 0);
/** Scans ran but found nothing — a genuinely clean result, not an error. */
const hasNoFindings = computed(() => !!summary.value && summary.value.scanCount > 0 && summary.value.totalFindings === 0);

const findingsChange = computed(() => summary.value?.findingsChangePercent ?? null);
const findingsFalling = computed(() => (findingsChange.value ?? 0) < 0);

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

// Per the design spec: green above 80, lighter green 60-79, amber below 60.
const scoreBarClass = (score: number) => {
    if (score >= 80) return 'bg-green-600';
    if (score >= 60) return 'bg-green-500';
    return 'bg-amber-500';
};
</script>

<template>
    <Head title="Insights" />
    <SidebarAppLayout>
        <div class="px-4 py-6 sm:px-6 lg:px-8">
            <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Insights &amp; Analytics</h1>
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
                        <option v-for="period in PERIODS" :key="period" :value="period">{{ periodLabels[period] }}</option>
                    </select>
                </div>
            </div>

            <div v-if="error" class="mb-6 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300">
                {{ error }}
            </div>

            <div v-if="loading" class="rounded-lg border border-gray-200 bg-white p-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                Loading insights...
            </div>

            <div
                v-else-if="hasNoScans"
                class="rounded-lg border border-gray-200 bg-white p-12 text-center dark:border-gray-700 dark:bg-gray-800"
            >
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">No scan data for {{ periodLabels[selectedPeriod].toLowerCase() }}</h2>
                <p class="mx-auto mt-2 max-w-md text-sm text-gray-500 dark:text-gray-400">
                    Insights are built from completed scans. Run a scan against an AWS account, or widen the period, to
                    see trends here.
                </p>
                <Link
                    :href="route('scans.index')"
                    class="mt-6 inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-blue-700"
                >
                    Run a scan
                </Link>
            </div>

            <div v-else-if="summary && severityDistribution" class="space-y-6">
                <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total findings</div>
                        <div class="mt-2 flex items-baseline gap-2">
                            <span class="text-3xl font-semibold text-gray-900 dark:text-white">{{ summary.totalFindings }}</span>
                            <span
                                v-if="findingsChange !== null"
                                :class="[
                                    'inline-flex items-baseline text-sm font-semibold',
                                    findingsFalling ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400',
                                ]"
                            >
                                <span aria-hidden="true">{{ findingsFalling ? '↓' : '↑' }}</span>
                                {{ Math.abs(findingsChange) }}%
                            </span>
                        </div>
                        <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            <template v-if="findingsChange !== null">
                                vs. {{ summary.previousTotalFindings }} in the previous period
                            </template>
                            <template v-else>In the selected period</template>
                        </div>
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
                    <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Avg compliance</div>
                        <div v-if="summary.averageCompliance !== null" class="mt-2 text-3xl font-semibold text-emerald-600 dark:text-emerald-400">
                            {{ summary.averageCompliance }}%
                        </div>
                        <div v-else class="mt-2 text-3xl font-semibold text-gray-400 dark:text-gray-500">—</div>
                        <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            {{ summary.averageCompliance !== null ? 'Across evaluated frameworks' : 'No framework evaluated yet' }}
                        </div>
                    </div>
                </section>

                <section class="grid gap-6 xl:grid-cols-[2fr_1fr]">
                    <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="mb-5 flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Findings trend</h2>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    New vs. resolved findings, aggregated by {{ insights?.granularity ?? 'day' }}
                                </p>
                            </div>
                            <div class="flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                                <span class="flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-blue-500"></span>New</span>
                                <span class="flex items-center gap-2"><span class="h-3 w-3 rounded-full bg-green-500"></span>Resolved</span>
                            </div>
                        </div>
                        <FindingsTrendChart v-if="trend.length" :points="trend" />
                        <div v-else class="flex h-64 items-center justify-center text-sm text-gray-400 dark:text-gray-500">
                            No scan data for selected period
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Severity distribution</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Findings by severity in this period</p>
                        <div v-if="hasNoFindings" class="mt-8 text-center text-sm text-gray-400 dark:text-gray-500">
                            No findings in this period
                        </div>
                        <div v-else class="mt-6 flex flex-col items-center gap-6 sm:flex-row xl:flex-col 2xl:flex-row">
                            <SeverityDonutChart :distribution="severityDistribution" />
                            <div class="w-full flex-1 space-y-3">
                                <div v-for="(count, severity) in severityDistribution" :key="severity" class="flex items-center justify-between text-sm">
                                    <span class="flex items-center gap-2">
                                        <span :class="['h-3 w-3 rounded-full', severityStyles[severity as keyof typeof severityStyles]]"></span>
                                        <span class="text-gray-700 dark:text-gray-300">{{ severityLabels[severity as keyof typeof severityLabels] }}</span>
                                    </span>
                                    <span class="font-medium text-gray-900 dark:text-white">{{ count }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="grid gap-6 xl:grid-cols-[1.3fr_0.7fr]">
                    <div class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Compliance scores</h2>
                            <span class="text-xs text-gray-500 dark:text-gray-400">By framework</span>
                        </div>
                        <div class="space-y-5 px-6 py-6">
                            <div v-for="framework in frameworks" :key="framework.key">
                                <div class="mb-2 flex items-center justify-between">
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300" :title="framework.description">
                                        {{ framework.label }}
                                    </span>
                                    <span v-if="framework.evaluated" class="text-sm font-medium text-gray-900 dark:text-white">
                                        {{ framework.score }}%
                                    </span>
                                    <span v-else class="text-xs text-gray-400 dark:text-gray-500">Not evaluated</span>
                                </div>
                                <div class="h-2.5 w-full rounded-full bg-gray-200 dark:bg-gray-700">
                                    <div
                                        v-if="framework.evaluated"
                                        :class="['h-2.5 rounded-full', scoreBarClass(framework.score ?? 0)]"
                                        :style="{ width: `${framework.score}%` }"
                                    ></div>
                                </div>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    <template v-if="framework.evaluated">
                                        {{ framework.totalRules - framework.failingRules }} of {{ framework.totalRules }} checks passing
                                    </template>
                                    <template v-else>
                                        Run a scan with this profile to score its {{ framework.totalRules }} checks
                                    </template>
                                </p>
                            </div>
                            <p v-if="!frameworks.length" class="text-sm text-gray-500 dark:text-gray-400">
                                No compliance rulesets are authored yet.
                            </p>
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
                            <p v-if="!topServices.length" class="text-sm text-gray-500 dark:text-gray-400">
                                No findings in this period.
                            </p>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="mb-4 flex items-center justify-between">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Key insights</h2>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Actionable takeaways based on the current data</p>
                        </div>
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ periodLabels[selectedPeriod] }}</span>
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
