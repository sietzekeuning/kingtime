<script setup lang="ts">
import { computed } from 'vue';
import { formatEuro } from '@/lib/formatters';
import { formatHours } from '@/lib/utils';

/**
 * What a project has used of its budget, Harvest style: the amount spent,
 * a bar that fills up as the budget is used and turns red once it is
 * exceeded, and what is left with its percentage. Works for a money budget
 * (unit "euro") and an hour budget (unit "hours"). Without a budget only
 * the spent value shows.
 */
const props = withDefaults(
    defineProps<{
        spent: string | null;
        budget: string | null;
        unit?: 'euro' | 'hours';
    }>(),
    { unit: 'euro' },
);

const format = (value: number | string | null) =>
    props.unit === 'hours' ? formatHours(value) : formatEuro(value);

const used = computed(() => Number.parseFloat(props.spent ?? '0') || 0);

const budgeted = computed(() => {
    const value = Number.parseFloat(props.budget ?? '');

    return Number.isFinite(value) && value > 0 ? value : null;
});

const remaining = computed(() =>
    budgeted.value === null ? null : budgeted.value - used.value,
);

const percentUsed = computed(() =>
    budgeted.value === null
        ? 0
        : Math.min(100, Math.round((used.value / budgeted.value) * 100)),
);

const percentLeft = computed(() =>
    budgeted.value === null
        ? null
        : Math.max(0, Math.round((remaining.value! / budgeted.value) * 100)),
);

const overBudget = computed(
    () => remaining.value !== null && remaining.value < 0,
);

const barClass = computed(() => {
    if (overBudget.value) {
        return 'bg-destructive';
    }

    return percentUsed.value >= 80 ? 'bg-chart-2' : 'bg-primary';
});
</script>

<template>
    <div class="flex items-center gap-3">
        <span class="w-24 shrink-0 text-right tabular-nums">
            {{ format(spent) }}
        </span>
        <template v-if="budgeted !== null">
            <div
                class="bg-muted h-2 w-24 shrink-0 overflow-hidden rounded-full"
                role="progressbar"
                :aria-valuenow="percentUsed"
                aria-valuemin="0"
                aria-valuemax="100"
                :title="`${format(spent)} of ${format(budget)}`"
            >
                <div
                    class="h-full rounded-full transition-all"
                    :class="barClass"
                    :style="{ width: `${percentUsed}%` }"
                />
            </div>
            <span
                class="text-xs whitespace-nowrap tabular-nums"
                :class="
                    overBudget ? 'text-destructive' : 'text-muted-foreground'
                "
            >
                <template v-if="overBudget">
                    {{ format(-remaining!) }} over
                </template>
                <template v-else>
                    {{ format(remaining) }} left ({{ percentLeft }}%)
                </template>
            </span>
        </template>
    </div>
</template>
