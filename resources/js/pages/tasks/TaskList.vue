<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import DataTable from '@/components/ui/DataTable.vue';
import DataTableColumn from '@/components/ui/DataTableColumn.vue';
import { useConfirmDelete } from '@/composables/useConfirmDelete';
import type { PaginatedData } from '@/interfaces/PaginatedData';
import { formatEuro } from '@/lib/formatters';
import tasks from '@/routes/tasks';
import type { TaskData } from '@/types/generated';

defineProps<{
    items: PaginatedData<TaskData>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Tasks', href: tasks.index() }],
    },
});

const { confirmDelete } = useConfirmDelete();

const rowUrl = (task: TaskData) => tasks.edit(task.id!);

const activeOptions = {
    Active: { value: '1', label: 'Active', colorClass: '' },
    Inactive: { value: '0', label: 'Inactive', colorClass: '' },
};

function deleteTask(event: Event, task: TaskData) {
    void confirmDelete(tasks.destroy(task.id!).url, {
        event,
        title: `Delete task "${task.name}"?`,
        description:
            'The task is removed from every project. Time entries keep their hours but lose the task. This cannot be undone.',
    });
}
</script>

<template>
    <Head title="Tasks" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <PageHeader
            title="Tasks"
            subtitle="The kinds of work you log hours on, such as development or meetings."
        >
            <template #actions>
                <Button as-child>
                    <Link :href="tasks.create()">
                        <Plus class="size-4" />
                        New task
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <DataTable :data="items" :row-url="rowUrl" label="tasks">
            <template #rows>
                <DataTableColumn show="name" label="Name">
                    <template #default="{ item }: { item: TaskData }">
                        <span class="font-medium">{{ item.name }}</span>
                    </template>
                </DataTableColumn>
                <DataTableColumn
                    show="is_billable_by_default"
                    label="Billable by default"
                >
                    <template #default="{ item }: { item: TaskData }">
                        <StatusBadge
                            :tone="
                                item.is_billable_by_default ? 'orange' : 'gray'
                            "
                            :label="
                                item.is_billable_by_default
                                    ? 'Billable'
                                    : 'Non-billable'
                            "
                        />
                    </template>
                </DataTableColumn>
                <DataTableColumn
                    show="default_hourly_rate"
                    label="Default rate"
                >
                    <template #default="{ item }: { item: TaskData }">
                        <span
                            v-if="item.default_hourly_rate !== null"
                            class="tabular-nums"
                        >
                            {{ formatEuro(item.default_hourly_rate) }}
                        </span>
                        <span v-else class="text-muted-foreground">·</span>
                    </template>
                </DataTableColumn>
                <DataTableColumn
                    show="is_active"
                    label="Status"
                    filter-type="select"
                    :filter-options="activeOptions"
                >
                    <template #default="{ item }: { item: TaskData }">
                        <StatusBadge
                            :tone="item.is_active ? 'green' : 'gray'"
                            :label="item.is_active ? 'Active' : 'Inactive'"
                        />
                    </template>
                </DataTableColumn>
                <DataTableColumn show="actions" label="">
                    <template #default="{ item }: { item: TaskData }">
                        <button
                            type="button"
                            class="text-muted-foreground hover:text-destructive transition-colors"
                            title="Delete task"
                            @click="deleteTask($event, item)"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </template>
                </DataTableColumn>
            </template>
        </DataTable>
    </div>
</template>
