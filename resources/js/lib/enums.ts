// Auto-generated TypeScript enums

export type EnumOption = {
    value: string
    label: string
    colorClass: string
}

export type EnumOptions = Record<string, EnumOption>

export enum InvoiceStatus {
    Draft = 'draft',
    Open = 'open',
    Paid = 'paid',
    Late = 'late',
    Uncollectible = 'uncollectible',
}

export const InvoiceStatusOptions: EnumOptions = {
    Draft: {
        value: 'draft',
        label: 'Draft',
        colorClass: 'bg-slate-100 text-slate-700',
    },
    Open: {
        value: 'open',
        label: 'Open',
        colorClass: 'bg-blue-100 text-blue-700',
    },
    Paid: {
        value: 'paid',
        label: 'Paid',
        colorClass: 'bg-emerald-100 text-emerald-700',
    },
    Late: {
        value: 'late',
        label: 'Late',
        colorClass: 'bg-rose-100 text-rose-700',
    },
    Uncollectible: {
        value: 'uncollectible',
        label: 'Uncollectible',
        colorClass: 'bg-gray-200 text-gray-600',
    },
};

export enum HarvestImportStatus {
    Queued = 'queued',
    Running = 'running',
    Finished = 'finished',
    Failed = 'failed',
}

export const HarvestImportStatusOptions: EnumOptions = {
    Queued: {
        value: 'queued',
        label: 'Queued',
        colorClass: 'bg-slate-100 text-slate-700',
    },
    Running: {
        value: 'running',
        label: 'Running',
        colorClass: 'bg-blue-100 text-blue-700',
    },
    Finished: {
        value: 'finished',
        label: 'Finished',
        colorClass: 'bg-green-100 text-green-700',
    },
    Failed: {
        value: 'failed',
        label: 'Failed',
        colorClass: 'bg-red-100 text-red-700',
    },
};

