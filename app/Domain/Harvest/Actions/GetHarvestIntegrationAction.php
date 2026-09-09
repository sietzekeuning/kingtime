<?php

declare(strict_types=1);

namespace App\Domain\Harvest\Actions;

use App\Domain\Harvest\Data\HarvestImportData;
use App\Domain\Harvest\Data\HarvestImportProgressData;
use App\Domain\Harvest\Data\HarvestIntegrationData;
use App\Domain\Harvest\Exceptions\HarvestException;
use App\Domain\Harvest\Models\HarvestImport;
use App\Domain\Harvest\Services\HarvestClient;
use App\Domain\User\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;

/**
 * Status block for the integrations settings page: the user's connection,
 * the Harvest account behind it and their recent import history.
 */
class GetHarvestIntegrationAction
{
    private const ACCOUNT_CACHE_SECONDS = 600;

    private const RECENT_IMPORTS = 10;

    public function handle(User $user): HarvestIntegrationData
    {
        $connection = $user->harvestConnection()->first();
        $accountName = $connection?->account_name;
        $accountEmail = $connection?->account_email;
        $accountError = null;

        if ($connection !== null) {
            try {
                /** @var array<string, mixed> $account */
                $account = Cache::remember(
                    'harvest:account:'.$connection->id.':'.md5($connection->account_id.$connection->access_token),
                    self::ACCOUNT_CACHE_SECONDS,
                    fn (): array => (new HarvestClient($connection))->me(),
                );

                $name = trim((string) ($account['first_name'] ?? '').' '.(string) ($account['last_name'] ?? ''));
                $accountName = $name !== '' ? $name : null;
                $accountEmail = isset($account['email']) ? (string) $account['email'] : null;
            } catch (HarvestException|ConnectionException $exception) {
                $accountError = $exception->getMessage();
            }
        }

        $imports = HarvestImport::query()
            ->where('user_id', $user->id)
            ->latest('started_at')
            ->latest('id')
            ->limit(self::RECENT_IMPORTS)
            ->get()
            ->map(fn (HarvestImport $import) => HarvestImportData::fromModel($import));

        return new HarvestIntegrationData(
            configured: $connection !== null,
            account_id: $connection?->account_id,
            account_name: $accountName,
            account_email: $accountEmail,
            account_error: $accountError,
            last_import: $imports->first(),
            imports: $imports,
            progress: HarvestImportProgressData::load($user->id),
        );
    }
}
