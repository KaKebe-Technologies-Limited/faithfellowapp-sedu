<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Mail;

/**
 * The email account FaithFellows sends from (receipts, thank-you notes to givers, password codes), set up by the super admin
 * on the website instead of in a server file. It is meant for a Gmail account with an App Password; the password is stored
 * encrypted and never shown again. When it is switched on, it replaces the mail settings of the server's .env for sending.
 */
class MailSettings
{
    public const DEFAULTS = [
        'host' => 'smtp.gmail.com',
        'port' => '587',
        'username' => 'faithfellowsofficial@gmail.com',
        'from_address' => 'faithfellowsofficial@gmail.com',
        'from_name' => 'FaithFellows',
    ];

    /** @return array{enabled: bool, host: string, port: string, username: string, from_address: string, from_name: string, has_password: bool} */
    public static function values(): array
    {
        $get = fn (string $key) => PlatformSetting::get("mail.{$key}", self::DEFAULTS[$key] ?? null);

        return [
            'enabled' => PlatformSetting::get('mail.enabled') === '1',
            'host' => (string) $get('host'),
            'port' => (string) $get('port'),
            'username' => (string) $get('username'),
            'from_address' => (string) $get('from_address'),
            'from_name' => (string) $get('from_name'),
            'has_password' => filled(PlatformSetting::get('mail.password')),
        ];
    }

    /** Whether sending through the account saved on the website is switched on and complete. */
    public static function active(): bool
    {
        $v = self::values();

        return $v['enabled'] && $v['has_password'] && filled($v['username']) && filled($v['host']);
    }

    /** Points Laravel's mailer at the saved account (does nothing when it is off, so the server's .env stays in charge). */
    public static function apply(): void
    {
        if (! self::active()) {
            return;
        }

        $v = self::values();
        $port = (int) ($v['port'] ?: 587);

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $v['host'],
            'mail.mailers.smtp.port' => $port,
            'mail.mailers.smtp.scheme' => $port === 465 ? 'smtps' : 'smtp',
            'mail.mailers.smtp.url' => null,
            'mail.mailers.smtp.username' => $v['username'],
            'mail.mailers.smtp.password' => (string) PlatformSetting::get('mail.password'),
            'mail.from.address' => $v['from_address'] ?: $v['username'],
            'mail.from.name' => $v['from_name'] ?: config('app.name'),
        ]);

        // A mailer that was already built (earlier in this request or queue run) must be rebuilt with the new settings.
        app('mail.manager')->forgetMailers();
    }

    /**
     * Saves the settings. A blank password keeps the one already saved.
     *
     * @param  array<string, mixed>  $data
     */
    public static function save(array $data): void
    {
        PlatformSetting::put('mail.enabled', ! empty($data['enabled']) ? '1' : '0');
        foreach (['host', 'port', 'username', 'from_address', 'from_name'] as $key) {
            PlatformSetting::put("mail.{$key}", trim((string) ($data[$key] ?? '')));
        }
        if (filled($data['password'] ?? null)) {
            // Google shows an App Password in groups of four letters; the spaces are not part of it.
            PlatformSetting::put('mail.password', preg_replace('/\s+/', '', (string) $data['password']), true);
        }
    }

    /** Sends a short test email through the saved account; returns null when it went out, otherwise what went wrong. */
    public static function sendTest(string $to): ?string
    {
        self::apply();

        try {
            Mail::raw(__("This is a test email from FaithFellows.\n\nIf you can read it, receipts and thank-you notes to givers will arrive from this address."), function ($message) use ($to) {
                $message->to($to)->subject(__('FaithFellows test email'));
            });
        } catch (\Throwable $e) {
            return self::explain($e);
        }

        return null;
    }

    /** A plain-words reason for a failed send (never includes the password). */
    protected static function explain(\Throwable $e): string
    {
        $text = $e->getMessage();

        return match (true) {
            str_contains($text, '535') || stripos($text, 'Username and Password not accepted') !== false => __('Google refused the sign-in. Check that the Gmail address is right and that the App Password is the 16-letter one made for this account (2-Step Verification must be on).'),
            stripos($text, 'Connection could not be established') !== false || stripos($text, 'timed out') !== false => __('The server could not reach the mail host. Check the host and port (Gmail: smtp.gmail.com, 587).'),
            default => __('The email could not be sent: :reason', ['reason' => mb_substr(preg_replace('/\s+/', ' ', $text), 0, 220)]),
        };
    }
}
