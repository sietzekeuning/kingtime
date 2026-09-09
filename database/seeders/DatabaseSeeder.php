<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed a demo account with sample clients, projects and time entries,
     * so a fresh install has something to look at.
     */
    public function run(): void
    {
        $this->call(DemoSeeder::class);
    }
}
