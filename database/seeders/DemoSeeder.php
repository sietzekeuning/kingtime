<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Client\Models\Client;
use App\Domain\Project\Models\Project;
use App\Domain\Time\Models\TimeEntry;
use App\Domain\User\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Demo data for a fresh install: one user (demo@kingtime.test / password),
 * three clients with projects, and six months of time entries.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@kingtime.test',
        ]);

        $projects = collect([
            ['Acme Corporation', 'Website redesign', 'ACME', '#F97316', '95.00'],
            ['Acme Corporation', 'Support retainer', 'ACME-S', '#F59E0B', '85.00'],
            ['Globex', 'Mobile app', 'GLX', '#3B82F6', '110.00'],
            ['Initech', 'TPS report automation', 'INT', '#10B981', '100.00'],
        ])->map(function (array $row): Project {
            [$clientName, $name, $code, $color, $rate] = $row;

            $client = Client::query()->firstOrCreate(['name' => $clientName], [
                'email' => fake()->companyEmail(),
                'address' => fake()->address(),
                'currency' => 'EUR',
                'is_active' => true,
            ]);

            return Project::factory()->create([
                'client_id' => $client->id,
                'name' => $name,
                'code' => $code,
                'color' => $color,
                'hourly_rate' => $rate,
            ]);
        });

        $day = Carbon::now()->subMonths(6)->startOfWeek();

        while ($day->lessThanOrEqualTo(Carbon::now())) {
            if ($day->isWeekday() && fake()->boolean(85)) {
                $count = fake()->numberBetween(1, 3);

                for ($i = 0; $i < $count; $i++) {
                    /** @var Project $project */
                    $project = $projects->random();

                    TimeEntry::factory()->create([
                        'user_id' => $user->id,
                        'project_id' => $project->id,
                        'spent_on' => $day->toDateString(),
                        'hours' => fake()->randomElement(['1.00', '1.50', '2.00', '2.50', '3.00', '4.00']),
                        'hourly_rate' => $project->hourly_rate,
                        'is_billable' => fake()->boolean(90),
                        'is_billed' => $day->lessThan(Carbon::now()->startOfMonth()->subMonth()),
                        'is_locked' => $day->lessThan(Carbon::now()->startOfMonth()->subMonth()),
                    ]);
                }
            }

            $day = $day->addDay();
        }
    }
}
