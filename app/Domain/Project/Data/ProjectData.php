<?php

declare(strict_types=1);

namespace App\Domain\Project\Data;

use App\Domain\Client\Data\ClientData;
use App\Domain\Project\Models\Project;
use App\Domain\Shared\Data\Attributes\Derived;
use App\Domain\Shared\Data\BaseData;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class ProjectData extends BaseData
{
    public function __construct(
        public ?int $id,
        #[Required, Exists('clients', 'id')]
        public int $client_id,
        #[Required, Max(255)]
        public string $name,
        #[Max(50)]
        public ?string $code = null,
        public bool $is_billable = true,
        #[Numeric, Min(0)]
        public ?string $hourly_rate = null,
        #[Numeric, Min(0)]
        public ?string $budget_hours = null,
        #[Numeric, Min(0)]
        public ?string $budget_amount = null,
        public bool $is_active = true,
        #[Max(20)]
        public ?string $color = null,
        #[Date]
        public ?string $starts_on = null,
        #[Date]
        public ?string $ends_on = null,
        public ?string $notes = null,
        public ?int $harvest_id = null,
        #[Derived]
        public ?ClientData $client = null,
        #[Derived]
        public ?string $total_hours = null,
        #[Derived]
        public ?string $unbilled_hours = null,
        #[Derived]
        public ?string $spent_amount = null,
    ) {}

    public static function fromModel(Project $project): self
    {
        return new self(
            id: $project->id,
            client_id: $project->client_id,
            name: $project->name,
            code: $project->code,
            is_billable: $project->is_billable,
            hourly_rate: $project->hourly_rate,
            budget_hours: $project->budget_hours,
            budget_amount: $project->budget_amount,
            is_active: $project->is_active,
            color: $project->color,
            starts_on: $project->starts_on?->toDateString(),
            ends_on: $project->ends_on?->toDateString(),
            notes: $project->notes,
            harvest_id: $project->harvest_id,
            client: $project->relationLoaded('client') ? ClientData::fromModel($project->client) : null,
            total_hours: self::aggregate($project, 'total_hours'),
            unbilled_hours: self::aggregate($project, 'unbilled_hours'),
            spent_amount: self::aggregate($project, 'spent_amount'),
        );
    }

    /**
     * Reads a `withSum` aggregate when the query added it. Strict mode throws
     * on attributes that were never selected, so a project loaded for its
     * form (without the sums) must not touch them.
     */
    private static function aggregate(Project $project, string $attribute): ?string
    {
        if (! $project->hasAttribute($attribute)) {
            return null;
        }

        $value = $project->getAttribute($attribute);

        return $value !== null ? (string) $value : null;
    }
}
