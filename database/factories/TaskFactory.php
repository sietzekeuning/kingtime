<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Project\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Task> */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Development', 'Design', 'Meeting', 'Support', 'Project management']),
            'is_billable_by_default' => true,
            'default_hourly_rate' => null,
            'is_active' => true,
        ];
    }
}
