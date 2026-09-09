<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Enums;

use App\Domain\Shared\Data\Contracts\HasEnumLabels;

/**
 * Mirrors the Moneybird sales invoice states we care about. `Draft` is the
 * only state this app creates itself; the rest are read back from Moneybird.
 */
enum InvoiceStatus: string implements HasEnumLabels
{
    case Draft = 'draft';
    case Open = 'open';
    case Paid = 'paid';
    case Late = 'late';
    case Uncollectible = 'uncollectible';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Open => 'Open',
            self::Paid => 'Paid',
            self::Late => 'Late',
            self::Uncollectible => 'Uncollectible',
        };
    }

    public function colorClass(): string
    {
        return match ($this) {
            self::Draft => 'bg-slate-100 text-slate-700',
            self::Open => 'bg-blue-100 text-blue-700',
            self::Paid => 'bg-emerald-100 text-emerald-700',
            self::Late => 'bg-rose-100 text-rose-700',
            self::Uncollectible => 'bg-gray-200 text-gray-600',
        };
    }

    public static function fromMoneybirdState(string $state): self
    {
        return match ($state) {
            'draft' => self::Draft,
            'paid' => self::Paid,
            'late', 'reminded' => self::Late,
            'uncollectible' => self::Uncollectible,
            default => self::Open,
        };
    }
}
