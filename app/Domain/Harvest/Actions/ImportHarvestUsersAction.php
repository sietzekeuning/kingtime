<?php

declare(strict_types=1);

namespace App\Domain\Harvest\Actions;

use App\Domain\Harvest\Data\HarvestImportResultData;
use App\Domain\Harvest\Services\HarvestClient;
use App\Domain\User\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Harvest users become local users. An existing account with the same email
 * is adopted (only its `harvest_id` is set); unknown people get an account
 * with a random password so they can be assigned time without being able to
 * log in until they reset it.
 */
class ImportHarvestUsersAction
{
    public function handle(HarvestClient $client, HarvestImportResultData $result, ?CarbonInterface $updatedSince = null, ?callable $tick = null): void
    {
        foreach ($client->users($updatedSince) as $record) {
            $harvestId = (int) $record['id'];
            $email = mb_strtolower(trim((string) ($record['email'] ?? '')));

            if ($email === '') {
                continue;
            }

            $user = User::query()->where('harvest_id', $harvestId)->first()
                ?? User::query()->whereRaw('lower(email) = ?', [$email])->first();

            if ($user === null) {
                User::create([
                    'name' => trim(($record['first_name'] ?? '').' '.($record['last_name'] ?? '')) ?: $email,
                    'email' => $email,
                    'password' => Str::random(40),
                    'harvest_id' => $harvestId,
                ]);
                $result->users->created++;
            } else {
                $user->harvest_id = $harvestId;
                $user->save();
                $result->users->updated++;
            }

            if ($tick !== null) {
                $tick();
            }
        }
    }
}
