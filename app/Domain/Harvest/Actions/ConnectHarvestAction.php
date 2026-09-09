<?php

declare(strict_types=1);

namespace App\Domain\Harvest\Actions;

use App\Domain\Harvest\Data\HarvestConnectionData;
use App\Domain\Harvest\Exceptions\HarvestException;
use App\Domain\Harvest\Models\HarvestConnection;
use App\Domain\Harvest\Services\HarvestClient;
use App\Domain\User\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Validation\ValidationException;

/**
 * Stores a user's Harvest credentials after checking them against the API,
 * so a typo never ends up as a silently broken connection.
 */
class ConnectHarvestAction
{
    /**
     * @throws ValidationException When Harvest rejects the credentials.
     */
    public function handle(User $user, HarvestConnectionData $data): HarvestConnection
    {
        $connection = $user->harvestConnection()->first() ?? new HarvestConnection(['user_id' => $user->id]);
        $connection->forceFill([
            'account_id' => trim($data->account_id),
            'access_token' => trim($data->access_token),
        ]);

        try {
            $me = (new HarvestClient($connection))->me();
        } catch (HarvestException|ConnectionException $exception) {
            throw ValidationException::withMessages(['access_token' => 'Harvest did not accept these credentials: '.$exception->getMessage()]);
        }

        $name = trim((string) ($me['first_name'] ?? '').' '.(string) ($me['last_name'] ?? ''));
        $harvestUserId = (int) ($me['id'] ?? 0);

        $connection->forceFill([
            'harvest_user_id' => $harvestUserId > 0 ? $harvestUserId : null,
            'account_name' => $name !== '' ? $name : null,
            'account_email' => isset($me['email']) ? (string) $me['email'] : null,
        ])->save();

        return $connection;
    }
}
