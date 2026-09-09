<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Client\Models\Client;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Client> */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            // The authenticated user owns what a test creates; without one a
            // fresh user does.
            'user_id' => fn () => auth()->id() ?? User::factory()->create()->id,
            'name' => fake()->company(),
            'email' => fake()->companyEmail(),
            'address' => fake()->address(),
            'currency' => 'EUR',
            'is_active' => true,
        ];
    }
}
