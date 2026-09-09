<script setup lang="ts">
import { computed } from 'vue';
import { Checkbox } from '@/components/ui/checkbox';
import EmptyState from '@/components/EmptyState.vue';
import { formatDate } from '@/lib/formatters';
import { formatHours } from '@/lib/utils';
import type { TimeEntryData } from '@/types/generated';

/**
 * The time entries behind an invoice, grouped per date. With `selectable`
 * every entry gets a checkbox; unticked ids are exposed via
 * `v-model:excluded` so the prepare page can leave hours off the invoice.
 */
const props = defineProps<{
    entries: TimeEntryData[];
    selectable?: boolean;
}>();

const excluded = defineModel<number[]>('excluded', { default: () => [] });

interface DayGroup {
    date: string;
    hours: number;
    entries: TimeEntryData[];
}

const days = computed<DayGroup[]>(() => {
    const groups = new Map<string, DayGroup>();

    for (const entry of props.entries) {
        const group = groups.get(entry.spent_on) ?? {
            date: entry.spent_on,
            hours: 0,
            entries: [],
        };
        group.entries.push(entry);

        if (!isExcluded(entry)) {
            group.hours += Number.parseFloat(entry.hours);
        }

        groups.set(entry.spent_on, group);
    }

    return Array.from(groups.values());
});

function isExcluded(entry: TimeEntryData): boolean {
    return entry.id !== null && excluded.value.includes(entry.id);
}

function toggle(entry: TimeEntryData, checked: boolean | 'indeterminate') {
    if (entry.id === null) {
        return;
    }

    const id = entry.id;

    excluded.value =
        checked === true
            ? excluded.value.filter((excludedId) => excludedId !== id)
            : [...excluded.value.filter((excludedId) => excludedId !== id), id];
}
</script>

<template>
    <EmptyState v-if="entries.length === 0" description="No time entries." />
    <div v-else class="divide-border divide-y">
        <div
            v-for="day in days"
            :key="day.date"
            class="py-3 first:pt-0 last:pb-0"
        >
            <div class="mb-1.5 flex items-baseline justify-between text-sm">
                <span class="font-medium">{{ formatDate(day.date) }}</span>
                <span class="text-muted-foreground tabular-nums">
                    {{ formatHours(day.hours) }} h
                </span>
            </div>
            <ul class="space-y-1">
                <li
                    v-for="entry in day.entries"
                    :key="entry.id ?? `${entry.spent_on}-${entry.project_id}`"
                    class="flex items-start gap-3 text-sm"
                    :class="{
                        'text-muted-foreground line-through': isExcluded(entry),
                    }"
                >
                    <Checkbox
                        v-if="selectable"
                        class="mt-0.5"
                        :model-value="!isExcluded(entry)"
                        :aria-label="`Include ${entry.project_name ?? 'entry'} on ${formatDate(entry.spent_on)}`"
                        @update:model-value="toggle(entry, $event)"
                    />
                    <span
                        class="mt-1.5 size-2 shrink-0 rounded-full"
                        :style="{
                            backgroundColor:
                                entry.project_color ??
                                'var(--color-muted-foreground)',
                        }"
                    />
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-baseline gap-x-2">
                            <span class="font-medium">{{
                                entry.project_name
                            }}</span>
                            <span
                                v-if="entry.task_name"
                                class="text-muted-foreground"
                            >
                                {{ entry.task_name }}
                            </span>
                            <span
                                v-if="entry.hourly_rate === null"
                                class="rounded-full bg-amber-100 px-1.5 text-xs text-amber-700"
                            >
                                no rate
                            </span>
                        </div>
                        <p
                            v-if="entry.notes"
                            class="text-muted-foreground truncate"
                        >
                            {{ entry.notes }}
                        </p>
                    </div>
                    <span class="shrink-0 tabular-nums">{{
                        formatHours(entry.hours)
                    }}</span>
                </li>
            </ul>
        </div>
    </div>
</template>
