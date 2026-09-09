export type LengthAwarePaginator<TKey, TValue> = {
data: TKey extends string ? Record<TKey, TValue> : TValue[],
links: {
url: string | null,
label: string,
active: boolean,
}[],
meta: {
total: number,
current_page: number,
first_page_url: string,
from: number | null,
last_page: number,
last_page_url: string,
next_page_url: string | null,
path: string,
per_page: number,
prev_page_url: string | null,
to: number | null,
},
};
export type LengthAwarePaginatorInterface<TKey, TValue> = LengthAwarePaginator<TKey, TValue>;
export type CursorPaginator<TKey, TValue> = {
data: TKey extends string ? Record<TKey, TValue> : TValue[],
links: {
url: string | null,
label: string,
active: boolean,
}[],
meta: {
path: string,
per_page: number,
next_cursor: string | null,
next_page_url: string | null,
prev_cursor: string | null,
prev_page_url: string | null,
},
};
export type CursorPaginatorInterface<TKey, TValue> = CursorPaginator<TKey, TValue>;
export type PaginatedDataCollection<TKey, TValue> = LengthAwarePaginator<TKey, TValue>;
export type CursorPaginatedDataCollection<TKey, TValue> = CursorPaginator<TKey, TValue>;
export type UserData = {
id: number,
name: string,
email: string,
harvest_id: number | null,
};
export type BaseData = object;
export type TimesheetDayData = {
date: string,
weekday: string,
day_of_month: number,
total_hours: string,
entries_count: number,
is_today: boolean,
};
export type ProjectOptionData = {
id: number,
name: string,
code: string | null,
color: string | null,
client_id: number,
client_name: string,
is_billable: boolean,
};
export type TimeEntryData = {
id: number | null,
project_id: number,
spent_on: string,
hours: string,
notes: string | null,
is_billable: boolean | null,
is_billed: boolean,
is_locked: boolean,
is_running: boolean,
timer_started_at: string | null,
hourly_rate: string | null,
user_id: number | null,
invoice_id: number | null,
harvest_id: number | null,
project_name: string | null,
project_code: string | null,
project_color: string | null,
client_name: string | null,
client_id: number | null,
user_name: string | null,
};
export type TimesheetData = {
selected_date: string,
week_start: string,
week_end: string,
week_total: string,
day_total: string,
days: TimesheetDayData[],
entries: TimeEntryData[],
};
export type HarvestImportProgressData = {
status: HarvestImportStatus,
step: string | null,
message: string,
counts: HarvestImportResultData,
harvest_import_id: number | null,
started_at: string | null,
finished_at: string | null,
error: string | null,
};
export type HarvestImportData = {
id: number,
status: HarvestImportStatus,
started_at: string,
finished_at: string | null,
updated_since: string | null,
counts: HarvestImportResultData | null,
error: string | null,
duration_seconds: number | null,
};
export type HarvestImportCountData = {
created: number,
updated: number,
};
export type HarvestIntegrationData = {
configured: boolean,
account_name: string | null,
account_email: string | null,
account_error: string | null,
last_import: HarvestImportData | null,
imports: HarvestImportData[],
progress: HarvestImportProgressData | null,
};
export type HarvestImportResultData = {
users: HarvestImportCountData,
clients: HarvestImportCountData,
projects: HarvestImportCountData,
time_entries: HarvestImportCountData,
};
export type ProjectData = {
id: number | null,
client_id: number,
name: string,
code: string | null,
is_billable: boolean,
hourly_rate: string | null,
budget_hours: string | null,
is_active: boolean,
color: string | null,
starts_on: string | null,
ends_on: string | null,
notes: string | null,
harvest_id: number | null,
client: ClientData | null,
total_hours: string | null,
unbilled_hours: string | null,
};
export type DashboardStatisticsData = {
billable_ratio: number | null,
billable_ratio_previous: number | null,
billable_ratio_delta: number | null,
average_hours_per_working_day: string,
working_days_this_month: number,
running_timer: TimeEntryData | null,
};
export type DashboardProjectSummaryData = {
id: number,
name: string,
color: string | null,
client_name: string,
hours: string,
percent_of_month: number,
};
export type DashboardChartData = {
range: string,
labels: string[],
values: number[],
};
export type DashboardStatData = {
label: string,
value: string,
previous_value: string,
delta_percent: number | null,
current_label: string,
previous_label: string,
format: string,
};
export type DashboardData = {
stats: DashboardStatsData,
chart: DashboardChartData,
statistics: DashboardStatisticsData,
recent_entries: TimeEntryData[],
top_projects: DashboardProjectSummaryData[],
};
export type DashboardStatsData = {
items: DashboardStatData[],
};
export type ClientData = {
id: number | null,
name: string,
email: string | null,
address: string | null,
currency: string,
is_active: boolean,
harvest_id: number | null,
moneybird_contact_id: string | null,
notes: string | null,
projects_count: number | null,
};
export type ReportsData = {
granularity: string,
from: string,
to: string,
previous_from: string,
previous_to: string,
earliest_entry_on: string | null,
buckets: ReportBucketData[],
totals: ReportTotalsData,
clients: ReportBreakdownRowData[],
projects: ReportBreakdownRowData[],
};
export type ReportTotalsData = {
hours: string,
billable_hours: string,
earned: string,
invoiced: string,
entry_count: number,
previous_hours: string,
previous_billable_hours: string,
previous_earned: string,
previous_invoiced: string,
hours_delta_percent: number | null,
billable_hours_delta_percent: number | null,
earned_delta_percent: number | null,
invoiced_delta_percent: number | null,
};
export type ReportsFilterData = {
granularity: string,
from: string | null,
to: string | null,
client_id: number | null,
project_id: number | null,
billable_only: boolean,
};
export type ReportBucketData = {
period: string,
label: string,
starts_on: string,
ends_on: string,
hours: string,
billable_hours: string,
earned: string,
invoiced: string,
entry_count: number,
};
export type ReportBreakdownRowData = {
id: number,
name: string,
client_name: string | null,
color: string | null,
hours: string,
billable_hours: string,
earned: string,
entry_count: number,
share_percent: number,
};
export type InvoiceSpecificationData = {
client: ClientData,
period_starts_on: string,
period_ends_on: string,
lines: InvoiceLineData[],
subtotal: string,
total_hours: string,
entries: TimeEntryData[],
unpriced_entries: number,
specification_text: string,
};
export type InvoiceData = {
id: number | null,
client_id: number,
number: string | null,
status: InvoiceStatus,
period_starts_on: string | null,
period_ends_on: string | null,
issued_on: string | null,
due_on: string | null,
subtotal: string,
total: string,
currency: string,
notes: string | null,
specification: string | null,
moneybird_invoice_id: string | null,
moneybird_url: string | null,
created_at: string | null,
client: ClientData | null,
client_name: string | null,
lines: InvoiceLineData[] | null,
total_hours: string | null,
time_entries_count: number | null,
time_entries: TimeEntryData[] | null,
};
export type InvoiceLineData = {
id: number | null,
project_id: number | null,
description: string,
quantity: string,
unit_price: string,
amount: string,
sort_order: number,
project_name: string | null,
};
export type PrepareInvoiceData = {
client_id: number,
period_starts_on: string,
period_ends_on: string,
time_entry_ids: number[] | null,
notes: string | null,
push_to_moneybird: boolean,
};
