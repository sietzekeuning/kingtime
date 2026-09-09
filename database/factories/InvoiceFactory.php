<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Client\Models\Client;
use App\Domain\Invoice\Enums\InvoiceStatus;
use App\Domain\Invoice\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Invoice> */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            // An invoice belongs to whoever owns its client.
            'user_id' => fn (array $attributes) => Client::withoutGlobalScopes()->whereKey($attributes['client_id'])->value('user_id'),
            'status' => InvoiceStatus::Draft,
            'period_starts_on' => now()->startOfMonth()->toDateString(),
            'period_ends_on' => now()->endOfMonth()->toDateString(),
            'subtotal' => '0.00',
            'total' => '0.00',
            'currency' => 'EUR',
        ];
    }
}
