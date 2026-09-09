<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Exceptions;

use RuntimeException;

class NothingToInvoiceException extends RuntimeException
{
    public static function forPeriod(string $clientName, string $from, string $to): self
    {
        return new self("There are no unbilled billable hours for {$clientName} between {$from} and {$to}.");
    }
}
