<?php

declare(strict_types=1);

namespace App\Domain\Moneybird\Actions;

use App\Domain\Moneybird\Data\MoneybirdConnectionData;
use App\Domain\Moneybird\Exceptions\MoneybirdException;
use App\Domain\Moneybird\Models\MoneybirdConnection;
use App\Domain\Moneybird\Services\MoneybirdClient;
use App\Domain\User\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Validation\ValidationException;

/**
 * Stores a user's Moneybird credentials after checking that the token can
 * see the administration, so a wrong id never ends up as a silently broken
 * connection.
 */
class ConnectMoneybirdAction
{
    /**
     * @throws ValidationException When the token is missing or Moneybird rejects the credentials.
     */
    public function handle(User $user, MoneybirdConnectionData $data): MoneybirdConnection
    {
        $connection = $user->moneybirdConnection()->first() ?? new MoneybirdConnection(['user_id' => $user->id]);
        $token = trim((string) $data->access_token);

        if ($token === '' && ! $connection->exists) {
            throw ValidationException::withMessages(['access_token' => 'Enter a Moneybird API token.']);
        }

        $connection->forceFill([
            'access_token' => $token !== '' ? $token : $connection->access_token,
            'administration_id' => trim($data->administration_id),
            'tax_rate_id' => self::optional($data->tax_rate_id),
            'ledger_account_id' => self::optional($data->ledger_account_id),
            'workflow_id' => self::optional($data->workflow_id),
        ]);

        try {
            $administration = (new MoneybirdClient($connection))->administration();
        } catch (MoneybirdException|ConnectionException $exception) {
            throw ValidationException::withMessages(['access_token' => 'Moneybird did not accept these credentials: '.$exception->getMessage()]);
        }

        if ($administration === null) {
            throw ValidationException::withMessages(['administration_id' => 'The token works, but it cannot see administration '.$connection->administration_id.'.']);
        }

        $connection->forceFill([
            'administration_name' => isset($administration['name']) ? (string) $administration['name'] : null,
        ])->save();

        return $connection;
    }

    private static function optional(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
