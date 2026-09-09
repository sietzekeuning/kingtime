<?php

declare(strict_types=1);

namespace App\Domain\Harvest\Enums;

use App\Domain\Shared\Data\Contracts\HasEnumLabels;

enum HarvestImportStatus: string implements HasEnumLabels
{
    case Queued = 'queued';
    case Running = 'running';
    case Finished = 'finished';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Running => 'Running',
            self::Finished => 'Finished',
            self::Failed => 'Failed',
        };
    }

    public function colorClass(): string
    {
        return match ($this) {
            self::Queued => 'bg-slate-100 text-slate-700',
            self::Running => 'bg-blue-100 text-blue-700',
            self::Finished => 'bg-green-100 text-green-700',
            self::Failed => 'bg-red-100 text-red-700',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Queued || $this === self::Running;
    }
}
