<script setup lang="ts">
import { computed } from 'vue';
import dayjs from 'dayjs';
import PeriodNav from '@/components/time/PeriodNav.vue';
import { addMonths, formatDate } from '@/lib/time';
import { visitTimesheetDate } from '@/lib/timesheet';
import { formatHours } from '@/lib/utils';
import type { MonthOverviewData, TimesheetDayData } from '@/types/generated';

/**
 * A calendar of the month with the hours per day. Working days that have
 * nothing logged yet stand out, so a glance shows what is still missing.
 * Clicking a day opens it in the day view.
 */
const props = defineProps<{
    month: MonthOverviewData;
}>();

const emit = defineEmits<{
    selectDay: [date: string];
}>();

const previousMonth = () =>
    visitTimesheetDate(addMonths(props.month.month_start, -1));
const nextMonth = () =>
    visitTimesheetDate(addMonths(props.month.month_start, 1));
const thisMonth = () => visitTimesheetDate(null);

const weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

/** Empty cells before the first day, so the 1st lands on its weekday. */
const leadingBlanks = computed(
    () => (dayjs(props.month.month_start).day() + 6) % 7,
);

const isCurrentMonth = computed(() =>
    props.month.days.some((day) => day.is_today),
);

function isMissing(day: TimesheetDayData): boolean {
    return !day.is_weekend && !day.is_future && day.entries_count === 0;
}

function dayClass(day: TimesheetDayData): string {
    if (isMissing(day)) {
        return 'border-destructive/40 bg-destructive/5 text-destructive hover:bg-destructive/10';
    }

    if (day.is_weekend) {
        return 'border-border bg-muted/40 text-muted-foreground hover:bg-muted';
    }

    if (day.is_future) {
        return 'border-border bg-card text-muted-foreground hover:bg-muted';
    }

    return 'border-border bg-card hover:bg-muted';
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <PeriodNav
            :label="formatDate(month.month_start, 'MMMM YYYY')"
            :current-label="
                isCurrentMonth ? 'This month' : 'Return to this month'
            "
            previous-title="Previous month"
            next-title="Next month"
            @previous="previousMonth"
            @next="nextMonth"
            @current="thisMonth"
        >
            <div class="flex items-center gap-4 text-sm">
                <span>
                    <span class="text-muted-foreground">Booked</span>
                    <span class="ml-2 font-semibold tabular-nums">
                        {{ month.booked_days }} / {{ month.working_days }}
                    </span>
                    <span class="text-muted-foreground"> days</span>
                </span>
                <span
                    v-if="month.missing_days > 0"
                    class="text-destructive font-medium tabular-nums"
                >
                    {{ month.missing_days }} missing
                </span>
                <span>
                    <span class="text-muted-foreground">Month total</span>
                    <span class="ml-2 text-lg font-semibold tabular-nums">
                        {{ formatHours(month.total_hours) }}
                    </span>
                </span>
            </div>
        </PeriodNav>

        <div class="grid grid-cols-7 gap-1.5">
            <div
                v-for="weekday in weekdays"
                :key="weekday"
                class="text-muted-foreground pb-1 text-center text-[11px] font-medium tracking-wide uppercase"
            >
                {{ weekday }}
            </div>
            <div v-for="blank in leadingBlanks" :key="`blank-${blank}`" />
            <button
                v-for="day in month.days"
                :key="day.date"
                type="button"
                class="flex min-h-16 flex-col justify-between rounded-lg border p-2 text-left transition-colors"
                :class="[
                    dayClass(day),
                    day.is_today ? 'ring-primary ring-2 ring-offset-1' : '',
                ]"
                :title="`${formatDate(day.date, 'dddd D MMMM')} · ${formatHours(day.total_hours)} hours`"
                @click="emit('selectDay', day.date)"
            >
                <span class="text-xs font-medium">{{ day.day_of_month }}</span>
                <span
                    class="self-end text-sm tabular-nums"
                    :class="
                        day.entries_count > 0
                            ? 'font-semibold'
                            : 'text-muted-foreground/60'
                    "
                >
                    {{
                        day.entries_count > 0
                            ? formatHours(day.total_hours)
                            : '·'
                    }}
                </span>
            </button>
        </div>

        <div
            class="text-muted-foreground flex flex-wrap items-center gap-4 text-xs"
        >
            <span class="flex items-center gap-1.5">
                <span class="border-border bg-card size-3 rounded border" />
                Booked
            </span>
            <span class="flex items-center gap-1.5">
                <span
                    class="border-destructive/40 bg-destructive/5 size-3 rounded border"
                />
                Working day without hours
            </span>
            <span class="flex items-center gap-1.5">
                <span class="border-border bg-muted/40 size-3 rounded border" />
                Weekend
            </span>
        </div>
    </div>
</template>
