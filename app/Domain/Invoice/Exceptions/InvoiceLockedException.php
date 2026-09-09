<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Exceptions;

use RuntimeException;

class InvoiceLockedException extends RuntimeException
{
    public static function pushed(): self
    {
        return new self('This invoice has been pushed to Moneybird. Delete it in Moneybird first, or force the deletion.');
    }

    public static function notDraft(): self
    {
        return new self('Only draft invoices can be deleted.');
    }
}
