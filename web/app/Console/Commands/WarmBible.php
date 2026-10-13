<?php

namespace App\Console\Commands;

use App\Models\BibleChapter;
use App\Services\Bible\BibleService;
use App\Services\Bible\BibleUnavailable;
use App\Support\Bible\Books;
use Illuminate\Console\Command;

/**
 * Loads a whole translation into the chapter cache, so members who download the Bible for offline reading get it
 * instantly instead of waiting for the Bible provider (the free provider allows about 15 requests every 30 seconds).
 * Run it once per translation, e.g. `php artisan bible:warm kjv` (about 45 minutes), and again to continue after a stop.
 */
class WarmBible extends Command
{
    protected $signature = 'bible:warm {translation=kjv : kjv, web, bbe, asv…} {--book=* : Only these book codes (JHN, PSA…)} {--delay=2.5 : Seconds to wait after each request}';

    protected $description = 'Fetch every chapter of a Bible translation into the local cache';

    public function handle(BibleService $bible): int
    {
        $translation = (string) $this->argument('translation');
        if (! array_key_exists($translation, $bible->translations())) {
            $this->error("Unknown or unavailable translation “{$translation}”. Available: ".implode(', ', array_keys($bible->translations())));

            return self::FAILURE;
        }

        $only = array_map('strtoupper', (array) $this->option('book'));
        $books = collect(Books::ALL)->keys()->when($only !== [], fn ($c) => $c->filter(fn ($code) => in_array($code, $only, true)))->values();
        if ($books->isEmpty()) {
            $this->error('No such books.');

            return self::FAILURE;
        }

        $delay = (int) round(((float) $this->option('delay')) * 1_000_000);
        $fetched = 0;
        $failed = 0;

        foreach ($books as $code) {
            $have = BibleChapter::where('translation', $translation)->where('book', $code)->pluck('chapter')->all();
            $todo = array_values(array_diff(range(1, Books::chapters($code)), $have));
            $this->line(sprintf('%s: %d of %d chapters to fetch', Books::name($code), count($todo), Books::chapters($code)));

            foreach ($todo as $chapter) {
                for ($attempt = 1; $attempt <= 4; $attempt++) {
                    try {
                        $bible->chapter($translation, $code, $chapter);
                        $fetched++;
                        usleep($delay);

                        continue 2;
                    } catch (BibleUnavailable $e) {
                        $this->warn("  {$code} {$chapter}: ".$e->getMessage().' (waiting 30 s)');
                        sleep(30);
                    }
                }
                $failed++;
                $this->error("  {$code} {$chapter}: giving up for now");
            }
        }

        $this->info("Done. Fetched {$fetched} chapters".($failed ? ", {$failed} could not be fetched (run it again)." : '.'));

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
