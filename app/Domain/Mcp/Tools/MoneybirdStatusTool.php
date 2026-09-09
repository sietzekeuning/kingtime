<?php

declare(strict_types=1);

namespace App\Domain\Mcp\Tools;

use App\Domain\Moneybird\Exceptions\MoneybirdException;
use App\Domain\User\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly, IsIdempotent]
class MoneybirdStatusTool extends MoneybirdTool
{
    protected string $name = 'moneybird_status';

    protected string $title = 'Moneybird status';

    protected string $description = 'Tells whether the Moneybird integration is configured and, when it is, which administration the other moneybird_* tools read from (id, name, currency, language). Call this first when a Moneybird tool reports an error.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    protected function execute(Request $request, User $user): Response|ResponseFactory
    {
        if (! $this->moneybird->isConfigured()) {
            return Response::structured([
                'configured' => false,
                'message' => MoneybirdException::notConfigured()->getMessage(),
            ]);
        }

        $administration = $this->moneybird->administration();

        if ($administration === null) {
            return Response::structured([
                'configured' => true,
                'administration_id' => config('services.moneybird.administration_id'),
                'reachable' => false,
                'message' => 'The token works but MONEYBIRD_ADMINISTRATION_ID is not one of the administrations it can access.',
            ]);
        }

        return Response::structured([
            'configured' => true,
            'reachable' => true,
            'administration_id' => self::id($administration['id'] ?? null),
            'name' => self::nullableString($administration['name'] ?? null),
            'currency' => self::nullableString($administration['currency'] ?? null),
            'language' => self::nullableString($administration['language'] ?? null),
            'country' => self::nullableString($administration['country'] ?? null),
            'time_zone' => self::nullableString($administration['time_zone'] ?? null),
        ]);
    }
}
