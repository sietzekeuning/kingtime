<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Lock, Save, Trash2 } from '@lucide/vue';
import EmptyState from '@/components/EmptyState.vue';
import Form from '@/components/Form.vue';
import PageHeader from '@/components/PageHeader.vue';
import TimeEntryForm from '@/components/time/TimeEntryForm.vue';
import type { TimeEntryFormData } from '@/components/time/TimeEntryForm.vue';
import TimerButton from '@/components/time/TimerButton.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useConfirmDelete } from '@/composables/useConfirmDelete';
import { formatDate } from '@/lib/time';
import { formatHours } from '@/lib/utils';
import timeEntries from '@/routes/time-entries';
import type { ProjectOptionData, TimeEntryData } from '@/types/generated';

const props = defineProps<{
    entry: TimeEntryData;
    projects: ProjectOptionData[];
}>();

const isNew = props.entry.id === null;
const locked = computed(() => props.entry.is_locked || props.entry.is_billed);

// Inertia calls a layout function with the page props, not the page itself.
defineOptions({
    layout: ({ entry }: { entry: TimeEntryData }) => ({
        breadcrumbs: [
            {
                title: 'Time entries',
                href: timeEntries.index({
                    query: { date: entry.spent_on },
                }),
            },
            {
                title: entry.id
                    ? `${formatDate(entry.spent_on)} · ${entry.project_name}`
                    : 'New entry',
                href: entry.id
                    ? timeEntries.edit(entry.id)
                    : timeEntries.create(),
            },
        ],
    }),
});

const form = useForm<TimeEntryFormData>({
    project_id: props.entry.project_id || null,
    spent_on: props.entry.spent_on,
    hours: props.entry.hours,
    notes: props.entry.notes,
    is_billable: props.entry.is_billable ?? true,
});

const { confirmDelete } = useConfirmDelete();

function submit() {
    if (isNew) {
        form.post(timeEntries.store().url);

        return;
    }

    form.patch(timeEntries.update(props.entry.id!).url, {
        preserveScroll: true,
    });
}

function destroy() {
    void confirmDelete(timeEntries.destroy(props.entry.id!).url, {
        title: 'Delete this time entry?',
        description: `${formatHours(props.entry.hours)} hours on ${props.entry.project_name}. This cannot be undone.`,
    });
}
</script>

<template>
    <Head :title="isNew ? 'New time entry' : 'Edit time entry'" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <PageHeader
            :title="isNew ? 'New time entry' : 'Edit time entry'"
            :subtitle="
                isNew
                    ? 'Log hours on a project.'
                    : `${formatDate(entry.spent_on, 'dddd D MMMM YYYY')} · ${formatHours(entry.hours)} hours`
            "
            :back-href="timeEntries.index({ query: { date: entry.spent_on } })"
            back-label="Timesheet"
        >
            <template #actions>
                <TimerButton v-if="!isNew" :entry="entry" :disabled="locked" />
                <Button
                    v-if="!isNew"
                    variant="outline"
                    class="text-destructive"
                    :disabled="locked"
                    @click="destroy"
                >
                    <Trash2 class="size-4" />
                    Delete
                </Button>
                <Button
                    form="time-entry-form"
                    type="submit"
                    :disabled="form.processing || locked"
                >
                    <Save class="size-4" />
                    Save
                </Button>
            </template>
        </PageHeader>

        <Card class="max-w-3xl">
            <CardContent>
                <EmptyState v-if="locked" class="mb-4">
                    <span class="inline-flex items-center gap-2">
                        <Lock class="size-4" />
                        {{
                            entry.is_billed
                                ? 'This entry has been billed and can no longer be changed.'
                                : 'This entry is locked and can no longer be changed.'
                        }}
                    </span>
                </EmptyState>
                <Form id="time-entry-form" :form="form" @submit="submit">
                    <TimeEntryForm
                        :form="form"
                        :projects="projects"
                        :disabled="locked"
                    />
                    <div
                        v-if="!isNew && entry.hourly_rate"
                        class="text-muted-foreground mt-4 text-sm"
                    >
                        Billed at {{ entry.hourly_rate }} per hour.
                    </div>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>
