<?php

namespace App\Support;

use App\Models\PlatformSetting;

/**
 * The flash of rolling photos the app shows each time it is opened (the platform admin sets it in Super admin → Settings): whether it shows, how many
 * seconds it stays, how fast the photos roll, and the two lines of text. The app reads it from GET /api/v1/app/opening and keeps the last answer for the
 * next time it opens.
 */
class OpeningFlash
{
    public const SECONDS = [2, 20];

    public const SPEED = [1, 10];

    /** @return array{enabled: bool, seconds: int, speed: int, title: string, text: string} */
    public static function settings(): array
    {
        $get = fn (string $key) => rescue(fn () => PlatformSetting::get($key), null, false);
        $between = fn (mixed $value, array $range, int $default): int => is_numeric($value) ? min($range[1], max($range[0], (int) $value)) : $default;

        return [
            'enabled' => (string) ($get('opening_flash_enabled') ?? '1') !== '0',
            'seconds' => $between($get('opening_flash_seconds'), self::SECONDS, 6),
            'speed' => $between($get('opening_flash_speed'), self::SPEED, 5),
            'title' => trim((string) $get('opening_flash_title')) ?: __('Stay Connected in Faith'),
            'text' => trim((string) $get('opening_flash_text')) ?: __('Worship, pray, learn and grow together with your church family, wherever you are.'),
        ];
    }
}
