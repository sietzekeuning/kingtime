<?php

declare(strict_types=1);

namespace App\Domain\Client\Controllers;

use App\Domain\Client\Data\ClientData;
use App\Domain\Client\Models\Client;
use App\Domain\Client\Tables\ClientTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientController
{
    public function index(Request $request, ClientTable $table): Response
    {
        return Inertia::render('clients/ClientList', [
            'items' => $table->getData($request)->through(fn (Client $client) => ClientData::fromModel($client)),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('clients/ClientForm', [
            'client' => ClientData::empty(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = ClientData::validateAndCreate($request->all());

        $client = Client::create($data->toUpdateArray());

        return redirect()->route('clients.edit', $client)->with('toast', ['type' => 'success', 'message' => 'Client created.']);
    }

    public function edit(Client $client): Response
    {
        $client->loadCount('projects');

        return Inertia::render('clients/ClientForm', [
            'client' => ClientData::fromModel($client),
        ]);
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $data = ClientData::validateAndCreate($request->all());

        $client->update($data->toUpdateArray());

        return back()->with('toast', ['type' => 'success', 'message' => 'Client saved.']);
    }

    public function destroy(Client $client): RedirectResponse
    {
        $client->delete();

        return redirect()->route('clients.index')->with('toast', ['type' => 'success', 'message' => 'Client deleted.']);
    }
}
