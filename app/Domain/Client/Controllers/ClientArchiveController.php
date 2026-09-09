<?php

declare(strict_types=1);

namespace App\Domain\Client\Controllers;

use App\Domain\Client\Actions\ArchiveClientAction;
use App\Domain\Client\Models\Client;
use Illuminate\Http\RedirectResponse;

class ClientArchiveController
{
    public function store(Client $client, ArchiveClientAction $archiveClient): RedirectResponse
    {
        $archiveClient->handle($client);

        return back()->with('toast', ['type' => 'success', 'message' => 'Client archived, together with its projects.']);
    }

    /**
     * Restores the client only; its projects are restored one by one, because
     * a returning customer rarely revives every old project.
     */
    public function destroy(Client $client): RedirectResponse
    {
        $client->update(['is_active' => true]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Client restored.']);
    }
}
