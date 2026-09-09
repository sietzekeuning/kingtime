<?php

declare(strict_types=1);

use App\Domain\Client\Models\Client;
use App\Domain\Project\Models\Project;
use App\Domain\User\Models\User;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

it('hides archived clients from the list unless the filter asks for them', function (): void {
    Client::factory()->create(['name' => 'Acme', 'is_active' => true]);
    Client::factory()->create(['name' => 'Gone', 'is_active' => false]);

    $this->get(route('clients.index'))
        ->assertInertia(fn ($page) => $page->has('items.data', 1)->where('items.data.0.name', 'Acme'));

    $this->get(route('clients.index', ['filter' => ['is_active' => 'all']]))
        ->assertInertia(fn ($page) => $page->has('items.data', 2));
});

it('archives a client together with its projects and restores only the client', function (): void {
    $client = Client::factory()->create(['is_active' => true]);
    $project = Project::factory()->for($client)->create(['is_active' => true]);

    $this->post(route('clients.archive.store', $client))
        ->assertSessionHas('toast.message', 'Client archived, together with its projects.');

    expect($client->refresh()->is_active)->toBeFalse()
        ->and($project->refresh()->is_active)->toBeFalse();

    $this->delete(route('clients.archive.destroy', $client))
        ->assertSessionHas('toast.message', 'Client restored.');

    expect($client->refresh()->is_active)->toBeTrue()
        ->and($project->refresh()->is_active)->toBeFalse();
});
