<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Harvest\Models\HarvestConnection;
use App\Domain\User\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Fakes the Harvest API v2 with the fixtures in tests/Fixtures/harvest.
 */
final class HarvestApi
{
    public const BASE = 'api.harvestapp.com/api/v2';

    /**
     * Connects Harvest for the given user (or a fresh one) with the account
     * and token the fixtures and header assertions expect.
     */
    public static function connect(?User $user = null): HarvestConnection
    {
        config(['services.harvest.base_url' => 'https://api.harvestapp.com/api/v2']);

        return HarvestConnection::factory()
            ->for($user ?? User::factory()->create())
            ->create(['account_id' => '12345', 'access_token' => 'test-token']);
    }

    /**
     * Fake every endpoint the import touches. Extra stubs win over the
     * defaults so a test can override a single endpoint.
     *
     * @param  array<string, mixed>  $overrides
     */
    public static function fake(array $overrides = []): void
    {
        Http::preventStrayRequests();

        $defaults = [
            self::BASE.'/users/me*' => Http::response(self::fixture('me')),
            self::BASE.'/users*' => Http::response(self::fixture('users')),
            self::BASE.'/clients*' => fn (Request $request) => Http::response(
                self::fixture(self::page($request) === 2 ? 'clients_page2' : 'clients_page1'),
            ),
            self::BASE.'/projects*' => Http::response(self::fixture('projects')),
            self::BASE.'/time_entries*' => Http::response(self::fixture('time_entries')),
        ];

        // Stubs are matched in order, so overrides go first and the default
        // for an overridden pattern is dropped instead of shadowing it.
        Http::fake([...$overrides, ...array_diff_key($defaults, $overrides)]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function fixture(string $name): array
    {
        $json = file_get_contents(base_path("tests/Fixtures/harvest/{$name}.json"));

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * Decoded query string of a faked request.
     *
     * @return array<string, mixed>
     */
    public static function query(Request $request): array
    {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        /** @var array<string, mixed> $query */
        return $query;
    }

    public static function page(Request $request): int
    {
        return (int) (self::query($request)['page'] ?? 1);
    }
}
