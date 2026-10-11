<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Demo data for local development and pilot demos.
 * Each sprint extends this with its own sample records.
 */
class DemoSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'password';

    public function run(): void
    {
        $member = User::updateOrCreate(['email' => 'member@faithfellows.test'], [
            'name' => 'Grace Akello',
            'password' => self::DEMO_PASSWORD,
            'phone' => '0772000111',
            'city' => 'Lira',
            'email_verified_at' => now(),
            'terms_accepted_at' => now(),
            'email_consent' => true,
            'directory_opt_in' => true,
        ]);
        $member->assignRole(Role::findOrCreate('member', 'web'));

        User::factory(20)->create()->each(fn (User $u) => $u->assignRole('member'));
    }
}
