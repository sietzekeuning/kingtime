<?php

declare(strict_types=1);

namespace App\Domain\Harvest\Actions;

use App\Domain\Harvest\Data\HarvestImportResultData;
use App\Domain\Harvest\Models\HarvestConnection;
use App\Domain\Harvest\Services\HarvestClient;
use App\Domain\User\Models\User;
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
    public const STEPS = ['clients', 'projects', 'time_entries'];

    public const DONE = 'done';

    private const REPORT_EVERY = 25;

    public function __construct(
        private readonly ImportHarvestClientsAction $clients,
        private readonly ImportHarvestProjectsAction $projects,
        private readonly ImportHarvestTimeEntriesAction $timeEntries,
    ) {}

    public function handle(HarvestConnection $connection, ?CarbonInterface $updatedSince = null, ?callable $progress = null): HarvestImportResultData
    {
        $client = new HarvestClient($connection);
        $result = new HarvestImportResultData;

        $this->linkTokenOwner($client);

        $steps = [
            'clients' => $this->clients,
            'projects' => $this->projects,
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

            $action->handle($client, $result, $updatedSince, function () use (&$processed, $report): void {
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

    /**
     * The Harvest user behind the token is the Kingtime user who connected
     * it, whatever email either side uses. Only their hours are imported,
     * onto their own account; the Harvest id is also remembered on the user
     * unless another local user already carries it.
     */
    private function linkTokenOwner(HarvestClient $client): void
    {
        $me = $client->me();
        $harvestUserId = (int) ($me['id'] ?? 0);
        $connection = $client->connection();
        $owner = $connection->user;

        $name = trim((string) ($me['first_name'] ?? '').' '.(string) ($me['last_name'] ?? ''));
        $connection->forceFill([
            'harvest_user_id' => $harvestUserId > 0 ? $harvestUserId : null,
            'account_name' => $name !== '' ? $name : null,
            'account_email' => isset($me['email']) ? (string) $me['email'] : null,
        ])->save();

        if ($harvestUserId <= 0 || $owner->harvest_id === $harvestUserId) {
            return;
        }

        $taken = User::query()->where('harvest_id', $harvestUserId)->whereKeyNot($owner->id)->exists();

        if (! $taken) {
            $owner->harvest_id = $harvestUserId;
            $owner->save();
        }
    }
}
