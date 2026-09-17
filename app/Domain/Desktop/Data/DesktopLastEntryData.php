<?php

declare(strict_types=1);

namespace App\Domain\Desktop\Data;

use App\Domain\Shared\Data\BaseData;
use App\Domain\Time\Models\TimeEntry;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The entry of today that was worked on last while no timer runs: the menu
 * bar shows its time next to the play button, and play continues it.
 */
#[TypeScript]
class DesktopLastEntryData extends BaseData
{
    public function __construct(
        public int $id,
        public int $project_id,
        public string $project_name,
        public string $client_name,
        public ?string $notes,
        public int $seconds,
    ) {}

    /** Expects an entry with `project.client` loaded. */
    public static function fromModel(TimeEntry $entry): self
    {
        return new self(
            id: $entry->id,
            project_id: $entry->project_id,
            project_name: $entry->project->name,
            client_name: $entry->project->client->name,
            notes: $entry->notes,
            seconds: (int) round((float) $entry->hours * 3600),
        );
    }
}
