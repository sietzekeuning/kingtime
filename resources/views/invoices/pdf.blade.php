@php
    use App\Domain\Invoice\Enums\InvoiceStatus;
    use App\Domain\Time\Models\TimeEntry;
    use Illuminate\Support\Number;

    /** @var \App\Domain\Invoice\Models\Invoice $invoice */
    $user = $invoice->user;
    $client = $invoice->client;
    $isDraft = $invoice->status === InvoiceStatus::Draft && $invoice->number === null;
    $title = $invoice->number !== null ? "Invoice {$invoice->number}" : "Draft invoice #{$invoice->id}";
    $money = fn (string|float|int|null $amount): string => (string) Number::currency((float) ($amount ?? 0), in: $invoice->currency, locale: 'nl');
    $hours = fn (string|float|int|null $amount): string => number_format((float) ($amount ?? 0), 2, ',', '.');
    $issuedOn = $invoice->issued_on ?? $invoice->created_at;
    $byProject = $invoice->timeEntries
        ->groupBy('project_id')
        ->sortBy(fn ($entries) => mb_strtolower($entries->first()?->project?->name ?? ''));
    $hasVat = (float) $invoice->total !== (float) $invoice->subtotal;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { size: A4; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Helvetica, Arial, "Liberation Sans", sans-serif;
            font-size: 10.5pt;
            line-height: 1.45;
            color: #1c1917;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .muted { color: #78716c; }
        .accent { color: #ea580c; }
        .num { font-variant-numeric: tabular-nums; text-align: right; white-space: nowrap; }
        .pre { white-space: pre-line; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; padding-bottom: 14px; border-bottom: 3px solid #ea580c; }
        .brand { font-size: 20pt; font-weight: 700; letter-spacing: -0.02em; margin: 0; }
        .brand small { display: block; font-size: 9pt; font-weight: 400; color: #78716c; letter-spacing: 0; margin-top: 2px; }
        .sender { text-align: right; font-size: 9pt; }
        .title { font-size: 22pt; font-weight: 700; letter-spacing: -0.02em; margin: 26px 0 2px; }
        .badge { display: inline-block; font-size: 8pt; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; padding: 2px 8px; border-radius: 999px; background: #fff7ed; color: #c2410c; vertical-align: middle; margin-left: 8px; }
        .meta { display: flex; gap: 32px; margin: 18px 0 26px; }
        .meta .block { min-width: 150px; }
        .meta .block.grow { flex: 1; }
        .label { font-size: 7.5pt; font-weight: 600; text-transform: uppercase; letter-spacing: 0.1em; color: #a8a29e; margin-bottom: 3px; }
        table { width: 100%; border-collapse: collapse; }
        th { font-size: 7.5pt; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: #78716c; text-align: left; padding: 6px 8px; border-bottom: 1px solid #d6d3d1; }
        th.num { text-align: right; }
        td { padding: 7px 8px; border-bottom: 1px solid #f5f5f4; vertical-align: top; }
        tfoot td { border-bottom: 0; border-top: 1px solid #d6d3d1; font-weight: 600; }
        tfoot tr.total td { font-size: 12pt; padding-top: 10px; border-top: 0; }
        tfoot tr.total td.num { color: #ea580c; }
        .section { margin-top: 30px; }
        .section h2 { font-size: 12pt; margin: 0 0 8px; }
        .spec h3 { font-size: 10pt; margin: 16px 0 4px; }
        .spec td { padding: 4px 8px; font-size: 9.5pt; }
        .spec td.date { width: 84px; white-space: nowrap; }
        .spec td.hours { width: 70px; }
        .spec tr.subtotal td { border-bottom: 0; font-weight: 600; color: #57534e; }
        .notes { margin-top: 24px; padding: 12px 14px; background: #fafaf9; border-radius: 8px; font-size: 9.5pt; }
        .footer { margin-top: 28px; padding-top: 10px; border-top: 1px solid #e7e5e4; font-size: 8.5pt; color: #78716c; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <header class="header">
        <div>
            <p class="brand">
                {{ $user->company_name ?? $user->name }}
                <small>{{ $user->company_name !== null ? $user->name : $user->email }}</small>
            </p>
        </div>
        <div class="sender">
            @if ($user->company_address !== null)
                <div class="pre">{{ $user->company_address }}</div>
            @endif
            @if ($user->company_name !== null)
                <div>{{ $user->email }}</div>
            @endif
            @if ($user->vat_number !== null)
                <div>VAT {{ $user->vat_number }}</div>
            @endif
            @if ($user->coc_number !== null)
                <div>CoC {{ $user->coc_number }}</div>
            @endif
        </div>
    </header>

    <h1 class="title">
        {{ $title }}
        @if ($isDraft)
            <span class="badge">Draft</span>
        @endif
    </h1>
    <div class="muted">{{ $invoice->periodLabel() }}</div>

    <section class="meta">
        <div class="block grow">
            <div class="label">Billed to</div>
            <div><strong>{{ $client->name }}</strong></div>
            @if ($client->address !== null)
                <div class="pre">{{ $client->address }}</div>
            @endif
            @if ($client->email !== null)
                <div class="muted">{{ $client->email }}</div>
            @endif
        </div>
        <div class="block">
            <div class="label">Invoice date</div>
            <div>{{ $issuedOn?->format('d-m-Y') }}</div>
        </div>
        @if ($invoice->due_on !== null)
            <div class="block">
                <div class="label">Due date</div>
                <div>{{ $invoice->due_on->format('d-m-Y') }}</div>
            </div>
        @endif
        <div class="block">
            <div class="label">Period</div>
            <div>{{ $invoice->period_starts_on?->format('d-m-Y') }} to {{ $invoice->period_ends_on?->format('d-m-Y') }}</div>
        </div>
    </section>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th class="num">Hours</th>
                <th class="num">Rate</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->lines as $line)
                <tr>
                    <td>{{ $line->description }}</td>
                    <td class="num">{{ $hours($line->quantity) }}</td>
                    <td class="num">{{ $money($line->unit_price) }}</td>
                    <td class="num">{{ $money($line->amount) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td>Subtotal (excl. VAT)</td>
                <td class="num">{{ $hours($invoice->lines->sum('quantity')) }}</td>
                <td></td>
                <td class="num">{{ $money($invoice->subtotal) }}</td>
            </tr>
            @if ($hasVat)
                <tr class="total">
                    <td colspan="3">Total (incl. VAT)</td>
                    <td class="num">{{ $money($invoice->total) }}</td>
                </tr>
            @else
                <tr class="total">
                    <td colspan="3">Total</td>
                    <td class="num">{{ $money($invoice->subtotal) }}</td>
                </tr>
            @endif
        </tfoot>
    </table>

    @if ($invoice->notes !== null)
        <div class="notes pre">{{ $invoice->notes }}</div>
    @endif

    @if ($user->iban !== null)
        <p class="footer">
            Please transfer the amount to {{ $user->iban }}{{ $user->company_name !== null ? ' in the name of '.$user->company_name : '' }}, quoting {{ $invoice->number ?? $title }}.
        </p>
    @endif

    @if ($byProject->isNotEmpty())
        <section class="section spec page-break">
            <h2>Hour specification <span class="muted">· {{ $invoice->periodLabel() }}</span></h2>
            @foreach ($byProject as $entries)
                @php /** @var \Illuminate\Support\Collection<int, TimeEntry> $entries */ $first = $entries->first(); @endphp
                <h3>{{ $first?->project?->name }}</h3>
                <table>
                    <tbody>
                        @foreach ($entries as $entry)
                            <tr>
                                <td class="date">{{ $entry->spent_on->format('d-m-Y') }}</td>
                                <td class="num hours">{{ $hours($entry->hours) }}</td>
                                <td>{{ $entry->notes }}</td>
                            </tr>
                        @endforeach
                        <tr class="subtotal">
                            <td>Subtotal</td>
                            <td class="num hours">{{ $hours($entries->sum(fn (TimeEntry $entry) => (float) $entry->hours)) }}</td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            @endforeach
            <p class="footer">Total: {{ $hours($invoice->timeEntries->sum(fn (TimeEntry $entry) => (float) $entry->hours)) }} hours over {{ $invoice->timeEntries->count() }} entries.</p>
        </section>
    @endif
</body>
</html>
