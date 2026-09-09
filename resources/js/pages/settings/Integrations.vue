<script setup lang="ts">
import { Head, router, useForm, useHttp } from '@inertiajs/vue3';
import {
    CircleCheck,
    CircleX,
    Clock,
    Link2,
    LoaderCircle,
    RefreshCw,
    TriangleAlert,
    Unlink2,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import HarvestImportController from '@/actions/App/Domain/Harvest/Controllers/HarvestImportController';
import HarvestImportProgressController from '@/actions/App/Domain/Harvest/Controllers/HarvestImportProgressController';
import Form from '@/components/Form.vue';
import FormRow from '@/components/FormRow.vue';
import Heading from '@/components/Heading.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import type { StatusBadgeTone } from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useConfirmDelete } from '@/composables/useConfirmDelete';
import { HarvestImportStatus, HarvestImportStatusOptions } from '@/lib/enums';
import { edit } from '@/routes/integrations';
import harvestRoutes from '@/routes/integrations/harvest';
import moneybirdRoutes from '@/routes/integrations/moneybird';
import type {
    HarvestConnectionData,
    HarvestImportData,
    HarvestImportProgressData,
    HarvestImportResultData,
    HarvestIntegrationData,
    MoneybirdConnectionData,
    MoneybirdIntegrationData,
} from '@/types/generated';

const props = defineProps<{
    harvest: HarvestIntegrationData;
    moneybird: MoneybirdIntegrationData;
}>();

const { confirmDelete } = useConfirmDelete();

const harvestForm = useForm<HarvestConnectionData>({
    account_id: '',
    access_token: '',
});

function connectHarvest(): void {
    harvestForm.post(harvestRoutes.store().url, {
        preserveScroll: true,
        onSuccess: () => harvestForm.reset(),
    });
}

function disconnectHarvest(): void {
    void confirmDelete(harvestRoutes.destroy().url, {
        title: 'Disconnect Harvest?',
        description:
            'The token is forgotten. Everything imported so far stays in Kingtime.',
        confirmLabel: 'Disconnect',
    });
}

const moneybirdForm = useForm<MoneybirdConnectionData>({
    access_token: '',
    administration_id: props.moneybird.administration_id ?? '',
    tax_rate_id: props.moneybird.tax_rate_id,
    ledger_account_id: props.moneybird.ledger_account_id,
    workflow_id: props.moneybird.workflow_id,
});

function connectMoneybird(): void {
    moneybirdForm.post(moneybirdRoutes.store().url, {
        preserveScroll: true,
        onSuccess: () => moneybirdForm.reset('access_token'),
    });
}

function disconnectMoneybird(): void {
    void confirmDelete(moneybirdRoutes.destroy().url, {
        title: 'Disconnect Moneybird?',
        description:
            'The token is forgotten. Invoices already in Moneybird keep their link.',
        confirmLabel: 'Disconnect',
    });
}

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Integrations', href: edit() }],
    },
});

const POLL_INTERVAL_MS = 2000;

const importSteps: { key: keyof HarvestImportResultData; label: string }[] = [
    { key: 'clients', label: 'Clients' },
    { key: 'projects', label: 'Projects' },
    { key: 'time_entries', label: 'Time entries' },
];

const statusTones: Record<HarvestImportStatus, StatusBadgeTone> = {
    [HarvestImportStatus.Queued]: 'slate',
    [HarvestImportStatus.Running]: 'blue',
    [HarvestImportStatus.Finished]: 'green',
    [HarvestImportStatus.Failed]: 'red',
};

function statusTone(status: HarvestImportStatus): StatusBadgeTone {
    return statusTones[status];
}

function statusLabel(status: HarvestImportStatus): string {
    return (
        Object.values(HarvestImportStatusOptions).find(
            (option) => option.value === status,
        )?.label ?? status
    );
}

const progress = ref<HarvestImportProgressData | null>(props.harvest.progress);

const isImporting = computed(
    () =>
        progress.value?.status === HarvestImportStatus.Queued ||
        progress.value?.status === HarvestImportStatus.Running,
);

type ProgressResponse = { progress: HarvestImportProgressData | null };

const startRequest = useHttp<Record<string, never>, ProgressResponse>();
const pollRequest = useHttp<Record<string, never>, ProgressResponse>();

let pollTimer: ReturnType<typeof setTimeout> | null = null;

function stopPolling(): void {
    if (pollTimer !== null) {
        clearTimeout(pollTimer);
        pollTimer = null;
    }
}

function schedulePoll(): void {
    stopPolling();
    pollTimer = setTimeout(() => void poll(), POLL_INTERVAL_MS);
}

async function poll(): Promise<void> {
    try {
        const response = await pollRequest.get(
            HarvestImportProgressController.url(),
        );
        progress.value = response.progress;
    } catch {
        schedulePoll();

        return;
    }

    if (isImporting.value) {
        schedulePoll();

        return;
    }

    router.reload({ only: ['harvest'] });
}

async function startImport(): Promise<void> {
    const response = await startRequest.post(
        HarvestImportController.store.url(),
    );
    progress.value = response.progress;

    if (isImporting.value) {
        schedulePoll();
    }
}

if (isImporting.value) {
    schedulePoll();
}

onBeforeUnmount(stopPolling);

const dateTimeFormatter = new Intl.DateTimeFormat('en-GB', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

function formatDateTime(value: string | null): string {
    return value ? dateTimeFormatter.format(new Date(value)) : '';
}

function formatDuration(seconds: number | null): string {
    if (seconds === null) {
        return '';
    }

    if (seconds < 60) {
        return `${seconds}s`;
    }

    return `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
}

function stepState(
    step: keyof HarvestImportResultData,
): 'done' | 'active' | 'pending' {
    const current = progress.value;

    if (!current) {
        return 'pending';
    }

    if (
        current.status === HarvestImportStatus.Finished ||
        current.step === 'done'
    ) {
        return 'done';
    }

    if (current.status !== HarvestImportStatus.Running) {
        return 'pending';
    }

    const currentIndex = importSteps.findIndex(
        (item) => item.key === current.step,
    );
    const index = importSteps.findIndex((item) => item.key === step);

    if (index < currentIndex) {
        return 'done';
    }

    return index === currentIndex ? 'active' : 'pending';
}

function countSummary(counts: HarvestImportResultData | null): string {
    if (!counts) {
        return '';
    }

    return importSteps
        .map(({ key, label }) => {
            const count = counts[key];

            return `${count.created + count.updated} ${label.toLowerCase()}`;
        })
        .join(' · ');
}

function importTitle(item: HarvestImportData): string {
    return item.updated_since
        ? `Incremental since ${formatDateTime(item.updated_since)}`
        : 'Full import';
}
</script>

<template>
    <Head title="Integrations" />

    <h1 class="sr-only">Integrations</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            title="Integrations"
            description="Connect Harvest to import your history and Moneybird to send invoices"
        />

        <Card>
            <CardHeader>
                <div class="flex items-start justify-between gap-4">
                    <div class="space-y-1">
                        <CardTitle>Harvest</CardTitle>
                        <CardDescription>
                            Import clients, projects and your own time entries
                            from your Harvest account.
                        </CardDescription>
                    </div>
                    <StatusBadge
                        v-if="harvest.configured"
                        tone="green"
                        :icon="CircleCheck"
                        label="Configured"
                    />
                    <StatusBadge
                        v-else
                        tone="gray"
                        :icon="CircleX"
                        label="Not configured"
                    />
                </div>
            </CardHeader>

            <CardContent class="space-y-6">
                <template v-if="!harvest.configured">
                    <p class="text-muted-foreground text-sm">
                        Create a personal access token on the
                        <a
                            href="https://id.getharvest.com/developers"
                            target="_blank"
                            rel="noopener"
                            class="text-foreground underline underline-offset-4"
                            >developers page of your Harvest ID account</a
                        >
                        and paste it here together with the account id shown
                        next to it. The token is stored encrypted and only you
                        use it: your hours are imported to your own account.
                    </p>

                    <Form
                        id="harvest-form"
                        :form="harvestForm"
                        @submit="connectHarvest"
                    >
                        <div class="max-w-xl space-y-4">
                            <FormRow
                                label="Account id"
                                field="account_id"
                                required
                            >
                                <Input
                                    v-model="harvestForm.account_id"
                                    inputmode="numeric"
                                    placeholder="123456"
                                    class="w-40"
                                />
                            </FormRow>
                            <FormRow
                                label="Access token"
                                field="access_token"
                                required
                            >
                                <Input
                                    v-model="harvestForm.access_token"
                                    type="password"
                                    autocomplete="off"
                                />
                            </FormRow>
                            <div class="flex justify-end">
                                <Button
                                    type="submit"
                                    :disabled="harvestForm.processing"
                                >
                                    <LoaderCircle
                                        v-if="harvestForm.processing"
                                        class="size-4 animate-spin"
                                    />
                                    <Link2 v-else class="size-4" />
                                    Connect Harvest
                                </Button>
                            </div>
                        </div>
                    </Form>
                </template>

                <template v-else>
                    <div
                        class="flex flex-wrap items-start justify-between gap-4 text-sm"
                    >
                        <div>
                            <p
                                v-if="harvest.account_error"
                                class="text-destructive flex items-start gap-2"
                            >
                                <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                                <span>{{ harvest.account_error }}</span>
                            </p>
                            <p v-else class="text-muted-foreground">
                                Connected as
                                <span class="text-foreground font-medium">{{
                                    harvest.account_name ??
                                    harvest.account_email
                                }}</span>
                                <span
                                    v-if="
                                        harvest.account_email &&
                                        harvest.account_name
                                    "
                                >
                                    ({{ harvest.account_email }})</span
                                >
                                on account {{ harvest.account_id }}.
                            </p>
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="isImporting"
                            @click="disconnectHarvest"
                        >
                            <Unlink2 class="size-4" />
                            Disconnect
                        </Button>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center justify-between gap-4">
                            <div class="text-sm">
                                <p class="font-medium">Last import</p>
                                <p
                                    v-if="harvest.last_import"
                                    class="text-muted-foreground"
                                >
                                    {{
                                        formatDateTime(
                                            harvest.last_import.started_at,
                                        )
                                    }}
                                    <span
                                        v-if="harvest.last_import.counts"
                                        class="block"
                                    >
                                        {{
                                            countSummary(
                                                harvest.last_import.counts,
                                            )
                                        }}
                                    </span>
                                    <span
                                        v-else-if="harvest.last_import.error"
                                        class="text-destructive block"
                                    >
                                        {{ harvest.last_import.error }}
                                    </span>
                                </p>
                                <p v-else class="text-muted-foreground">
                                    No import has run yet.
                                </p>
                            </div>
                            <Button
                                :disabled="
                                    isImporting || startRequest.processing
                                "
                                @click="startImport"
                            >
                                <LoaderCircle
                                    v-if="isImporting"
                                    class="animate-spin"
                                />
                                <RefreshCw v-else />
                                {{
                                    isImporting ? 'Importing' : 'Run import now'
                                }}
                            </Button>
                        </div>

                        <div
                            v-if="progress"
                            class="border-border bg-muted/40 rounded-lg border p-4 text-sm"
                            data-test="harvest-import-progress"
                        >
                            <div class="flex items-center gap-2">
                                <StatusBadge
                                    :tone="statusTone(progress.status)"
                                    :label="statusLabel(progress.status)"
                                />
                                <span class="text-muted-foreground">{{
                                    progress.message
                                }}</span>
                            </div>
                            <p
                                v-if="progress.error"
                                class="text-destructive mt-2"
                            >
                                {{ progress.error }}
                            </p>
                            <ul
                                v-if="
                                    progress.status !==
                                    HarvestImportStatus.Failed
                                "
                                class="mt-3 grid gap-1 sm:grid-cols-2"
                            >
                                <li
                                    v-for="step in importSteps"
                                    :key="step.key"
                                    class="flex items-center gap-2"
                                    :class="
                                        stepState(step.key) === 'pending'
                                            ? 'text-muted-foreground'
                                            : ''
                                    "
                                >
                                    <CircleCheck
                                        v-if="stepState(step.key) === 'done'"
                                        class="size-4 text-green-600"
                                    />
                                    <LoaderCircle
                                        v-else-if="
                                            stepState(step.key) === 'active'
                                        "
                                        class="text-primary size-4 animate-spin"
                                    />
                                    <Clock v-else class="size-4" />
                                    <span>{{ step.label }}</span>
                                    <span
                                        v-if="stepState(step.key) !== 'pending'"
                                        class="text-muted-foreground text-xs"
                                    >
                                        {{ progress.counts[step.key].created }}
                                        new ·
                                        {{ progress.counts[step.key].updated }}
                                        updated
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div v-if="harvest.imports.length > 0" class="space-y-2">
                        <p class="text-sm font-medium">Recent imports</p>
                        <div class="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Started</TableHead>
                                        <TableHead>Scope</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead class="text-right"
                                            >Duration</TableHead
                                        >
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="item in harvest.imports"
                                        :key="item.id"
                                    >
                                        <TableCell class="whitespace-nowrap">
                                            {{
                                                formatDateTime(item.started_at)
                                            }}
                                        </TableCell>
                                        <TableCell
                                            class="text-muted-foreground"
                                        >
                                            {{ importTitle(item) }}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                :tone="statusTone(item.status)"
                                                :label="
                                                    statusLabel(item.status)
                                                "
                                            />
                                        </TableCell>
                                        <TableCell
                                            class="text-muted-foreground text-right whitespace-nowrap"
                                        >
                                            {{
                                                formatDuration(
                                                    item.duration_seconds,
                                                )
                                            }}
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                </template>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <div class="flex items-start justify-between gap-4">
                    <div class="space-y-1">
                        <CardTitle>Moneybird</CardTitle>
                        <CardDescription>
                            Invoices you prepare here are pushed to your
                            Moneybird administration as drafts.
                        </CardDescription>
                    </div>
                    <StatusBadge
                        v-if="moneybird.configured"
                        tone="green"
                        :icon="CircleCheck"
                        label="Connected"
                    />
                    <StatusBadge
                        v-else
                        tone="gray"
                        :icon="CircleX"
                        label="Not connected"
                    />
                </div>
            </CardHeader>
            <CardContent class="space-y-6">
                <div
                    v-if="moneybird.configured"
                    class="flex flex-wrap items-start justify-between gap-4 text-sm"
                >
                    <div>
                        <p
                            v-if="moneybird.administration_error"
                            class="text-destructive flex items-start gap-2"
                        >
                            <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                            <span>{{ moneybird.administration_error }}</span>
                        </p>
                        <p v-else class="text-muted-foreground">
                            Connected to
                            <span class="text-foreground font-medium">{{
                                moneybird.administration_name ??
                                moneybird.administration_id
                            }}</span>
                            <span v-if="moneybird.administration_name">
                                ({{ moneybird.administration_id }})</span
                            >.
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        size="sm"
                        @click="disconnectMoneybird"
                    >
                        <Unlink2 class="size-4" />
                        Disconnect
                    </Button>
                </div>
                <p v-else class="text-muted-foreground text-sm">
                    Create a personal API token under
                    <a
                        href="https://moneybird.com/user/applications"
                        target="_blank"
                        rel="noopener"
                        class="text-foreground underline underline-offset-4"
                        >Applications in your Moneybird user settings</a
                    >
                    and paste it here with the administration id from the
                    Moneybird URL. The token is stored encrypted and only used
                    for invoices you prepare.
                </p>

                <Form
                    id="moneybird-form"
                    :form="moneybirdForm"
                    @submit="connectMoneybird"
                >
                    <div class="max-w-xl space-y-4">
                        <FormRow
                            label="API token"
                            field="access_token"
                            :required="!moneybird.configured"
                        >
                            <Input
                                v-model="moneybirdForm.access_token"
                                type="password"
                                autocomplete="off"
                                :placeholder="
                                    moneybird.configured
                                        ? 'Leave empty to keep the current token'
                                        : ''
                                "
                            />
                        </FormRow>
                        <FormRow
                            label="Administration id"
                            field="administration_id"
                            required
                        >
                            <Input
                                v-model="moneybirdForm.administration_id"
                                inputmode="numeric"
                                class="w-56"
                            />
                        </FormRow>
                        <FormRow label="Tax rate id" field="tax_rate_id">
                            <Input
                                v-model="moneybirdForm.tax_rate_id"
                                inputmode="numeric"
                                class="w-56"
                                placeholder="Optional"
                            />
                        </FormRow>
                        <FormRow
                            label="Ledger account id"
                            field="ledger_account_id"
                        >
                            <Input
                                v-model="moneybirdForm.ledger_account_id"
                                inputmode="numeric"
                                class="w-56"
                                placeholder="Optional"
                            />
                        </FormRow>
                        <FormRow label="Workflow id" field="workflow_id">
                            <Input
                                v-model="moneybirdForm.workflow_id"
                                inputmode="numeric"
                                class="w-56"
                                placeholder="Optional"
                            />
                        </FormRow>
                        <p class="text-muted-foreground text-xs">
                            The optional ids are applied to every invoice line
                            and invoice pushed to Moneybird. Find them in the
                            Moneybird URL of the tax rate, ledger account or
                            workflow, or through the moneybird_get MCP tool
                            (tax_rates, ledger_accounts, workflows).
                        </p>
                        <div class="flex justify-end">
                            <Button
                                type="submit"
                                :disabled="moneybirdForm.processing"
                            >
                                <LoaderCircle
                                    v-if="moneybirdForm.processing"
                                    class="size-4 animate-spin"
                                />
                                <Link2 v-else class="size-4" />
                                {{
                                    moneybird.configured
                                        ? 'Save settings'
                                        : 'Connect Moneybird'
                                }}
                            </Button>
                        </div>
                    </div>
                </Form>
            </CardContent>
        </Card>
    </div>
</template>
