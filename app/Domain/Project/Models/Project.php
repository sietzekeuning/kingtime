<?php

declare(strict_types=1);

namespace App\Domain\Project\Models;

use App\Domain\Client\Models\Client;
use App\Domain\Time\Models\TimeEntry;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $client_id
 * @property string $name
 * @property string|null $code
 * @property bool $is_billable
 * @property string|null $hourly_rate
 * @property string|null $budget_hours
 * @property string|null $budget_amount
 * @property bool $is_active
 * @property string|null $color
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property string|null $notes
 * @property int|null $harvest_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Client $client
 */
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_billable' => 'boolean',
            'is_active' => 'boolean',
            'hourly_rate' => 'decimal:2',
            'budget_hours' => 'decimal:2',
            'budget_amount' => 'decimal:2',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return HasMany<TimeEntry, $this> */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /**
     * The rate a new entry on this project is billed at: the project's
     * hourly rate, or nothing when the project is not billable.
     */
    public function billableRate(): ?string
    {
        return $this->is_billable ? $this->hourly_rate : null;
    }
}
