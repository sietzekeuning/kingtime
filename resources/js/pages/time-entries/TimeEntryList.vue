<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Clock, Play, Plus, Save } from '@lucide/vue';
import { ref, watch } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import Form from '@/components/Form.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import TimeEntryForm from '@/components/time/TimeEntryForm.vue';
import type { TimeEntryFormData } from '@/components/time/TimeEntryForm.vue';
import TimeEntryRow from '@/components/time/TimeEntryRow.vue';
import WeekStrip from '@/components/time/WeekStrip.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import DataTable from '@/components/ui/DataTable.vue';
import DataTableColumn from '@/components/ui/DataTableColumn.vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type { PaginatedData } from '@/interfaces/PaginatedData';
import type { EnumOptions } from '@/lib/enums';
import { formatDate } from '@/lib/time';
import { formatHours } from '@/lib/utils';
import timeEntries from '@/routes/time-entries';
import type {
    ClientData,
    ProjectOptionData,
    TimeEntryData,
    TimesheetData,
} from '@/types/generated';
import type { User } from '@/types';

const props = defineProps<{
    timesheet: TimesheetData;
    items: PaginatedData<TimeEntryData>;
    projects: ProjectOptionData[];
    clients: ClientData[];
    auth: { user: User };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Time entries', href: timeEntries.index() }],
    },
});

// A URL that carries table state was shared from the "All entries" tab.
const initialTab =
    typeof window !== 'undefined' &&
    /[?&](filter\[|sort=|page=)/.test(window.location.search)
        ? 'all'
        : 'timesheet';
const tab = ref<string>(initialTab);

const form = useForm<TimeEntryFormData & { start_timer: boolean }>({
    project_id:
        props.projects.length === 1 ? (props.projects[0]?.id ?? null) : null,
    task_id: null,
    spent_on: props.timesheet.selected_date,
    hours: '',
    notes: '',
    is_billable: true,
    start_timer: false,
});

watch(
    () => props.timesheet.selected_date,
    (date) => {
        form.spent_on = date;
    },
);

function submit(startTimer = false) {
    form.start_timer = startTimer;

    if (startTimer && form.hours === '') {
        form.hours = '0';
    }

    form.post(timeEntries.store().url, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('hours', 'notes', 'start_timer');
        },
    });
}

const projectOptions: EnumOptions = Object.fromEntries(
    props.projects.map((project) => [
        project.id,
        { value: String(project.id), label: project.name, colorClass: '' },
    ]),
);

const clientOptions: EnumOptions = Object.fromEntries(
    props.clients.map((client) => [
        client.id!,
        { value: String(client.id), label: client.name, colorClass: '' },
    ]),
);

const yesNoOptions: EnumOptions = {
    Yes: { value: '1', label: 'Yes', colorClass: '' },
    No: { value: '0', label: 'No', colorClass: '' },
};

const rowUrl = (entry: TimeEntryData) =>
    entry.user_id === props.auth.user.id ? timeEntries.edit(entry.id!) : null;
</script>

<template>
    <Head title="Time entries" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <PageHeader
            title="Time entries"
            subtitle="Your hours per day, and everything that has ever been logged."
        >
            <template #actions>
                <Button as-child>
                    <Link
                        :href="
                            timeEntries.create({
                                query: { date: timesheet.selected_date },
                            })
                        "
                    >
                        <Plus class="size-4" />
                        New entry
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <Tabs v-model="tab" class="gap-4">
            <TabsList>
                <TabsTrigger value="timesheet">Timesheet</TabsTrigger>
                <TabsTrigger value="all">All entries</TabsTrigger>
            </TabsList>

            <TabsContent value="timesheet" class="flex flex-col gap-4">
                <Card class="gap-4 py-4">
                    <CardContent>
                        <WeekStrip :timesheet="timesheet" />
                    </CardContent>
                </Card>

                <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_360px]">
                    <Card class="gap-2 py-4">
                        <CardHeader
                            class="flex flex-row items-baseline justify-between"
                        >
                            <CardTitle class="text-base">
                                {{
                                    formatDate(
                                        timesheet.selected_date,
                                        'dddd D MMMM YYYY',
                                    )
                                }}
                            </CardTitle>
                            <span class="text-sm">
                                <span class="text-muted-foreground"
                                    >Day total</span
                                >
                                <span
                                    class="ml-2 text-lg font-semibold tabular-nums"
                                >
                                    {{ formatHours(timesheet.day_total) }}
                                </span>
                            </span>
                        </CardHeader>
                        <CardContent>
                            <div
                                v-if="timesheet.entries.length > 0"
                                class="divide-border divide-y"
                            >
                                <TimeEntryRow
                                    v-for="entry in timesheet.entries"
                                    :key="entry.id!"
                                    :entry="entry"
                                    :can-edit="entry.user_id === auth.user.id"
                                />
                            </div>
                            <EmptyState
                                v-else
                                variant="centered"
                                :icon="Clock"
                                title="Nothing logged yet"
                                description="Log your first hours for this day with the form, or start a timer and let it run."
                            />
                        </CardContent>
                    </Card>

                    <Card class="gap-4 self-start py-4">
                        <CardHeader>
                            <CardTitle class="text-base">Log time</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Form
                                id="new-entry-form"
                                :form="form"
                                @submit="submit(false)"
                            >
                                <TimeEntryForm
                                    :form="form"
                                    :projects="projects"
                                    stacked
                                />
                                <div class="mt-4 flex items-center gap-2">
                                    <Button
                                        type="submit"
                                        :disabled="form.processing"
                                    >
                                        <Save class="size-4" />
                                        Save
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        :disabled="form.processing"
                                        @click="submit(true)"
                                    >
                                        <Play class="size-4" />
                                        Start timer
                                    </Button>
                                </div>
                            </Form>
                            <EmptyState
                                v-if="projects.length === 0"
                                class="mt-4"
                                description="No active projects yet. Create a project first to log time on it."
                            />
                        </CardContent>
                    </Card>
                </div>
            </TabsContent>

            <TabsContent value="all">
                <DataTable :data="items" :row-url="rowUrl" label="entries">
                    <template #rows>
                        <DataTableColumn
                            show="spent_on"
                            label="Date"
                            filter-placeholder="e.g. 09-2026"
                        >
                            <template
                                #default="{ item }: { item: TimeEntryData }"
                            >
                                <span class="whitespace-nowrap tabular-nums">
                                    {{ formatDate(item.spent_on) }}
                                </span>
                            </template>
                        </DataTableColumn>
                        <DataTableColumn
                            show="project_id"
                            label="Project"
                            filter-type="select"
                            :filter-options="projectOptions"
                        >
                            <template
                                #default="{ item }: { item: TimeEntryData }"
                            >
                                <span class="flex items-center gap-2">
                                    <span
                                        class="size-2 shrink-0 rounded-full"
                                        :style="{
                                            backgroundColor:
                                                item.project_color ??
                                                'var(--primary)',
                                        }"
                                    />
                                    <span class="font-medium">{{
                                        item.project_name
                                    }}</span>
                                </span>
                            </template>
                        </DataTableColumn>
                        <DataTableColumn
                            show="client_id"
                            label="Client"
                            filter-type="select"
                            :filter-options="clientOptions"
                        >
                            <template
                                #default="{ item }: { item: TimeEntryData }"
                            >
                                <span class="text-muted-foreground">{{
                                    item.client_name
                                }}</span>
                            </template>
                        </DataTableColumn>
                        <DataTableColumn show="task_name" label="Task">
                            <template
                                #default="{ item }: { item: TimeEntryData }"
                            >
                                {{ item.task_name }}
                            </template>
                        </DataTableColumn>
                        <DataTableColumn show="notes" label="Notes">
                            <template
                                #default="{ item }: { item: TimeEntryData }"
                            >
                                <span
                                    class="text-muted-foreground line-clamp-1 max-w-md"
                                >
                                    {{ item.notes }}
                                </span>
                            </template>
                        </DataTableColumn>
                        <DataTableColumn show="hours" label="Hours">
                            <template
                                #default="{ item }: { item: TimeEntryData }"
                            >
                                <span
                                    class="flex items-center gap-2 tabular-nums"
                                >
                                    {{ formatHours(item.hours) }}
                                    <span
                                        v-if="item.is_running"
                                        class="bg-primary size-1.5 animate-pulse rounded-full"
                                        title="Timer running"
                                    />
                                </span>
                            </template>
                        </DataTableColumn>
                        <DataTableColumn
                            show="is_billable"
                            label="Billable"
                            filter-type="select"
                            :filter-options="yesNoOptions"
                        >
                            <template
                                #default="{ item }: { item: TimeEntryData }"
                            >
                                <StatusBadge
                                    :tone="item.is_billable ? 'green' : 'gray'"
                                    :label="
                                        item.is_billable
                                            ? 'Billable'
                                            : 'Non-billable'
                                    "
                                />
                            </template>
                        </DataTableColumn>
                        <DataTableColumn
                            show="is_billed"
                            label="Billed"
                            filter-type="select"
                            :filter-options="yesNoOptions"
                        >
                            <template
                                #default="{ item }: { item: TimeEntryData }"
                            >
                                <StatusBadge
                                    v-if="item.is_billed"
                                    tone="blue"
                                    label="Billed"
                                />
                                <StatusBadge
                                    v-else-if="item.is_locked"
                                    tone="amber"
                                    label="Locked"
                                />
                                <span
                                    v-else
                                    class="text-muted-foreground text-xs"
                                    >Open</span
                                >
                            </template>
                        </DataTableColumn>
                    </template>
                </DataTable>
            </TabsContent>
        </Tabs>
    </div>
</template>
