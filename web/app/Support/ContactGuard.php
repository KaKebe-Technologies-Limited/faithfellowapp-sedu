<?php

namespace App\Support;

/**
 * Keeps conversations inside the app: spots phone numbers, emails and links that open a chat on another app
 * (WhatsApp, Telegram…) in text that members write to each other. Used by the chat, connection messages, study
 * group posts, comments and profile bios. Church admins see members' contacts in their own admin area instead.
 */
final class ContactGuard
{
    private const DIGIT_WORDS = ['zero', 'oh', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine'];

    /** True when the text carries a phone number, email address or messenger link. */
    public static function found(?string $text): bool
    {
        $text = trim((string) $text);
        if ($text === '') {
            return false;
        }
        $plain = self::asciiDigits(mb_strtolower($text));

        return self::hasEmail($plain) || self::hasMessengerLink($plain) || self::hasPhoneNumber($plain) || self::hasSpelledNumber($plain);
    }

    public static function message(): string
    {
        return __('For everyone’s safety, phone numbers, emails and chat links can’t be shared here. Keep talking in the app.');
    }

    private static function hasEmail(string $text): bool
    {
        return (bool) preg_match('/[a-z0-9._%+\-]+\s?(@|\[at\]|\(at\))\s?[a-z0-9\-]+(\.|\s?\[dot\]\s?|\s?\(dot\)\s?)[a-z]{2,}/i', $text);
    }

    private static function hasMessengerLink(string $text): bool
    {
        return (bool) preg_match('/(wa\.me|api\.whatsapp|chat\.whatsapp|whatsapp\.com\/send|t\.me\/|telegram\.me|signal\.me|m\.me\/)/i', $text);
    }

    private static function hasPhoneNumber(string $text): bool
    {
        // Dates (13-10-2026, 2026/10/13) and times are not phone numbers.
        $text = preg_replace(['/\b\d{1,4}\s?[\/.\-]\s?\d{1,2}\s?[\/.\-]\s?\d{1,4}\b/', '/\b\d{1,2}:\d{2}(:\d{2})?\b/'], ' ', $text) ?? $text;

        // +256 772 123 456, +1 (555) 123-4567
        if (preg_match('/\+\s?\d[\d\s().\-]{6,}\d/', $text)) {
            return true;
        }

        // 0772123456, 0772 123 456, 077-212-3456, 0 7 7 2 1 2 3 4 5 6: seven or more digits with only spaces, dots, dashes or brackets between.
        return (bool) preg_match('/(?<!\d)(?:\d[\s.\-_()]{0,2}){7,}(?!\d)/', $text);
    }

    /** "zero seven seven two one two three four five six" */
    private static function hasSpelledNumber(string $text): bool
    {
        $words = implode('|', self::DIGIT_WORDS);

        return (bool) preg_match('/\b(?:('.$words.')[\s,.\-]+){6,}('.$words.')\b/', $text);
    }

    /** Arabic-Indic and full-width digits written as plain digits, so they cannot be used to slip a number through. */
    private static function asciiDigits(string $text): string
    {
        return strtr($text, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '０' => '0', '１' => '1', '２' => '2', '３' => '3', '４' => '4', '５' => '5', '６' => '6', '７' => '7', '８' => '8', '９' => '9',
        ]);
    }
}
