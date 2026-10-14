<?php

namespace App\Services;

use App\Models\Donation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Branded PDF receipts (FaithFellows + church logo).
 */
class ReceiptService
{
    public function pdf(Donation $donation): \Barryvdh\DomPDF\PDF
    {
        $donation->loadMissing(['church.media', 'user', 'campaign']);

        return Pdf::loadView('receipts.donation', [
            'donation' => $donation,
            'church' => $donation->church,
            'churchLogo' => $this->imageData($donation->church->getFirstMedia('logo')?->getPath()),
            'ffLogo' => $this->imageData(public_path('images/icon-192.png')),
        ])->setPaper('a5');
    }

    /** Render once and keep on the private disk. */
    public function path(Donation $donation): string
    {
        $relative = 'receipts/'.$donation->church_id.'/'.$donation->receipt_number.'.pdf';

        if (! Storage::disk('local')->exists($relative)) {
            Storage::disk('local')->put($relative, $this->pdf($donation)->output());
        }

        return Storage::disk('local')->path($relative);
    }

    public function filename(Donation $donation): string
    {
        return 'Receipt-'.$donation->receipt_number.'.pdf';
    }

    /** Embed images as data URIs so DomPDF never fetches remote files. */
    protected function imageData(?string $path): ?string
    {
        if (! $path || ! is_file($path)) {
            return null;
        }
        $mime = mime_content_type($path) ?: 'image/png';
        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/gif'], true)) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
    }
}
