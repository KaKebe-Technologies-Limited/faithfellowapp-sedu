<?php

namespace App\Console\Commands;

use App\Services\RadioService;
use Illuminate\Console\Command;

/** Adds song files to the gospel radio from the command line (the super admin page does the same through the browser). */
class AddRadioTrack extends Command
{
    protected $signature = 'radio:add {files* : Paths of MP3, M4A, AAC or WAV files} {--title= : Only when adding one file} {--artist= : Only when adding one file}';

    protected $description = 'Add songs to the FaithFellows gospel radio';

    public function handle(RadioService $radio): int
    {
        $status = self::SUCCESS;
        foreach ($this->argument('files') as $file) {
            if (! is_file($file)) {
                $this->error("Not found: {$file}");
                $status = self::FAILURE;

                continue;
            }
            try {
                $one = count($this->argument('files')) === 1;
                $track = $radio->addFile($file, basename($file), null, $one ? $this->option('title') : null, $one ? $this->option('artist') : null);
                $this->info(sprintf('Added “%s”%s — %s, position %d', $track->title, $track->artist ? " by {$track->artist}" : '', gmdate('i:s', $track->duration_seconds), $track->position));
            } catch (\InvalidArgumentException $e) {
                $this->error(basename($file).': '.$e->getMessage());
                $status = self::FAILURE;
            }
        }

        return $status;
    }
}
