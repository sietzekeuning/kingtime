<?php

declare(strict_types=1);

namespace App\Domain\Client\Models;

use App\Domain\Invoice\Models\Invoice;
use App\Domain\Project\Models\Project;
use App\Domain\Shared\Models\Concerns\BelongsToUser;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $email
 * @property string|null $address
 * @property string $currency
 * @property bool $is_active
 * @property int|null $harvest_id
 * @property string|null $moneybird_contact_id
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $user
 */
class Client extends Model
{
    use BelongsToUser;

    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** @return HasManyThrough<TimeEntry, Project, $this> */
    public function timeEntries(): HasManyThrough
    {
        return $this->hasManyThrough(TimeEntry::class, Project::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
