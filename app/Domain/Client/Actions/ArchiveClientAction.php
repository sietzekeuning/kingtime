<?php

declare(strict_types=1);

namespace App\Domain\Client\Actions;

use App\Domain\Client\Models\Client;

/**
 * Archives a client together with its projects, so nothing of a former
 * customer keeps showing up in the project selects.
 */
class ArchiveClientAction
{
    public function handle(Client $client): void
    {
        $client->update(['is_active' => false]);
        $client->projects()->update(['is_active' => false]);
    }
}
