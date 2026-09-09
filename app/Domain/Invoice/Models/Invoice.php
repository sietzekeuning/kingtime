<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Models;

use App\Domain\Client\Models\Client;
use App\Domain\Invoice\Enums\InvoiceStatus;
use App\Domain\Time\Models\TimeEntry;
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
}
