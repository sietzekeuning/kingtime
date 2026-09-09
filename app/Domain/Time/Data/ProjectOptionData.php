<?php

declare(strict_types=1);

namespace App\Domain\Time\Data;

use App\Domain\Project\Models\Project;
use App\Domain\Shared\Data\BaseData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A project as offered in the time entry form's project select: enough to
 * label it, colour it and default the billable flag.
 */
#[TypeScript]
class ProjectOptionData extends BaseData
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $code,
        public ?string $color,
        public int $client_id,
        public string $client_name,
        public bool $is_billable,
    ) {}

    /** Expects `client` to be loaded. */
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
        );
    }
}
