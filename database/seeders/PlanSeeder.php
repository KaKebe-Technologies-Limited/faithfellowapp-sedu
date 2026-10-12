<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::updateOrCreate(['slug' => 'starter'], [
            'name' => 'Starter',
            'price' => config('faithfellows.pricing.plan_price'),
            'currency' => 'UGX',
            'interval' => 'month',
            'storage_mb' => 5120,
            'stream_hours' => 40,
            'max_admins' => 10,
            'max_branches' => 5,
            'features' => config('faithfellows.pricing.includes'),
            'is_active' => true,
            'is_default' => true,
            'sort' => 1,
        ]);

        Plan::updateOrCreate(['slug' => 'growth'], [
            'name' => 'Growth',
            'price' => 150000,
            'currency' => 'UGX',
            'interval' => 'month',
            'storage_mb' => 25600,
            'stream_hours' => 150,
            'max_admins' => 30,
            'max_branches' => 20,
            'features' => ['Everything in Starter', 'Multi-branch networks', '5× storage & stream hours', 'Priority support'],
            'is_active' => true,
            'is_default' => false,
            'sort' => 2,
        ]);
    }
}
