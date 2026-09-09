<?php

declare(strict_types=1);

namespace App\Domain\Project\Data;

use App\Domain\Project\Models\ProjectTask;
use App\Domain\Shared\Data\Attributes\Derived;
use App\Domain\Shared\Data\BaseData;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\Required;
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
        #[Required, Exists('tasks', 'id')]
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

    /**
     * Validates the `tasks` array of a project form row by row. BaseData
     * strips nested Data collections before validating the parent, so the
     * assignments are validated here and their errors are re-keyed to
     * `tasks.{index}.{field}` so the form can show them on the right row.
     *
     * @return Collection<int, self>
     */
    public static function collectValidated(mixed $rows): Collection
    {
        if ($rows === null) {
            $rows = [];
        }

        if (! is_array($rows)) {
            throw ValidationException::withMessages(['tasks' => 'The tasks must be a list.']);
        }

        $assignments = collect();
        $seenTaskIds = [];

        foreach (array_values($rows) as $index => $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages(["tasks.{$index}" => 'The task assignment is invalid.']);
            }

            try {
                $assignment = self::validateAndCreate($row);
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages(
                    collect($exception->errors())->mapWithKeys(fn (array $messages, string $field) => ["tasks.{$index}.{$field}" => $messages])->all(),
                );
            }

            if (in_array($assignment->task_id, $seenTaskIds, true)) {
                throw ValidationException::withMessages(["tasks.{$index}.task_id" => 'This task is already assigned to the project.']);
            }

            $seenTaskIds[] = $assignment->task_id;
            $assignments->push($assignment);
        }

        return $assignments;
    }
}
