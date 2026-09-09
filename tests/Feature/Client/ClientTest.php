<?php

declare(strict_types=1);

use App\Domain\Client\Models\Client;
use App\Domain\User\Models\User;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

it('lists clients with the table payload', function (): void {
    Client::factory()->count(3)->create();

    $this->get(route('clients.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clients/ClientList')
            ->has('items.data', 3)
            ->has('items.allowed_sorts')
            ->has('items.allowed_filters'));
});

it('filters clients by name', function (): void {
    Client::factory()->create(['name' => 'Acme']);
    Client::factory()->create(['name' => 'Globex']);

    $this->get(route('clients.index', ['filter' => ['name' => 'Glob']]))
        ->assertInertia(fn ($page) => $page->has('items.data', 1)->where('items.data.0.name', 'Globex'));
});

it('creates a client from a validated payload', function (): void {
    $this->post(route('clients.store'), [
        'name' => 'Acme',
        'email' => 'billing@acme.test',
        'currency' => 'EUR',
        'is_active' => true,
    ])->assertRedirect();

    expect(Client::query()->where('name', 'Acme')->exists())->toBeTrue();
});

it('rejects an invalid client', function (): void {
    $this->from(route('clients.create'))
        ->post(route('clients.store'), ['name' => '', 'email' => 'nope'])
        ->assertSessionHasErrors(['name', 'email']);
});

it('updates and deletes a client', function (): void {
    $client = Client::factory()->create(['name' => 'Old']);

    $this->patch(route('clients.update', $client), [...$client->toArray(), 'name' => 'New'])->assertRedirect();
    expect($client->fresh()->name)->toBe('New');

    $this->delete(route('clients.destroy', $client))->assertRedirect(route('clients.index'));
    expect(Client::query()->find($client->id))->toBeNull();
});
