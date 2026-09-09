<?php

declare(strict_types=1);

namespace App\Domain\Harvest\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;

class HarvestException extends RuntimeException
{
    public static function notConfigured(): self
    {
        return new self('Harvest is not configured. Set HARVEST_ACCOUNT_ID and HARVEST_ACCESS_TOKEN in .env.');
    }

    public static function alreadyRunning(): self
    {
        return new self('A Harvest import is already running.');
    }

    public static function fromResponse(string $method, string $path, Response $response): self
    {
        $status = $response->status();
        $detail = $response->json('message') ?? $response->json('error_description') ?? trim($response->body());
        $detail = is_string($detail) && $detail !== '' ? ': '.mb_substr($detail, 0, 300) : '';

        $hint = match (true) {
            $status === 401 => ' Check HARVEST_ACCESS_TOKEN.',
            $status === 403 => ' Check HARVEST_ACCOUNT_ID and the token permissions.',
            $status === 429 => ' Harvest rate limit exceeded, try again in a moment.',
            default => '',
        };

        return new self("Harvest API {$method} {$path} failed with status {$status}{$detail}.{$hint}");
    }
}
