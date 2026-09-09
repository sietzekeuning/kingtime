<?php

declare(strict_types=1);

namespace App\Domain\Time\Data;

use App\Domain\Shared\Data\BaseData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One project on one day in the week grid. A cell with a single open entry
 * is editable in place; a cell with several entries, a locked entry or a
 * running timer only shows its total.
 */
#[TypeScript]
class TimesheetCellData extends BaseData
{
    public function __construct(
        public string $date,
        public string $hours,
        public int $entries_count,
        /** The entry behind the cell, when there is exactly one. */
        public ?int $entry_id,
        public bool $is_locked,
        public bool $is_running,
        public ?string $notes,
    ) {}
}
