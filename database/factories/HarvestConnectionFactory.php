<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Harvest\Models\HarvestConnection;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HarvestConnection>
 */
class HarvestConnectionFactory extends Factory
{
    protected $model = HarvestConnection::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_id' => '12345',
            'access_token' => 'test-token',
            'harvest_user_id' => null,
            'account_name' => null,
            'account_email' => null,
        ];
    }
}
