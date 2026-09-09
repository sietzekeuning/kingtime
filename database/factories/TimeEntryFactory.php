<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Project\Models\Project;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TimeEntry> */
class TimeEntryFactory extends Factory
{
    protected $model = TimeEntry::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => User::factory(),
            'spent_on' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'hours' => fake()->randomElement(['0.50', '1.00', '1.50', '2.00', '3.25', '4.00']),
            'notes' => fake()->sentence(),
            'is_billable' => true,
            'is_billed' => false,
            'is_locked' => false,
            'is_running' => false,
            'hourly_rate' => '95.00',
        ];
    }

    public function running(): static
    {
        return $this->state(['is_running' => true, 'timer_started_at' => now()->subMinutes(30), 'hours' => '0.00']);
    }

    public function billed(): static
    {
        return $this->state(['is_billed' => true, 'is_locked' => true]);
    }
}
