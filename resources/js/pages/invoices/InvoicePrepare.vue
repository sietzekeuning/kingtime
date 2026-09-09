<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { AlertTriangle, FilePlus2, Receipt } from '@lucide/vue';
import dayjs from 'dayjs';
import debounce from 'lodash/debounce';
import { computed, ref, watch } from 'vue';
import EmptyState from '@/components/EmptyState.vue';
import Form from '@/components/Form.vue';
import FormRow from '@/components/FormRow.vue';
import InvoiceEntryList from '@/components/invoices/InvoiceEntryList.vue';
import InvoiceLinesTable from '@/components/invoices/InvoiceLinesTable.vue';
import SpecificationBlock from '@/components/invoices/SpecificationBlock.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { formatEuro } from '@/lib/formatters';
import { formatHours } from '@/lib/utils';
import invoices from '@/routes/invoices';
import type {
    InvoiceSpecificationData,
    TimeEntryData,
} from '@/types/generated';

interface ClientOption {
    id: number;
    name: string;
}

interface PrepareFilters {
    client_id: number | null;
    period_starts_on: string;
    period_ends_on: string;
    time_entry_ids: number[] | null;
}

const props = defineProps<{
    clients: ClientOption[];
    filters: PrepareFilters;
    specification: InvoiceSpecificationData | null;
    available_entries: TimeEntryData[] | null;
    moneybird_configured: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Invoices', href: invoices.index() },
            { title: 'Prepare invoice', href: invoices.prepare.create() },
        ],
    },
});

const form = useForm({
    client_id: props.filters.client_id,
    period_starts_on: props.filters.period_starts_on,
    period_ends_on: props.filters.period_ends_on,
    time_entry_ids: null as number[] | null,
    notes: '',
    push_to_moneybird: false,
});

/** Reka's Select works with string values; the form keeps the numeric id. */
const clientId = computed({
    get: () => (form.client_id === null ? null : String(form.client_id)),
    set: (value: string | null) => {
        form.client_id = value === null || value === '' ? null : Number(value);
    },
});

const entries = computed<TimeEntryData[]>(() => props.available_entries ?? []);

/** Ids the user unticked; the preview and the draft leave these out. */
const excluded = ref<number[]>(
    props.filters.time_entry_ids === null
        ? []
        : entries.value
              .map((entry) => entry.id!)
              .filter((id) => !props.filters.time_entry_ids!.includes(id)),
);

const includedIds = computed(() =>
    entries.value
        .map((entry) => entry.id!)
        .filter((id) => !excluded.value.includes(id)),
);

const canPreview = computed(
    () =>
        form.client_id !== null &&
        dayjs(form.period_starts_on).isValid() &&
        dayjs(form.period_ends_on).isValid() &&
        !dayjs(form.period_ends_on).isBefore(form.period_starts_on),
);

const loading = ref(false);

const reloadPreview = debounce(() => {
    if (!canPreview.value) {
        return;
    }

    loading.value = true;

    router.get(
        invoices.prepare.create.url(),
        {
            client_id: form.client_id,
            period_starts_on: form.period_starts_on,
            period_ends_on: form.period_ends_on,
            ...(excluded.value.length > 0
                ? { time_entry_ids: includedIds.value }
                : {}),
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['specification', 'available_entries', 'filters', 'errors'],
            onFinish: () => (loading.value = false),
        },
    );
}, 300);

watch(
    () => [form.client_id, form.period_starts_on, form.period_ends_on],
    () => {
        excluded.value = [];
        reloadPreview();
    },
);

watch(excluded, () => reloadPreview(), { deep: true });

function setPeriod(from: dayjs.Dayjs, to: dayjs.Dayjs) {
    form.period_starts_on = from.format('YYYY-MM-DD');
    form.period_ends_on = to.format('YYYY-MM-DD');
}

const presets = [
    {
        label: 'Last month',
        apply: () => {
            const month = dayjs().subtract(1, 'month');
            setPeriod(month.startOf('month'), month.endOf('month'));
        },
    },
    {
        label: 'This month',
        apply: () =>
            setPeriod(dayjs().startOf('month'), dayjs().endOf('month')),
    },
    {
        label: 'Last quarter',
        apply: () => {
            const start = dayjs()
                .subtract(3, 'month')
                .startOf('month')
                .month(
                    Math.floor(dayjs().subtract(3, 'month').month() / 3) * 3,
                );
            setPeriod(start, start.add(2, 'month').endOf('month'));
        },
    },
];

const hasHours = computed(
    () =>
        props.specification !== null && props.specification.entries.length > 0,
);

function submit() {
    form.transform((data) => ({
        ...data,
        time_entry_ids: excluded.value.length > 0 ? includedIds.value : null,
    })).post(invoices.prepare.store().url);
}
</script>

<template>
    <Head title="Prepare invoice" />

    <div class="flex flex-1 flex-col gap-4 p-4 md:p-6">
        <PageHeader
            title="Prepare invoice"
            subtitle="Pick a client and a period; the unbilled hours become the invoice lines."
            :back-href="invoices.index()"
            back-label="Invoices"
        >
            <template #actions>
                <Button
                    form="prepare-invoice-form"
                    type="submit"
                    :disabled="form.processing || !hasHours || loading"
                >
                    <FilePlus2 class="size-4" />
                    Create draft invoice
                </Button>
            </template>
        </PageHeader>

        <Form id="prepare-invoice-form" :form="form" @submit="submit">
            <div class="grid gap-4 xl:grid-cols-[2fr_3fr]">
                <div class="flex flex-col gap-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>Client and period</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-4">
                                <FormRow
                                    label="Client"
                                    field="client_id"
                                    required
                                >
                                    <Select v-model="clientId">
                                        <SelectTrigger class="w-full">
                                            <SelectValue
                                                placeholder="Select client"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="client in clients"
                                                :key="client.id"
                                                :value="String(client.id)"
                                            >
                                                {{ client.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </FormRow>
                                <FormRow
                                    label="From"
                                    field="period_starts_on"
                                    required
                                >
                                    <Input
                                        v-model="form.period_starts_on"
                                        type="date"
                                    />
                                </FormRow>
                                <FormRow
                                    label="To"
                                    field="period_ends_on"
                                    required
                                >
                                    <Input
                                        v-model="form.period_ends_on"
                                        type="date"
                                    />
                                </FormRow>
                                <FormRow label="Presets">
                                    <div class="flex flex-wrap gap-2">
                                        <Button
                                            v-for="preset in presets"
                                            :key="preset.label"
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            @click="preset.apply()"
                                        >
                                            {{ preset.label }}
                                        </Button>
                                    </div>
                                </FormRow>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Draft</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-4">
                                <FormRow label="Notes" field="notes">
                                    <Textarea
                                        v-model="form.notes"
                                        rows="3"
                                        placeholder="Internal notes for this invoice (not sent to Moneybird)"
                                    />
                                </FormRow>
                                <FormRow
                                    label="Moneybird"
                                    field="push_to_moneybird"
                                >
                                    <div class="flex items-start gap-2">
                                        <Checkbox
                                            id="push_to_moneybird"
                                            v-model="form.push_to_moneybird"
                                            :disabled="!moneybird_configured"
                                            class="mt-0.5"
                                        />
                                        <div>
                                            <Label
                                                for="push_to_moneybird"
                                                class="font-normal"
                                            >
                                                Push to Moneybird as a draft
                                                right away
                                            </Label>
                                            <p
                                                v-if="!moneybird_configured"
                                                class="text-muted-foreground mt-1 text-xs"
                                            >
                                                Configure Moneybird under
                                                Settings · Integrations to
                                                enable this.
                                            </p>
                                        </div>
                                    </div>
                                </FormRow>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div
                    class="flex flex-col gap-4"
                    :class="{ 'opacity-60': loading }"
                >
                    <Card v-if="specification === null">
                        <CardContent>
                            <EmptyState
                                variant="centered"
                                :icon="Receipt"
                                title="Choose a client"
                                description="The unbilled hours in the period show up here, grouped into invoice lines."
                            />
                        </CardContent>
                    </Card>

                    <template v-else>
                        <Alert
                            v-if="specification.unpriced_entries > 0"
                            class="border-amber-200 bg-amber-50 text-amber-900"
                        >
                            <AlertTriangle class="size-4" />
                            <AlertTitle>
                                {{ specification.unpriced_entries }}
                                {{
                                    specification.unpriced_entries === 1
                                        ? 'entry has'
                                        : 'entries have'
                                }}
                                no hourly rate
                            </AlertTitle>
                            <AlertDescription class="text-amber-800">
                                Their hours are on the invoice at 0,00. Give the
                                project a rate, untick them here, or fill in the
                                price in Moneybird.
                            </AlertDescription>
                        </Alert>

                        <Card>
                            <CardHeader>
                                <CardTitle
                                    class="flex items-baseline justify-between gap-4"
                                >
                                    <span>Invoice lines</span>
                                    <span
                                        class="text-muted-foreground text-sm font-normal tabular-nums"
                                    >
                                        {{
                                            formatHours(
                                                specification.total_hours,
                                            )
                                        }}
                                        h ·
                                        {{ formatEuro(specification.subtotal) }}
                                    </span>
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <EmptyState
                                    v-if="entries.length === 0"
                                    description="No unbilled billable hours for this client in this period."
                                />
                                <InvoiceLinesTable
                                    v-else
                                    :lines="specification.lines"
                                    :subtotal="specification.subtotal"
                                    :total-hours="specification.total_hours"
                                />
                            </CardContent>
                        </Card>

                        <Card v-if="entries.length > 0">
                            <CardHeader>
                                <CardTitle
                                    class="flex items-baseline justify-between gap-4"
                                >
                                    <span>Time entries</span>
                                    <span
                                        class="text-muted-foreground text-sm font-normal"
                                    >
                                        {{ includedIds.length }} of
                                        {{ entries.length }} included
                                    </span>
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <InvoiceEntryList
                                    v-model:excluded="excluded"
                                    :entries="entries"
                                    selectable
                                />
                            </CardContent>
                        </Card>

                        <Card v-if="entries.length > 0">
                            <CardHeader>
                                <CardTitle>Specification</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <SpecificationBlock
                                    :text="specification.specification_text"
                                />
                            </CardContent>
                        </Card>
                    </template>
                </div>
            </div>
        </Form>
    </div>
</template>
