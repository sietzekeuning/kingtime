<?php

declare(strict_types=1);

namespace App\Domain\Time\Actions;

use App\Domain\Project\Models\Project;

/**
 * What a fresh entry on this project inherits when the caller does not say
 * otherwise: the project's billability and, for a billable project, its
 * hourly rate.
 */
class ResolveEntryDefaultsAction
{
    /**
     * @return array{is_billable: bool, hourly_rate: string|null}
     */
    public function handle(Project $project): array
    {
        return [
            'is_billable' => $project->is_billable,
            'hourly_rate' => $project->billableRate(),
        ];
    }
}
