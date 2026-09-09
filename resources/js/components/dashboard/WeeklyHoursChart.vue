<script setup lang="ts">
import {
    CategoryScale,
    Chart,
    Filler,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
} from 'chart.js';
import type { ChartData, ChartOptions, ScriptableContext } from 'chart.js';
import { computed, onMounted, ref } from 'vue';
import { Line } from 'vue-chartjs';
import { formatHours } from '@/lib/utils';

Chart.register(
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Filler,
    Tooltip,
);

/**
 * Hours per week as an orange line with a soft gradient underneath. Colors
 * are read from the theme tokens at mount so the chart follows app.css in
 * both light and dark mode.
 */
const props = defineProps<{
    labels: string[];
    values: number[];
}>();

const fallback = {
    primary: 'hsl(18 90% 52%)',
    muted: 'hsl(25 6% 45%)',
    border: 'hsl(35 15% 89%)',
    card: 'hsl(0 0% 100%)',
    foreground: 'hsl(24 10% 10%)',
};

const colors = ref({ ...fallback });

function token(name: string, defaultValue: string): string {
    const value = getComputedStyle(document.documentElement)
        .getPropertyValue(name)
        .trim();

    return value === '' ? defaultValue : value;
}

/** `hsl(18 90% 52%)` -> `hsl(18 90% 52% / 0.3)`; leaves other syntaxes as is. */
function withAlpha(color: string, alpha: number): string {
    if (/^hsla?\([^/]+\)$/.test(color)) {
        return color.replace(/\)$/, ` / ${alpha})`);
    }

    return color;
}

onMounted(() => {
    colors.value = {
        primary: token('--primary', fallback.primary),
        muted: token('--muted-foreground', fallback.muted),
        border: token('--border', fallback.border),
        card: token('--card', fallback.card),
        foreground: token('--foreground', fallback.foreground),
    };
});

function gradient(context: ScriptableContext<'line'>): string | CanvasGradient {
    const { ctx, chartArea } = context.chart;

    if (!chartArea) {
        return 'transparent';
    }

    const fill = ctx.createLinearGradient(
        0,
        chartArea.top,
        0,
        chartArea.bottom,
    );
    fill.addColorStop(0, withAlpha(colors.value.primary, 0.28));
    fill.addColorStop(1, withAlpha(colors.value.primary, 0));

    return fill;
}

const chartData = computed<ChartData<'line'>>(() => ({
    labels: props.labels,
    datasets: [
        {
            label: 'Hours',
            data: props.values,
            borderColor: colors.value.primary,
            backgroundColor: gradient,
            borderWidth: 2,
            fill: true,
            tension: 0.35,
            pointRadius: 0,
            pointHitRadius: 12,
            pointHoverRadius: 5,
            pointHoverBackgroundColor: colors.value.primary,
            pointHoverBorderColor: colors.value.card,
            pointHoverBorderWidth: 2,
        },
    ],
}));

const chartOptions = computed<ChartOptions<'line'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    animation: { duration: 300 },
    interaction: { mode: 'index', intersect: false },
    plugins: {
        legend: { display: false },
        tooltip: {
            displayColors: false,
            backgroundColor: colors.value.foreground,
            titleColor: colors.value.card,
            bodyColor: colors.value.card,
            padding: 10,
            cornerRadius: 8,
            callbacks: {
                title: (items) => `Week of ${items[0]?.label ?? ''}`,
                label: (item) => `${formatHours(item.parsed.y)} hours`,
            },
        },
    },
    scales: {
        x: {
            grid: { display: false },
            border: { color: colors.value.border },
            ticks: {
                color: colors.value.muted,
                maxRotation: 0,
                autoSkip: true,
                maxTicksLimit: 9,
                font: { size: 11 },
            },
        },
        y: {
            beginAtZero: true,
            grid: { color: colors.value.border },
            border: { display: false, dash: [3, 3] },
            ticks: {
                color: colors.value.muted,
                font: { size: 11 },
                maxTicksLimit: 5,
                callback: (value) => `${value}h`,
            },
        },
    },
}));
</script>

<template>
    <div class="relative h-64 w-full">
        <Line :data="chartData" :options="chartOptions" />
    </div>
</template>
