<?php

declare(strict_types=1);

namespace App\Domain\Harvest\Actions;

use App\Domain\Harvest\Data\HarvestImportResultData;
use Carbon\CarbonInterface;

/**
 * Pull everything from Harvest in dependency order. Every record type upserts
 * by `harvest_id`, so running this twice is safe.
 *
 * The optional progress callback receives `(string $step, HarvestImportResultData $result)`
 * at the start of each step, every few records within a step, and once with
 * the step `done`.
 */
class ImportFromHarvestAction
{
    public const STEPS = ['users', 'clients', 'projects', 'tasks', 'task_assignments', 'time_entries'];

    public const DONE = 'done';

    private const REPORT_EVERY = 25;

    public function __construct(
        private readonly ImportHarvestUsersAction $users,
        private readonly ImportHarvestClientsAction $clients,
        private readonly ImportHarvestProjectsAction $projects,
        private readonly ImportHarvestTasksAction $tasks,
        private readonly ImportHarvestTaskAssignmentsAction $taskAssignments,
        private readonly ImportHarvestTimeEntriesAction $timeEntries,
    ) {}

    public function handle(?CarbonInterface $updatedSince = null, ?callable $progress = null): HarvestImportResultData
    {
        $result = new HarvestImportResultData;

        $steps = [
            'users' => $this->users,
            'clients' => $this->clients,
            'projects' => $this->projects,
            'tasks' => $this->tasks,
            'task_assignments' => $this->taskAssignments,
            'time_entries' => $this->timeEntries,
        ];

        foreach ($steps as $step => $action) {
            $processed = 0;

            $report = function () use ($progress, $step, $result): void {
                if ($progress !== null) {
                    $progress($step, $result);
                }
            };

            $report();

            $action->handle($result, $updatedSince, function () use (&$processed, $report): void {
                if (++$processed % self::REPORT_EVERY === 0) {
                    $report();
                }
            });
        }

        if ($progress !== null) {
            $progress(self::DONE, $result);
        }

        return $result;
    }
}
