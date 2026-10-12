<?php

namespace App\Rules;

use App\Models\Church;
use App\Support\StorageQuota;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Rejects uploads that would push a church past its plan's storage limit.
 */
class WithinStorageQuota implements ValidationRule
{
    public function __construct(protected Church $church) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $files = is_array($value) ? $value : [$value];
        $bytes = collect($files)->filter(fn ($f) => $f instanceof UploadedFile)->sum(fn (UploadedFile $f) => $f->getSize());

        if ($bytes > 0 && ! StorageQuota::canStore($this->church, $bytes)) {
            $fail(__('This upload exceeds your church’s storage quota (:used of :limit MB used). Remove old media or upgrade your plan.', [
                'used' => StorageQuota::usedMb($this->church),
                'limit' => StorageQuota::limitMb($this->church),
            ]));
        }
    }
}
