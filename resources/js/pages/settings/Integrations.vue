<script setup lang="ts">
import { Head, router, useHttp } from '@inertiajs/vue3';
import {
    CircleCheck,
    CircleX,
    Clock,
    LoaderCircle,
    RefreshCw,
    TriangleAlert,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';
import HarvestImportController from '@/actions/App/Domain/Harvest/Controllers/HarvestImportController';
import HarvestImportProgressController from '@/actions/App/Domain/Harvest/Controllers/HarvestImportProgressController';
import Heading from '@/components/Heading.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import type { StatusBadgeTone } from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
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
import { HarvestImportStatus, HarvestImportStatusOptions } from '@/lib/enums';
import { edit } from '@/routes/integrations';
import type {
    HarvestImportData,
    HarvestImportProgressData,
    HarvestImportResultData,
    HarvestIntegrationData,
} from '@/types/generated';

const props = defineProps<{
    harvest: HarvestIntegrationData;
    moneybird: { configured: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Integrations', href: edit() }],
    },
});

const POLL_INTERVAL_MS = 2000;

const importSteps: { key: keyof HarvestImportResultData; label: string }[] = [
    { key: 'users', label: 'Users' },
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
                            Import users, clients, projects and time entries
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
                <p
                    v-if="!harvest.configured"
                    class="text-muted-foreground text-sm"
                >
                    Add
                    <code class="bg-muted rounded px-1 py-0.5 text-xs"
                        >HARVEST_ACCOUNT_ID</code
                    >
                    and
                    <code class="bg-muted rounded px-1 py-0.5 text-xs"
                        >HARVEST_ACCESS_TOKEN</code
                    >
                    to your <code class="text-xs">.env</code> file. Both come
                    from the developers page of your Harvest ID account.
                </p>

                <template v-else>
                    <div class="text-sm">
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
                                harvest.account_name ?? harvest.account_email
                            }}</span>
                            <span
                                v-if="
                                    harvest.account_email &&
                                    harvest.account_name
                                "
                            >
                                ({{ harvest.account_email }})</span
                            >.
                        </p>
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
                            Invoices prepared here are pushed to Moneybird as
                            drafts.
                        </CardDescription>
                    </div>
                    <StatusBadge
                        v-if="moneybird.configured"
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
            <CardContent>
                <p
                    v-if="!moneybird.configured"
                    class="text-muted-foreground text-sm"
                >
                    Add
                    <code class="bg-muted rounded px-1 py-0.5 text-xs"
                        >MONEYBIRD_ACCESS_TOKEN</code
                    >
                    and
                    <code class="bg-muted rounded px-1 py-0.5 text-xs"
                        >MONEYBIRD_ADMINISTRATION_ID</code
                    >
                    to your <code class="text-xs">.env</code> file. Create a
                    personal token under Applications in your Moneybird user
                    settings.
                </p>
                <p v-else class="text-muted-foreground text-sm">
                    Moneybird is connected. Invoices can be sent as drafts from
                    the invoices page.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
