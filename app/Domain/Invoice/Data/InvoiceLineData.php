<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Data;

use App\Domain\Invoice\Models\InvoiceLine;
use App\Domain\Shared\Data\Attributes\Derived;
use App\Domain\Shared\Data\BaseData;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class InvoiceLineData extends BaseData
{
    public function __construct(
        public ?int $id,
        public ?int $project_id,
        public string $description,
        public string $quantity,
        public string $unit_price,
        public string $amount,
        public int $sort_order = 0,
        #[Derived]
        public ?string $project_name = null,
    ) {}

    public static function fromModel(InvoiceLine $line): self
    {
        return new self(
            id: $line->id,
            project_id: $line->project_id,
            description: $line->description,
            quantity: $line->quantity,
            unit_price: $line->unit_price,
            amount: $line->amount,
            sort_order: $line->sort_order,
            project_name: $line->relationLoaded('project') ? $line->project?->name : null,
        );
    }
}
