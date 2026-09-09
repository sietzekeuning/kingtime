<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import DataTable from '@/components/ui/DataTable.vue';
import DataTableColumn from '@/components/ui/DataTableColumn.vue';
import { useConfirmDelete } from '@/composables/useConfirmDelete';
import type { PaginatedData } from '@/interfaces/PaginatedData';
import { BillByOptions } from '@/lib/enums';
import type { EnumOptions } from '@/lib/enums';
import { formatEuro } from '@/lib/formatters';
import { formatHours } from '@/lib/utils';
import projects from '@/routes/projects';
import type { ClientData, ProjectData } from '@/types/generated';

const props = defineProps<{
    items: PaginatedData<ProjectData>;
    clients: ClientData[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: projects.index() }],
    },
});

const { confirmDelete } = useConfirmDelete();

const rowUrl = (project: ProjectData) => projects.edit(project.id!);

const activeOptions = {
    Active: { value: '1', label: 'Active', colorClass: '' },
    Inactive: { value: '0', label: 'Inactive', colorClass: '' },
};

const clientOptions = computed<EnumOptions>(() =>
    Object.fromEntries(
        props.clients.map((client) => [
            String(client.id),
            { value: String(client.id), label: client.name, colorClass: '' },
        ]),
    ),
);

function billByOption(project: ProjectData) {
    return Object.values(BillByOptions).find(
        (option) => option.value === project.bill_by,
    );
}

function deleteProject(event: Event, project: ProjectData) {
    void confirmDelete(projects.destroy(project.id!).url, {
        event,
        title: `Delete project "${project.name}"?`,
        description:
            'Time entries of this project are deleted too. This cannot be undone.',
    });
}
</script>

<template>
    <Head title="Projects" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <PageHeader
            title="Projects"
            subtitle="The work you log hours on, per client, with its rate and tasks."
        >
            <template #actions>
                <Button as-child>
                    <Link :href="projects.create()">
                        <Plus class="size-4" />
                        New project
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <DataTable :data="items" :row-url="rowUrl" label="projects">
            <template #rows>
                <DataTableColumn show="name" label="Project">
                    <template #default="{ item }: { item: ProjectData }">
                        <div class="flex items-center gap-2">
                            <span
                                class="bg-muted size-2.5 shrink-0 rounded-full"
                                :style="
                                    item.color
                                        ? { backgroundColor: item.color }
                                        : undefined
                                "
                            />
                            <span class="font-medium">{{ item.name }}</span>
                            <span
                                v-if="item.code"
                                class="text-muted-foreground text-xs"
                            >
                                {{ item.code }}
                            </span>
                        </div>
                    </template>
                </DataTableColumn>
                <DataTableColumn
                    show="client_id"
                    label="Client"
                    filter-type="select"
                    :filter-options="clientOptions"
                >
                    <template #default="{ item }: { item: ProjectData }">
                        <span class="text-muted-foreground">
                            {{ item.client?.name }}
                        </span>
                    </template>
                </DataTableColumn>
                <DataTableColumn
                    show="bill_by"
                    label="Billing"
                    filter-type="select"
                    :filter-options="BillByOptions"
                >
                    <template #default="{ item }: { item: ProjectData }">
                        <span
                            v-if="billByOption(item)"
                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="billByOption(item)!.colorClass"
                        >
                            {{ billByOption(item)!.label }}
                        </span>
                    </template>
                </DataTableColumn>
                <DataTableColumn show="hourly_rate" label="Rate">
                    <template #default="{ item }: { item: ProjectData }">
                        <span
                            v-if="item.hourly_rate !== null"
                            class="tabular-nums"
                        >
                            {{ formatEuro(item.hourly_rate) }}
                        </span>
                        <span v-else class="text-muted-foreground">·</span>
                    </template>
                </DataTableColumn>
                <DataTableColumn show="total_hours" label="Hours">
                    <template #default="{ item }: { item: ProjectData }">
                        <span class="tabular-nums">
                            {{ formatHours(item.total_hours) }}
                        </span>
                    </template>
                </DataTableColumn>
                <DataTableColumn show="unbilled_hours" label="Unbilled">
                    <template #default="{ item }: { item: ProjectData }">
                        <span
                            class="tabular-nums"
                            :class="
                                Number.parseFloat(item.unbilled_hours ?? '0') >
                                0
                                    ? 'text-foreground'
                                    : 'text-muted-foreground'
                            "
                        >
                            {{ formatHours(item.unbilled_hours) }}
                        </span>
                    </template>
                </DataTableColumn>
                <DataTableColumn
                    show="is_active"
                    label="Status"
                    filter-type="select"
                    :filter-options="activeOptions"
                >
                    <template #default="{ item }: { item: ProjectData }">
                        <StatusBadge
                            :tone="item.is_active ? 'green' : 'gray'"
                            :label="item.is_active ? 'Active' : 'Inactive'"
                        />
                    </template>
                </DataTableColumn>
                <DataTableColumn show="actions" label="">
                    <template #default="{ item }: { item: ProjectData }">
                        <button
                            type="button"
                            class="text-muted-foreground hover:text-destructive transition-colors"
                            title="Delete project"
                            @click="deleteProject($event, item)"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </template>
                </DataTableColumn>
            </template>
        </DataTable>
    </div>
</template>
