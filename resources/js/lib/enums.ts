// Auto-generated TypeScript enums

export type EnumOption = {
    value: string;
    label: string;
    colorClass: string;
};

export type EnumOptions = Record<string, EnumOption>;

export enum BillBy {
    Project = "project",
    Task = "task",
    None = "none",
}

export const BillByOptions: EnumOptions = {
    Project: {
        value: "project",
        label: "Project rate",
        colorClass: "bg-orange-100 text-orange-700",
    },
    Task: {
        value: "task",
        label: "Task rate",
        colorClass: "bg-amber-100 text-amber-700",
    },
    None: {
        value: "none",
        label: "Not billable",
        colorClass: "bg-slate-100 text-slate-700",
    },
};

export enum InvoiceStatus {
    Draft = "draft",
    Open = "open",
    Paid = "paid",
    Late = "late",
    Uncollectible = "uncollectible",
}

export const InvoiceStatusOptions: EnumOptions = {
    Draft: {
        value: "draft",
        label: "Draft",
        colorClass: "bg-slate-100 text-slate-700",
    },
    Open: {
        value: "open",
        label: "Open",
        colorClass: "bg-blue-100 text-blue-700",
    },
    Paid: {
        value: "paid",
        label: "Paid",
        colorClass: "bg-emerald-100 text-emerald-700",
    },
    Late: {
        value: "late",
        label: "Late",
        colorClass: "bg-rose-100 text-rose-700",
    },
    Uncollectible: {
        value: "uncollectible",
        label: "Uncollectible",
        colorClass: "bg-gray-200 text-gray-600",
    },
};
