<?php

declare(strict_types=1);

namespace App\Domain\Harvest\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;

class HarvestException extends RuntimeException
{
    public static function notConfigured(): self
    {
        return new self('Harvest is not connected. Connect your Harvest account under Settings, Integrations.');
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
            $status === 401 => ' The Harvest access token is invalid or revoked; reconnect Harvest under Settings, Integrations.',
            $status === 403 => ' Check the Harvest account id and the token permissions.',
            $status === 429 => ' Harvest rate limit exceeded, try again in a moment.',
            default => '',
        };

        return new self("Harvest API {$method} {$path} failed with status {$status}{$detail}.{$hint}");
    }
}
