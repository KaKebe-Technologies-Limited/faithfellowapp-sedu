<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
| Feature tests run against the MySQL "faithfellows_test" database
| (see phpunit.xml) and are refreshed between tests.
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function superAdmin(): User
{
    return tap(User::factory()->create(), fn (User $u) => $u->assignRole('super_admin'));
}

function member(array $attributes = []): User
{
    return tap(User::factory()->create($attributes), fn (User $u) => $u->assignRole('member'));
}
