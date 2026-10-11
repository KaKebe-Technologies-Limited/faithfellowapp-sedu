<?php

namespace App\Models\Concerns;

use App\Support\Audit;

/**
 * Automatically records create / update / delete of a model to audit_logs
 * whenever the change is made by an authenticated user over HTTP.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        $shouldLog = fn () => auth()->check() && ! app()->runningInConsole();
        $name = fn ($model) => class_basename($model);

        static::created(function ($model) use ($shouldLog, $name) {
            if ($shouldLog()) {
                Audit::log(strtolower($name($model)).'.created', $model, $model->auditLabel(), [], $model->getAttributes());
            }
        });

        static::updated(function ($model) use ($shouldLog, $name) {
            if (! $shouldLog()) {
                return;
            }
            $changes = collect($model->getChanges())->except(['updated_at'])->all();
            if ($changes === []) {
                return;
            }
            $old = collect($model->getOriginal())->only(array_keys($changes))->all();
            Audit::log(strtolower($name($model)).'.updated', $model, $model->auditLabel(), $old, $changes);
        });

        static::deleted(function ($model) use ($shouldLog, $name) {
            if ($shouldLog()) {
                Audit::log(strtolower($name($model)).'.deleted', $model, $model->auditLabel());
            }
        });
    }

    public function auditLabel(): ?string
    {
        return $this->title ?? $this->name ?? null;
    }
}
