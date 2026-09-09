<script setup lang="ts">
import { computed } from 'vue';
import { formatEuro } from '@/lib/formatters';
import { formatHours } from '@/lib/utils';
import type { DashboardStatData } from '@/types/generated';

/**
 * Dashboard headline tile: label, the current period's total with the
 * delta next to it, and one stacked bar in which the orange part is the
 * current period and the grey remainder the previous one. Sits inside the
 * hairline grid on the dashboard, so it draws no border of its own.
 */
const props = defineProps<{
    stat: DashboardStatData;
}>();

const isEuro = computed(() => props.stat.format === 'euro');

const value = computed(() =>
    isEuro.value ? formatEuro(props.stat.value) : formatHours(props.stat.value),
);

const current = computed(() => Number.parseFloat(props.stat.value) || 0);
const previous = computed(
    () => Number.parseFloat(props.stat.previous_value) || 0,
);

/** Share of the current period in current + previous, as a bar width. */
const currentWidth = computed(() => {
    const total = current.value + previous.value;

    if (total <= 0) {
        return '0%';
    }

    return `${Math.round((current.value / total) * 1000) / 10}%`;
});

const previousLabel = computed(() => props.stat.previous_label.toLowerCase());

const delta = computed(() => {
    const percent = props.stat.delta_percent;

    if (percent === null) {
        return null;
    }

    const sign = percent > 0 ? '+' : percent < 0 ? '-' : '';

    return {
        text: `${sign}${Math.abs(percent).toFixed(2)}%`,
        class:
            percent > 0
                ? 'text-emerald-600'
                : percent < 0
                  ? 'text-rose-600'
                  : 'text-muted-foreground',
    };
});
</script>

<template>
    <div class="bg-card flex flex-col p-5">
        <div class="text-foreground/80 text-[15px]">{{ stat.label }}</div>
        <div
            class="mt-1.5 mb-4 flex flex-wrap items-baseline gap-x-2.5 gap-y-0.5"
        >
            <span
                class="text-[32px] leading-none font-semibold tracking-tight tabular-nums"
            >
                {{ value
                }}<span
                    v-if="!isEuro"
                    class="text-muted-foreground ml-1 text-lg font-medium"
                    >h</span
                >
            </span>
            <span v-if="delta" class="text-[15px]">
                <span class="font-medium" :class="delta.class">
                    {{ delta.text }}
                </span>
                <span class="text-muted-foreground">
                    vs. {{ previousLabel }}
                </span>
            </span>
            <span v-else class="text-muted-foreground text-[15px]">
                No hours {{ previousLabel }}
            </span>
        </div>

        <div class="border-border mt-auto mb-4 border-t border-dashed" />

        <div class="bg-muted h-6 w-full overflow-hidden rounded-md">
            <div
                class="from-primary to-primary/75 h-full rounded-md bg-linear-to-r transition-[width] duration-500"
                :style="{ width: currentWidth }"
            />
        </div>
        <div
            class="text-muted-foreground mt-3 flex items-center justify-between text-[15px]"
        >
            <span class="inline-flex items-center gap-2">
                <span class="bg-primary size-3 rounded-[3px]" />
                {{ stat.current_label }}
            </span>
            <span class="inline-flex items-center gap-2">
                {{ stat.previous_label }}
                <span class="bg-muted size-3 rounded-[3px]" />
            </span>
        </div>
    </div>
</template>
