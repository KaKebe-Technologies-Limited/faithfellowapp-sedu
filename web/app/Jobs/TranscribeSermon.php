<?php

namespace App\Jobs;

use App\Models\Sermon;
use App\Services\Ai\TranscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sermon AI (Phase 2): transcribe → notes, key verses, discussion questions.
 */
class TranscribeSermon implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public function __construct(public int $sermonId) {}

    public function handle(TranscriptionService $ai): void
    {
        $sermon = Sermon::with('media')->find($this->sermonId);
        if (! $sermon) {
            return;
        }

        $transcript = $sermon->transcripts()->firstOrCreate(['language' => $sermon->language], ['church_id' => $sermon->church_id, 'source' => 'ai']);
        $transcript->update(['status' => 'processing']);

        $result = $ai->transcribe($sermon);

        $transcript->update($result === null
            ? ['status' => 'unavailable', 'error' => __('Sermon AI is not enabled yet on this platform.')]
            : [...$result, 'status' => 'completed', 'source' => 'ai', 'provider' => $ai->provider()]);
    }
}
