<?php

declare(strict_types=1);

namespace App\Domain\Harvest\Commands;

use App\Domain\Harvest\Actions\RunHarvestImportAction;
use App\Domain\Harvest\Data\HarvestImportResultData;
use App\Domain\Harvest\Enums\HarvestImportStatus;
use App\Domain\Harvest\Exceptions\HarvestException;
use App\Domain\Harvest\Models\HarvestConnection;
use App\Domain\Harvest\Models\HarvestImport;
use App\Domain\User\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Throwable;

class ImportFromHarvestCommand extends Command
{
    protected $signature = 'harvest:import
        {--user= : Only import for this user (id or email); defaults to every user with a Harvest connection}
        {--since= : Only fetch records updated since this date or time (defaults to the last successful import)}
        {--full : Ignore previous imports and fetch everything}';

    protected $description = 'Import users, clients, projects and time entries from Harvest, per connected user';

    public function handle(RunHarvestImportAction $runImport): int
    {
        $connections = $this->connections();

        if ($connections === null) {
            return self::FAILURE;
        }

        if ($connections->isEmpty()) {
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

        $exitCode = self::SUCCESS;

        foreach ($connections as $connection) {
            if ($connections->count() > 1) {
                $this->components->info("Importing for {$connection->user->email}.");
            }

            if ($this->import($runImport, $connection, $since) !== self::SUCCESS) {
                $exitCode = self::FAILURE;
            }
        }

        return $exitCode;
    }

    private function import(RunHarvestImportAction $runImport, HarvestConnection $connection, ?Carbon $since): int
    {
        $lastStep = null;

        try {
            $import = $runImport->handle(
                $connection,
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

    /**
     * The connections to import for, or null when --user names nobody.
     *
     * @return Collection<int, HarvestConnection>|null
     */
    private function connections(): ?Collection
    {
        $userOption = $this->option('user');
        $query = HarvestConnection::query()->with('user')->orderBy('user_id');

        if (! is_string($userOption) || $userOption === '') {
            return $query->get();
        }

        $user = User::query()
            ->when(ctype_digit($userOption), fn ($query) => $query->whereKey((int) $userOption), fn ($query) => $query->where('email', $userOption))
            ->first();

        if ($user === null) {
            $this->components->error("No user found for [{$userOption}].");

            return null;
        }

        $connection = $query->where('user_id', $user->id)->first();

        if ($connection === null) {
            $this->components->error("{$user->email} has not connected Harvest.");

            return null;
        }

        return new Collection([$connection]);
    }
}
