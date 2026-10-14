<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Church;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Writes admin actions to audit_logs. Sensitive attributes are stripped.
 */
class Audit
{
    private const HIDDEN = ['password', 'remember_token', 'two_factor_code', 'stream_key', 'credentials', 'api_key', 'secret'];

    public static function log(
        string $action,
        ?Model $subject = null,
        ?string $description = null,
        array $old = [],
        array $new = [],
        ?int $churchId = null,
    ): AuditLog {
        $request = app()->runningInConsole() ? null : request();

        $churchId ??= $subject?->getAttribute('church_id')
            ?? ($subject instanceof Church ? $subject->getKey() : null)
            ?? (app()->bound('currentChurch') ? app('currentChurch')?->id : null);

        return AuditLog::create([
            'church_id' => $churchId,
            'user_id' => auth()->id(),
            'impersonator_id' => session('impersonator_id'),
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'description' => $description,
            'old_values' => self::clean($old) ?: null,
            'new_values' => self::clean($new) ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? substr((string) $request->userAgent(), 0, 255) : 'console',
        ]);
    }

    private static function clean(array $values): array
    {
        return Arr::except($values, self::HIDDEN);
    }
}
