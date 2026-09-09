<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ExternalLink, FileDown, RefreshCw, Send, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import InvoiceEntryList from '@/components/invoices/InvoiceEntryList.vue';
import InvoiceLinesTable from '@/components/invoices/InvoiceLinesTable.vue';
import InvoiceStatusBadge from '@/components/invoices/InvoiceStatusBadge.vue';
import SpecificationBlock from '@/components/invoices/SpecificationBlock.vue';
import MetricCard from '@/components/MetricCard.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useConfirmDelete } from '@/composables/useConfirmDelete';
import { InvoiceStatus } from '@/lib/enums';
import { formatDate, formatEuro, formatPeriod } from '@/lib/formatters';
import { formatHours } from '@/lib/utils';
import invoices from '@/routes/invoices';
import type { InvoiceData } from '@/types/generated';

const props = defineProps<{
    invoice: InvoiceData;
    moneybird_configured: boolean;
}>();

defineOptions({
    layout: ({ invoice }: { invoice: InvoiceData }) => ({
        breadcrumbs: [
            { title: 'Invoices', href: invoices.index() },
            {
                title: invoice.number
                    ? `Invoice ${invoice.number}`
                    : `Draft invoice #${invoice.id}`,
                href: invoices.show(invoice.id!),
            },
        ],
    }),
});

const title = (invoice: InvoiceData) =>
    invoice.number
        ? `Invoice ${invoice.number}`
        : `Draft invoice #${invoice.id}`;

const { confirmDelete } = useConfirmDelete();

const busy = ref(false);
const isPushed = computed(() => props.invoice.moneybird_invoice_id !== null);
const isDraft = computed(() => props.invoice.status === InvoiceStatus.Draft);

function push() {
    busy.value = true;
    router.post(
        invoices.push(props.invoice.id!).url,
        {},
        { preserveScroll: true, onFinish: () => (busy.value = false) },
    );
}

function sync() {
    busy.value = true;
    router.post(
        invoices.sync(props.invoice.id!).url,
        {},
        { preserveScroll: true, onFinish: () => (busy.value = false) },
    );
}

function destroy() {
    const url = isPushed.value
        ? invoices.destroy(props.invoice.id!, { query: { force: 1 } }).url
        : invoices.destroy(props.invoice.id!).url;

    void confirmDelete(url, {
        title: `Delete ${title(props.invoice).toLowerCase()}?`,
        description: isPushed.value
            ? 'The copy in Moneybird stays; only the local invoice is removed and its hours become unbilled again.'
            : 'The hours on it become unbilled again so they can be invoiced later.',
    });
}
</script>

<template>
    <Head :title="title(invoice)" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <PageHeader
            :title="title(invoice)"
            :subtitle="`${invoice.client_name} · ${formatPeriod(invoice.period_starts_on, invoice.period_ends_on)}`"
            :back-href="invoices.index()"
            back-label="Invoices"
        >
            <template #actions>
                <Button
                    v-if="isDraft || isPushed"
                    variant="outline"
                    class="text-destructive"
                    @click="destroy"
                >
                    <Trash2 class="size-4" />
                    Delete
                </Button>
                <Button
                    v-if="isPushed"
                    variant="outline"
                    :disabled="busy"
                    @click="sync"
                >
                    <RefreshCw
                        class="size-4"
                        :class="{ 'animate-spin': busy }"
                    />
                    Refresh status
                </Button>
                <Button variant="outline" as-child>
                    <a
                        :href="invoices.pdf(invoice.id!).url"
                        target="_blank"
                        rel="noopener"
                    >
                        <FileDown class="size-4" />
                        Download PDF
                    </a>
                </Button>
                <Button v-if="invoice.moneybird_url" variant="outline" as-child>
                    <a
                        :href="invoice.moneybird_url"
                        target="_blank"
                        rel="noopener"
                    >
                        <ExternalLink class="size-4" />
                        Open in Moneybird
                    </a>
                </Button>
                <Button
                    v-if="!isPushed"
                    :disabled="busy || !moneybird_configured"
                    :title="
                        moneybird_configured
                            ? undefined
                            : 'Configure Moneybird under Settings · Integrations first.'
                    "
                    @click="push"
                >
                    <Send class="size-4" />
                    Push to Moneybird
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <Card class="gap-0 p-4">
                <div
                    class="text-muted-foreground text-xs tracking-wide uppercase"
                >
                    Status
                </div>
                <div class="mt-2">
                    <InvoiceStatusBadge :status="invoice.status" />
                </div>
                <dl class="text-muted-foreground mt-3 space-y-1 text-xs">
                    <div
                        v-if="invoice.issued_on"
                        class="flex justify-between gap-2"
                    >
                        <dt>Issued</dt>
                        <dd class="text-foreground">
                            {{ formatDate(invoice.issued_on) }}
                        </dd>
                    </div>
                    <div
                        v-if="invoice.due_on"
                        class="flex justify-between gap-2"
                    >
                        <dt>Due</dt>
                        <dd class="text-foreground">
                            {{ formatDate(invoice.due_on) }}
                        </dd>
                    </div>
                    <div
                        v-if="!invoice.issued_on && !invoice.due_on"
                        class="flex justify-between gap-2"
                    >
                        <dt>Created</dt>
                        <dd class="text-foreground">
                            {{ formatDate(invoice.created_at) }}
                        </dd>
                    </div>
                </dl>
            </Card>
            <MetricCard
                label="Total"
                :value="formatEuro(invoice.total)"
                :hint="isPushed ? 'incl. VAT, from Moneybird' : 'excl. VAT'"
            />
            <MetricCard
                label="Hours"
                :value="formatHours(invoice.total_hours)"
                :hint="`${invoice.time_entries_count ?? 0} time entries`"
            />
            <MetricCard
                label="Client"
                :value="invoice.client_name ?? ''"
                :hint="
                    invoice.client?.moneybird_contact_id
                        ? `Moneybird contact #${invoice.client.moneybird_contact_id}`
                        : 'Not linked to a Moneybird contact yet'
                "
            />
        </div>

        <div class="grid gap-4 xl:grid-cols-[3fr_2fr]">
            <div class="flex flex-col gap-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Invoice lines</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <InvoiceLinesTable
                            :lines="invoice.lines ?? []"
                            :subtotal="invoice.subtotal"
                            :total="invoice.total"
                            :total-hours="invoice.total_hours ?? '0.00'"
                        />
                    </CardContent>
                </Card>

                <Card v-if="invoice.specification">
                    <CardHeader>
                        <CardTitle>Specification</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <SpecificationBlock :text="invoice.specification" />
                    </CardContent>
                </Card>

                <Card v-if="invoice.notes">
                    <CardHeader>
                        <CardTitle>Notes</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <p class="text-sm whitespace-pre-line">
                            {{ invoice.notes }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <Card class="self-start">
                <CardHeader>
                    <CardTitle>Time entries</CardTitle>
                </CardHeader>
                <CardContent>
                    <InvoiceEntryList :entries="invoice.time_entries ?? []" />
                </CardContent>
            </Card>
        </div>
    </div>
</template>
