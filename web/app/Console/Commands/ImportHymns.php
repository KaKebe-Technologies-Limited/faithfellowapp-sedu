<?php

namespace App\Console\Commands;

use App\Services\HymnImporter;
use Illuminate\Console\Command;
use InvalidArgumentException;
use JsonException;

class ImportHymns extends Command
{
    protected $signature = 'hymns:import {file : The JSON file of the hymnal} {--hidden : Keep a new hymnal hidden from the app until you publish it}';

    protected $description = 'Load a whole hymnal (a hymn book converted to JSON) with its parts, hymns and credits';

    public function handle(HymnImporter $importer): int
    {
        $path = $this->argument('file');
        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $result = $importer->import($data, publish: ! $this->option('hidden'));
        } catch (JsonException|InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("“{$result['hymnal']->name}”: {$result['created']} added, {$result['updated']} updated, {$result['skipped']} skipped (no title or words).");
        $this->line($result['hymnal']->is_published ? 'The hymnal is showing in the app.' : 'The hymnal is hidden from the app: publish it from Super admin, Hymn book.');

        return self::SUCCESS;
    }
}
