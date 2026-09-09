<?php

declare(strict_types=1);

namespace App\Domain\Mcp\Servers;

use App\Domain\Mcp\Tools\DeleteTimeEntryTool;
use App\Domain\Mcp\Tools\GetRunningTimerTool;
use App\Domain\Mcp\Tools\GetTimesheetTool;
use App\Domain\Mcp\Tools\GetUnbilledSummaryTool;
use App\Domain\Mcp\Tools\ListClientsTool;
use App\Domain\Mcp\Tools\ListProjectsTool;
use App\Domain\Mcp\Tools\ListTimeEntriesTool;
use App\Domain\Mcp\Tools\LogTimeTool;
use App\Domain\Mcp\Tools\MoneybirdGetSalesInvoiceTool;
use App\Domain\Mcp\Tools\MoneybirdGetTool;
use App\Domain\Mcp\Tools\MoneybirdListContactsTool;
use App\Domain\Mcp\Tools\MoneybirdListPurchaseInvoicesTool;
use App\Domain\Mcp\Tools\MoneybirdListReceiptsTool;
use App\Domain\Mcp\Tools\MoneybirdListSalesInvoicesTool;
use App\Domain\Mcp\Tools\MoneybirdRevenueSummaryTool;
use App\Domain\Mcp\Tools\MoneybirdStatusTool;
use App\Domain\Mcp\Tools\PrepareInvoiceTool;
use App\Domain\Mcp\Tools\PreviewInvoiceTool;
use App\Domain\Mcp\Tools\StartTimerTool;
use App\Domain\Mcp\Tools\StopTimerTool;
use App\Domain\Mcp\Tools\UpdateTimeEntryTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Tool;

/**
 * The MCP server an LLM (Claude Desktop, Claude Code, Cursor) talks to.
 * Served over HTTP at /mcp behind a Sanctum token (see routes/ai.php) and
 * over stdio through `php artisan mcp:start kingtime`, where it acts as the
 * first user in the database.
 */
class KingtimeServer extends Server
{
    protected string $name = 'Kingtime';

    protected string $version = '1.0.0';

    /**
     * All tools fit on one tools/list page; not every client follows cursors.
     */
    public int $defaultPaginationLength = 50;

    protected string $instructions = <<<'MARKDOWN'
        Kingtime is the time tracker of a freelancer. These tools log hours, manage the timer and prepare invoices for the user you are authenticated as.

        Workflow:
        1. Find the project first. Call list_projects (optionally with `search` or `client_id`); the project id is what log_time expects. list_clients helps when you only know the client.
        2. Log hours with log_time. Hours are decimal (1.5 = one and a half hours, 0.25 = a quarter), dates are YYYY-MM-DD and default to today. Leave `is_billable` out to inherit the project default. Pass `start_timer: true` to start a running timer instead of logging a fixed number of hours.
        3. Timers: a user has one running timer at a time. Starting another stops the running one and keeps its time. stop_timer without an id stops whatever is running. Running entries report their live hours.
        4. Review with get_timesheet (the Monday to Sunday week around a date, with per-day totals) or list_time_entries (a date range with optional project, client and unbilled filters, plus totals).
        5. Entries that are billed or locked belong to an invoice and can no longer be changed or deleted; the tools say so when it happens.
        6. Invoicing: get_unbilled_summary shows per client what can be invoiced. preview_invoice shows the lines, totals and the plain-text hour specification for a client and period without changing anything. prepare_invoice creates a draft invoice, locks the hours to it and, only when asked, pushes it to Moneybird as a draft. Nothing is ever sent to the customer; the user reviews and sends the invoice in Moneybird.
        7. Bookkeeping questions go to the read-only moneybird_* tools, which query the Moneybird administration directly: moneybird_status (is it configured, which administration), moneybird_list_contacts, moneybird_list_sales_invoices and moneybird_get_sales_invoice (outgoing invoices, with lines and payments), moneybird_list_purchase_invoices and moneybird_list_receipts (costs), moneybird_revenue_summary (invoiced amounts per month and per contact for a period) and moneybird_get for any other GET endpoint. Filters use Moneybird's syntax: `period` is this_month, prev_month, this_quarter, this_year, prev_year or a 202601..202603 range; `state` is draft, open, late, paid, ... or all. Moneybird contact ids are not Kingtime client ids; moneybird_list_contacts shows which Kingtime client is linked to a contact. None of these tools change anything in Moneybird.

        Hours and money are strings with two decimals. Amounts are in the client's currency (EUR by default) and exclude VAT. When a tool returns an error, the message explains what to change; fix the input and retry rather than guessing ids.
        MARKDOWN;

    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        ListClientsTool::class,
        ListProjectsTool::class,
        ListTimeEntriesTool::class,
        GetTimesheetTool::class,
        LogTimeTool::class,
        UpdateTimeEntryTool::class,
        DeleteTimeEntryTool::class,
        StartTimerTool::class,
        StopTimerTool::class,
        GetRunningTimerTool::class,
        GetUnbilledSummaryTool::class,
        PreviewInvoiceTool::class,
        PrepareInvoiceTool::class,
        MoneybirdStatusTool::class,
        MoneybirdListContactsTool::class,
        MoneybirdListSalesInvoicesTool::class,
        MoneybirdGetSalesInvoiceTool::class,
        MoneybirdListPurchaseInvoicesTool::class,
        MoneybirdListReceiptsTool::class,
        MoneybirdRevenueSummaryTool::class,
        MoneybirdGetTool::class,
    ];
}
