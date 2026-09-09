<?php

declare(strict_types=1);

use App\Domain\User\Models\User;
use Inertia\Testing\AssertableInertia;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

it('requires a login', function (): void {
    $this->get(route('api-tokens.index'))->assertRedirect(route('login'));
});

it('lists the user\'s own tokens with the MCP connection details', function (): void {
    $this->travelTo('2026-09-09 10:00:00');
    $this->user->createToken('Claude Desktop');
    User::factory()->create()->createToken('Someone else');

    $this->actingAs($this->user)
        ->get(route('api-tokens.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('settings/ApiTokens')
            ->has('tokens', 1)
            ->where('tokens.0.name', 'Claude Desktop')
            ->where('tokens.0.last_used_at', null)
            ->where('tokens.0.created_at', now()->toIso8601String())
            ->where('plainTextToken', null)
            ->where('mcpUrl', url('/mcp')));
});

it('creates a token and shows the plain text once', function (): void {
    $response = $this->actingAs($this->user)
        ->from(route('api-tokens.index'))
        ->post(route('api-tokens.store'), ['name' => 'Claude Code']);

    $response->assertRedirect(route('api-tokens.index'))
        ->assertSessionHas('plainTextToken', fn (string $token) => str_contains($token, '|'));

    $token = PersonalAccessToken::query()->firstOrFail();
    expect($token->name)->toBe('Claude Code')
        ->and($token->tokenable_id)->toBe($this->user->id);

    $this->actingAs($this->user)
        ->get(route('api-tokens.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('plainTextToken', fn (?string $plainText) => is_string($plainText) && str_contains($plainText, '|'))
            ->has('tokens', 1));

    $this->actingAs($this->user)
        ->get(route('api-tokens.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('plainTextToken', null));
});

it('validates the token name', function (): void {
    $this->actingAs($this->user)
        ->from(route('api-tokens.index'))
        ->post(route('api-tokens.store'), ['name' => ''])
        ->assertRedirect(route('api-tokens.index'))
        ->assertSessionHasErrors(['name']);

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

it('revokes a token but never someone else\'s', function (): void {
    $mine = $this->user->createToken('Mine')->accessToken;
    $theirs = User::factory()->create()->createToken('Theirs')->accessToken;

    $this->actingAs($this->user)
        ->delete(route('api-tokens.destroy', $mine->id))
        ->assertRedirect(route('api-tokens.index'));

    $this->actingAs($this->user)
        ->delete(route('api-tokens.destroy', $theirs->id))
        ->assertNotFound();

    expect(PersonalAccessToken::query()->find($mine->id))->toBeNull()
        ->and(PersonalAccessToken::query()->find($theirs->id))->not->toBeNull();
});
