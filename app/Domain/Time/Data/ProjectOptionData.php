<?php

declare(strict_types=1);

namespace App\Domain\Time\Data;

use App\Domain\Project\Data\ProjectTaskData;
use App\Domain\Project\Models\Project;
use App\Domain\Shared\Data\BaseData;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A project as offered in the time entry form's project select: enough to
 * label it, colour it and list the tasks that may be logged on it.
 */
#[TypeScript]
class ProjectOptionData extends BaseData
{
    /**
     * @param  Collection<int, ProjectTaskData>  $tasks
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $code,
        public ?string $color,
        public int $client_id,
        public string $client_name,
        public bool $is_billable,
        /** @var Collection<int, ProjectTaskData> */
        #[DataCollectionOf(ProjectTaskData::class)]
        public Collection $tasks,
    ) {}

    /** Expects `client` and `taskAssignments.task` to be loaded. */
    public static function fromModel(Project $project): self
    {
        return new self(
            id: $project->id,
            name: $project->name,
            code: $project->code,
            color: $project->color,
            client_id: $project->client_id,
            client_name: $project->client->name,
            is_billable: $project->is_billable,
            tasks: $project->taskAssignments
                ->filter(fn ($assignment) => $assignment->is_active)
                ->values()
                ->map(fn ($assignment) => ProjectTaskData::fromModel($assignment)),
        );
    }
}
