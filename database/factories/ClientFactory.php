<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Client\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Client> */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'email' => fake()->companyEmail(),
            'address' => fake()->address(),
            'currency' => 'EUR',
            'is_active' => true,
        ];
    }
}
