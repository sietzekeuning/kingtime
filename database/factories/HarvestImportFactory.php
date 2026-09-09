<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Harvest\Enums\HarvestImportStatus;
use App\Domain\Harvest\Models\HarvestImport;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HarvestImport> */
class HarvestImportFactory extends Factory
{
    protected $model = HarvestImport::class;

    public function definition(): array
    {
        $startedAt = fake()->dateTimeBetween('-30 days', '-1 hour');

        return [
            'user_id' => User::factory(),
            'status' => HarvestImportStatus::Finished,
            'started_at' => $startedAt,
            'finished_at' => (clone $startedAt)->modify('+2 minutes'),
            'updated_since' => null,
            'counts' => [
                'users' => ['created' => 0, 'updated' => 1],
                'clients' => ['created' => 2, 'updated' => 0],
                'projects' => ['created' => 3, 'updated' => 0],
                'time_entries' => ['created' => 25, 'updated' => 0],
            ],
            'error' => null,
        ];
    }

    public function failed(string $error = 'Harvest API GET /clients failed with status 401.'): static
    {
        return $this->state(['status' => HarvestImportStatus::Failed, 'counts' => null, 'error' => $error]);
    }

    public function running(): static
    {
        return $this->state(['status' => HarvestImportStatus::Running, 'finished_at' => null, 'counts' => null]);
    }
}
