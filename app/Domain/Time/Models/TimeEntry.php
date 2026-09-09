<?php

declare(strict_types=1);

namespace App\Domain\Time\Models;

use App\Domain\Invoice\Models\Invoice;
use App\Domain\Project\Models\Project;
use App\Domain\Project\Models\Task;
use App\Domain\User\Models\User;
use Database\Factories\TimeEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $project_id
 * @property int|null $task_id
 * @property int|null $invoice_id
 * @property Carbon $spent_on
 * @property string $hours
 * @property string|null $notes
 * @property bool $is_billable
 * @property bool $is_billed
 * @property bool $is_locked
 * @property bool $is_running
 * @property Carbon|null $timer_started_at
 * @property string|null $hourly_rate
 * @property int|null $harvest_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $user
 * @property-read Project $project
 * @property-read Task|null $task
 * @property-read Invoice|null $invoice
 */
class TimeEntry extends Model
{
    /** @use HasFactory<TimeEntryFactory> */
    use HasFactory;

    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'spent_on' => 'date',
            'hours' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'is_billable' => 'boolean',
            'is_billed' => 'boolean',
            'is_locked' => 'boolean',
            'is_running' => 'boolean',
            'timer_started_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Billable hours that have not been put on an invoice yet.
     *
     * @param  Builder<TimeEntry>  $query
     * @return Builder<TimeEntry>
     */
    public function scopeUnbilled(Builder $query): Builder
    {
        return $query->where('is_billable', true)->where('is_billed', false)->whereNull('invoice_id');
    }

    /**
     * Billed or locked entries back an invoice and are frozen: no edit, no
     * delete, no timer.
     */
    public function isLocked(): bool
    {
        return $this->is_locked || $this->is_billed;
    }

    /**
     * Seconds the running timer has been going; 0 when the entry is idle.
     */
    public function elapsedSeconds(): int
    {
        if (! $this->is_running || $this->timer_started_at === null) {
            return 0;
        }

        return max(0, (int) $this->timer_started_at->diffInSeconds(now()));
    }

    /**
     * Hours including the running timer's elapsed time, so a live entry
     * shows its current total instead of the snapshot at the last stop.
     */
    public function currentHours(): float
    {
        return round((float) $this->hours + $this->elapsedSeconds() / 3600, 2);
    }

    public function billableAmount(): float
    {
        if (! $this->is_billable || $this->hourly_rate === null) {
            return 0.0;
        }

        return round((float) $this->hours * (float) $this->hourly_rate, 2);
    }
}
