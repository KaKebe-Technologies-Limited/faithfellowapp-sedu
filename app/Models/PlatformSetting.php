<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Simple key/value store for platform-wide settings managed by super admins
 * (gateway toggles & keys, global announcement banner…). Secrets are encrypted.
 */
class PlatformSetting extends Model
{
    protected $fillable = ['key', 'value', 'encrypted'];

    protected function casts(): array
    {
        return ['encrypted' => 'boolean'];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::rememberForever('platform_settings', fn () => static::all()->mapWithKeys(fn ($s) => [
            $s->key => $s->encrypted && $s->value ? rescue(fn () => Crypt::decryptString($s->value), null, false) : $s->value,
        ])->all());

        return $all[$key] ?? $default;
    }

    public static function put(string $key, mixed $value, bool $encrypted = false): void
    {
        static::updateOrCreate(['key' => $key], [
            'value' => $encrypted && $value !== null && $value !== '' ? Crypt::encryptString((string) $value) : $value,
            'encrypted' => $encrypted,
        ]);
        Cache::forget('platform_settings');
    }
}
