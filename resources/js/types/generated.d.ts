export type LengthAwarePaginator<TKey, TValue> = {
    data: TKey extends string ? Record<TKey, TValue> : TValue[];
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
    meta: {
        total: number;
        current_page: number;
        first_page_url: string;
        from: number | null;
        last_page: number;
        last_page_url: string;
        next_page_url: string | null;
        path: string;
        per_page: number;
        prev_page_url: string | null;
        to: number | null;
    };
};
export type LengthAwarePaginatorInterface<TKey, TValue> = LengthAwarePaginator<
    TKey,
    TValue
>;
export type CursorPaginator<TKey, TValue> = {
    data: TKey extends string ? Record<TKey, TValue> : TValue[];
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
    meta: {
        path: string;
        per_page: number;
        next_cursor: string | null;
        next_page_url: string | null;
        prev_cursor: string | null;
        prev_page_url: string | null;
    };
};
export type CursorPaginatorInterface<TKey, TValue> = CursorPaginator<
    TKey,
    TValue
>;
export type PaginatedDataCollection<TKey, TValue> = LengthAwarePaginator<
    TKey,
    TValue
>;
export type CursorPaginatedDataCollection<TKey, TValue> = CursorPaginator<
    TKey,
    TValue
>;
export type UserData = {
    id: number;
    name: string;
    email: string;
    harvest_id: number | null;
};
export type BaseData = object;
export type TimeEntryData = {
    id: number | null;
    project_id: number;
    task_id: number | null;
    spent_on: string;
    hours: string;
    notes: string | null;
    is_billable: boolean;
    is_billed: boolean;
    is_locked: boolean;
    is_running: boolean;
    timer_started_at: string | null;
    hourly_rate: string | null;
    user_id: number | null;
    invoice_id: number | null;
    harvest_id: number | null;
    project_name: string | null;
    project_code: string | null;
    project_color: string | null;
    client_name: string | null;
    client_id: number | null;
    task_name: string | null;
    user_name: string | null;
};
export type TaskData = {
    id: number | null;
    name: string;
    is_billable_by_default: boolean;
    default_hourly_rate: string | null;
    is_active: boolean;
    harvest_id: number | null;
};
export type ProjectTaskData = {
    id: number | null;
    task_id: number;
    task_name: string | null;
    is_billable: boolean;
    hourly_rate: string | null;
    is_active: boolean;
};
export type ProjectData = {
    id: number | null;
    client_id: number;
    name: string;
    code: string | null;
    is_billable: boolean;
    bill_by: BillBy;
    hourly_rate: string | null;
    budget_hours: string | null;
    is_active: boolean;
    color: string | null;
    starts_on: string | null;
    ends_on: string | null;
    notes: string | null;
    harvest_id: number | null;
    client: ClientData | null;
    tasks: ProjectTaskData[] | null;
    total_hours: string | null;
    unbilled_hours: string | null;
};
export type ClientData = {
    id: number | null;
    name: string;
    email: string | null;
    address: string | null;
    currency: string;
    is_active: boolean;
    harvest_id: number | null;
    moneybird_contact_id: string | null;
    notes: string | null;
    projects_count: number | null;
};
export type InvoiceData = {
    id: number | null;
    client_id: number;
    number: string | null;
    status: InvoiceStatus;
    period_starts_on: string | null;
    period_ends_on: string | null;
    issued_on: string | null;
    due_on: string | null;
    subtotal: string;
    total: string;
    currency: string;
    notes: string | null;
    moneybird_invoice_id: string | null;
    moneybird_url: string | null;
    created_at: string | null;
    client: ClientData | null;
    client_name: string | null;
    lines: InvoiceLineData[] | null;
    total_hours: string | null;
    time_entries_count: number | null;
};
export type InvoiceLineData = {
    id: number | null;
    project_id: number | null;
    task_id: number | null;
    description: string;
    quantity: string;
    unit_price: string;
    amount: string;
    sort_order: number;
    project_name: string | null;
    task_name: string | null;
};
