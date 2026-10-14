<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            PlanSeeder::class,
            SuperAdminSeeder::class,
            BlogSeeder::class,
            HymnSeeder::class,
        ]);

        // Demo data (churches, members, content, giving) for local / staging.
        if (! app()->isProduction()) {
            $this->call(DemoSeeder::class);
        }
    }
}
