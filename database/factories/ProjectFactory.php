<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Client\Models\Client;
use App\Domain\Project\Enums\BillBy;
use App\Domain\Project\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Project> */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'name' => rtrim(fake()->sentence(2), '.'),
            'code' => strtoupper(fake()->lexify('???')),
            'is_billable' => true,
            'bill_by' => BillBy::Project,
            'hourly_rate' => '95.00',
            'is_active' => true,
            'color' => fake()->randomElement(['#F97316', '#F59E0B', '#EF4444', '#10B981', '#3B82F6']),
        ];
    }

    public function nonBillable(): static
    {
        return $this->state(['is_billable' => false, 'bill_by' => BillBy::None, 'hourly_rate' => null]);
    }
}
