<?php

namespace App\Services;

use App\Models\PlatformSetting;

/**
 * "Continue with Google": the OAuth client the super admin saves on the website (Super admin → Google sign-in) instead of in a
 * server file. The client secret is stored encrypted and never shown again. When both are saved they replace the GOOGLE_* lines
 * of the server's .env.
 */
class GoogleSettings
{
    /** @return array{client_id: string, has_secret: bool, redirect_uri: string, on: bool} */
    public static function values(): array
    {
        $id = (string) (PlatformSetting::get('google.client_id') ?: ((array) config('services.google.client_ids'))[0] ?? '');

        return [
            'client_id' => $id,
            'has_secret' => filled(PlatformSetting::get('google.client_secret')) || filled(config('services.google.client_secret')),
            'redirect_uri' => (string) (config('services.google.redirect') ?: route('auth.google.app.callback')),
            'on' => filled($id) && (filled(PlatformSetting::get('google.client_secret')) || filled(config('services.google.client_secret'))),
        ];
    }

    /** Points the sign-in code at the saved client (does nothing until a client ID is saved). */
    public static function apply(): void
    {
        $id = trim((string) PlatformSetting::get('google.client_id'));
        if ($id !== '') {
            config(['services.google.client_ids' => [$id]]);
        }

        $secret = (string) PlatformSetting::get('google.client_secret');
        if ($secret !== '') {
            config(['services.google.client_secret' => $secret]);
        }
    }

    /**
     * Saves the client. A blank secret keeps the one already saved.
     *
     * @param  array{client_id: string, client_secret?: ?string}  $data
     */
    public static function save(array $data): void
    {
        PlatformSetting::put('google.client_id', trim($data['client_id']));
        if (filled($data['client_secret'] ?? null)) {
            PlatformSetting::put('google.client_secret', trim((string) $data['client_secret']), true);
        }
    }
}
