<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Moneybird\Models\MoneybirdConnection;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MoneybirdConnection>
 */
class MoneybirdConnectionFactory extends Factory
{
    protected $model = MoneybirdConnection::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'access_token' => 'secret-token',
            'administration_id' => '123456789',
            'administration_name' => null,
            'tax_rate_id' => null,
            'ledger_account_id' => null,
            'workflow_id' => null,
        ];
    }
}
