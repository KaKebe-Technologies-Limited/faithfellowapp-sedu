<?php

namespace Tests;

use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // A test never reaches the real internet: it fakes the service it needs.
        Http::preventStrayRequests();
        $this->withoutVite();
        $this->seed(RolesSeeder::class);
    }
}
