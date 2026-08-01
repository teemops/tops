<script setup lang="ts">
/**
 * A grouped, severity-stacked, drillable breakdown.
 *
 * Shared on purpose: Scan detail keys it on one account, Insights (F-5/F-6) keys the same
 * block on the whole organization. If this had been written inline on Scan detail, the
 * Insights work would have cost twice as much.
 *
 * It deliberately knows nothing about routing — it emits which group was chosen and lets
 * the page decide where that goes.
 */
import { ref, computed } from 'vue';
import type { BreakdownGroup, Severity } from '@/composables/useScans';

export interface BreakdownTab {
    key: string;
    label: string;
    groups: BreakdownGroup[];
    /** Optional secondary text per group key, e.g. a rule's title. */
    sublabels?: Record<string, string>;
    /** Uppercase the group name — right for service codes, wrong for rule titles. */
    uppercase?: boolean;
}

const props = defineProps<{
    tabs: BreakdownTab[];
    title?: string;
}>();

const emit = defineEmits<{
    select: [tabKey: string, groupKey: string];
}>();

const activeTabKey = ref(props.tabs[0]?.key ?? '');

const activeTab = computed(
    () => props.tabs.find((tab) => tab.key === activeTabKey.value) ?? props.tabs[0]
);

const SEVERITIES: Severity[] = ['critical', 'high', 'medium', 'low'];

const SEVERITY_BAR: Record<Severity, string> = {
    critical: 'bg-red-500',
    high: 'bg-orange-500',
    medium: 'bg-amber-500',
    low: 'bg-blue-500',
};

const SEVERITY_LABEL: Record<Severity, string> = {
    critical: 'Critical',
    high: 'High',
    medium: 'Medium',
    low: 'Low',
};

/** Segment widths as percentages of the row's own total, so every bar fills its track. */
const segments = (group: BreakdownGroup) =>
    SEVERITIES.filter((severity) => group.severities[severity] > 0).map((severity) => ({
        severity,
        count: group.severities[severity],
        width: `${(group.severities[severity] / group.total) * 100}%`,
    }));
</script>

<template>
    <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm">
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <h2 v-if="title" class="text-sm font-medium text-gray-900 dark:text-white">{{ title }}</h2>

            <div v-if="tabs.length > 1" class="inline-flex overflow-hidden rounded-md border border-gray-300 dark:border-gray-600">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    :aria-pressed="tab.key === activeTabKey"
                    :class="[
                        'px-3 py-1 text-xs',
                        tab.key === activeTabKey
                            ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
                            : 'bg-white text-gray-600 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700',
                    ]"
                    @click="activeTabKey = tab.key"
                >
                    {{ tab.label }}
                </button>
            </div>

            <span class="ml-auto text-xs text-gray-400 dark:text-gray-500">Select a row to see those findings</span>
        </div>

        <p v-if="!activeTab || activeTab.groups.length === 0" class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
            Nothing open to break down.
        </p>

        <ul v-else class="divide-y divide-gray-100 dark:divide-gray-700">
            <li v-for="group in activeTab.groups" :key="group.key">
                <button
                    type="button"
                    class="flex w-full items-center gap-3 rounded px-2 py-2.5 text-left hover:bg-gray-50 dark:hover:bg-gray-700/50"
                    @click="emit('select', activeTab.key, group.key)"
                >
                    <span class="min-w-0 flex-1">
                        <span
                            :class="[
                                'block truncate text-sm font-medium text-gray-900 dark:text-white',
                                activeTab.uppercase ? 'uppercase' : '',
                            ]"
                        >{{ group.key }}</span>
                        <span
                            v-if="activeTab.sublabels?.[group.key]"
                            class="block truncate text-xs text-gray-500 dark:text-gray-400"
                        >{{ activeTab.sublabels[group.key] }}</span>
                    </span>

                    <span class="flex h-3 w-28 flex-none overflow-hidden rounded-sm bg-gray-100 dark:bg-gray-700">
                        <span
                            v-for="segment in segments(group)"
                            :key="segment.severity"
                            :class="['h-full', SEVERITY_BAR[segment.severity]]"
                            :style="{ width: segment.width }"
                            :title="`${SEVERITY_LABEL[segment.severity]}: ${segment.count}`"
                        ></span>
                    </span>

                    <span class="w-8 flex-none text-right text-sm tabular-nums text-gray-900 dark:text-white">{{ group.total }}</span>
                    <span class="flex-none text-blue-600 dark:text-blue-400" aria-hidden="true">&rarr;</span>
                </button>
            </li>
        </ul>

        <div class="mt-3 flex flex-wrap gap-3 border-t border-gray-100 dark:border-gray-700 pt-3 text-xs text-gray-500 dark:text-gray-400">
            <span v-for="severity in SEVERITIES" :key="severity" class="inline-flex items-center gap-1.5">
                <span :class="['inline-block h-2 w-2 rounded-sm', SEVERITY_BAR[severity]]"></span>
                {{ SEVERITY_LABEL[severity] }}
            </span>
        </div>
    </div>
</template>
