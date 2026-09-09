<?php

declare(strict_types=1);

namespace App\Domain\Invoice\Models;

use App\Domain\Project\Models\Project;
use App\Domain\Project\Models\Task;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of the invoice specification: a project/task pair with the hours
 * worked in the period and the rate they are billed at.
 *
 * @property int $id
 * @property int $invoice_id
 * @property int|null $project_id
 * @property int|null $task_id
 * @property string $description
 * @property string $quantity
 * @property string $unit_price
 * @property string $amount
 * @property int $sort_order
 * @property-read Project|null $project
 * @property-read Task|null $task
 */
class InvoiceLine extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
