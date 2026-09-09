<?php

declare(strict_types=1);

use App\Domain\Client\Models\Client;
use App\Domain\User\Models\User;

/**
 * The client form and list, driven through Chromium: the Inertia form, its
 * inline validation errors, the flash toast and the delete confirmation.
 */
beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('creates a client from the form', function (): void {
    $page = visit('/clients/create');

    $page->assertSee('New client');
    $page->fill('[data-field="name"] input', 'Acme');
    $page->fill('[data-field="email"] input', 'billing@acme.test');
    $page->press('Save');

    browserWaitUntilSee($page, 'Client created.');

    $client = Client::query()->where('name', 'Acme')->sole();

    expect($client->email)->toBe('billing@acme.test')
        ->and($client->user_id)->toBe($this->user->id);

    $page->assertPathIs("/clients/{$client->id}/edit")->assertNoJavaScriptErrors();
});

it('shows a validation error next to the field it belongs to', function (): void {
    $page = visit('/clients/create');

    // Only the name is missing; an invalid email would be stopped by the
    // browser's own check before the form ever reaches the server.
    $page->fill('[data-field="email"] input', 'billing@acme.test');
    $page->press('Save');

    // The DTO's type rule runs before its Required attribute, so a missing
    // name reports the string rule first; the row shows that first message.
    browserWaitUntilSee($page, 'The name field must be a string.');
    $page->assertSeeIn('[data-field="name"]', 'The name field must be a string.');

    expect(Client::query()->count())->toBe(0);
});

it('edits a client', function (): void {
    $client = Client::factory()->create(['name' => 'Old name']);

    $page = visit("/clients/{$client->id}/edit");

    $page->assertSee('Old name');
    $page->fill('[data-field="name"] input', 'New name');
    $page->fill('[data-field="notes"] textarea', 'Pays within 14 days.');
    $page->press('Save');

    browserWaitUntilSee($page, 'Client saved.');

    $client->refresh();

    expect($client->name)->toBe('New name')
        ->and($client->notes)->toBe('Pays within 14 days.');

    $page->assertNoJavaScriptErrors();
});

it('deletes a client from the list after confirming', function (): void {
    $client = Client::factory()->create(['name' => 'Acme']);

    $page = visit('/clients');

    $page->assertSee('Acme');
    $page->click('button[title="Delete client"]');
    browserConfirmDelete($page, 'Delete client "Acme"?');

    browserWaitUntilSee($page, 'Client deleted.');
    browserWaitUntilDontSee($page, 'Acme');

    expect(Client::query()->find($client->id))->toBeNull();
});
