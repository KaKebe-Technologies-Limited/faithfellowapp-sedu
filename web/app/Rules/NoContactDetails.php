<?php

namespace App\Rules;

use App\Support\ContactGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Refuses text that carries a phone number, email address or chat link (see ContactGuard). */
class NoContactDetails implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && ContactGuard::found($value)) {
            $fail(ContactGuard::message());
        }
    }
}
