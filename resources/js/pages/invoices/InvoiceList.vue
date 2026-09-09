<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ExternalLink, FilePlus2, Trash2 } from '@lucide/vue';
import InvoiceStatusBadge from '@/components/invoices/InvoiceStatusBadge.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import DataTable from '@/components/ui/DataTable.vue';
import DataTableColumn from '@/components/ui/DataTableColumn.vue';
import { useConfirmDelete } from '@/composables/useConfirmDelete';
import type { PaginatedData } from '@/interfaces/PaginatedData';
import { InvoiceStatus, InvoiceStatusOptions } from '@/lib/enums';
import { formatEuro, formatPeriod } from '@/lib/formatters';
import { formatHours } from '@/lib/utils';
import invoices from '@/routes/invoices';
import type { InvoiceData } from '@/types/generated';

defineProps<{
    items: PaginatedData<InvoiceData>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Invoices', href: invoices.index() }],
    },
});

const { confirmDelete } = useConfirmDelete();

const rowUrl = (invoice: InvoiceData) => invoices.show(invoice.id!);

const isDeletable = (invoice: InvoiceData) =>
    invoice.moneybird_invoice_id === null &&
    invoice.status === InvoiceStatus.Draft;

function deleteInvoice(event: Event, invoice: InvoiceData) {
    void confirmDelete(invoices.destroy(invoice.id!).url, {
        event,
        title: `Delete draft invoice for ${invoice.client_name}?`,
        description:
            'The hours on it become unbilled again so they can be invoiced later.',
    });
}
</script>

<template>
    <Head title="Invoices" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <PageHeader
            title="Invoices"
            subtitle="Drafts prepared from your hours and the invoices that went to Moneybird."
        >
            <template #actions>
                <Button as-child>
                    <Link :href="invoices.prepare.create()">
                        <FilePlus2 class="size-4" />
                        Prepare invoice
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <DataTable :data="items" :row-url="rowUrl" label="invoices">
            <template #rows>
                <DataTableColumn show="number" label="Number">
                    <template #default="{ item }: { item: InvoiceData }">
                        <span class="font-medium">
                            {{ item.number ?? `Draft #${item.id}` }}
                        </span>
                    </template>
                </DataTableColumn>
                <DataTableColumn show="client_name" label="Client">
                    <template #default="{ item }: { item: InvoiceData }">
                        {{ item.client_name }}
                    </template>
                </DataTableColumn>
                <DataTableColumn show="period_starts_on" label="Period">
                    <template #default="{ item }: { item: InvoiceData }">
                        <span class="text-muted-foreground">
                            {{
                                formatPeriod(
                                    item.period_starts_on,
                                    item.period_ends_on,
                                )
                            }}
                        </span>
                    </template>
                </DataTableColumn>
                <DataTableColumn
                    show="status"
                    label="Status"
                    filter-type="select"
                    :filter-options="InvoiceStatusOptions"
                >
                    <template #default="{ item }: { item: InvoiceData }">
                        <InvoiceStatusBadge :status="item.status" />
                    </template>
                </DataTableColumn>
                <DataTableColumn show="total_hours" label="Hours">
                    <template #default="{ item }: { item: InvoiceData }">
                        <span class="tabular-nums">
                            {{
                                item.total_hours
                                    ? formatHours(item.total_hours)
                                    : ''
                            }}
                        </span>
                    </template>
                </DataTableColumn>
                <DataTableColumn show="total" label="Total">
                    <template #default="{ item }: { item: InvoiceData }">
                        <span class="font-medium tabular-nums">
                            {{ formatEuro(item.total) }}
                        </span>
                    </template>
                </DataTableColumn>
                <DataTableColumn show="moneybird_url" label="Moneybird">
                    <template #default="{ item }: { item: InvoiceData }">
                        <a
                            v-if="item.moneybird_url"
                            :href="item.moneybird_url"
                            target="_blank"
                            rel="noopener"
                            class="text-muted-foreground hover:text-primary inline-flex items-center gap-1 transition-colors"
                            title="Open in Moneybird"
                            @click.stop
                        >
                            <ExternalLink class="size-4" />
                        </a>
                    </template>
                </DataTableColumn>
                <DataTableColumn show="actions" label="">
                    <template #default="{ item }: { item: InvoiceData }">
                        <button
                            v-if="isDeletable(item)"
                            type="button"
                            class="text-muted-foreground hover:text-destructive transition-colors"
                            title="Delete draft"
                            @click="deleteInvoice($event, item)"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </template>
                </DataTableColumn>
            </template>
        </DataTable>
    </div>
</template>
