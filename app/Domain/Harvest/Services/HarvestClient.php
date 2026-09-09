<?php

declare(strict_types=1);

namespace App\Domain\Harvest\Services;

use App\Domain\Harvest\Exceptions\HarvestException;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Sleep;

/**
 * Thin adapter over the Harvest API v2. Every list endpoint is exposed as a
 * lazy collection that walks Harvest's `next_page` pagination on demand.
 *
 * @phpstan-type HarvestRecord array<string, mixed>
 */
class HarvestClient
{
    public const PER_PAGE = 100;

    /** Harvest allows 100 general API requests per 15 seconds. */
    private const RATE_LIMIT_REQUESTS = 100;

    private const RATE_LIMIT_WINDOW_SECONDS = 15;

    private const MAX_ATTEMPTS = 3;

    /** @var array<int, float> Unix timestamps of the requests made in the current window. */
    private array $requestTimes = [];

    private readonly ?string $accountId;

    private readonly ?string $accessToken;

    private readonly string $baseUrl;

    public function __construct()
    {
        $accountId = config('services.harvest.account_id');
        $accessToken = config('services.harvest.access_token');
        $baseUrl = config('services.harvest.base_url');

        $this->accountId = is_scalar($accountId) && (string) $accountId !== '' ? (string) $accountId : null;
        $this->accessToken = is_string($accessToken) && $accessToken !== '' ? $accessToken : null;
        $this->baseUrl = rtrim(is_string($baseUrl) && $baseUrl !== '' ? $baseUrl : 'https://api.harvestapp.com/api/v2', '/');
    }

    public function isConfigured(): bool
    {
        return $this->accountId !== null && $this->accessToken !== null;
    }

    /**
     * The user that owns the access token.
     *
     * @return HarvestRecord
     */
    public function me(): array
    {
        return $this->get('/users/me');
    }

    /** @return LazyCollection<int, HarvestRecord> */
    public function clients(?CarbonInterface $updatedSince = null): LazyCollection
    {
        return $this->paginate('/clients', 'clients', $this->updatedSinceQuery($updatedSince));
    }

    /** @return LazyCollection<int, HarvestRecord> */
    public function projects(?CarbonInterface $updatedSince = null): LazyCollection
    {
        return $this->paginate('/projects', 'projects', $this->updatedSinceQuery($updatedSince));
    }

    /** @return LazyCollection<int, HarvestRecord> */
    public function tasks(?CarbonInterface $updatedSince = null): LazyCollection
    {
        return $this->paginate('/tasks', 'tasks', $this->updatedSinceQuery($updatedSince));
    }

    /**
     * Task assignments across all projects.
     *
     * @return LazyCollection<int, HarvestRecord>
     */
    public function taskAssignments(?CarbonInterface $updatedSince = null): LazyCollection
    {
        return $this->paginate('/task_assignments', 'task_assignments', $this->updatedSinceQuery($updatedSince));
    }

    /** @return LazyCollection<int, HarvestRecord> */
    public function users(?CarbonInterface $updatedSince = null): LazyCollection
    {
        return $this->paginate('/users', 'users', $this->updatedSinceQuery($updatedSince));
    }

    /** @return LazyCollection<int, HarvestRecord> */
    public function timeEntries(
        ?CarbonInterface $updatedSince = null,
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
    ): LazyCollection {
        return $this->paginate('/time_entries', 'time_entries', array_filter([
            ...$this->updatedSinceQuery($updatedSince),
            'from' => $from?->toDateString(),
            'to' => $to?->toDateString(),
        ]));
    }

    /**
     * Harvest filters on `updated_at >= updated_since` for every list endpoint.
     *
     * @return array<string, string>
     */
    private function updatedSinceQuery(?CarbonInterface $updatedSince): array
    {
        return $updatedSince === null ? [] : ['updated_since' => $updatedSince->toIso8601String()];
    }

    /**
     * Iterate every record of a list endpoint, following `next_page` until
     * Harvest reports there is none.
     *
     * @param  array<string, mixed>  $query
     * @return LazyCollection<int, HarvestRecord>
     */
    public function paginate(string $path, string $key, array $query = []): LazyCollection
    {
        return LazyCollection::make(function () use ($path, $key, $query) {
            $page = 1;

            do {
                $body = $this->get($path, [...$query, 'page' => $page, 'per_page' => self::PER_PAGE]);

                /** @var array<int, HarvestRecord> $records */
                $records = is_array($body[$key] ?? null) ? $body[$key] : [];

                foreach ($records as $record) {
                    yield $record;
                }

                $page = is_int($body['next_page'] ?? null) ? $body['next_page'] : null;
            } while ($page !== null);
        });
    }

    /**
     * @param  array<string, mixed>  $query
     * @return HarvestRecord
     */
    private function get(string $path, array $query = []): array
    {
        if (! $this->isConfigured()) {
            throw HarvestException::notConfigured();
        }

        $response = null;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $this->throttle();

            $response = $this->request()->get($this->baseUrl.$path, $query);

            if ($response->status() !== 429 || $attempt === self::MAX_ATTEMPTS) {
                break;
            }

            $retryAfter = (int) $response->header('Retry-After');
            Sleep::for($retryAfter > 0 ? $retryAfter : self::RATE_LIMIT_WINDOW_SECONDS)->seconds();
        }

        /** @var Response $response */
        if (! $response->successful()) {
            throw HarvestException::fromResponse('GET', $path, $response);
        }

        /** @var HarvestRecord $json */
        $json = $response->json() ?? [];

        return $json;
    }

    private function request(): PendingRequest
    {
        return Http::withToken((string) $this->accessToken)
            ->withHeaders([
                'Harvest-Account-Id' => (string) $this->accountId,
                'User-Agent' => 'Kingtime (https://kingtime.nl)',
            ])
            ->acceptJson()
            ->timeout(30);
    }

    /**
     * Stay under Harvest's request quota by pausing once the window is full.
     */
    private function throttle(): void
    {
        $windowStart = microtime(true) - self::RATE_LIMIT_WINDOW_SECONDS;
        $this->requestTimes = array_values(array_filter($this->requestTimes, fn (float $time) => $time > $windowStart));

        if (count($this->requestTimes) >= self::RATE_LIMIT_REQUESTS) {
            $wait = (int) ceil($this->requestTimes[0] - $windowStart);
            Sleep::for(max(1, $wait))->seconds();
            $this->requestTimes = [];
        }

        $this->requestTimes[] = microtime(true);
    }
}
