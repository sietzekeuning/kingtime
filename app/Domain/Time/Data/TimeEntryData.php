<?php

declare(strict_types=1);

namespace App\Domain\Time\Data;

use App\Domain\Shared\Data\Attributes\Derived;
use App\Domain\Shared\Data\BaseData;
use App\Domain\Time\Models\TimeEntry;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class TimeEntryData extends BaseData
{
    public function __construct(
        public ?int $id,
        #[Required, Exists('projects', 'id')]
        public int $project_id,
        #[Required, Date]
        public string $spent_on,
        #[Required, Numeric, Min(0), Max(24)]
        public string $hours,
        public ?string $notes = null,
        /** Null means "not given": the actions then take the project default. */
        public ?bool $is_billable = null,
        public bool $is_billed = false,
        public bool $is_locked = false,
        public bool $is_running = false,
        public ?string $timer_started_at = null,
        public ?string $hourly_rate = null,
        public ?int $user_id = null,
        public ?int $invoice_id = null,
        public ?int $harvest_id = null,
        #[Derived]
        public ?string $project_name = null,
        #[Derived]
        public ?string $project_code = null,
        #[Derived]
        public ?string $project_color = null,
        #[Derived]
        public ?string $client_name = null,
        #[Derived]
        public ?int $client_id = null,
        #[Derived]
        public ?string $user_name = null,
    ) {}

    public static function fromModel(TimeEntry $entry): self
    {
        $project = $entry->relationLoaded('project') ? $entry->project : null;
        $client = $project?->relationLoaded('client') ? $project->client : null;

        return new self(
            id: $entry->id,
            project_id: $entry->project_id,
            spent_on: $entry->spent_on->toDateString(),
            hours: number_format($entry->currentHours(), 2, '.', ''),
            notes: $entry->notes,
            is_billable: $entry->is_billable,
            is_billed: $entry->is_billed,
            is_locked: $entry->is_locked,
            is_running: $entry->is_running,
            timer_started_at: $entry->timer_started_at?->toIso8601String(),
            hourly_rate: $entry->hourly_rate,
            user_id: $entry->user_id,
            invoice_id: $entry->invoice_id,
            harvest_id: $entry->harvest_id,
            project_name: $project?->name,
            project_code: $project?->code,
            project_color: $project?->color,
            client_name: $client?->name,
            client_id: $project?->client_id,
            user_name: $entry->relationLoaded('user') ? $entry->user->name : null,
        );
    }
}
