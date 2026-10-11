<?php

namespace App\Services;

use App\Models\Church;
use App\Models\Sermon;
use App\Models\SermonSeries;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SermonService
{
    public function __construct(protected ContentPublisher $publisher) {}

    public function save(Church $church, Request $request, ?Sermon $sermon = null, ?User $user = null): Sermon
    {
        $data = $request->validated();

        $sermon = DB::transaction(function () use ($church, $request, $data, $sermon, $user) {
            if (! empty($data['new_series'])) {
                $data['sermon_series_id'] = SermonSeries::create(['church_id' => $church->id, 'title' => $data['new_series']])->id;
            }

            $attributes = Sermon::resolvePublishing(collect($data)->only([
                'title', 'preacher', 'scripture', 'description', 'sermon_series_id', 'branch_id', 'language',
                'preached_on', 'audio_url', 'video_url', 'status', 'visibility', 'published_at',
            ])->all());
            $attributes['description'] = isset($attributes['description']) ? ff_safe_html($attributes['description']) : null;
            $attributes['duration_seconds'] = ! empty($data['duration_minutes']) ? $data['duration_minutes'] * 60 : ($sermon?->duration_seconds);

            if ($sermon) {
                // notified_at is kept, so subscribers are only ever notified once per sermon.
                $sermon->update($attributes);
            } else {
                $sermon = $church->sermons()->create([...$attributes, 'user_id' => $user?->id]);
            }

            foreach (['audio', 'video', 'notes'] as $collection) {
                if ($request->boolean('remove_'.$collection)) {
                    $sermon->clearMediaCollection($collection);
                }
            }
            foreach (['audio', 'video', 'notes', 'thumbnail'] as $collection) {
                if ($request->hasFile($collection)) {
                    $sermon->addMediaFromRequest($collection)->toMediaCollection($collection);
                }
            }

            if ($request->filled('transcript')) {
                $sermon->transcripts()->updateOrCreate(
                    ['language' => $sermon->language],
                    ['church_id' => $church->id, 'body' => strip_tags($request->input('transcript')), 'source' => 'manual', 'status' => 'completed'],
                );
            }

            return $sermon;
        });

        $this->publisher->announceIfLive($sermon->fresh(['church']));

        return $sermon;
    }
}
