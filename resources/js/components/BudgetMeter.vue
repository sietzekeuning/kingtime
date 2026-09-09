<script setup lang="ts">
import { computed } from 'vue';
import { formatHours } from '@/lib/utils';

/**
 * Hours spent on a project against its hour budget, Harvest style: the
 * hours, a bar that fills up as the budget is used and turns red once it
 * is exceeded, and what is left. Without a budget only the hours show.
 */
const props = defineProps<{
    hours: string | null;
    budget: string | null;
}>();

const spent = computed(() => Number.parseFloat(props.hours ?? '0') || 0);

const budgeted = computed(() => {
    const value = Number.parseFloat(props.budget ?? '');

    return Number.isFinite(value) && value > 0 ? value : null;
});

const remaining = computed(() =>
    budgeted.value === null ? null : budgeted.value - spent.value,
);

const percentUsed = computed(() =>
    budgeted.value === null
        ? 0
        : Math.min(100, Math.round((spent.value / budgeted.value) * 100)),
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
        <span class="w-16 shrink-0 text-right tabular-nums">
            {{ formatHours(hours) }}
        </span>
        <template v-if="budgeted !== null">
            <div
                class="bg-muted h-2 w-24 shrink-0 overflow-hidden rounded-full"
                role="progressbar"
                :aria-valuenow="percentUsed"
                aria-valuemin="0"
                aria-valuemax="100"
                :title="`${formatHours(hours)} of ${formatHours(budget)} hours`"
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
                    {{ formatHours(-remaining!) }} over
                </template>
                <template v-else>
                    {{ formatHours(remaining) }} left ({{ percentLeft }}%)
                </template>
            </span>
        </template>
    </div>
</template>
