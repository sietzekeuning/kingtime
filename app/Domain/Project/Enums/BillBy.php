<?php

declare(strict_types=1);

namespace App\Domain\Project\Enums;

use App\Domain\Shared\Data\Contracts\HasEnumLabels;

/**
 * Where the hourly rate for a time entry comes from, same vocabulary as
 * Harvest's `bill_by`: one rate for the whole project, or a rate per task.
 */
enum BillBy: string implements HasEnumLabels
{
    case Project = 'project';
    case Task = 'task';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Project => 'Project rate',
            self::Task => 'Task rate',
            self::None => 'Not billable',
        };
    }

    public function colorClass(): string
    {
        return match ($this) {
            self::Project => 'bg-orange-100 text-orange-700',
            self::Task => 'bg-amber-100 text-amber-700',
            self::None => 'bg-slate-100 text-slate-700',
        };
    }
}
