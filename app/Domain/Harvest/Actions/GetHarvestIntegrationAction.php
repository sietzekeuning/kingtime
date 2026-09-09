<?php

declare(strict_types=1);

namespace App\Domain\Harvest\Actions;

use App\Domain\Harvest\Data\HarvestImportData;
use App\Domain\Harvest\Data\HarvestImportProgressData;
use App\Domain\Harvest\Data\HarvestIntegrationData;
use App\Domain\Harvest\Exceptions\HarvestException;
use App\Domain\Harvest\Models\HarvestImport;
use App\Domain\Harvest\Services\HarvestClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;

/**
 * Status block for the integrations settings page: configuration, the
 * connected Harvest account and the recent import history.
 */
class GetHarvestIntegrationAction
{
    private const ACCOUNT_CACHE_SECONDS = 600;

    private const RECENT_IMPORTS = 10;

    public function __construct(private readonly HarvestClient $client) {}

    public function handle(): HarvestIntegrationData
    {
        $configured = $this->client->isConfigured();
        $accountName = null;
        $accountEmail = null;
        $accountError = null;

        if ($configured) {
            try {
                /** @var array<string, mixed> $account */
                $account = Cache::remember(
                    'harvest:account:'.md5((string) config('services.harvest.account_id').(string) config('services.harvest.access_token')),
                    self::ACCOUNT_CACHE_SECONDS,
                    fn (): array => $this->client->me(),
                );

                $name = trim((string) ($account['first_name'] ?? '').' '.(string) ($account['last_name'] ?? ''));
                $accountName = $name !== '' ? $name : null;
                $accountEmail = isset($account['email']) ? (string) $account['email'] : null;
            } catch (HarvestException|ConnectionException $exception) {
                $accountError = $exception->getMessage();
            }
        }

        $imports = HarvestImport::query()
            ->latest('started_at')
            ->latest('id')
            ->limit(self::RECENT_IMPORTS)
            ->get()
            ->map(fn (HarvestImport $import) => HarvestImportData::fromModel($import));

        return new HarvestIntegrationData(
            configured: $configured,
            account_name: $accountName,
            account_email: $accountEmail,
            account_error: $accountError,
            last_import: $imports->first(),
            imports: $imports,
            progress: HarvestImportProgressData::load(),
        );
    }
}
