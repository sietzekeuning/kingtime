<?php

declare(strict_types=1);

namespace App\Domain\Moneybird\Actions;

use App\Domain\Moneybird\Data\MoneybirdIntegrationData;
use App\Domain\Moneybird\Exceptions\MoneybirdException;
use App\Domain\Moneybird\Services\MoneybirdClient;
use App\Domain\User\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;

/**
 * Status block for the integrations settings page: the user's Moneybird
 * connection and whether the token still reaches the administration.
 */
class GetMoneybirdIntegrationAction
{
    private const ADMINISTRATION_CACHE_SECONDS = 600;

    public function handle(User $user): MoneybirdIntegrationData
    {
        $connection = $user->moneybirdConnection()->first();

        if ($connection === null) {
            return new MoneybirdIntegrationData(false, null, null, null, null, null, null);
        }

        $name = $connection->administration_name;
        $error = null;

        try {
            /** @var array<string, mixed>|null $administration */
            $administration = Cache::remember(
                'moneybird:administration:'.$connection->id.':'.md5($connection->administration_id.$connection->access_token),
                self::ADMINISTRATION_CACHE_SECONDS,
                fn (): array => (new MoneybirdClient($connection))->administration() ?? [],
            );

            if ($administration === []) {
                $error = 'The token cannot see administration '.$connection->administration_id.' any more.';
            } elseif (isset($administration['name'])) {
                $name = (string) $administration['name'];
            }
        } catch (MoneybirdException|ConnectionException $exception) {
            $error = $exception->getMessage();
        }

        return new MoneybirdIntegrationData(
            configured: true,
            administration_id: $connection->administration_id,
            administration_name: $name,
            administration_error: $error,
            tax_rate_id: $connection->tax_rate_id,
            ledger_account_id: $connection->ledger_account_id,
            workflow_id: $connection->workflow_id,
        );
    }
}
