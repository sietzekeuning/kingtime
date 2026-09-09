<script setup lang="ts">
import PeriodNav from '@/components/time/PeriodNav.vue';
import { addDays, formatDate } from '@/lib/time';
import { visitTimesheetDate } from '@/lib/timesheet';
import { formatHours } from '@/lib/utils';
import type { TimesheetData } from '@/types/generated';

/**
 * Monday-to-Sunday strip with a total per day. Selecting a day (or moving a
 * week) reloads only the timesheet props and keeps the table filters in
 * the URL, so the "All entries" tab is unaffected.
 */
const props = defineProps<{
    timesheet: TimesheetData;
}>();

const previousWeek = () =>
    visitTimesheetDate(addDays(props.timesheet.week_start, -7));
const nextWeek = () =>
    visitTimesheetDate(addDays(props.timesheet.week_start, 7));
const today = () => visitTimesheetDate(null);
</script>

<template>
    <div class="flex flex-col gap-3">
        <PeriodNav
            :label="`${formatDate(timesheet.week_start, 'D MMM')} · ${formatDate(timesheet.week_end, 'D MMM YYYY')}`"
            current-label="Today"
            previous-title="Previous week"
            next-title="Next week"
            @previous="previousWeek"
            @next="nextWeek"
            @current="today"
        >
            <div class="text-sm">
                <span class="text-muted-foreground">Week total</span>
                <span class="ml-2 text-lg font-semibold tabular-nums">
                    {{ formatHours(timesheet.week_total) }}
                </span>
            </div>
        </PeriodNav>

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
                @click="visitTimesheetDate(day.date)"
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
