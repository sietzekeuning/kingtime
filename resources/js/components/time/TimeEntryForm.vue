<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import TimeEntryField from '@/components/time/TimeEntryField.vue';
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
import type { ProjectOptionData } from '@/types/generated';

export type TimeEntryFormData = {
    project_id: number | null;
    spent_on: string;
    hours: string;
    notes: string | null;
    is_billable: boolean;
};

/**
 * The fields of a time entry. Picking a project resets billability to what
 * the project says; the rate is derived server-side.
 */
const props = defineProps<{
    form: InertiaForm<TimeEntryFormData>;
    projects: ProjectOptionData[];
    disabled?: boolean;
    /** Labels above the inputs instead of beside them. */
    stacked?: boolean;
}>();

// The Inertia form is a shared reactive object; the fields write to it directly.
const form = props.form;

const selectedProject = computed(
    () =>
        props.projects.find((project) => project.id === form.project_id) ??
        null,
);

watch(
    () => form.project_id,
    () => {
        form.is_billable = selectedProject.value?.is_billable ?? true;
    },
);
</script>

<template>
    <div class="space-y-4">
        <TimeEntryField
            label="Project"
            field="project_id"
            required
            :stacked="stacked"
        >
            <Select v-model="form.project_id" :disabled="disabled">
                <SelectTrigger class="w-full">
                    <SelectValue placeholder="Select project">
                        <span
                            v-if="selectedProject"
                            class="flex items-center gap-2"
                        >
                            <span
                                class="size-2.5 shrink-0 rounded-full"
                                :style="{
                                    backgroundColor:
                                        selectedProject.color ??
                                        'var(--primary)',
                                }"
                            />
                            <span class="truncate">
                                {{ selectedProject.name }}
                                <span class="text-muted-foreground">
                                    · {{ selectedProject.client_name }}
                                </span>
                            </span>
                        </span>
                    </SelectValue>
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="project in projects"
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
        </TimeEntryField>

        <div :class="stacked ? 'grid grid-cols-2 gap-3' : 'contents'">
            <TimeEntryField
                label="Date"
                field="spent_on"
                required
                :stacked="stacked"
            >
                <Input
                    v-model="form.spent_on"
                    type="date"
                    :disabled="disabled"
                    :class="stacked ? '' : 'w-44'"
                />
            </TimeEntryField>

            <TimeEntryField
                label="Hours"
                field="hours"
                required
                :stacked="stacked"
            >
                <Input
                    v-model="form.hours"
                    type="number"
                    inputmode="decimal"
                    step="0.25"
                    min="0"
                    max="24"
                    placeholder="0.00"
                    :disabled="disabled"
                    :class="stacked ? '' : 'w-28'"
                />
            </TimeEntryField>
        </div>

        <TimeEntryField label="Notes" field="notes" :stacked="stacked">
            <Textarea
                v-model="form.notes"
                :rows="stacked ? 2 : 3"
                placeholder="What did you work on?"
                :disabled="disabled"
            />
        </TimeEntryField>

        <TimeEntryField
            label="Billable"
            field="is_billable"
            :stacked="stacked"
            inline
        >
            <div class="flex h-9 items-center gap-2">
                <Switch v-model="form.is_billable" :disabled="disabled" />
                <span class="text-muted-foreground text-sm">
                    {{ form.is_billable ? 'Billable' : 'Not billable' }}
                </span>
            </div>
        </TimeEntryField>
    </div>
</template>
