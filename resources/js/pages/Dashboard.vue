<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CardMenu from '@/components/dashboard/CardMenu.vue';
import StatCard from '@/components/dashboard/StatCard.vue';
import WeeklyHoursChart from '@/components/dashboard/WeeklyHoursChart.vue';
import EmptyState from '@/components/EmptyState.vue';
import FaIcon from '@/components/FaIcon.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { useTimerElapsed } from '@/composables/useTimerElapsed';
import { formatHours } from '@/lib/utils';
import { dashboard } from '@/routes';
import projects from '@/routes/projects';
import reports from '@/routes/reports';
import timeEntries from '@/routes/time-entries';
import type {
    DashboardChartData,
    DashboardProjectSummaryData,
    DashboardStatisticsData,
    DashboardStatsData,
    TimeEntryData,
} from '@/types/generated';

const props = defineProps<{
    stats: DashboardStatsData;
    chart: DashboardChartData;
    statistics: DashboardStatisticsData;
    recent_entries: TimeEntryData[];
    top_projects: DashboardProjectSummaryData[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

const today = new Intl.DateTimeFormat('en-US', {
    weekday: 'long',
    month: 'short',
    day: 'numeric',
}).format(new Date());

// -- Chart range -----------------------------------------------------------

const ranges = [
    { value: '3m', label: '3M' },
    { value: '6m', label: '6M' },
    { value: '1y', label: '1Y' },
] as const;

const loadingRange = ref<string | null>(null);

function selectRange(range: string) {
    if (range === props.chart.range || loadingRange.value !== null) {
        return;
    }

    loadingRange.value = range;

    router.reload({
        data: { range },
        only: ['chart'],
        onFinish: () => {
            loadingRange.value = null;
        },
    });
}

const chartMenu = [
    { title: 'Time entries', href: timeEntries.index(), icon: 'clock' },
    { title: 'Reports', href: reports.index(), icon: 'chart-simple' },
];

// -- Statistics ------------------------------------------------------------

const { formatted: runningElapsed } = useTimerElapsed(
    () => props.statistics.running_timer?.timer_started_at,
);

const billableRatio = computed(() => {
    const ratio = props.statistics.billable_ratio;

    return ratio === null ? null : `${ratio.toFixed(1)}%`;
});

const billableDelta = computed(() => {
    const delta = props.statistics.billable_ratio_delta;

    if (delta === null) {
        return null;
    }

    const sign = delta > 0 ? '+' : delta < 0 ? '-' : '';

    return {
        text: `${sign}${Math.abs(delta).toFixed(1)} pts`,
        class:
            delta > 0
                ? 'text-emerald-600'
                : delta < 0
                  ? 'text-rose-600'
                  : 'text-muted-foreground',
    };
});

const workingDays = computed(() => {
    const days = props.statistics.working_days_this_month;

    return `${days} working ${days === 1 ? 'day' : 'days'} so far`;
});

// -- Lists -----------------------------------------------------------------

const dayFormatter = new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
});

function formatDay(date: string): string {
    return dayFormatter.format(new Date(`${date}T00:00:00`));
}

function initials(name: string | null | undefined): string {
    return (name ?? 'P')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0]?.toUpperCase() ?? '')
        .join('');
}

/** Avatar bubble tinted with the project color. */
function bubbleStyle(color: string | null | undefined) {
    const base = color ?? 'var(--primary)';

    return {
        color: base,
        backgroundColor: `color-mix(in oklab, ${base} 14%, transparent)`,
    };
}

function formatPercent(value: number): string {
    return `${value.toFixed(1)}%`;
}
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-1 flex-col gap-5 p-4 md:p-6">
        <PageHeader title="Dashboard">
            <template #actions>
                <span
                    class="border-border bg-card text-foreground hidden h-9 items-center gap-2 rounded-lg border px-3 text-sm sm:inline-flex"
                >
                    <FaIcon icon="calendar" class="text-muted-foreground" />
                    {{ today }}
                </span>
                <Button variant="outline" class="rounded-lg" as-child>
                    <Link :href="reports.index()">
                        <FaIcon icon="chart-simple" class="text-sm" />
                        Reports
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <!-- Stat tiles share hairlines instead of gaps: a border-colored grid with 1px gaps. -->
        <div
            class="bg-border border-border grid gap-px overflow-hidden rounded-xl border sm:grid-cols-2 xl:grid-cols-4"
        >
            <StatCard
                v-for="stat in stats.items"
                :key="stat.label"
                :stat="stat"
            />
        </div>

        <div
            class="bg-border border-border grid gap-px overflow-hidden rounded-xl border lg:grid-cols-3"
        >
            <section class="bg-card flex flex-col lg:col-span-2">
                <div
                    class="flex flex-wrap items-center justify-between gap-3 px-5 pt-5 pb-2"
                >
                    <h2 class="text-lg font-semibold tracking-tight">
                        Weekly hours
                    </h2>
                    <div class="flex items-center gap-2">
                        <div
                            class="bg-muted inline-flex rounded-lg p-1"
                            role="group"
                            aria-label="Chart range"
                        >
                            <button
                                v-for="range in ranges"
                                :key="range.value"
                                type="button"
                                class="rounded-md px-3 py-1 text-sm font-medium transition-colors"
                                :class="
                                    range.value === chart.range
                                        ? 'bg-card text-foreground border-border border shadow-xs'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                :aria-pressed="range.value === chart.range"
                                :disabled="loadingRange !== null"
                                @click="selectRange(range.value)"
                            >
                                {{ range.label }}
                            </button>
                        </div>
                        <CardMenu :items="chartMenu" />
                    </div>
                </div>
                <div
                    class="px-5 pt-3 pb-5 transition-opacity"
                    :class="{ 'opacity-60': loadingRange !== null }"
                >
                    <WeeklyHoursChart
                        :labels="chart.labels"
                        :values="chart.values"
                    />
                </div>
            </section>

            <section class="bg-card flex flex-col">
                <div
                    class="flex items-center justify-between gap-3 px-5 pt-5 pb-2"
                >
                    <h2 class="text-lg font-semibold tracking-tight">
                        Statistics
                    </h2>
                    <CardMenu :items="chartMenu" />
                </div>
                <div class="flex flex-1 flex-col px-5 pb-5">
                    <div class="py-4">
                        <div class="text-foreground/80 text-[15px]">
                            Billable ratio
                        </div>
                        <div
                            class="mt-1.5 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1"
                        >
                            <span
                                class="text-[32px] leading-none font-semibold tracking-tight tabular-nums"
                            >
                                {{ billableRatio ?? '0.0%' }}
                            </span>
                            <span v-if="billableDelta" class="text-[15px]">
                                <span
                                    class="font-medium"
                                    :class="billableDelta.class"
                                >
                                    {{ billableDelta.text }}
                                </span>
                                <span class="text-muted-foreground">
                                    vs. last month
                                </span>
                            </span>
                            <span
                                v-else
                                class="text-muted-foreground text-[15px]"
                            >
                                {{
                                    billableRatio === null
                                        ? 'No hours logged yet'
                                        : 'No hours last month'
                                }}
                            </span>
                        </div>
                    </div>

                    <div class="border-border border-t border-dashed py-4">
                        <div class="text-foreground/80 text-[15px]">
                            Average per working day
                        </div>
                        <div
                            class="mt-1.5 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1"
                        >
                            <span
                                class="text-[32px] leading-none font-semibold tracking-tight tabular-nums"
                            >
                                {{
                                    formatHours(
                                        statistics.average_hours_per_working_day,
                                    )
                                }}<span
                                    class="text-muted-foreground ml-1 text-lg font-medium"
                                    >h</span
                                >
                            </span>
                            <span class="text-muted-foreground text-[15px]">
                                {{ workingDays }}
                            </span>
                        </div>
                    </div>

                    <div class="border-border border-t border-dashed pt-4">
                        <div class="text-foreground/80 text-[15px]">
                            Running timer
                        </div>
                        <div
                            class="mt-1.5 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1"
                        >
                            <span
                                class="text-[32px] leading-none font-semibold tracking-tight tabular-nums"
                                :class="{
                                    'text-muted-foreground/60':
                                        !statistics.running_timer,
                                }"
                            >
                                {{
                                    statistics.running_timer
                                        ? runningElapsed
                                        : '0:00:00'
                                }}
                            </span>
                            <span
                                v-if="statistics.running_timer"
                                class="text-muted-foreground inline-flex min-w-0 items-center gap-2 text-[15px]"
                            >
                                <span class="relative flex size-2.5 shrink-0">
                                    <span
                                        class="bg-primary absolute inline-flex size-full animate-ping rounded-full opacity-60"
                                    />
                                    <span
                                        class="bg-primary relative inline-flex size-2.5 rounded-full"
                                    />
                                </span>
                                <span class="truncate">
                                    {{
                                        statistics.running_timer.project_name ??
                                        'Project'
                                    }}
                                </span>
                            </span>
                            <span
                                v-else
                                class="text-muted-foreground text-[15px]"
                            >
                                No timer running
                            </span>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
            <Card class="gap-0 py-0 shadow-none">
                <div
                    class="flex items-center justify-between gap-3 px-5 pt-5 pb-4"
                >
                    <h2 class="text-lg font-semibold tracking-tight">
                        Recent entries
                    </h2>
                    <CardMenu
                        :items="[
                            {
                                title: 'Time entries',
                                href: timeEntries.index(),
                                icon: 'clock',
                            },
                            {
                                title: 'Log time',
                                href: timeEntries.create(),
                                icon: 'plus',
                            },
                        ]"
                    />
                </div>
                <div class="border-border flex-1 border-t border-dashed px-5">
                    <EmptyState v-if="recent_entries.length === 0" class="my-5">
                        No time entries yet.
                    </EmptyState>
                    <ul v-else class="py-2">
                        <li
                            v-for="entry in recent_entries"
                            :key="entry.id ?? entry.spent_on"
                            class="flex items-center gap-4 py-3"
                        >
                            <span
                                class="flex size-11 shrink-0 items-center justify-center rounded-full text-sm font-semibold"
                                :style="bubbleStyle(entry.project_color)"
                            >
                                {{ initials(entry.project_name) }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-[15px] font-medium">
                                    {{ entry.project_name ?? 'Project' }}
                                </div>
                                <div
                                    class="text-muted-foreground truncate text-sm"
                                >
                                    {{ entry.notes || entry.client_name || '' }}
                                </div>
                            </div>
                            <div
                                class="text-muted-foreground inline-flex shrink-0 items-center gap-2 text-[15px] tabular-nums"
                            >
                                <FaIcon icon="clock" class="text-sm" />
                                <span class="text-foreground font-medium">
                                    {{ formatHours(entry.hours) }} h
                                </span>
                                <span class="hidden sm:inline">
                                    · {{ formatDay(entry.spent_on) }}
                                </span>
                            </div>
                        </li>
                    </ul>
                </div>
                <div
                    class="border-border flex items-center justify-between gap-4 border-t border-dashed px-5 py-4"
                >
                    <p class="text-muted-foreground text-[15px]">
                        The last six things you logged.
                    </p>
                    <Button variant="outline" class="rounded-lg" as-child>
                        <Link :href="timeEntries.index()">View all</Link>
                    </Button>
                </div>
            </Card>

            <Card class="gap-0 py-0 shadow-none">
                <div
                    class="flex items-center justify-between gap-3 px-5 pt-5 pb-4"
                >
                    <h2 class="text-lg font-semibold tracking-tight">
                        Top projects this month
                    </h2>
                    <CardMenu
                        :items="[
                            {
                                title: 'Projects',
                                href: projects.index(),
                                icon: 'briefcase',
                            },
                            {
                                title: 'Reports',
                                href: reports.index(),
                                icon: 'chart-simple',
                            },
                        ]"
                    />
                </div>
                <div class="border-border flex-1 border-t border-dashed px-5">
                    <EmptyState v-if="top_projects.length === 0" class="my-5">
                        No hours logged this month yet.
                    </EmptyState>
                    <ul v-else class="py-2">
                        <li
                            v-for="project in top_projects"
                            :key="project.id"
                            class="flex items-center gap-4 py-3"
                        >
                            <span
                                class="flex size-11 shrink-0 items-center justify-center rounded-full text-sm font-semibold"
                                :style="bubbleStyle(project.color)"
                            >
                                {{ initials(project.name) }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-[15px] font-medium">
                                    {{ project.name }}
                                </div>
                                <div
                                    class="text-muted-foreground truncate text-sm"
                                >
                                    {{ project.client_name }}
                                </div>
                            </div>
                            <div
                                class="text-muted-foreground inline-flex shrink-0 items-center gap-2 text-[15px] tabular-nums"
                            >
                                <FaIcon icon="clock" class="text-sm" />
                                <span class="text-foreground font-medium">
                                    {{ formatHours(project.hours) }} h
                                </span>
                                <span class="hidden sm:inline">
                                    ·
                                    {{
                                        formatPercent(project.percent_of_month)
                                    }}
                                </span>
                            </div>
                        </li>
                    </ul>
                </div>
                <div
                    class="border-border flex items-center justify-between gap-4 border-t border-dashed px-5 py-4"
                >
                    <p class="text-muted-foreground text-[15px]">
                        Where the hours went, by share of the month.
                    </p>
                    <Button variant="outline" class="rounded-lg" as-child>
                        <Link :href="projects.index()">View all</Link>
                    </Button>
                </div>
            </Card>
        </div>
    </div>
</template>
