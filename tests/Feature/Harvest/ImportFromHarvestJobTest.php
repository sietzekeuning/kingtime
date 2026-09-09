<?php

declare(strict_types=1);

use App\Domain\Harvest\Actions\RunHarvestImportAction;
use App\Domain\Harvest\Data\HarvestImportProgressData;
use App\Domain\Harvest\Enums\HarvestImportStatus;
use App\Domain\Harvest\Exceptions\HarvestException;
use App\Domain\Harvest\Jobs\ImportFromHarvestJob;
use App\Domain\Harvest\Models\HarvestImport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\HarvestApi;

beforeEach(function (): void {
    $this->connection = HarvestApi::connect();
    $this->userId = $this->connection->user_id;
});

it('records a harvest import row and publishes progress to the cache', function (): void {
    HarvestApi::fake();

    ImportFromHarvestJob::dispatchSync($this->connection);

    $import = HarvestImport::query()->sole();
    expect($import->status)->toBe(HarvestImportStatus::Finished)
        ->and($import->counts['projects'])->toBe(['created' => 3, 'updated' => 0]);

    $progress = HarvestImportProgressData::load($this->userId);
    expect($progress)->not->toBeNull()
        ->and($progress->status)->toBe(HarvestImportStatus::Finished)
        ->and($progress->harvest_import_id)->toBe($import->id)
        ->and($progress->counts->time_entries->created)->toBe(3)
        ->and($progress->message)->toContain('records synced');

    expect(Cache::get(HarvestImportProgressData::cacheKey($this->userId)))->toBeArray()
        ->and($import->user_id)->toBe($this->userId);
});

it('marks the row and the progress as failed when harvest errors', function (): void {
    HarvestApi::fake([
        HarvestApi::BASE.'/projects*' => Http::response(['message' => 'Server error'], 500),
    ]);

    expect(fn () => ImportFromHarvestJob::dispatchSync($this->connection))->toThrow(HarvestException::class);

    $import = HarvestImport::query()->sole();
    expect($import->status)->toBe(HarvestImportStatus::Failed)
        ->and($import->error)->toContain('status 500');

    $progress = HarvestImportProgressData::load($this->userId);
    expect($progress?->status)->toBe(HarvestImportStatus::Failed)
        ->and($progress?->error)->toContain('status 500');
});

it('does not run two imports at the same time', function (): void {
    HarvestApi::fake();
    $lock = Cache::lock(RunHarvestImportAction::lockKey($this->connection), 60);
    expect($lock->get())->toBeTrue();

    try {
        expect(fn () => ImportFromHarvestJob::dispatchSync($this->connection))->toThrow(HarvestException::class, 'already running');
    } finally {
        $lock->release();
    }

    expect(HarvestImport::query()->count())->toBe(0);
    Http::assertNothingSent();
});
