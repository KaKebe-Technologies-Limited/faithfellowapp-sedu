<?php

namespace App\Services\Ai;

use App\Models\Sermon;

/**
 * Sermon AI — Phase 2 stub.
 *
 * Planned: speech-to-text (English, Luo, Luganda, Swahili), translation,
 * summaries, key verses, discussion questions and short social clips.
 * Plug a provider in by returning data from transcribe(); until then the
 * job marks transcripts as "unavailable" and manual transcripts still work.
 */
class TranscriptionService
{
    public function provider(): ?string
    {
        return config('services.sermon_ai.provider');
    }

    public function enabled(): bool
    {
        return (bool) $this->provider();
    }

    /**
     * @return array{body: string, summary?: string, key_verses?: array<int, string>, discussion_questions?: array<int, string>}|null
     */
    public function transcribe(Sermon $sermon): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        // Provider integration goes here (e.g. Whisper-compatible API).
        return null;
    }
}
