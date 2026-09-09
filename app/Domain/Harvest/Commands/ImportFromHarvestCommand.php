<?php

declare(strict_types=1);

namespace App\Domain\Harvest\Commands;

use App\Domain\Harvest\Actions\RunHarvestImportAction;
use App\Domain\Harvest\Data\HarvestImportResultData;
use App\Domain\Harvest\Enums\HarvestImportStatus;
use App\Domain\Harvest\Exceptions\HarvestException;
use App\Domain\Harvest\Models\HarvestImport;
use App\Domain\Harvest\Services\HarvestClient;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Throwable;

class ImportFromHarvestCommand extends Command
{
    protected $signature = 'harvest:import
        {--since= : Only fetch records updated since this date or time (defaults to the last successful import)}
        {--full : Ignore previous imports and fetch everything}';

    protected $description = 'Import users, clients, projects, tasks and time entries from Harvest';

    public function handle(HarvestClient $client, RunHarvestImportAction $runImport): int
    {
        if (! $client->isConfigured()) {
            $this->components->error(HarvestException::notConfigured()->getMessage());

            return self::FAILURE;
        }

        $sinceOption = $this->option('since');
        $since = null;

        if (is_string($sinceOption) && $sinceOption !== '') {
            try {
                $since = Carbon::parse($sinceOption);
            } catch (Throwable) {
                $this->components->error("Could not parse --since value [{$sinceOption}].");

                return self::FAILURE;
            }
        }

        $lastStep = null;

        try {
            $import = $runImport->handle(
                $since,
                (bool) $this->option('full'),
                function (HarvestImport $import, string $step, HarvestImportResultData $result) use (&$lastStep): void {
                    if ($step !== $lastStep && $step !== 'done') {
                        $this->components->task('Importing '.str_replace('_', ' ', $step));
                    }

                    $lastStep = $step;
                },
            );
        } catch (HarvestException|ConnectionException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($import->status !== HarvestImportStatus::Finished) {
            $this->components->error($import->error ?? 'Import failed.');

            return self::FAILURE;
        }

        $counts = HarvestImportResultData::restore($import->counts) ?? new HarvestImportResultData;

        $this->components->info($import->updated_since === null
            ? 'Full import finished.'
            : "Incremental import finished (records updated since {$import->updated_since->toDateTimeString()}).");

        $this->table(['Type', 'Created', 'Updated'], collect($counts->toArray())
            ->map(fn (array $count, string $type) => [str_replace('_', ' ', $type), $count['created'], $count['updated']])
            ->values()
            ->all());

        return self::SUCCESS;
    }
}
