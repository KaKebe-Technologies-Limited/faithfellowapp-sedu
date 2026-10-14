<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SEED_SUPER_ADMIN_EMAIL', 'admin@faithfellows.com');
        $password = env('SEED_SUPER_ADMIN_PASSWORD', 'ChangeMe!2026');

        if (app()->isProduction() && $password === 'ChangeMe!2026') {
            throw new RuntimeException('Set SEED_SUPER_ADMIN_PASSWORD before seeding production.');
        }

        $admin = User::updateOrCreate(['email' => $email], [
            'name' => 'FaithFellows Admin',
            'password' => $password,
            'phone' => config('faithfellows.company.phone'),
            'email_verified_at' => now(),
            'terms_accepted_at' => now(),
            'status' => 'active',
        ]);

        $admin->syncRoles(['super_admin', 'member']);
    }
}
