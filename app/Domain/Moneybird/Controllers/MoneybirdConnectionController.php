<?php

declare(strict_types=1);

namespace App\Domain\Moneybird\Controllers;

use App\Domain\Moneybird\Actions\ConnectMoneybirdAction;
use App\Domain\Moneybird\Data\MoneybirdConnectionData;
use App\Domain\User\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MoneybirdConnectionController
{
    public function store(Request $request, ConnectMoneybirdAction $connectMoneybird): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $connection = $connectMoneybird->handle($user, MoneybirdConnectionData::validateAndCreate($request->all()));

        return back()->with('toast', ['type' => 'success', 'message' => 'Moneybird connected to '.($connection->administration_name ?? $connection->administration_id).'.']);
    }

    /**
     * Forgets the token. Invoices already in Moneybird keep their link.
     */
    public function destroy(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->moneybirdConnection()->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Moneybird disconnected.']);
    }
}
