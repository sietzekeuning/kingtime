<?php

declare(strict_types=1);

namespace App\Domain\Moneybird\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;

class MoneybirdException extends RuntimeException
{
    public static function notConfigured(): self
    {
        return new self('Moneybird is not configured. Set MONEYBIRD_ACCESS_TOKEN and MONEYBIRD_ADMINISTRATION_ID in the environment.');
    }

    public static function fromResponse(string $method, string $path, Response $response): self
    {
        $body = $response->json();
        $detail = is_array($body) && isset($body['error']) ? (string) json_encode($body['error']) : mb_substr($response->body(), 0, 300);

        return new self(sprintf(
            'Moneybird %s %s failed with HTTP %d: %s',
            $method,
            $path,
            $response->status(),
            $detail !== '' ? $detail : 'no response body',
        ));
    }
}
