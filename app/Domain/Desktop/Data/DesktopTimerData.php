<?php

declare(strict_types=1);

namespace App\Domain\Desktop\Data;

use App\Domain\Shared\Data\BaseData;
use App\Domain\Time\Models\TimeEntry;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The running timer as the menu bar app draws it. Instead of the rounded
 * live hours the web app shows, this carries the raw parts, so the app can
 * tick a clock of its own that agrees with the server to the second and
 * does not jump every time it polls: the seconds banked before the timer
 * was (re)started, and when that happened.
 */
#[TypeScript]
class DesktopTimerData extends BaseData
{
    public function __construct(
        public int $id,
        public int $project_id,
        public string $project_name,
        public ?string $project_color,
        public int $client_id,
        public string $client_name,
        public string $spent_on,
        public ?string $notes,
        public int $seconds_before_timer,
        public string $timer_started_at,
    ) {}

    /** Expects a running entry with `project.client` loaded. */
    public static function fromModel(TimeEntry $entry): self
    {
        return new self(
            id: $entry->id,
            project_id: $entry->project_id,
            project_name: $entry->project->name,
            project_color: $entry->project->color,
            client_id: $entry->project->client_id,
            client_name: $entry->project->client->name,
            spent_on: $entry->spent_on->toDateString(),
            notes: $entry->notes,
            seconds_before_timer: (int) round((float) $entry->hours * 3600),
            timer_started_at: (string) $entry->timer_started_at?->toIso8601String(),
        );
    }
}
