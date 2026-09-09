<?php

declare(strict_types=1);

use App\Domain\Client\Models\Client;
use App\Domain\User\Models\User;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

it('hands a session toast to the Inertia flash data of the next page', function (): void {
    $this->withSession(['toast' => ['type' => 'success', 'message' => 'Client saved.']])
        ->get(route('clients.index'))
        ->assertInertia(fn ($page) => $page
            ->component('clients/ClientList')
            ->hasFlash('toast.type', 'success')
            ->hasFlash('toast.message', 'Client saved.'));
});

it('shows the toast once', function (): void {
    $client = Client::factory()->create();

    $this->patch(route('clients.update', $client), [...$client->toArray(), 'name' => 'Renamed'])
        ->assertRedirect()
        ->assertSessionHas('toast.message', 'Client saved.');

    $this->get(route('clients.edit', $client))
        ->assertInertia(fn ($page) => $page->hasFlash('toast.message', 'Client saved.'));

    $this->get(route('clients.edit', $client))
        ->assertInertia(fn ($page) => $page->missingFlash('toast'));
});
