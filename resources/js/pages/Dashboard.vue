<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, Clock, Timer } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/dashboard/StatCard.vue';
import WeeklyHoursChart from '@/components/dashboard/WeeklyHoursChart.vue';
import { Card } from '@/components/ui/card';
import { formatHours } from '@/lib/utils';
import { dashboard } from '@/routes';
import projects from '@/routes/projects';
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

// -- Running timer ---------------------------------------------------------

const loadedAt = Date.now();
const now = ref(Date.now());
let ticker: ReturnType<typeof setInterval> | undefined;

onMounted(() => {
    ticker = setInterval(() => {
        now.value = Date.now();
    }, 1000);
});

onUnmounted(() => {
    if (ticker !== undefined) {
        clearInterval(ticker);
    }
});

const runningElapsed = computed(() => {
    const timer = props.statistics.running_timer;

    if (!timer) {
        return '';
    }

    const baseSeconds = (Number.parseFloat(timer.hours) || 0) * 3600;
    const total = Math.max(
        0,
        Math.round(baseSeconds + (now.value - loadedAt) / 1000),
    );
    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    const seconds = total % 60;

    return `${hours}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
});

// -- Statistics ------------------------------------------------------------

const billableRatio = computed(() => {
    const ratio = props.statistics.billable_ratio;

    return ratio === null ? null : `${ratio.toFixed(1).replace('.', ',')}%`;
});

const billableDelta = computed(() => {
    const delta = props.statistics.billable_ratio_delta;

    if (delta === null) {
        return null;
    }

    const sign = delta > 0 ? '+' : delta < 0 ? '-' : '';

    return {
        text: `${sign}${Math.abs(delta).toFixed(1).replace('.', ',')} pts vs. last month`,
        class:
            delta > 0
                ? 'text-emerald-700'
                : delta < 0
                  ? 'text-rose-700'
                  : 'text-muted-foreground',
    };
});

// -- Lists -----------------------------------------------------------------

const dayFormatter = new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
});

function formatDay(date: string): string {
    return dayFormatter.format(new Date(`${date}T00:00:00`));
}

function entryDetail(entry: TimeEntryData): string {
    return entry.notes ?? '';
}
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <PageHeader
            title="Dashboard"
            subtitle="Your hours at a glance: today, this week and this month."
        />

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                v-for="stat in stats.items"
                :key="stat.label"
                :stat="stat"
            />
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <Card class="gap-0 py-0 shadow-none lg:col-span-2">
                <div
                    class="flex flex-wrap items-center justify-between gap-3 px-5 pt-5 pb-3"
                >
                    <div>
                        <h2 class="font-semibold">Weekly hours</h2>
                        <p class="text-muted-foreground text-sm">
                            Hours logged per week, Monday to Sunday.
                        </p>
                    </div>
                    <div
                        class="bg-muted inline-flex rounded-lg p-0.5"
                        role="group"
                        aria-label="Chart range"
                    >
                        <button
                            v-for="range in ranges"
                            :key="range.value"
                            type="button"
                            class="rounded-md px-3 py-1 text-xs font-medium transition-colors"
                            :class="
                                range.value === chart.range
                                    ? 'bg-card text-foreground border-border border'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            :aria-pressed="range.value === chart.range"
                            :disabled="loadingRange !== null"
                            @click="selectRange(range.value)"
                        >
                            {{ range.label }}
                        </button>
                    </div>
                </div>
                <div
                    class="px-5 pb-5 transition-opacity"
                    :class="{ 'opacity-60': loadingRange !== null }"
                >
                    <WeeklyHoursChart
                        :labels="chart.labels"
                        :values="chart.values"
                    />
                </div>
            </Card>

            <Card class="gap-0 py-0 shadow-none">
                <div class="px-5 pt-5 pb-2">
                    <h2 class="font-semibold">Statistics</h2>
                    <p class="text-muted-foreground text-sm">This month</p>
                </div>
                <div class="flex flex-1 flex-col px-5 pb-5">
                    <div class="py-4">
                        <div class="text-muted-foreground text-sm">
                            Billable ratio
                        </div>
                        <div
                            class="mt-1 text-2xl font-semibold tracking-tight tabular-nums"
                        >
                            {{ billableRatio ?? '0,0%' }}
                        </div>
                        <div
                            v-if="billableDelta"
                            class="mt-0.5 text-xs font-medium"
                            :class="billableDelta.class"
                        >
                            {{ billableDelta.text }}
                        </div>
                        <div
                            v-else
                            class="text-muted-foreground mt-0.5 text-xs"
                        >
                            {{
                                billableRatio === null
                                    ? 'No hours logged yet'
                                    : 'No hours last month'
                            }}
                        </div>
                    </div>

                    <div class="border-border border-t border-dashed py-4">
                        <div class="text-muted-foreground text-sm">
                            Average per working day
                        </div>
                        <div
                            class="mt-1 flex items-baseline gap-1 text-2xl font-semibold tracking-tight tabular-nums"
                        >
                            {{
                                formatHours(
                                    statistics.average_hours_per_working_day,
                                )
                            }}
                            <span class="text-muted-foreground text-sm">h</span>
                        </div>
                        <div class="text-muted-foreground mt-0.5 text-xs">
                            {{ statistics.working_days_this_month }}
                            working
                            {{
                                statistics.working_days_this_month === 1
                                    ? 'day'
                                    : 'days'
                            }}
                            so far
                        </div>
                    </div>

                    <div class="border-border border-t border-dashed pt-4">
                        <div class="text-muted-foreground text-sm">
                            Running timer
                        </div>
                        <template v-if="statistics.running_timer">
                            <div class="mt-1 flex items-center gap-2">
                                <span class="relative flex size-2.5">
                                    <span
                                        class="bg-primary absolute inline-flex size-full animate-ping rounded-full opacity-60"
                                    />
                                    <span
                                        class="bg-primary relative inline-flex size-2.5 rounded-full"
                                    />
                                </span>
                                <span class="truncate font-medium">
                                    {{
                                        statistics.running_timer.project_name ??
                                        'Project'
                                    }}
                                </span>
                            </div>
                            <div
                                class="mt-1 text-2xl font-semibold tracking-tight tabular-nums"
                            >
                                {{ runningElapsed }}
                            </div>
                            <div
                                v-if="entryDetail(statistics.running_timer)"
                                class="text-muted-foreground mt-0.5 truncate text-xs"
                            >
                                {{ entryDetail(statistics.running_timer) }}
                            </div>
                        </template>
                        <div
                            v-else
                            class="text-muted-foreground mt-2 flex items-center gap-2 text-sm"
                        >
                            <Timer class="size-4" />
                            No timer running
                        </div>
                    </div>
                </div>
            </Card>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <Card class="gap-0 py-0 shadow-none">
                <div class="px-5 pt-5 pb-2">
                    <h2 class="font-semibold">Recent entries</h2>
                    <p class="text-muted-foreground text-sm">
                        The last six things you logged.
                    </p>
                </div>
                <div class="flex-1 px-5 pb-4">
                    <EmptyState v-if="recent_entries.length === 0">
                        No time entries yet.
                    </EmptyState>
                    <ul v-else class="divide-border divide-y">
                        <li
                            v-for="entry in recent_entries"
                            :key="entry.id ?? entry.spent_on"
                            class="flex items-center gap-3 py-2.5"
                        >
                            <span
                                class="size-2.5 shrink-0 rounded-full"
                                :style="{
                                    backgroundColor:
                                        entry.project_color ?? 'var(--primary)',
                                }"
                            />
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-medium">
                                    {{ entry.project_name ?? 'Project' }}
                                </div>
                                <div
                                    v-if="entryDetail(entry)"
                                    class="text-muted-foreground truncate text-xs"
                                >
                                    {{ entryDetail(entry) }}
                                </div>
                            </div>
                            <div class="shrink-0 text-right">
                                <div class="text-sm font-medium tabular-nums">
                                    {{ formatHours(entry.hours) }} h
                                </div>
                                <div
                                    class="text-muted-foreground flex items-center justify-end gap-1 text-xs"
                                >
                                    <Clock class="size-3" />
                                    {{ formatDay(entry.spent_on) }}
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>
                <div class="border-border border-t px-5 py-3">
                    <Link
                        :href="timeEntries.index()"
                        class="text-primary inline-flex items-center gap-1 text-sm font-medium hover:underline"
                    >
                        View all
                        <ArrowRight class="size-3.5" />
                    </Link>
                </div>
            </Card>

            <Card class="gap-0 py-0 shadow-none">
                <div class="px-5 pt-5 pb-2">
                    <h2 class="font-semibold">Top projects this month</h2>
                    <p class="text-muted-foreground text-sm">
                        Where the hours went, by share of the month.
                    </p>
                </div>
                <div class="flex-1 px-5 pb-4">
                    <EmptyState v-if="top_projects.length === 0">
                        No hours logged this month yet.
                    </EmptyState>
                    <ul v-else class="divide-border divide-y">
                        <li
                            v-for="project in top_projects"
                            :key="project.id"
                            class="py-2.5"
                        >
                            <div class="flex items-center gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-medium">
                                        {{ project.name }}
                                    </div>
                                    <div
                                        class="text-muted-foreground truncate text-xs"
                                    >
                                        {{ project.client_name }}
                                    </div>
                                </div>
                                <div class="shrink-0 text-right">
                                    <div
                                        class="text-sm font-medium tabular-nums"
                                    >
                                        {{ formatHours(project.hours) }} h
                                    </div>
                                    <div
                                        class="text-muted-foreground text-xs tabular-nums"
                                    >
                                        {{
                                            project.percent_of_month
                                                .toFixed(1)
                                                .replace('.', ',')
                                        }}%
                                    </div>
                                </div>
                            </div>
                            <div
                                class="bg-muted mt-2 h-1.5 w-full overflow-hidden rounded-full"
                            >
                                <div
                                    class="h-full rounded-full"
                                    :style="{
                                        width: `${Math.min(100, project.percent_of_month)}%`,
                                        backgroundColor:
                                            project.color ?? 'var(--primary)',
                                    }"
                                />
                            </div>
                        </li>
                    </ul>
                </div>
                <div class="border-border border-t px-5 py-3">
                    <Link
                        :href="projects.index()"
                        class="text-primary inline-flex items-center gap-1 text-sm font-medium hover:underline"
                    >
                        View all
                        <ArrowRight class="size-3.5" />
                    </Link>
                </div>
            </Card>
        </div>
    </div>
</template>
