<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Save, Trash2 } from '@lucide/vue';
import Form from '@/components/Form.vue';
import FormRow from '@/components/FormRow.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { useConfirmDelete } from '@/composables/useConfirmDelete';
import tasks from '@/routes/tasks';
import type { TaskData } from '@/types/generated';

const props = defineProps<{
    task: TaskData;
}>();

const isNew = props.task.id === null;

defineOptions({
    layout: (page: { props: { task: TaskData } }) => ({
        breadcrumbs: [
            { title: 'Tasks', href: tasks.index() },
            {
                title: page.props.task.id ? page.props.task.name : 'New task',
                href: page.props.task.id
                    ? tasks.edit(page.props.task.id)
                    : tasks.create(),
            },
        ],
    }),
});

const form = useForm({ ...props.task });

const { confirmDelete } = useConfirmDelete();

function submit() {
    if (isNew) {
        form.post(tasks.store().url);

        return;
    }

    form.patch(tasks.update(props.task.id!).url, { preserveScroll: true });
}

function destroy() {
    void confirmDelete(tasks.destroy(props.task.id!).url, {
        title: `Delete task "${props.task.name}"?`,
        description:
            'The task is removed from every project. Time entries keep their hours but lose the task. This cannot be undone.',
    });
}
</script>

<template>
    <Head :title="isNew ? 'New task' : task.name" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <PageHeader
            :title="isNew ? 'New task' : task.name"
            :subtitle="
                isNew
                    ? 'Add a kind of work you log hours on.'
                    : 'Defaults apply when the task is assigned to a project.'
            "
            :back-href="tasks.index()"
            back-label="Tasks"
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
                    form="task-form"
                    type="submit"
                    :disabled="form.processing"
                >
                    <Save class="size-4" />
                    Save
                </Button>
            </template>
        </PageHeader>

        <Card class="max-w-3xl">
            <CardContent>
                <Form id="task-form" :form="form" @submit="submit">
                    <div class="space-y-4">
                        <FormRow label="Name" field="name" required>
                            <Input v-model="form.name" autofocus />
                        </FormRow>
                        <FormRow
                            label="Billable by default"
                            field="is_billable_by_default"
                            inline
                        >
                            <Switch v-model="form.is_billable_by_default" />
                        </FormRow>
                        <FormRow
                            label="Default hourly rate"
                            field="default_hourly_rate"
                        >
                            <div class="flex items-center gap-2">
                                <span class="text-muted-foreground text-sm"
                                    >€</span
                                >
                                <Input
                                    v-model="form.default_hourly_rate"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="w-32"
                                    placeholder="0,00"
                                />
                            </div>
                            <p class="text-muted-foreground mt-1 text-xs">
                                Used for projects that bill by task and have no
                                rate on the assignment.
                            </p>
                        </FormRow>
                        <FormRow label="Active" field="is_active" inline>
                            <Switch v-model="form.is_active" />
                        </FormRow>
                        <FormRow v-if="task.harvest_id" label="Harvest id">
                            <span class="text-muted-foreground text-sm">
                                #{{ task.harvest_id }}
                            </span>
                        </FormRow>
                    </div>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>
