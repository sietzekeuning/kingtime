<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { addDays, formatDate } from '@/lib/time';
import { formatHours } from '@/lib/utils';
import timeEntries from '@/routes/time-entries';
import type { TimesheetData } from '@/types/generated';

/**
 * Monday-to-Sunday strip with a total per day. Selecting a day (or moving a
 * week) reloads only the timesheet prop and keeps the table filters in the
 * URL, so the "All entries" tab is unaffected.
 */
const props = defineProps<{
    timesheet: TimesheetData;
}>();

function visitDate(date: string | null) {
    const params = new URLSearchParams(window.location.search);

    if (date) {
        params.set('date', date);
    } else {
        params.delete('date');
    }

    const query = params.toString();

    router.visit(`${timeEntries.index.url()}${query ? `?${query}` : ''}`, {
        only: ['timesheet'],
        preserveState: true,
        preserveScroll: true,
    });
}

const previousWeek = () => visitDate(addDays(props.timesheet.week_start, -7));
const nextWeek = () => visitDate(addDays(props.timesheet.week_start, 7));
const today = () => visitDate(null);
</script>

<template>
    <div class="flex flex-col gap-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-1">
                <Button
                    variant="outline"
                    size="icon-sm"
                    title="Previous week"
                    @click="previousWeek"
                >
                    <ChevronLeft class="size-4" />
                </Button>
                <Button
                    variant="outline"
                    size="icon-sm"
                    title="Next week"
                    @click="nextWeek"
                >
                    <ChevronRight class="size-4" />
                </Button>
                <Button variant="outline" size="sm" @click="today">
                    Today
                </Button>
                <span class="text-muted-foreground ml-2 text-sm">
                    {{ formatDate(timesheet.week_start, 'D MMM') }} ·
                    {{ formatDate(timesheet.week_end, 'D MMM YYYY') }}
                </span>
            </div>
            <div class="text-sm">
                <span class="text-muted-foreground">Week total</span>
                <span class="ml-2 text-lg font-semibold tabular-nums">
                    {{ formatHours(timesheet.week_total) }}
                </span>
            </div>
        </div>

        <div class="grid grid-cols-7 gap-1.5">
            <button
                v-for="day in timesheet.days"
                :key="day.date"
                type="button"
                class="flex flex-col items-center gap-0.5 rounded-lg border px-1 py-2 text-center transition-colors"
                :class="[
                    day.date === timesheet.selected_date
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'border-border bg-card hover:bg-muted',
                    day.is_today && day.date !== timesheet.selected_date
                        ? 'border-primary/60'
                        : '',
                ]"
                @click="visitDate(day.date)"
            >
                <span
                    class="text-[11px] font-medium tracking-wide uppercase"
                    :class="
                        day.date === timesheet.selected_date
                            ? 'text-primary-foreground/80'
                            : 'text-muted-foreground'
                    "
                >
                    {{ day.weekday }}
                </span>
                <span class="text-base leading-tight font-semibold">
                    {{ day.day_of_month }}
                </span>
                <span
                    class="text-xs tabular-nums"
                    :class="
                        day.date === timesheet.selected_date
                            ? 'text-primary-foreground/80'
                            : day.entries_count > 0
                              ? 'text-foreground'
                              : 'text-muted-foreground/60'
                    "
                >
                    {{ formatHours(day.total_hours) }}
                </span>
            </button>
        </div>
    </div>
</template>
