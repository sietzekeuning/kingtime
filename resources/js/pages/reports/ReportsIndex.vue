<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import dayjs from 'dayjs';
import debounce from 'lodash/debounce';
import { computed, ref, watch } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatCard from '@/components/dashboard/StatCard.vue';
import ReportBarChart from '@/components/reports/ReportBarChart.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatEuro } from '@/lib/formatters';
import { formatHours } from '@/lib/utils';
import reportsRoute from '@/routes/reports';
import type {
    DashboardStatData,
    ReportBreakdownRowData,
    ReportsData,
    ReportsFilterData,
} from '@/types/generated';

interface ClientOption {
    id: number;
    name: string;
}

interface ProjectOption {
    id: number;
    name: string;
    client_id: number;
}

type Granularity = ReportsFilterData['granularity'];

const props = defineProps<{
    reports: ReportsData;
    filter: ReportsFilterData;
    clients: ClientOption[];
    projects: ProjectOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Reports', href: reportsRoute.index() }],
    },
});

// -- Filter ----------------------------------------------------------------

const granularities: { value: Granularity; label: string }[] = [
    { value: 'week', label: 'Week' },
    { value: 'month', label: 'Month' },
    { value: 'year', label: 'Year' },
];

const ALL = 'all';

const granularity = ref<Granularity>(props.filter.granularity);
const from = ref<string | null>(props.reports.from);
const to = ref<string | null>(props.reports.to);
const clientId = ref<string>(
    props.filter.client_id === null ? ALL : String(props.filter.client_id),
);
const projectId = ref<string>(
    props.filter.project_id === null ? ALL : String(props.filter.project_id),
);
const billableOnly = ref<boolean>(props.filter.billable_only);
const loading = ref(false);

const projectOptions = computed(() =>
    clientId.value === ALL
        ? props.projects
        : props.projects.filter(
              (project) => String(project.client_id) === clientId.value,
          ),
);

const periodNoun = computed(() =>
    granularity.value === 'week'
        ? 'week'
        : granularity.value === 'year'
          ? 'year'
          : 'month',
);

const reload = debounce(() => {
    loading.value = true;

    router.get(
        reportsRoute.index(),
        {
            granularity: granularity.value,
            from: from.value || undefined,
            to: to.value || undefined,
            client_id: clientId.value === ALL ? undefined : clientId.value,
            project_id: projectId.value === ALL ? undefined : projectId.value,
            billable_only: billableOnly.value ? 1 : undefined,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onSuccess: () => {
                from.value = props.reports.from;
                to.value = props.reports.to;
            },
            onFinish: () => {
                loading.value = false;
            },
        },
    );
}, 250);

/** Switching the period lets the server pick the range that suits it. */
function selectGranularity(value: Granularity) {
    if (value === granularity.value) {
        return;
    }

    granularity.value = value;
    from.value = null;
    to.value = null;
    reload();
}

watch(clientId, () => {
    const stillVisible = projectOptions.value.some(
        (project) => String(project.id) === projectId.value,
    );

    if (!stillVisible) {
        projectId.value = ALL;
    }
});

watch([from, to, clientId, projectId, billableOnly], () => reload());

function setRange(start: dayjs.Dayjs, end: dayjs.Dayjs) {
    from.value = start.format('YYYY-MM-DD');
    to.value = end.format('YYYY-MM-DD');
}

/** Monday of the week that holds `date` (ISO weeks, no plugin needed). */
function startOfIsoWeek(date: dayjs.Dayjs): dayjs.Dayjs {
    const offset = (date.day() + 6) % 7;

    return date.subtract(offset, 'day').startOf('day');
}

const presets = [
    {
        label: 'This year',
        apply: () => setRange(dayjs().startOf('year'), dayjs().endOf('year')),
    },
    {
        label: 'Last year',
        apply: () => {
            const year = dayjs().subtract(1, 'year');
            setRange(year.startOf('year'), year.endOf('year'));
        },
    },
    {
        label: 'Last 12 months',
        apply: () =>
            setRange(
                dayjs().subtract(11, 'month').startOf('month'),
                dayjs().endOf('month'),
            ),
    },
    {
        label: 'Last 26 weeks',
        apply: () => {
            const monday = startOfIsoWeek(dayjs());
            setRange(monday.subtract(25, 'week'), monday.add(6, 'day'));
        },
    },
    {
        label: 'All time',
        apply: () =>
            setRange(
                dayjs(
                    props.reports.earliest_entry_on ?? dayjs().startOf('year'),
                ),
                dayjs().endOf('year'),
            ),
    },
];

// -- Tiles -----------------------------------------------------------------

const tiles = computed<DashboardStatData[]>(() => {
    const totals = props.reports.totals;
    const labels = {
        current_label: 'This range',
        previous_label: 'Previous range',
    };

    return [
        {
            label: 'Hours',
            value: totals.hours,
            previous_value: totals.previous_hours,
            delta_percent: totals.hours_delta_percent,
            format: 'hours',
            ...labels,
        },
        {
            label: 'Billable hours',
            value: totals.billable_hours,
            previous_value: totals.previous_billable_hours,
            delta_percent: totals.billable_hours_delta_percent,
            format: 'hours',
            ...labels,
        },
        {
            label: 'Earned',
            value: totals.earned,
            previous_value: totals.previous_earned,
            delta_percent: totals.earned_delta_percent,
            format: 'euro',
            ...labels,
        },
        {
            label: 'Invoiced',
            value: totals.invoiced,
            previous_value: totals.previous_invoiced,
            delta_percent: totals.invoiced_delta_percent,
            format: 'euro',
            ...labels,
        },
    ];
});

// -- Charts ----------------------------------------------------------------

const labels = computed(() =>
    props.reports.buckets.map((bucket) => bucket.label),
);

function series(key: 'hours' | 'billable_hours' | 'earned' | 'invoiced') {
    return props.reports.buckets.map(
        (bucket) => Number.parseFloat(bucket[key]) || 0,
    );
}

const hoursDatasets = computed(() => [
    { label: 'Hours', data: series('hours'), color: 'primary' as const },
    {
        label: 'Billable hours',
        data: series('billable_hours'),
        color: 'amber' as const,
    },
]);

const moneyDatasets = computed(() => [
    { label: 'Earned', data: series('earned'), color: 'primary' as const },
    { label: 'Invoiced', data: series('invoiced'), color: 'amber' as const },
]);

const hasHours = computed(() =>
    props.reports.buckets.some((bucket) => Number.parseFloat(bucket.hours) > 0),
);

// -- Breakdowns ------------------------------------------------------------

function share(row: ReportBreakdownRowData): string {
    return `${row.share_percent.toFixed(1).replace('.', ',')}%`;
}
</script>

<template>
    <Head title="Reports" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <PageHeader
            title="Reports"
            subtitle="Hours worked and money earned per week, month and year."
        >
            <template #actions>
                <div
                    class="bg-muted inline-flex rounded-lg p-0.5"
                    role="group"
                    aria-label="Period"
                >
                    <button
                        v-for="option in granularities"
                        :key="option.value"
                        type="button"
                        class="rounded-md px-3 py-1 text-xs font-medium transition-colors"
                        :class="
                            option.value === granularity
                                ? 'bg-card text-foreground border-border border'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        :aria-pressed="option.value === granularity"
                        @click="selectGranularity(option.value)"
                    >
                        {{ option.label }}
                    </button>
                </div>
            </template>
        </PageHeader>

        <Card class="gap-0 py-0 shadow-none">
            <div class="flex flex-col gap-4 p-5">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <div class="grid gap-1.5">
                        <Label for="reports-from">From</Label>
                        <Input id="reports-from" v-model="from" type="date" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="reports-to">To</Label>
                        <Input id="reports-to" v-model="to" type="date" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Client</Label>
                        <Select v-model="clientId">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="All clients" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="ALL"
                                    >All clients</SelectItem
                                >
                                <SelectItem
                                    v-for="client in clients"
                                    :key="client.id"
                                    :value="String(client.id)"
                                >
                                    {{ client.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Project</Label>
                        <Select v-model="projectId">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="All projects" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="ALL">
                                    All projects
                                </SelectItem>
                                <SelectItem
                                    v-for="project in projectOptions"
                                    :key="project.id"
                                    :value="String(project.id)"
                                >
                                    {{ project.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="reports-billable">Billable only</Label>
                        <div class="flex h-9 items-center gap-2">
                            <Switch
                                id="reports-billable"
                                v-model="billableOnly"
                            />
                            <span class="text-muted-foreground text-sm">
                                Leave out non-billable hours
                            </span>
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-muted-foreground text-xs">Presets</span>
                    <Button
                        v-for="preset in presets"
                        :key="preset.label"
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="preset.apply()"
                    >
                        {{ preset.label }}
                    </Button>
                </div>
            </div>
        </Card>

        <div
            class="flex flex-col gap-4 transition-opacity"
            :class="{ 'opacity-60': loading }"
        >
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    v-for="tile in tiles"
                    :key="tile.label"
                    :stat="tile"
                />
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <Card class="gap-0 py-0 shadow-none">
                    <div class="px-5 pt-5 pb-3">
                        <h2 class="font-semibold">
                            Hours per {{ periodNoun }}
                        </h2>
                        <p class="text-muted-foreground text-sm">
                            All hours next to the billable ones.
                        </p>
                    </div>
                    <div class="px-5 pb-5">
                        <ReportBarChart
                            :labels="labels"
                            :datasets="hoursDatasets"
                            format="hours"
                        />
                    </div>
                </Card>

                <Card class="gap-0 py-0 shadow-none">
                    <div class="px-5 pt-5 pb-3">
                        <h2 class="font-semibold">
                            Earned per {{ periodNoun }}
                        </h2>
                        <p class="text-muted-foreground text-sm">
                            Billable value of the work, and what is on an
                            invoice.
                        </p>
                    </div>
                    <div class="px-5 pb-5">
                        <ReportBarChart
                            :labels="labels"
                            :datasets="moneyDatasets"
                            format="euro"
                        />
                    </div>
                </Card>
            </div>

            <Card class="gap-0 py-0 shadow-none">
                <div class="px-5 pt-5 pb-3">
                    <h2 class="font-semibold">Per {{ periodNoun }}</h2>
                    <p class="text-muted-foreground text-sm">
                        {{ reports.from }} to {{ reports.to }}, compared with
                        {{ reports.previous_from }} to
                        {{ reports.previous_to }}.
                    </p>
                </div>
                <div class="px-5 pb-5">
                    <EmptyState v-if="!hasHours">
                        No hours in this range.
                    </EmptyState>
                    <div v-else class="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Period</TableHead>
                                    <TableHead class="text-right">
                                        Hours
                                    </TableHead>
                                    <TableHead class="text-right">
                                        Billable hours
                                    </TableHead>
                                    <TableHead class="text-right">
                                        Earned
                                    </TableHead>
                                    <TableHead class="text-right">
                                        Invoiced
                                    </TableHead>
                                    <TableHead class="text-right">
                                        Entries
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="bucket in reports.buckets"
                                    :key="bucket.period"
                                >
                                    <TableCell class="font-medium">
                                        {{ bucket.label }}
                                    </TableCell>
                                    <TableCell class="text-right tabular-nums">
                                        {{ formatHours(bucket.hours) }}
                                    </TableCell>
                                    <TableCell class="text-right tabular-nums">
                                        {{ formatHours(bucket.billable_hours) }}
                                    </TableCell>
                                    <TableCell class="text-right tabular-nums">
                                        {{ formatEuro(bucket.earned) }}
                                    </TableCell>
                                    <TableCell class="text-right tabular-nums">
                                        {{ formatEuro(bucket.invoiced) }}
                                    </TableCell>
                                    <TableCell
                                        class="text-muted-foreground text-right tabular-nums"
                                    >
                                        {{ bucket.entry_count }}
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                            <TableFooter>
                                <TableRow>
                                    <TableCell class="font-semibold">
                                        Total
                                    </TableCell>
                                    <TableCell
                                        class="text-right font-semibold tabular-nums"
                                    >
                                        {{ formatHours(reports.totals.hours) }}
                                    </TableCell>
                                    <TableCell
                                        class="text-right font-semibold tabular-nums"
                                    >
                                        {{
                                            formatHours(
                                                reports.totals.billable_hours,
                                            )
                                        }}
                                    </TableCell>
                                    <TableCell
                                        class="text-right font-semibold tabular-nums"
                                    >
                                        {{ formatEuro(reports.totals.earned) }}
                                    </TableCell>
                                    <TableCell
                                        class="text-right font-semibold tabular-nums"
                                    >
                                        {{
                                            formatEuro(reports.totals.invoiced)
                                        }}
                                    </TableCell>
                                    <TableCell
                                        class="text-muted-foreground text-right font-semibold tabular-nums"
                                    >
                                        {{ reports.totals.entry_count }}
                                    </TableCell>
                                </TableRow>
                            </TableFooter>
                        </Table>
                    </div>
                </div>
            </Card>

            <div class="grid gap-4 lg:grid-cols-2">
                <Card class="gap-0 py-0 shadow-none">
                    <div class="px-5 pt-5 pb-2">
                        <h2 class="font-semibold">Per client</h2>
                        <p class="text-muted-foreground text-sm">
                            Share of the hours in this range.
                        </p>
                    </div>
                    <div class="flex-1 px-5 pb-4">
                        <EmptyState v-if="reports.clients.length === 0">
                            No hours in this range.
                        </EmptyState>
                        <ul v-else class="divide-border divide-y">
                            <li
                                v-for="row in reports.clients"
                                :key="row.id"
                                class="py-2.5"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="min-w-0 flex-1">
                                        <div
                                            class="truncate text-sm font-medium"
                                        >
                                            {{ row.name }}
                                        </div>
                                        <div
                                            class="text-muted-foreground truncate text-xs"
                                        >
                                            {{ row.entry_count }}
                                            {{
                                                row.entry_count === 1
                                                    ? 'entry'
                                                    : 'entries'
                                            }}
                                        </div>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <div
                                            class="text-sm font-medium tabular-nums"
                                        >
                                            {{ formatHours(row.hours) }} h
                                            <span
                                                class="text-muted-foreground font-normal"
                                            >
                                                · {{ formatEuro(row.earned) }}
                                            </span>
                                        </div>
                                        <div
                                            class="text-muted-foreground text-xs tabular-nums"
                                        >
                                            {{ share(row) }}
                                        </div>
                                    </div>
                                </div>
                                <div
                                    class="bg-muted mt-2 h-1.5 w-full overflow-hidden rounded-full"
                                >
                                    <div
                                        class="bg-primary h-full rounded-full"
                                        :style="{
                                            width: `${Math.min(100, row.share_percent)}%`,
                                        }"
                                    />
                                </div>
                            </li>
                        </ul>
                    </div>
                </Card>

                <Card class="gap-0 py-0 shadow-none">
                    <div class="px-5 pt-5 pb-2">
                        <h2 class="font-semibold">Per project</h2>
                        <p class="text-muted-foreground text-sm">
                            The fifteen projects with the most hours.
                        </p>
                    </div>
                    <div class="flex-1 px-5 pb-4">
                        <EmptyState v-if="reports.projects.length === 0">
                            No hours in this range.
                        </EmptyState>
                        <ul v-else class="divide-border divide-y">
                            <li
                                v-for="row in reports.projects"
                                :key="row.id"
                                class="py-2.5"
                            >
                                <div class="flex items-center gap-3">
                                    <span
                                        class="size-2.5 shrink-0 rounded-full"
                                        :style="{
                                            backgroundColor:
                                                row.color ?? 'var(--primary)',
                                        }"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <div
                                            class="truncate text-sm font-medium"
                                        >
                                            {{ row.name }}
                                        </div>
                                        <div
                                            class="text-muted-foreground truncate text-xs"
                                        >
                                            {{ row.client_name }}
                                        </div>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <div
                                            class="text-sm font-medium tabular-nums"
                                        >
                                            {{ formatHours(row.hours) }} h
                                            <span
                                                class="text-muted-foreground font-normal"
                                            >
                                                · {{ formatEuro(row.earned) }}
                                            </span>
                                        </div>
                                        <div
                                            class="text-muted-foreground text-xs tabular-nums"
                                        >
                                            {{ share(row) }}
                                        </div>
                                    </div>
                                </div>
                                <div
                                    class="bg-muted mt-2 h-1.5 w-full overflow-hidden rounded-full"
                                >
                                    <div
                                        class="h-full rounded-full"
                                        :style="{
                                            width: `${Math.min(100, row.share_percent)}%`,
                                            backgroundColor:
                                                row.color ?? 'var(--primary)',
                                        }"
                                    />
                                </div>
                            </li>
                        </ul>
                    </div>
                </Card>
            </div>
        </div>
    </div>
</template>
