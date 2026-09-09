<script setup lang="ts">
import { computed } from 'vue';
import { Card } from '@/components/ui/card';
import { formatEuro } from '@/lib/formatters';
import { formatHours } from '@/lib/utils';
import type { DashboardStatData } from '@/types/generated';

/**
 * Dashboard headline tile: a MetricCard variant that also draws the current
 * period as an orange bar over the previous period's grey bar, so the delta
 * has a shape and not only a number.
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
const scale = computed(() => Math.max(current.value, previous.value));

function widthOf(amount: number): string {
    if (scale.value <= 0) {
        return '0%';
    }

    return `${Math.round((amount / scale.value) * 1000) / 10}%`;
}

const currentWidth = computed(() => widthOf(current.value));
const previousWidth = computed(() => widthOf(previous.value));

const previousLabel = computed(() => props.stat.previous_label.toLowerCase());

const deltaClass = computed(() => {
    const delta = props.stat.delta_percent;

    if (delta === null || delta === 0) {
        return 'text-muted-foreground';
    }

    return delta > 0 ? 'text-emerald-700' : 'text-rose-700';
});

const deltaText = computed(() => {
    const delta = props.stat.delta_percent;

    if (delta === null) {
        return `No hours ${previousLabel.value}`;
    }

    const sign = delta > 0 ? '+' : delta < 0 ? '-' : '';

    return `${sign}${Math.abs(delta).toFixed(1)}% vs. ${previousLabel.value}`;
});
</script>

<template>
    <Card class="gap-0 p-5 shadow-none">
        <div class="text-muted-foreground text-sm">{{ stat.label }}</div>
        <div class="mt-1 flex items-baseline gap-1">
            <span class="text-3xl font-semibold tracking-tight tabular-nums">
                {{ value }}
            </span>
            <span v-if="!isEuro" class="text-muted-foreground text-base">
                h
            </span>
        </div>
        <div class="mt-1 text-xs font-medium" :class="deltaClass">
            {{ deltaText }}
        </div>

        <div class="bg-muted relative mt-4 h-1.5 w-full rounded-full">
            <div
                class="bg-muted-foreground/25 absolute inset-y-0 left-0 rounded-full"
                :style="{ width: previousWidth }"
            />
            <div
                class="bg-primary absolute inset-y-0 left-0 rounded-full transition-[width]"
                :style="{ width: currentWidth }"
            />
        </div>
        <div class="text-muted-foreground mt-2 flex items-center gap-3 text-xs">
            <span class="inline-flex items-center gap-1.5">
                <span class="bg-primary size-1.5 rounded-full" />
                {{ stat.current_label }}
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span class="bg-muted-foreground/40 size-1.5 rounded-full" />
                {{ stat.previous_label }}
            </span>
        </div>
    </Card>
</template>
