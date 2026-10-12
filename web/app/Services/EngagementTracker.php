<?php

namespace App\Services;

use App\Models\ContentView;
use Illuminate\Database\Eloquent\Model;

/**
 * Records views / listens / watch time / downloads for analytics.
 * Views are de-duplicated per session so refreshes don't inflate counts.
 */
class EngagementTracker
{
    public function view(Model $model, string $counterColumn = 'views_count'): void
    {
        $key = 'viewed.'.$model->getMorphClass().'.'.$model->getKey();
        if (session()->has($key)) {
            return;
        }
        session()->put($key, true);

        $this->record($model, 'view');
        if (array_key_exists($counterColumn, $model->getAttributes())) {
            $model->newQuery()->whereKey($model->getKey())->increment($counterColumn);
        }
    }

    public function listen(Model $model, int $seconds, string $action = 'listen'): void
    {
        $this->record($model, $action, min($seconds, 6 * 3600));

        $key = 'listened.'.$model->getMorphClass().'.'.$model->getKey();
        if (! session()->has($key) && array_key_exists('listens_count', $model->getAttributes())) {
            session()->put($key, true);
            $model->newQuery()->whereKey($model->getKey())->increment('listens_count');
        }
    }

    public function download(Model $model): void
    {
        $this->record($model, 'download');
        if (array_key_exists('downloads_count', $model->getAttributes())) {
            $model->newQuery()->whereKey($model->getKey())->increment('downloads_count');
        }
    }

    protected function record(Model $model, string $action, int $seconds = 0): void
    {
        ContentView::create([
            'church_id' => $model->church_id,
            'viewable_type' => $model->getMorphClass(),
            'viewable_id' => $model->getKey(),
            'user_id' => auth()->id(),
            'action' => $action,
            'seconds' => $seconds,
        ]);
    }
}
