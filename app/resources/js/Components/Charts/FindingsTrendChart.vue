<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import {
    CategoryScale,
    Chart,
    Filler,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
} from 'chart.js';
import { useDarkMode } from '@/composables/useDarkMode';
import type { InsightPoint } from '@/composables/useInsights';

// Registered explicitly rather than via chart.js/auto so only the line-chart
// pieces end up in the bundle.
Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Filler);

const props = defineProps<{
    points: InsightPoint[];
}>();

const { isDark } = useDarkMode();

const canvas = ref<HTMLCanvasElement | null>(null);
let chart: Chart | null = null;

const gridColor = () => (isDark.value ? 'rgba(255, 255, 255, 0.08)' : 'rgba(17, 24, 39, 0.08)');
const tickColor = () => (isDark.value ? '#9ca3af' : '#6b7280');

const buildConfig = () => ({
    labels: props.points.map((point) => point.date),
    datasets: [
        {
            label: 'New',
            data: props.points.map((point) => point.new),
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.12)',
            fill: true,
            tension: 0.3,
            pointRadius: 0,
            pointHoverRadius: 4,
            borderWidth: 2,
        },
        {
            label: 'Resolved',
            data: props.points.map((point) => point.resolved),
            borderColor: '#22c55e',
            backgroundColor: 'rgba(34, 197, 94, 0.12)',
            fill: true,
            tension: 0.3,
            pointRadius: 0,
            pointHoverRadius: 4,
            borderWidth: 2,
        },
    ],
});

const render = () => {
    if (!canvas.value) {
        return;
    }

    chart = new Chart(canvas.value, {
        type: 'line',
        data: buildConfig(),
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: isDark.value ? '#1f2937' : '#111827',
                    padding: 10,
                    displayColors: true,
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        color: tickColor(),
                        maxRotation: 0,
                        autoSkip: true,
                        maxTicksLimit: 8,
                    },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: gridColor() },
                    ticks: { color: tickColor(), precision: 0 },
                },
            },
        },
    });
};

const refresh = () => {
    if (!chart) {
        return;
    }

    const next = buildConfig();
    chart.data.labels = next.labels;
    chart.data.datasets.forEach((dataset, index) => {
        dataset.data = next.datasets[index].data;
    });

    if (chart.options.scales?.x?.ticks) {
        (chart.options.scales.x.ticks as { color?: string }).color = tickColor();
    }
    if (chart.options.scales?.y?.ticks) {
        (chart.options.scales.y.ticks as { color?: string }).color = tickColor();
    }
    if (chart.options.scales?.y?.grid) {
        (chart.options.scales.y.grid as { color?: string }).color = gridColor();
    }

    chart.update();
};

onMounted(render);
watch(() => props.points, refresh, { deep: true });
watch(isDark, refresh);

onBeforeUnmount(() => {
    chart?.destroy();
    chart = null;
});
</script>

<template>
    <div class="relative h-64">
        <canvas ref="canvas" role="img" aria-label="Findings trend: new versus resolved over time"></canvas>
    </div>
</template>
