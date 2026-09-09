<script setup lang="ts">
import {
    BarElement,
    CategoryScale,
    Chart,
    LinearScale,
    Tooltip,
} from 'chart.js';
import type { ChartData, ChartOptions } from 'chart.js';
import { computed, onMounted, ref } from 'vue';
import { Bar } from 'vue-chartjs';
import { formatHours } from '@/lib/utils';

Chart.register(CategoryScale, LinearScale, BarElement, Tooltip);

/**
 * Hours per week as rounded orange bars; the last bar is the current,
 * still growing week and is drawn lighter. Colors are read from the theme
 * tokens at mount so the chart follows app.css in light and dark mode.
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

const chartData = computed<ChartData<'bar'>>(() => {
    const lastIndex = props.values.length - 1;

    return {
        labels: props.labels,
        datasets: [
            {
                label: 'Hours',
                data: props.values,
                backgroundColor: props.values.map((_, index) =>
                    index === lastIndex
                        ? withAlpha(colors.value.primary, 0.45)
                        : colors.value.primary,
                ),
                hoverBackgroundColor: colors.value.primary,
                borderRadius: 6,
                borderSkipped: 'bottom',
                maxBarThickness: 28,
                categoryPercentage: 0.7,
                barPercentage: 0.9,
            },
        ],
    };
});

const chartOptions = computed<ChartOptions<'bar'>>(() => ({
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
                label: (item) =>
                    `${formatHours(item.parsed.y)} hours${
                        item.dataIndex === props.values.length - 1
                            ? ' so far'
                            : ''
                    }`,
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
                font: { size: 12 },
            },
        },
        y: {
            beginAtZero: true,
            grid: { color: colors.value.border },
            border: { display: false, dash: [3, 3] },
            ticks: {
                color: colors.value.muted,
                font: { size: 12 },
                maxTicksLimit: 6,
                callback: (value) => `${value}h`,
            },
        },
    },
}));
</script>

<template>
    <div class="relative h-72 w-full">
        <Bar :data="chartData" :options="chartOptions" />
    </div>
</template>
