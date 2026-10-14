<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Database changes that came with an update but have not been applied yet. On hosting without a terminal the super admin applies them
 * from Super admin, Database updates (the scheduler also does it when FF_AUTO_MIGRATE is on).
 */
class DatabaseUpdates
{
    /** @return list<string> names of the migrations that have not run */
    public static function pending(): array
    {
        try {
            $migrator = app('migrator');
            $files = $migrator->getMigrationFiles(array_merge($migrator->paths(), [database_path('migrations')]));
            $ran = $migrator->repositoryExists() ? $migrator->getRepository()->getRan() : [];

            return array_values(array_diff(array_keys($files), $ran));
        } catch (Throwable) {
            return [];
        }
    }

    /** Applies every pending change and returns what Laravel printed. */
    public static function apply(): string
    {
        Artisan::call('migrate', ['--force' => true]);

        return trim(Artisan::output());
    }
}
