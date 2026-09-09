<?php

declare(strict_types=1);

namespace App\Domain\Project\Data;

use App\Domain\Project\Models\ProjectTask;
use App\Domain\Shared\Data\Attributes\Derived;
use App\Domain\Shared\Data\BaseData;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A task as assigned to one project: which task, and the project-specific
 * billability/rate override. `task_name` is denormalised for the UI.
 */
#[TypeScript]
class ProjectTaskData extends BaseData
{
    public function __construct(
        public ?int $id,
        public int $task_id,
        #[Derived]
        public ?string $task_name = null,
        public bool $is_billable = true,
        #[Numeric, Min(0)]
        public ?string $hourly_rate = null,
        public bool $is_active = true,
    ) {}

    public static function fromModel(ProjectTask $assignment): self
    {
        return new self(
            id: $assignment->id,
            task_id: $assignment->task_id,
            task_name: $assignment->relationLoaded('task') ? $assignment->task->name : null,
            is_billable: $assignment->is_billable,
            hourly_rate: $assignment->hourly_rate,
            is_active: $assignment->is_active,
        );
    }
}
