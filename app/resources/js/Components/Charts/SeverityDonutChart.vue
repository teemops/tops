<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { ArcElement, Chart, DoughnutController, Tooltip } from 'chart.js';
import { useDarkMode } from '@/composables/useDarkMode';

Chart.register(DoughnutController, ArcElement, Tooltip);

const props = defineProps<{
    distribution: {
        critical: number;
        high: number;
        medium: number;
        low: number;
    };
}>();

const { isDark } = useDarkMode();

const canvas = ref<HTMLCanvasElement | null>(null);
let chart: Chart | null = null;

// Matches the severity palette used across the findings UI.
const SEVERITY_COLORS = ['#dc2626', '#ea580c', '#f59e0b', '#3b82f6'];

const total = computed(
    () => props.distribution.critical + props.distribution.high + props.distribution.medium + props.distribution.low
);

const values = () => [
    props.distribution.critical,
    props.distribution.high,
    props.distribution.medium,
    props.distribution.low,
];

const borderColor = () => (isDark.value ? '#1f2937' : '#ffffff');

const render = () => {
    if (!canvas.value) {
        return;
    }

    chart = new Chart(canvas.value, {
        type: 'doughnut',
        data: {
            labels: ['Critical', 'High', 'Medium', 'Low'],
            datasets: [
                {
                    data: values(),
                    backgroundColor: SEVERITY_COLORS,
                    borderColor: borderColor(),
                    borderWidth: 2,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '68%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: isDark.value ? '#1f2937' : '#111827',
                    padding: 10,
                    callbacks: {
                        label: (context) => {
                            const value = context.parsed as number;
                            const share = total.value ? Math.round((value / total.value) * 100) : 0;
                            return ` ${context.label}: ${value} (${share}%)`;
                        },
                    },
                },
            },
        },
    });
};

const refresh = () => {
    if (!chart) {
        return;
    }

    chart.data.datasets[0].data = values();
    chart.data.datasets[0].borderColor = borderColor();
    chart.update();
};

onMounted(render);
watch(() => props.distribution, refresh, { deep: true });
watch(isDark, refresh);

onBeforeUnmount(() => {
    chart?.destroy();
    chart = null;
});
</script>

<template>
    <div class="relative h-48 w-48 flex-shrink-0">
        <canvas ref="canvas" role="img" aria-label="Findings by severity"></canvas>
        <!-- Total sits in the donut hole; pointer-events-none keeps hover on the arcs. -->
        <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
            <span class="text-2xl font-semibold text-gray-900 dark:text-white">{{ total }}</span>
            <span class="text-xs text-gray-500 dark:text-gray-400">findings</span>
        </div>
    </div>
</template>
