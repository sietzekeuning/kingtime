<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import ArchiveFilterTabs from '@/components/ArchiveFilterTabs.vue';
import ArchiveToggleButton from '@/components/ArchiveToggleButton.vue';
import BudgetMeter from '@/components/BudgetMeter.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import DataTable from '@/components/ui/DataTable.vue';
import DataTableColumn from '@/components/ui/DataTableColumn.vue';
import { useConfirmDelete } from '@/composables/useConfirmDelete';
import type { PaginatedData } from '@/interfaces/PaginatedData';
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

const billableOptions = {
    Billable: { value: '1', label: 'Billable', colorClass: '' },
    NonBillable: { value: '0', label: 'Non-billable', colorClass: '' },
};

const clientOptions = computed<EnumOptions>(() =>
    Object.fromEntries(
        props.clients.map((client) => [
            String(client.id),
            { value: String(client.id), label: client.name, colorClass: '' },
        ]),
    ),
);

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
            subtitle="The work you log hours on, per client, with its rate."
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
            <template #buttons="{ filters }">
                <ArchiveFilterTabs
                    :model-value="filters.get('is_active')"
                    @update:model-value="filters.set('is_active', $event)"
                />
            </template>
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
                    show="is_billable"
                    label="Billing"
                    filter-type="select"
                    :filter-options="billableOptions"
                >
                    <template #default="{ item }: { item: ProjectData }">
                        <StatusBadge
                            :tone="item.is_billable ? 'amber' : 'gray'"
                            :label="
                                item.is_billable ? 'Billable' : 'Non-billable'
                            "
                        />
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
                        <BudgetMeter
                            :hours="item.total_hours"
                            :budget="item.budget_hours"
                        />
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
                    :filterable="false"
                >
                    <template #default="{ item }: { item: ProjectData }">
                        <StatusBadge
                            :tone="item.is_active ? 'green' : 'gray'"
                            :label="item.is_active ? 'Active' : 'Archived'"
                        />
                    </template>
                </DataTableColumn>
                <DataTableColumn show="actions" label="">
                    <template #default="{ item }: { item: ProjectData }">
                        <div class="flex items-center justify-end gap-3">
                            <ArchiveToggleButton
                                :active="item.is_active"
                                :archive-url="
                                    projects.archive.store(item.id!).url
                                "
                                :restore-url="
                                    projects.archive.destroy(item.id!).url
                                "
                                subject="project"
                                icon-only
                            />
                            <button
                                type="button"
                                class="text-muted-foreground hover:text-destructive transition-colors"
                                title="Delete project"
                                @click="deleteProject($event, item)"
                            >
                                <Trash2 class="size-4" />
                            </button>
                        </div>
                    </template>
                </DataTableColumn>
            </template>
        </DataTable>
    </div>
</template>
