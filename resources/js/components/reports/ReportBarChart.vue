<script setup lang="ts">
import {
    BarElement,
    CategoryScale,
    Chart,
    Legend,
    LinearScale,
    Tooltip,
} from 'chart.js';
import type { ChartData, ChartOptions } from 'chart.js';
import { computed, onMounted, ref } from 'vue';
import { Bar } from 'vue-chartjs';
import { formatEuro, formatEuroCompact } from '@/lib/formatters';
import { formatHours } from '@/lib/utils';

Chart.register(CategoryScale, LinearScale, BarElement, Tooltip, Legend);

export interface ReportBarDataset {
    label: string;
    data: number[];
    /** `primary` is the theme orange, `amber` the fixed second series color. */
    color: 'primary' | 'amber';
}

/**
 * Grouped bar chart for the reports page: one bar per period and one
 * series per dataset (hours next to billable hours, earned next to
 * invoiced). Colors are read from the theme tokens at mount so the chart
 * follows app.css in both light and dark mode; `format` decides how the
 * axis and the tooltip print a value.
 */
const props = defineProps<{
    labels: string[];
    datasets: ReportBarDataset[];
    format: 'hours' | 'euro';
}>();

const fallback = {
    primary: 'hsl(18 90% 52%)',
    amber: 'hsl(38 95% 55%)',
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

onMounted(() => {
    colors.value = {
        ...fallback,
        primary: token('--primary', fallback.primary),
        muted: token('--muted-foreground', fallback.muted),
        border: token('--border', fallback.border),
        card: token('--card', fallback.card),
        foreground: token('--foreground', fallback.foreground),
    };
});

function formatValue(value: number): string {
    return props.format === 'euro'
        ? formatEuro(value)
        : `${formatHours(value)} h`;
}

function formatTick(value: number | string): string {
    const amount = typeof value === 'string' ? Number.parseFloat(value) : value;

    return props.format === 'euro' ? formatEuroCompact(amount) : `${amount}h`;
}

const chartData = computed<ChartData<'bar'>>(() => ({
    labels: props.labels,
    datasets: props.datasets.map((dataset) => ({
        label: dataset.label,
        data: dataset.data,
        backgroundColor: colors.value[dataset.color],
        hoverBackgroundColor: colors.value[dataset.color],
        borderRadius: 4,
        borderSkipped: false,
        maxBarThickness: 28,
    })),
}));

const chartOptions = computed<ChartOptions<'bar'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    animation: { duration: 300 },
    interaction: { mode: 'index', intersect: false },
    plugins: {
        legend: {
            display: props.datasets.length > 1,
            position: 'top',
            align: 'end',
            labels: {
                color: colors.value.muted,
                boxWidth: 8,
                boxHeight: 8,
                usePointStyle: true,
                pointStyle: 'circle',
                font: { size: 11 },
            },
        },
        tooltip: {
            backgroundColor: colors.value.foreground,
            titleColor: colors.value.card,
            bodyColor: colors.value.card,
            padding: 10,
            cornerRadius: 8,
            usePointStyle: true,
            boxWidth: 8,
            boxHeight: 8,
            callbacks: {
                label: (item) =>
                    ` ${item.dataset.label}: ${formatValue(item.parsed.y ?? 0)}`,
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
                maxTicksLimit: 13,
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
                callback: (value) => formatTick(value),
            },
        },
    },
}));
</script>

<template>
    <div class="relative h-64 w-full">
        <Bar :data="chartData" :options="chartOptions" />
    </div>
</template>
