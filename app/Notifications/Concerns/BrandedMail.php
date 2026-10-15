<?php

namespace App\Notifications\Concerns;

use Illuminate\Notifications\Messages\MailMessage;

/** A FaithFellows-branded email (the wine header, the details box, one button) for notices the platform sends to people. */
trait BrandedMail
{
    /**
     * @param  array<int, string>  $lines  the paragraphs
     * @param  array<string, string>  $details  label => value, shown in a soft box
     * @param  array{label: string, url: string}|null  $button
     */
    protected function branded(string $subject, string $title, array $lines, array $details = [], ?array $button = null, ?string $note = null): MailMessage
    {
        return (new MailMessage)->subject($subject)->view(['emails.notice', 'emails.notice-text'], compact('title', 'lines', 'details', 'button', 'note'));
    }
}
