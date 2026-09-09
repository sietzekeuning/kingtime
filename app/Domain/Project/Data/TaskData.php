<?php

declare(strict_types=1);

namespace App\Domain\Project\Data;

use App\Domain\Project\Models\Task;
use App\Domain\Shared\Data\BaseData;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class TaskData extends BaseData
{
    public function __construct(
        public ?int $id,
        #[Required, Max(255)]
        public string $name,
        public bool $is_billable_by_default = true,
        #[Numeric, Min(0)]
        public ?string $default_hourly_rate = null,
        public bool $is_active = true,
        public ?int $harvest_id = null,
    ) {}

    public static function fromModel(Task $task): self
    {
        return new self(
            id: $task->id,
            name: $task->name,
            is_billable_by_default: $task->is_billable_by_default,
            default_hourly_rate: $task->default_hourly_rate,
            is_active: $task->is_active,
            harvest_id: $task->harvest_id,
        );
    }
}
