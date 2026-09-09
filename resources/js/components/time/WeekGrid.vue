<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Copy, Lock, Plus, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import AlertError from '@/components/AlertError.vue';
import PeriodNav from '@/components/time/PeriodNav.vue';
import WeekGridCell from '@/components/time/WeekGridCell.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useConfirmDelete } from '@/composables/useConfirmDelete';
import { addDays, formatDate } from '@/lib/time';
import { visitTimesheetDate } from '@/lib/timesheet';
import { formatHours } from '@/lib/utils';
import timesheetRoutes from '@/routes/timesheet';
import type {
    ProjectOptionData,
    TimesheetCellData,
    TimesheetData,
    TimesheetRowData,
} from '@/types/generated';

/**
 * Harvest-style week grid: one row per project, a cell per day. Rows come
 * from the entries of the week; "Add row" and "Copy from last week" add
 * empty rows on the client until a cell in them is saved.
 */
const props = defineProps<{
    timesheet: TimesheetData;
    projects: ProjectOptionData[];
}>();

const emit = defineEmits<{
    openDay: [date: string];
}>();

const { confirmDelete } = useConfirmDelete();

const previousWeek = () =>
    visitTimesheetDate(addDays(props.timesheet.week_start, -7));
const nextWeek = () =>
    visitTimesheetDate(addDays(props.timesheet.week_start, 7));
const thisWeek = () => visitTimesheetDate(null);

/** Projects added to the grid by hand, not (yet) backed by an entry. */
const extraProjectIds = ref<number[]>([]);

const serverProjectIds = computed(
    () => new Set(props.timesheet.rows.map((row) => row.project_id)),
);

// Once a saved cell brings the project into the server rows, drop the copy.
watch(serverProjectIds, (ids) => {
    extraProjectIds.value = extraProjectIds.value.filter((id) => !ids.has(id));
});

function emptyRow(project: ProjectOptionData): TimesheetRowData {
    return {
        project_id: project.id,
        project_name: project.name,
        project_code: project.code,
        project_color: project.color,
        client_name: project.client_name,
        total_hours: '0.00',
        is_locked: false,
        cells: props.timesheet.days.map((day): TimesheetCellData => ({
            date: day.date,
            hours: '0.00',
            entries_count: 0,
            entry_id: null,
            is_locked: false,
            is_running: false,
            notes: null,
        })),
    };
}

const rows = computed<TimesheetRowData[]>(() => [
    ...props.timesheet.rows,
    ...extraProjectIds.value
        .map((id) => props.projects.find((project) => project.id === id))
        .filter(
            (project): project is ProjectOptionData => project !== undefined,
        )
        .map(emptyRow),
]);

const rowProjectIds = computed(
    () => new Set(rows.value.map((row) => row.project_id)),
);

const addableProjects = computed(() =>
    props.projects.filter((project) => !rowProjectIds.value.has(project.id)),
);

const lastWeekProjects = computed(() =>
    addableProjects.value.filter((project) =>
        props.timesheet.previous_week_project_ids.includes(project.id),
    ),
);

const projectToAdd = ref<number | null>(null);

watch(projectToAdd, (id) => {
    if (id !== null) {
        extraProjectIds.value = [...extraProjectIds.value, id];
        projectToAdd.value = null;
    }
});

function copyLastWeek() {
    extraProjectIds.value = [
        ...extraProjectIds.value,
        ...lastWeekProjects.value.map((project) => project.id),
    ];
}

const savingKey = ref<string | null>(null);
const error = ref<string | null>(null);

function saveCell(
    row: TimesheetRowData,
    cell: TimesheetCellData,
    hours: number | null,
) {
    const key = `${row.project_id}:${cell.date}`;
    savingKey.value = key;
    error.value = null;

    router.post(
        timesheetRoutes.cells.store.url(),
        {
            project_id: row.project_id,
            spent_on: cell.date,
            hours: hours === null ? null : hours.toFixed(2),
        },
        {
            preserveState: true,
            preserveScroll: true,
            only: ['timesheet', 'month', 'errors'],
            onError: (errors) => {
                error.value = Object.values(errors).flat().join(' ');
            },
            onFinish: () => {
                if (savingKey.value === key) {
                    savingKey.value = null;
                }
            },
        },
    );
}

function removeRow(row: TimesheetRowData) {
    if (!serverProjectIds.value.has(row.project_id)) {
        extraProjectIds.value = extraProjectIds.value.filter(
            (id) => id !== row.project_id,
        );

        return;
    }

    void confirmDelete(
        timesheetRoutes.rows.destroy.url({
            query: {
                project_id: row.project_id,
                week_start: props.timesheet.week_start,
            },
        }),
        {
            title: `Remove ${row.project_name} from this week?`,
            description: `${formatHours(row.total_hours)} hours on ${row.project_name} are deleted. Billed and locked hours stay. This cannot be undone.`,
            confirmLabel: 'Remove',
        },
    );
}

const isCurrentWeek = computed(() =>
    props.timesheet.days.some((day) => day.is_today),
);
</script>

<template>
    <div class="flex flex-col gap-4">
        <PeriodNav
            :label="`${formatDate(timesheet.week_start, 'D MMM')} · ${formatDate(timesheet.week_end, 'D MMM YYYY')}`"
            :current-label="isCurrentWeek ? 'This week' : 'Return to this week'"
            previous-title="Previous week"
            next-title="Next week"
            @previous="previousWeek"
            @next="nextWeek"
            @current="thisWeek"
        >
            <div class="text-sm">
                <span class="text-muted-foreground">Week total</span>
                <span class="ml-2 text-lg font-semibold tabular-nums">
                    {{ formatHours(timesheet.week_total) }}
                </span>
            </div>
        </PeriodNav>

        <AlertError v-if="error" :errors="[error]" title="Not saved" />

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-border border-b">
                        <th class="pb-2 text-left font-normal" />
                        <th
                            v-for="day in timesheet.days"
                            :key="day.date"
                            class="px-1 pb-2 text-center font-normal"
                            :class="
                                day.is_today
                                    ? 'text-primary border-primary border-b-2'
                                    : day.is_weekend
                                      ? 'text-muted-foreground/70'
                                      : 'text-muted-foreground'
                            "
                        >
                            <div class="font-medium">{{ day.weekday }}</div>
                            <div class="text-xs">
                                {{ formatDate(day.date, 'D MMM') }}
                            </div>
                        </th>
                        <th
                            class="text-muted-foreground pb-2 pl-2 text-right font-normal"
                        >
                            Total
                        </th>
                        <th class="pb-2" />
                    </tr>
                </thead>
                <tbody class="divide-border divide-y">
                    <tr
                        v-for="row in rows"
                        :key="row.project_id"
                        :class="row.is_locked ? 'bg-muted/40' : ''"
                    >
                        <td class="py-2 pr-4">
                            <div class="flex items-center gap-2">
                                <span
                                    class="size-2.5 shrink-0 rounded-full"
                                    :style="{
                                        backgroundColor:
                                            row.project_color ??
                                            'var(--primary)',
                                    }"
                                />
                                <div class="min-w-0">
                                    <div class="truncate font-medium">
                                        {{ row.project_name }}
                                        <span
                                            v-if="row.project_code"
                                            class="text-muted-foreground text-xs font-normal"
                                        >
                                            {{ row.project_code }}
                                        </span>
                                    </div>
                                    <div
                                        class="text-muted-foreground truncate text-xs"
                                    >
                                        {{ row.client_name }}
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td
                            v-for="cell in row.cells"
                            :key="cell.date"
                            class="px-1 py-2 text-center"
                        >
                            <WeekGridCell
                                class="mx-auto"
                                :cell="cell"
                                :saving="
                                    savingKey ===
                                    `${row.project_id}:${cell.date}`
                                "
                                @save="saveCell(row, cell, $event)"
                                @open-day="emit('openDay', $event)"
                            />
                        </td>
                        <td
                            class="py-2 pl-2 text-right font-semibold tabular-nums"
                        >
                            {{ formatHours(row.total_hours) }}
                        </td>
                        <td class="py-2 pl-2 text-right">
                            <span
                                v-if="row.is_locked"
                                class="text-muted-foreground inline-flex size-8 items-center justify-center"
                                title="Billed or locked"
                            >
                                <Lock class="size-3.5" />
                            </span>
                            <Button
                                v-else
                                variant="ghost"
                                size="icon-sm"
                                class="text-muted-foreground hover:text-destructive"
                                title="Remove row"
                                @click="removeRow(row)"
                            >
                                <X class="size-4" />
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="rows.length === 0">
                        <td
                            :colspan="timesheet.days.length + 3"
                            class="text-muted-foreground py-8 text-center"
                        >
                            Nothing logged this week. Add a row to start.
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="border-border border-t">
                        <td class="pt-3" />
                        <td
                            v-for="day in timesheet.days"
                            :key="day.date"
                            class="px-1 pt-3 text-center font-semibold tabular-nums"
                            :class="
                                Number.parseFloat(day.total_hours) > 0
                                    ? ''
                                    : 'text-muted-foreground'
                            "
                        >
                            {{ formatHours(day.total_hours) }}
                        </td>
                        <td
                            class="pt-3 pl-2 text-right font-semibold tabular-nums"
                        >
                            {{ formatHours(timesheet.week_total) }}
                        </td>
                        <td class="pt-3" />
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <Select
                v-model="projectToAdd"
                :disabled="addableProjects.length === 0"
            >
                <SelectTrigger class="w-56">
                    <SelectValue placeholder="Add row">
                        <span class="flex items-center gap-2">
                            <Plus class="size-4" />
                            Add row
                        </span>
                    </SelectValue>
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="project in addableProjects"
                        :key="project.id"
                        :value="project.id"
                    >
                        <span class="flex items-center gap-2">
                            <span
                                class="size-2.5 shrink-0 rounded-full"
                                :style="{
                                    backgroundColor:
                                        project.color ?? 'var(--primary)',
                                }"
                            />
                            <span>
                                {{ project.name }}
                                <span class="text-muted-foreground">
                                    · {{ project.client_name }}
                                </span>
                            </span>
                        </span>
                    </SelectItem>
                </SelectContent>
            </Select>
            <Button
                variant="outline"
                :disabled="lastWeekProjects.length === 0"
                @click="copyLastWeek"
            >
                <Copy class="size-4" />
                Copy from last week (projects only)
            </Button>
        </div>
    </div>
</template>
