<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Plus, Save, Trash2, X } from '@lucide/vue';
import { computed } from 'vue';
import EnumSelect from '@/components/EnumSelect.vue';
import Form from '@/components/Form.vue';
import FormRow from '@/components/FormRow.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useConfirmDelete } from '@/composables/useConfirmDelete';
import { BillBy, BillByOptions } from '@/lib/enums';
import { formatHours } from '@/lib/utils';
import projects from '@/routes/projects';
import type {
    ClientData,
    ProjectData,
    ProjectTaskData,
    TaskData,
} from '@/types/generated';

const props = defineProps<{
    project: ProjectData;
    clients: ClientData[];
    tasks: TaskData[];
}>();

const isNew = props.project.id === null;

defineOptions({
    layout: (page: { props: { project: ProjectData } }) => ({
        breadcrumbs: [
            { title: 'Projects', href: projects.index() },
            {
                title: page.props.project.id
                    ? page.props.project.name
                    : 'New project',
                href: page.props.project.id
                    ? projects.edit(page.props.project.id)
                    : projects.create(),
            },
        ],
    }),
});

/** A fresh project arrives with `client_id: null`; the form keeps that until a client is picked. */
type ProjectForm = Omit<ProjectData, 'client_id' | 'tasks'> & {
    client_id: number | null;
    tasks: ProjectTaskData[];
};

const form = useForm<ProjectForm>({
    ...props.project,
    client_id: props.project.client_id ?? null,
    tasks: (props.project.tasks ?? []).map((task) => ({ ...task })),
});

const { confirmDelete } = useConfirmDelete();

/** Colours the palette offers; anything else can be typed in the hex input. */
const colorPalette = [
    '#F97316',
    '#F59E0B',
    '#EF4444',
    '#10B981',
    '#3B82F6',
    '#8B5CF6',
    '#EC4899',
    '#64748B',
];

/** shadcn Select speaks strings; the DTO speaks integer ids. */
const clientId = computed<string | undefined>({
    get: () => (form.client_id === null ? undefined : String(form.client_id)),
    set: (value) => {
        form.client_id = value ? Number(value) : null;
    },
});

const billsByTask = computed(() => form.bill_by === BillBy.Task);
const billsByProject = computed(() => form.bill_by === BillBy.Project);

const taskById = computed(
    () => new Map(props.tasks.map((task) => [task.id, task])),
);

const unassignedTasks = computed(() =>
    props.tasks.filter(
        (task) => !form.tasks.some((row) => row.task_id === task.id),
    ),
);

function taskOptionsFor(row: ProjectTaskData): TaskData[] {
    const current = taskById.value.get(row.task_id);

    return current
        ? [current, ...unassignedTasks.value].sort((a, b) =>
              a.name.localeCompare(b.name),
          )
        : unassignedTasks.value;
}

function addTask() {
    const task = unassignedTasks.value[0];

    if (!task) {
        return;
    }

    form.tasks.push({
        id: null,
        task_id: task.id!,
        task_name: task.name,
        is_billable: task.is_billable_by_default,
        hourly_rate: task.default_hourly_rate,
        is_active: true,
    });
}

function changeTask(row: ProjectTaskData, value: unknown) {
    const task = taskById.value.get(Number(value));

    if (!task) {
        return;
    }

    row.task_id = task.id!;
    row.task_name = task.name;
    row.is_billable = task.is_billable_by_default;
    row.hourly_rate = task.default_hourly_rate;
}

function removeTask(index: number) {
    form.tasks.splice(index, 1);
}

function submit() {
    if (isNew) {
        form.post(projects.store().url);

        return;
    }

    form.patch(projects.update(props.project.id!).url, {
        preserveScroll: true,
    });
}

function destroy() {
    void confirmDelete(projects.destroy(props.project.id!).url, {
        title: `Delete project "${props.project.name}"?`,
        description:
            'Time entries of this project are deleted too. This cannot be undone.',
    });
}
</script>

<template>
    <Head :title="isNew ? 'New project' : project.name" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <PageHeader
            :title="isNew ? 'New project' : project.name"
            :subtitle="
                isNew
                    ? 'Add a project you log hours on.'
                    : `${project.client?.name ?? ''} · ${formatHours(project.total_hours)} hours logged`
            "
            :back-href="projects.index()"
            back-label="Projects"
        >
            <template #actions>
                <Button
                    v-if="!isNew"
                    variant="outline"
                    class="text-destructive"
                    @click="destroy"
                >
                    <Trash2 class="size-4" />
                    Delete
                </Button>
                <Button
                    form="project-form"
                    type="submit"
                    :disabled="form.processing"
                >
                    <Save class="size-4" />
                    Save
                </Button>
            </template>
        </PageHeader>

        <Form id="project-form" :form="form" @submit="submit">
            <div class="flex max-w-3xl flex-col gap-4">
                <Card>
                    <CardContent>
                        <div class="space-y-4">
                            <FormRow label="Client" field="client_id" required>
                                <Select v-model="clientId">
                                    <SelectTrigger class="w-full">
                                        <SelectValue
                                            placeholder="Select client"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="client in clients"
                                            :key="client.id!"
                                            :value="String(client.id)"
                                        >
                                            {{ client.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </FormRow>
                            <FormRow label="Name" field="name" required>
                                <Input v-model="form.name" autofocus />
                            </FormRow>
                            <FormRow label="Code" field="code">
                                <Input
                                    v-model="form.code"
                                    class="w-40"
                                    placeholder="e.g. ACME"
                                />
                            </FormRow>
                            <FormRow label="Color" field="color">
                                <div class="flex flex-wrap items-center gap-2">
                                    <button
                                        v-for="swatch in colorPalette"
                                        :key="swatch"
                                        type="button"
                                        class="size-7 rounded-full border-2 transition-transform hover:scale-110"
                                        :class="
                                            form.color?.toUpperCase() === swatch
                                                ? 'border-foreground'
                                                : 'border-transparent'
                                        "
                                        :style="{ backgroundColor: swatch }"
                                        :title="swatch"
                                        @click="form.color = swatch"
                                    />
                                    <Input
                                        v-model="form.color"
                                        class="w-28 font-mono"
                                        placeholder="#F97316"
                                        maxlength="20"
                                    />
                                </div>
                            </FormRow>
                            <FormRow label="Notes" field="notes">
                                <Textarea v-model="form.notes" rows="3" />
                            </FormRow>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent>
                        <div class="space-y-4">
                            <h2 class="text-sm font-semibold">Billing</h2>
                            <FormRow
                                label="Billable"
                                field="is_billable"
                                inline
                            >
                                <Switch v-model="form.is_billable" />
                            </FormRow>
                            <FormRow label="Bill by" field="bill_by">
                                <EnumSelect
                                    v-model="form.bill_by"
                                    :enum-options="BillByOptions"
                                    label="billing"
                                    :disabled="!form.is_billable"
                                />
                            </FormRow>
                            <FormRow
                                v-if="billsByProject"
                                label="Hourly rate"
                                field="hourly_rate"
                            >
                                <div class="flex items-center gap-2">
                                    <span class="text-muted-foreground text-sm"
                                        >€</span
                                    >
                                    <Input
                                        v-model="form.hourly_rate"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="w-32"
                                        placeholder="0,00"
                                    />
                                </div>
                            </FormRow>
                            <FormRow
                                label="Budget (hours)"
                                field="budget_hours"
                            >
                                <Input
                                    v-model="form.budget_hours"
                                    type="number"
                                    step="0.25"
                                    min="0"
                                    class="w-32"
                                    placeholder="0,00"
                                />
                            </FormRow>
                            <FormRow
                                label="Period"
                                :fields="['starts_on', 'ends_on']"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <Input
                                        v-model="form.starts_on"
                                        type="date"
                                        class="w-40"
                                    />
                                    <span class="text-muted-foreground text-sm"
                                        >to</span
                                    >
                                    <Input
                                        v-model="form.ends_on"
                                        type="date"
                                        class="w-40"
                                    />
                                </div>
                            </FormRow>
                            <FormRow label="Active" field="is_active" inline>
                                <Switch v-model="form.is_active" />
                            </FormRow>
                            <FormRow
                                v-if="project.harvest_id"
                                label="Harvest id"
                            >
                                <span class="text-muted-foreground text-sm">
                                    #{{ project.harvest_id }}
                                </span>
                            </FormRow>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent>
                        <div class="space-y-4">
                            <div
                                class="flex items-center justify-between gap-4"
                            >
                                <div>
                                    <h2 class="text-sm font-semibold">Tasks</h2>
                                    <p class="text-muted-foreground text-xs">
                                        Only assigned tasks can be picked when
                                        logging hours on this project.
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    :disabled="unassignedTasks.length === 0"
                                    @click="addTask"
                                >
                                    <Plus class="size-4" />
                                    Add task
                                </Button>
                            </div>

                            <FormRow field="tasks">
                                <p
                                    v-if="form.tasks.length === 0"
                                    class="text-muted-foreground text-sm"
                                >
                                    No tasks assigned yet.
                                </p>

                                <div v-else class="overflow-x-auto">
                                    <div class="min-w-[36rem] space-y-2">
                                        <div
                                            class="text-muted-foreground grid grid-cols-[1fr_5rem_8rem_4rem_2.25rem] items-center gap-3 px-1 text-xs font-medium"
                                        >
                                            <span>Task</span>
                                            <span>Billable</span>
                                            <span>{{
                                                billsByTask ? 'Rate' : ''
                                            }}</span>
                                            <span>Active</span>
                                            <span />
                                        </div>
                                        <div
                                            v-for="(row, index) in form.tasks"
                                            :key="row.id ?? `new-${index}`"
                                            class="grid grid-cols-[1fr_5rem_8rem_4rem_2.25rem] items-start gap-3 rounded-md border px-1 py-2"
                                        >
                                            <FormRow
                                                :field="`tasks.${index}.task_id`"
                                            >
                                                <Select
                                                    :model-value="
                                                        String(row.task_id)
                                                    "
                                                    @update:model-value="
                                                        changeTask(row, $event)
                                                    "
                                                >
                                                    <SelectTrigger
                                                        class="w-full"
                                                    >
                                                        <SelectValue
                                                            placeholder="Select task"
                                                        />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem
                                                            v-for="task in taskOptionsFor(
                                                                row,
                                                            )"
                                                            :key="task.id!"
                                                            :value="
                                                                String(task.id)
                                                            "
                                                        >
                                                            {{ task.name }}
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </FormRow>
                                            <div class="flex h-9 items-center">
                                                <Switch
                                                    v-model="row.is_billable"
                                                />
                                            </div>
                                            <FormRow
                                                :field="`tasks.${index}.hourly_rate`"
                                            >
                                                <Input
                                                    v-if="billsByTask"
                                                    v-model="row.hourly_rate"
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    placeholder="0,00"
                                                    :disabled="!row.is_billable"
                                                />
                                            </FormRow>
                                            <div class="flex h-9 items-center">
                                                <Switch
                                                    v-model="row.is_active"
                                                />
                                            </div>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                class="text-muted-foreground hover:text-destructive"
                                                title="Remove task"
                                                @click="removeTask(index)"
                                            >
                                                <X class="size-4" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </FormRow>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </Form>
    </div>
</template>
