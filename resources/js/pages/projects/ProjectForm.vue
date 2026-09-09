<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Save, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import ArchiveToggleButton from '@/components/ArchiveToggleButton.vue';
import Form from '@/components/Form.vue';
import FormRow from '@/components/FormRow.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
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
import { formatHours } from '@/lib/utils';
import projects from '@/routes/projects';
import type { ClientData, ProjectData } from '@/types/generated';

const props = defineProps<{
    project: ProjectData;
    clients: ClientData[];
}>();

const isNew = props.project.id === null;

// Inertia calls a layout function with the page props, not the page itself.
defineOptions({
    layout: ({ project }: { project: ProjectData }) => ({
        breadcrumbs: [
            { title: 'Projects', href: projects.index() },
            {
                title: project.id ? project.name : 'New project',
                href: project.id
                    ? projects.edit(project.id)
                    : projects.create(),
            },
        ],
    }),
});

/** A fresh project arrives with `client_id: null`; the form keeps that until a client is picked. */
type ProjectForm = Omit<ProjectData, 'client_id'> & {
    client_id: number | null;
};

const form = useForm<ProjectForm>({
    ...props.project,
    client_id: props.project.client_id ?? null,
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
                <StatusBadge
                    v-if="!isNew && !project.is_active"
                    tone="gray"
                    label="Archived"
                />
                <ArchiveToggleButton
                    v-if="!isNew"
                    :active="project.is_active"
                    :archive-url="projects.archive.store(project.id!).url"
                    :restore-url="projects.archive.destroy(project.id!).url"
                    subject="project"
                />
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
                            <FormRow
                                v-if="form.is_billable"
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
            </div>
        </Form>
    </div>
</template>
