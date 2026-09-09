<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import ArchiveToggleButton from '@/components/ArchiveToggleButton.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import DataTable from '@/components/ui/DataTable.vue';
import DataTableColumn from '@/components/ui/DataTableColumn.vue';
import { useConfirmDelete } from '@/composables/useConfirmDelete';
import type { PaginatedData } from '@/interfaces/PaginatedData';
import clients from '@/routes/clients';
import type { ClientData } from '@/types/generated';

defineProps<{
    items: PaginatedData<ClientData>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Clients', href: clients.index() }],
    },
});

const { confirmDelete } = useConfirmDelete();

const rowUrl = (client: ClientData) => clients.edit(client.id!);

/** The list shows active clients by default; the filter opens up the archive. */
const archiveOptions = {
    Archived: { value: '0', label: 'Archived', colorClass: '' },
    All: { value: 'all', label: 'Active and archived', colorClass: '' },
};

function deleteClient(event: Event, client: ClientData) {
    void confirmDelete(clients.destroy(client.id!).url, {
        event,
        title: `Delete client "${client.name}"?`,
        description:
            'Projects and time entries of this client are deleted too. This cannot be undone.',
    });
}
</script>

<template>
    <Head title="Clients" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <PageHeader
            title="Clients"
            subtitle="The companies you work for and bill through Moneybird."
        >
            <template #actions>
                <Button as-child>
                    <Link :href="clients.create()">
                        <Plus class="size-4" />
                        New client
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <DataTable :data="items" :row-url="rowUrl" label="clients">
            <template #rows>
                <DataTableColumn show="name" label="Name">
                    <template #default="{ item }: { item: ClientData }">
                        <span class="font-medium">{{ item.name }}</span>
                    </template>
                </DataTableColumn>
                <DataTableColumn show="email" label="Email">
                    <template #default="{ item }: { item: ClientData }">
                        <span class="text-muted-foreground">{{
                            item.email
                        }}</span>
                    </template>
                </DataTableColumn>
                <DataTableColumn show="projects_count" label="Projects">
                    <template #default="{ item }: { item: ClientData }">
                        {{ item.projects_count }}
                    </template>
                </DataTableColumn>
                <DataTableColumn
                    show="is_active"
                    label="Status"
                    filter-type="select"
                    :filter-options="archiveOptions"
                    filter-placeholder="Active"
                >
                    <template #default="{ item }: { item: ClientData }">
                        <StatusBadge
                            :tone="item.is_active ? 'green' : 'gray'"
                            :label="item.is_active ? 'Active' : 'Archived'"
                        />
                    </template>
                </DataTableColumn>
                <DataTableColumn show="actions" label="">
                    <template #default="{ item }: { item: ClientData }">
                        <div class="flex items-center justify-end gap-3">
                            <ArchiveToggleButton
                                :active="item.is_active"
                                :archive-url="
                                    clients.archive.store(item.id!).url
                                "
                                :restore-url="
                                    clients.archive.destroy(item.id!).url
                                "
                                subject="client"
                                icon-only
                            />
                            <button
                                type="button"
                                class="text-muted-foreground hover:text-destructive transition-colors"
                                title="Delete client"
                                @click="deleteClient($event, item)"
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
