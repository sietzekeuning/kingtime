<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Models;

use App\Domain\Client\Models\Client;
use App\Domain\Invoice\Enums\InvoiceStatus;
use App\Domain\Time\Models\TimeEntry;
use Carbon\CarbonInterface;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $client_id
 * @property string|null $number
 * @property InvoiceStatus $status
 * @property Carbon|null $period_starts_on
 * @property Carbon|null $period_ends_on
 * @property Carbon|null $issued_on
 * @property Carbon|null $due_on
 * @property string $subtotal
 * @property string $total
 * @property string $currency
 * @property string|null $notes
 * @property string|null $specification
 * @property string|null $moneybird_invoice_id
 * @property string|null $moneybird_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Client $client
 */
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'period_starts_on' => 'date',
            'period_ends_on' => 'date',
            'issued_on' => 'date',
            'due_on' => 'date',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return HasMany<InvoiceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort_order');
    }

    /** @return HasMany<TimeEntry, $this> */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function isPushedToMoneybird(): bool
    {
        return $this->moneybird_invoice_id !== null;
    }

    /**
     * "August 2026" for a whole calendar month, otherwise the explicit range.
     */
    public function periodLabel(): string
    {
        if ($this->period_starts_on === null || $this->period_ends_on === null) {
            return '';
        }

        return self::formatPeriod($this->period_starts_on, $this->period_ends_on);
    }

    public static function formatPeriod(CarbonInterface $from, CarbonInterface $to): string
    {
        $isWholeMonth = $from->isSameMonth($to)
            && $from->day === 1
            && $to->day === $to->daysInMonth;

        if ($isWholeMonth) {
            return $from->format('F Y');
        }

        return sprintf('%s to %s', $from->format('d-m-Y'), $to->format('d-m-Y'));
    }

    /**
     * Copies the fields we mirror from a Moneybird sales invoice payload
     * (as returned by MoneybirdClient) onto this invoice without saving.
     * Moneybird owns the number, the state and the dates once the invoice
     * has been pushed; totals are taken over too so they include VAT.
     *
     * @param  array<string, mixed>  $payload
     */
    public function fillFromMoneybird(array $payload): static
    {
        $this->moneybird_invoice_id = (string) $payload['id'];
        $this->moneybird_url = isset($payload['url']) ? (string) $payload['url'] : $this->moneybird_url;
        $this->status = InvoiceStatus::fromMoneybirdState((string) ($payload['state'] ?? 'draft'));

        if (! empty($payload['invoice_id'])) {
            $this->number = (string) $payload['invoice_id'];
        }

        if (! empty($payload['invoice_date'])) {
            $this->issued_on = Carbon::parse((string) $payload['invoice_date']);
        }

        if (! empty($payload['due_date'])) {
            $this->due_on = Carbon::parse((string) $payload['due_date']);
        }

        if (isset($payload['total_price_excl_tax'])) {
            $this->subtotal = number_format((float) $payload['total_price_excl_tax'], 2, '.', '');
        }

        if (isset($payload['total_price_incl_tax'])) {
            $this->total = number_format((float) $payload['total_price_incl_tax'], 2, '.', '');
        }

        return $this;
    }
}
